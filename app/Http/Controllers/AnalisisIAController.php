<?php

namespace App\Http\Controllers;

use App\Exceptions\ExtraccionNoticiaException;
use App\Services\ExtractorNoticiasService;
use App\Services\MotorReglas;
use App\Services\SaBERTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalisisIAController extends Controller
{
    public function analizar(Request $request, MotorReglas $motor, SaBERTService $sabert): JsonResponse
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'min:20', 'max:'.config('services.sabert.max_characters')],
        ], [
            'texto.min' => 'Escribe al menos 20 caracteres.',
            'texto.max' => 'El documento supera el límite de :max caracteres permitido. Reduce el texto e intenta nuevamente.',
        ]);

        return response()->json($this->analizarTexto($datos['texto'], $motor, $sabert));
    }

    public function analizarUrl(Request $request, MotorReglas $motor, SaBERTService $sabert, ExtractorNoticiasService $extractor): JsonResponse
    {
        $datos = $request->validate([
            'url' => ['required', 'string', 'max:4096', 'url:http,https'],
        ], [
            'url.required' => 'Introduce el enlace de una noticia.',
            'url.string' => 'El enlace de la noticia debe ser una URL en texto.',
            'url.url' => 'Introduce una URL completa que empiece por http:// o https://.',
            'url.max' => 'La URL supera el límite de 4096 caracteres.',
        ]);

        try {
            $noticia = $extractor->extraer($datos['url']);
        } catch (ExtraccionNoticiaException $error) {
            return response()->json(['message' => $error->getMessage(), 'error' => $error->codigo], $error->estado);
        }

        $resultado = $this->analizarTexto($noticia['contenido'], $motor, $sabert);
        // url_original es un campo añadido por Laravel; los otros seis proceden de FastAPI.
        $resultado['noticia'] = [...$noticia, 'url_original' => $datos['url']];

        return response()->json($resultado);
    }

    private function analizarTexto(string $texto, MotorReglas $motor, SaBERTService $sabert): array
    {
        $texto = trim($texto);
        validator(['texto' => $texto], ['texto' => ['required', 'min:20']])->validate();

        $resultado = $motor->analizar($texto);
        $resultado['sabert'] = $sabert->analizar($texto);

        return $resultado;
    }
}
