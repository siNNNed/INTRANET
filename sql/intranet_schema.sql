/* =====================================================================
   INTRANET — Base de datos común de la intranet
   Motor: SQL Server 2016 SP1 o superior (probado para 2019)
   Versión: 3.1 (2026-11-19) — ingreso solo con correo y contraseña

   Base Intranet, esquema seg: seguridad común a todos los sistemas
   (usuarios, sistemas, perfiles por sistema, accesos, cargos,
   departamentos, comunicados, bitácora y parámetros de la empresa).

   Cada sistema tiene su PROPIA base (Egresos, y las que vengan) y lee
   los usuarios y accesos de aquí mediante sinónimos.

   Orden de ejecución:
     1. sql\intranet_schema.sql   (este archivo)
     2. sql\egresos_schema.sql
     3. php sql\crear_admin.php
   ===================================================================== */

/* ---------------------------------------------------------------------
   1. Base de datos y esquema
   --------------------------------------------------------------------- */
IF DB_ID(N'Intranet') IS NULL
    CREATE DATABASE Intranet COLLATE Modern_Spanish_CI_AS;
GO

USE Intranet;
GO

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF SCHEMA_ID(N'seg') IS NULL EXEC (N'CREATE SCHEMA seg AUTHORIZATION dbo');
GO
/* ---------------------------------------------------------------------
   2. Tablas, datos iniciales y vistas de seguridad
   --------------------------------------------------------------------- */
CREATE TABLE seg.Cargos (
    CargoId     SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Cargos PRIMARY KEY,
    Codigo      VARCHAR(20)   NOT NULL CONSTRAINT UQ_Cargos_Codigo UNIQUE,
    Nombre      NVARCHAR(100) NOT NULL,
    Activo      BIT           NOT NULL CONSTRAINT DF_Cargos_Activo DEFAULT (1)
);
GO

CREATE TABLE seg.Departamentos (
    DepartamentoId  SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Departamentos PRIMARY KEY,
    Codigo          VARCHAR(20)   NOT NULL CONSTRAINT UQ_Departamentos_Codigo UNIQUE,
    Nombre          NVARCHAR(100) NOT NULL,
    JefeUsuarioId   INT           NULL,          -- FK a seg.Usuarios (se agrega más abajo)
    Activo          BIT           NOT NULL CONSTRAINT DF_Departamentos_Activo DEFAULT (1)
);
GO

/* Una cuenta por persona para toda la intranet. Los accesos van en seg.UsuarioPerfiles.
   Se ingresa con correo y contraseña (password_hash() de PHP). El administrador crea la
   cuenta con una contraseña inicial y, por defecto, la persona debe cambiarla al entrar.
   PasswordHash NULL = cuenta sin contraseña asignada: no puede ingresar. */
CREATE TABLE seg.Usuarios (
    UsuarioId           INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Usuarios PRIMARY KEY,
    Rut                 VARCHAR(10)    NULL,                   -- 12345678-K
    Nombre              NVARCHAR(100)  NOT NULL,
    Email               NVARCHAR(150)  NOT NULL,
    PasswordHash        VARCHAR(255)   NULL,                   -- password_hash() de PHP
    CargoId             SMALLINT       NULL CONSTRAINT FK_Usuarios_Cargos REFERENCES seg.Cargos (CargoId),
    DepartamentoId      SMALLINT       NULL CONSTRAINT FK_Usuarios_Departamentos REFERENCES seg.Departamentos (DepartamentoId),
    Activo              BIT            NOT NULL CONSTRAINT DF_Usuarios_Activo DEFAULT (1),
    DebeCambiarPassword BIT            NOT NULL CONSTRAINT DF_Usuarios_DebeCambiar DEFAULT (1),
    IntentosFallidos    TINYINT        NOT NULL CONSTRAINT DF_Usuarios_Intentos DEFAULT (0),
    BloqueadoHasta      DATETIME2(0)   NULL,
    UltimoAcceso        DATETIME2(0)   NULL,
    CreadoEn            DATETIME2(0)   NOT NULL CONSTRAINT DF_Usuarios_CreadoEn DEFAULT (SYSDATETIME()),
    CreadoPor           INT            NULL,
    ActualizadoEn       DATETIME2(0)   NULL,
    CONSTRAINT UQ_Usuarios_Email UNIQUE (Email),
    CONSTRAINT CK_Usuarios_Rut CHECK (Rut IS NULL OR Rut LIKE '%[0-9]-[0-9K]')
);
GO
CREATE UNIQUE INDEX UX_Usuarios_Rut ON seg.Usuarios (Rut) WHERE Rut IS NOT NULL;
GO

ALTER TABLE seg.Departamentos
    ADD CONSTRAINT FK_Departamentos_Jefe FOREIGN KEY (JefeUsuarioId) REFERENCES seg.Usuarios (UsuarioId);
GO

