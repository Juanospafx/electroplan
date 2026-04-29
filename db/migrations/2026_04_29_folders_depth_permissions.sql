-- Feature 1: recursive folders (run first)
ALTER TABLE folders ADD COLUMN parent_id INT NULL DEFAULT NULL;
ALTER TABLE folders ADD COLUMN depth TINYINT NOT NULL DEFAULT 0;
ALTER TABLE folders ADD INDEX idx_parent_id (parent_id);
ALTER TABLE folders ADD INDEX idx_depth (depth);

INSERT INTO folders (project_id, name, parent_id, depth, created_at)
SELECT sf.project_id, sf.name, sf.folder_id, 1, NOW()
FROM sub_folders sf
WHERE sf.deleted_at IS NULL;

UPDATE files f
JOIN sub_folders sf ON f.sub_folder_id = sf.id
JOIN folders fn ON fn.name = sf.name AND fn.parent_id = sf.folder_id AND fn.depth = 1
SET f.folder_id = fn.id, f.sub_folder_id = NULL
WHERE f.sub_folder_id IS NOT NULL;

-- Feature 2: granular folder permissions (run after feature 1 alters)
CREATE TABLE IF NOT EXISTS folder_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  folder_id INT NOT NULL,
  user_id INT NOT NULL,
  granted_by INT NOT NULL,
  created_at DATETIME DEFAULT NOW(),
  UNIQUE KEY uniq_folder_user (folder_id, user_id),
  INDEX idx_user_id (user_id),
  INDEX idx_folder_id (folder_id)
);
