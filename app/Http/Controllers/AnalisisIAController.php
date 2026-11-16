<?php

namespace App\Http\Controllers;

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
        $texto = trim($datos['texto']);
        validator(['texto' => $texto], ['texto' => ['required', 'min:20']])->validate();

        $resultado = $motor->analizar($texto);
        $resultado['sabert'] = $sabert->analizar($texto);

        return response()->json($resultado);
    }
}
