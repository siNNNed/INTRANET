<?php
declare(strict_types=1);

namespace Intranet;

/**
 * Inicio de sesión con Google Workspace (OpenID Connect, flujo de código con PKCE).
 *
 * El ID token se obtiene directamente del endpoint de tokens de Google por HTTPS
 * (con verificación del certificado), así que no hace falta verificar su firma:
 * se validan emisor, audiencia, vencimiento, nonce, correo verificado y dominio.
 */
final class GoogleOidc
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const EMISORES  = ['https://accounts.google.com', 'accounts.google.com'];
    private const VIGENCIA_ESTADO = 600;   // segundos para volver desde Google

    public static function configurado(): bool
    {
        return Config::get('google.client_id') && Config::get('google.client_secret') && Config::get('google.redirect_uri');
    }

    /** Arma la URL de Google y guarda state, nonce y verificador PKCE en la sesión. */
    public static function urlAutorizacion(string $destino): string
    {
        $verificador = self::b64url(random_bytes(48));
        $_SESSION['oidc'] = [
            'state'    => bin2hex(random_bytes(24)),
            'nonce'    => bin2hex(random_bytes(24)),
            'verifier' => $verificador,
            'destino'  => $destino,
            'creado'   => time(),
        ];

        $parametros = [
            'client_id'             => Config::get('google.client_id'),
            'redirect_uri'          => Config::get('google.redirect_uri'),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $_SESSION['oidc']['state'],
            'nonce'                 => $_SESSION['oidc']['nonce'],
            'code_challenge'        => self::b64url(hash('sha256', $verificador, true)),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ];
        $dominios = Parametros::dominiosGoogle();
        if (count($dominios) === 1) {
            $parametros['hd'] = $dominios[0];   // solo sugerencia; la validación real es el claim hd
        }
        return self::AUTH_URL . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Procesa el regreso desde Google. Devuelve ['email','sub','nombre','destino'].
     * Lanza LoginError con el código del problema.
     */
    public static function procesarRetorno(array $get): array
    {
        $o = $_SESSION['oidc'] ?? null;
        unset($_SESSION['oidc']);

        if (!is_array($o) || !is_string($get['state'] ?? null) || !hash_equals($o['state'], $get['state'])) {
            throw new LoginError('estado', 'state no coincide');
        }
        if (time() - (int) $o['creado'] > self::VIGENCIA_ESTADO) {
            throw new LoginError('estado', 'state vencido');
        }
        if (isset($get['error'])) {
            throw new LoginError('cancelado', 'Google devolvió error: ' . substr((string) $get['error'], 0, 50));
        }
        $codigo = $get['code'] ?? null;
        if (!is_string($codigo) || $codigo === '') {
            throw new LoginError('google', 'sin code');
        }

        $respuesta = self::pedirToken($codigo, (string) $o['verifier']);
        $idToken = $respuesta['id_token'] ?? null;
        if (!is_string($idToken)) {
            throw new LoginError('google', 'respuesta sin id_token');
        }

        $claims = self::leerClaims($idToken);
        $ahora = time();
        $clientId = (string) Config::get('google.client_id');
        $aud = $claims['aud'] ?? null;

        if (!in_array($claims['iss'] ?? '', self::EMISORES, true)) {
            throw new LoginError('google', 'iss inválido');
        }
        if (!(is_string($aud) ? $aud === $clientId : (is_array($aud) && in_array($clientId, $aud, true)))) {
            throw new LoginError('google', 'aud inválido');
        }
        if ((int) ($claims['exp'] ?? 0) < $ahora - 60 || (int) ($claims['iat'] ?? 0) > $ahora + 300) {
            throw new LoginError('google', 'token vencido o con fecha futura');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($o['nonce'], $claims['nonce'])) {
            throw new LoginError('estado', 'nonce no coincide');
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $verificado = $claims['email_verified'] ?? false;
        if ($email === '' || !($verificado === true || $verificado === 'true')) {
            throw new LoginError('google', 'correo no verificado');
        }

        $dominios = Parametros::dominiosGoogle();
        $hd = strtolower((string) ($claims['hd'] ?? ''));
        if ($hd === '' || !in_array($hd, $dominios, true) || !Validator::emailDeDominio($email, [$hd])) {
            throw new LoginError('dominio', 'dominio no permitido: ' . $hd, $email);
        }

        $sub = (string) ($claims['sub'] ?? '');
        if ($sub === '' || strlen($sub) > 64) {
            throw new LoginError('google', 'sub inválido');
        }

        return [
            'email'   => $email,
            'sub'     => $sub,
            'nombre'  => (string) ($claims['name'] ?? ''),
            'destino' => (string) ($o['destino'] ?? ''),
        ];
    }

    private static function pedirToken(string $codigo, string $verificador): array
    {
        $ch = curl_init(self::TOKEN_URL);
        $opciones = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $codigo,
                'client_id'     => Config::get('google.client_id'),
                'client_secret' => Config::get('google.client_secret'),
                'redirect_uri'  => Config::get('google.redirect_uri'),
                'grant_type'    => 'authorization_code',
                'code_verifier' => $verificador,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ];
        $ca = Config::get('google.ca_bundle');
        if (is_string($ca) && $ca !== '') {
            $opciones[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opciones);

        $cuerpo = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errorCurl = curl_error($ch);

        if ($cuerpo === false) {
            throw new LoginError('google', 'cURL: ' . $errorCurl);
        }
        $json = json_decode((string) $cuerpo, true);
        if ($http !== 200 || !is_array($json)) {
            throw new LoginError('google', 'token HTTP ' . $http . ': ' . substr((string) $cuerpo, 0, 300));
        }
        return $json;
    }

    private static function leerClaims(string $jwt): array
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) {
            throw new LoginError('google', 'id_token mal formado');
        }
        $claims = json_decode(self::b64urlDecode($partes[1]), true);
        if (!is_array($claims)) {
            throw new LoginError('google', 'id_token ilegible');
        }
        return $claims;
    }

    private static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $texto): string
    {
        $texto = strtr($texto, '-_', '+/');
        $relleno = strlen($texto) % 4;
        if ($relleno) {
            $texto .= str_repeat('=', 4 - $relleno);
        }
        return (string) base64_decode($texto, true);
    }
}