/* Sistemas de la intranet. Se agregan con el script de cada sistema nuevo. */
CREATE TABLE seg.Sistemas (
    SistemaId   SMALLINT      NOT NULL CONSTRAINT PK_Sistemas PRIMARY KEY,
    Codigo      VARCHAR(20)   NOT NULL CONSTRAINT UQ_Sistemas_Codigo UNIQUE,
    Nombre      NVARCHAR(60)  NOT NULL,
    Descripcion NVARCHAR(200) NULL,
    Url         NVARCHAR(100) NOT NULL,                       -- ruta base, ej. /egresos
    Icono       VARCHAR(30)   NULL,                           -- nombre del ícono en la portada
    Orden       TINYINT       NOT NULL CONSTRAINT DF_Sistemas_Orden DEFAULT (1),
    Activo      BIT           NOT NULL CONSTRAINT DF_Sistemas_Activo DEFAULT (1)
);
GO

/* Perfiles propios de cada sistema. El código es el que usa el programa. */
CREATE TABLE seg.Perfiles (
    PerfilId    INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Perfiles PRIMARY KEY,
    SistemaId   SMALLINT      NOT NULL CONSTRAINT FK_Perfiles_Sistemas REFERENCES seg.Sistemas (SistemaId),
    Codigo      VARCHAR(20)   NOT NULL,
    Nombre      NVARCHAR(60)  NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Activo      BIT           NOT NULL CONSTRAINT DF_Perfiles_Activo DEFAULT (1),
    CONSTRAINT UQ_Perfiles_Sistema_Codigo UNIQUE (SistemaId, Codigo),
    CONSTRAINT UQ_Perfiles_Id_Sistema UNIQUE (PerfilId, SistemaId)   -- destino de la FK compuesta
);
GO

/* Acceso de un usuario a un sistema: un perfil por usuario y sistema.
   Sin fila = sin acceso a ese sistema. La FK compuesta impide asignar un perfil de otro sistema. */
CREATE TABLE seg.UsuarioPerfiles (
    UsuarioId   INT           NOT NULL CONSTRAINT FK_UsuPerf_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    SistemaId   SMALLINT      NOT NULL,
    PerfilId    INT           NOT NULL,
    VigenteHasta DATE         NULL,                           -- NULL = sin vencimiento
    AsignadoPor INT           NULL CONSTRAINT FK_UsuPerf_AsignadoPor REFERENCES seg.Usuarios (UsuarioId),
    AsignadoEn  DATETIME2(0)  NOT NULL CONSTRAINT DF_UsuPerf_AsignadoEn DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_UsuarioPerfiles PRIMARY KEY (UsuarioId, SistemaId),
    CONSTRAINT FK_UsuPerf_Perfil FOREIGN KEY (PerfilId, SistemaId) REFERENCES seg.Perfiles (PerfilId, SistemaId)
);
GO
CREATE INDEX IX_UsuPerf_Perfil ON seg.UsuarioPerfiles (PerfilId);
GO

CREATE TABLE seg.Bitacora (
    BitacoraId      BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Bitacora PRIMARY KEY,
    SistemaId       SMALLINT       NULL CONSTRAINT FK_Bitacora_Sistemas REFERENCES seg.Sistemas (SistemaId),
    UsuarioId       INT            NULL CONSTRAINT FK_Bitacora_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    Accion          VARCHAR(40)    NOT NULL,                   -- LOGIN_OK, LOGIN_FALLIDO, ACCESO_ASIGNADO, CREAR, EDITAR...
    Entidad         VARCHAR(40)    NULL,                       -- Usuario, Egreso, Proveedor...
    EntidadId       INT            NULL,
    Detalle         NVARCHAR(2000) NULL,                       -- JSON con cambios relevantes
    IP              VARCHAR(45)    NULL,
    Fecha           DATETIME2(0)   NOT NULL CONSTRAINT DF_Bitacora_Fecha DEFAULT (SYSDATETIME())
);
GO
CREATE INDEX IX_Bitacora_Fecha   ON seg.Bitacora (Fecha);
CREATE INDEX IX_Bitacora_Entidad ON seg.Bitacora (SistemaId, Entidad, EntidadId);
CREATE INDEX IX_Bitacora_Usuario ON seg.Bitacora (UsuarioId, Fecha);
GO

/* Parámetros comunes de la intranet (empresa, login). */
CREATE TABLE seg.Parametros (
    Clave           VARCHAR(50)    NOT NULL CONSTRAINT PK_Parametros PRIMARY KEY,
    Valor           NVARCHAR(500)  NULL,
    Descripcion     NVARCHAR(200)  NULL
);
GO

INSERT INTO seg.Sistemas (SistemaId, Codigo, Nombre, Descripcion, Url, Icono, Orden) VALUES
    (1, 'INTRANET', N'Administración de la intranet', N'Usuarios, perfiles y accesos a los sistemas', N'/intranet/admin', 'shield', 99),
    (2, 'EGRESOS',  N'Egresos', N'Registro, aprobación y pago de egresos', N'/egresos', 'wallet', 1);

