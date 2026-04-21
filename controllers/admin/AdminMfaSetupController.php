<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Setup iniziale TOTP: mostra QR code, conferma codice, salva secret e genera recovery codes.
 */
class AdminMfaSetupController extends ModuleAdminController
{
    private ?string $setupError = null;

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaSetup')) {
            $this->processSetup();
        }
    }

    public function initContent(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        // Se MFA già abilitato e verificato, non serve il setup
        $mfa = EmployeeMfa::getByEmployeeId($employeeId);
        if ($mfa && $mfa->mfa_enabled && $mfa->mfa_secret && Mfaadmin::isMfaVerified($employeeId)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }

        $mfaService = new MfaService();

        // Riusa il secret temporaneo già generato (evita QR diversi su ogni reload)
        $secret = $_SESSION['_mfaadmin_temp_secret'] ?? $mfaService->generateSecret();
        $_SESSION['_mfaadmin_temp_secret'] = $secret;

        $employee = $this->context->employee;
        $uri = $mfaService->getQrCodeUri((string) $employee->email, $secret);
        $svg = $mfaService->getQrCodeSvg($uri);

        $logo = Configuration::get('PS_LOGO');
        $this->context->smarty->assign([
            'mfa_error'       => $this->setupError,
            'qr_svg'          => $svg,
            'mfa_secret'      => $secret,
            'form_action'     => $this->context->link->getAdminLink('AdminMfaSetup'),
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
        echo $this->context->smarty->fetch('setup.tpl');
        exit;
    }

    private function processSetup(): void
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin'));
        }

        $secret = $_SESSION['_mfaadmin_temp_secret'] ?? '';
        $code   = trim((string) Tools::getValue('code', ''));

        if (!$secret || !(new MfaService())->verifyCode($secret, $code)) {
            $this->setupError = 'Codice non valido. Assicurati di aver scansionato correttamente il QR.';
            return;
        }

        // Salva secret e abilita MFA
        $mfa = EmployeeMfa::getOrCreate($employeeId);
        $mfa->mfa_secret  = $secret;
        $mfa->mfa_enabled = 1;
        $mfa->date_upd    = date('Y-m-d H:i:s');
        $mfa->update();

        // Genera codici di recupero
        $codes = (new MfaService())->generateRecoveryCodes($employeeId);
        $_SESSION['_mfaadmin_recovery_codes'] = $codes;

        unset($_SESSION['_mfaadmin_temp_secret']);
        Mfaadmin::setMfaVerified($employeeId);

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaCodes'));
    }

    private function getEmployeeId(): int
    {
        return (int) ($this->context->employee->id ?? 0);
    }
}
