<?php

use App\Services\MotorReglas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.sabert.url' => 'http://127.0.0.1:8001',
        'services.sabert.endpoint' => '/analizar',
        'services.sabert.text_field' => 'texto',
        'services.sabert.extraction_endpoint' => '/extraer-url',
        'services.sabert.max_characters' => 100000,
    ]);
});

function noticiaExtraida(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/noticia-extraida.json'), true, 512, JSON_THROW_ON_ERROR);
}

function analisisURLSimulado(array $noticia): void
{
    $analisis = json_decode(file_get_contents(__DIR__.'/../Fixtures/sabert-chunks.json'), true, 512, JSON_THROW_ON_ERROR);
    Http::fake([
        '127.0.0.1:8001/extraer-url' => Http::response($noticia),
        '127.0.0.1:8001/analizar' => Http::response($analisis),
    ]);
}

it('extrae una URL y reutiliza exactamente las reglas y el adaptador de análisis manual', function () {
    $noticia = noticiaExtraida();
    $noticia['contenido'] = json_decode(file_get_contents(__DIR__.'/../Fixtures/sabert-chunks.json'), true)['texto_analizado'];
    analisisURLSimulado($noticia);

    $url = 'https://noticias.example.org/enlace-original';
    $respuesta = $this->postJson(route('analisis.url'), ['url' => $url, 'texto' => 'No analizar este dato del cliente.'])
        ->assertOk()->assertJson(['noticia' => [...$noticia, 'url_original' => $url]])
        ->assertJsonPath('sabert.disponible', true)->assertJsonPath('sabert.cantidad_fragmentos', 13)
        ->assertJsonPath('sabert.ventana_tokens', 128)->assertJsonPath('sabert.solapamiento_tokens', 32)
        ->assertJsonPath('sabert.cobertura_porcentaje', 100)->assertJsonPath('total_reglas', 17)
        ->assertJson(app(MotorReglas::class)->analizar($noticia['contenido']))
        ->assertJsonStructure(['bfs', 'dfs', 'prueba_hacia_atras', 'reglas_activadas']);

    expect(mb_strlen($noticia['contenido']))->toBeGreaterThan(5000);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/extraer-url'
        && $request->method() === 'POST' && $request->data() === ['url' => $url]);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/analizar'
        && $request->data() === ['texto' => $noticia['contenido']]);

    $manual = $this->postJson(route('analisis.ia'), ['texto' => $noticia['contenido']])->assertOk()->json();
    $porURL = $respuesta->json();
    unset($porURL['noticia']);
    expect($porURL)->toBe($manual);
});

it('conserva párrafos y no mezcla los metadatos con el contenido enviado a los motores', function () {
    $noticia = noticiaExtraida();
    analisisURLSimulado($noticia);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertOk()
        ->assertJsonPath('noticia.contenido', $noticia['contenido'])
        ->assertJson(app(MotorReglas::class)->analizar($noticia['contenido']));
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/analizar' && $request['texto'] === $noticia['contenido']);
});

it('mantiene nulos los metadatos ausentes', function ($ausentes) {
    $noticia = noticiaExtraida();
    foreach (['titulo', 'autor', 'fecha_publicacion'] as $campo) {
        if ($ausentes) {
            unset($noticia[$campo]);
        } else {
            $noticia[$campo] = null;
        }
    }
    analisisURLSimulado($noticia);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertOk()
        ->assertJsonPath('noticia.titulo', null)->assertJsonPath('noticia.autor', null)
        ->assertJsonPath('noticia.fecha_publicacion', null)->assertJsonPath('sabert.disponible', true);
})->with([true, false]);

it('rechaza entradas URL inválidas antes de consultar FastAPI', function ($entrada) {
    Http::fake();
    $this->postJson(route('analisis.url'), $entrada)->assertUnprocessable()->assertJsonValidationErrors('url');
    Http::assertNothingSent();
})->with([
    [[]], [['url' => '']], [['url' => null]], [['url' => ['https://example.org']]],
    [['url' => 123]], [['url' => 'example.org/noticia']], [['url' => '/relativa']],
    [['url' => 'ftp://example.org/noticia']], [['url' => 'javascript:alert(1)']],
    [['url' => 'https://example.org/'.str_repeat('a', 4096)]],
]);

