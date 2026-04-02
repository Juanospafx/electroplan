USE panelmaster;

INSERT INTO projects
  (project_name, project_number, basis_of_design, last_update, service_voltage, service_amps, service_kva, total_panels,
   load_lighting, load_recept, load_cooling, load_heating, load_motors, load_lg_mtr, load_equip, created_at, updated_at)
VALUES
  ('Sample Project', 'A000001', 'SQUARE D', CURDATE(), '480Y/277V', 0, 0, 2,
   0, 0, 0, 0, 0, 0, 0, NOW(), NOW());

SET @project_id = LAST_INSERT_ID();

INSERT INTO panelboards
  (project_id, item_order, panel_name, panel_status, voltage, phase_wire, poles_config, panel_type, main_type, main_size_type, mounting,
   connected_kva, connected_amps, demand_kva, demand_amps, percent_imbalance, balance_status, balance_message, minimum_feeder_size,
   schedule_json, last_update, created_at, updated_at)
VALUES
  (@project_id, 1, 'Panel 1P', 'NEW', '208Y/120V', '1PH, 3W', '42', 'Lighting/Appliance', 'BREAKER', '100A', 'Surface',
   0, 0, 0, 0, 0, 'OK', 'Imbalance within 10%', '',
   '{"poles_config":"42","left":[],"right":[]}', CURDATE(), NOW(), NOW()),
  (@project_id, 2, 'Panel 3P', 'NEW', '480Y/277V', '3PH, 4W', '42', 'Distribution', 'BREAKER', '225A', 'Flush',
   0, 0, 0, 0, 0, 'OK', 'Imbalance within 10%', '',
   '{"poles_config":"42","left":[],"right":[]}', CURDATE(), NOW(), NOW());
