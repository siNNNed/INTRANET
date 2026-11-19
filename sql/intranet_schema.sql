/* =====================================================================
   INTRANET — Script de creación de base de datos
   Motor: SQL Server 2016 SP1 o superior (probado para 2019)
   Versión: 2.2 (2026-11-19) — Google Workspace y comunicados de la portada

   Una sola base, Intranet, con un esquema por área:
     seg  Seguridad común a todos los sistemas: usuarios, sistemas,
          perfiles por sistema, accesos, cargos, departamentos,
          comunicados, bitácora y parámetros de la empresa.
     egr  Sistema de Egresos.
   Cada sistema nuevo de la intranet agrega su propio esquema.

   Contenido:
     1. Base de datos y esquemas
     2. Esquema seg (tablas, datos iniciales, vista de accesos)
     3. Esquema egr: catálogos
     4. Esquema egr: maestros
     5. Esquema egr: egresos y seguimiento
     6. Esquema egr: datos iniciales
     7. Esquema egr: función de tipo de cambio y vistas
     8. Esquema egr: procedimientos del flujo

   El usuario administrador inicial NO se crea aquí, porque su
   contraseña debe generarse con password_hash() de PHP. Después de
   ejecutar este script, corra:  php sql/crear_admin.php
   ===================================================================== */

/* ---------------------------------------------------------------------
   1. Base de datos y esquemas
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
IF SCHEMA_ID(N'egr') IS NULL EXEC (N'CREATE SCHEMA egr AUTHORIZATION dbo');
GO

/* ---------------------------------------------------------------------
   2. Esquema seg: seguridad y datos comunes de la intranet
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
   GOOGLE: entra con su cuenta de Google Workspace (dominios en GOOGLE_DOMINIOS); el
           administrador la crea antes con su correo y la intranet no guarda contraseña.
   LOCAL:  cuenta de emergencia con contraseña, solo para administradores. */
CREATE TABLE seg.Usuarios (
    UsuarioId           INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Usuarios PRIMARY KEY,
    Rut                 VARCHAR(10)    NULL,                   -- 12345678-K
    Nombre              NVARCHAR(100)  NOT NULL,
    Email               NVARCHAR(150)  NOT NULL,
    MetodoAcceso        VARCHAR(10)    NOT NULL CONSTRAINT DF_Usuarios_Metodo DEFAULT ('GOOGLE'),  -- GOOGLE o LOCAL
    GoogleSub           VARCHAR(64)    NULL,                   -- id de Google ('sub' del token); se guarda en el primer ingreso
    PasswordHash        VARCHAR(255)   NULL,                   -- solo cuentas LOCAL: password_hash() de PHP
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
    CONSTRAINT CK_Usuarios_Rut CHECK (Rut IS NULL OR Rut LIKE '%[0-9]-[0-9K]'),
    CONSTRAINT CK_Usuarios_Metodo CHECK (MetodoAcceso IN ('GOOGLE', 'LOCAL')),
    -- una cuenta LOCAL necesita contraseña; una cuenta GOOGLE nunca guarda contraseña
    CONSTRAINT CK_Usuarios_Password CHECK (
        (MetodoAcceso = 'LOCAL'  AND PasswordHash IS NOT NULL) OR
        (MetodoAcceso = 'GOOGLE' AND PasswordHash IS NULL))
);
GO
CREATE UNIQUE INDEX UX_Usuarios_Rut ON seg.Usuarios (Rut) WHERE Rut IS NOT NULL;
CREATE UNIQUE INDEX UX_Usuarios_GoogleSub ON seg.Usuarios (GoogleSub) WHERE GoogleSub IS NOT NULL;
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
    ('SESION_MINUTOS',        N'30', N'Minutos de inactividad antes de cerrar la sesión'),
    ('GOOGLE_DOMINIOS',       N'nissui.cl,yadran.cl', N'Dominios de Google Workspace que pueden iniciar sesión');
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
     N'Ahora entra con su cuenta de Google de la empresa. Si no ve un sistema, solicite acceso a TI.', 1);
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

