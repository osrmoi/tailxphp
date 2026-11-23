# To-do — Proyecto en PHP puro

Aplicación de tareas por usuario, construida con **PHP + MySQLi** en la carpeta `pagina/`. Cada usuario inicia sesión y ve únicamente sus propias tareas, puede crearlas, editarlas, buscarlas, filtrarlas por categoría, marcarlas como completadas y eliminarlas. Las completadas se separan en una pestaña "Terminadas".

---

## 1. Tecnologías utilizadas

| Tecnología | Uso |
| --- | --- |
| **PHP** | Toda la lógica del servidor. Mezcla PHP con HTML usando short echo `<?= ?>`. |
| **MySQLi** | Acceso a la base de datos (`new mysqli`, `prepare`, `bind_param`, `get_result`, `bind_result`, `store_result`). |
| **Tailwind CSS (CDN)** | Estilos. Se carga con `<script src="https://cdn.tailwindcss.com"></script>` en el `<head>` de `index.php`, `registro.php` y `panel.php`. No hay archivos CSS propios ni build. |
| **Lucide (CDN)** | Iconos. Se carga con `<script src="https://unpkg.com/lucide@latest"></script>` y se activa con `lucide.createIcons()` al final del `<body>`. Iconos usados: `plus`, `trash-2`, `door-open`. |
| **JavaScript** | Mínimo: `toggleNueva()` (muestra/oculta el formulario de alta), `this.form.submit()` en el checkbox, `confirm()` al eliminar y `lucide.createIcons()`. |
| **Apache / XAMPP** | Servidor. La BD se llama `lista`, usuario `root`, sin contraseña. |

> Tailwind y Lucide vienen por CDN, así que requieren conexión a internet en el navegador. Es una configuración de desarrollo; para producción se compilaría Tailwind localmente.

---

## 2. Estructura de archivos

```
pagina/
├── creaciondb.sql          Script de creación de la BD (no se modifica desde el código)
├── README.md               Este archivo
├── includes/               Archivos auxiliares, se incluyen con require_once
│   ├── conexion.php        Conexión MySQLi + charset
│   ├── sesion.php          session_start() + requerir_login()
│   └── funciones.php       h(), redirect(), olvidar_preferencias()
├── index.php               Inicio de sesión (punto de entrada)
├── registro.php            Alta de usuarios
├── logout.php              Cierre de sesión
├── seeder.php              Carga de categorías y usuarios de prueba
├── acciones.php            Controlador POST del CRUD de tareas (sin HTML)
└── panel.php               Pantalla principal: filtros, pestañas, cards, edición inline
```

**Convención:** los archivos dentro de `includes/` no son páginas: no se visitan directamente, se traen con `require_once`. Se usa `require_once` (no `include`) para que, si dos archivos los incluyen, no se dupliquen las definiciones de funciones ni se abra dos veces la sesión. Los archivos de `includes/` no llevan etiqueta de cierre `?>` para evitar que se emita whitespace accidentalmente.

---

## 3. Puesta en marcha

1. Tener Apache y MySQL corriendo (XAMPP).
2. Ejecutar `creaciondb.sql` (por phpMyAdmin o `mysql < creaciondb.sql`). Crea la BD `lista` y las tablas `usuarios`, `categorias` y `tareas`.
3. Abrir en el navegador `http://localhost/proyecto/pagina/seeder.php`. Siembra 9 categorías y 2 usuarios de prueba.
4. Ir a `http://localhost/proyecto/pagina/index.php` y entrar con `user1` / `1234` (o `user2` / `1234`).

### Usuarios de prueba

| Usuario | Contraseña |
| --- | --- |
| `user1` | `1234` |
| `user2` | `1234` |

La contraseña se guarda **hasheada** con `password_hash($password, PASSWORD_DEFAULT)` y se verifica con `password_verify()`. Nunca se guarda en texto plano, ni en la BD, ni en la sesión, ni en cookies.

