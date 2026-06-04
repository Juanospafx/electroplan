-- Supports File Manager project filtering and filename-first search candidate selection.
ALTER TABLE files
  ADD INDEX idx_files_filename (filename(191)),
  ADD INDEX idx_files_project_deleted_uploaded (project_id, deleted_at, uploaded_at);

ALTER TABLE folders
  ADD INDEX idx_folders_name (name(100));

ALTER TABLE sub_folders
  ADD INDEX idx_sub_folders_name (name(100));