/* ---------------------------------------------------------------------
   3. Esquema egr: catálogos
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'egr.seq_FolioEgreso', N'SO') IS NULL
    CREATE SEQUENCE egr.seq_FolioEgreso AS INT START WITH 1 INCREMENT BY 1 NO CACHE;
GO
CREATE TABLE egr.Monedas (
    MonedaId    CHAR(3)       NOT NULL CONSTRAINT PK_Monedas PRIMARY KEY,   -- CLP, USD, EUR, CLF (UF)
    Nombre      NVARCHAR(50)  NOT NULL,
    Simbolo     NVARCHAR(5)   NOT NULL,
    Decimales   TINYINT       NOT NULL CONSTRAINT CK_Monedas_Decimales CHECK (Decimales BETWEEN 0 AND 4),
    EsBase      BIT           NOT NULL CONSTRAINT DF_Monedas_EsBase DEFAULT (0),
    Activo      BIT           NOT NULL CONSTRAINT DF_Monedas_Activo DEFAULT (1)
);
GO
-- Solo una moneda base
CREATE UNIQUE INDEX UX_Monedas_Base ON egr.Monedas (EsBase) WHERE EsBase = 1;
GO

CREATE TABLE egr.EstadosEgreso (
    -- EstadoId y EsFinal son fijos (los usan los procedimientos del flujo).
    -- El mantenedor solo edita Nombre, Descripcion, Color y Orden.
    EstadoId    VARCHAR(10)   NOT NULL CONSTRAINT PK_EstadosEgreso PRIMARY KEY,
    Nombre      NVARCHAR(30)  NOT NULL,
    Descripcion NVARCHAR(200) NULL,
    Color       CHAR(7)       NOT NULL CONSTRAINT DF_EstadosEgreso_Color DEFAULT ('#6c757d')
                              CONSTRAINT CK_EstadosEgreso_Color CHECK (Color LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
    Orden       TINYINT       NOT NULL,
    EsFinal     BIT           NOT NULL CONSTRAINT DF_EstadosEgreso_EsFinal DEFAULT (0),
    ActualizadoEn DATETIME2(0) NULL
);
GO

CREATE TABLE egr.TiposDocumento (
    TipoDocumentoId SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_TiposDocumento PRIMARY KEY,
    Codigo      VARCHAR(10)   NOT NULL CONSTRAINT UQ_TiposDocumento_Codigo UNIQUE,
    Nombre      NVARCHAR(60)  NOT NULL,
    Activo      BIT           NOT NULL CONSTRAINT DF_TiposDocumento_Activo DEFAULT (1)
);
GO

CREATE TABLE egr.FormasPago (
    FormaPagoId SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_FormasPago PRIMARY KEY,
    Codigo      VARCHAR(10)   NOT NULL CONSTRAINT UQ_FormasPago_Codigo UNIQUE,
    Nombre      NVARCHAR(60)  NOT NULL,
    RequiereBanco      BIT    NOT NULL CONSTRAINT DF_FormasPago_ReqBanco DEFAULT (0),
    RequiereReferencia BIT    NOT NULL CONSTRAINT DF_FormasPago_ReqRef DEFAULT (0),
    Activo      BIT           NOT NULL CONSTRAINT DF_FormasPago_Activo DEFAULT (1)
);
GO

CREATE TABLE egr.Bancos (
    BancoId     SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Bancos PRIMARY KEY,
    Codigo      VARCHAR(10)   NOT NULL CONSTRAINT UQ_Bancos_Codigo UNIQUE,
    Nombre      NVARCHAR(80)  NOT NULL CONSTRAINT UQ_Bancos_Nombre UNIQUE,
    Activo      BIT           NOT NULL CONSTRAINT DF_Bancos_Activo DEFAULT (1)
);
GO

/* Destino del gasto: obra, sucursal, proyecto u otro uso que defina la empresa. */
CREATE TABLE egr.Destinos (
    DestinoId   INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Destinos PRIMARY KEY,
    Codigo      VARCHAR(20)   NOT NULL CONSTRAINT UQ_Destinos_Codigo UNIQUE,
    Nombre      NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Activo      BIT           NOT NULL CONSTRAINT DF_Destinos_Activo DEFAULT (1)
);
GO

/* ---------------------------------------------------------------------
   4. Esquema egr: maestros
   --------------------------------------------------------------------- */
/* Personas que solicitan el egreso. Pueden no tener cuenta en el sistema. */
CREATE TABLE egr.Solicitantes (
    SolicitanteId   INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Solicitantes PRIMARY KEY,
    Rut             VARCHAR(10)    NOT NULL,
    Nombre          NVARCHAR(100)  NOT NULL,
    CargoId         SMALLINT       NULL CONSTRAINT FK_Solicitantes_Cargos REFERENCES seg.Cargos (CargoId),
    DepartamentoId  SMALLINT       NULL CONSTRAINT FK_Solicitantes_Departamentos REFERENCES seg.Departamentos (DepartamentoId),
    Email           NVARCHAR(150)  NULL,
    Telefono        VARCHAR(20)    NULL,
    UsuarioId       INT            NULL CONSTRAINT FK_Solicitantes_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    Activo          BIT            NOT NULL CONSTRAINT DF_Solicitantes_Activo DEFAULT (1),
    CreadoEn        DATETIME2(0)   NOT NULL CONSTRAINT DF_Solicitantes_CreadoEn DEFAULT (SYSDATETIME()),
    ActualizadoEn   DATETIME2(0)   NULL,
    CONSTRAINT UQ_Solicitantes_Rut UNIQUE (Rut),
    CONSTRAINT CK_Solicitantes_Rut CHECK (Rut LIKE '%[0-9]-[0-9K]')
);
GO
CREATE UNIQUE INDEX UX_Solicitantes_Usuario ON egr.Solicitantes (UsuarioId) WHERE UsuarioId IS NOT NULL;
CREATE INDEX IX_Solicitantes_Nombre ON egr.Solicitantes (Nombre);
GO

CREATE TABLE egr.TiposCambio (
    MonedaId        CHAR(3)        NOT NULL CONSTRAINT FK_TiposCambio_Monedas REFERENCES egr.Monedas (MonedaId),
    Fecha           DATE           NOT NULL,
    ValorCLP        DECIMAL(18,6)  NOT NULL CONSTRAINT CK_TiposCambio_Valor CHECK (ValorCLP > 0),
    Fuente          NVARCHAR(50)   NULL,                       -- 'Manual', 'CSV', etc.
    RegistradoPor   INT            NULL CONSTRAINT FK_TiposCambio_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    RegistradoEn    DATETIME2(0)   NOT NULL CONSTRAINT DF_TiposCambio_RegistradoEn DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_TiposCambio PRIMARY KEY (MonedaId, Fecha),
    CONSTRAINT CK_TiposCambio_NoCLP CHECK (MonedaId <> 'CLP')
);
GO

CREATE TABLE egr.Proveedores (
    ProveedorId     INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Proveedores PRIMARY KEY,
    Rut             VARCHAR(10)    NOT NULL,
    RazonSocial     NVARCHAR(150)  NOT NULL,
    NombreFantasia  NVARCHAR(150)  NULL,
    Giro            NVARCHAR(150)  NULL,
    Direccion       NVARCHAR(200)  NULL,
    Comuna          NVARCHAR(60)   NULL,
    Ciudad          NVARCHAR(60)   NULL,
    Email           NVARCHAR(150)  NULL,
    Telefono        VARCHAR(20)    NULL,
    Contacto        NVARCHAR(100)  NULL,
    BancoId         SMALLINT       NULL CONSTRAINT FK_Proveedores_Bancos REFERENCES egr.Bancos (BancoId),
    TipoCuenta      NVARCHAR(30)   NULL,                       -- Corriente, Vista, Ahorro
    NumeroCuenta    VARCHAR(30)    NULL,
    EmailPagos      NVARCHAR(150)  NULL,
    Activo          BIT            NOT NULL CONSTRAINT DF_Proveedores_Activo DEFAULT (1),
    CreadoEn        DATETIME2(0)   NOT NULL CONSTRAINT DF_Proveedores_CreadoEn DEFAULT (SYSDATETIME()),
    CreadoPor       INT            NULL CONSTRAINT FK_Proveedores_CreadoPor REFERENCES seg.Usuarios (UsuarioId),
    ActualizadoEn   DATETIME2(0)   NULL,
    CONSTRAINT UQ_Proveedores_Rut UNIQUE (Rut),
    CONSTRAINT CK_Proveedores_Rut CHECK (Rut LIKE '%[0-9]-[0-9K]')
);
GO
CREATE INDEX IX_Proveedores_RazonSocial ON egr.Proveedores (RazonSocial);
GO

