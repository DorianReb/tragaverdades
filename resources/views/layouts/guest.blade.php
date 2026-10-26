<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · TragaVerdades</title>
<meta name="description" content="Accede a TragaVerdades para guardar y consultar tus análisis de noticias en español.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@vite(['resources/js/app.js'])
<style>
:root{--bg:#f2f4f7;--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#1f3a5f;--brand-hover:#17304f;--brand-soft:#eaf0f7;--bad:#b42318;--bad-soft:#fbeae8;--ok:#2e7d57;--ok-soft:#e9f4ee}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:24px}
.shell{width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1fr;gap:16px}
.tile{background:#fff;border:1px solid var(--line);border-radius:24px;padding:34px;box-shadow:0 1px 2px rgba(16,24,40,.05);animation:rise .5s ease both}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.side{background:var(--brand);color:#fff;border-color:var(--brand);display:flex;flex-direction:column;justify-content:space-between;gap:28px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit}
.mark{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.14);display:grid;place-items:center;font-size:17px}
.brand b{font-size:17px;letter-spacing:-.02em;display:block}
.brand small{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:#c6d3e4}
.side h2{font-size:28px;letter-spacing:-.03em;line-height:1.15;margin-bottom:10px}
.side p{font-size:14px;color:#c6d3e4;line-height:1.6}
.perks{list-style:none;display:flex;flex-direction:column;gap:12px;font-size:13px;color:#dbe5f1}
.perks i{width:28px;height:28px;border-radius:9px;background:rgba(255,255,255,.12);display:inline-grid;place-items:center;margin-right:10px;font-size:12px}
h1{font-size:24px;letter-spacing:-.03em;margin-bottom:6px}
.sub{font-size:13.5px;color:var(--muted);margin-bottom:24px}
.field{margin-bottom:16px}
label{display:block;font-size:12.5px;font-weight:700;margin-bottom:6px}
.input{position:relative}
.input i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#98a2b3;font-size:13px}
input[type=text],input[type=email],input[type=password]{width:100%;border:1px solid var(--line);border-radius:14px;padding:13px 14px 13px 40px;font:inherit;font-size:14px;background:#fafbfc;outline:none;transition:border-color .2s,background .2s}
input:focus{border-color:var(--brand);background:#fff}
input.err{border-color:var(--bad)}
.msg{font-size:12px;color:var(--bad);margin-top:6px}
.alert{background:var(--bad-soft);color:var(--bad);border-radius:14px;padding:12px 14px;font-size:12.5px;margin-bottom:16px}
.status{background:var(--ok-soft);color:var(--ok);border-radius:14px;padding:12px 14px;font-size:12.5px;margin-bottom:16px}
.row{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;margin-bottom:20px}
.row label{display:flex;align-items:center;gap:8px;margin:0;font-weight:500;color:var(--muted)}
.row a,.alt a{color:var(--brand);font-weight:700;text-decoration:none}
.row a:hover,.alt a:hover{text-decoration:underline}
.go{width:100%;padding:14px;border:0;border-radius:999px;background:var(--brand);color:#fff;font:inherit;font-weight:700;font-size:14px;cursor:pointer;transition:background .2s}
.go:hover{background:var(--brand-hover)}
.alt{text-align:center;font-size:13px;color:var(--muted);margin-top:18px}
.back{display:inline-flex;gap:8px;align-items:center;font-size:12.5px;color:var(--muted);text-decoration:none;margin-bottom:18px}
.back:hover{color:var(--brand)}
@media(max-width:760px){.shell{grid-template-columns:1fr}.side{display:none}}
</style>
</head>
<body>
<main class="shell">
  <aside class="tile side">
    <a class="brand" href="{{ url('/') }}">
      <span class="mark"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
      <span><b>TragaVerdades</b><small>Detector de desinformación</small></span>
    </a>
    <div>
      <h2>@yield('side-title')</h2>
      <p>@yield('side-text')</p>
    </div>
    <ul class="perks">
      <li><i class="fa-solid fa-clock-rotate-left"></i>Historial de tus análisis</li>
      <li><i class="fa-solid fa-shield-halved"></i>Cuenta protegida con contraseña</li>
      <li><i class="fa-solid fa-link"></i>Acceso a fuentes de verificación</li>
    </ul>
  </aside>
  <section class="tile">
    <a class="back" href="{{ url('/') }}"><i class="fa-solid fa-arrow-left"></i>Volver al analizador</a>
    @yield('content')
  </section>
</main>
</body>
</html>
