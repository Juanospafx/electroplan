-- Schema changes for project assignment and directory

-- 1) Add assigned_user_id to projects
ALTER TABLE projects
  ADD COLUMN assigned_user_id INT(11) NULL AFTER created_by,
  ADD KEY idx_assigned_user_id (assigned_user_id);

-- 2) Backfill assigned_user_id with first admin user (if any)
UPDATE projects
SET assigned_user_id = (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1
)
WHERE assigned_user_id IS NULL;

-- 3) Add FK for assigned_user_id
ALTER TABLE projects
  ADD CONSTRAINT projects_ibfk_2
  FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4) Create directory table (project-user assignments)
CREATE TABLE IF NOT EXISTS directory (
  project_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, user_id),
  KEY idx_directory_user (user_id),
  CONSTRAINT directory_ibfk_1 FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT directory_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5) Ensure directory has at least the primary assignment
INSERT IGNORE INTO directory (project_id, user_id)
SELECT id, assigned_user_id FROM projects WHERE assigned_user_id IS NOT NULL;
