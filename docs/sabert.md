# SaBERT: análisis completo por fragmentos

Laravel envía el documento completo a FastAPI desde `SaBERTService`. FastAPI carga el modelo, tokeniza, fragmenta e infiere; PHP no reproduce estas operaciones. No se escriben análisis en la base de datos ni se modifican `MotorReglas.php` o las tablas.

`POST /analisis-ia` recibe `{"texto":"..."}` y devuelve todos los campos del motor simbólico, añadiendo `sabert`. Admite de 20 a 100 000 caracteres por defecto. Se recortan únicamente los espacios exteriores, sin truncar el contenido. `POST /analisis-reglas` conserva su contrato y su límite anterior de 5000 caracteres; no consulta FastAPI.

## Configuración y arranque

En el entorno Python de tu API, inicia `uvicorn main:app --host 127.0.0.1 --port 8001` y espera a que `GET /health` indique que el modelo está disponible.

Laravel usa estos valores de `config/services.php` (también incluidos en `.env.example`):

| Variable | Predeterminado |
| --- | --- |
| `SABERT_URL` | `http://127.0.0.1:8001` |
| `SABERT_ENDPOINT` | `/analizar` |
| `SABERT_TEXT_FIELD` | `texto` |
| `SABERT_MAX_CHARACTERS` | `100000` |
| `SABERT_CONNECT_TIMEOUT` | `3` segundos |
| `SABERT_TIMEOUT` | `120` segundos |

Revisa las variables `SABERT_*` de tu `.env`: los valores antiguos `/predict` y `text` anulan los nuevos predeterminados. Después de cambiarlos ejecuta `php artisan config:clear`. El límite de caracteres de Laravel debe corresponder al configurado en FastAPI. El timeout es configurable para documentos largos; no hay reintentos automáticos de inferencia.

La API consultada usa ventanas de **128 tokens**, solapamiento de **32 tokens** y lotes de **4 fragmentos**. La ventana incluye tokens especiales; con este tokenizador quedan 126 tokens de contenido y se avanza 94 posiciones. El tamaño del lote no es un campo de la respuesta y no se inventa en la interfaz. Los límites de FastAPI por defecto son 20 000 tokens originales, 256 fragmentos y 100 000 caracteres; los límites de tokens y fragmentos los decide FastAPI, no una estimación por caracteres en PHP.

## Contrato real de FastAPI

Verificado el **13 de noviembre de 2026**, antes de modificar el adaptador, consultando `POST http://127.0.0.1:8001/analizar` y `/openapi.json`, además del `main.py` local. Los JSON completos recibidos se guardan en `tests/Fixtures/sabert-*.json`. Las noticias de prueba son texto sintético, sin datos privados.

Entrada: `{"texto":"Noticia de al menos veinte caracteres..."}`. La respuesta conserva los campos anteriores:

| Campo | Tipo / significado |
| --- | --- |
| `texto_analizado`, `modelo`, `dispositivo` | `string` |
| `clasificacion` | `string`: `True` o `False` |
| `confianza_porcentaje` | `number`, 0 a 100 |
| `probabilidades` | Objeto con `True` y `False`, **porcentajes de 0 a 100**, globales redondeados a dos decimales |
| `tokens_procesados` | `integer`: contenido y especiales de todas las ventanas, sin padding, incluye repeticiones |
| `texto_truncado` | `boolean`; `false` en documentos aceptados por esta versión |
| `advertencia` | `string`: limitaciones estadísticas del resultado |

Nuevos campos confirmados:

| Campo | Tipo / significado |
| --- | --- |
| `fragmentos` | Lista ordenada de resultados individuales |
| `cantidad_fragmentos` | `integer`, cantidad de ventanas analizadas |
| `total_tokens_originales` | `integer`, contenido original sin especiales |
| `tokens_contenido_procesados` | `integer`, contenido procesado con repeticiones por solapamiento |
| `tokens_cubiertos` | `integer`, posiciones únicas de contenido cubiertas |
| `cobertura_porcentaje` | `number`, `100 * tokens_cubiertos / total_tokens_originales` |
| `ventana_tokens`, `solapamiento_tokens` | `integer`, configuración efectiva |
| `metodo_agregacion` | Objeto descrito a continuación |

Cada fragmento contiene `indice` (entero desde **0**), `inicio_token`, `fin_token` (exclusivo), `tokens_contenido`, `tokens` (cantidad con especiales, sin padding), `clasificacion`, `confianza_porcentaje` y `probabilidades`. Los porcentajes de cada fragmento llegan **sin redondear**. La API no devuelve el texto decodificado de cada fragmento; la interfaz identifica sus rangos de tokens.

`metodo_agregacion` contiene `nombre="promedio_probabilidades"`, `ponderacion="uniforme_por_fragmento"`, `probabilidad_calibrada=false` y `verificacion_factual=false`. La clasificación global procede del promedio uniforme de las probabilidades de todos los fragmentos, no de una votación de sus etiquetas. Laravel conserva la clasificación de FastAPI incluso cuando los porcentajes redondeados empatan.

