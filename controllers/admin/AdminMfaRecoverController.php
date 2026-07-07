<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Login con codice di recupero one-time come alternativa al TOTP.
 */
class AdminMfaRecoverController extends ModuleAdminController
{
    private ?string $recoverError = null;

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    /**
     * Vedi AdminMfaVerifyController::viewAccess(): pagina raggiungibile da ogni
     * employee autenticato a prescindere dai permessi ACL del proprio profilo.
     */
    public function viewAccess($disable = false): bool
    {
        return true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaRecover')) {
            $this->processRecover();
        }
    }

    public function initContent(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        if (Mfaadmin::isMfaVerified($employeeId)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($employeeId);
        if ($mfa && $mfa->isLocked()) {
            $this->recoverError = $this->lockedMessage($mfa);
        }

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'mfa_error'       => $this->recoverError,
            'verify_url'      => $this->context->link->getAdminLink('AdminMfaVerify'),
            'form_action'     => $this->context->link->getAdminLink('AdminMfaRecover'),
            'card_max_width'  => '520px',
            'module_dir'      => $this->module->getPathUri(),
            'shop_name'       => Configuration::get('PS_SHOP_NAME'),
            'shop_logo_url'   => $logo ? Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'img/' . $logo : '',
            'admin_theme_url' => Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/themes/default/public/',
        ]);
    }

    public function display(): void
    {
        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        echo $this->context->smarty->fetch('recover.tpl');
        exit;
    }

    private function processRecover(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($employeeId);

        // Blocco persistito su DB, condiviso con la verifica TOTP: non e' azzerabile
        // aprendo una nuova sessione/tab.
        if ($mfa && $mfa->isLocked()) {
            $this->recoverError = $this->lockedMessage($mfa);
            return;
        }

        $code = trim((string) Tools::getValue('recovery_code', ''));

        if (!(new MfaService())->verifyRecoveryCode($employeeId, $code)) {
            if ($mfa) {
                $newAttempts = $mfa->registerFailedAttempt();

                if ($newAttempts === EmployeeMfa::WARN_AT_ATTEMPT) {
                    Mfaadmin::sendFailAlert($employeeId, 'warning', $newAttempts);
                }

                if ($mfa->isLocked()) {
                    Mfaadmin::sendFailAlert($employeeId, 'lockout', $newAttempts);
                    $this->recoverError = $this->lockedMessage($mfa);
                    return;
                }
            }

            $this->recoverError = 'Codice di recupero non valido o già usato.';
            return;
        }

        if ($mfa) {
            $mfa->resetFailedAttempts();
        }
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
