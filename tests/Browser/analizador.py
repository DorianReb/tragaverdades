"""Prueba opcional de navegador con Laravel y FastAPI reales, sin escribir en la BD.

Requiere Playwright y Chrome. Ver docs/sabert.md para iniciar ambos servidores.
"""

import argparse
import copy
import json
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8010")
    parser.add_argument("--screenshots", type=Path)
    args = parser.parse_args()
    output = args.screenshots or Path(tempfile.mkdtemp(prefix="tragaverdades-ui-"))
    output.mkdir(parents=True, exist_ok=True)
    fixture = json.loads((Path(__file__).parents[1] / "Fixtures/sabert-chunks.json").read_text(encoding="utf-8"))

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 1000})
        errors = []
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.goto(args.base_url)
        page.wait_for_load_state("networkidle")
        expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()
        page.locator("#text").fill(fixture["texto_analizado"])
        with page.expect_response(lambda r: r.url.endswith("/analisis-ia"), timeout=150000) as response:
            page.locator("#btn-analizar").click()
        data = response.value.json()
        assert response.value.ok, (response.value.status, data)
        ia = data["sabert"]
        assert ia["disponible"], ia["mensaje"]
        assert ia["cantidad_fragmentos"] == len(fixture["fragmentos"])
        assert ia["cobertura_porcentaje"] == 100
        expect(page.locator("#sabert-clase")).to_have_text("Clasificación global: " + ia["clasificacion"])
        for label in ["True", "False"]:
            expected = f"{ia['probabilidades'][label] * 100:.2f}".replace(".", ",") + " %"
            expect(page.locator("#sabert-" + label.lower())).to_have_text(expected)
        expect(page.locator("#sabert-fragmentos-total")).to_have_text(str(ia["cantidad_fragmentos"]))
        expect(page.locator("#sabert-cobertura")).to_have_text("100 %")
        expect(page.locator("#bfs-estadisticas")).not_to_have_text("—")
        expect(page.locator("#dfs-estadisticas")).not_to_have_text("—")
        assert data["total_reglas"] == 17
        summary = page.locator("#sabert-fragmentos-resumen")
        summary.focus()
        page.keyboard.press("Enter")
        expect(page.locator("#sabert-detalle-fragmentos")).to_have_attribute("open", "")
        expect(page.locator(".sabert-fragment")).to_have_count(ia["cantidad_fragmentos"])
        expect(page.locator(".sabert-fragment").last).to_contain_text(f"Fragmento {ia['cantidad_fragmentos']}")
        expect(page.locator(".sabert-fragment").last).to_contain_text("True")
        expect(page.locator(".sabert-fragment").last).to_contain_text("False")
        for width in [1440, 768, 390, 320]:
            page.set_viewport_size({"width": width, "height": 1000})
            page.locator("#resultado-sabert").scroll_into_view_if_needed()
            assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"), f"Overflow at {width}px"
            page.locator("#resultado-sabert").screenshot(path=str(output / f"sabert-{width}.png"),
                                                       style="header { visibility: hidden; }", animations="disabled")

        # Simular fallos de transporte y respuestas de versiones anteriores en la UI.
        # Las pruebas PHP verifican por separado su interpretación en el servidor.
        def mock(result, status=200):
            page.unroute("**/analisis-ia")
            page.route("**/analisis-ia", lambda route: route.fulfill(status=status, json=result))

        async_result = copy.deepcopy(data)
        async_result["sabert"].update(disponible=False, error="no_disponible", clasificacion=None,
                                     probabilidades=None, fragmentos=[], mensaje="FastAPI no está disponible. El análisis por reglas sigue disponible.")
        mock(async_result)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-clase")).to_have_text("SaBERT no disponible")
        expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()
        expect(page.locator("#sabert-cobertura")).to_have_text("—")
        expect(page.locator("#sabert-true")).to_have_text("—")
        expect(page.locator("#bfs-estadisticas")).not_to_have_text("—")
        page.locator("#rlist .ritem").nth(1).click()
        expect(page.locator("#sabert-fragmentos-total")).to_have_text(str(ia["cantidad_fragmentos"]))
        expect(page.locator("#sabert-detalle-fragmentos")).not_to_have_attribute("open", "")

        async_result["sabert"].update(error="limite_documento", mensaje="El documento excede los límites de FastAPI. Reduce el texto.")
        mock(async_result)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-clase")).to_have_text("Documento fuera de límite")
        expect(page.locator("#sabert-estado")).to_contain_text("Reduce el texto")
        legacy = copy.deepcopy(data)
        legacy["sabert"].update(fragmentos=[], cantidad_fragmentos=None, cobertura_porcentaje=None)
        mock(legacy)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-detalle-estado")).to_contain_text("no informa el detalle")
        expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()

        # Un mensaje remoto debe mostrarse como texto, sin ejecutarse como HTML.
        legacy["sabert"]["advertencia"] = '<img src=x onerror="window.injected=true">'
        mock(legacy)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-advertencia")).to_have_text(legacy["sabert"]["advertencia"])
        assert page.locator("#sabert-advertencia img").count() == 0
        mock({"errors": {"texto": ["El documento supera el límite del servidor."]}}, 422)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-estado")).to_contain_text("supera el límite")
        expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()

        page.locator("#text").fill("a" * 100001)
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-clase")).to_have_text("Documento no admitido")
        expect(page.locator("#sabert-estado")).to_contain_text("Reduce el texto")
        page.locator("#text").fill("corto")
        page.locator("#btn-analizar").click()
        expect(page.locator("#sabert-estado")).to_have_text("Escribe al menos 20 caracteres.")

        page.unroute("**/analisis-ia")
        page.locator("#text").fill("sol " * 21000)
        with page.expect_response(lambda r: r.url.endswith("/analisis-ia"), timeout=150000) as response:
            page.locator("#btn-analizar").click()
        oversized = response.value.json()
        assert response.value.ok and oversized["total_reglas"] == 17
        assert oversized["sabert"]["error"] == "limite_documento", oversized["sabert"]
        expect(page.locator("#sabert-clase")).to_have_text("Documento fuera de límite")
        expect(page.locator("#sabert-detalle-fragmentos")).to_be_hidden()
        rules = page.request.post(args.base_url + "/analisis-reglas",
                                  data={"texto": "Noticia sintética de prueba para el motor simbólico."},
                                  headers={"Accept": "application/json", "X-CSRF-TOKEN": page.locator('meta[name="csrf-token"]').get_attribute("content")})
        assert rules.ok and rules.json()["total_reglas"] == 17
        assert "sabert" not in rules.json()
        assert not errors, errors
        browser.close()
        print(f"OK: FastAPI real, {ia['cantidad_fragmentos']} fragmentos, porcentajes, reglas, teclado, historial, errores, compatibilidad, XSS y 4 anchos. Capturas: {output}")


if __name__ == "__main__":
    main()