it('delega a FastAPI la política SSRF sin descargar el enlace desde Laravel', function () {
    Http::fake(['127.0.0.1:8001/extraer-url' => Http::response([
        'detail' => ['codigo' => 'destino_bloqueado', 'mensaje' => 'Mensaje remoto'],
    ], 403)]);
    $this->postJson(route('analisis.url'), ['url' => 'http://127.0.0.1/privado'])->assertForbidden()
        ->assertJsonPath('error', 'destino_bloqueado')->assertJsonMissingPath('sabert');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/extraer-url'
        && $request->data() === ['url' => 'http://127.0.0.1/privado']);
});

it('explica los errores de extracción sin invocar la inferencia ni exponer mensajes externos', function ($codigo, $estado) {
    Http::fake(['127.0.0.1:8001/extraer-url' => Http::response([
        'detail' => ['codigo' => $codigo, 'mensaje' => '<script>credenciales_privadas</script>'],
    ], $estado)]);
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])
        ->assertStatus($estado)->assertJsonPath('error', $codigo)->assertJsonStructure(['message'])
        ->assertDontSee('credenciales_privadas')->assertJsonMissingPath('noticia')->assertJsonMissingPath('sabert');
    Http::assertSentCount(1);
})->with([
    ['url_invalida', 422], ['destino_bloqueado', 403], ['acceso_bloqueado', 403],
    ['descarga_demasiado_grande', 413], ['texto_demasiado_largo', 413], ['contenido_no_html', 415],
    ['codificacion_no_admitida', 415], ['texto_insuficiente', 422], ['extraccion_fallida', 422],
    ['redireccion_invalida', 502], ['redirecciones_excedidas', 502], ['descarga_fallida', 502], ['tiempo_agotado', 504],
]);

it('trata desconexiones y timeouts como indisponibilidad de extracción', function () {
    Http::fake(fn () => throw new ConnectionException('Ruta privada y credenciales_privadas'));
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])
        ->assertStatus(503)->assertJsonPath('error', 'no_disponible')->assertDontSee('credenciales_privadas');
});

it('no sigue redirecciones del backend ni expone errores de FastAPI', function ($estado, $cuerpo, $error, $respuesta) {
    Http::fake(['127.0.0.1:8001/extraer-url' => Http::response($cuerpo, $estado, ['Location' => 'http://127.0.0.1/privado'])]);
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])
        ->assertStatus($respuesta)->assertJsonPath('error', $error)->assertDontSee('credenciales_privadas');
    Http::assertSentCount(1);
})->with([
    [302, 'credenciales_privadas', 'no_disponible', 502],
    [500, 'credenciales_privadas', 'no_disponible', 502],
    [422, ['detail' => [['msg' => 'credenciales_privadas']]], 'url_invalida', 422],
    [504, 'credenciales_privadas', 'tiempo_agotado', 504],
]);

it('rechaza una respuesta de extracción inválida antes de analizar', function ($campo, $valor) {
    $noticia = noticiaExtraida();
    $noticia[$campo] = $valor;
    analisisURLSimulado($noticia);
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])
        ->assertStatus(502)->assertJsonPath('error', 'respuesta_invalida');
    Http::assertSentCount(1);
})->with([
    ['contenido', null], ['contenido', ['texto']], ['titulo', ['titulo']], ['autor', 123],
    ['fecha_publicacion', false], ['dominio', null], ['dominio', ''], ['url_final', null],
    ['url_final', 'javascript:alert(1)'], ['url_final', 'https://usuario:secreto@example.org/noticia'],
    ['url_final', 'https://example.org/ espacio'], ['url_final', 'https://example.org\\@example.com/'],
]);

it('rechaza JSON inválido o incompleto del extractor', function ($cuerpo) {
    Http::fake(['127.0.0.1:8001/extraer-url' => Http::response($cuerpo)]);
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])
        ->assertStatus(502)->assertJsonPath('error', 'respuesta_invalida');
    Http::assertSentCount(1);
})->with(['invalid', 'null', '"texto"', [[]]]);