CREATE TABLE egr.CuentasContables (
    CuentaContableId INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_CuentasContables PRIMARY KEY,
    Codigo          VARCHAR(20)    NOT NULL CONSTRAINT UQ_CuentasContables_Codigo UNIQUE,
    Nombre          NVARCHAR(150)  NOT NULL,
    Tipo            NVARCHAR(30)   NULL,                       -- Gasto, Activo, Pasivo...
    Imputable       BIT            NOT NULL CONSTRAINT DF_CuentasContables_Imputable DEFAULT (1),
    Activo          BIT            NOT NULL CONSTRAINT DF_CuentasContables_Activo DEFAULT (1)
);
GO

CREATE TABLE egr.Categorias (
    CategoriaId     INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Categorias PRIMARY KEY,
    Codigo          VARCHAR(20)    NOT NULL CONSTRAINT UQ_Categorias_Codigo UNIQUE,
    Nombre          NVARCHAR(100)  NOT NULL,
    CuentaContableId INT           NULL CONSTRAINT FK_Categorias_Cuentas REFERENCES egr.CuentasContables (CuentaContableId),
    Activo          BIT            NOT NULL CONSTRAINT DF_Categorias_Activo DEFAULT (1)
);
GO

CREATE TABLE egr.CentrosCosto (
    CentroCostoId   INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_CentrosCosto PRIMARY KEY,
    Codigo          VARCHAR(20)    NOT NULL CONSTRAINT UQ_CentrosCosto_Codigo UNIQUE,
    Nombre          NVARCHAR(100)  NOT NULL,
    DepartamentoId  SMALLINT       NULL CONSTRAINT FK_CentrosCosto_Departamentos REFERENCES seg.Departamentos (DepartamentoId),
    Activo          BIT            NOT NULL CONSTRAINT DF_CentrosCosto_Activo DEFAULT (1)
);
GO

/* Jerarquía de autorizaciones.
   CentroCostoId NULL = regla general (se usa cuando el centro de costo no tiene reglas propias).
   Se exigen todos los niveles con MontoDesdeCLP <= MontoCLP del egreso.
   Varios aprobadores en el mismo nivel = basta que apruebe uno. */
CREATE TABLE egr.ReglasAprobacion (
    ReglaId         INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ReglasAprobacion PRIMARY KEY,
    CentroCostoId   INT            NULL CONSTRAINT FK_Reglas_CentrosCosto REFERENCES egr.CentrosCosto (CentroCostoId),
    Nivel           TINYINT        NOT NULL CONSTRAINT CK_Reglas_Nivel CHECK (Nivel BETWEEN 1 AND 9),
    MontoDesdeCLP   DECIMAL(18,0)  NOT NULL CONSTRAINT CK_Reglas_Monto CHECK (MontoDesdeCLP >= 0),
    AprobadorId     INT            NOT NULL CONSTRAINT FK_Reglas_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    Activo          BIT            NOT NULL CONSTRAINT DF_Reglas_Activo DEFAULT (1),
    CreadoEn        DATETIME2(0)   NOT NULL CONSTRAINT DF_Reglas_CreadoEn DEFAULT (SYSDATETIME()),
    CreadoPor       INT            NULL
);
GO
CREATE UNIQUE INDEX UX_Reglas_CC_Nivel_Aprobador
    ON egr.ReglasAprobacion (CentroCostoId, Nivel, AprobadorId) WHERE Activo = 1;
CREATE INDEX IX_Reglas_Aprobador ON egr.ReglasAprobacion (AprobadorId) WHERE Activo = 1;
GO

/* ---------------------------------------------------------------------
   5. Esquema egr: egresos y seguimiento
   --------------------------------------------------------------------- */
