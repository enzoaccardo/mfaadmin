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

    /**
     * Pagina di impostazioni personali: ogni employee deve poter gestire il
     * proprio MFA a prescindere dai permessi ACL del proprio profilo (per
     * default PrestaShop concede l'accesso ai tab dei moduli al solo SuperAdmin).
     */
    public function viewAccess($disable = false): bool
    {
        return true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitDisableMfa')) {
            $this->processDisableMfa();
        } elseif (Tools::isSubmit('submitRegenerateCodes')) {
            $this->processRegenerateCodes();
        } elseif (Tools::isSubmit('submitEnableMfa')) {
            $this->processEnableMfa();
        } elseif (Tools::isSubmit('submitClearNewCodes')) {
            unset($_SESSION['_mfaadmin_new_codes_show']);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
        }
    }

    public function initContent(): void
    {
        parent::initContent();

        $employeeId = $this->getVerifiedEmployeeId();

        $mfa           = EmployeeMfa::getByEmployeeId($employeeId);
        $passkeys      = EmployeePasskey::getByEmployeeId($employeeId);
        $recoveryCodes = $mfa ? EmployeeRecoveryCode::countAvailable($employeeId) : 0;

        $this->context->smarty->assign([
            'mfa_enabled'          => $mfa && $mfa->mfa_enabled,
            'recovery_codes_count' => $recoveryCodes,
            'passkeys'             => $passkeys,
            'passkey_ajax_url'     => $this->context->link->getAdminLink('AdminMfaPasskeyAjax'),
            'form_action'          => $this->context->link->getAdminLink('AdminMfaProfile'),
            'mfa_new_codes'        => $_SESSION['_mfaadmin_new_codes_show'] ?? [],
            'mfa_success'          => $this->getAndClearFlash('mfa_success'),
            'mfa_error'            => $this->getAndClearFlash('mfa_error'),
            'module_dir'           => $this->module->getPathUri(),
        ]);

        $this->addCSS($this->module->getPathUri() . 'views/css/mfa-profile.css');
        $this->addJS($this->module->getPathUri() . 'views/js/passkey-register.js');

        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        $this->setTemplate('profile.tpl');
    }

    private function processEnableMfa(): void
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaSetup'));
    }

    private function processDisableMfa(): void
    {
        $employeeId = $this->getVerifiedEmployeeId();

        $code = trim((string) Tools::getValue('confirm_code', ''));
        $mfa  = EmployeeMfa::getByEmployeeId($employeeId);

        if (!$mfa || !$mfa->mfa_secret || !(new MfaService())->verifyCode((string) $mfa->getPlainSecret(), $code)) {
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

        if (!$mfa || !$mfa->mfa_secret || !(new MfaService())->verifyCode((string) $mfa->getPlainSecret(), $code)) {
            $this->setFlash('mfa_error', 'Codice TOTP non valido. Inserisci il codice corrente per rigenerare.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
        }

        $_SESSION['_mfaadmin_new_codes_show'] = (new MfaService())->generateRecoveryCodes($employeeId);

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
    }

    private function getVerifiedEmployeeId(): int
    {
        $id = (int) ($this->context->employee->id ?? 0);

        if (!$id) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($id);

        if (!$mfa || !$mfa->mfa_enabled) {
            return $id;
        }

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
        $value      = $_SESSION[$sessionKey] ?? null;
        unset($_SESSION[$sessionKey]);
        return $value;
    }
}