### Categorías sembradas

`Trabajo`, `Escuela`, `Hogar`, `Personal`, `Salud`, `Finanzas`, `Compras`, `Proyectos`, `Ocio`. Cada una tiene descripción y un color hexadecimal (por ejemplo `#2563eb`) que se usa en la interfaz. No tienen CRUD: son datos de referencia fijos.

`seeder.php` es **idempotente**, se puede ejecutar varias veces:

- Categorías: `INSERT ... ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), color = VALUES(color)`. Actualiza descripción/color si el `nombre` (UNIQUE) ya existe.
- Usuarios: `INSERT IGNORE`. Si el usuario ya existe, no sobrescribe su contraseña (por si la cambiaste).

---

## 4. Base de datos

```sql
usuarios(id, usuario UNIQUE, password, creado_en)

categorias(id, nombre UNIQUE, descripcion NULL, color NULL)

tareas(id, usuario_id FK -> usuarios(id) ON DELETE CASCADE,
       categoria_id FK -> categorias(id) ON DELETE RESTRICT NULL,
       titulo, descripcion NULL, completada BOOLEAN DEFAULT FALSE, creado_en)
```

Puntos importantes:

- `usuario_id` es **NOT NULL**: toda tarea pertenece a un usuario. Al borrar un usuario, sus tareas se borran en cascada (`ON DELETE CASCADE`).
- `categoria_id` es **nullable** (`NULL` = "Sin categoría") y tiene `ON DELETE RESTRICT`: no se puede borrar una categoría que tenga tareas asociadas.
- `completada` es `BOOLEAN` (en MySQL es `TINYINT(1)`: 0 = pendiente, 1 = completada).

---

## 5. Los `includes`

### `includes/sesion.php`

```php
session_start();

function requerir_login() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: index.php");
        exit;
    }
}
```

Inicia la sesión y define el guard de acceso. **Debe incluirse primero** en las páginas protegidas. `panel.php` y `acciones.php` llaman a `requerir_login()` justo después; si no hay sesión, redirigen a `index.php` y cortan con `exit`.

### `includes/conexion.php`

```php
$conexion = new mysqli("localhost", "root", "", "lista");
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");
```

Crea la variable `$conexion` que usan todos los archivos. `set_charset("utf8mb4")` asegura que acentos y emojis se manejen correctamente entre PHP y MySQL.

### `includes/funciones.php`

- `h($valor)` — atajo de `htmlspecialchars($valor ?? "", ENT_QUOTES, "utf-8")`. Escapea toda salida hacia HTML para evitar XSS. El `?? ""` evita avisos cuando el valor es `NULL` (por ejemplo una descripción vacía). `ENT_QUOTES` escapa comillas dobles y simples, importante dentro de `value="..."`.
- `redirect($url)` — hace `header("Location: ...")` y `exit`. Centraliza las redirecciones.
- `olvidar_preferencias()` — borra la única cookie existente, `usuario_recordado`, con `setcookie(..., time() - 3600, "/")`.

---

## 6. Autenticación, sesión y cookies

### Registro (`registro.php`)

Formulario con usuario, contraseña y confirmación. Validaciones en servidor:

1. Ningún campo vacío.
2. `$password === $confirmacion` (no se confía en el `required` del HTML).
3. `SELECT id FROM usuarios WHERE usuario = ?` para detectar duplicados (la columna además es UNIQUE).
4. `INSERT` con `password_hash()`.

Si todo sale bien: `redirect("index.php?registro=ok")`, es decir, **vuelve al login** y muestra un mensaje verde. `index.php:20` lee `$_GET['registro']` para pintarlo. Si hay errores, se muestran en rojo en el propio `registro.php`.

### Login (`index.php`)

