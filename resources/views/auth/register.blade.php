@extends('layouts.guest')

@section('title', 'Crear cuenta')
@section('side-title', 'Crea tu cuenta')
@section('side-text', 'Regístrate gratis para llevar el historial de tus análisis y verificar noticias con más confianza.')

@section('content')
<h1>Crear cuenta</h1>
<p class="sub">Solo te tomará un minuto.</p>

<form method="POST" action="{{ route('register') }}">
    @csrf

    <div class="field">
        <label for="name">Nombre</label>
        <div class="input">
            <i class="fa-regular fa-user"></i>
            <input id="name" type="text" name="name" value="{{ old('name') }}" class="@error('name') err @enderror" placeholder="Tu nombre" required autocomplete="name" autofocus>
        </div>
        @error('name')<div class="msg">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="email">Correo electrónico</label>
        <div class="input">
            <i class="fa-regular fa-envelope"></i>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="@error('email') err @enderror" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
        </div>
        @error('email')<div class="msg">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password">Contraseña</label>
        <div class="input">
            <i class="fa-solid fa-lock"></i>
            <input id="password" type="password" name="password" class="@error('password') err @enderror" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
        </div>
        @error('password')<div class="msg">{{ $message }}</div>@enderror
    </div>

    <div class="field" style="margin-bottom:22px">
        <label for="password-confirm">Confirmar contraseña</label>
        <div class="input">
            <i class="fa-solid fa-lock"></i>
            <input id="password-confirm" type="password" name="password_confirmation" placeholder="Repite tu contraseña" required autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="go" id="btn-register">Crear cuenta</button>
</form>

<p class="alt">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></p>
@endsection
