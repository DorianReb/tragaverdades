<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>TragaVerdades · Detector de noticias falsas en español</title>
<meta name="description" content="Explora señales textuales mediante reglas y algoritmos de búsqueda. La clasificación con SaBERT está pendiente.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
@vite(['resources/js/app.js'])
<style>
:root{
  --bg:#f2f4f7; --card:#fff; --ink:#111827; --muted:#667085; --line:#e4e7ec;
  --brand:#1f3a5f; --brand-hover:#17304f; --brand-soft:#eaf0f7;
  --ok:#2e7d57; --ok-soft:#e9f4ee; --warn:#a96a0f; --warn-soft:#fbf1de; --bad:#b42318; --bad-soft:#fbeae8;
  --r:24px; --shadow:0 1px 2px rgba(16,24,40,.05);
  --accent:var(--muted); --accent-soft:#f2f4f7;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh}
button{font:inherit;cursor:pointer;border:0;background:none;color:inherit}
.wrap{max-width:1240px;margin:0 auto;padding:20px 24px 48px}

/* Header */
header{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 12px 10px 18px;box-shadow:var(--shadow);position:sticky;top:12px;z-index:10}
.logo{display:flex;align-items:center;gap:12px}
.logo-mark{width:40px;height:40px;border-radius:12px;background:var(--brand);display:grid;place-items:center;color:#fff}
.logo-mark{font-size:17px}.ico{font-size:13px}
.logo b{font-size:17px;letter-spacing:-.02em;display:block}
.logo small{font-size:9.5px;letter-spacing:.14em;color:var(--muted);font-weight:600;text-transform:uppercase}
.seg{display:flex;background:#f2f4f7;border-radius:999px;padding:4px}
.seg button{padding:8px 18px;border-radius:999px;font-size:13px;font-weight:600;color:var(--muted);transition:.2s}
.seg button.on{background:#fff;color:var(--brand);box-shadow:0 1px 3px rgba(16,24,40,.1)}
.hr{display:flex;gap:10px;align-items:center}
.btn{background:var(--brand);color:#fff;padding:10px 20px;border-radius:999px;font-weight:700;font-size:13px;transition:background .2s}
.btn:hover{background:var(--brand-hover)}
.avatar{width:40px;height:40px;border-radius:50%;border:1px solid var(--line);display:grid;place-items:center;background:#fff}

/* Bento */
.bento{display:grid;grid-template-columns:repeat(12,1fr);grid-auto-rows:minmax(80px,auto);gap:16px;margin-top:20px}
.tile{background:var(--card);border-radius:var(--r);padding:22px;box-shadow:var(--shadow);border:1px solid var(--line);position:relative;overflow:hidden;animation:rise .5s ease both;transition:border-color .2s}
.tile:hover{border-color:#cfd5de}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.tile h2{font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px;letter-spacing:-.01em}
.ico{width:30px;height:30px;border-radius:10px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;flex:none}
.cap{font-size:10px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--muted)}

.t-hero{grid-column:span 12;background:var(--brand);color:#fff;padding:30px 34px}
.t-hero h1{font-size:clamp(24px,3.2vw,36px);letter-spacing:-.03em;line-height:1.1;max-width:680px}
.t-hero p{margin-top:10px;color:#c6d3e4;font-size:14px;max-width:620px;line-height:1.55}
.t-hero:hover{border-color:var(--brand)}

.t-legal{grid-column:span 12;background:var(--brand-soft);border-color:#d5e0ed;display:flex;gap:12px;align-items:center;padding:14px 22px;font-size:12.5px;color:#2b4366;border-radius:20px}
.t-recent{grid-column:span 3;grid-row:span 5}
.t-input{grid-column:span 6;grid-row:span 5;display:flex;flex-direction:column}
.t-result{grid-column:span 3;grid-row:span 2;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;background:var(--accent-soft);transition:background .4s}
.t-conf{grid-column:span 3;grid-row:span 1}
.t-src{grid-column:span 3;grid-row:span 2}
.t-tips{grid-column:span 12;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;background:transparent;box-shadow:none;border:0;padding:0}
.tip{background:#fff;border:1px solid var(--line);border-radius:20px;padding:18px 20px;font-size:12.5px;color:var(--muted);line-height:1.5}
.tip b{display:block;color:var(--ink);font-size:14px;margin:8px 0 3px}
.tip i{font-size:16px;color:var(--brand);width:38px;height:38px;display:grid;place-items:center;background:var(--brand-soft);border-radius:12px}

/* Recents */
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--muted);font-size:12.5px;text-align:center;height:calc(100% - 40px);min-height:200px}
.empty div{width:52px;height:52px;border-radius:50%;background:#f2f4f7;display:grid;place-items:center;font-size:20px;color:#98a2b3}
.rlist{margin-top:16px;display:flex;flex-direction:column;gap:8px}
.ritem{text-align:left;padding:12px 14px;border-radius:14px;background:#f8f9fb;border:1px solid var(--line);font-size:12px;transition:.2s;animation:rise .3s both}
.ritem:hover{border-color:var(--brand);background:var(--brand-soft)}
.ritem p{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;margin-bottom:6px}
.pill{display:inline-block;font-size:10px;font-weight:800;letter-spacing:.06em;padding:3px 9px;border-radius:999px}
.pill.ok{background:var(--ok-soft);color:var(--ok)}.pill.warn{background:var(--warn-soft);color:var(--warn)}.pill.bad{background:var(--bad-soft);color:var(--bad)}

/* Input */
.in-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.count{font-family:'JetBrains Mono',monospace;font-size:11px;background:#f2f4f7;border:1px solid var(--line);padding:6px 12px;border-radius:10px;color:var(--muted)}
textarea{flex:1;min-height:340px;resize:none;border:1px solid var(--line);border-radius:16px;padding:18px;font:inherit;font-size:14px;line-height:1.7;color:#344054;background:#fafbfc;outline:none;transition:border-color .2s,background .2s}
textarea:focus{border-color:var(--brand);background:#fff}
.in-foot{display:flex;gap:10px;margin-top:14px;align-items:center}
.ghost{padding:12px 18px;border-radius:999px;border:1px solid var(--line);font-size:13px;font-weight:600;color:var(--muted);transition:.2s}
.ghost:hover{border-color:var(--brand);color:var(--brand)}
.go{flex:1;padding:14px;border-radius:999px;font-weight:700;font-size:14px;color:#fff;background:var(--brand);transition:background .2s}
.go:hover{background:var(--brand-hover)}.go:disabled{opacity:.6;cursor:wait}

/* Result */
.badge{width:56px;height:56px;border-radius:50%;background:#fff;border:1px solid var(--line);display:grid;place-items:center;font-size:22px;color:var(--accent);margin-bottom:10px}
.verdict{font-size:26px;font-weight:800;letter-spacing:-.03em;color:var(--accent)}
.t-result em{font-size:12.5px;color:var(--muted);margin-top:4px}
.t-result.scan .badge{animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

.bar{height:8px;background:#eceff3;border-radius:99px;margin-top:12px;overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;background:var(--accent);transition:width .9s ease}
.row{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--muted);margin-top:12px}
.tag{font-weight:800;font-size:11px;color:var(--accent)}

/* Sources */
.srcs{margin-top:14px;display:flex;flex-direction:column;gap:8px}
.srcs a{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;background:#f8f9fb;border-radius:12px;font-size:12.5px;font-weight:700;color:var(--ink);text-decoration:none;border:1px solid var(--line);transition:.2s}
.srcs a:hover{border-color:var(--brand);background:var(--brand-soft)}
.srcs a span{color:var(--muted);font-weight:500;font-size:11px}

@media(max-width:1000px){
  .t-recent,.t-input,.t-result,.t-conf,.t-src{grid-column:span 12;grid-row:auto}
  .t-result,.t-conf{grid-column:span 6}
  .t-tips{grid-template-columns:repeat(2,1fr)}
  .seg{display:none}
}
@media(max-width:640px){.t-result,.t-conf{grid-column:span 12}.t-tips{grid-template-columns:1fr}.wrap{padding:12px}}
.btn{display:inline-block;text-decoration:none;border:0;cursor:pointer}
.btn.out{background:#fff;color:var(--brand);border:1px solid var(--line)}.btn.out:hover{background:var(--brand-soft)}
.uname{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--ink);padding:0 6px}
.uname i{color:var(--muted)}

/* Laboratorio de Unidad III: información explicable, sin probabilidades */
.t-kb{grid-column:span 12}
.kb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:16px}
.kb-panel{border:1px solid var(--line);border-radius:16px;padding:16px;background:#fafbfc;min-width:0}
.kb-panel h3{font-size:14px;margin-bottom:10px}
.kb-muted{font-size:12px;color:var(--muted);line-height:1.6}
.kb-chipbox{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}
.kb-chip{font-size:11px;padding:5px 9px;border-radius:12px;background:var(--brand-soft);color:var(--brand);word-break:break-word}
.kb-list{font-size:12px;line-height:1.65;padding-left:20px}
.kb-list li{margin:8px 0;overflow-wrap:anywhere}
.kb-list strong{font-size:12px}
.kb-stats{font-size:12px;margin:7px 0 10px;color:var(--brand)}
.kb-proof ul{padding-left:18px;border-left:1px dashed var(--line);margin:6px 0 6px 10px}
.kb-proof li{font-size:12px;line-height:1.7;overflow-wrap:anywhere}
@media(max-width:720px){.kb-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="logo">
      <div class="logo-mark"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
      <div><b>TragaVerdades</b><small>Detector de desinformación</small></div>
    </div>
    <div class="seg" role="tablist"><button class="on" id="tab-general">General</button><button id="tab-investigador">Investigador</button></div>
    <div class="hr">
      @auth
        <span class="uname" id="user-name"><i class="fa-regular fa-user"></i>{{ \Illuminate\Support\Str::limit(Auth::user()->name, 18) }}</span>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="btn" id="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Salir</button>
        </form>
      @else
        <a class="btn out" id="btn-login" href="{{ route('login') }}">Iniciar sesión</a>
        <a class="btn" id="btn-registro" href="{{ route('register') }}">Registro</a>
      @endauth
    </div>
  </header>

  <main class="bento">
    <section class="tile t-hero">
      <h1>¿Es verdad lo que acabas de leer?</h1>
      <p>Pega una noticia o publicación en español para explorar señales de alerta y explicar cómo se derivan las recomendaciones.</p>
    </section>

    <aside class="tile t-legal">
      <i class="fa-solid fa-circle-info" style="font-size:18px"></i>
      <p><b>Aviso legal:</b> Los resultados provienen de una clasificación automatizada y son una estimación probabilística.
          Úsalos solo como apoyo y contrasta siempre con medios verificados y organizaciones de fact-checking.</p>
    </aside>

    <aside class="tile t-recent">
      <h2><span class="ico"><i class="fa-solid fa-clock-rotate-left"></i></span>Recientes</h2>
      <div class="empty" id="empty"><div><i class="fa-solid fa-inbox"></i></div>No hay análisis<br>previos.</div>
      <div class="rlist" id="rlist"></div>
    </aside>

    <section class="tile t-input">
      <div class="in-head">
        <h2><span class="ico"><i class="fa-regular fa-file-lines"></i></span>Analizador</h2>
        <span class="count" id="count">0 caracteres</span>
      </div>
      <textarea id="text" placeholder="Pega aquí el titular o el texto completo de la noticia…"></textarea>
      <div class="in-foot">
        <button class="ghost" id="btn-ejemplo">Ejemplo</button>
        <button class="ghost" id="btn-limpiar">Limpiar</button>
        <button class="go" id="btn-analizar" data-endpoint="{{ route('analisis.reglas') }}">Analizar noticia</button>
      </div>
    </section>

    <section class="tile t-result" id="result">
      <span class="cap">Resultado</span>
      <div class="badge" id="badge"><i class="fa-solid fa-magnifying-glass"></i></div>
      <div class="verdict" id="verdict">En espera</div>
      <em id="vsub">Ingresa un texto para examinar sus señales.</em>
    </section>

    <section class="tile t-conf">
      <div class="row" style="margin:0"><span class="cap">Reglas ejecutadas</span><b id="confnum" style="font-size:15px">—</b></div>
      <div class="bar"><i id="confbar"></i></div>
      <div class="row"><span>Recomendación</span><span class="tag" id="class">—</span></div>
    </section>

    <section class="tile t-src">
      <h2><span class="ico"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>Verificar en fuentes</h2>
      <div class="srcs">
        <a href="https://maldita.es" target="_blank" rel="noopener">Maldita.es <span>España</span></a>
        <a href="https://newtral.es" target="_blank" rel="noopener">Newtral <span>España</span></a>
        <a href="https://www.animalpolitico.com/verificado" target="_blank" rel="noopener">Verificado <span>México</span></a>
        <a href="https://chequeado.com" target="_blank" rel="noopener">Chequeado <span>Argentina</span></a>
      </div>
    </section>

    <section class="tile t-tips">
      <div class="tip"><i class="fa-regular fa-newspaper"></i><b>Revisa la fuente</b>¿Existe el medio? ¿Tiene datos de contacto y autor?</div>
      <div class="tip"><i class="fa-regular fa-calendar"></i><b>Mira la fecha</b>Noticias viejas se reciclan como si fueran actuales.</div>
      <div class="tip"><i class="fa-solid fa-triangle-exclamation"></i><b>Desconfía de la urgencia</b>Las MAYÚSCULAS y "¡compártelo ya!" son bandera roja.</div>
      <div class="tip"><i class="fa-solid fa-code-compare"></i><b>Busca otra versión</b>Si es real, otros medios fiables también lo cubren.</div>
    </section>

    <section class="tile t-kb" id="detalle-reglas" aria-live="polite">
      <h2><span class="ico"><i class="fa-solid fa-diagram-project"></i></span> Unidad III · Reglas y búsqueda</h2>
      <p class="kb-muted" id="interpretacion">Al analizar el texto aparecerán los hechos, las reglas activadas y la comparación de búsqueda en anchura y profundidad. Estas señales no certifican la veracidad.</p>
      <div class="kb-grid">
        <div class="kb-panel"><h3>Hechos detectados (memoria inicial)</h3><div class="kb-chipbox" id="hechos-base">Sin análisis</div></div>
        <div class="kb-panel"><h3>Hechos inferidos (memoria final)</h3><div class="kb-chipbox" id="hechos-derivados">Sin análisis</div></div>
        <div class="kb-panel"><h3>Agenda y mecanismo de control</h3><p class="kb-muted">Las reglas aplicables se seleccionan por prioridad descendente.</p><ol class="kb-list" id="agenda-reglas"></ol></div>
        <div class="kb-panel"><h3>Reglas de producción activadas</h3><ol class="kb-list" id="reglas-activadas"></ol></div>
        <div class="kb-panel"><h3>Encadenamiento hacia atrás</h3><div class="kb-proof" id="prueba-atras"><p class="kb-muted">Meta: recomendar_verificacion.</p></div></div>
        <div class="kb-panel"><h3>Comparación de búsqueda sistemática</h3><p class="kb-muted">Cada transición representa una aplicación de una regla.</p><div class="kb-grid" style="grid-template-columns:1fr 1fr;gap:12px">
          <div><h3>BFS (anchura)</h3><div class="kb-stats" id="bfs-estadisticas">—</div><ol class="kb-list" id="bfs-recorrido"></ol></div>
          <div><h3>DFS (profundidad)</h3><div class="kb-stats" id="dfs-estadisticas">—</div><ol class="kb-list" id="dfs-recorrido"></ol></div>
        </div></div>
      </div>
    </section>
  </main>
</div>

<script>
const $ = id => document.getElementById(id);
const text = $('text');
const recents = [];
const EJ = 'URGENTE!!! Científicos NO QUIEREN que sepas esto: el gobierno oculta un remedio secreto que cura todo. Comparte antes de que lo borren. Según fuentes anónimas, millones ya fueron engañados y los medios callan. ¡Despierta!';

text.addEventListener('input', () => {
    $('count').textContent = text.value.length.toLocaleString('es') + ' caracteres';
});
$('btn-limpiar').onclick = () => { text.value = ''; text.dispatchEvent(new Event('input')); };
$('btn-ejemplo').onclick = () => { text.value = EJ; text.dispatchEvent(new Event('input')); };
document.querySelectorAll('.seg button').forEach(b => b.onclick = () => {
    document.querySelectorAll('.seg button').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
});

// Los textos del usuario se muestran con textContent (no con innerHTML).
function itemTexto(tipo, contenido, clase = '') {
    const n = document.createElement(tipo);
    n.textContent = contenido;
    if (clase) n.className = clase;
    return n;
}
function pintarChips(id, valores) {
    const contenedor = $(id);
    contenedor.replaceChildren();
    if (!valores || valores.length === 0) {
        contenedor.append(itemTexto('span', 'Sin hechos en esta categoría', 'kb-muted'));
        return;
    }
    valores.forEach(hecho => contenedor.append(itemTexto('span', hecho, 'kb-chip')));
}
function pintarReglas(id, reglas) {
    const contenedor = $(id);
    contenedor.replaceChildren();
    if (!reglas || reglas.length === 0) {
        contenedor.append(itemTexto('li', 'No hay reglas aplicables.'));
        return;
    }
    reglas.forEach(r => {
        const li = document.createElement('li');
        li.append(itemTexto('strong', r.id + ' · Prioridad ' + r.prioridad));
        li.append(itemTexto('div', 'SI ' + r.si.join(' Y ') + ' ENTONCES ' + r.entonces));
        li.append(itemTexto('div', r.explicacion, 'kb-muted'));
        contenedor.append(li);
    });
}
function nodoPrueba(prueba) {
    const ul = document.createElement('ul');
    const li = document.createElement('li');
    let nombre = prueba.tipo === 'hecho'
        ? 'HECHO: ' + prueba.nombre
        : 'REGLA: ' + prueba.nombre + ' ⇒ ' + prueba.conclusion;
    li.append(itemTexto('strong', nombre));
    (prueba.hijos || []).forEach(h => li.append(nodoPrueba(h)));
    ul.append(li);
    return ul;
}
function pintarBusqueda(estrategia, resultado) {
    const id = estrategia.toLowerCase();
    $(id + '-estadisticas').textContent = resultado.encontrada
        ? `Meta alcanzada · ${resultado.pasos} pasos · ${resultado.explorados} estados explorados`
        : `Meta no alcanzada · ${resultado.explorados} estados explorados${resultado.limite_alcanzado ? ' · límite alcanzado' : ''}`;
    const ol = $(id + '-recorrido');
    ol.replaceChildren();
    resultado.recorrido.forEach(r => ol.append(itemTexto('li', r.id + ' → ' + r.entonces)));
    if (!resultado.recorrido.length) ol.append(itemTexto('li', 'Sin recorrido hasta la meta.'));
}
function pintarHistorial() {
    $('empty').style.display = recents.length ? 'none' : '';
    const contenedor = $('rlist');
    contenedor.replaceChildren();
    recents.slice(0, 6).forEach((x, i) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'ritem';
        boton.dataset.i = String(i);
        boton.append(itemTexto('p', x.t.slice(0, 60) + (x.t.length > 60 ? '…' : '')));
        boton.append(itemTexto('span', x.datos.resultado, 'pill ' + (x.datos.meta_alcanzada ? 'warn' : 'ok')));
        contenedor.append(boton);
    });
}
function render(datos, texto, guardar = true) {
    const root = document.documentElement.style;
    root.setProperty('--accent', datos.meta_alcanzada ? '#a96a0f' : '#1f3a5f');
    root.setProperty('--accent-soft', datos.meta_alcanzada ? '#fbf1de' : '#eaf0f7');
    // Íconos elegidos en el código, no proceden del usuario ni del servidor.
    $('badge').innerHTML = datos.meta_alcanzada
        ? '<i class="fa-solid fa-triangle-exclamation"></i>'
        : '<i class="fa-solid fa-circle-info"></i>';
    $('verdict').textContent = datos.resultado;
    $('vsub').textContent = datos.interpretacion;
    $('class').textContent = datos.meta_alcanzada ? 'REVISAR' : 'SIN META';
    const disparos = datos.reglas_activadas.length;
    $('confnum').textContent = `${disparos} / ${datos.total_reglas}`;
    $('confbar').style.width = (100 * disparos / Math.max(datos.total_reglas, 1)) + '%';
    $('interpretacion').textContent = datos.advertencia + ' ' + datos.interpretacion;
    pintarChips('hechos-base', datos.hechos_iniciales);
    pintarChips('hechos-derivados', datos.hechos_derivados);
    pintarReglas('agenda-reglas', datos.agenda_inicial);
    pintarReglas('reglas-activadas', datos.reglas_activadas);
    const prueba = $('prueba-atras');
    prueba.replaceChildren();
    prueba.append(datos.prueba_hacia_atras
        ? nodoPrueba(datos.prueba_hacia_atras)
        : itemTexto('p', 'No se pudo demostrar la meta con los hechos actuales.', 'kb-muted'));
    pintarBusqueda('BFS', datos.bfs);
    pintarBusqueda('DFS', datos.dfs);
    if (guardar) {
        recents.unshift({ t: texto, datos });
        // Conservar solo los últimos 10 para evitar crecimiento de memoria innecesario.
        if (recents.length > 10) recents.length = 10;
        pintarHistorial();
    }
}
$('rlist').onclick = e => {
    const boton = e.target.closest('.ritem');
    if (!boton) return;
    const x = recents[Number(boton.dataset.i)];
    if (!x) return;
    text.value = x.t;
    text.dispatchEvent(new Event('input'));
    render(x.datos, x.t, false);
};

// Laravel se encarga de ejecutar las reglas: NO es la futura API de SaBERT.
$('btn-analizar').onclick = async () => {
    const t = text.value.trim();
    if (t.length < 20) {
        text.focus();
        $('vsub').textContent = 'Escribe al menos 20 caracteres.';
        return;
    }
    if (t.length > 5000) {
        $('vsub').textContent = 'El texto no puede superar los 5000 caracteres.';
        return;
    }
    const btn = $('btn-analizar'), res = $('result');
    btn.disabled = true;
    btn.textContent = 'Analizando…';
    res.classList.add('scan');
    $('badge').innerHTML = '<i class="fa-solid fa-spinner"></i>';
    $('verdict').textContent = 'Procesando';
    try {
        const respuesta = await fetch(btn.dataset.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ texto: t })
        });
        if (!respuesta.ok) {
            if (respuesta.status === 419) throw new Error('La sesión expiró. Actualiza la página y vuelve a intentarlo.');
            if (respuesta.status === 422) throw new Error('El texto no cumple con los requisitos (20 a 5000 caracteres).');
            throw new Error('No fue posible procesar el texto (HTTP ' + respuesta.status + ').');
        }
        const datos = await respuesta.json();
        render(datos, t);
    } catch (error) {
        $('verdict').textContent = 'Error de análisis';
        $('vsub').textContent = error.message;
    } finally {
        res.classList.remove('scan');
        btn.disabled = false;
        btn.textContent = 'Analizar noticia';
    }
};
</script>
</body>
</html>
