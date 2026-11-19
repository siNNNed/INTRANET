# INTRANET

Intranet de Salmones Antártica S.A. (Chile). Desde una portada común se accede a los sistemas internos.
El primer sistema es **Egresos** (registro, aprobación y pago de egresos).
Responder siempre en español.

## Stack

- Windows Server 2019, XAMPP con Apache y PHP 8.5.12 (Thread Safe x64).
- PDO con driver `pdo_sqlsrv` (Microsoft Drivers for PHP for SQL Server 5.13.3, ODBC Driver 18 for SQL Server).
- SQL Server 2019 Standard, **una base por sistema**: `Intranet` (seguridad común) y `Egresos`; intercalación `Modern_Spanish_CI_AS`. Login de la aplicación: `intranet_app` (nunca `sa`), con usuario en cada base.
- Bootstrap 5, jQuery y AJAX. Los endpoints PHP en `api/` devuelven siempre JSON.
- Composer: PhpSpreadsheet (Excel), Dompdf (PDF), PHPMailer (correo SMTP).

## Estructura

```
C:\xampp\htdocs\INTRANET\
├── CLAUDE.md
├── config\          config.php (BD, SMTP, rutas) — fuera del repositorio y del navegador
├── comun\           código compartido por todos los sistemas
│   ├── src\         bootstrap.php y clases Intranet\* (Db, Auth, Csrf, Json, Http, View, Portal, Usuarios, Bitacora, Parametros, Validator)
│   ├── views\       layout.php (menú lateral + cabecera), sin_perfil.php, error.php
│   └── assets\      css y js comunes (estilo, validaciones, helper AJAX)
├── apache\          intranet.conf (alias y permisos; se incluye desde httpd.conf)
├── portal\
│   ├── public\      login, cambiar_clave, api\, portada, sin_perfil, logout, admin\
│   └── views\       vistas del portal
├── egresos\
│   ├── public\      páginas, api\, assets\ propios de Egresos
│   ├── views\       vistas de Egresos
│   ├── src\Services\
│   └── templates\   plantilla del PDF y del correo
├── sql\             intranet_schema.sql, egresos_schema.sql, crear_admin.php
├── docs\            documento de diseño (Markdown)
└── vendor\          Composer (uno solo, compartido)
```

