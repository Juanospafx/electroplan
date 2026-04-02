CREATE DATABASE IF NOT EXISTS brightro_panelmaster
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE panelmaster;


CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_name VARCHAR(255) NOT NULL,
  project_number VARCHAR(20) NOT NULL UNIQUE,
  basis_of_design VARCHAR(50),
  last_update DATE,
  service_voltage VARCHAR(50),
  service_amps DECIMAL(10,2),
  service_kva DECIMAL(10,2),
  total_panels INT,
  load_lighting DECIMAL(10,2),
  load_recept DECIMAL(10,2),
  load_cooling DECIMAL(10,2),
  load_heating DECIMAL(10,2),
  load_motors DECIMAL(10,2),
  load_lg_mtr DECIMAL(10,2),
  load_equip DECIMAL(10,2),
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS panelboards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  item_order INT NOT NULL,
  panel_name VARCHAR(255) NOT NULL,
  panel_status VARCHAR(20),
  voltage VARCHAR(20),
  phase_wire VARCHAR(20),
  poles_config VARCHAR(20),
  panel_type VARCHAR(50),
  main_type VARCHAR(20),
  main_size_type VARCHAR(100),
  mounting VARCHAR(100),
  connected_kva DECIMAL(10,2),
  connected_amps DECIMAL(10,2),
  demand_kva DECIMAL(10,2),
  demand_amps DECIMAL(10,2),
  percent_imbalance DECIMAL(10,2),
  balance_status ENUM('OK','WARN','FAIL') DEFAULT 'OK',
  balance_message VARCHAR(255),
  minimum_feeder_size VARCHAR(100),
  schedule_json LONGTEXT,
  last_update DATE,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_panelboards_projects
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_panelboards_project ON panelboards(project_id, item_order);

-- Update for existing databases (run if you are updating an existing DB):
-- ALTER TABLE panelboards ADD COLUMN balance_message VARCHAR(255) AFTER balance_status;
