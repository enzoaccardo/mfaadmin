<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Verifica del secondo fattore (TOTP o Passkey) al login.
 * Raggiunto quando l'employee è autenticato ma non ha ancora superato MFA.
 */
class AdminMfaVerifyController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        // Se MFA già verificato, vai alla dashboard
        if (Mfaadmin::isMfaVerified($employeeId)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($employeeId);

        if (!$mfa || !$mfa->mfa_enabled || !$mfa->mfa_secret) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaSetup'));
        }

        $passkeys = EmployeePasskey::getByEmployeeId($employeeId);

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'mfa_error'        => $this->getAndClearFlash('mfa_error'),
            'has_passkeys'     => !empty($passkeys),
            'recover_url'      => $this->context->link->getAdminLink('AdminMfaRecover'),
            'passkey_ajax_url' => $this->context->link->getAdminLink('AdminMfaPasskeyAjax'),
            'form_action'      => $this->context->link->getAdminLink('AdminMfaVerify'),
            'module_dir'       => $this->module->getPathUri(),
            'shop_name'        => Configuration::get('PS_SHOP_NAME'),
            'shop_logo_url'    => $logo ? Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'img/' . $logo : '',
            'admin_theme_url'  => Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/themes/default/public/',
        ]);
    }

    public function display(): void
    {
        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        echo $this->context->smarty->fetch('verify.tpl');
        exit;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaVerify')) {
            $this->processVerify();
        }
    }

    private function processVerify(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        // Limite tentativi: massimo 5 per sessione per prevenire brute-force sul TOTP
        $attemptKey = '_mfaadmin_verify_attempts_' . $employeeId;
        $attempts   = (int) ($_SESSION[$attemptKey] ?? 0);

        if ($attempts >= 5) {
            $this->setFlash('mfa_error', 'Troppi tentativi falliti. Disconnettiti e riaccedi per riprovare.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaVerify'));
        }

        $mfa  = EmployeeMfa::getByEmployeeId($employeeId);
        $code = trim((string) Tools::getValue('code', ''));

        if (!$mfa || !$mfa->mfa_secret) {
            $this->setFlash('mfa_error', 'MFA non configurato.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaVerify'));
        }

        if (!(new MfaService())->verifyCode($mfa->mfa_secret, $code)) {
            $remaining = 4 - $attempts;
            $_SESSION[$attemptKey] = $attempts + 1;
            $msg = $remaining > 0
                ? 'Codice non valido o scaduto. Tentativi rimanenti: ' . $remaining . '.'
                : 'Codice non valido. Hai esaurito i tentativi. Disconnettiti e riaccedi.';
            $this->setFlash('mfa_error', $msg);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaVerify'));
        }

        // Successo -> azzera contatore
        unset($_SESSION[$attemptKey]);
        Mfaadmin::setMfaVerified($employeeId);
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
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