CREATE TABLE egr.Egresos (
    EgresoId        INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Egresos PRIMARY KEY,
    Folio           INT            NOT NULL CONSTRAINT DF_Egresos_Folio DEFAULT (NEXT VALUE FOR egr.seq_FolioEgreso),
    Fecha           DATE           NOT NULL,                   -- fecha del egreso (define el tipo de cambio)
    SolicitanteId   INT            NOT NULL CONSTRAINT FK_Egresos_Solicitantes REFERENCES egr.Solicitantes (SolicitanteId),
    DestinoId       INT            NOT NULL CONSTRAINT FK_Egresos_Destinos REFERENCES egr.Destinos (DestinoId),
    ProveedorId     INT            NOT NULL CONSTRAINT FK_Egresos_Proveedores REFERENCES egr.Proveedores (ProveedorId),
    CategoriaId     INT            NOT NULL CONSTRAINT FK_Egresos_Categorias REFERENCES egr.Categorias (CategoriaId),
    CentroCostoId   INT            NOT NULL CONSTRAINT FK_Egresos_CentrosCosto REFERENCES egr.CentrosCosto (CentroCostoId),
    CuentaContableId INT           NOT NULL CONSTRAINT FK_Egresos_Cuentas REFERENCES egr.CuentasContables (CuentaContableId),
    TipoDocumentoId SMALLINT       NULL CONSTRAINT FK_Egresos_TiposDocumento REFERENCES egr.TiposDocumento (TipoDocumentoId),
    NumeroDocumento VARCHAR(30)    NULL,
    FechaDocumento  DATE           NULL,
    FormaPagoId     SMALLINT       NULL CONSTRAINT FK_Egresos_FormasPago REFERENCES egr.FormasPago (FormaPagoId),
    Descripcion     NVARCHAR(500)  NOT NULL,

    -- Montos en moneda original
    MonedaId        CHAR(3)        NOT NULL CONSTRAINT FK_Egresos_Monedas REFERENCES egr.Monedas (MonedaId),
    MontoNeto       DECIMAL(18,4)  NULL,
    MontoImpuesto   DECIMAL(18,4)  NULL,
    MontoTotal      DECIMAL(18,4)  NOT NULL,
    -- Conversión a CLP (congelada al enviar a aprobación)
    TipoCambio      DECIMAL(18,6)  NOT NULL,
    MontoCLP        DECIMAL(18,0)  NOT NULL,

    -- Flujo
    EstadoId        VARCHAR(10)    NOT NULL CONSTRAINT DF_Egresos_Estado DEFAULT ('BORRADOR')
                                   CONSTRAINT FK_Egresos_Estados REFERENCES egr.EstadosEgreso (EstadoId),
    Ciclo           TINYINT        NOT NULL CONSTRAINT DF_Egresos_Ciclo DEFAULT (0),   -- sube en cada envío
    NivelActual     TINYINT        NULL,
    FechaEnvio      DATETIME2(0)   NULL,
    FechaAprobacion DATETIME2(0)   NULL,

    -- Pago (Tesorería)
    FechaPago       DATE           NULL,
    PagadoPor       INT            NULL CONSTRAINT FK_Egresos_PagadoPor REFERENCES seg.Usuarios (UsuarioId),
    BancoPagoId     SMALLINT       NULL CONSTRAINT FK_Egresos_BancoPago REFERENCES egr.Bancos (BancoId),
    ReferenciaPago  NVARCHAR(60)   NULL,                       -- N° transferencia / cheque

    -- Anulación
    MotivoAnulacion NVARCHAR(300)  NULL,

    -- Auditoría y concurrencia
    CreadoPor       INT            NOT NULL CONSTRAINT FK_Egresos_CreadoPor REFERENCES seg.Usuarios (UsuarioId),
    CreadoEn        DATETIME2(0)   NOT NULL CONSTRAINT DF_Egresos_CreadoEn DEFAULT (SYSDATETIME()),
    ActualizadoPor  INT            NULL CONSTRAINT FK_Egresos_ActualizadoPor REFERENCES seg.Usuarios (UsuarioId),
    ActualizadoEn   DATETIME2(0)   NULL,
    RowVer          ROWVERSION     NOT NULL,

    CONSTRAINT UQ_Egresos_Folio UNIQUE (Folio),
    CONSTRAINT CK_Egresos_Total CHECK (MontoTotal > 0),
    CONSTRAINT CK_Egresos_TipoCambio CHECK (TipoCambio > 0),
    CONSTRAINT CK_Egresos_NetoImpuesto CHECK (
        (MontoNeto IS NULL AND MontoImpuesto IS NULL)
        OR (MontoNeto IS NOT NULL AND MontoImpuesto IS NOT NULL AND MontoNeto + MontoImpuesto = MontoTotal)
    ),
    CONSTRAINT CK_Egresos_CLP CHECK (MonedaId <> 'CLP' OR TipoCambio = 1),
    CONSTRAINT CK_Egresos_Pago CHECK (EstadoId <> 'PAGADO' OR (FechaPago IS NOT NULL AND PagadoPor IS NOT NULL))
);
GO
CREATE INDEX IX_Egresos_Fecha        ON egr.Egresos (Fecha) INCLUDE (EstadoId, MontoCLP, CentroCostoId);
CREATE INDEX IX_Egresos_Estado       ON egr.Egresos (EstadoId, CentroCostoId) INCLUDE (NivelActual, MontoCLP);
CREATE INDEX IX_Egresos_Proveedor    ON egr.Egresos (ProveedorId, Fecha);
CREATE INDEX IX_Egresos_CentroCosto  ON egr.Egresos (CentroCostoId, Fecha);
CREATE INDEX IX_Egresos_CreadoPor    ON egr.Egresos (CreadoPor, EstadoId);
CREATE INDEX IX_Egresos_Solicitante  ON egr.Egresos (SolicitanteId, Fecha);
CREATE INDEX IX_Egresos_Destino      ON egr.Egresos (DestinoId, Fecha);
GO

/* Un registro por nivel requerido y por ciclo de envío. */
CREATE TABLE egr.EgresoAprobaciones (
    EgresoAprobacionId INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_EgresoAprobaciones PRIMARY KEY,
    EgresoId        INT            NOT NULL CONSTRAINT FK_EgrAprob_Egresos REFERENCES egr.Egresos (EgresoId),
    Ciclo           TINYINT        NOT NULL,
    Nivel           TINYINT        NOT NULL,
    MontoDesdeCLP   DECIMAL(18,0)  NOT NULL,                   -- copia de la regla aplicada
    Estado          VARCHAR(10)    NOT NULL CONSTRAINT DF_EgrAprob_Estado DEFAULT ('PENDIENTE'),
    ResueltoPor     INT            NULL CONSTRAINT FK_EgrAprob_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    FechaResolucion DATETIME2(0)   NULL,
    Comentario      NVARCHAR(500)  NULL,
    CONSTRAINT UQ_EgrAprob UNIQUE (EgresoId, Ciclo, Nivel),
    CONSTRAINT CK_EgrAprob_Estado CHECK (Estado IN ('PENDIENTE','APROBADO','DEVUELTO','RECHAZADO','OMITIDO'))
);
GO
CREATE INDEX IX_EgrAprob_Pendientes ON egr.EgresoAprobaciones (Estado, Nivel) INCLUDE (EgresoId, Ciclo);
GO

CREATE TABLE egr.EgresoHistorial (
    HistorialId     BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_EgresoHistorial PRIMARY KEY,
    EgresoId        INT            NOT NULL CONSTRAINT FK_EgrHist_Egresos REFERENCES egr.Egresos (EgresoId),
    EstadoAnterior  VARCHAR(10)    NULL,
    EstadoNuevo     VARCHAR(10)    NOT NULL,
    Nivel           TINYINT        NULL,
    UsuarioId       INT            NOT NULL CONSTRAINT FK_EgrHist_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    Fecha           DATETIME2(0)   NOT NULL CONSTRAINT DF_EgrHist_Fecha DEFAULT (SYSDATETIME()),
    Comentario      NVARCHAR(500)  NULL
);
GO
CREATE INDEX IX_EgrHist_Egreso ON egr.EgresoHistorial (EgresoId, Fecha);
GO

