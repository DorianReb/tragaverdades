<?php

namespace App\Http\Controllers;

use App\Services\MotorReglas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalisisReglasController extends Controller
{
    public function analizar(Request $request, MotorReglas $motor): JsonResponse
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        return response()->json($motor->analizar(trim($datos['texto'])));
    }
}
