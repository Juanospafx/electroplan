-- Migration: 20261008_takeoff_won_project_import.sql
-- Description: Tablas InnoDB aditivas para persistencia, idempotencia y auditoria de WonProjectExport.v1
--
-- ROLLBACK:
--   DROP TABLE IF EXISTS `takeoff_imported_materials`;
--   DROP TABLE IF EXISTS `takeoff_project_links`;
--   DROP TABLE IF EXISTS `takeoff_won_project_receipts`;
--
-- NOTA DE SEGURIDAD ROLLBACK:
--   La reversion documentada elimina exclusivamente las tres tablas creadas por esta migracion
--   (takeoff_imported_materials, takeoff_project_links, takeoff_won_project_receipts)
--   en orden seguro de dependencias de claves foraneas y nunca elimina ni modifica filas de las tablas
--   'projects', 'folders' ni datos importados del proyecto existentes.

CREATE TABLE IF NOT EXISTS `takeoff_won_project_receipts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
    `source_system` VARCHAR(64) NOT NULL,
    `source_project_id` VARCHAR(128) NOT NULL,
    `occurred_at` DATETIME(6) NOT NULL,
    `payload_hash` CHAR(64) NOT NULL,
    `payload_canonical` LONGTEXT NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `result` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_receipt_event_id` (`event_id`),
    KEY `idx_receipt_source` (`source_system`, `source_project_id`),
    KEY `idx_receipt_hash` (`payload_hash`),
    KEY `idx_receipt_order` (`occurred_at`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `takeoff_project_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `source_system` VARCHAR(64) NOT NULL,
    `source_project_id` VARCHAR(128) NOT NULL,
    `source_bid_id` VARCHAR(128) NULL,
    `source_estimate_id` VARCHAR(128) NULL,
    `last_event_id` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
    `last_occurred_at` DATETIME(6) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_link_source_project` (`source_system`, `source_project_id`),
    KEY `idx_link_project_id` (`project_id`),
    KEY `idx_link_last_order` (`last_occurred_at`, `last_event_id`),
    CONSTRAINT `fk_takeoff_project_links_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `takeoff_imported_materials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `link_id` BIGINT UNSIGNED NOT NULL,
    `item_id` VARCHAR(128) NOT NULL,
    `item_code` VARCHAR(128) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(128) NOT NULL,
    `quantity` DECIMAL(15, 4) NOT NULL,
    `unit_of_measure` VARCHAR(32) NOT NULL,
    `unit_cost` DECIMAL(15, 4) NULL,
    `total_cost` DECIMAL(15, 4) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_material_link_item` (`link_id`, `item_id`),
    KEY `idx_material_link_active` (`link_id`, `is_active`),
    CONSTRAINT `fk_takeoff_imported_materials_link` FOREIGN KEY (`link_id`) REFERENCES `takeoff_project_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
