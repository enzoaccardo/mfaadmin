<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Pagina di gestione MFA nel profilo dell'employee:
 * - Abilitazione/disabilitazione TOTP
 * - Rigenera codici di recupero
 * - Gestione passkey registrate
 */
class AdminMfaProfileController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $employeeId = $this->getVerifiedEmployeeId();

        $mfa           = EmployeeMfa::getByEmployeeId($employeeId);
        $passkeys      = EmployeePasskey::getByEmployeeId($employeeId);
        $recoveryCodes = $mfa ? EmployeeRecoveryCode::countAvailable($employeeId) : 0;
        $passkeyAjaxUrl = $this->context->link->getAdminLink('AdminMfaPasskeyAjax');

        $this->context->smarty->assign([
            'mfa_enabled'         => $mfa && $mfa->mfa_enabled,
            'mfa_has_secret'      => $mfa && !empty($mfa->mfa_secret),
            'recovery_codes_count' => $recoveryCodes,
            'passkeys'            => $passkeys,
            'passkey_ajax_url'    => $passkeyAjaxUrl,
            'form_action'         => $this->context->link->getAdminLink('AdminMfaProfile'),
            'mfa_success'         => $this->getAndClearFlash('mfa_success'),
            'mfa_error'           => $this->getAndClearFlash('mfa_error'),
        ]);

        $this->addJS($this->module->getPathUri() . 'views/js/passkey-register.js');

        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        $this->setTemplate('profile.tpl');
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitDisableMfa')) {
            $this->processDisableMfa();
        } elseif (Tools::isSubmit('submitRegenerateCodes')) {
            $this->processRegenerateCodes();
        } elseif (Tools::isSubmit('submitEnableMfa')) {
            $this->processEnableMfa();
        }
    }

    private function processEnableMfa(): void
    {
        $employeeId = $this->getVerifiedEmployeeId();
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaSetup'));
    }

    private function processDisableMfa(): void
    {
        $employeeId = $this->getVerifiedEmployeeId();

        $code = trim((string) Tools::getValue('confirm_code', ''));
        $mfa  = EmployeeMfa::getByEmployeeId($employeeId);

        if (!$mfa || !$mfa->mfa_secret || !(new MfaService())->verifyCode($mfa->mfa_secret, $code)) {
            $this->setFlash('mfa_error', 'Codice TOTP non valido. Inserisci il codice corrente per disabilitare MFA.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
        }

        $mfa->mfa_enabled = 0;
        $mfa->mfa_secret  = null;
        $mfa->date_upd    = date('Y-m-d H:i:s');
        $mfa->update();

        EmployeeRecoveryCode::deleteAllForEmployee($employeeId);

        $this->setFlash('mfa_success', 'MFA disabilitato con successo.');
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
    }

    private function processRegenerateCodes(): void
    {
        $employeeId = $this->getVerifiedEmployeeId();

        $code = trim((string) Tools::getValue('confirm_code_regen', ''));
        $mfa  = EmployeeMfa::getByEmployeeId($employeeId);

        if (!$mfa || !$mfa->mfa_secret || !(new MfaService())->verifyCode($mfa->mfa_secret, $code)) {
            $this->setFlash('mfa_error', 'Codice TOTP non valido. Inserisci il codice corrente per rigenerare.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
        }

        $codes = (new MfaService())->generateRecoveryCodes($employeeId);
        $_SESSION['_mfaadmin_recovery_codes'] = $codes;

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaCodes'));
    }

    private function getVerifiedEmployeeId(): int
    {
        $id = (int) ($this->context->employee->id ?? 0);

        if (!$id) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($id);

        // MFA non ancora abilitato → accesso libero per configurarlo
        if (!$mfa || !$mfa->mfa_enabled) {
            return $id;
        }

        // MFA abilitato ma non verificato in questa sessione
        if (!Mfaadmin::isMfaVerified($id)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaVerify'));
        }

        return $id;
    }

    private function setFlash(string $key, string $value): void
    {
        $_SESSION['_mfaadmin_flash_' . $key] = $value;
    }

    private function getAndClearFlash(string $key): ?string
    {
        $sessionKey = '_mfaadmin_flash_' . $key;
        $value = $_SESSION[$sessionKey] ?? null;
        unset($_SESSION[$sessionKey]);

        return $value;
    }
}