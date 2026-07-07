<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Mfaadmin extends Module
{
    /** Prefisso chiave sessione per stato MFA verificato */
    private const SESSION_PREFIX = '_mfaadmin_verified_';

    /** Controller MFA interni: sempre esclusi dal redirect, non configurabili */
    private const MFA_CONTROLLERS = [
        'AdminMfaVerify',
        'AdminMfaSetup',
        'AdminMfaRecover',
        'AdminMfaCodes',
        'AdminMfaPasskeyAjax',
        'AdminMfaProfile',
        'AdminMfaConfig',
        'AdminLogin',
    ];

    /**
     * Restituisce la whitelist completa: controller MFA interni + controller configurati da UI.
     * @return string[]
     */
    public static function getWhitelistedControllers(): array
    {
        $extra = (string) Configuration::get('MFAADMIN_BYPASS_CONTROLLERS');
        if ($extra === '') {
            return self::MFA_CONTROLLERS;
        }

        $parsed = array_filter(array_map('trim', explode(',', $extra)));

        return array_merge(self::MFA_CONTROLLERS, $parsed);
    }

    public function __construct()
    {
        $this->name    = 'mfaadmin';
        $this->tab     = 'administration';
        $this->version = '1.0.0';
        $this->author  = 'Vincenzo Accardo';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('Admin MFA & Passkey');
        $this->description = $this->l('Autenticazione a due fattori (TOTP + Passkey WebAuthn) per l\'area admin.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    // -------------------------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------------------------

    public function install(): bool
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('actionAdminControllerInitBefore')
            && $this->registerHook('actionDispatcherBefore')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayAdminAfterHeader')
            && $this->registerHook('displayAdminNavBarBeforeEnd')
            && $this->installTabs();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && $this->uninstallTabs()
            && $this->uninstallConfiguration();
    }

    private function uninstallConfiguration(): bool
    {
        foreach ([
            'MFAADMIN_REQUIRED',
            'MFAADMIN_DISABLED',
            'MFAADMIN_BYPASS_CONTROLLERS',
            'MFAADMIN_ALERT_EMAIL',
        ] as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    private function installDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function installTabs(): bool
    {
        $tabs = [
            // Nascosti: endpoint interni non accessibili dal menu
            'AdminMfaVerify'      => ['name' => 'MFA Verify',       'parent' => -1,                       'icon' => ''],
            'AdminMfaSetup'       => ['name' => 'MFA Setup',        'parent' => -1,                       'icon' => ''],
            'AdminMfaRecover'     => ['name' => 'MFA Recovery',     'parent' => -1,                       'icon' => ''],
            'AdminMfaCodes'       => ['name' => 'MFA Codes',        'parent' => -1,                       'icon' => ''],
            'AdminMfaPasskeyAjax' => ['name' => 'MFA Passkey Ajax', 'parent' => -1,                       'icon' => ''],
            // Visibili nel menu laterale
            'AdminMfaProfile'     => ['name' => 'Profilo MFA',      'parent' => 'AdminParentEmployees',   'icon' => 'security'],
            'AdminMfaConfig'      => ['name' => 'Configurazione MFA', 'parent' => 'AdminAdvancedParameters', 'icon' => 'security'],
        ];

        foreach ($tabs as $className => $config) {
            if (Tab::getIdFromClassName($className)) {
                continue;
            }

            $parentId = $config['parent'];
            if (is_string($parentId)) {
                $parentId = (int) Tab::getIdFromClassName($parentId) ?: -1;
            }

            $tab = new Tab();
            $tab->class_name = $className;
            $tab->module     = $this->name;
            $tab->id_parent  = $parentId;
            $tab->icon       = $config['icon'];

            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = $config['name'];
            }

            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $controllers = ['AdminMfaConfig', 'AdminMfaVerify', 'AdminMfaSetup', 'AdminMfaRecover', 'AdminMfaCodes', 'AdminMfaPasskeyAjax', 'AdminMfaProfile'];

        foreach ($controllers as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                (new Tab($idTab))->delete();
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Hook: intercetta ogni controller admin "legacy" (AdminController)
    // -------------------------------------------------------------------------

    public function hookActionAdminControllerInitBefore(array $params): void
    {
        $this->enforceMfaGate((string) Tools::getValue('controller'));
    }

    // -------------------------------------------------------------------------
    // Hook: intercetta le pagine admin migrate a Symfony (FrameworkBundleAdminController).
    // Necessario perché actionAdminControllerInitBefore e' emesso solo da
    // AdminController::init() (classes/controller/AdminController.php) e NON viene
    // mai eseguito per i controller Symfony (es. Dashboard, Employees, Modules):
    // senza questo hook aggiuntivo, un employee con MFA già configurato che dopo il
    // login atterra su una pagina Symfony non verrebbe mai reindirizzato alla verifica.
    // -------------------------------------------------------------------------

    public function hookActionDispatcherBefore(array $params): void
    {
        // 2 = ActionDispatcherLegacyHooksSubscriber::BACK_OFFICE_CONTROLLER
        if ((int) ($params['controller_type'] ?? 0) !== 2) {
            return;
        }

        // Le pagine legacy "bridged" verso Symfony mantengono ?controller=X nella query
        // string (es. i controller di questo stesso modulo): Tools::getValue('controller')
        // la restituisce comunque. Le pagine Symfony native (routing by path, es.
        // /configure/advanced/employees/) non hanno questo parametro: la whitelist per
        // nome controller semplicemente non trova corrispondenza e si passa al gate MFA.
        $this->enforceMfaGate((string) Tools::getValue('controller'));
    }

    private function enforceMfaGate(string $currentController): void
    {
        // MFA globalmente disabilitato → salta qualsiasi verifica
        if (Configuration::get('MFAADMIN_DISABLED')) {
            return;
        }

        $context = Context::getContext();

        // Nessun employee loggato → nulla da fare
        if (!$context->employee || !(int) $context->employee->id) {
            return;
        }

        // Controller in whitelist (interni MFA + configurati da UI) → sempre accessibili
        if ($currentController !== '' && in_array($currentController, self::getWhitelistedControllers(), true)) {
            return;
        }

        $employeeId = (int) $context->employee->id;

        if (self::isMfaVerified($employeeId)) {
            unset($_SESSION['_mfaadmin_show_setup_modal'], $_SESSION['_mfaadmin_temp_secret']);
            return;
        }

        $mfa      = EmployeeMfa::getByEmployeeId($employeeId);
        $forceAll = (bool) Configuration::get('MFAADMIN_REQUIRED');

        // Né MFA personale attivo né globale → nulla da fare
        if (!$forceAll && (!$mfa || !$mfa->mfa_enabled)) {
            unset($_SESSION['_mfaadmin_show_setup_modal']);
            return;
        }

        // MFA richiesto, setup già fatto ma non verificato in sessione → redirect a verifica
        if ($mfa && $mfa->mfa_secret) {
            Tools::redirectAdmin($context->link->getAdminLink('AdminMfaVerify'));
        }

        // Setup non ancora fatto → mostra modal inline sulla pagina corrente
        $_SESSION['_mfaadmin_show_setup_modal'] = true;
    }

    public function getContent(): void
    {
        Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminMfaConfig'));
    }

    public function hookDisplayAdminNavBarBeforeEnd(array $params): string
    {
        return '';
    }

    public function hookDisplayAdminAfterHeader(array $params): string
    {
        $employeeId = (int) ($this->context->employee->id ?? 0);
        if (!$employeeId) {
            return '';
        }

        $mfa     = EmployeeMfa::getByEmployeeId($employeeId);
        $isSetup = $mfa && $mfa->mfa_enabled && $mfa->mfa_secret;

        // A questo punto del layout (line 127 di layout.tpl) header e nav sono gia' nel DOM.
        // 1) Sidebar: aggiunge voce in .main-menu se il tab PS non compare ancora per cache
        // 2) Topbar: inserisce dropdown-item prima di #header_logout
        $data = json_encode([
            'url'   => $this->context->link->getAdminLink('AdminMfaProfile'),
            'label' => $isSetup ? 'Impostazioni MFA' : 'Configura MFA',
            'color' => $isSetup ? '#25b9d7' : '#f39c12',
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return '<script>(function(){'
            . 'var d=' . $data . ';'

            // Sidebar: inietta solo se la voce non e' gia' presente (tab PS non ancora in cache)
            . 'var menu=document.querySelector(".nav-bar-overflow .main-menu");'
            . 'if(menu&&!document.getElementById("mfa-nav-item")){'
            .   'var li=document.createElement("li");li.id="mfa-nav-item";li.className="link-levelone";'
            .   'var na=document.createElement("a");na.href=d.url;na.className="link";'
            .   'var ni=document.createElement("i");ni.className="material-icons";ni.style.color=d.color;ni.textContent="security";'
            .   'var ns=document.createElement("span");ns.textContent=d.label;'
            .   'na.appendChild(ni);na.appendChild(ns);li.appendChild(na);menu.appendChild(li);'
            . '}'

            // Topbar: inserisce dropdown-item prima del pulsante di logout
            . 'var lo=document.getElementById("header_logout");'
            . 'if(lo&&lo.parentNode){'
            .   'var sep=document.createElement("p");sep.className="divider";'
            .   'var a=document.createElement("a");a.href=d.url;a.className="dropdown-item employee-link";'
            .   'a.innerHTML=\'<i class="material-icons" style="color:\'+d.color+\'">security</i> \'+d.label;'
            .   'lo.parentNode.insertBefore(sep,lo);lo.parentNode.insertBefore(a,sep);'
            . '}'

            . '})();</script>';
    }

    public function hookDisplayBackOfficeHeader(array $params): string
    {
        if (empty($_SESSION['_mfaadmin_show_setup_modal'])) {
            return '';
        }

        $employeeId = (int) ($this->context->employee->id ?? 0);
        if (!$employeeId) {
            return '';
        }

        $mfaService = new MfaService();
        $secret = $_SESSION['_mfaadmin_temp_secret'] ?? $mfaService->generateSecret();
        $_SESSION['_mfaadmin_temp_secret'] = $secret;

        $uri = $mfaService->getQrCodeUri((string) $this->context->employee->email, $secret);
        $svg = $mfaService->getQrCodeSvg($uri);

        if (isset($this->context->controller)) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/setup-modal.js');
        }

        return '<script>window.mfaSetupData = ' . json_encode([
            'qrSvg'    => $svg,
            'secret'   => $secret,
            'ajaxUrl'  => $this->context->link->getAdminLink('AdminMfaPasskeyAjax'),
            'codesUrl' => $this->context->link->getAdminLink('AdminMfaCodes'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
    }

    // -------------------------------------------------------------------------
    // API pubblica (usata dai controller)
    // -------------------------------------------------------------------------

    public static function setMfaVerified(int $employeeId): void
    {
        // Usa il cookie PS8 (distrutto al logout) invece di $_SESSION (che persiste tra logout/login)
        $cookie = Context::getContext()->cookie;
        $cookie->{self::SESSION_PREFIX . $employeeId} = time();
        $cookie->write();
    }

    public static function isMfaVerified(int $employeeId): bool
    {
        // L'employee deve essere loggato e il suo ID deve corrispondere a quello richiesto
        $contextId = (int) (Context::getContext()->employee->id ?? 0);
        if (!$contextId || $contextId !== $employeeId) {
            return false;
        }

        return !empty(Context::getContext()->cookie->{self::SESSION_PREFIX . $employeeId});
    }

    public static function clearMfaVerified(int $employeeId): void
    {
        $cookie = Context::getContext()->cookie;
        $cookie->{self::SESSION_PREFIX . $employeeId} = '';
        $cookie->write();
    }

    // -------------------------------------------------------------------------
    // Alert email per tentativi MFA falliti
    // -------------------------------------------------------------------------

    /**
     * Restituisce l'email di allerta configurata, con fallback su PS_SHOP_EMAIL.
     */
    public static function getAlertEmail(): string
    {
        $configured = trim((string) Configuration::get('MFAADMIN_ALERT_EMAIL'));
        if (!empty($configured) && Validate::isEmail($configured)) {
            return $configured;
        }

        return (string) Configuration::get('PS_SHOP_EMAIL');
    }

    /**
     * Invia email di allerta sicurezza all'employee e all'indirizzo admin configurato.
     *
     * @param int    $employeeId   ID employee target
     * @param string $type         'warning' (3° fail) | 'lockout' (5° fail)
     * @param int    $attemptCount Numero di tentativi falliti accumulati
     */
    public static function sendFailAlert(int $employeeId, string $type, int $attemptCount): void
    {
        $employee = new Employee($employeeId);
        if (!$employee->id) {
            return;
        }

        $alertEmail = self::getAlertEmail();
        $mailFolder = _PS_MODULE_DIR_ . 'mfaadmin/mails/';
        $idLang     = (int) Configuration::get('PS_LANG_DEFAULT');
        $template   = 'mfa_alert_' . $type;
        $subject    = $type === 'lockout'
            ? '[ALLERTA] Account MFA bloccato — ' . $employee->firstname . ' ' . $employee->lastname
            : '[Sicurezza] Tentativi MFA falliti — ' . $employee->firstname . ' ' . $employee->lastname;

        $templateVars = [
            '{employee_name}'      => $employee->firstname . ' ' . $employee->lastname,
            '{employee_email}'     => $employee->email,
            '{attempt_count}'      => (string) $attemptCount,
            '{remaining_attempts}' => (string) max(0, 5 - $attemptCount),
            '{ip_address}'         => Tools::getRemoteAddr(),
            '{user_agent}'         => htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/D', ENT_QUOTES),
            '{datetime}'           => date('d/m/Y H:i:s'),
            '{shop_name}'          => (string) Configuration::get('PS_SHOP_NAME'),
        ];

        // Carica configurazione SMTP da un modulo esterno
        $mailUpParams = null;
        if (!class_exists('SmtpExterno')) {
            $moduleFile = _PS_MODULE_DIR_ . 'smtpexterno/smtpexterno.php';
            if (file_exists($moduleFile)) {
                require_once $moduleFile;
            }
        }
        if (class_exists('SmtpExterno') && method_exists('SmtpExterno', 'getSmtpConfig')) {
            $cfg = SmtpExterno::getSmtpConfig();
            if (!empty($cfg)) {
                $mailUpParams = $cfg;
            }
        }

        // Invia all'employee
        if (!empty($employee->email) && Validate::isEmail($employee->email)) {
            Mail::send($idLang, $template, $subject, $templateVars, $employee->email,
                null, null, null, null, null, $mailFolder, false, null, null, null, null, $mailUpParams);
        }

        // Invia all'admin alert (se diverso dall'employee)
        if (!empty($alertEmail) && Validate::isEmail($alertEmail) && $alertEmail !== $employee->email) {
            Mail::send($idLang, $template, $subject, $templateVars, $alertEmail,
                null, null, null, null, null, $mailFolder, false, null, null, null, null, $mailUpParams);
        }
    }
}
