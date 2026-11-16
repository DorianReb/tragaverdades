<?php

use App\Services\MotorReglas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.sabert.url' => 'http://127.0.0.1:8001']);
});

function respuestaSaBERT(string $nombre = 'sabert-chunks'): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/'.$nombre.'.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('interpreta el JSON real de FastAPI sin perder reglas ni campos previos', function ($fixture) {
    $api = respuestaSaBERT($fixture);
    Http::fake(['127.0.0.1:8001/analizar' => Http::response($api)]);
    $texto = $api['texto_analizado'];
    $reglas = app(MotorReglas::class)->analizar($texto);
    $response = $this->postJson(route('analisis.ia'), ['texto' => $texto])->assertOk()
        ->assertJson($reglas)
        ->assertJsonPath('sabert.disponible', true)
        ->assertJsonPath('sabert.error', null)
        ->assertJsonPath('sabert.clasificacion', $api['clasificacion'])
        ->assertJsonPath('sabert.probabilidades.True', $api['probabilidades']['True'] / 100)
        ->assertJsonPath('sabert.probabilidades.False', $api['probabilidades']['False'] / 100)
        ->assertJsonCount($api['cantidad_fragmentos'], 'sabert.fragmentos')
        ->assertJsonPath('sabert.mensaje', fn ($mensaje) => str_contains($mensaje, 'no una verificación de hechos'));

    foreach (['modelo', 'confianza_porcentaje', 'dispositivo', 'tokens_procesados', 'texto_truncado', 'advertencia',
        'metodo_agregacion', 'total_tokens_originales', 'cantidad_fragmentos', 'tokens_contenido_procesados',
        'tokens_cubiertos', 'cobertura_porcentaje', 'ventana_tokens', 'solapamiento_tokens'] as $campo) {
        $response->assertJsonPath('sabert.'.$campo, $api[$campo]);
    }
    foreach ($api['fragmentos'] as $i => $fragmento) {
        foreach (['indice', 'inicio_token', 'fin_token', 'tokens_contenido', 'tokens', 'clasificacion', 'confianza_porcentaje'] as $campo) {
            $response->assertJsonPath("sabert.fragmentos.$i.$campo", $fragmento[$campo]);
        }
        foreach (['True', 'False'] as $etiqueta) {
            $response->assertJsonPath("sabert.fragmentos.$i.probabilidades.$etiqueta", $fragmento['probabilidades'][$etiqueta] / 100);
        }
    }
    // Una sola solicitud: FastAPI se encarga del chunking y de los lotes.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/analizar' && $request->data() === ['texto' => $texto]);
})->with(['sabert-single', 'sabert-chunks']);

it('combina SaBERT con las 17 reglas y conserva el contrato anterior', function () {
    config(['services.sabert.endpoint' => '/predict', 'services.sabert.text_field' => 'text']);
    Http::fake(['127.0.0.1:8001/*' => Http::response(['probabilities' => ['True' => 0.15, 'False' => 0.85]])]);
    $texto = 'URGENTE!!! Comparte este remedio secreto que cura todo según fuentes anónimas.';
    $reglas = $this->postJson(route('analisis.reglas'), ['texto' => $texto])->assertOk()->json();
    Http::assertNothingSent();
    $this->postJson(route('analisis.ia'), ['texto' => $texto])->assertOk()
        ->assertJson($reglas)->assertJsonPath('total_reglas', 17)
        ->assertJsonPath('sabert.disponible', true)->assertJsonPath('sabert.clasificacion', 'False')
        ->assertJsonPath('sabert.probabilidades.True', 0.15)->assertJsonPath('sabert.probabilidades.False', 0.85)
        ->assertJsonPath('sabert.cantidad_fragmentos', null)->assertJsonPath('sabert.cobertura_porcentaje', null)
        ->assertJsonPath('sabert.fragmentos', []);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/predict' && $request['text'] === $texto);
});