- Si ya existe `$_SESSION['usuario']`, redirige directamente a `panel.php`.
- Busca el usuario con `SELECT id, password ... WHERE usuario = ?` usando `bind_param("s", ...)` y `bind_result($id, $hash)`.
- Compara con `password_verify($password, $hash)`.
- Si es correcto, guarda en sesión:
  - `$_SESSION['usuario']` → nombre, para mostrar el saludo.
  - `$_SESSION['usuario_id']` → ID, **imprescindible** para filtrar las tareas por dueño.
- Si es incorrecto: `$error = "Credenciales incorrectas"`.

### Cookies

Solo existe **`usuario_recordado`**:

- Si el checkbox "Recordar usuario" está marcado al hacer login: `setcookie("usuario_recordado", $usuario, time() + 7*24*3600, "/")` (7 días, todo el sitio).
- Al cargar `index.php`, se lee `$_COOKIE['usuario_recordado'] ?? ''` y se usa como valor inicial del input y para marcar el checkbox.
- **Es solo prellenado, no auto-login**: nunca se guarda la contraseña en una cookie.
- El enlace `index.php?olvidar=1` ("Olvidar usuario prellenado") llama a `olvidar_preferencias()` y redirige.
- Si se desmarca el checkbox y el login es exitoso, también se borra la cookie. Si el login falla o simplemente se cierra la página, no se borra nada.
- `logout.php` **no toca la cookie**: hace `session_destroy()` y redirige a `index.php`, donde el usuario vuelve a aparecer prellenado.

---

## 7. Seguridad

| Medida | Dónde |
| --- | --- |
| Contraseñas hasheadas (`password_hash` / `password_verify`) | `registro.php`, `index.php`, `seeder.php` |
| Consultas preparadas (`prepare` + `bind_param`) | Todas las consultas con datos del usuario |
| Escape de salida con `h()` | Todo lo que se imprime con `<?= ?>` |
| IDs casteados a `(int)` | `id`, `categoria`, `editar`, `categoria_id`, `usuario_id` |
| Protección de vistas | `requerir_login()` en `panel.php` y `acciones.php` |
| Aislamiento por usuario | Toda consulta sobre `tareas` incluye `usuario_id = ?` con el ID de la sesión |

Detalle clave: en `acciones.php`, `UPDATE` y `DELETE` llevan `WHERE id = ? AND usuario_id = ?`. Así, aunque alguien manipule el `id` de un formulario, no puede modificar tareas ajenas.

---

## 8. Cómo funciona `panel.php`

### Parámetros de la URL

| Parámetro | Significado |
| --- | --- |
| `buscar` | Texto a buscar en título **y** descripción. |
| `categoria` | ID de categoría; `0` = todas. |
| `estado` | `pendientes` (por defecto) o `terminadas`. |
| `editar` | ID de la tarea que se está editando en ese momento. |

Todo se lee desde `$_GET` con valores por defecto. `estado` se sanitiza: cualquier valor que no sea `terminadas` se convierte en `pendientes`.

Los dos primeros se conservan al editar o al Guardar gracias a campos `hidden` y a `http_build_query`. **No se guardan en cookies** (se eliminó esa cookie): cada visita empieza limpia y cambiar de pestaña limpia búsqueda y categoría, porque los links de las pestañas solo envían `?estado=...`.

### Contadores

```sql
SELECT SUM(completada = 0) AS pendientes,
       SUM(completada = 1) AS terminadas
FROM tareas WHERE usuario_id = ?
```

`SUM(condicion)` cuenta cuántas filas cumplen la condición (en MySQL, `TRUE = 1`, `FALSE = 0`). Si el usuario no tiene tareas, `SUM` devuelve `NULL`, por eso se casa a `(int)` y da `0`. El resumen del header muestra solo `pendientes`; las pestañas muestran ambos contadores.

### Consulta de categorías

```sql
SELECT id, nombre, color FROM categorias ORDER BY nombre
```

Se guarda en el array `$categorias` para rellenar los `<select>` y para los colores. Se recorre con `fetch_assoc()` y luego se llama a `$resultadoCategorias->free()` (ver sección 11).

### Consulta de tareas

