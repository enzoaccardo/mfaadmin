<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    private Google2FA $google2fa;
    private string $issuer;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->issuer    = (string) Configuration::get('PS_SHOP_NAME');
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUri(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($this->issuer, $email, $secret);
    }

    public function getQrCodeSvg(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($uri);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Verifica un codice TOTP al login impedendone il replay: un codice gia'
     * accettato (o piu' vecchio dell'ultimo accettato) viene sempre rifiutato,
     * anche se ancora entro la finestra di validita' RFC 6238.
     */
    public function verifyLoginCode(EmployeeMfa $mfa, string $code): bool
    {
        $secret = $mfa->getPlainSecret();
        if (!$secret) {
            return false;
        }

        $result = $this->google2fa->verifyKeyNewer($secret, $code, (int) $mfa->last_totp_counter);

        if ($result === false) {
            return false;
        }

        $mfa->last_totp_counter = (int) $result;
        $mfa->date_upd = date('Y-m-d H:i:s');
        $mfa->update();

        return true;
    }

    /**
     * Genera 10 codici di recupero one-time per un employee.
     * I codici precedenti vengono eliminati.
     *
     * @return string[] Codici in chiaro da mostrare una sola volta
     */
    public function generateRecoveryCodes(int $employeeId): array
    {
        EmployeeRecoveryCode::deleteAllForEmployee($employeeId);

        $codes = [];
        $now   = date('Y-m-d H:i:s');

        for ($i = 0; $i < 10; $i++) {
            $plain = strtoupper(
                bin2hex(random_bytes(2)) . '-' .
                bin2hex(random_bytes(2)) . '-' .
                bin2hex(random_bytes(2))
            );

            $codes[] = $plain;

            // Raw insert: ObjectModel serializza le date null come '0000-00-00 00:00:00',
            // usare SQL diretto garantisce used_at = NULL.
            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'employee_recovery_code`
                 (`id_employee`, `code_hash`, `used_at`, `date_add`)
                 VALUES (' . (int) $employeeId . ', \'' . pSQL(password_hash($plain, PASSWORD_BCRYPT)) . '\', NULL, \'' . pSQL($now) . '\')'
            );
        }

        return $codes;
    }

    public function verifyRecoveryCode(int $employeeId, string $code): bool
    {
        $candidates = EmployeeRecoveryCode::getAvailableForEmployee($employeeId);
        $code = strtoupper(trim($code));

        $verified   = false;
        $verifiedId = null;

        // Itera TUTTI i candidati senza uscire in anticipo per evitare attacchi timing
        foreach ($candidates as $row) {
            if (password_verify($code, $row['code_hash'])) {
                $verified   = true;
                $verifiedId = (int) $row['id_recovery_code'];
            }
        }

        if ($verified && $verifiedId) {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'employee_recovery_code`
                 SET `used_at` = NOW()
                 WHERE `id_recovery_code` = ' . $verifiedId
            );
        }

        return $verified;
    }

    public function countAvailableRecoveryCodes(int $employeeId): int
    {
        return EmployeeRecoveryCode::countAvailable($employeeId);
    }
}
