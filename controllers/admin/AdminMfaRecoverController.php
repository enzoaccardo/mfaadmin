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

        $code = trim((string) Tools::getValue('recovery_code', ''));

        if (!(new MfaService())->verifyRecoveryCode($employeeId, $code)) {
            $this->recoverError = 'Codice di recupero non valido o già usato.';
            return;
        }

        Mfaadmin::setMfaVerified($employeeId);
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
    }
}