it('admite la respuesta anterior de FastAPI en porcentajes sin inventar cobertura', function () {
    $api = respuestaSaBERT('sabert-single');
    foreach (['fragmentos', 'metodo_agregacion', 'total_tokens_originales', 'cantidad_fragmentos',
        'tokens_contenido_procesados', 'tokens_cubiertos', 'cobertura_porcentaje', 'ventana_tokens', 'solapamiento_tokens'] as $campo) {
        unset($api[$campo]);
    }
    Http::fake(['*' => Http::response($api)]);
    $this->postJson(route('analisis.ia'), ['texto' => $api['texto_analizado']])->assertOk()
        ->assertJsonPath('sabert.disponible', true)
        ->assertJsonPath('sabert.tokens_procesados', $api['tokens_procesados'])
        ->assertJsonPath('sabert.probabilidades.True', $api['probabilidades']['True'] / 100)
        ->assertJsonPath('sabert.cobertura_porcentaje', null)->assertJsonPath('sabert.fragmentos', []);
});

it('respeta la clasificación remota aunque los porcentajes redondeados empaten', function () {
    Http::fake(['*' => Http::response(['clasificacion' => 'False', 'probabilidades' => ['True' => 50, 'False' => 50]])]);
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('Noticia de prueba. ', 3)])
        ->assertOk()->assertJsonPath('sabert.clasificacion', 'False');
});

it('clasifica True cuando tiene mayor probabilidad', function () {
    Http::fake(['*' => Http::response(['probabilities' => ['True' => 0.9, 'False' => 0.1]])]);
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('Noticia de prueba. ', 3)])
        ->assertOk()->assertJsonPath('sabert.clasificacion', 'True');
});

it('conserva las reglas ante fallos de FastAPI', function ($tipo) {
    Http::fake(fn () => match ($tipo) {
        'conexion' => throw new ConnectionException('Connection refused'),
        'http' => Http::response([], 503),
        'json' => Http::response('invalid', 200),
        'incompleta' => Http::response(['probabilities' => ['True' => 0.8]]),
        'rango' => Http::response(['probabilities' => ['True' => 2, 'False' => -1]]),
        'suma' => Http::response(['probabilities' => ['True' => 0.8, 'False' => 0.8]]),
        'porcentajes_fuera_de_rango' => Http::response(['clasificacion' => 'True', 'probabilidades' => ['True' => 110, 'False' => -10]]),
        'escala_incorrecta' => Http::response(['clasificacion' => 'True', 'probabilidades' => ['True' => 0.8, 'False' => 0.2]]),
        'sin_clasificacion' => Http::response(['probabilidades' => ['True' => 80, 'False' => 20]]),
        'etiqueta_desconocida' => Http::response(['clasificacion' => 'LABEL_0', 'probabilidades' => ['True' => 80, 'False' => 20]]),
    });
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('Noticia de prueba. ', 3)])
        ->assertOk()->assertJsonPath('total_reglas', 17)->assertJsonPath('sabert.disponible', false)
        ->assertJsonPath('sabert.probabilidades', null)->assertJsonStructure(['bfs', 'dfs', 'prueba_hacia_atras']);
})->with(['conexion', 'http', 'json', 'incompleta', 'rango', 'suma', 'porcentajes_fuera_de_rango', 'escala_incorrecta', 'sin_clasificacion', 'etiqueta_desconocida']);

it('informa el límite remoto sin perder los resultados simbólicos', function ($fixture, $estado) {
    Http::fake(['*' => Http::response(respuestaSaBERT($fixture), $estado)]);
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('Noticia de prueba. ', 3)])->assertOk()
        ->assertJsonPath('total_reglas', 17)->assertJsonPath('sabert.disponible', false)
        ->assertJsonPath('sabert.error', 'limite_documento')->assertJsonPath('sabert.probabilidades', null)
        ->assertJsonPath('sabert.fragmentos', [])->assertJsonPath('sabert.cobertura_porcentaje', null)
        ->assertJsonPath('sabert.mensaje', fn ($mensaje) => str_contains($mensaje, 'Reduce el texto'));
})->with([['sabert-limit-tokens', 413], ['sabert-limit-characters', 422]]);

it('distingue un documento inválido de un servicio no disponible', function ($estado, $error) {
    Http::fake(['*' => Http::response(['detail' => 'No disponible'], $estado)]);
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('Noticia de prueba. ', 3)])->assertOk()
        ->assertJsonPath('total_reglas', 17)->assertJsonPath('sabert.error', $error);
})->with([[422, 'documento_invalido'], [503, 'no_disponible'], [500, 'no_disponible']]);