CREATE TABLE egr.EnviosCorreo (
    EnvioId         INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_EnviosCorreo PRIMARY KEY,
    EgresoId        INT            NOT NULL CONSTRAINT FK_Envios_Egresos REFERENCES egr.Egresos (EgresoId),
    Destinatarios   NVARCHAR(1000) NOT NULL,
    Asunto          NVARCHAR(200)  NOT NULL,
    Mensaje         NVARCHAR(MAX)  NULL,
    EnviadoPor      INT            NOT NULL CONSTRAINT FK_Envios_Usuarios REFERENCES seg.Usuarios (UsuarioId),
    Fecha           DATETIME2(0)   NOT NULL CONSTRAINT DF_Envios_Fecha DEFAULT (SYSDATETIME()),
    Exitoso         BIT            NOT NULL,
    Error           NVARCHAR(1000) NULL
);
GO
CREATE INDEX IX_Envios_Egreso ON egr.EnviosCorreo (EgresoId, Fecha);
GO

/* Parámetros propios de Egresos. */
CREATE TABLE egr.Parametros (
    Clave           VARCHAR(50)    NOT NULL CONSTRAINT PK_EgrParametros PRIMARY KEY,
    Valor           NVARCHAR(500)  NULL,
    Descripcion     NVARCHAR(200)  NULL
);
GO

/* ---------------------------------------------------------------------
   6. Esquema egr: datos iniciales
   --------------------------------------------------------------------- */
INSERT INTO egr.Monedas (MonedaId, Nombre, Simbolo, Decimales, EsBase) VALUES
    ('CLP', N'Peso chileno',      N'$',   0, 1),
    ('USD', N'Dólar',             N'US$', 2, 0),
    ('EUR', N'Euro',              N'€',   2, 0),
    ('CLF', N'Unidad de Fomento', N'UF',  4, 0);

INSERT INTO egr.EstadosEgreso (EstadoId, Nombre, Color, Orden, EsFinal) VALUES
    ('BORRADOR',  N'Borrador',  '#6c757d', 1, 0),
    ('PENDIENTE', N'Pendiente', '#ffc107', 2, 0),
    ('DEVUELTO',  N'Devuelto',  '#fd7e14', 3, 0),
    ('APROBADO',  N'Aprobado',  '#0d6efd', 4, 0),
    ('RECHAZADO', N'Rechazado', '#dc3545', 5, 1),
    ('PAGADO',    N'Pagado',    '#198754', 6, 1),
    ('ANULADO',   N'Anulado',   '#343a40', 7, 1);

INSERT INTO egr.TiposDocumento (Codigo, Nombre) VALUES
    ('FAC',  N'Factura electrónica'),
    ('FEX',  N'Factura exenta'),
    ('BOL',  N'Boleta'),
    ('BHE',  N'Boleta de honorarios'),
    ('NC',   N'Nota de crédito'),
    ('INV',  N'Invoice (extranjero)'),
    ('OTRO', N'Otro');

INSERT INTO egr.FormasPago (Codigo, Nombre, RequiereBanco, RequiereReferencia) VALUES
    ('TRF', N'Transferencia',      1, 1),
    ('CHQ', N'Cheque',             1, 1),
    ('EFE', N'Efectivo',           0, 0),
    ('TC',  N'Tarjeta de crédito', 0, 1);

-- Lista inicial editable desde el mantenedor de bancos (revisar contra los bancos con que opera la empresa).
INSERT INTO egr.Bancos (Codigo, Nombre) VALUES
    ('CHILE',  N'Banco de Chile'),
    ('ESTADO', N'BancoEstado'),
    ('SANT',   N'Banco Santander'),
    ('BCI',    N'Banco BCI'),
    ('ITAU',   N'Banco Itaú'),
    ('SCOTIA', N'Scotiabank'),
    ('BICE',   N'Banco BICE'),
    ('SECUR',  N'Banco Security'),
    ('FALAB',  N'Banco Falabella'),
    ('RIPLEY', N'Banco Ripley'),
    ('CONSOR', N'Banco Consorcio'),
    ('INTERN', N'Banco Internacional');

INSERT INTO egr.Parametros (Clave, Valor, Descripcion) VALUES
    ('TC_DIAS_TOLERANCIA', N'7', N'Días hacia atrás para buscar tipo de cambio');
GO

/* ---------------------------------------------------------------------
   7. Esquema egr: función de tipo de cambio y vistas
   --------------------------------------------------------------------- */
CREATE OR ALTER FUNCTION egr.fn_TipoCambio (@MonedaId CHAR(3), @Fecha DATE)
RETURNS DECIMAL(18,6)
AS
BEGIN
    IF @MonedaId = 'CLP' RETURN 1;

    DECLARE @Dias INT = ISNULL(TRY_CAST((SELECT Valor FROM egr.Parametros WHERE Clave = 'TC_DIAS_TOLERANCIA') AS INT), 7);
    DECLARE @Valor DECIMAL(18,6);

    SELECT TOP (1) @Valor = ValorCLP
    FROM egr.TiposCambio
    WHERE MonedaId = @MonedaId
      AND Fecha <= @Fecha
      AND Fecha >= DATEADD(DAY, -@Dias, @Fecha)
    ORDER BY Fecha DESC;

    RETURN @Valor;   -- NULL si no hay valor: el guardado debe fallar
END;
GO

/* Usuarios con perfil APROBADOR vigente en Egresos. Solo ellos pueden aparecer en la cadena. */
CREATE OR ALTER VIEW egr.vw_Aprobadores
AS
SELECT a.UsuarioId, a.Usuario AS Nombre, a.Email
FROM seg.vw_AccesosUsuario a
WHERE a.SistemaCodigo = 'EGRESOS' AND a.PerfilCodigo = 'APROBADOR';
GO

