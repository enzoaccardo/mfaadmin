CREATE TABLE IF NOT EXISTS `PREFIX_employee_mfa` (
  `id_employee_mfa` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT(10) UNSIGNED NOT NULL,
  `mfa_secret` VARCHAR(64) NULL DEFAULT NULL,
  `mfa_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `date_add` DATETIME NULL DEFAULT NULL,
  `date_upd` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_employee_mfa`),
  UNIQUE KEY `id_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_employee_recovery_code` (
  `id_recovery_code` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT(10) UNSIGNED NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `date_add` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_recovery_code`),
  KEY `idx_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_employee_passkey` (
  `id_passkey` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT(10) UNSIGNED NOT NULL,
  `credential_id` VARCHAR(512) NOT NULL,
  `credential_source` MEDIUMTEXT NOT NULL,
  `device_label` VARCHAR(100) NOT NULL DEFAULT '',
  `date_add` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_passkey`),
  UNIQUE KEY `credential_id` (`credential_id`(255)),
  KEY `idx_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;