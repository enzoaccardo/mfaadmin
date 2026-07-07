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

        if ($mfa->isLocked()) {
            $this->verifyError = $this->lockedMessage($mfa);
        }

        $passkeys = EmployeePasskey::getByEmployeeId($employeeId);

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'mfa_error'        => $this->verifyError,
            'mfa_locked'       => $mfa->isLocked(),
            'has_passkeys'     => !empty($passkeys) && !$mfa->isLocked(),
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

        $mfa = EmployeeMfa::getByEmployeeId($employeeId);

        if (!$mfa || !$mfa->mfa_secret) {
            $this->verifyError = 'MFA non configurato.';
            return;
        }

        // Blocco persistito su DB: non e' azzerabile aprendo una nuova sessione/tab.
        if ($mfa->isLocked()) {
            $this->verifyError = $this->lockedMessage($mfa);
            return;
        }

        $code = trim((string) Tools::getValue('code', ''));

        if (!(new MfaService())->verifyLoginCode($mfa, $code)) {
            $newAttempts = $mfa->registerFailedAttempt();
            $remaining   = EmployeeMfa::MAX_ATTEMPTS - $newAttempts;

            if ($newAttempts === EmployeeMfa::WARN_AT_ATTEMPT) {
                Mfaadmin::sendFailAlert($employeeId, 'warning', $newAttempts);
            }

            if ($mfa->isLocked()) {
                Mfaadmin::sendFailAlert($employeeId, 'lockout', $newAttempts);
                $this->verifyError = $this->lockedMessage($mfa);
                return;
            }

            $this->verifyError = 'Codice non valido o scaduto. Tentativi rimanenti: ' . $remaining . '.';
            return;
        }

        // Successo → azzera contatore fallimenti, rigenera la sessione (mitiga session fixation) e verifica
        $mfa->resetFailedAttempts();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Mfaadmin::setMfaVerified($employeeId);
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
    }

    private function lockedMessage(EmployeeMfa $mfa): string
    {
        $minutes = (int) ceil($mfa->getRemainingLockoutSeconds() / 60);

        return 'Troppi tentativi falliti. Riprova tra ' . $minutes . ' minut' . ($minutes === 1 ? 'o' : 'i') . '.';
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
    }
}
