# Análisis de noticias por URL

El dashboard conserva el modo **Analizar texto** y añade **Analizar URL**.
Ambos utilizan el mismo motor de reglas y el mismo adaptador `SaBERTService`.
No se modifica `MotorReglas.php`, el modelo de SaBERT ni la base de datos.

## Revisión y contrato de FastAPI

Antes de modificar se revisaron el árbol del proyecto, las rutas, controladores,
servicios, configuración, vistas, assets, pruebas y documentación. La suite
inicial tenía **51 pruebas correctas, con 451 aserciones**.

El contrato se verificó el **16 de noviembre de 2026** mediante
`http://127.0.0.1:8001/openapi.json`, la documentación del servicio Python y una
solicitud real al extractor. `POST /extraer-url` recibe `{"url":"https://..."}`
y devuelve estos campos:

| Campo de FastAPI | Tipo | Tratamiento en Laravel |
| --- | --- | --- |
| `titulo` | `string` o `null` | Se muestra cuando existe |
| `autor` | `string` o `null` | Se muestra cuando existe |
| `fecha_publicacion` | `string` ISO o `null` | Se conserva la fecha publicada, incluida su zona cuando exista |
| `dominio` | `string` | Se muestra como texto |
| `url_final` | `string` | Se valida HTTP/HTTPS antes de ofrecer un enlace |
| `contenido` | `string` | Se envía completo al análisis actual, conservando párrafos |

No se usan alias como `title`, `author`, `text` o `published_at` para la
extracción. Los metadatos opcionales omitidos por el servicio se normalizan a
`null`; los tipos incorrectos y la ausencia de contenido, dominio o URL final
producen un error de respuesta inválida. No se inventan fechas ni autores.

La fixture `tests/Fixtures/noticia-extraida.json` contiene texto **sintético**
con este contrato. Los artículos públicos de integración no se guardan completos
en el repositorio.

## Flujo y rutas

1. El navegador envía `POST /analisis-url` con `{"url":"https://..."}` a Laravel.
2. `ExtractorNoticiasService` llama únicamente a la URL configurada de FastAPI,
   con `/extraer-url`. La URL del usuario se envía en el JSON; PHP no descarga
   directamente el sitio periodístico.
3. El servicio comprueba la respuesta y el límite de caracteres de Laravel.
4. `AnalisisIAController::analizarTexto` recibe el contenido. Esta misma función
   atiende el análisis manual: recorta solo espacios exteriores, ejecuta
   `MotorReglas` y llama una vez a `SaBERTService` con todo el texto admitido.
5. FastAPI realiza el chunking y devuelve su clasificación global y fragmentos.
   Laravel mantiene la adaptación anterior de probabilidades a escala 0–1.

No se dividen noticias en PHP ni en JavaScript. Las ventanas de 128 tokens y
el solapamiento de 32 siguen configurados en FastAPI. El motor simbólico analiza
el mismo contenido; no se le anteponen título, autor, dominio ni URL, que podrían
alterar artificialmente los hechos detectados.

`POST /analisis-ia`, `POST /analisis-reglas`, autenticación y el resto de las
rutas conservan sus contratos. La nueva ruta usa el grupo web, CSRF y el límite
`throttle:30,1`, como el análisis anterior.

La respuesta correcta de `/analisis-url` contiene todos los campos actuales de
reglas y `sabert`, más un objeto `noticia`:

```json
{
  "noticia": {
    "titulo": null,
    "autor": null,
    "fecha_publicacion": null,
    "dominio": "medio.example.org",
    "url_final": "https://medio.example.org/articulo",
    "contenido": "Texto del artículo...\n\nOtro párrafo...",
    "url_original": "https://medio.example.org/enlace"
  }
}
```

El ejemplo muestra únicamente el objeto añadido. **`url_original` lo añade
Laravel** a partir de la entrada validada; no es un campo de FastAPI. La URL
final sigue siendo la devuelta por el extractor. El navegador ofrece ambos
enlaces cuando difieren, con `rel="noopener noreferrer"`.

## Interfaz e historial

Los selectores de modo reutilizan los colores y formas del dashboard. Se
mantienen Plus Jakarta Sans, las tarjetas, los componentes de SaBERT y la
adaptación móvil. El borrador de texto y el campo URL se conservan al alternar.

Durante el procesamiento se muestra «Extrayendo y analizando…», con estado
accesible mediante `aria-live` y `aria-busy`. El selector, los campos, los botones
y el historial quedan deshabilitados hasta terminar, evitando resultados de
peticiones que correspondan a otra entrada.

