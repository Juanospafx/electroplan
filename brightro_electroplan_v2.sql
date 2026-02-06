-- 1. LIMPIEZA INICIAL Y CONFIGURACIÓN
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Borrar la BD anterior si existe para evitar conflictos de "tablas ya existentes"
DROP DATABASE IF EXISTS `brightro_electroplan_v2`;
CREATE DATABASE `brightro_electroplan_v2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `brightro_electroplan_v2`;

-- --------------------------------------------------------
-- 1. TABLA FILES
-- --------------------------------------------------------
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `sub_folder_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_type` enum('pdf','image') NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `version_group_id` varchar(50) DEFAULT NULL,
  `version_number` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `folder_id` (`folder_id`),
  KEY `sub_folder_id` (`sub_folder_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_deleted_files` (`deleted_at`),
  KEY `idx_versions` (`version_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 2. TABLA FILE_REPORTS
-- --------------------------------------------------------
CREATE TABLE `file_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `technician_name` varchar(100) DEFAULT NULL,
  `technician_role` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `annotations_json` longtext DEFAULT NULL,
  `scale_pixels_per_unit` float DEFAULT 1,
  `scale_unit` varchar(10) DEFAULT 'ft',
  `report_pdf_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_reports_active` (`file_id`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. TABLA FOLDERS
-- --------------------------------------------------------
CREATE TABLE `folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_id` (`project_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- (HEMOS ELIMINADO EL INSERT DE DATOS BASURA AQUÍ PARA EVITAR EL ERROR #1452)

-- --------------------------------------------------------
-- 4. TABLA PROJECTS (YA INCLUYE LOS NUEVOS CAMPOS)
-- --------------------------------------------------------
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  
  -- Campos nuevos necesarios para tu formulario:
  `address` text DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `date_bid_sent` date DEFAULT NULL,
  `date_bid_awarded` date DEFAULT NULL,
  `date_started` date DEFAULT NULL,
  `date_finished` date DEFAULT NULL,
  `date_warranty_end` date DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  
  `status` enum('Planning','Active','On Hold','Completed') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_deleted_projects` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 5. TABLA SUB_FOLDERS
-- --------------------------------------------------------
CREATE TABLE `sub_folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_system_folder` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 6. TABLA USERS
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','technician','viewer') DEFAULT 'technician',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `users` (ESTOS SÍ SON NECESARIOS PARA ENTRAR)
INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(2, 'admin', '$2y$10$MfNd.HbFVGhuItuhROvx1OsriJZllURUPrtaPPkOwBLua49c45n5.', 'admin', '2026-01-12 13:31:11'),
(6, 'Guillermo', '$2y$10$YAGoQu6JjQs8DlJ1sVvgkeAcpy/fBfwxNdwbnKMGrLyCYgyEvhVYm', 'technician', '2026-01-13 12:11:55'),
(7, 'View', '$2y$10$ZMF/pcnMZAvx9crfDByKM.NCPG6Qzw9YaxVeiJPUhpoVWPVDRP2ZW', 'viewer', '2026-01-13 12:13:18');

-- --------------------------------------------------------
-- 7. RESTRICCIONES (FOREIGN KEYS)
-- --------------------------------------------------------
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_3` FOREIGN KEY (`sub_folder_id`) REFERENCES `sub_folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `file_reports`
  ADD CONSTRAINT `file_reports_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sub_folders`
  ADD CONSTRAINT `sub_folders_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

-- Reactivar seguridad y confirmar
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;