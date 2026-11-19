<?php
/**
 * Crea la cuenta LOCAL de emergencia (con contraseña) con perfil de Administrador
 * en la administración de la intranet y en Egresos. Sirve para entrar aunque
 * falle el inicio de sesión con Google. Las demás personas se crean desde el
 * portal como cuentas GOOGLE, sin contraseña.
 *
 * Uso (línea de comandos):  php sql/crear_admin.php
 * Pide nombre, correo y contraseña; guarda la contraseña con password_hash().
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la línea de comandos.');
}

$config = require __DIR__ . '/../config/config.php';

$db  = $config['db'];
$dsn = 'sqlsrv:Server=' . ($db['servidor'] ?? 'localhost') . ';Database=' . $db['bases']['INTRANET']
     . (!empty($db['opciones']) ? ';' . $db['opciones'] : '');

$pdo = new PDO(
    $dsn,
    $config['db']['user'],
    $config['db']['pass'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ]
);

function preguntar(string $texto): string {
    echo $texto;
    return trim((string) fgets(STDIN));
}

$nombre = preguntar('Nombre: ');
$email  = preguntar('Correo: ');
$pass   = preguntar('Contraseña (mín. 10, letras y números): ');

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Nombre o correo inválido.\n");
}
if (strlen($pass) < 10 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
    exit("La contraseña no cumple la política.\n");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO seg.Usuarios (Nombre, Email, MetodoAcceso, PasswordHash, DebeCambiarPassword)
         OUTPUT INSERTED.UsuarioId
         VALUES (:nombre, :email, \'LOCAL\', :hash, 0)'
    );
    $stmt->execute([
        ':nombre' => $nombre,
        ':email'  => $email,
        ':hash'   => password_hash($pass, PASSWORD_DEFAULT),
    ]);
    $usuarioId = (int) $stmt->fetchColumn();

    // Perfil ADMIN en cada sistema indicado
    $asignar = $pdo->prepare(
        'INSERT INTO seg.UsuarioPerfiles (UsuarioId, SistemaId, PerfilId)
         SELECT :usuario, s.SistemaId, p.PerfilId
         FROM seg.Sistemas s
         JOIN seg.Perfiles p ON p.SistemaId = s.SistemaId AND p.Codigo = :perfil
         WHERE s.Codigo = :sistema'
    );
    foreach (['INTRANET', 'EGRESOS'] as $sistema) {
        $asignar->execute([':usuario' => $usuarioId, ':sistema' => $sistema, ':perfil' => 'ADMIN']);
        if ($asignar->rowCount() !== 1) {
            throw new RuntimeException("No se encontró el perfil ADMIN del sistema $sistema.");
        }
    }

    $pdo->commit();
    echo "Administrador creado (UsuarioId $usuarioId) con acceso a INTRANET y EGRESOS.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('Error: ' . $e->getMessage() . "\n");
}
