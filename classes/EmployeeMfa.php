<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmployeeMfa extends ObjectModel
{
    /** @var int */
    public $id_employee;

    /** @var string|null */
    public $mfa_secret;

    /** @var int */
    public $mfa_enabled = 0;

    /** @var string|null */
    public $date_add;

    /** @var string|null */
    public $date_upd;

    public static $definition = [
        'table'   => 'employee_mfa',
        'primary' => 'id_employee_mfa',
        'fields'  => [
            'id_employee' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'mfa_secret'  => ['type' => self::TYPE_STRING, 'size' => 64],
            'mfa_enabled' => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'date_add'    => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'    => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

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
        $mfa->date_add = date('Y-m-d H:i:s');
        $mfa->date_upd = date('Y-m-d H:i:s');
        $mfa->add();

        return $mfa;
    }
}