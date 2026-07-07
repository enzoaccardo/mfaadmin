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
    private ?string $verifyError = null;

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    /**
     * Pagina di gate obbligatoria per ogni employee autenticato, indipendentemente
     * dai permessi del proprio profilo: l'ACL "legacy" di PrestaShop concede di
     * default accesso ai soli tab creati da un modulo al profilo SuperAdmin, il
     * che bloccherebbe qui ogni altro employee prima ancora di poter verificare l'MFA.
     */
    public function viewAccess($disable = false): bool
    {
        return true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaVerify')) {
            $this->processVerify();
        }
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
            'mfa_error'        => $this->verifyError,
            'has_passkeys'     => !empty($passkeys),
            'recover_url'      => $this->context->link->getAdminLink('AdminMfaRecover'),
            'passkey_ajax_url' => $this->context->link->getAdminLink('AdminMfaPasskeyAjax'),
            'form_action'      => $this->context->link->getAdminLink('AdminMfaVerify'),
            'card_max_width'   => '500px',
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
            $this->verifyError = 'Troppi tentativi falliti. Disconnettiti e riaccedi per riprovare.';
            return;
        }

        $mfa  = EmployeeMfa::getByEmployeeId($employeeId);
        $code = trim((string) Tools::getValue('code', ''));

        if (!$mfa || !$mfa->mfa_secret) {
            $this->verifyError = 'MFA non configurato.';
            return;
        }

        if (!(new MfaService())->verifyCode($mfa->mfa_secret, $code)) {
            $newAttempts = $attempts + 1;
            $_SESSION[$attemptKey] = $newAttempts;
            $remaining = 5 - $newAttempts;

            // Warning al 3° fail (una sola volta per sessione)
            if ($newAttempts === 3 && empty($_SESSION['_mfaadmin_warned_' . $employeeId])) {
                $_SESSION['_mfaadmin_warned_' . $employeeId] = true;
                Mfaadmin::sendFailAlert($employeeId, 'warning', $newAttempts);
            }

            // Lockout al 5° fail (una sola volta per sessione)
            if ($newAttempts >= 5 && empty($_SESSION['_mfaadmin_locked_' . $employeeId])) {
                $_SESSION['_mfaadmin_locked_' . $employeeId] = true;
                Mfaadmin::sendFailAlert($employeeId, 'lockout', $newAttempts);
            }

            $this->verifyError = $remaining > 0
                ? 'Codice non valido o scaduto. Tentativi rimanenti: ' . $remaining . '.'
                : 'Codice non valido. Hai esaurito i tentativi. Disconnettiti e riaccedi.';
            return;
        }

        // Successo → azzera contatore, flag alert e verifica
        unset($_SESSION[$attemptKey], $_SESSION['_mfaadmin_warned_' . $employeeId], $_SESSION['_mfaadmin_locked_' . $employeeId]);
        Mfaadmin::setMfaVerified($employeeId);
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
    }
}
