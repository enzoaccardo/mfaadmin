<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMfaConfigController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $this->context->smarty->assign([
            'employees'   => $this->getEmployeesWithMfaStatus(),
            'force_mfa'   => (bool) Configuration::get('MFAADMIN_REQUIRED'),
            'form_action' => $this->context->link->getAdminLink('AdminMfaConfig'),
            'mfa_success' => $this->getAndClearFlash('mfa_success'),
            'mfa_error'   => $this->getAndClearFlash('mfa_error'),
        ]);

        $this->context->smarty->addTemplateDir(_PS_MODULE_DIR_ . 'mfaadmin/views/templates/admin/');
        $this->setTemplate('config.tpl');
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitMfaConfig')) {
            $this->processSaveConfig();
        } elseif (Tools::isSubmit('submitResetEmployee')) {
            $this->processResetEmployee();
        }
    }

    private function processSaveConfig(): void
    {
        Configuration::updateValue('MFAADMIN_REQUIRED', (int) (bool) Tools::getValue('force_mfa'));
        $this->setFlash('mfa_success', 'Configurazione salvata.');
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaConfig'));
    }

    private function processResetEmployee(): void
    {
        // Solo il Super Admin puo' resettare il MFA di altri utenti
        if (!$this->context->employee->isSuperAdmin()) {
            $this->setFlash('mfa_error', 'Operazione non autorizzata: richiede privilegi Super Admin.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaConfig'));
        }

        $employeeId  = (int) Tools::getValue('id_employee');
        $currentId   = (int) $this->context->employee->id;

        if (!$employeeId) {
            $this->setFlash('mfa_error', 'Utente non valido.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaConfig'));
        }

        // Impedisce di resettare il proprio account tramite questo flusso
        if ($employeeId === $currentId) {
            $this->setFlash('mfa_error', 'Per modificare il tuo MFA usa la pagina Profilo MFA.');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaConfig'));
        }

        $mfa = EmployeeMfa::getByEmployeeId($employeeId);
        if ($mfa) {
            $mfa->mfa_enabled = 0;
            $mfa->mfa_secret  = null;
            $mfa->date_upd    = date('Y-m-d H:i:s');
            $mfa->update();
        }

        EmployeeRecoveryCode::deleteAllForEmployee($employeeId);
        Mfaadmin::clearMfaVerified($employeeId);

        PrestaShopLogger::addLog(
            '[mfaadmin] Admin #' . $currentId . ' ha resettato MFA per employee #' . $employeeId,
            1,
            null,
            'mfaadmin'
        );

        $this->setFlash('mfa_success', 'MFA reimpostato per l\'utente.');
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMfaConfig'));
    }

    private function getEmployeesWithMfaStatus(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_employee`, `firstname`, `lastname`, `email`
             FROM `' . _DB_PREFIX_ . 'employee`
             ORDER BY `lastname` ASC'
        );
        $result = [];

        foreach ($rows as $row) {
            $id   = (int) $row['id_employee'];
            $mfa  = EmployeeMfa::getByEmployeeId($id);

            $result[] = [
                'id_employee' => $id,
                'firstname'   => $row['firstname'],
                'lastname'    => $row['lastname'],
                'email'       => $row['email'],
                'mfa_enabled' => $mfa ? (bool) $mfa->mfa_enabled : false,
                'has_secret'  => $mfa && !empty($mfa->mfa_secret),
                'passkeys'    => count(EmployeePasskey::getByEmployeeId($id)),
            ];
        }

        return $result;
    }

    private function setFlash(string $key, string $value): void
    {
        $_SESSION['_mfaadmin_flash_' . $key] = $value;
    }

    private function getAndClearFlash(string $key): ?string
    {
        $k     = '_mfaadmin_flash_' . $key;
        $value = $_SESSION[$k] ?? null;
        unset($_SESSION[$k]);

        return $value;
    }
}
