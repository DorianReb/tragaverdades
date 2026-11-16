"""Integración opt-in de URL y texto con Laravel/FastAPI reales y casos UI controlados.

Requiere Playwright, Chrome y ambos servidores activos. No escribe en la BD.
Solo descarga el artículo público solicitado explícitamente en --article-url.
Ver docs/analisis-url.md para ejecutar y configurar los tiempos del servidor.
"""

import argparse
import copy
import json
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright


ARTICULO_PUBLICO = (
    "https://ciencia.nasa.gov/sistema-solar/"
    "la-nasa-halla-nueva-forma-en-que-la-tierra-pudo-haber-obtenido-los-elementos-necesarios-para-la-vida/"
)


def comprobar_resultado(page, datos):
    ia = datos["sabert"]
    assert ia["disponible"], ia["mensaje"]
    assert ia["clasificacion"] in {"True", "False"}
    assert ia["cobertura_porcentaje"] == 100
    assert datos["total_reglas"] == 17
    expect(page.locator("#sabert-clase")).to_have_text("Clasificación global: " + ia["clasificacion"])
    for etiqueta in ("True", "False"):
        valor = f"{100 * ia['probabilidades'][etiqueta]:.2f}".replace(".", ",") + " %"
        expect(page.locator("#sabert-" + etiqueta.lower())).to_have_text(valor)
    expect(page.locator("#sabert-fragmentos-total")).to_have_text(str(ia["cantidad_fragmentos"]))
    expect(page.locator("#sabert-cobertura")).to_have_text("100 %")
    expect(page.locator(".sabert-fragment")).to_have_count(ia["cantidad_fragmentos"])
    expect(page.locator("#bfs-estadisticas")).not_to_have_text("—")
    expect(page.locator("#dfs-estadisticas")).not_to_have_text("—")
    expect(page.locator("#btn-analizar")).to_be_enabled()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8010")
    parser.add_argument("--article-url", default=ARTICULO_PUBLICO)
    parser.add_argument("--screenshots", type=Path)
    args = parser.parse_args()
    output = args.screenshots or Path(tempfile.mkdtemp(prefix="tragaverdades-url-ui-"))
    output.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 1000})
        errores = []
        peticiones = []
        page.on("pageerror", lambda error: errores.append(str(error)))
        page.on("request", lambda request: peticiones.append(request.url) if "/analisis-" in request.url else None)
        page.goto(args.base_url)
        page.wait_for_load_state("networkidle")
        expect(page.get_by_role("button", name="Analizar texto", exact=True)).to_have_attribute("aria-pressed", "true")
        expect(page.locator("#entrada-url")).to_be_hidden()
        expect(page.locator("#noticia-extraida")).to_be_hidden()
        fuente = page.locator("body").evaluate("el => getComputedStyle(el).fontFamily")
        assert "Plus Jakarta Sans" in fuente
        assert page.locator("body").evaluate("el => getComputedStyle(el).backgroundColor") == "rgb(242, 244, 247)"

        # Análisis manual real, conservado como borrador e historial al pasar a URL.
        texto = "El ayuntamiento anunció nuevas obras de mantenimiento en las escuelas de la ciudad durante noviembre. Las familias podrán consultar los horarios en el edificio municipal."
        page.locator("#text").fill(texto)
        with page.expect_response(lambda r: r.url.endswith("/analisis-ia"), timeout=150000) as r:
            page.locator("#btn-analizar").click()
        assert r.value.ok, r.value.text()
        manual = r.value.json()
        comprobar_resultado(page, manual)
        expect(page.locator("#noticia-extraida")).to_be_hidden()

        page.get_by_role("button", name="Analizar URL", exact=True).focus()
        page.keyboard.press("Enter")
        expect(page.locator("#entrada-url")).to_be_visible()
        expect(page.locator("#entrada-texto")).to_be_hidden()
        page.locator("#url").fill(args.article_url)
        with page.expect_response(lambda r: r.url.endswith("/analisis-url"), timeout=180000) as r:
            page.locator("#btn-analizar").click()
        assert r.value.ok, r.value.text()
        datos = r.value.json()
        comprobar_resultado(page, datos)
        noticia = datos["noticia"]
        ia = datos["sabert"]
        assert ia["cantidad_fragmentos"] > 1, "Elegir un artículo público suficientemente largo para comprobar chunking"
        assert ia["ventana_tokens"] == 128 and ia["solapamiento_tokens"] == 32
        expect(page.locator("#noticia-extraida")).to_be_visible()
        expect(page.locator("#noticia-titulo")).to_have_text(noticia["titulo"])
        expect(page.locator("#noticia-dominio")).to_have_text(noticia["dominio"])
        for campo, id in (("autor", "autor"), ("fecha_publicacion", "fecha")):
            if noticia[campo]:
                expect(page.locator("#noticia-" + id)).to_have_text(noticia[campo])
            else:
                expect(page.locator("#noticia-" + id + "-fila")).to_be_hidden()
        expect(page.locator("#noticia-enlace")).to_have_attribute("href", args.article_url)
        expect(page.locator("#noticia-enlace")).to_have_attribute("rel", "noopener noreferrer")
        assert page.locator("#noticia-contenido").text_content() == noticia["contenido"]
        assert "\n\n" in noticia["contenido"]
        expect(page.locator("#text")).to_have_value(texto)

        page.locator("#sabert-fragmentos-resumen").focus()
        page.keyboard.press("Enter")
        expect(page.locator("#sabert-detalle-fragmentos")).to_have_attribute("open", "")
        expect(page.locator(".sabert-fragment").last).to_contain_text(f"Fragmento {ia['cantidad_fragmentos']}")
        for ancho in (1440, 768, 390, 320):
            page.set_viewport_size({"width": ancho, "height": 1000})
            assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"), f"Overflow a {ancho}px"
            for selector, nombre in (("#entrada-noticia", "entrada"), ("#noticia-extraida", "noticia"), ("#resultado-sabert", "sabert")):
                page.locator(selector).screenshot(path=str(output / f"{nombre}-{ancho}.png"),
                                                 style="header { visibility: hidden; }", animations="disabled")

        # Restaurar ambos tipos sin repetir peticiones ni perder metadatos.
        cantidad = len(peticiones)
        page.locator("#rlist .ritem").nth(1).click()
        expect(page.locator("#modo-texto")).to_have_attribute("aria-pressed", "true")
        expect(page.locator("#text")).to_have_value(texto)
        expect(page.locator("#noticia-extraida")).to_be_hidden()
        comprobar_resultado(page, manual)
        page.locator("#rlist .ritem").first.click()
        expect(page.locator("#modo-url")).to_have_attribute("aria-pressed", "true")
        expect(page.locator("#url")).to_have_value(args.article_url)
        comprobar_resultado(page, datos)
        expect(page.locator("#noticia-titulo")).to_have_text(noticia["titulo"])
        assert len(peticiones) == cantidad

        # Respuesta retenida: comprobar estados de carga y evitar carreras con historial/modos.
        pendientes = []
        page.route("**/analisis-url", lambda route: pendientes.append(route))
        page.locator("#btn-analizar").click()
        expect(page.locator("#entrada-noticia")).to_have_attribute("aria-busy", "true")
        expect(page.locator("#input-estado")).to_contain_text("Extrayendo")
        for selector in ("#btn-analizar", "#modo-texto", "#modo-url", "#url", "#btn-limpiar", "#rlist .ritem"):
            for elemento in page.locator(selector).all():
                expect(elemento).to_be_disabled()
        expect(page.locator("#noticia-extraida")).to_be_hidden()
        expect(page.locator("#bfs-estadisticas")).to_have_text("—")

        # HTML externo y enlaces peligrosos jamás se interpretan como DOM/JavaScript.
        maliciosa = copy.deepcopy(datos)
        payload = '<img src=x onerror="window.injected=true">'
        maliciosa["noticia"].update(titulo=payload, autor=None, fecha_publicacion=None,
                                   dominio=payload, contenido=payload + "\n\nTexto de prueba.",
                                   url_original="javascript:window.injected=true", url_final="data:text/html,payload")
        pendientes.pop().fulfill(json=maliciosa)
        expect(page.locator("#btn-analizar")).to_be_enabled()
        expect(page.locator("#noticia-titulo")).to_have_text(payload)
        expect(page.locator("#noticia-dominio")).to_have_text(payload)
        expect(page.locator("#noticia-autor-fila")).to_be_hidden()
        expect(page.locator("#noticia-fecha-fila")).to_be_hidden()
        expect(page.locator("#noticia-enlace")).to_be_hidden()
        expect(page.locator("#noticia-enlace-final")).to_be_hidden()
        assert page.locator("#noticia-extraida img, #rlist img").count() == 0
        assert page.locator("#noticia-contenido").text_content().startswith(payload)
        assert not page.evaluate("window.injected || false")

        page.unroute("**/analisis-url")
        historial = page.locator("#rlist .ritem").count()
        casos = [
            (403, {"message": "El sitio restringe el acceso a esta noticia."}, "restringe el acceso"),
            (413, {"message": "El artículo supera el límite permitido."}, "supera el límite"),
            (415, {"message": "La página no contiene HTML compatible."}, "HTML compatible"),
            (422, {"errors": {"url": ["Introduce una URL válida."]}}, "URL válida"),
            (422, {"message": "No se encontró suficiente texto público."}, "suficiente texto"),
            (502, {"message": "No fue posible descargar la noticia."}, "descargar la noticia"),
            (503, {"message": "Servicio de extracción no disponible."}, "no disponible"),
            (504, {"message": "Se agotó el tiempo de extracción."}, "tiempo de extracción"),
            (419, {}, "La sesión expiró"), (429, {}, "límite de solicitudes"),
            (502, {"message": payload}, payload),
        ]
        for estado, cuerpo, mensaje in casos:
            page.route("**/analisis-url", lambda route, request, s=estado, c=cuerpo: route.fulfill(status=s, json=c))
            page.locator("#btn-analizar").click()
            expect(page.locator("#input-estado")).to_contain_text(mensaje)
            expect(page.locator("#btn-analizar")).to_be_enabled()
            expect(page.locator("#entrada-noticia")).to_have_attribute("aria-busy", "false")
            expect(page.locator("#noticia-extraida")).to_be_hidden()
            expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()
            expect(page.locator("#sabert-true")).to_have_text("—")
            expect(page.locator("#sabert-cobertura")).to_have_text("—")
            expect(page.locator("#bfs-estadisticas")).to_have_text("—")
            expect(page.locator("#dfs-estadisticas")).to_have_text("—")
            assert page.locator("#input-estado img").count() == 0
            page.unroute("**/analisis-url")
        expect(page.locator("#rlist .ritem")).to_have_count(historial)

        page.route("**/analisis-url", lambda route: route.fulfill(status=200, body="<html>Error</html>", content_type="text/html"))
        page.locator("#btn-analizar").click()
        expect(page.locator("#input-estado")).to_contain_text("respuesta no válida")
        expect(page.locator("#btn-analizar")).to_be_enabled()
        page.unroute("**/analisis-url")
        page.route("**/analisis-url", lambda route: route.abort())
        page.locator("#btn-analizar").click()
        expect(page.locator("#input-estado")).to_contain_text("No fue posible conectar con el servidor")
        expect(page.locator("#btn-analizar")).to_be_enabled()
        page.unroute("**/analisis-url")

        # Timeout del navegador configurable, sin esperar minutos en pruebas.
        page.locator("#btn-analizar").evaluate("el => el.dataset.urlTimeout = '50'")
        page.route("**/analisis-url", lambda route: pendientes.append(route))
        page.locator("#btn-analizar").click()
        expect(page.locator("#input-estado")).to_contain_text("tiempo de espera del análisis")
        expect(page.locator("#btn-analizar")).to_be_enabled()
        for route in pendientes:
            route.abort()
        page.unroute("**/analisis-url")

        # Validación local de URL no válida y conservación del historial al limpiar.
        cantidad = len(peticiones)
        for valor in ("", "ftp://example.org/noticia", "javascript:alert(1)", "https://usuario:clave@example.org/"):
            page.locator("#url").fill(valor)
            page.locator("#btn-analizar").click()
            expect(page.locator("#input-estado")).to_contain_text("URL completa HTTP o HTTPS")
        assert len(peticiones) == cantidad
        page.locator("#btn-limpiar").click()
        expect(page.locator("#url")).to_have_value("")
        expect(page.locator("#noticia-extraida")).to_be_hidden()
        expect(page.locator("#rlist .ritem")).to_have_count(historial)
        assert not errores, errores
        browser.close()

    resumen = {
        "url": args.article_url, "caracteres": len(noticia["contenido"]),
        "fragmentos": ia["cantidad_fragmentos"], "tokens_originales": ia["total_tokens_originales"],
        "tokens_procesados": ia["tokens_procesados"], "cobertura": ia["cobertura_porcentaje"],
        "pruebas": "URL y texto reales; reglas, BFS/DFS, historial mixto, carga, errores, timeout, XSS, teclado y 4 anchos",
    }
    (output / "resultado.json").write_text(json.dumps(resumen, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(resumen, ensure_ascii=True))
    print(f"Capturas: {output}")


if __name__ == "__main__":
    main()
