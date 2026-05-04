-- 1. Crear la plantilla maestra (Asumiendo que el admin tiene id = 1)
INSERT INTO `task_templates` (`id`, `name`, `description`, `created_by`) 
VALUES (1, 'Electro Plan Master Standard Flow', 'Flujo completo: Desde Licitación (Quote) hasta ejecución en Job Site (incluyendo RFIs).', 1);

-- 2. Inyectar TODAS las 80 tareas maestras con su espaciado de 10,000
INSERT INTO `task_template_items` (`template_id`, `stage_name`, `item_order`, `name`, `estimated_hours`) VALUES

-- ==========================================
-- Etapa 1: Quote Projects
-- ==========================================
(1, 'Quote Projects', 10000, 'Get email with the invitation', 0.00),
(1, 'Quote Projects', 20000, 'Read the details and scope of this project', 0.50),
(1, 'Quote Projects', 30000, 'Validate address and due date of the project is within working range', 0.50),
(1, 'Quote Projects', 40000, 'Download files from invitation', 0.50),
(1, 'Quote Projects', 50000, 'Upload files to takeoff platform set dates | GC and scope on notes', 0.50),
(1, 'Quote Projects', 60000, 'Create the BoM for this project', 3.00),
(1, 'Quote Projects', 70000, 'Send BoM to supplier indicated on plans, if non is specified, send to rexel and city electric', 1.50),
(1, 'Quote Projects', 80000, 'Follow up Received BoM of this project the day of the due date', 0.50),
(1, 'Quote Projects', 90000, 'Verify quote and list of equipment included on quote from supplier match lighting and gear schedule', 1.00),
(1, 'Quote Projects', 100000, 'Add lighting package and gear package cost + taxes to the quote', 0.50),
(1, 'Quote Projects', 110000, 'Complete underground, rough in, overhead and rest of work and materials required on plans, always checking notes', 8.00),
(1, 'Quote Projects', 120000, 'Confirm pricing of materials and wires is updated', 1.00),
(1, 'Quote Projects', 130000, 'Make final adjustments on pricing and send to client or GC', 1.00),
(1, 'Quote Projects', 140000, 'Allocate files in their respective folders', 1.00),
(1, 'Quote Projects', 150000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 2: Awarded project
-- ==========================================
(1, 'Awarded project', 160000, 'Sign contract', 0.50),
(1, 'Awarded project', 170000, 'Request noc from client', 0.50),
(1, 'Awarded project', 180000, 'Create certificate of insurance to GC and ask for their certificate of insurance (WC and GL and W9)', 1.00),
(1, 'Awarded project', 190000, 'Request submittal to suppliers', 0.50),
(1, 'Awarded project', 200000, 'Follow up from supplier about submittal after 3 days', 0.50),
(1, 'Awarded project', 210000, 'Verify that the submittal includes everithing according to spec on plans', 1.00),
(1, 'Awarded project', 220000, 'After received submittal send to owner or GC for signature by engineer', 0.50),
(1, 'Awarded project', 230000, 'Follow up from GC or owner about submittal after 3 days', 0.50),
(1, 'Awarded project', 240000, 'Send submittals back to supplier', 0.50),
(1, 'Awarded project', 250000, 'Ask for shipping report to supplier', 0.50),
(1, 'Awarded project', 260000, 'After shipping report received create on calendar dates of deliveries to follow up with them', 1.00),
(1, 'Awarded project', 270000, 'Make list rough in materials to order', 1.00),
(1, 'Awarded project', 280000, 'Make list miscellaneus materials to order', 1.00),
(1, 'Awarded project', 290000, 'Schedule first day of work in the jobsite', 1.00),
(1, 'Awarded project', 300000, 'Create NTO of project', 1.00),
(1, 'Awarded project', 310000, 'Pay NTO service', 0.50),
(1, 'Awarded project', 320000, 'Create pay application every 15th -20th of every month (per project)', 1.00),
(1, 'Awarded project', 330000, 'After payment is ready to be picked up get summary of pending account from supplier and let alberto know to make payment', 0.50),
(1, 'Awarded project', 340000, 'Request waivers from accounts', 0.50),
(1, 'Awarded project', 350000, 'Receive payment', 0.50),
(1, 'Awarded project', 360000, 'Go back to process 17', 0.50),
(1, 'Awarded project', 370000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 3: Follow up on bids to GC and clients
-- ==========================================
(1, 'Follow up on bids to GC and clients on bidded projects', 380000, 'Call or email GC a week after bid to know about our bid', 0.50),
(1, 'Follow up on bids to GC and clients on bidded projects', 390000, 'Call or email GC a month after bid to know about our bid', 0.50),
(1, 'Follow up on bids to GC and clients on bidded projects', 400000, 'If there is no RFI move Project to status', 0.50),
(1, 'Follow up on bids to GC and clients on bidded projects', 410000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 4: RFI from GC
-- ==========================================
(1, 'RFI from GC', 420000, 'Read RFI | pass RFI to our platform', 0.50),
(1, 'RFI from GC', 430000, 'Find documentation required by GC | call or email person with the information', 1.00),
(1, 'RFI from GC', 440000, 'Redact response and forward information to GC', 0.50),
(1, 'RFI from GC', 450000, 'Monitor responses from this RFI daily for 1 week', 0.50),
(1, 'RFI from GC', 460000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 5: RFI To GC
-- ==========================================
(1, 'RFI To GC', 470000, 'Recolect information and documentation pertaining this RFI', 0.50),
(1, 'RFI To GC', 480000, 'Redact explanation to GC to explain the issue and the solution', 1.00),
(1, 'RFI To GC', 490000, 'Pass RFI to our platform', 0.50),
(1, 'RFI To GC', 500000, 'Monitor responses from this RFI daily for 1 week if there is a response asking for more information return to #1', 0.50),
(1, 'RFI To GC', 510000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 6: RFI from BTX Foreman
-- ==========================================
(1, 'RFI from BTX Foreman', 520000, 'Read RFI | pass RFI to our platform', 0.50),
(1, 'RFI from BTX Foreman', 530000, 'Find documentation required by GC | call or email person with the information', 1.00),
(1, 'RFI from BTX Foreman', 540000, 'Redact response and forward information to GC', 0.50),
(1, 'RFI from BTX Foreman', 550000, 'Monitor responses from this RFI daily for 1 week', 0.50),
(1, 'RFI from BTX Foreman', 560000, 'Upload documentation to project database', 0.50),

-- ==========================================
-- Etapa 7: Layout underground conduits (24 Hrs)
-- ==========================================
(1, 'Layout underground conduits', 570000, 'Get the mesurement of where the conduits are going to begin/end of all the conduits to run Based on de Electrical plans', 24.00),
(1, 'Layout underground conduits', 580000, 'Trace the route of each one of the conduits to find a way to minimize the amount of trenches needed to run the conduits', 24.00),
(1, 'Layout underground conduits', 590000, 'Check correct location on the conduits taking in to account the wall width', 24.00),
(1, 'Layout underground conduits', 600000, 'Look at the riser and based on the submittals of the gear that is going to be used, make the separation according to it', 24.00),
(1, 'Layout underground conduits', 610000, 'Adjust the stub up and separation on the conduits according to the riser equipment', 24.00),
(1, 'Layout underground conduits', 620000, 'Find the location of the existing seccundary conduits and make summary of the amount of materials to extend to the wall where the service is going to go up', 24.00),

-- ==========================================
-- Etapa 8: Underground Conduits (24 Hrs)
-- ==========================================
(1, 'Underground Conduits', 630000, 'Start the trench', 24.00),
(1, 'Underground Conduits', 640000, 'While doing the trench the other part of the crew start laying the conduits inside the trench', 24.00),
(1, 'Underground Conduits', 650000, 'Use 45deg or 90deg when required', 24.00),
(1, 'Underground Conduits', 660000, 'Put the final 90deg to stub up', 24.00),
(1, 'Underground Conduits', 670000, 'Put glue on all the conduits joints', 24.00),
(1, 'Underground Conduits', 680000, 'Before backfill lay the electrical service tape on top of the conduits', 24.00),
(1, 'Underground Conduits', 690000, 'Pass inspection', 24.00),
(1, 'Underground Conduits', 700000, 'Backfill trenches', 24.00),
(1, 'Underground Conduits', 710000, 'Use the tamper to give for leveling ground', 24.00),

-- ==========================================
-- Etapa 9: Order Material to supplier (24 Hrs)
-- ==========================================
(1, 'Order Material to supplier', 720000, 'Receive request from foreman on the jobsite', 24.00),
(1, 'Order Material to supplier', 730000, 'Validate if we have any of the material in stock in our warehouse (if no skip until 5)', 24.00),
(1, 'Order Material to supplier', 740000, 'If we do start checking each of the materials and put in their respective bags', 24.00),
(1, 'Order Material to supplier', 750000, 'Set a pallet down and put all materials on a pallet and send picture to foreman to let them know they can pic it up by the end of the day', 24.00),
(1, 'Order Material to supplier', 760000, 'Set order of material to supplier and indicate if there is any urgency in the material, either for early morning or regular delivery', 24.00),
(1, 'Order Material to supplier', 770000, 'Follow up after 1 or 2 hours with supplier if they have not confirm the order is ready for delivery or acknowledgement', 24.00),
(1, 'Order Material to supplier', 780000, 'Upload documentation to project database', 24.00),

-- ==========================================
-- Etapa 10: Apply to be electrical contractor (24 Hrs)
-- ==========================================
(1, 'Apply to be electrical contractor to a new GC or owner', 790000, 'Look on website if there is a way to apply and what is the requirement to appl', 24.00),
(1, 'Apply to be electrical contractor to a new GC or owner', 800000, 'Create letter indicating interest in becoming their electrical contractor', 24.00);