it('comprueba los límites del texto extraído sin truncarlo ni enviar análisis parciales', function ($contenido, $estado, $error) {
    $noticia = noticiaExtraida();
    $noticia['contenido'] = $contenido;
    analisisURLSimulado($noticia);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertStatus($estado)
        ->assertJsonPath('error', $error)->assertJsonMissingPath('sabert');
    Http::assertSentCount(1);
})->with([
    ['corto', 422, 'texto_insuficiente'], [str_repeat(' ', 100), 422, 'texto_insuficiente'],
    [str_repeat('á', 100001), 413, 'texto_demasiado_largo'],
]);

it('respeta el límite de caracteres local configurado para ambos modos', function () {
    config(['services.sabert.max_characters' => 100]);
    $noticia = noticiaExtraida();
    analisisURLSimulado($noticia);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertStatus(413)
        ->assertJsonPath('error', 'texto_demasiado_largo');
    Http::assertSentCount(1);
});

it('conserva noticia y reglas si SaBERT no está disponible o excede sus límites', function ($estado, $error) {
    $noticia = noticiaExtraida();
    Http::fake([
        '127.0.0.1:8001/extraer-url' => Http::response($noticia),
        '127.0.0.1:8001/analizar' => Http::response(['detail' => 'No disponible'], $estado),
    ]);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertOk()
        ->assertJsonPath('noticia.contenido', $noticia['contenido'])
        ->assertJsonPath('sabert.disponible', false)->assertJsonPath('sabert.error', $error)
        ->assertJsonPath('sabert.fragmentos', [])->assertJsonPath('sabert.cobertura_porcentaje', null)
        ->assertJson(app(MotorReglas::class)->analizar($noticia['contenido']));
})->with([[503, 'no_disponible'], [413, 'limite_documento']]);

it('respeta el contrato anterior del adaptador SaBERT también al analizar una URL', function () {
    config(['services.sabert.endpoint' => '/predict', 'services.sabert.text_field' => 'text']);
    $noticia = noticiaExtraida();
    Http::fake([
        '127.0.0.1:8001/extraer-url' => Http::response($noticia),
        '127.0.0.1:8001/predict' => Http::response(['probabilities' => ['True' => 0.9, 'False' => 0.1]]),
    ]);
    $this->postJson(route('analisis.url'), ['url' => $noticia['url_final']])->assertOk()
        ->assertJsonPath('sabert.disponible', true)->assertJsonPath('sabert.probabilidades.True', 0.9)
        ->assertJsonPath('sabert.fragmentos', [])->assertJsonPath('sabert.cobertura_porcentaje', null);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8001/predict'
        && $request->data() === ['text' => $noticia['contenido']]);
});

it('usa tiempo de extracción independiente y desactiva redirecciones HTTP del backend', function () {
    config(['services.sabert.extraction_timeout' => 27, 'services.sabert.connect_timeout' => 4]);
    Http::fake(function ($request, $opciones) {
        expect($request->url())->toBe('http://127.0.0.1:8001/extraer-url');
        expect($opciones['timeout'])->toBe(27)->and($opciones['connect_timeout'])->toBe(4)
            ->and($opciones['allow_redirects'])->toBeFalse();

        return Http::response(['detail' => ['codigo' => 'acceso_bloqueado']], 403);
    });
    $this->postJson(route('analisis.url'), ['url' => 'https://noticias.example.org/noticia'])->assertForbidden();
});

it('presenta dos modos y metadatos sin alterar los componentes del dashboard', function () {
    $this->get('/')->assertOk()->assertSee('Analizar texto')->assertSee('Analizar URL')
        ->assertSee(route('analisis.url'))->assertSee('noticia-extraida')->assertSee('noticia-enlace')
        ->assertSee('noticia-contenido')->assertSee('input-estado')->assertSee('sabert-fragmentos')
        ->assertSee('bfs-estadisticas')->assertSee('dfs-estadisticas')->assertSee('prueba-atras');
});
