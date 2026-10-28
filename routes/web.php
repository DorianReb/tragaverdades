<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalisisReglasController;

Route::view('/', 'analizador')->name('analizador');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::post('/analisis-reglas', [
    AnalisisReglasController::class,
    'analizar'
])->middleware('throttle:30,1')
    ->name('analisis.reglas');