CREATE OR ALTER VIEW egr.vw_Egresos
AS
SELECT
    e.EgresoId, e.Folio, e.Fecha, e.EstadoId, es.Nombre AS Estado, es.Color AS EstadoColor,
    e.SolicitanteId, s.Rut AS SolicitanteRut, s.Nombre AS Solicitante,
    ca.Nombre AS SolicitanteCargo, s.DepartamentoId, de.Nombre AS Departamento,
    e.DestinoId, ds.Codigo AS DestinoCodigo, ds.Nombre AS Destino,
    e.ProveedorId, p.Rut AS ProveedorRut, p.RazonSocial AS Proveedor,
    e.CategoriaId, c.Codigo AS CategoriaCodigo, c.Nombre AS Categoria,
    e.CentroCostoId, cc.Codigo AS CentroCostoCodigo, cc.Nombre AS CentroCosto,
    e.CuentaContableId, cu.Codigo AS CuentaCodigo, cu.Nombre AS Cuenta,
    e.TipoDocumentoId, td.Nombre AS TipoDocumento, e.NumeroDocumento, e.FechaDocumento,
    e.FormaPagoId, fp.Nombre AS FormaPago,
    e.Descripcion,
    e.MonedaId, m.Simbolo AS MonedaSimbolo, m.Decimales AS MonedaDecimales,
    e.MontoNeto, e.MontoImpuesto, e.MontoTotal, e.TipoCambio, e.MontoCLP,
    e.Ciclo, e.NivelActual, e.FechaEnvio, e.FechaAprobacion,
    e.FechaPago, e.PagadoPor, up.Nombre AS PagadoPorNombre,
    e.BancoPagoId, bp.Nombre AS BancoPago, e.ReferenciaPago,
    e.MotivoAnulacion,
    e.CreadoPor, uc.Nombre AS CreadoPorNombre, e.CreadoEn,
    e.ActualizadoEn
FROM egr.Egresos e
JOIN egr.EstadosEgreso    es ON es.EstadoId = e.EstadoId
JOIN egr.Solicitantes     s  ON s.SolicitanteId = e.SolicitanteId
JOIN egr.Destinos         ds ON ds.DestinoId = e.DestinoId
JOIN egr.Proveedores      p  ON p.ProveedorId = e.ProveedorId
JOIN egr.Categorias       c  ON c.CategoriaId = e.CategoriaId
JOIN egr.CentrosCosto     cc ON cc.CentroCostoId = e.CentroCostoId
JOIN egr.CuentasContables cu ON cu.CuentaContableId = e.CuentaContableId
JOIN egr.Monedas          m  ON m.MonedaId = e.MonedaId
JOIN seg.Usuarios         uc ON uc.UsuarioId = e.CreadoPor
LEFT JOIN egr.TiposDocumento td ON td.TipoDocumentoId = e.TipoDocumentoId
LEFT JOIN egr.FormasPago     fp ON fp.FormaPagoId = e.FormaPagoId
LEFT JOIN seg.Usuarios       up ON up.UsuarioId = e.PagadoPor
LEFT JOIN egr.Bancos         bp ON bp.BancoId = e.BancoPagoId
LEFT JOIN seg.Cargos         ca ON ca.CargoId = s.CargoId
LEFT JOIN seg.Departamentos  de ON de.DepartamentoId = s.DepartamentoId;
GO

/* Bandeja del aprobador: egresos PENDIENTE cuyo nivel actual le corresponde. */
CREATE OR ALTER VIEW egr.vw_PendientesAprobacion
AS
SELECT
    e.EgresoId, e.Folio, e.Fecha, e.CentroCostoId, e.MontoCLP, e.NivelActual, e.Ciclo,
    e.CreadoPor, e.FechaEnvio, r.AprobadorId,
    DATEDIFF(DAY, e.FechaEnvio, SYSDATETIME()) AS DiasEspera
FROM egr.Egresos e
JOIN egr.ReglasAprobacion r
  ON r.Activo = 1
 AND r.Nivel = e.NivelActual
 AND (
        r.CentroCostoId = e.CentroCostoId
     OR (r.CentroCostoId IS NULL AND NOT EXISTS (
            SELECT 1 FROM egr.ReglasAprobacion r2
            WHERE r2.Activo = 1 AND r2.CentroCostoId = e.CentroCostoId))
     )
JOIN egr.vw_Aprobadores ap ON ap.UsuarioId = r.AprobadorId   -- perfil APROBADOR vigente
WHERE e.EstadoId = 'PENDIENTE'
  AND r.AprobadorId <> e.CreadoPor;    -- nadie aprueba lo que creó
GO

/* ---------------------------------------------------------------------
   8. Esquema egr: procedimientos del flujo
   Convención: errores de negocio con THROW 50001-50099; PHP los
   traduce a HTTP 400/409 con el mensaje tal cual.
   --------------------------------------------------------------------- */

