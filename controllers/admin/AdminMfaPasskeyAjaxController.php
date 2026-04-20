<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Endpoint JSON per tutte le operazioni WebAuthn (passkey).
 * Usato sia dalla pagina di verifica MFA che dal profilo.
 *
 * Azioni disponibili (parametro GET/POST 'action'):
 *   - authOptions    → genera opzioni autenticazione (verifica MFA)
 *   - authVerify     → verifica assertion autenticazione
 *   - registerOptions → genera opzioni registrazione (profilo)
 *   - register       → verifica attestazione e salva passkey
 *   - delete         → elimina passkey
 */
class AdminMfaPasskeyAjaxController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap        = true;
        $this->display_header   = false;
        $this->display_footer   = false;
    }

    public function display(): void
    {
        // Assicura che la sessione PHP nativa sia attiva (Symfony la avvia in lazy solo per le pagine)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Pulisce i buffer di output per evitare che notice/warning PHP contaminino il JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $action = (string) Tools::getValue('action', '');

        header('Content-Type: application/json; charset=utf-8');

        try {
            $payload = match ($action) {
                'authOptions'     => $this->actionAuthOptions(),
                'authVerify'      => $this->actionAuthVerify(),
                'registerOptions' => $this->actionRegisterOptions(),
                'register'        => $this->actionRegister(),
                'delete'          => $this->actionDelete(),
                'setupVerify'     => $this->actionSetupVerify(),
                default           => throw new InvalidArgumentException('Azione non valida.'),
            };

            echo json_encode(['success' => true] + $payload, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        }

        exit;
    }

    // -------------------------------------------------------------------------
    // Autenticazione MFA via passkey
    // -------------------------------------------------------------------------

    private function actionAuthOptions(): array
    {
        $employeeId = $this->requireEmployee();

        $options = (new PasskeyService())->getAuthenticationOptions($employeeId);

        return $options;
    }

    private function actionAuthVerify(): array
    {
        $employeeId   = $this->requireEmployee();
        $body         = $this->getJsonBody();
        $credentialJson = json_encode($body, JSON_THROW_ON_ERROR);

        if (!(new PasskeyService())->verifyAuthentication($employeeId, $credentialJson)) {
            throw new RuntimeException('Verifica passkey fallita. Riprova.');
        }

        Mfaadmin::setMfaVerified($employeeId);

        return ['redirect' => $this->context->link->getAdminLink('AdminDashboard')];
    }

    // -------------------------------------------------------------------------
    // Registrazione passkey (dal profilo)
    // -------------------------------------------------------------------------

    private function actionRegisterOptions(): array
    {
        $employeeId = $this->requireEmployeeVerified();
        $employee   = $this->context->employee;

        return (new PasskeyService())->getRegistrationOptions(
            $employeeId,
            (string) $employee->email,
            $employee->firstname . ' ' . $employee->lastname
        );
    }

    private function actionRegister(): array
    {
        $employeeId = $this->requireEmployeeVerified();
        $body  = $this->getJsonBody();
        $label = trim((string) ($body['label'] ?? ''));

        // Rimuove tag HTML e tronca a 64 caratteri
        $label = mb_substr(strip_tags($label), 0, 64);
        if ($label === '') {
            $label = 'Passkey';
        }

        unset($body['label']);
        $credentialJson = json_encode($body, JSON_THROW_ON_ERROR);

        $passkey = (new PasskeyService())->verifyRegistration($employeeId, $label, $credentialJson);

        return [
            'passkey' => [
                'id'           => (int) $passkey->id,
                'device_label' => $passkey->device_label,
                'date_add'     => $passkey->date_add,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Eliminazione passkey
    // -------------------------------------------------------------------------

    private function actionDelete(): array
    {
        $employeeId = $this->requireEmployeeVerified();
        $passkeyId  = (int) Tools::getValue('passkeyId', 0);

        if (!$passkeyId) {
            throw new InvalidArgumentException('ID passkey mancante.');
        }

        if (!(new PasskeyService())->deletePasskey($passkeyId, $employeeId)) {
            throw new RuntimeException('Passkey non trovata o non autorizzato.');
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // Setup TOTP via modal (inline nel backoffice)
    // -------------------------------------------------------------------------

    private function actionSetupVerify(): array
    {
        $employeeId = $this->requireEmployee();

        $secret = $_SESSION['_mfaadmin_temp_secret'] ?? '';
        $code   = trim((string) Tools::getValue('code', ''));

        if (!$secret || !(new MfaService())->verifyCode($secret, $code)) {
            throw new RuntimeException('Codice TOTP non valido. Assicurati di aver scansionato il QR correttamente.');
        }

        $mfa = EmployeeMfa::getOrCreate($employeeId);
        $mfa->mfa_secret  = $secret;
        $mfa->mfa_enabled = 1;
        $mfa->date_upd    = date('Y-m-d H:i:s');
        $mfa->update();

        $codes = (new MfaService())->generateRecoveryCodes($employeeId);
        $_SESSION['_mfaadmin_recovery_codes'] = $codes;

        unset($_SESSION['_mfaadmin_temp_secret'], $_SESSION['_mfaadmin_show_setup_modal']);
        Mfaadmin::setMfaVerified($employeeId);

        return ['redirect' => $this->context->link->getAdminLink('AdminMfaCodes')];
    }

    // -------------------------------------------------------------------------
    // Metodi di supporto
    // -------------------------------------------------------------------------

    private function requireEmployee(): int
    {
        $id = (int) ($this->context->employee->id ?? 0);
        if (!$id) {
            throw new RuntimeException('Non autenticato.');
        }

        return $id;
    }

    private function requireEmployeeVerified(): int
    {
        $id = $this->requireEmployee();

        if (!Mfaadmin::isMfaVerified($id)) {
            throw new RuntimeException('MFA non verificato.');
        }

        return $id;
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}