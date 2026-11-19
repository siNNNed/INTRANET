# Intranet Salmones Antártica — instalación (etapa 1: portal)

Esta carpeta va completa en `C:\xampp\htdocs\INTRANET`.

## 1. Bases de datos

Hay una base por sistema: `Intranet` (usuarios, perfiles y accesos, esquema `seg`) y `Egresos`
(todo lo de egresos, esquema `egr`). Egresos lee los usuarios de Intranet mediante sinónimos.

1. En SQL Server Management Studio ejecute, **en este orden**:
   1. `sql\intranet_schema.sql` (versión 3.0). Crea la base `Intranet`.
   2. `sql\egresos_schema.sql` (versión 3.0). Crea la base `Egresos`. Si `Intranet` no existe, se detiene sin crear nada.

   Si ejecutó una versión anterior, borre antes las bases `Intranet` y `Egresos`; todavía no tienen datos.
2. Dé acceso al usuario de la aplicación en **las dos** bases. El login `intranet_app` ya existe; si
   olvidó su contraseña, cámbiela con `ALTER LOGIN` (escríbala usted, no la pegue en el chat):

   ```sql
   USE master;
   -- Solo si el login no existe:
   -- CREATE LOGIN intranet_app WITH PASSWORD = 'CambiaEstaClave_2026!', CHECK_POLICY = ON;
   -- Solo si olvidó la contraseña:
   -- ALTER LOGIN intranet_app WITH PASSWORD = 'NuevaClave_2026!';
   GO
   USE Intranet;
   CREATE USER intranet_app FOR LOGIN intranet_app;
   ALTER ROLE db_owner ADD MEMBER intranet_app;   -- solo en desarrollo
   GO
   USE Egresos;
   CREATE USER intranet_app FOR LOGIN intranet_app;
   ALTER ROLE db_owner ADD MEMBER intranet_app;   -- solo en desarrollo
   GO
   ```
3. Verifique: `Intranet` debe tener 9 tablas en `seg` y `Egresos` 18 tablas en `egr`.

   ```sql
   SELECT 'Intranet' AS Base, COUNT(*) AS Tablas FROM Intranet.sys.tables WHERE SCHEMA_NAME(schema_id) = 'seg'
   UNION ALL
   SELECT 'Egresos', COUNT(*) FROM Egresos.sys.tables WHERE SCHEMA_NAME(schema_id) = 'egr';
   ```

## 2. Configuración

```powershell
cd C:\xampp\htdocs\INTRANET
Copy-Item config\config.example.php config\config.php
notepad config\config.php
```

Complete al menos `db.pass` con la contraseña de `intranet_app` (sirve para las dos bases; los nombres están en `db.bases`). Deje `google` vacío por ahora.

## 3. Apache

1. Respalde `C:\xampp\apache\conf\httpd.conf`.
2. Agregue al final de `httpd.conf` esta línea:

   ```
   Include "C:/xampp/htdocs/INTRANET/apache/intranet.conf"
   ```

3. Reinicie Apache desde el panel de XAMPP (Stop y Start). Si no arranca, revise
   `C:\xampp\apache\logs\error.log`.

## 4. Cuenta de emergencia (administrador)

```powershell
cd C:\xampp\htdocs\INTRANET
C:\xampp\php\php.exe sql\crear_admin.php
```

Use un correo de la empresa y una contraseña de al menos 10 caracteres con letras y números.
Esta cuenta tiene perfil de administrador en la intranet y en Egresos.

## 5. Probar

| Dirección | Qué debe pasar |
| --- | --- |
| `http://localhost/` | Redirige a `/intranet/` y luego al login |
| `http://localhost/intranet/login.php` | Login. Use "Acceso con contraseña" con la cuenta de emergencia |
| `http://localhost/intranet/` | Portada con los sistemas y comunicados |
| `http://localhost/egresos/` | Entrada de Egresos con su perfil |
| `http://localhost/intranet/admin/` | Listado de usuarios (solo administradores) |
| `http://localhost/INTRANET/config/config.php` | Debe responder **403 Forbidden** |

Para probar el mensaje "Usted no tiene el perfil para este sistema", quite a un usuario su fila en
`seg.UsuarioPerfiles` para EGRESOS y entre a `/egresos/`.

## 6. Google Workspace (cuando esté el dominio)

1. En Google Cloud Console cree un proyecto, configure la pantalla de consentimiento
   (Interna si nissui.cl y yadran.cl son la misma organización; Externa si son dos) y cree un
   **ID de cliente OAuth** de tipo **Aplicación web**.
2. URI de redireccionamiento autorizado: `https://<dominio-de-la-intranet>/intranet/auth/callback.php`
   (HTTPS, con nombre de dominio público; no sirve una IP).
3. Copie el Client ID y el Client Secret a `config\config.php` → `google`.
4. Cree las personas en `seg.Usuarios` con `MetodoAcceso = 'GOOGLE'` y sin contraseña, y asígneles
   perfiles en `seg.UsuarioPerfiles` (la pantalla para hacerlo llega en la próxima etapa).

## Notas

- Bootstrap 5.3.3, jQuery 3.7.1 y la fuente Inter se cargan desde CDN. Si el servidor no tiene salida
  a internet, descárguelos en `comun\assets\vendor` y cambie las rutas en `comun\views\layout.php` y
  `portal\public\login.php`.
- Producción: `app.entorno = 'produccion'`, `sesion.secure = true`, HTTPS con certificado válido y un
  usuario de base con permisos limitados en vez de `db_owner`.