INSERT INTO seg.Perfiles (SistemaId, Codigo, Nombre, Descripcion) VALUES
    (1, 'ADMIN',     N'Administrador de la intranet', N'Crea usuarios y asigna sus perfiles en cada sistema'),
    (2, 'ADMIN',     N'Administrador', N'Mantenedores, reglas de aprobación y anulaciones'),
    (2, 'APROBADOR', N'Aprobador', N'Aprueba, devuelve o rechaza egresos de su nivel'),
    (2, 'DIGITADOR', N'Digitador', N'Registra egresos y los envía a aprobación'),
    (2, 'TESORERIA', N'Tesorería', N'Registra el pago de egresos aprobados'),
    (2, 'CONSULTA',  N'Consulta', N'Solo lectura y reportes');

INSERT INTO seg.Parametros (Clave, Valor, Descripcion) VALUES
    ('EMPRESA_RAZON_SOCIAL',  N'Salmones Antártica S.A.', N'Razón social de la empresa'),
    ('EMPRESA_RUT',           N'', N'RUT de la empresa'),
    ('EMPRESA_DIRECCION',     N'', N'Dirección de la empresa'),
    ('EMPRESA_LOGO',          N'/intranet-assets/img/logo_oscuro_256.png', N'Logo para fondos claros (PDF, correos)'),
    ('LOGIN_MAX_INTENTOS',    N'5',  N'Intentos antes de bloquear'),
    ('LOGIN_MINUTOS_BLOQUEO', N'15', N'Minutos de bloqueo'),
    ('SESION_MINUTOS',        N'30', N'Minutos de inactividad antes de cerrar la sesión');
GO

/* Comunicados internos que se muestran en la portada. */
CREATE TABLE seg.Comunicados (
    ComunicadoId    INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Comunicados PRIMARY KEY,
    Categoria       NVARCHAR(30)   NOT NULL,                   -- Finanzas, Intranet, Personas...
    Titulo          NVARCHAR(150)  NOT NULL,
    Resumen         NVARCHAR(500)  NOT NULL,
    Destacado       BIT            NOT NULL CONSTRAINT DF_Comunicados_Destacado DEFAULT (0),
    PublicadoEn     DATETIME2(0)   NOT NULL CONSTRAINT DF_Comunicados_PublicadoEn DEFAULT (SYSDATETIME()),
    VigenteHasta    DATE           NULL,                       -- NULL = sin vencimiento
    Activo          BIT            NOT NULL CONSTRAINT DF_Comunicados_Activo DEFAULT (1),
    CreadoPor       INT            NULL CONSTRAINT FK_Comunicados_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    CreadoEn        DATETIME2(0)   NOT NULL CONSTRAINT DF_Comunicados_CreadoEn DEFAULT (SYSDATETIME())
);
GO
CREATE INDEX IX_Comunicados_Publicacion ON seg.Comunicados (Activo, PublicadoEn DESC);
GO

INSERT INTO seg.Comunicados (Categoria, Titulo, Resumen, Destacado) VALUES
    (N'Intranet', N'Nueva intranet corporativa',
     N'Ingrese con su correo y la contraseña que le entregó el administrador. Si no ve un sistema, solicite acceso a TI.', 1);
GO

/* Accesos vigentes: usuario activo + sistema activo + perfil activo + no vencido.
   La usan el login (para armar la portada) y cada sistema (para validar permisos). */
CREATE OR ALTER VIEW seg.vw_AccesosUsuario
AS
SELECT
    u.UsuarioId, u.Nombre AS Usuario, u.Email,
    s.SistemaId, s.Codigo AS SistemaCodigo, s.Nombre AS Sistema, s.Url, s.Icono, s.Orden,
    p.PerfilId, p.Codigo AS PerfilCodigo, p.Nombre AS Perfil
FROM seg.UsuarioPerfiles up
JOIN seg.Usuarios u ON u.UsuarioId = up.UsuarioId AND u.Activo = 1
JOIN seg.Sistemas s ON s.SistemaId = up.SistemaId AND s.Activo = 1
JOIN seg.Perfiles p ON p.PerfilId = up.PerfilId AND p.Activo = 1
WHERE up.VigenteHasta IS NULL OR up.VigenteHasta >= CAST(SYSDATETIME() AS DATE);
GO

/* Portada de la intranet: TODOS los sistemas activos, marcando si el usuario tiene perfil.
   Los que no tienen perfil se muestran deshabilitados; al intentar entrar, el sistema
   responde "Usted no tiene el perfil para este sistema". */
CREATE OR ALTER FUNCTION seg.fn_PortalUsuario (@UsuarioId INT)
RETURNS TABLE
AS
RETURN
    SELECT
        s.SistemaId, s.Codigo AS SistemaCodigo, s.Nombre AS Sistema, s.Descripcion,
        s.Url, s.Icono, s.Orden,
        a.PerfilCodigo, a.Perfil,
        CAST(CASE WHEN a.PerfilId IS NULL THEN 0 ELSE 1 END AS BIT) AS TienePerfil
    FROM seg.Sistemas s
    LEFT JOIN seg.vw_AccesosUsuario a
           ON a.SistemaId = s.SistemaId AND a.UsuarioId = @UsuarioId
    WHERE s.Activo = 1;
GO

/* =====================================================================
   Fin del script de Intranet.
   Siguiente paso: ejecutar sql\egresos_schema.sql
   ===================================================================== */
