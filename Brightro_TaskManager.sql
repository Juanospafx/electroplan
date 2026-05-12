-- 1. Actualizar tabla principal
ALTER TABLE `projects`
  ADD `total_tasks` INT(11) DEFAULT 0 AFTER `status`,
  ADD `completed_tasks` INT(11) DEFAULT 0 AFTER `total_tasks`;

-- 2. Sistema de Plantillas
CREATE TABLE `task_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `task_template_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `template_id` INT(11) NOT NULL,
  `stage_name` VARCHAR(150) NOT NULL,
  `parent_item_id` INT(11) DEFAULT NULL,
  `item_order` INT(11) NOT NULL,
  `name` TEXT NOT NULL,
  `estimated_minutes` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`template_id`) REFERENCES `task_templates` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_item_id`) REFERENCES `task_template_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Sistema Vivo (Project Tasks)
CREATE TABLE `project_stages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_id` INT(11) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `stage_order` INT(11) NOT NULL,
  `assigned_user_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `project_tasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_id` INT(11) NOT NULL,
  `stage_id` INT(11) NOT NULL,
  `parent_task_id` INT(11) DEFAULT NULL,
  `folder_id` INT(11) DEFAULT NULL,
  `task_order` INT(11) NOT NULL,
  `name` TEXT NOT NULL,
  `estimated_minutes` INT NOT NULL DEFAULT 0,
  `worked_minutes` INT NOT NULL DEFAULT 0,
  `status` ENUM('Pending','Active','On_Hold','System_Pause','Overdue','Bypassed','Completed') DEFAULT 'Pending',
  `assigned_user_id` INT(11) DEFAULT NULL,
  `actual_start_time` DATETIME DEFAULT NULL,
  `expected_end_time` DATETIME DEFAULT NULL,
  `actual_end_time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_task_id`) REFERENCES `project_tasks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Historial y Justificaciones
CREATE TABLE `task_time_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `action_type` ENUM('Started','Paused','Resumed','Bypassed','Completed','Extended') NOT NULL,
  `justification_note` TEXT DEFAULT NULL,
  `logged_at` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`task_id`) REFERENCES `project_tasks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `project_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fase 89
CREATE INDEX idx_project_status ON project_tasks (project_id, status);
CREATE INDEX idx_parent_task ON project_tasks (parent_task_id);
CREATE INDEX idx_task_logged ON task_time_logs (task_id, logged_at);

-- Fase 92
ALTER TABLE `users` 
ADD COLUMN `work_start_time` TIME DEFAULT '07:00:00',
ADD COLUMN `work_end_time` TIME DEFAULT '19:00:00';
