<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SaBERTService
{
    private const ADVERTENCIA = 'La clasificación global es una agregación estadística, no una verificación de hechos. Las probabilidades no representan veracidad factual. Contrasta la noticia con fuentes independientes.';

    private const METADATOS = [
        'modelo', 'confianza_porcentaje', 'dispositivo', 'tokens_procesados',
        'texto_truncado', 'advertencia', 'metodo_agregacion', 'total_tokens_originales',
        'cantidad_fragmentos', 'tokens_contenido_procesados', 'tokens_cubiertos',
        'cobertura_porcentaje', 'ventana_tokens', 'solapamiento_tokens',
    ];

    public function analizar(string $texto): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.sabert.connect_timeout', 3))
                ->timeout((int) config('services.sabert.timeout', 120))
                ->withOptions(['allow_redirects' => false])
                ->post(rtrim(config('services.sabert.url'), '/').'/'.ltrim(config('services.sabert.endpoint'), '/'), [
                    config('services.sabert.text_field') => $texto,
                ]);

            if (! $response->successful()) {
                $limiteCaracteres = $response->status() === 422
                    && collect($response->json('detail'))->contains(fn ($error) => is_array($error) && ($error['type'] ?? null) === 'string_too_long');

                if ($response->status() === 413 || $limiteCaracteres) {
                    return $this->fallo('limite_documento', 'El documento excede los límites de tamaño permitidos por FastAPI (caracteres, tokens o fragmentos). Reduce el texto e intenta nuevamente. El análisis por reglas sigue disponible.');
                }
                if ($response->status() === 422) {
                    return $this->fallo('documento_invalido', 'FastAPI no pudo analizar el documento: revisa que contenga texto válido. El análisis por reglas sigue disponible.');
                }

                return $this->fallo('no_disponible', 'SaBERT / FastAPI no está disponible en este momento. El análisis por reglas sigue disponible. Intenta nuevamente más tarde.');
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new RuntimeException('Invalid JSON');
            }

            // FastAPI usa porcentajes; el adaptador anterior de Laravel usaba fracciones.
            // Se conserva la escala 0..1 en la salida PHP, incluidos los fragmentos.
            $porcentajes = array_key_exists('probabilidades', $data);
            $probabilidades = $this->probabilidades($data[$porcentajes ? 'probabilidades' : 'probabilities'] ?? null, $porcentajes ? 100 : 1);
            $clasificacion = $porcentajes ? ($data['clasificacion'] ?? null)
                : ($data['clasificacion'] ?? ($probabilidades['True'] >= $probabilidades['False'] ? 'True' : 'False'));
            $this->validarClase($clasificacion);

            $resultado = array_merge(array_fill_keys(self::METADATOS, null), array_intersect_key($data, array_flip(self::METADATOS)), [
                'disponible' => true,
                'error' => null,
                'clasificacion' => $clasificacion,
                'probabilidades' => $probabilidades,
                'fragmentos' => [],
                'mensaje' => self::ADVERTENCIA,
            ]);

            if (array_key_exists('fragmentos', $data) || array_key_exists('cantidad_fragmentos', $data)) {
                $resultado['fragmentos'] = $this->fragmentos($data);
            }

            return $resultado;
        } catch (ConnectionException $exception) {
            return $this->fallo('no_disponible', 'No fue posible conectar con SaBERT / FastAPI o se agotó el tiempo de espera. El análisis por reglas sigue disponible. Intenta nuevamente más tarde.', $exception);
        } catch (Throwable $exception) {
            return $this->fallo('respuesta_invalida', 'SaBERT devolvió una respuesta inválida. El análisis por reglas sigue disponible. Intenta nuevamente más tarde.', $exception);
        }
    }

    private function probabilidades(mixed $valores, int $escala): array
    {
        $resultado = [];
        foreach (['True', 'False'] as $etiqueta) {
            $valor = is_array($valores) ? ($valores[$etiqueta] ?? null) : null;
            if (! is_numeric($valor) || ! is_finite((float) $valor) || $valor < 0 || $valor > $escala) {
                throw new RuntimeException('Invalid probability');
            }
            $resultado[$etiqueta] = (float) $valor / $escala;
        }
        if (abs(array_sum($resultado) - 1) > 0.01) {
            throw new RuntimeException('Invalid probability sum');
        }

        return $resultado;
    }

    private function validarClase(mixed $clase): void
    {
        if (! in_array($clase, ['True', 'False'], true)) {
            throw new RuntimeException('Invalid class');
        }
    }

    private function entero(array $datos, string $campo, int $minimo = 0): int
    {
        $valor = $datos[$campo] ?? null;
        if (! is_int($valor) || $valor < $minimo) {
            throw new RuntimeException('Invalid count: '.$campo);
        }

        return $valor;
    }

    private function porcentaje(mixed $valor): float
    {
        if (! is_numeric($valor) || ! is_finite((float) $valor) || $valor < 0 || $valor > 100) {
            throw new RuntimeException('Invalid percentage');
        }

        return (float) $valor;
    }

    private function fragmentos(array $data): array
    {
        $fragmentos = $data['fragmentos'] ?? null;
        $cantidad = $this->entero($data, 'cantidad_fragmentos', 1);
        $originales = $this->entero($data, 'total_tokens_originales', 1);
        $ventana = $this->entero($data, 'ventana_tokens', 1);
        $solapamiento = $this->entero($data, 'solapamiento_tokens');
        $cobertura = $this->porcentaje($data['cobertura_porcentaje'] ?? null);
        if (! is_array($fragmentos) || ! array_is_list($fragmentos) || count($fragmentos) !== $cantidad
            || $solapamiento >= $ventana || ! is_bool($data['texto_truncado'] ?? null)) {
            throw new RuntimeException('Invalid chunks metadata');
        }

        $agregacion = $data['metodo_agregacion'] ?? [];
        if (($agregacion['nombre'] ?? null) !== 'promedio_probabilidades'
            || ($agregacion['ponderacion'] ?? null) !== 'uniforme_por_fragmento'
            || ($agregacion['probabilidad_calibrada'] ?? null) !== false
            || ($agregacion['verificacion_factual'] ?? null) !== false) {
            throw new RuntimeException('Invalid aggregation');
        }

        $normalizados = [];
        $finAnterior = $cubiertos = $tokens = $contenido = 0;
        foreach ($fragmentos as $indice => $fragmento) {
            if (! is_array($fragmento) || $this->entero($fragmento, 'indice') !== $indice) {
                throw new RuntimeException('Invalid chunk index');
            }
            $inicio = $this->entero($fragmento, 'inicio_token');
            $fin = $this->entero($fragmento, 'fin_token', 1);
            $tokensFragmento = $this->entero($fragmento, 'tokens', 1);
            $contenidoFragmento = $this->entero($fragmento, 'tokens_contenido', 1);
            if ($fin <= $inicio || $fin > $originales || $fin <= $finAnterior || $inicio > $finAnterior
                || ($indice > 0 && $finAnterior - $inicio !== $solapamiento)
                || $fin - $inicio !== $contenidoFragmento || $tokensFragmento < $contenidoFragmento || $tokensFragmento > $ventana) {
                throw new RuntimeException('Invalid token range');
            }
            $this->validarClase($fragmento['clasificacion'] ?? null);
            $fragmento['confianza_porcentaje'] = $this->porcentaje($fragmento['confianza_porcentaje'] ?? null);
            $fragmento['probabilidades'] = $this->probabilidades($fragmento['probabilidades'] ?? null, 100);
            $normalizados[] = $fragmento;
            $cubiertos += $fin - $finAnterior;
            $finAnterior = $fin;
            $tokens += $tokensFragmento;
            $contenido += $contenidoFragmento;
        }

        if ($this->entero($data, 'tokens_procesados', 1) !== $tokens
            || $this->entero($data, 'tokens_contenido_procesados', 1) !== $contenido
            || $this->entero($data, 'tokens_cubiertos', 1) !== $cubiertos
            || abs($cobertura - 100 * $cubiertos / $originales) > 0.01
            || ($data['texto_truncado'] === false && $cubiertos !== $originales)) {
            throw new RuntimeException('Inconsistent coverage');
        }

        return $normalizados;
    }

    private function fallo(string $codigo, string $mensaje, ?Throwable $exception = null): array
    {
        // No registrar noticias, cuerpos remotos ni mensajes de excepción.
        Log::warning('SaBERT: análisis no disponible.', array_filter([
            'error' => $codigo,
            'exception' => $exception ? get_class($exception) : null,
        ]));

        return array_merge(array_fill_keys(self::METADATOS, null), [
            'disponible' => false,
            'error' => $codigo,
            'clasificacion' => null,
            'probabilidades' => null,
            'fragmentos' => [],
            'mensaje' => $mensaje,
        ]);
    }
}