```sql
SELECT t.id, t.titulo, t.descripcion, t.categoria_id, t.completada,
       c.nombre AS categoria, c.color AS categoria_color
FROM tareas t
LEFT JOIN categorias c ON c.id = t.categoria_id
WHERE t.usuario_id = ?
  AND (? = '' OR t.titulo LIKE ? OR t.descripcion LIKE ?)
  AND (? = 0 OR t.categoria_id = ?)
  AND t.completada = ?
ORDER BY t.creado_en DESC
```

Detalles:

- **`LEFT JOIN`**: trae el nombre/color de la categoría; si la tarea no tiene categoría, esas columnas llegan como `NULL` y no se pierde la tarea.
- **Búsqueda en un solo input**: `$termino = "%$buscar%"` se pasa dos veces como parámetro (título y descripción). La condición `(? = '' OR ... )` hace que, si el input está vacío, no filtre nada. Es un truco para usar siempre la misma consulta y un `bind_param` fijo.
- **Filtro por categoría**: `(? = 0 OR t.categoria_id = ?)` funciona igual: con `0` no filtra.
- **Estado**: `$completada = $estado === "terminadas" ? 1 : 0`, por lo que la lista muestra solo pendientes o solo completadas.
- **`bind_param("isssiii", ...)`**: 7 parámetros en orden → `i` usuario_id, `s` buscar, `s` término, `s` término, `i` categoría, `i` categoría, `i` completada.
- Se usa `get_result()` para obtener un `mysqli_result` y recorrerlo con `fetch_assoc()`.

### Pestañas y "Limpiar"

- `Pendientes (n)` y `Terminadas (n)` son links a `panel.php?estado=...`. Al cambiar, no se envían `buscar` ni `categoria`: se limpian solos.
- "Limpiar" apunta a `panel.php?estado=<actual>`: borra filtros sin cambiar de pestaña.

### Edición inline (sin cambiar de página)

Cada tarea se pinta como una **card**:

- En modo lectura, título y descripción están dentro de un `<a href="panel.php?editar=ID&buscar=...&categoria=...&estado=...">`. Por eso **hacer clic en el texto abre la edición en el mismo `panel.php`**, sin ir a otro archivo. El checkbox queda fuera de ese `<a>` para no solaparse.
- La descripción se recorta visualmente con la clase `line-clamp-2` de Tailwind; el texto completo sigue en la BD y se ve al editar.
- El badge de categoría y el borde izquierdo de la card usan el `color` de la BD con estilos inline (`background-color` y `border-left-color`). Si la tarea no tiene categoría, no se muestra badge.
- A la derecha, un checkbox circular. Su `onchange="this.form.submit()"` envía automáticamente un POST a `acciones.php` con la acción `completar`.
- Si la URL trae `editar=ID` y coincide con la tarea, esa card se convierte en un `<form>` con título, descripción, categoría, **Guardar**, **Cancelar** (vuelve a `$volver`) y un icono de basura. Guardar y Eliminar son dos `<button type="submit" name="accion" value="...">` dentro del **mismo formulario**: `acciones.php` lee `$_POST['accion']` según el botón pulsado, evitando forms anidados.

### Botones fijos

- **`+`** (abajo derecha): solo existe en la pestaña Pendientes. Con `toggleNueva()` alterna la clase `hidden` del `<div id="nueva-tarea">`, donde está el formulario de alta. El formulario se abre inline, justo donde estaba.
- **Logout** (abajo izquierda): link fijo `fixed bottom-4 left-4 z-50` con icono `door-open` y `hover:text-red-600`.

El `<body>` tiene `pb-24` para que los botones fijos no tapen la última card.

### JavaScript en `panel.php`

```js
function toggleNueva() {
    document.getElementById('nueva-tarea').classList.toggle('hidden');
}
lucide.createIcons();
```

`lucide.createIcons()` convierte los `<i data-lucide="...">` en `<svg>`. Debe ejecutarse al final, cuando el DOM ya existe.

