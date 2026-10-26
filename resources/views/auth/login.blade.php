@extends('layouts.guest')

@section('title', 'Iniciar sesión')
@section('side-title', 'Bienvenido de nuevo')
@section('side-text', 'Inicia sesión para guardar tus análisis y volver a consultarlos cuando quieras.')

@section('content')
<h1>Iniciar sesión</h1>
<p class="sub">Ingresa tus datos para continuar.</p>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="field">
        <label for="email">Correo electrónico</label>
        <div class="input">
            <i class="fa-regular fa-envelope"></i>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="@error('email') err @enderror" placeholder="tucorreo@ejemplo.com" required autocomplete="email" autofocus>
        </div>
        @error('email')<div class="msg">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="password">Contraseña</label>
        <div class="input">
            <i class="fa-solid fa-lock"></i>
            <input id="password" type="password" name="password" class="@error('password') err @enderror" placeholder="••••••••" required autocomplete="current-password">
        </div>
        @error('password')<div class="msg">{{ $message }}</div>@enderror
    </div>

    <div class="row">
        <label for="remember"><input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>Recordarme</label>
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
        @endif
    </div>

    <button type="submit" class="go" id="btn-login">Iniciar sesión</button>
</form>

<p class="alt">¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></p>
@endsection