El resultado de URL añade título, autor, fecha y dominio disponibles, enlaces
y una vista desplegable del contenido analizado con párrafos. Los metadatos
ausentes se ocultan. Se mantienen clasificación `True`/`False`, ambas
probabilidades globales, todos los fragmentos, tokens, cobertura y los resultados
de las 17 reglas, BFS, DFS y encadenamiento hacia atrás.

El historial sigue en memoria del navegador: conserva hasta diez resultados y
muestra los seis más recientes. Distingue texto manual y URL, guarda los
metadatos y el contenido, y restaura el modo y el resultado sin repetir descargas
ni inferencias. Recargar la página elimina ese historial; no hay persistencia
en base de datos.

Al cambiar de modo, limpiar, empezar una petición o producirse un error se
limpian los resultados previos, incluidos metadatos, reglas y fragmentos. Un
error no añade una entrada al historial. Si falla únicamente SaBERT después de
extraer, se conservan la noticia y las reglas con el mismo estado
`sabert.disponible=false` que en el análisis manual.

El contenido externo se inserta mediante `textContent`, nunca mediante
`innerHTML`. Los pocos iconos que usan `innerHTML` son cadenas constantes del
código. El servidor y el navegador validan el esquema de los enlaces; no se
aceptan enlaces `javascript:` o `data:` ni credenciales en el enlace final.

## Configuración y tiempos

Se reutilizan `SABERT_URL`, `SABERT_ENDPOINT`, `SABERT_TEXT_FIELD`,
`SABERT_MAX_CHARACTERS`, `SABERT_CONNECT_TIMEOUT` y `SABERT_TIMEOUT`.

| Variable nueva | Predeterminado | Uso |
| --- | --- | --- |
| `SABERT_EXTRACTION_ENDPOINT` | `/extraer-url` | Ruta del extractor en FastAPI |
| `SABERT_EXTRACTION_TIMEOUT` | `30` s | Espera máxima de Laravel por extracción |

La extracción usa el mismo `SABERT_CONNECT_TIMEOUT` (3 s) y el mismo servidor
FastAPI que el análisis. No sigue redirecciones HTTP del backend y no reintenta
automáticamente. Las redirecciones de los sitios periodísticos las valida
exclusivamente el extractor seguro de Python.

FastAPI limita su fase de descarga a 20 s por defecto. Laravel permite 30 s
para extracción y otros 120 s para inferencia. El navegador reserva la suma
más 15 s de margen: **165 s para URL y 135 s para texto** con los valores
predeterminados. Un timeout del navegador no garantiza que una inferencia
ya iniciada en el servidor se interrumpa.

Tras cambiar `.env`, ejecuta `php artisan config:clear`. Revisa los valores
antiguos `/predict` y `text` si aparecen en tu instalación: se mantiene su
compatibilidad, pero el FastAPI actual usa `/analizar` y `texto`.

Para artículos extensos, PHP y cualquier proxy deben permitir una petición
de al menos la suma de ambos tiempos más margen (180 s con estos valores).
En XAMPP/Apache ajusta `max_execution_time` y el timeout del servidor si es
necesario. Esto no se cambia globalmente desde el controlador.

## Errores

La validación de Laravel devuelve HTTP 422 con `errors.url`. Solo acepta
cadenas de hasta 4096 caracteres y URL completas HTTP/HTTPS. El navegador
también valida la entrada, pero el servidor vuelve a comprobarla.

Los errores de extracción devuelven `{"message":"Mensaje público","error":"codigo"}`:

| HTTP | Códigos / situación |
| --- | --- |
| 403 | `destino_bloqueado`, `acceso_bloqueado` |
| 413 | `descarga_demasiado_grande`, `texto_demasiado_largo` |
| 415 | `contenido_no_html`, `codificacion_no_admitida` |
| 422 | `url_invalida`, `texto_insuficiente`, `extraccion_fallida` |
| 502 | `descarga_fallida`, `redireccion_invalida`, `redirecciones_excedidas`, `respuesta_invalida` o respuesta HTTP inesperada |
| 503 | `no_disponible` por conexión o timeout del cliente HTTP de extracción |
| 504 | `tiempo_agotado` comunicado por FastAPI |

