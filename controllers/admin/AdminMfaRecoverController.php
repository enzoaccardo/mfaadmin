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

        if (Mfaadmin::isMfaVerified($employeeId)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'mfa_error'       => $this->getAndClearFlash('mfa_error'),
            'verify_url'      => $this->context->link->getAdminLink('AdminMfaVerify'),
            'form_action'     => $this->context->link->getAdminLink('AdminMfaRecover'),
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

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaRecover')) {
            $this->processRecover();
        }
    }

    private function processRecover(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        $code = trim((string) Tools::getValue('recovery_code', ''));

        if (!(new MfaService())->verifyRecoveryCode($employeeId, $code)) {
            $this->setFlash('mfa_error', 'Codice di recupero non valido o già usato.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaRecover'));
        }

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