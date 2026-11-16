<?php

namespace App\Services;

use App\Exceptions\ExtraccionNoticiaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ExtractorNoticiasService
{
    private const ERRORES = [
        'url_invalida' => [422, 'Introduce una URL HTTP o HTTPS válida, sin credenciales.'],
        'destino_bloqueado' => [403, 'La URL apunta a un destino no permitido. Usa el enlace público de una noticia.'],
        'acceso_bloqueado' => [403, 'El sitio restringe el acceso a esta noticia. Prueba con un artículo público o introduce el texto manualmente.'],
        'descarga_demasiado_grande' => [413, 'La página supera el tamaño máximo de descarga permitido.'],
        'texto_demasiado_largo' => [413, 'El artículo supera el límite de caracteres permitido. No se ha truncado su contenido.'],
        'contenido_no_html' => [415, 'La URL no contiene una página HTML compatible. Usa el enlace de un artículo periodístico.'],
        'codificacion_no_admitida' => [415, 'La página utiliza una compresión no compatible con el extractor.'],
        'texto_insuficiente' => [422, 'No se encontró suficiente texto público en la página. Prueba con otro enlace o introduce el texto manualmente.'],
        'extraccion_fallida' => [422, 'No fue posible extraer el artículo de esta página. Prueba con otro enlace.'],
        'redireccion_invalida' => [502, 'El sitio devolvió una redirección que no se puede procesar.'],
        'redirecciones_excedidas' => [502, 'La URL contiene demasiadas redirecciones o un ciclo de enlaces.'],
        'descarga_fallida' => [502, 'No fue posible descargar la noticia. Comprueba el enlace o inténtalo más tarde.'],
        'tiempo_agotado' => [504, 'Se agotó el tiempo para descargar la noticia. Inténtalo más tarde o introduce el texto manualmente.'],
    ];

    public function extraer(string $url): array
    {
        try {
            // El destino HTTP siempre es FastAPI configurado por el servidor.
            // La URL del lector se envía como dato, nunca se descarga desde PHP.
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.sabert.connect_timeout', 3))
                ->timeout((int) config('services.sabert.extraction_timeout', 30))
                ->withOptions(['allow_redirects' => false])
                ->post(rtrim(config('services.sabert.url'), '/').'/'.ltrim(config('services.sabert.extraction_endpoint'), '/'), [
                    'url' => $url,
                ]);
        } catch (ConnectionException) {
            throw new ExtraccionNoticiaException('no_disponible', 'No fue posible conectar con el servicio de extracción o se agotó la espera. Inténtalo más tarde.', 503);
        }

        if ($response->status() !== 200) {
            $codigo = $response->json('detail.codigo');
            if (is_string($codigo) && isset(self::ERRORES[$codigo])) {
                [$estado, $mensaje] = self::ERRORES[$codigo];
                throw new ExtraccionNoticiaException($codigo, $mensaje, $estado);
            }
            if ($response->status() === 422) {
                throw new ExtraccionNoticiaException('url_invalida', self::ERRORES['url_invalida'][1], 422);
            }
            if ($response->status() === 504) {
                throw new ExtraccionNoticiaException('tiempo_agotado', self::ERRORES['tiempo_agotado'][1], 504);
            }

            throw new ExtraccionNoticiaException('no_disponible', 'El servicio de extracción no está disponible en este momento. Inténtalo más tarde.', 502);
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['contenido'] ?? null)
            || ! is_string($data['dominio'] ?? null) || trim($data['dominio']) === ''
            || ! is_string($data['url_final'] ?? null)
            || validator(['url' => $data['url_final']], ['url' => ['required', 'url:http,https', 'max:4096']])->fails()
            || parse_url($data['url_final'], PHP_URL_USER) !== null
            || preg_match('/[\x00-\x20\x7f\\\\]/', $data['url_final'])) {
            $this->respuestaInvalida();
        }

        $noticia = array_intersect_key($data, array_flip(['contenido', 'dominio', 'url_final']));
        foreach (['titulo', 'autor', 'fecha_publicacion'] as $campo) {
            $valor = $data[$campo] ?? null;
            if ($valor !== null && ! is_string($valor)) {
                $this->respuestaInvalida();
            }
            $noticia[$campo] = $valor;
        }

        if (mb_strlen(trim($noticia['contenido'])) < 20) {
            throw new ExtraccionNoticiaException('texto_insuficiente', self::ERRORES['texto_insuficiente'][1], 422);
        }
        if (mb_strlen($noticia['contenido']) > config('services.sabert.max_characters')) {
            throw new ExtraccionNoticiaException('texto_demasiado_largo', self::ERRORES['texto_demasiado_largo'][1], 413);
        }

        return $noticia;
    }

    private function respuestaInvalida(): never
    {
        throw new ExtraccionNoticiaException('respuesta_invalida', 'El servicio de extracción devolvió una respuesta inválida. Inténtalo más tarde.', 502);
    }
}
