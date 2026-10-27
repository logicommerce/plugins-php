# Reglas del complemento MagicFront

Para quien escribe o revisa código de `ComLogicommerceMagicfront`, sea persona o agente. Si una regla tiene guarda, se
nombra aquí: es un test de `test/Guards/` que el Jenkins de plugins-php pasa en cada PR. Lo que ya había antes de cada
guarda está en la lista de deuda de su test. Esa lista sólo puede bajar: el cambio que paga una deuda baja también su
número.

## 1. Los textos y los ajustes son del widget

- Un texto que lee el comprador es una propiedad del widget: va por idioma y el comerciante la edita en el editor.
  Nunca es una etiqueta de LogiCommerce (`LanguageLabels`, `languageSheet`, `getLabelValue`), porque el comerciante no
  la ve ni la puede cambiar, y sale en el idioma que tenga la hoja de la tienda.
  La única excepción es `mff_label`, porque la siguen llamando las páginas publicadas con los widgets antiguos.
  Guarda: `NoLanguageLabelsTest`.
- Lo que un widget enseña o hace es un ajuste del widget, nunca la configuración del tema (`getConfiguration()`,
  `themeConfiguration`). La configuración del tema es código de la tienda: nadie la ve desde el editor, y el mismo
  widget saldría distinto, o vacío, según la tienda.
  La única lectura permitida es `anonymousRatingEnabled`. El servidor de opiniones de LogiCommerce
  (`AddCommentController`) decide con el tema si acepta una opinión sin sesión, y no hay un ajuste por API: si el
  widget no lo supiera, enseñaría un formulario que el servidor rechaza.
  Guarda: `NoThemeConfigurationTest`.

## 2. La dirección de la petición se lee en un solo sitio

Los parámetros de la dirección se leen sólo con `RequestQuery`, la clase de lectura única. Un controlador, un servicio
o una función Twig no leen `$_GET`, `$_REQUEST` ni `QUERY_STRING` por su cuenta: cada lectura suelta acaba filtrando o
decodificando distinto que las demás. Mientras `RequestQuery` no esté en la rama, no se añade ninguna lectura suelta
nueva.

## 3. Los nombres de los parámetros, en una sola clase

- Todos los nombres de parámetro del complemento viven en una sola clase. No hereda del FWK, para que también la cargue
  el renderer de la vista previa. Los nombres de LogiCommerce se escriben con las constantes del FWK, y un literal
  suelto en otro sitio no vale.
- Un parámetro nuevo de MagicFront se llama `mf` + camelCase (`mfPage`, `mfChromeDraft`), igual que los de LogiCommerce.
- Lo que ya existe en LogiCommerce se pide con su nombre (`sort`, `page`, `categoryIdList`, `brandsList`, `priceRange`,
  `q`), nunca con uno propio.
- Un nombre que ya está en producción no se renombra: hay enlaces publicados que lo llevan. Por eso se siguen aceptando
  `orderBy` y `category[]`, y los nombres antiguos en snake_case se quedan en la lista.
- El contrato con el editor (`docs/integration/storefront-plugin-contract.md`, en magicfront-frontend) recoge todos los
  nombres.

## 4. Se registra con `MagicfrontLogger`, nunca con `error_log()`

En `src/` no se añade `error_log()`: esa línea sale por la salida de PHP sin el comercio, sin la petición y sin los
interruptores de registro de la tienda, y ningún otro complemento de LogiCommerce lo hace.
- El registro del complemento es `MagicfrontLogger` (`src/Core/Resources`), el registrador de LogiCommerce (el
  `LoggerAdapter` del SDK, como su `ExceptionLogger`). Cada línea lleva el comercio, la petición y la IP, va adonde
  la tienda manda sus registros (`LOG_HANDLER`: syslog, o un fichero en `LOG_PATH/magicfront/`) y se apaga con
  `LOG_ENABLED` o `MAGICFRONTLOGGER_ENABLED`.
- Se llama con `MagicfrontLogger::record(nivel, código, mensaje, datos)`, que nunca lanza: un registro que falla no
  rompe lo que se está pintando.
- Un código por suceso, como constante de la clase. De los datos sólo llegan a la línea las claves de su `PARAMS`: el
  adaptador tira las demás sin avisar, así que un dato nuevo se declara antes de usarlo.
- Lo que se mide por la dirección (las llamadas a una ruta obsoleta, por ejemplo) se sigue midiendo en el registro de
  accesos de la tienda.
- Los dos `error_log()` del sandbox de los widgets son deuda (`INCOMING` de la guarda): pasan a `MagicfrontLogger`
  cuando el sandbox esté en integration.
Guarda: `NoErrorLogTest`.

## 5. Nada de código comentado por si acaso

Lo que no se usa se borra: el historial de git ya lo guarda. Un bloque comentado «para reactivarlo más adelante» se
queda viejo sin que nadie lo vea, y confunde a quien lee.

## 6. Nada de cuentas propias sobre lo que calcula LogiCommerce

Precios, impuestos, descuentos, stock, el estado de un pedido o de una línea, las reglas de envío: se pinta lo que
devuelven la API o el SDK. Copiar en PHP una regla del núcleo funciona el primer día y deja de coincidir el día que
LogiCommerce la cambia. Si falta un dato, se pide a la API; no se deduce.
