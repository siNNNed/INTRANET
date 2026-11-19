# INTRANET

Intranet de Salmones Antártica S.A. (Chile). Desde una portada común se accede a los sistemas internos.
El primer sistema es **Egresos** (registro, aprobación y pago de egresos).
Responder siempre en español.

## Stack

- Windows Server 2019, XAMPP con Apache y PHP 8.5.12 (Thread Safe x64).
- PDO con driver `pdo_sqlsrv` (Microsoft Drivers for PHP for SQL Server 5.13.3, ODBC Driver 18 for SQL Server).
- SQL Server 2019 Standard, base `Intranet`, intercalación `Modern_Spanish_CI_AS`. Login de la aplicación: `intranet_app` (nunca `sa`).
- Bootstrap 5, jQuery y AJAX. Los endpoints PHP en `api/` devuelven siempre JSON.
- Composer: PhpSpreadsheet (Excel), Dompdf (PDF), PHPMailer (correo SMTP).

## Estructura

```
C:\xampp\htdocs\INTRANET\
├── CLAUDE.md
├── config\          config.php (BD, SMTP, rutas) — fuera del repositorio y del navegador
├── comun\           código compartido por todos los sistemas
│   ├── src\         bootstrap.php y clases Intranet\* (Db, Auth, Csrf, Json, Http, View, Portal, GoogleOidc, Bitacora, Parametros, Validator)
│   ├── views\       layout.php (menú lateral + cabecera), sin_perfil.php, error.php
│   └── assets\      css y js comunes (estilo, validaciones, helper AJAX)
├── apache\          intranet.conf (alias y permisos; se incluye desde httpd.conf)
├── portal\
│   ├── public\      login, auth\ (Google), api\, portada, sin_perfil, logout, admin\
│   └── views\       vistas del portal
├── egresos\
│   ├── public\      páginas, api\, assets\ propios de Egresos
│   ├── views\       vistas de Egresos
│   ├── src\Services\
│   └── templates\   plantilla del PDF y del correo
├── sql\             intranet_schema.sql, crear_admin.php
├── docs\            documento de diseño (Markdown)
└── vendor\          Composer (uno solo, compartido)
```

Cada sistema nuevo se agrega como carpeta hermana de `egresos\`, con su `public\` y su esquema en la base.

## Apache y URLs

- Solo se publican las carpetas `public\` mediante alias:
  - `/intranet` → `C:/xampp/htdocs/INTRANET/portal/public`
  - `/egresos`  → `C:/xampp/htdocs/INTRANET/egresos/public`
- `Require all denied` sobre `C:/xampp/htdocs/INTRANET` (config, comun\src, sql, docs y vendor nunca accesibles desde el navegador). `comun\assets` se publica con su propio alias `/intranet-assets`.
- La raíz `/` redirige a `/intranet`.
- Rutas base en `config.php` (`'rutas' => ['INTRANET' => '/intranet', 'EGRESOS' => '/egresos', 'ASSETS' => '/intranet-assets']`). Todo enlace, recurso y llamada AJAX se arma con `View::url()` y `View::asset()`; nunca rutas escritas a mano.

## Seguridad: usuarios, perfiles y accesos

- **Una cuenta por persona** para toda la intranet (`seg.Usuarios`). La gran mayoría son cuentas `GOOGLE` (ver abajo), sin contraseña en la intranet; solo existe una cuenta `LOCAL` de emergencia para administradores.
- **Perfiles por sistema** (`seg.Perfiles`). Egresos: ADMIN, APROBADOR, DIGITADOR, TESORERIA, CONSULTA. Administración de la intranet: ADMIN.
- **Acceso individual**: `seg.UsuarioPerfiles` asigna como máximo un perfil por usuario y sistema, con vencimiento opcional (`VigenteHasta`). Sin fila = sin acceso.
- **Sesión única** de la intranet: cookie `INTRANET_SID`, `path=/`, `HttpOnly`, `Secure`, `SameSite=Lax`; `session_regenerate_id(true)` al iniciar sesión; expira por inactividad (`seg.Parametros.SESION_MINUTOS`). El login está solo en el portal; cerrar sesión sale de todos los sistemas.
- **Portada**: muestra todos los sistemas activos (`seg.fn_PortalUsuario(@UsuarioId)`). Los que el usuario no tiene se ven deshabilitados, con un candado.
- **Entrada a un sistema**: cada página llama `Auth::requireSistema('EGRESOS')`, que vuelve a consultar `seg.vw_AccesosUsuario` en cada petición (así un acceso revocado se corta de inmediato). Si no tiene perfil:
  - página: respuesta 403 con el mensaje **"Usted no tiene el perfil para este sistema"** y un botón para volver a la portada;
  - endpoint AJAX: HTTP 403 con `{"ok":false,"error":{"codigo":"SIN_PERFIL","mensaje":"Usted no tiene el perfil para este sistema"}}`.
- Dentro del sistema, `Auth::requirePerfil('EGRESOS', ['DIGITADOR','ADMIN'])` limita cada acción, y en egresos también se verifica por fila.
- Intentos de login con contraseña (solo cuentas LOCAL): bloqueo tras `LOGIN_MAX_INTENTOS` (5) por `LOGIN_MINUTOS_BLOQUEO` (15). Todo inicio de sesión, rechazo por perfil y cambio de accesos queda en `seg.Bitacora`.

## Inicio de sesión con Google Workspace

- Las personas entran con su cuenta de Google Workspace de los dominios en `seg.Parametros.GOOGLE_DOMINIOS` (`nissui.cl,yadran.cl`). Google confirma quién es; la intranet decide si entra y con qué perfil.
- Flujo OpenID Connect con código de autorización en el servidor: `state` y `nonce` guardados en la sesión, PKCE, scopes `openid email profile` (nada más). Client ID y Client Secret en `config.php`, nunca en la base ni en el repositorio.
- Al volver de Google, `comun/src/GoogleOidc.php` canjea el código directamente con el endpoint de tokens de Google por HTTPS (certificado verificado) y valida el ID token: `iss`, `aud` = nuestro Client ID, `exp`, `nonce`, `email_verified = true` y `hd` dentro de los dominios permitidos. Como el token llega directo de Google por TLS, no se verifica su firma (lo permite la especificación OpenID Connect). El parámetro `hd` de la URL de autorización es solo una sugerencia: la validación real es el claim `hd` del token.
- Después busca el correo en `seg.Usuarios` con `MetodoAcceso = 'GOOGLE'` y `Activo = 1`. Si no existe o está inactivo: "Su cuenta no está habilitada en la intranet. Contacte al administrador." No se crean usuarios automáticamente: el administrador los crea antes y les asigna perfiles.
- En el primer ingreso se guarda `GoogleSub`; en los siguientes se exige que coincida (protege si un correo se reasigna a otra persona).
- Botón de Google según sus pautas de marca oficiales. Debajo, un enlace discreto "Acceso con contraseña (solo administradores)" para la cuenta LOCAL de emergencia.
- Requisitos de Google: la URL de retorno debe ser HTTPS, con un nombre de dominio público (no una IP ni `.local`), por ejemplo `https://intranet.nissui.cl/intranet/auth/callback.php`. Puede resolverse solo en el DNS interno, pero necesita un certificado válido.
- Sin librerías externas: solo cURL de PHP. Si cURL no encuentra los certificados raíz, configure `google.ca_bundle` en `config.php`.

