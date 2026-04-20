<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Visualizzazione one-time dei codici di recupero appena generati.
 * Dopo la conferma i codici vengono rimossi dalla sessione.
 */
class AdminMfaCodesController extends ModuleAdminController
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

        $codes = $_SESSION['_mfaadmin_recovery_codes'] ?? [];

        // Se non ci sono codici da mostrare, vai al profilo MFA
        if (empty($codes)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaProfile'));
        }

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'recovery_codes'  => $codes,
            'form_action'     => $this->context->link->getAdminLink('AdminMfaCodes'),
            'shop_name'       => Configuration::get('PS_SHOP_NAME'),
            'shop_logo_url'   => $logo ? Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'img/' . $logo : '',
            'admin_theme_url' => Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/themes/default/public/',
        ]);
    }

    public function display(): void
    {
        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        echo $this->context->smarty->fetch('codes.tpl');
        exit;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaCodesConfirm')) {
            unset($_SESSION['_mfaadmin_recovery_codes']);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
    }
}