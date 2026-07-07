<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cifra/decifra i secret TOTP a riposo con AES-256-GCM.
 * La chiave e' derivata (HKDF-SHA256) da _COOKIE_KEY_, il salt segreto
 * univoco per installazione gia' generato da PrestaShop: evita di introdurre
 * un nuovo segreto da gestire (e da rischiare di hardcodare o versionare).
 */
class MfaCrypto
{
    private const CIPHER = 'aes-256-gcm';
    private const INFO   = 'mfaadmin-totp-secret-v1';
    private const IV_LENGTH  = 12;
    private const TAG_LENGTH = 16;

    public static function encrypt(string $plaintext): string
    {
        $key = self::deriveKey();
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Impossibile cifrare il secret MFA.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::deriveKey(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }

    private static function deriveKey(): string
    {
        return hash_hkdf('sha256', _COOKIE_KEY_, 32, self::INFO);
    }
}