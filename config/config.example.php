<?php
/**
 * Configuración de la intranet.
 * Copie este archivo como config.php y complete los datos.
 * config.php NO se sube al repositorio (tiene contraseñas y secretos).
 */
return [
    'app' => [
        'entorno'       => 'desarrollo',          // 'desarrollo' muestra el detalle de los errores; 'produccion' no
        'soporte_email' => '',                    // correo de soporte TI para el acceso rápido de la portada
    ],

    'db' => [
        'servidor' => 'localhost',                 // instancia de SQL Server (ej. 'localhost' o 'SERVIDOR\\INSTANCIA')
        // TrustServerCertificate=1 solo en desarrollo, si SQL Server no tiene un certificado válido.
        'opciones' => 'TrustServerCertificate=1',
        'user'     => 'intranet_app',              // el mismo usuario tiene acceso a todas las bases
        'pass'     => '',
        // Una base por sistema. La clave es el código del sistema; el valor, el nombre de la base.
        'bases'    => [
            'INTRANET' => 'Intranet',
            'EGRESOS'  => 'Egresos',
        ],
    ],

    // Rutas publicadas por Apache (ver apache/intranet.conf)
    'rutas' => [
        'INTRANET' => '/intranet',
        'EGRESOS'  => '/egresos',
        'ASSETS'   => '/intranet-assets',
    ],

    'sesion' => [
        'nombre'  => 'INTRANET_SID',
        'minutos' => 30,          // cierre por inactividad
        'secure'  => null,        // null = automático (Secure cuando se entra por HTTPS); true en producción
    ],

    // Credenciales OAuth de Google Cloud (tipo "Aplicación web").
    // Mientras estén vacías, el botón de Google aparece deshabilitado y se usa la cuenta LOCAL.
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
        'redirect_uri'  => 'https://intranet.nissui.cl/intranet/auth/callback.php',
        // Certificados raíz para cURL. XAMPP trae uno; ajuste la ruta si no existe.
        'ca_bundle'     => 'C:/xampp/apache/bin/curl-ca-bundle.crt',
    ],
];