/* Envía un egreso BORRADOR o DEVUELTO a aprobación. */
CREATE OR ALTER PROCEDURE egr.sp_Egreso_Enviar
    @EgresoId  INT,
    @UsuarioId INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    BEGIN TRAN;

    DECLARE @Estado VARCHAR(10), @CC INT, @Moneda CHAR(3), @Fecha DATE,
            @Total DECIMAL(18,4), @TC DECIMAL(18,6), @MontoCLP DECIMAL(18,0), @Ciclo TINYINT;

    SELECT @Estado = EstadoId, @CC = CentroCostoId, @Moneda = MonedaId, @Fecha = Fecha,
           @Total = MontoTotal, @Ciclo = Ciclo
    FROM egr.Egresos WITH (UPDLOCK, ROWLOCK)
    WHERE EgresoId = @EgresoId;

    IF @Estado IS NULL
        THROW 50001, N'El egreso no existe.', 1;
    IF @Estado NOT IN ('BORRADOR', 'DEVUELTO')
        THROW 50002, N'Solo se puede enviar un egreso en Borrador o Devuelto.', 1;

    -- Recalcular tipo de cambio y monto CLP con la tabla vigente
    SET @TC = egr.fn_TipoCambio(@Moneda, @Fecha);
    IF @TC IS NULL
        THROW 50003, N'No hay tipo de cambio cargado para la moneda y fecha del egreso.', 1;
    SET @MontoCLP = ROUND(@Total * @TC, 0);

    -- ¿El centro de costo tiene reglas propias?
    DECLARE @UsaCC BIT = CASE WHEN EXISTS (
        SELECT 1 FROM egr.ReglasAprobacion WHERE Activo = 1 AND CentroCostoId = @CC) THEN 1 ELSE 0 END;

    DECLARE @Niveles TABLE (Nivel TINYINT PRIMARY KEY, MontoDesdeCLP DECIMAL(18,0));

    INSERT INTO @Niveles (Nivel, MontoDesdeCLP)
    SELECT r.Nivel, MIN(r.MontoDesdeCLP)
    FROM egr.ReglasAprobacion r
    JOIN egr.vw_Aprobadores u ON u.UsuarioId = r.AprobadorId
    WHERE r.Activo = 1
      AND r.MontoDesdeCLP <= @MontoCLP
      AND ((@UsaCC = 1 AND r.CentroCostoId = @CC) OR (@UsaCC = 0 AND r.CentroCostoId IS NULL))
    GROUP BY r.Nivel;

    IF NOT EXISTS (SELECT 1 FROM @Niveles)
        THROW 50004, N'No hay reglas de aprobación que apliquen a este centro de costo y monto.', 1;

    SET @Ciclo = @Ciclo + 1;

    INSERT INTO egr.EgresoAprobaciones (EgresoId, Ciclo, Nivel, MontoDesdeCLP)
    SELECT @EgresoId, @Ciclo, Nivel, MontoDesdeCLP FROM @Niveles;

    UPDATE egr.Egresos
    SET EstadoId = 'PENDIENTE',
        TipoCambio = @TC,
        MontoCLP = @MontoCLP,
        Ciclo = @Ciclo,
        NivelActual = (SELECT MIN(Nivel) FROM @Niveles),
        FechaEnvio = SYSDATETIME(),
        ActualizadoPor = @UsuarioId,
        ActualizadoEn = SYSDATETIME()
    WHERE EgresoId = @EgresoId;

    INSERT INTO egr.EgresoHistorial (EgresoId, EstadoAnterior, EstadoNuevo, UsuarioId, Comentario)
    VALUES (@EgresoId, @Estado, 'PENDIENTE', @UsuarioId, CONCAT(N'Envío ciclo ', @Ciclo));

    COMMIT;

    SELECT EgresoId, EstadoId, NivelActual, MontoCLP FROM egr.Egresos WHERE EgresoId = @EgresoId;
END;
GO

/* Aprueba, devuelve o rechaza el nivel actual. @Accion: APROBAR | DEVOLVER | RECHAZAR */
CREATE OR ALTER PROCEDURE egr.sp_Egreso_Resolver
    @EgresoId   INT,
    @UsuarioId  INT,
    @Accion     VARCHAR(10),
    @Comentario NVARCHAR(500) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @Accion NOT IN ('APROBAR', 'DEVOLVER', 'RECHAZAR')
        THROW 50010, N'Acción no válida.', 1;
    IF @Accion IN ('DEVOLVER', 'RECHAZAR') AND NULLIF(LTRIM(RTRIM(@Comentario)), N'') IS NULL
        THROW 50011, N'Debe indicar un comentario al devolver o rechazar.', 1;

    BEGIN TRAN;

    DECLARE @Estado VARCHAR(10), @Nivel TINYINT, @Ciclo TINYINT, @CreadoPor INT;

    SELECT @Estado = EstadoId, @Nivel = NivelActual, @Ciclo = Ciclo, @CreadoPor = CreadoPor
    FROM egr.Egresos WITH (UPDLOCK, ROWLOCK)
    WHERE EgresoId = @EgresoId;

    IF @Estado IS NULL
        THROW 50001, N'El egreso no existe.', 1;
    IF @Estado <> 'PENDIENTE'
        THROW 50012, N'El egreso ya no está pendiente de aprobación.', 1;
    IF @CreadoPor = @UsuarioId
        THROW 50013, N'No puede aprobar un egreso que usted creó.', 1;
    IF NOT EXISTS (SELECT 1 FROM egr.vw_PendientesAprobacion
                   WHERE EgresoId = @EgresoId AND AprobadorId = @UsuarioId)
        THROW 50014, N'No es aprobador del nivel actual de este egreso.', 1;

    DECLARE @EstadoNivel VARCHAR(10) =
        CASE @Accion WHEN 'APROBAR' THEN 'APROBADO' WHEN 'DEVOLVER' THEN 'DEVUELTO' ELSE 'RECHAZADO' END;

    UPDATE egr.EgresoAprobaciones
    SET Estado = @EstadoNivel, ResueltoPor = @UsuarioId, FechaResolucion = SYSDATETIME(), Comentario = @Comentario
    WHERE EgresoId = @EgresoId AND Ciclo = @Ciclo AND Nivel = @Nivel AND Estado = 'PENDIENTE';

    DECLARE @NuevoEstado VARCHAR(10), @SiguienteNivel TINYINT;

    IF @Accion = 'APROBAR'
    BEGIN
        SELECT @SiguienteNivel = MIN(Nivel)
        FROM egr.EgresoAprobaciones
        WHERE EgresoId = @EgresoId AND Ciclo = @Ciclo AND Estado = 'PENDIENTE';

        SET @NuevoEstado = CASE WHEN @SiguienteNivel IS NULL THEN 'APROBADO' ELSE 'PENDIENTE' END;
    END
    ELSE
    BEGIN
        -- Los niveles restantes del ciclo ya no se resolverán
        UPDATE egr.EgresoAprobaciones SET Estado = 'OMITIDO'
        WHERE EgresoId = @EgresoId AND Ciclo = @Ciclo AND Estado = 'PENDIENTE';

        SET @NuevoEstado = @EstadoNivel;   -- DEVUELTO o RECHAZADO
        SET @SiguienteNivel = NULL;
    END;

    UPDATE egr.Egresos
    SET EstadoId = @NuevoEstado,
        NivelActual = @SiguienteNivel,
        FechaAprobacion = CASE WHEN @NuevoEstado = 'APROBADO' THEN SYSDATETIME() ELSE FechaAprobacion END,
        ActualizadoPor = @UsuarioId,
        ActualizadoEn = SYSDATETIME()
    WHERE EgresoId = @EgresoId;

    INSERT INTO egr.EgresoHistorial (EgresoId, EstadoAnterior, EstadoNuevo, Nivel, UsuarioId, Comentario)
    VALUES (@EgresoId, @Estado, @NuevoEstado, @Nivel, @UsuarioId, @Comentario);

    COMMIT;

    SELECT EgresoId, EstadoId, NivelActual FROM egr.Egresos WHERE EgresoId = @EgresoId;