## Reglas de código (obligatorias)

- Toda consulta con sentencias preparadas de PDO y parámetros. Nunca concatenar datos del usuario en SQL; columnas de orden y filtros contra listas blancas.
- Contraseñas con `password_hash()` / `password_verify()`, `password_needs_rehash()` al iniciar sesión. Mínimo 10 caracteres con letras y números.
- Validar en el cliente (para guiar) y en el servidor (fuente de verdad). Reglas compartidas en `comun/src/Validator.php` y `Intranet.validar` de `comun/assets/js/intranet.js`.
- Token CSRF por sesión en la cabecera `X-CSRF-Token` para todo POST, comparado con `hash_equals`.
- Salida escapada: `htmlspecialchars` en PHP, `.text()` en jQuery (nunca `.html()` con datos del usuario).
- Montos en `DECIMAL`, nunca `float`; en PHP como texto o con `bcmath`. Fechas enviadas a SQL siempre en `AAAA-MM-DD`.
- Sobre JSON: `{"ok":true,"data":...}` o `{"ok":false,"error":{"codigo","mensaje","campos"}}`. HTTP 200, 400 validación, 401 sin sesión, 403 sin permiso o sin perfil, 409 conflicto, 500 error interno (detalle solo al log).
- Errores de negocio de los procedimientos: `THROW 50001–50099`; PHP los traduce a 400/409 con el mensaje tal cual.
- Cada sistema usa su esquema (`egr.` para Egresos) y lee la seguridad solo desde `seg.`.

## Base de datos (`sql/intranet_schema.sql`)

- `seg`: Usuarios, Cargos, Departamentos, Sistemas, Perfiles, UsuarioPerfiles, Comunicados, Bitacora, Parametros; vista `vw_AccesosUsuario`; función `fn_PortalUsuario`.
- `egr`: catálogos (Monedas, TiposCambio, EstadosEgreso, TiposDocumento, FormasPago, Bancos, Destinos), maestros (Solicitantes, Proveedores, CuentasContables, Categorias, CentrosCosto, ReglasAprobacion), Egresos y seguimiento (EgresoAprobaciones, EgresoHistorial, EnviosCorreo), Parametros; vistas `vw_Aprobadores`, `vw_Egresos`, `vw_PendientesAprobacion`; procedimientos `sp_Egreso_Enviar`, `sp_Egreso_Resolver`, `sp_Egreso_Pagar`, `sp_Egreso_Anular`, `sp_Reglas_Simular`.
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

- Etapa 1 (base del portal) generada: login con Google (OIDC + PKCE, sin librerías) y LOCAL, portada con sistemas, pendientes y comunicados, sin_perfil, logout, listado de usuarios en modo lectura (`/intranet/admin/`) y entrada provisional de Egresos.
- Pendiente de la etapa 1: crear y editar usuarios, cargos, departamentos y accesos; publicar comunicados; cambio de contraseña de la cuenta LOCAL.
- Nuevas páginas: `require dirname(__DIR__, N) . '/comun/src/bootstrap.php';`, luego `Auth::requireSistema('CODIGO')` y `View::render(vista, [...])`.

## Plan por etapas

1. Base común y portal: `config.php`, conexión, inicio de sesión con Google Workspace (y cuenta LOCAL de emergencia), sesión de la intranet, portada con todos los sistemas, página "sin perfil", administración de usuarios, cargos, departamentos y accesos por sistema.
2. Egresos: layout con menú según perfil y mantenedores.
3. Monedas y tipos de cambio.
4. Registro de egresos.
5. Reglas de aprobación, envío y bandeja del aprobador.
6. Tesorería.
7. PDF y envío por correo.
8. Reportes, Excel y pruebas del flujo con los cinco perfiles.

Cada etapa se prueba antes de pasar a la siguiente.
