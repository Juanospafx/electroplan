ALTER TABLE panelboards
  ADD COLUMN panel_status VARCHAR(20) NULL AFTER panel_name,
  ADD COLUMN phase_wire VARCHAR(20) NULL AFTER voltage,
  ADD COLUMN main_type VARCHAR(20) NULL AFTER panel_type,
  ADD COLUMN schedule_json LONGTEXT NULL AFTER minimum_feeder_size,
  ADD COLUMN last_update DATE NULL AFTER schedule_json;

ALTER TABLE panelboards
  CHANGE COLUMN poles_config poles_config VARCHAR(20) NULL;