En la captura real de varios fragmentos: **7199 caracteres**, **1200 tokens originales**, **13 fragmentos**, **1584 tokens de contenido procesados**, **1610 tokens procesados**, **100 % de cobertura**. La clasificación fue `True` y las probabilidades globales fueron `True: 76.48`, `False: 23.52`. Estos valores son una captura de referencia, no expectativas universales para otras noticias o versiones del modelo.

## Adaptación y compatibilidad en Laravel

Se conservan `sabert.disponible`, `clasificacion`, `probabilidades` y `mensaje`. Para conservar el contrato PHP anterior, **`sabert.probabilidades` y `sabert.fragmentos[].probabilidades` se expresan en fracciones 0 a 1**; la vista las convierte a porcentajes exactamente una vez. `confianza_porcentaje` y `cobertura_porcentaje` conservan su escala 0 a 100. El resto de los metadatos mantiene los nombres de FastAPI. No se devuelve otra copia de `texto_analizado` dentro de `sabert`.

El servicio también acepta la respuesta anterior de FastAPI en español, sin fragmentos, y el contrato provisional `probabilities: {True: 0.8, False: 0.2}` que ya admitía Laravel. Solo para ese contrato sin clasificación se deriva la etiqueta de la probabilidad mayor (empate: `True`). No se convierte `LABEL_0` o `LABEL_1` en etiquetas conocidas.

Los datos no informados por una versión anterior quedan en `null` y `fragmentos=[]`; no se inventan fragmentos ni cobertura del 100 %. Cuando hay fragmentos se validan etiquetas, probabilidades, cantidades, rangos, solapamiento y coherencia de cobertura y totales antes de presentar el resultado.

Ante errores remotos se conserva la respuesta simbólica con HTTP 200 y `sabert.disponible=false`, probabilidades `null`, fragmentos vacíos y un mensaje para el usuario. `sabert.error` distingue:

| Error | Causa |
| --- | --- |
| `limite_documento` | HTTP 413 por tokens/fragmentos, o HTTP 422 con `detail[].type="string_too_long"` |
| `documento_invalido` | Otros errores de validación HTTP 422 de FastAPI |
| `no_disponible` | Conexión rechazada, timeout o error HTTP del servicio, incluido 503 |
| `respuesta_invalida` | JSON, probabilidades o estadísticas incoherentes |

La validación local de entrada devuelve HTTP 422 con `errors.texto` antes de consultar FastAPI; la vista muestra ese mensaje. No se registran noticias, cuerpos remotos ni mensajes de excepción. No se presentan resultados parciales como si cubrieran todo el documento.

## Interfaz e interpretación

El dashboard conserva el resultado global `True`/`False`, ambos porcentajes y los resultados de las 17 reglas, BFS, DFS y encadenamiento hacia atrás. Añade estadísticas de fragmentos, tokens originales/procesados y cobertura, más un desplegable accesible por teclado con **todos** los fragmentos, numerados desde 1 para su lectura, su clasificación, rango y ambos porcentajes. La cuadrícula se adapta a móvil y la lista permite desplazamiento para documentos largos. El historial sigue siendo temporal, en memoria del navegador.

La advertencia permanece visible: **la clasificación global es una agregación estadística, no una verificación de hechos; las probabilidades no representan veracidad factual**. También se muestra la advertencia recibida de la API. El solapamiento repite contenido, por lo que los tokens procesados pueden superar a los originales; esto no implica cobertura superior al 100 %.

Al iniciar otro análisis, fallar una petición o seleccionar un resultado anterior se actualizan o limpian el detalle y sus estadísticas para no mostrar fragmentos de otra noticia. Las cadenas recibidas se insertan con `textContent`.

## Pruebas

```powershell
php artisan test
php vendor/bin/pint --test app/Services/SaBERTService.php app/Http/Controllers/AnalisisIAController.php tests/Feature/AnalisisIATest.php config/services.php
npm run build
```

Las pruebas PHP usan HTTP simulado con las capturas reales: uno y varios fragmentos, porcentajes, metadatos, compatibilidad anterior, documentos de más de 5000 caracteres, límites locales/remotos, desconexión y respuestas inválidas. Comprueban que `/analisis-reglas` conserva exactamente sus resultados, su límite anterior y su independencia de FastAPI. No requieren un modelo activo, migraciones ni una base de datos persistente.

Prueba opcional de navegador: requiere FastAPI activo, Python con `pip install playwright` y Chrome instalado. Inicia Laravel en otra terminal con sesiones en archivos y caché en memoria para no usar tablas:

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'array'
php artisan serve --host=127.0.0.1 --port=8010 --no-reload
```

```powershell
python tests/Browser/analizador.py --base-url http://127.0.0.1:8010
```

El script envía una noticia sintética a la integración real, verifica todos los fragmentos, porcentajes y resultados simbólicos, y comprueba teclado, historial y presentación a 1440, 768, 390 y 320 px. Después simula errores y respuestas anteriores en el navegador para comprobar limpieza, mensajes y tratamiento de HTML como texto. También verifica un rechazo real por superar los 20 000 tokens y una llamada directa a `/analisis-reglas`. Asume los límites predeterminados de FastAPI. Guarda capturas en una carpeta temporal (o la indicada mediante `--screenshots`).
