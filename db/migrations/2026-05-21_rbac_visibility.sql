-- RBAC + visibility rules
CREATE TABLE IF NOT EXISTS user_project_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_project (user_id, project_id),
  KEY idx_project_role (project_id, role)
);

CREATE TABLE IF NOT EXISTS folder_visibility_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  folder_id BIGINT UNSIGNED NOT NULL,
  subject_type ENUM('role','user') NOT NULL,
  subject_value VARCHAR(64) NULL,
  subject_id BIGINT UNSIGNED NULL,
  allow_view TINYINT(1) DEFAULT 0,
  deny_view TINYINT(1) DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_folder (folder_id),
  KEY idx_subject (subject_type, subject_value, subject_id)
);

CREATE TABLE IF NOT EXISTS file_visibility_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id BIGINT UNSIGNED NOT NULL,
  subject_type ENUM('role','user') NOT NULL,
  subject_value VARCHAR(64) NULL,
  subject_id BIGINT UNSIGNED NULL,
  allow_view TINYINT(1) DEFAULT 0,
  deny_view TINYINT(1) DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_file (file_id),
  KEY idx_subject (subject_type, subject_value, subject_id)
);

CREATE TABLE IF NOT EXISTS role_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(64) NOT NULL,
  permission_key VARCHAR(128) NOT NULL,
  permission_value TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_role_perm (role, permission_key)
);
