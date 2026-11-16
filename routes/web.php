<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalisisReglasController;
use App\Http\Controllers\AnalisisIAController;

Route::view('/', 'analizador')->name('analizador');

Route::post('/analisis-ia', [AnalisisIAController::class, 'analizar'])
    ->middleware('throttle:30,1')->name('analisis.ia');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::post('/analisis-reglas', [
    AnalisisReglasController::class,
    'analizar'
])->middleware('throttle:30,1')
    ->name('analisis.reglas');
