<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmployeePasskey extends ObjectModel
{
    /** @var int */
    public $id_employee;

    /** @var string */
    public $credential_id;

    /** @var string */
    public $credential_source;

    /** @var string */
    public $device_label = '';

    /** @var string|null */
    public $date_add;

    public static $definition = [
        'table'   => 'employee_passkey',
        'primary' => 'id_passkey',
        'fields'  => [
            'id_employee'       => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'credential_id'     => ['type' => self::TYPE_STRING, 'size' => 512, 'required' => true],
            'credential_source' => ['type' => self::TYPE_STRING, 'required' => true],
            'device_label'      => ['type' => self::TYPE_STRING, 'size' => 100],
            'date_add'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /**
     * @return self[]
     */
    public static function getByEmployeeId(int $employeeId): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT 
                `id_passkey` FROM `' . _DB_PREFIX_ . 'employee_passkey`
             WHERE `id_employee` = ' . $employeeId . '
             ORDER BY `date_add` ASC'
        );

        if (!$rows) {
            return [];
        }

        $passkeys = [];
        foreach ($rows as $row) {
            $obj = new self((int) $row['id_passkey']);
            if ($obj->id) {
                $passkeys[] = $obj;
            }
        }

        return $passkeys;
    }

    public static function getByCredentialId(string $credentialId, int $employeeId): ?self
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_passkey` FROM `' . _DB_PREFIX_ . 'employee_passkey`
             WHERE `credential_id` = \'' . pSQL($credentialId) . '\'
               AND `id_employee` = ' . $employeeId
        );

        if (!$id) {
            return null;
        }

        $obj = new self($id);

        return $obj->id ? $obj : null;
    }
}
