CREATE TABLE IF NOT EXISTS file_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_id INT NOT NULL,
  user_id INT NOT NULL,
  viewed_at DATETIME NOT NULL DEFAULT NOW(),
  INDEX idx_user_file (user_id, file_id),
  INDEX idx_viewed_at (viewed_at)
);