Los mensajes proceden de una lista local: no se reenvían trazas, cuerpos remotos
ni mensajes arbitrarios del sitio. Se informa también de sesión expirada,
límite de solicitudes, desconexión del navegador y espera agotada. No se inicia
inferencia cuando falla la extracción o el contenido excede el límite local.

Si SaBERT rechaza el documento por tokens/fragmentos, el resultado HTTP 200
conserva las reglas, la noticia y `sabert.error="limite_documento"`, sin presentar
una clasificación parcial. Es el comportamiento anterior del adaptador.

## Pruebas automatizadas

```powershell
php artisan test --compact
php vendor/bin/pint --test app/Exceptions/ExtraccionNoticiaException.php app/Services/ExtractorNoticiasService.php app/Http/Controllers/AnalisisIAController.php tests/Feature/AnalisisURLTest.php config/services.php
npm run build
```

Las pruebas PHP simulan HTTP sin acceder a Internet. Cubren contrato real,
contenido completo de más de 5000 caracteres, equivalencia con el modo manual,
párrafos, metadatos ausentes, errores y límites de extracción, respuestas
inválidas, destinos fijos de FastAPI, tiempos configurados y conservación de
reglas/compatibilidad cuando falla SaBERT. No necesitan migraciones ni modelo
activo. La política SSRF, pinning de IP y rebinding se prueban en la API Python;
Laravel delega esas decisiones en ese servicio.

## Integración real en navegador

Requiere FastAPI activo en `http://127.0.0.1:8001`, Chrome y Python con
`pip install playwright`. El servidor debe disponer de SaBERT y del extractor
Trafilatura. No se simula la extracción ni la inferencia en la primera parte
de las pruebas de navegador.

Para probar Laravel sin usar tablas de sesión/caché, desde la raíz del proyecto
en una terminal dedicada:

```powershell
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'array'
Set-Location public
php -d max_execution_time=180 -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

En otra terminal, desde la raíz del proyecto:

```powershell
python tests/Browser/analizador.py --base-url http://127.0.0.1:8010
python tests/Browser/analizador_url.py --base-url http://127.0.0.1:8010
```

En Windows, si usas `artisan serve`, su proceso supervisor debe tener tiempo
de ejecución ilimitado; un límite de 180 s en ese supervisor puede detenerlo
tras tres minutos aunque no haya una petición larga. El servidor PHP directo
del ejemplo permite configurar el límite para cada petición.

`analizador.py` conserva las regresiones del análisis manual. `analizador_url.py`
verifica texto manual y extracción/análisis reales de un artículo público de
NASA Ciencia, metadatos, fragmentos, probabilidades, reglas, teclado, historial
mixto y diseño a 1440, 768, 390 y 320 px. Después simula las respuestas de Laravel
para comprobar carga, errores, timeout y resistencia a HTML/enlaces maliciosos.
No intenta sortear accesos restringidos. Permite seleccionar otro artículo
público mediante `--article-url` y guardar capturas mediante `--screenshots`.
El artículo elegido debe ser lo bastante largo para producir varios fragmentos.

## Validación y limitaciones

Validado con PHP 8.4 en Windows, Chrome y FastAPI/SaBERT reales. El artículo
público de [NASA Ciencia sobre los elementos necesarios para la vida](https://ciencia.nasa.gov/sistema-solar/la-nasa-halla-nueva-forma-en-que-la-tierra-pudo-haber-obtenido-los-elementos-necesarios-para-la-vida/)
produjo **6560 caracteres, 1272 tokens originales, 1716 tokens procesados,
14 fragmentos y 100 % de cobertura**. Estos valores son una observación de esta
ejecución; las páginas públicas pueden cambiar.

La suite final dio **109 pruebas correctas con 739 aserciones**. Pint y
`git diff --check` pasaron. Pasaron también ambos scripts de navegador:
el anterior verificó el análisis manual de 13 fragmentos, sus errores y su
compatibilidad; el nuevo comprobó el análisis de URL de 14 fragmentos y el
historial mixto, sin desbordamiento horizontal en los cuatro anchos. No hubo
errores JavaScript sin capturar.

El extractor no recupera contenido que requiera JavaScript, sesión o superar
un paywall. Sus metadatos son los del editor, no identidades verificadas. Un
artículo puede extraerse correctamente y superar después los límites de
SaBERT. La clasificación sigue siendo estadística y no verifica hechos.

La compilación de assets conserva los avisos de deprecación existentes de
Sass/Bootstrap. No se actualizan esas dependencias ni el diseño en este cambio.