Cada sistema nuevo se agrega como carpeta hermana de `egresos\`, con su `public\`, su propia base de datos (`sql\<sistema>_schema.sql`) y su entrada en `db.bases` de `config.php`.

## Apache y URLs

- Solo se publican las carpetas `public\` mediante alias:
  - `/intranet` → `C:/xampp/htdocs/INTRANET/portal/public`
  - `/egresos`  → `C:/xampp/htdocs/INTRANET/egresos/public`
- `Require all denied` sobre `C:/xampp/htdocs/INTRANET` (config, comun\src, sql, docs y vendor nunca accesibles desde el navegador). `comun\assets` se publica con su propio alias `/intranet-assets`.
- La raíz `/` redirige a `/intranet`.
- Rutas base en `config.php` (`'rutas' => ['INTRANET' => '/intranet', 'EGRESOS' => '/egresos', 'ASSETS' => '/intranet-assets']`). Todo enlace, recurso y llamada AJAX se arma con `View::url()` y `View::asset()`; nunca rutas escritas a mano.

## Seguridad: usuarios, perfiles y accesos

- **Una cuenta por persona** para toda la intranet (`seg.Usuarios`). Se ingresa con **correo y contraseña** guardados en la base (sin Google ni otro proveedor externo).
- **Perfiles por sistema** (`seg.Perfiles`). Egresos: ADMIN, APROBADOR, DIGITADOR, TESORERIA, CONSULTA. Administración de la intranet: ADMIN.
- **Acceso individual**: `seg.UsuarioPerfiles` asigna como máximo un perfil por usuario y sistema, con vencimiento opcional (`VigenteHasta`). Sin fila = sin acceso.
- **Sesión única** de la intranet: cookie `INTRANET_SID`, `path=/`, `HttpOnly`, `Secure`, `SameSite=Lax`; `session_regenerate_id(true)` al iniciar sesión; expira por inactividad (`seg.Parametros.SESION_MINUTOS`). El login está solo en el portal; cerrar sesión sale de todos los sistemas.
- **Portada**: muestra todos los sistemas activos (`seg.fn_PortalUsuario(@UsuarioId)`). Los que el usuario no tiene se ven deshabilitados, con un candado.
- **Entrada a un sistema**: cada página llama `Auth::requireSistema('EGRESOS')`, que vuelve a consultar `seg.vw_AccesosUsuario` en cada petición (así un acceso revocado se corta de inmediato). Si no tiene perfil:
  - página: respuesta 403 con el mensaje **"Usted no tiene el perfil para este sistema"** y un botón para volver a la portada;
  - endpoint AJAX: HTTP 403 con `{"ok":false,"error":{"codigo":"SIN_PERFIL","mensaje":"Usted no tiene el perfil para este sistema"}}`.
- Dentro del sistema, `Auth::requirePerfil('EGRESOS', ['DIGITADOR','ADMIN'])` limita cada acción, y en egresos también se verifica por fila.
- Intentos de login fallidos: bloqueo tras `LOGIN_MAX_INTENTOS` (5) por `LOGIN_MINUTOS_BLOQUEO` (15). Todo inicio de sesión, rechazo por perfil y cambio de accesos queda en `seg.Bitacora`.

## Inicio de sesión y contraseñas

- Login en `/intranet/login.php` (correo + contraseña) → `api/auth/login.php`. Mensaje genérico "Correo o contraseña incorrectos." para no revelar qué correos existen.
- El administrador crea cada cuenta en `/intranet/admin/` con una **contraseña inicial** y, por defecto, `DebeCambiarPassword = 1`. Si olvidan la contraseña, el administrador les asigna otra (también desbloquea la cuenta).
- Con `DebeCambiarPassword = 1`, `Auth::requireLogin()` solo permite `cambiar_clave.php` (página → redirige; AJAX → 403 `DEBE_CAMBIAR_CLAVE`).
- `cambiar_clave.php` + `api/auth/cambiar_clave.php`: pide la actual, la nueva (distinta) y su repetición; regenera el id de sesión. Enlace con ícono de llave en el menú lateral.
- `PasswordHash` NULL = cuenta sin contraseña: no puede entrar hasta que el administrador le asigne una.
- El propio administrador cambia su contraseña solo en "Cambiar contraseña", no desde la edición de usuarios.

## Reglas de código (obligatorias)

- Toda consulta con sentencias preparadas de PDO y parámetros. Nunca concatenar datos del usuario en SQL; columnas de orden y filtros contra listas blancas.
- Contraseñas con `password_hash()` / `password_verify()`, `password_needs_rehash()` al iniciar sesión. Mínimo 10 caracteres con letras y números.
- Validar en el cliente (para guiar) y en el servidor (fuente de verdad). Reglas compartidas en `comun/src/Validator.php` y `Intranet.validar` de `comun/assets/js/intranet.js`.
- Token CSRF por sesión en la cabecera `X-CSRF-Token` para todo POST, comparado con `hash_equals`.
- Salida escapada: `htmlspecialchars` en PHP, `.text()` en jQuery (nunca `.html()` con datos del usuario).
- Montos en `DECIMAL`, nunca `float`; en PHP como texto o con `bcmath`. Fechas enviadas a SQL siempre en `AAAA-MM-DD`.
- Sobre JSON: `{"ok":true,"data":...}` o `{"ok":false,"error":{"codigo","mensaje","campos"}}`. HTTP 200, 400 validación, 401 sin sesión, 403 sin permiso o sin perfil, 409 conflicto, 500 error interno (detalle solo al log).
- Errores de negocio de los procedimientos: `THROW 50001–50099`; PHP los traduce a 400/409 con el mensaje tal cual.
- Cada sistema usa su base y su esquema (`egr.` en la base Egresos) y lee la seguridad solo desde `seg.`.
- Conexiones: `Db::fila/todas/valor/ejecutar($sql, $params, $base)`; `$base` por omisión `'INTRANET'`; para egresos, `'EGRESOS'`. Los nombres de las bases están en `config.php` → `db.bases`.

## Bases de datos

Orden de instalación: `sql/intranet_schema.sql`, luego `sql/egresos_schema.sql`, luego `php sql/crear_admin.php`.

- Base **Intranet**, esquema `seg`: Usuarios, Cargos, Departamentos, Sistemas, Perfiles, UsuarioPerfiles, Comunicados, Bitacora, Parametros; vista `vw_AccesosUsuario`; función `fn_PortalUsuario`.
- Base **Egresos**, esquema `egr`: catálogos (Monedas, TiposCambio, EstadosEgreso, TiposDocumento, FormasPago, Bancos, Destinos), maestros (Solicitantes, Proveedores, CuentasContables, Categorias, CentrosCosto, ReglasAprobacion), Egresos y seguimiento (EgresoAprobaciones, EgresoHistorial, EnviosCorreo), Parametros; vistas `vw_Aprobadores`, `vw_Egresos`, `vw_PendientesAprobacion`; procedimientos `sp_Egreso_Enviar`, `sp_Egreso_Resolver`, `sp_Egreso_Pagar`, `sp_Egreso_Anular`, `sp_Reglas_Simular`.
- En Egresos, el esquema `seg` contiene solo **sinónimos** a `Intranet.seg.*` (Usuarios, Cargos, Departamentos, Sistemas, Bitacora, vw_AccesosUsuario), para que vistas y procedimientos escriban `seg.Usuarios` igual que en Intranet. No hay claves foráneas entre bases: la existencia del usuario se valida en el código y en los procedimientos.
- El primer administrador se crea con `php sql/crear_admin.php` (perfil ADMIN en INTRANET y en EGRESOS).

## Dominio de Egresos

- Estados: BORRADOR → PENDIENTE → APROBADO → PAGADO; desde PENDIENTE también DEVUELTO (corrige y reenvía, nuevo ciclo) o RECHAZADO (final). ANULADO desde BORRADOR, DEVUELTO o APROBADO sin pagar. Los códigos son fijos; el mantenedor solo edita nombre, color, orden y descripción.
- Aprobación: reglas por centro de costo (si no tiene, reglas generales con `CentroCostoId` NULL). Se exigen todos los niveles con `MontoDesdeCLP <= MontoCLP`, en orden; varios aprobadores en un nivel = basta uno. Solo aparecen en la cadena usuarios con perfil APROBADOR vigente (`egr.vw_Aprobadores`). Nadie aprueba lo que creó. El monto comparado es el total (con IVA).
- Multimoneda: CLP base; USD, EUR y UF (código `CLF`). `egr.fn_TipoCambio` busca el valor de la fecha o el último anterior dentro de 7 días. `MontoCLP = ROUND(MontoTotal × TipoCambio, 0)`, congelado al enviar a aprobación.
- Mantenedores de Egresos: proveedores, solicitantes, bancos, destinos, estados, formas de pago, categorías, centros de costo, cuentas contables y reglas de aprobación. Usuarios, cargos, departamentos y accesos se administran en el portal. Nada se borra si fue usado: se desactiva con `Activo = 0`.
- Sin documentos adjuntos en v1.

## Pendientes de confirmar con el usuario

- Qué significa "Destinos" (se asumió: obra, sucursal o proyecto al que va el gasto).
- Si el jefe de departamento aprueba egresos (se asumió: no; la aprobación va solo por reglas de centro de costo).

## Diseño visual

Bosquejo aprobado: menú lateral oscuro (#0b1324) con íconos, acento #2f5bea, fondo #f5f6f8, tarjetas blancas con borde #e5e7eb y radio 12 px, tipografía Inter, tablas de 13 px en una sola línea con montos alineados a la derecha (`tabular-nums`), estados como etiquetas con punto de color. El portal usa el mismo estilo; los sistemas sin perfil se ven en gris con candado.

## Logo de la empresa

Dos peces que forman una "S". Archivos en `comun/assets/img/`:
- `logo_blanco_256.png`: blanco con fondo transparente, para el menú lateral y el panel del login (fondo #0b1324).
- `logo_oscuro_256.png`: azul oscuro #0b1324 con fondo transparente, para el PDF del egreso, los correos y cualquier fondo claro.
- `favicon_32.png` y `favicon_192.png`: logo blanco sobre cuadrado azul oscuro, para la pestaña del navegador.
Nunca usar la versión blanca sobre fondo claro.

## Estado actual

- Etapa 1 (base del portal) generada: login con correo y contraseña, cambio de contraseña (obligatorio en el primer ingreso), portada con sistemas, pendientes y comunicados, sin_perfil, logout y entrada provisional de Egresos. Se descartó el inicio con Google (versión 3.1 del esquema; `sql/migracion_3_1_sin_google.sql` para bases creadas con la 3.0).
- Administración de usuarios lista: `/intranet/admin/` (listado con filtros) y `admin/usuario.php` (crear/editar, accesos por sistema con vencimiento, actividad desde la bitácora); guarda `api/usuarios/guardar.php` con la lógica en `comun/src/Usuarios.php`. Un administrador no puede desactivarse ni quitarse ADMIN de la intranet.
- Pendiente de la etapa 1: mantenedores de cargos y departamentos; publicar comunicados.
- Nuevas páginas: `require dirname(__DIR__, N) . '/comun/src/bootstrap.php';`, luego `Auth::requireSistema('CODIGO')` y `View::render(vista, [...])`.

## Plan por etapas

1. Base común y portal: `config.php`, conexión, inicio de sesión con correo y contraseña, sesión de la intranet, portada con todos los sistemas, página "sin perfil", administración de usuarios, cargos, departamentos y accesos por sistema.
2. Egresos: layout con menú según perfil y mantenedores.
3. Monedas y tipos de cambio.
4. Registro de egresos.
5. Reglas de aprobación, envío y bandeja del aprobador.
6. Tesorería.
7. PDF y envío por correo.
8. Reportes, Excel y pruebas del flujo con los cinco perfiles.

Cada etapa se prueba antes de pasar a la siguiente.
