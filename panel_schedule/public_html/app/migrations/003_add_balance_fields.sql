ALTER TABLE panelboards
  ADD COLUMN balance_status ENUM('OK','WARN','FAIL') DEFAULT 'OK' AFTER percent_imbalance,
  ADD COLUMN balance_message VARCHAR(255) NULL AFTER balance_status;
