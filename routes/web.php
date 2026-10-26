<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'analizador')->name('analizador');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