---

## 9. Cómo funciona `acciones.php`

Es un **controlador**: no genera HTML. Recibe POST, ejecuta una acción y redirige.

| `accion` | Consulta |
| --- | --- |
| `crear` | `INSERT INTO tareas (usuario_id, categoria_id, titulo, descripcion, completada) VALUES (?, ?, ?, ?, 0)` |
| `actualizar` | `UPDATE tareas SET titulo = ?, descripcion = ?, categoria_id = ? WHERE id = ? AND usuario_id = ?` |
| `eliminar` | `DELETE FROM tareas WHERE id = ? AND usuario_id = ?` |
| `completar` | `UPDATE tareas SET completada = NOT completada WHERE id = ? AND usuario_id = ?` |

- `completar` es un **toggle**: `NOT completada` alterna entre 0 y 1. Al desmarcar una terminada, la tarea pasa a Pendientes en la BD, pero la vista no cambia: el redirect conserva `estado=terminadas` y la tarjeta simplemente desaparece de esa lista.
- `categoria_id`: si el `<select>` trae `0`, se usa `null` y MySQL guarda `NULL` ("Sin categoría"). Si trae un ID, se verifica antes con `SELECT id FROM categorias WHERE id = ?`; si no existe, también se guarda `NULL`.
- El `titulo` vacío se ignora (no se crea ni se actualiza).

### PRG (Post — Redirect — Get)

Después de cualquier mutación se cierra la conexión y se redirige:

```php
redirect("panel.php?" . http_build_query([
    "buscar" => $buscar,
    "categoria" => $categoriaFiltro,
    "estado" => $estado
]));
```

Esto evita que, al recargar la página, el navegador vuelva a enviar el POST (duplicando una alta o un borrado).

---

## 10. `http_build_query`: para qué sirve

Convierte un array asociativo en una cadena de URL codificada:

```php
$params = ["buscar" => "pan", "categoria" => 3, "estado" => "pendientes"];
echo http_build_query($params);
// buscar=pan&categoria=3&estado=pendientes
```

Codifica automáticamente espacios, acentos y caracteres especiales (`lápiz rojo` → `lápiz+rojo`), y omite valores `null`. Ventajas:

1. **No concatenar a mano** `?a=1&b=2...` (propenso a errores y a URLs rotas con acentos).
2. **Conservar el estado** entre peticiones: los campos `hidden` de los POST guardan `buscar`/`categoria`/`estado`, y `http_build_query` los devuelve a la URL al redirigir. Así, si editas una tarea dentro de una búsqueda, al guardar sigues viendo esa misma búsqueda.
3. **Armar links** de Editar, Cancelar, pestañas y Limpiar sin repetir código de concatenación.

Ejemplo de propagación:

```php
$estadoActual = ["buscar" => $buscar, "categoria" => $categoria, "estado" => $estado];
$volver = "panel.php?" . http_build_query($estadoActual);
$linkEditar = "panel.php?" . http_build_query(["editar" => 7] + $estadoActual);
```

El operador `+` de arrays añade `editar` sin sobrescribir las claves existentes.

---

## 11. Los cierres: `free()`, `close()` y `store_result()`

- **`$resultado->free()`** libera la memoria usada por un `mysqli_result`. En `panel.php` se llama tanto para `$resultadoCategorias` como para `$resultadoTareas` cuando ya se terminó de recorrer. Es una buena práctica, sobre todo en resultados grandes, y evita dejar resultados pendientes antes de emitir nuevas consultas (los resultados no almacenados en buffer pueden dar el error *"Commands out of sync"* si no se consumen o se liberan).
- **`$stmt->close()`** cierra cada sentencia preparada y libera sus recursos.
- **`$conexion->close()`** cierra la conexión a MySQL al final de cada archivo.
- **`$stmt->store_result()`** transfiere el resultado a memoria para poder usar `$stmt->num_rows` inmediatamente. Se usa en `registro.php` (comprobar usuario duplicado) y en `acciones.php` (validar que la categoría existe), donde solo interesa la cantidad de filas, no los datos.
- **`$stmt->get_result()`** devuelve un `mysqli_result` para recorrer con `fetch_assoc()` (panel, contadores). **`$stmt->bind_result()`** enlaza columnas directamente a variables (login: `id` y `hash`).

