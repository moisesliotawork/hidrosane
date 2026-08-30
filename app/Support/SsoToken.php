<?php

namespace App\Support;

/**
 * Token firmado para la entrada entre aplicaciones.
 *
 * Formato:  base64url(json) . "." . base64url(hmac_sha256(base64url(json), secreto))
 * Carga:    {"perfil":"superadmin","exp":<unix>,"nonce":"<32 hex>"}
 *
 * La firma se calcula sobre la carga YA codificada, no sobre el JSON: así las
 * dos aplicaciones no dependen de generar exactamente el mismo texto JSON
 * (orden de claves, escapado de barras) para que la firma cuadre.
 *
 * ESTE FICHERO ESTÁ DUPLICADO EN OHANA. Si cambia el formato, cambia en los dos
 * o los botones dejan de funcionar.
 */
final class SsoToken
{
    public static function firmar(array $carga, string $secreto): string
    {
        $json = self::b64(json_encode($carga, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $json.'.'.self::b64(hash_hmac('sha256', $json, $secreto, true));
    }

    /** Devuelve la carga si el token es válido y no ha caducado; null si no. */
    public static function verificar(string $token, string $secreto): ?array
    {
        if ($secreto === '' || substr_count($token, '.') !== 1) {
            return null;
        }

        [$json, $firma] = explode('.', $token, 2);

        $esperada = self::b64(hash_hmac('sha256', $json, $secreto, true));

        // hash_equals: comparación en tiempo constante, para no filtrar por
        // cuánto tarda en fallar cuántos bytes iniciales acertó quien pruebe.
        if (! hash_equals($esperada, $firma)) {
            return null;
        }

        $carga = json_decode((string) self::deB64($json), true);

        if (! is_array($carga)) {
            return null;
        }

        foreach (['perfil', 'exp', 'nonce'] as $clave) {
            if (! isset($carga[$clave])) {
                return null;
            }
        }

        if ((int) $carga['exp'] < time()) {
            return null;
        }

        return $carga;
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function b64(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }

    private static function deB64(string $valor): string|false
    {
        return base64_decode(strtr($valor, '-_', '+/'), true);
    }
}
