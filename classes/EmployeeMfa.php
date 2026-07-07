<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmployeeMfa extends ObjectModel
{
    /** @var int */
    public $id_employee;

    /** @var string|null Secret TOTP cifrato a riposo (AES-256-GCM, vedi MfaCrypto). Non usare direttamente: vedi getPlainSecret()/setPlainSecret(). */
    public $mfa_secret;

    /** @var int */
    public $mfa_enabled = 0;

    /** @var int Tentativi di verifica falliti consecutivi (TOTP + codice di recupero). Persistito lato server: non azzerabile aprendo una nuova sessione. */
    public $failed_attempts = 0;

    /** @var string|null Se valorizzato e futuro, la verifica MFA e' bloccata fino a questa data/ora. */
    public $locked_until;

    /** @var int Ultimo time-step TOTP (RFC 6238) accettato: impedisce il replay dello stesso codice. */
    public $last_totp_counter = 0;

    /** @var string|null */
    public $date_add;

    /** @var string|null */
    public $date_upd;

    /** Tentativi falliti consecutivi oltre i quali l'account viene bloccato temporaneamente. */
    public const MAX_ATTEMPTS = 5;

    /** Tentativo al quale viene inviata l'email di allerta "warning". */
    public const WARN_AT_ATTEMPT = 3;

    /** Durata del blocco temporaneo dopo MAX_ATTEMPTS fallimenti. */
    public const LOCKOUT_MINUTES = 15;

    public static $definition = [
        'table'   => 'employee_mfa',
        'primary' => 'id_employee_mfa',
        'fields'  => [
            'id_employee'       => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'mfa_secret'        => ['type' => self::TYPE_STRING, 'size' => 255],
            'mfa_enabled'       => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'failed_attempts'   => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'locked_until'      => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'allow_null' => true],
            'last_totp_counter' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'date_add'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /**
     * Decifra e restituisce il secret TOTP in chiaro, pronto per Google2FA.
     */
    public function getPlainSecret(): ?string
    {
        if (!$this->mfa_secret) {
            return null;
        }

        return MfaCrypto::decrypt($this->mfa_secret);
    }

    /**
     * Cifra e imposta il secret TOTP. Passare null per rimuoverlo (disable MFA).
     */
    public function setPlainSecret(?string $plainSecret): void
    {
        $this->mfa_secret = $plainSecret !== null && $plainSecret !== ''
            ? MfaCrypto::encrypt($plainSecret)
            : null;
    }

    public function isLocked(): bool
    {
        return !empty($this->locked_until)
            && $this->locked_until !== '0000-00-00 00:00:00'
            && strtotime($this->locked_until) > time();
    }

    public function getRemainingLockoutSeconds(): int
    {
        if (!$this->isLocked()) {
            return 0;
        }

        return max(0, strtotime($this->locked_until) - time());
    }

    /**
     * Registra un tentativo di verifica fallito (TOTP o codice di recupero) e,
     * raggiunta la soglia, blocca temporaneamente l'account. Persistito su DB:
     * a differenza di un contatore in sessione, non e' azzerabile aprendo una
     * nuova sessione/cookie di sessione.
     *
     * @return int Numero di tentativi falliti consecutivi dopo questo fallimento
     */
    public function registerFailedAttempt(): int
    {
        $this->failed_attempts = (int) $this->failed_attempts + 1;

        if ($this->failed_attempts >= self::MAX_ATTEMPTS) {
            $this->locked_until = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
        }

        $this->date_upd = date('Y-m-d H:i:s');
        $this->update();

        return $this->failed_attempts;
    }

    public function resetFailedAttempts(): void
    {
        if ((int) $this->failed_attempts === 0 && empty($this->locked_until)) {
            return;
        }

        $this->failed_attempts = 0;
        $this->locked_until    = null;
        $this->date_upd        = date('Y-m-d H:i:s');
        $this->update();
    }

    public static function getByEmployeeId(int $employeeId): ?self
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT 
                `id_employee_mfa` 
             FROM 
                `' . _DB_PREFIX_ . 'employee_mfa`
             WHERE 
                `id_employee` = ' . $employeeId
        );

        if (!$id) {
            return null;
        }

        $obj = new self($id);

        return $obj->id ? $obj : null;
    }

    public static function getOrCreate(int $employeeId): self
    {
        $existing = self::getByEmployeeId($employeeId);

        if ($existing) {
            return $existing;
        }

        $mfa = new self();
        $mfa->id_employee = $employeeId;
        $mfa->mfa_enabled = 0;
        $mfa->last_totp_counter = 0;
        $mfa->date_add = date('Y-m-d H:i:s');
        $mfa->date_upd = date('Y-m-d H:i:s');
        $mfa->add();

        return $mfa;
    }
}