END;
GO

/* Tesorería registra el pago de un egreso APROBADO. */
CREATE OR ALTER PROCEDURE egr.sp_Egreso_Pagar
    @EgresoId       INT,
    @UsuarioId      INT,
    @FechaPago      DATE,
    @FormaPagoId    SMALLINT,
    @BancoPagoId    SMALLINT = NULL,
    @ReferenciaPago NVARCHAR(60) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    BEGIN TRAN;

    DECLARE @Estado VARCHAR(10);
    SELECT @Estado = EstadoId FROM egr.Egresos WITH (UPDLOCK, ROWLOCK) WHERE EgresoId = @EgresoId;

    IF @Estado IS NULL
        THROW 50001, N'El egreso no existe.', 1;
    IF @Estado <> 'APROBADO'
        THROW 50020, N'Solo se puede pagar un egreso Aprobado.', 1;
    DECLARE @ReqBanco BIT, @ReqRef BIT;
    SELECT @ReqBanco = RequiereBanco, @ReqRef = RequiereReferencia
    FROM egr.FormasPago WHERE FormaPagoId = @FormaPagoId AND Activo = 1;

    IF @ReqBanco IS NULL
        THROW 50021, N'La forma de pago no es válida.', 1;
    IF @ReqBanco = 1 AND NOT EXISTS (SELECT 1 FROM egr.Bancos WHERE BancoId = @BancoPagoId AND Activo = 1)
        THROW 50022, N'Esta forma de pago exige indicar el banco.', 1;
    IF @ReqRef = 1 AND NULLIF(LTRIM(RTRIM(@ReferenciaPago)), N'') IS NULL
        THROW 50023, N'Esta forma de pago exige el número de referencia.', 1;

    UPDATE egr.Egresos
    SET EstadoId = 'PAGADO', FechaPago = @FechaPago, FormaPagoId = @FormaPagoId,
        BancoPagoId = @BancoPagoId, ReferenciaPago = @ReferenciaPago, PagadoPor = @UsuarioId,
        ActualizadoPor = @UsuarioId, ActualizadoEn = SYSDATETIME()
    WHERE EgresoId = @EgresoId;

    INSERT INTO egr.EgresoHistorial (EgresoId, EstadoAnterior, EstadoNuevo, UsuarioId, Comentario)
    VALUES (@EgresoId, @Estado, 'PAGADO', @UsuarioId, @ReferenciaPago);

    COMMIT;
END;
GO

/* Anula un egreso BORRADOR, DEVUELTO o APROBADO (sin pagar).
   El permiso por rol (Admin, o Digitador sobre sus borradores) lo valida PHP. */
CREATE OR ALTER PROCEDURE egr.sp_Egreso_Anular
    @EgresoId  INT,
    @UsuarioId INT,
    @Motivo    NVARCHAR(300)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NULLIF(LTRIM(RTRIM(@Motivo)), N'') IS NULL
        THROW 50030, N'Debe indicar el motivo de la anulación.', 1;

    BEGIN TRAN;

    DECLARE @Estado VARCHAR(10);
    SELECT @Estado = EstadoId FROM egr.Egresos WITH (UPDLOCK, ROWLOCK) WHERE EgresoId = @EgresoId;

    IF @Estado IS NULL
        THROW 50001, N'El egreso no existe.', 1;
    IF @Estado NOT IN ('BORRADOR', 'DEVUELTO', 'APROBADO')
        THROW 50031, N'Este egreso no se puede anular en su estado actual.', 1;

    UPDATE egr.Egresos
    SET EstadoId = 'ANULADO', NivelActual = NULL, MotivoAnulacion = @Motivo,
        ActualizadoPor = @UsuarioId, ActualizadoEn = SYSDATETIME()
    WHERE EgresoId = @EgresoId;

    INSERT INTO egr.EgresoHistorial (EgresoId, EstadoAnterior, EstadoNuevo, UsuarioId, Comentario)
    VALUES (@EgresoId, @Estado, 'ANULADO', @UsuarioId, @Motivo);

    COMMIT;
END;
GO

/* Simulador para el mantenedor de reglas: qué niveles y aprobadores pediría un monto. */
CREATE OR ALTER PROCEDURE egr.sp_Reglas_Simular
    @CentroCostoId INT,
    @MontoCLP      DECIMAL(18,0)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @UsaCC BIT = CASE WHEN EXISTS (
        SELECT 1 FROM egr.ReglasAprobacion WHERE Activo = 1 AND CentroCostoId = @CentroCostoId) THEN 1 ELSE 0 END;

    SELECT r.Nivel, r.MontoDesdeCLP, u.UsuarioId, u.Nombre AS Aprobador,
           CASE WHEN @UsaCC = 1 THEN N'Centro de costo' ELSE N'Regla general' END AS Origen
    FROM egr.ReglasAprobacion r
    JOIN egr.vw_Aprobadores u ON u.UsuarioId = r.AprobadorId
    WHERE r.Activo = 1
      AND r.MontoDesdeCLP <= @MontoCLP
      AND ((@UsaCC = 1 AND r.CentroCostoId = @CentroCostoId) OR (@UsaCC = 0 AND r.CentroCostoId IS NULL))
    ORDER BY r.Nivel, u.Nombre;
END;
GO

/* =====================================================================
   Fin del script.
   Siguiente paso: php sql/crear_admin.php
   ===================================================================== */