it('rechaza fragmentos o estadísticas inconsistentes sin presentar cobertura falsa', function ($campo, $valor) {
    $api = respuestaSaBERT();
    data_set($api, $campo, $valor);
    Http::fake(['*' => Http::response($api)]);
    $this->postJson(route('analisis.ia'), ['texto' => $api['texto_analizado']])->assertOk()
        ->assertJsonPath('total_reglas', 17)->assertJsonPath('sabert.disponible', false)
        ->assertJsonPath('sabert.error', 'respuesta_invalida')->assertJsonPath('sabert.fragmentos', [])
        ->assertJsonPath('sabert.cobertura_porcentaje', null);
})->with([
    ['fragmentos', null], ['fragmentos', []], ['cantidad_fragmentos', '13'],
    ['fragmentos.0.indice', 1], ['fragmentos.0.clasificacion', 'LABEL_0'],
    ['fragmentos.0.probabilidades.True', 101], ['fragmentos.0.probabilidades.False', -1],
    ['fragmentos.0.probabilidades', ['True' => 80, 'False' => 80]],
    ['fragmentos.1.inicio_token', 200], ['fragmentos.0.tokens', 129],
    ['total_tokens_originales', 0], ['tokens_procesados', 1], ['tokens_contenido_procesados', 1],
    ['tokens_cubiertos', 1], ['cobertura_porcentaje', 99], ['cobertura_porcentaje', 101],
    ['metodo_agregacion.verificacion_factual', true], ['texto_truncado', 'false'],
]);

it('rechaza textos inválidos antes de consultar FastAPI', function ($texto) {
    Http::fake();
    $this->postJson(route('analisis.ia'), ['texto' => $texto])->assertUnprocessable()->assertJsonValidationErrors('texto');
    Http::assertNothingSent();
})->with(['corto', '', str_repeat(' ', 25), str_repeat('a', 100001), null, ['texto']]);

it('comunica el límite local configurado antes de consultar FastAPI', function () {
    config(['services.sabert.max_characters' => 100]);
    Http::fake();
    $this->postJson(route('analisis.ia'), ['texto' => str_repeat('a', 101)])->assertUnprocessable()
        ->assertJsonPath('errors.texto.0', 'El documento supera el límite de 100 caracteres permitido. Reduce el texto e intenta nuevamente.');
    Http::assertNothingSent();
});

it('admite documentos de más de 5000 caracteres sin recortarlos', function () {
    $api = respuestaSaBERT();
    expect(mb_strlen($api['texto_analizado']))->toBeGreaterThan(5000);
    Http::fake(['*' => Http::response($api)]);
    $this->postJson(route('analisis.ia'), ['texto' => $api['texto_analizado']])->assertOk()
        ->assertJsonPath('sabert.disponible', true)->assertJsonCount($api['cantidad_fragmentos'], 'sabert.fragmentos');
    Http::assertSent(fn ($request) => $request['texto'] === $api['texto_analizado']);
});

it('mantiene la ruta anterior de reglas independiente de FastAPI y sus límites', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    $texto = 'URGENTE!!! Comparte este remedio secreto que cura todo según fuentes anónimas.';
    $this->postJson('/analisis-reglas', ['texto' => $texto])->assertOk()
        ->assertExactJson(app(MotorReglas::class)->analizar($texto));
    $this->postJson('/analisis-reglas', ['texto' => str_repeat('a', 5001)])->assertUnprocessable();
    Http::assertNothingSent();
});

it('muestra el analizador con la nueva ruta y ambos resultados', function () {
    $this->get('/')->assertOk()->assertSee(route('analisis.ia'))->assertSee('sabert-true')->assertSee('sabert-false')
        ->assertSee('bfs-estadisticas')->assertSee('sabert-fragmentos')->assertSee('sabert-cobertura')
        ->assertSee('sabert-tokens-procesados')->assertSee('sabert-detalle-fragmentos')
        ->assertSee('no una verificación de hechos')->assertSee('data-max-characters="100000"', false);
});
