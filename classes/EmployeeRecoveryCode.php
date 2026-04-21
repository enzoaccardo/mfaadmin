<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmployeeRecoveryCode extends ObjectModel
{
    /** @var int */
    public $id_employee;

    /** @var string */
    public $code_hash;

    /** @var string|null */
    public $used_at;

    /** @var string|null */
    public $date_add;

    public static $definition = [
        'table'   => 'employee_recovery_code',
        'primary' => 'id_recovery_code',
        'fields'  => [
            'id_employee' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'code_hash'   => ['type' => self::TYPE_STRING, 'size' => 255, 'required' => true],
            'used_at'     => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_add'    => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    public static function countAvailable(int $employeeId): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'employee_recovery_code`
             WHERE `id_employee` = ' . $employeeId . '
               AND (`used_at` IS NULL OR `used_at` = \'0000-00-00 00:00:00\')'
        );
    }

    public static function deleteAllForEmployee(int $employeeId): void
    {
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'employee_recovery_code`
             WHERE `id_employee` = ' . $employeeId
        );
    }

    /**
     * @return array{id_recovery_code: int, code_hash: string}[]
     */
    public static function getAvailableForEmployee(int $employeeId): array
    {
        return Db::getInstance()->executeS(
            'SELECT `id_recovery_code`, `code_hash`
             FROM `' . _DB_PREFIX_ . 'employee_recovery_code`
             WHERE `id_employee` = ' . $employeeId . '
               AND (`used_at` IS NULL OR `used_at` = \'0000-00-00 00:00:00\')'
        ) ?: [];
    }
}