---

## 12. Comportamientos resumidos

- Contraseñas siempre hasheadas; jamás se guardan en cookies ni en sesión.
- Cada usuario ve **solo sus tareas**: todas las consultas filtran por `usuario_id`.
- `panel.php` y `acciones.php` están protegidos por sesión.
- Registro exitoso → vuelta al login con mensaje; siempre se empieza en `index.php`.
- Login con sesión activa → va directo al panel.
- Recordar usuario → cookie de 7 días con el nombre; "Olvidar usuario prellenado" la borra; el logout la conserva.
- Búsqueda por título **o** descripción en el mismo input.
- Filtro por categoría con colores de la BD en badge y borde de card.
- Pestañas Pendientes / Terminadas con contadores; cambiar de pestaña limpia búsqueda y categoría.
- Edición en la misma página clicando el texto de la tarea; eliminar solo desde la edición.
- Checkbox circular para completar/descompletar con envío automático.
- Widgets flotantes: `+` abajo derecha (solo en Pendientes), logout abajo izquierda.
- Mutaciones por POST + redirección (PRG); navegación y estado por GET.

---

## 13. Cosas deliberadamente NO incluidas

- **CRUD de categorías**: son fijas, sembradas. Agregar categorías personalizadas exigiría modificar el SQL (columna `usuario_id` en `categorias`), un módulo CRUD y filtrar por dueño (`usuario_id IS NULL OR usuario_id = ?`).
- **Cookie del filtro de categoría**: se eliminó; el filtro siempre arranca limpio.
- **Tema claro/oscuro**: descartado desde el inicio.
- **Auto-login**: la cookie solo prellena el usuario, nunca autentica.
- **Recarga parcial / SPA**: cambiar de pestaña hace una petición GET y recarga la página. Es simple, mantiene URLs compartibles y hace que el botón Atrás funcione sin código extra.

---

## 14. Verificación

Chequeo de sintaxis (XAMPP):

```powershell
C:\xampp\php\php.exe -l C:\xampp\htdocs\proyecto\pagina\index.php
C:\xampp\php\php.exe -l C:\xampp\htdocs\proyecto\pagina\registro.php
C:\xampp\php\php.exe -l C:\xampp\htdocs\proyecto\pagina\acciones.php
C:\xampp\php\php.exe -l C:\xampp\htdocs\proyecto\pagina\panel.php
C:\xampp\php\php.exe -l C:\xampp\htdocs\proyecto\pagina\seeder.php
```

Pruebas manuales sugeridas:

1. Registrar un usuario → vuelve al login con mensaje verde.
2. Login `user1` / `1234`; ver saludo "Hola, user1" y contador de pendientes.
3. Crear tarea con el botón `+`; ver badge y borde con el color de la categoría.
4. Buscar por título y por descripción con el mismo input.
5. Clic en el texto de una tarea → edición inline; Guardar y Cancelar.
6. Tachar una tarea: desaparece de Pendientes y aparece en Terminadas.
7. En Terminadas, desmarcar el checkbox: desaparece de esa pestaña y vuelve a Pendientes; la vista no cambia.
8. Cambiar de pestaña con un filtro activo: búsqueda y categoría se limpian.
9. Eliminar una tarea desde el icono de basura dentro de la edición.
10. Cerrar sesión: el usuario sigue prellenado en el login; "Olvidar usuario prellenado" lo limpia.
11. Entrar a `panel.php` sin sesión: redirige a `index.php`.
12. Repetir con `user2`: no debe ver las tareas de `user1`.
