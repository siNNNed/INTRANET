/* =====================================================================
   Migración 3.0 → 3.1: ingreso solo con correo y contraseña (sin Google)
   Ejecutar UNA vez en SQL Server Management Studio sobre una base
   Intranet creada con la versión 3.0. Se puede repetir sin problemas.

   - Quita MetodoAcceso y GoogleSub de seg.Usuarios.
   - Las cuentas que eran de Google quedan SIN contraseña (no pueden
     entrar) hasta que el administrador les asigne una desde
     /intranet/admin/. Se les marca "debe cambiar contraseña".
   - Borra el parámetro GOOGLE_DOMINIOS.
   Una instalación nueva no necesita este archivo: intranet_schema.sql
   ya viene en la versión 3.1.
   ===================================================================== */
USE Intranet;
GO

IF COL_LENGTH('seg.Usuarios', 'MetodoAcceso') IS NOT NULL
BEGIN
    -- Restricciones e índice que dependen de las columnas
    IF OBJECT_ID(N'seg.CK_Usuarios_Password', N'C') IS NOT NULL ALTER TABLE seg.Usuarios DROP CONSTRAINT CK_Usuarios_Password;
    IF OBJECT_ID(N'seg.CK_Usuarios_Metodo',   N'C') IS NOT NULL ALTER TABLE seg.Usuarios DROP CONSTRAINT CK_Usuarios_Metodo;
    IF OBJECT_ID(N'seg.DF_Usuarios_Metodo',   N'D') IS NOT NULL ALTER TABLE seg.Usuarios DROP CONSTRAINT DF_Usuarios_Metodo;
    IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Usuarios_GoogleSub' AND object_id = OBJECT_ID(N'seg.Usuarios'))
        DROP INDEX UX_Usuarios_GoogleSub ON seg.Usuarios;

    -- Las cuentas de Google no tienen contraseña: deberán cambiarla cuando el administrador les asigne una.
    EXEC (N'UPDATE seg.Usuarios SET PasswordHash = NULL, DebeCambiarPassword = 1 WHERE MetodoAcceso = ''GOOGLE''');

    ALTER TABLE seg.Usuarios DROP COLUMN MetodoAcceso;
END
GO

IF COL_LENGTH('seg.Usuarios', 'GoogleSub') IS NOT NULL
    ALTER TABLE seg.Usuarios DROP COLUMN GoogleSub;
GO

DELETE FROM seg.Parametros WHERE Clave = 'GOOGLE_DOMINIOS';
UPDATE seg.Comunicados
   SET Resumen = N'Ingrese con su correo y la contraseña que le entregó el administrador. Si no ve un sistema, solicite acceso a TI.'
 WHERE Titulo = N'Nueva intranet corporativa' AND Resumen LIKE N'%Google%';
GO

SELECT UsuarioId, Nombre, Email,
       CASE WHEN PasswordHash IS NULL THEN N'Sin contraseña: asígnela en /intranet/admin/' ELSE N'Con contraseña' END AS Estado
  FROM seg.Usuarios
 ORDER BY UsuarioId;
GO
