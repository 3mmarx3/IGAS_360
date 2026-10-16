<?php
require_once __DIR__ . '/../config/db.php';

echo "=== Applying Unified Schema Alignments and Seeding ===\n";

// Helper for adding column if not exists
function addColIfNotExists(PDO $pdo, string $table, string $colDef) {
    try {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$colDef}");
    } catch (\PDOException $e) {
        // column already exists, safe to ignore
    }
}

// 1. PARTNERS (Suppliers)
addColIfNotExists($pdo, 'partners', 'assigned_to INT(11) DEFAULT NULL');
$suppCount = (int)$pdo->query("SELECT COUNT(*) FROM partners WHERE partner_type = 'supplier'")->fetchColumn();
if ($suppCount === 0) {
    $pdo->exec("
        INSERT INTO partners (reference_id, partner_type, company_name, cr_number, country, city, address, contact_first_name, contact_last_name, email, phone, status, entity_type, segment, rating)
        VALUES 
        ('SUP-001', 'supplier', 'Air Liquide Industrial Middle East', '1010892341', 'Saudi Arabia', 'Jubail', 'Support Industrial Area, Way 14', 'Ahmed', 'Al-Ghamdi', 'supplies@airliquide-me.com', '+966 13 345 6789', 'approved', 'Corporate', 'Raw Gas Supply', 4.8),
        ('SUP-002', 'supplier', 'Linde Cryogenic Plants & Equipment', '1010776512', 'Saudi Arabia', 'Dammam', '2nd Industrial City, St 40', 'Fahad', 'Al-Otaibi', 'sales@linde-gulf.com', '+966 13 812 3456', 'approved', 'Corporate', 'Cryogenics', 4.9),
        ('SUP-003', 'supplier', 'Gulf Specialty Gas Cylinders Corp', '1010664321', 'Saudi Arabia', 'Riyadh', 'Industrial Area Phase 2', 'Tariq', 'Mansour', 'orders@gulfcylinders.com', '+966 11 498 7654', 'approved', 'Corporate', 'Hardware & Valves', 4.6)
    ");
    echo "Seeded 3 suppliers in partners.\n";
}

// Update raw_materials to link to supplier
$sup1 = $pdo->query("SELECT id FROM partners WHERE reference_id = 'SUP-001'")->fetchColumn();
if ($sup1) {
    $pdo->exec("UPDATE raw_materials SET supplier_id = {$sup1} WHERE supplier_id IS NULL OR supplier_id = 0");
    echo "Linked raw_materials to supplier_id {$sup1}.\n";
}

// 2. SHIFT LOGS
addColIfNotExists($pdo, 'shift_logs', "shift_id VARCHAR(50) DEFAULT NULL");
addColIfNotExists($pdo, 'shift_logs', "shift_cycle VARCHAR(50) DEFAULT 'Morning'");
addColIfNotExists($pdo, 'shift_logs', "operational_date DATE DEFAULT NULL");
addColIfNotExists($pdo, 'shift_logs', "actual_output DECIMAL(10,2) DEFAULT 0.00");
addColIfNotExists($pdo, 'shift_logs', "target_output DECIMAL(10,2) DEFAULT 500.00");
addColIfNotExists($pdo, 'shift_logs', "operators_count INT(11) DEFAULT 3");
addColIfNotExists($pdo, 'shift_logs', "status VARCHAR(50) DEFAULT 'completed'");
addColIfNotExists($pdo, 'shift_logs', "safety_alerts INT(11) DEFAULT 0");

$shiftCount = (int)$pdo->query("SELECT COUNT(*) FROM shift_logs")->fetchColumn();
if ($shiftCount === 0) {
    $pdo->exec("
        INSERT INTO shift_logs (
            shift_code, shift_id, shift_date, operational_date, shift_type, shift_cycle,
            supervisor, operator, operators_count, compressor_hours, power_consumption_kwh,
            output_o2_m3, output_n2_m3, output_ar_m3, actual_output, target_output,
            liquid_produced_liters, status, safety_alerts
        ) VALUES 
        ('SHF-0501', 'SHF-0501', date('now'), date('now'), 'morning', 'Morning', 'Sami Al-Harbi', 'Khalid Aziz', 4, 7.5, 3420.00, 180.00, 120.00, 45.00, 345.00, 350.00, 4500.00, 'in_progress', 0),
        ('SHF-0500', 'SHF-0500', date('now', '-1 day'), date('now', '-1 day'), 'evening', 'Evening', 'Omar Al-Zahrani', 'Yousef Hassan', 3, 8.0, 3890.00, 200.00, 140.00, 50.00, 390.00, 380.00, 5100.00, 'completed', 0),
        ('SHF-0499', 'SHF-0499', date('now', '-2 days'), date('now', '-2 days'), 'night', 'Night', 'Bandar Al-Dossary', 'Nasser Salem', 3, 7.8, 3600.00, 195.00, 135.00, 48.00, 378.00, 380.00, 4900.00, 'completed', 0)
    ");
    echo "Seeded 3 shift_logs.\n";
} else {
    $pdo->exec("UPDATE shift_logs SET shift_id = COALESCE(shift_id, shift_code), operational_date = COALESCE(operational_date, shift_date), actual_output = COALESCE(actual_output, (output_o2_m3 + output_n2_m3 + output_ar_m3))");
}

// 3. GAS CATEGORIES & GAS MIXTURES
addColIfNotExists($pdo, 'gas_categories', 'category_name VARCHAR(100) DEFAULT NULL');
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM gas_categories")->fetchColumn();
if ($catCount === 0) {
    $pdo->exec("
        INSERT INTO gas_categories (name, category_name) VALUES 
        ('Industrial Gases', 'Industrial Gases'),
        ('Medical Gases', 'Medical Gases'),
        ('Specialty Gases', 'Specialty Gases'),
        ('Welding Mixtures', 'Welding Mixtures'),
        ('Cryogenic Liquids', 'Cryogenic Liquids')
    ");
    echo "Seeded gas_categories.\n";
} else {
    $pdo->exec("UPDATE gas_categories SET category_name = name WHERE category_name IS NULL");
}

addColIfNotExists($pdo, 'gas_mixtures', 'product_name VARCHAR(100) DEFAULT NULL');
addColIfNotExists($pdo, 'gas_mixtures', 'standard_cost DECIMAL(10,2) DEFAULT 0.00');
$mixCount = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures")->fetchColumn();
if ($mixCount === 0) {
    $pdo->exec("
        INSERT INTO gas_mixtures (mixture_name, product_name, code, gas_components, category, standard_cost, selling_price, stock_quantity, status)
        VALUES 
        ('Argon / CO2 (80/20)', 'Argon / CO2 (80/20)', 'MIX-ARCO2-8020', '80% Argon, 20% Carbon Dioxide', 'Welding Mixtures', 85.00, 140.00, 120, 'active'),
        ('Medical Oxygen Ultra-Pure', 'Medical Oxygen Ultra-Pure', 'MIX-O2-MED', '99.5% Oxygen', 'Medical Gases', 50.00, 95.00, 250, 'active'),
        ('Nitrogen / Hydrogen (95/5)', 'Nitrogen / Hydrogen (95/5)', 'MIX-N2H2-9505', '95% Nitrogen, 5% Hydrogen Forming Gas', 'Specialty Gases', 110.00, 190.00, 80, 'active'),
        ('Laser Gas Helium/N2/CO2', 'Laser Gas Helium/N2/CO2', 'MIX-LASER-01', 'Helium 82%, N2 13.5%, CO2 4.5%', 'Specialty Gases', 220.00, 360.00, 45, 'active')
    ");
    echo "Seeded gas_mixtures.\n";
} else {
    $pdo->exec("UPDATE gas_mixtures SET product_name = mixture_name, standard_cost = unit_cost WHERE product_name IS NULL");
}

// 4. CUSTOMER TANKS
addColIfNotExists($pdo, 'customer_tanks', 'current_level_pct DECIMAL(5,2) DEFAULT 75.00');
$tankCount = (int)$pdo->query("SELECT COUNT(*) FROM customer_tanks")->fetchColumn();
if ($tankCount === 0) {
    $pdo->exec("
        INSERT INTO customer_tanks (client_id, serial_number, tank_type, capacity_liters, current_level_liters, current_level_pct, location_address, telemetry_installed, status)
        VALUES 
        (1, 'TNK-LOX-901', 'Vacuum Insulated Cryo-Tank', 15000.00, 11250.00, 75.00, 'SABIC Complex 3, Jubail', 1, 'operational'),
        (2, 'TNK-LIN-802', 'High-Pressure Microbulk Tank', 8000.00, 5600.00, 70.00, 'Aramco Gas Plant Gate 5', 1, 'operational'),
        (3, 'TNK-LAR-703', 'Ultra-Cold Liquid Argon Vessel', 5000.00, 1200.00, 24.00, 'GASCO Dammam Distribution Site', 1, 'refuel_req'),
        (4, 'TNK-LOX-604', 'Static Bulk Storage Tank', 20000.00, 16000.00, 80.00, 'Al-Jubail Industrial Gases Park', 0, 'operational')
    ");
    echo "Seeded customer_tanks.\n";
} else {
    $pdo->exec("UPDATE customer_tanks SET current_level_pct = ROUND((current_level_liters / capacity_liters) * 100, 1) WHERE current_level_pct IS NULL OR current_level_pct = 0");
}

// 5. DISPATCHES
addColIfNotExists($pdo, 'dispatches', 'instructions TEXT DEFAULT NULL');
$pdo->exec("UPDATE dispatches SET instructions = delivery_notes WHERE instructions IS NULL");

// 6. WAREHOUSES & INVENTORY
addColIfNotExists($pdo, 'warehouses', "warehouse_name VARCHAR(100) DEFAULT NULL");
addColIfNotExists($pdo, 'warehouses', "warehouse_type VARCHAR(50) DEFAULT 'hub'");
addColIfNotExists($pdo, 'warehouses', "status VARCHAR(50) DEFAULT 'active'");
addColIfNotExists($pdo, 'warehouses', "last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
$pdo->exec("UPDATE warehouses SET warehouse_name = name WHERE warehouse_name IS NULL");

addColIfNotExists($pdo, 'warehouse_inventory', "sku VARCHAR(100) DEFAULT NULL");
addColIfNotExists($pdo, 'warehouse_inventory', "item_name VARCHAR(255) DEFAULT NULL");
addColIfNotExists($pdo, 'warehouse_inventory', "quantity_on_hand DECIMAL(10,2) DEFAULT 100.00");
addColIfNotExists($pdo, 'warehouse_inventory', "min_threshold DECIMAL(10,2) DEFAULT 20.00");
addColIfNotExists($pdo, 'warehouse_inventory', "max_capacity DECIMAL(10,2) DEFAULT 500.00");
addColIfNotExists($pdo, 'warehouse_inventory', "category VARCHAR(100) DEFAULT 'Industrial Gas'");
addColIfNotExists($pdo, 'warehouse_inventory', "unit_cost DECIMAL(10,2) DEFAULT 45.00");
addColIfNotExists($pdo, 'warehouse_inventory', "status VARCHAR(50) DEFAULT 'In Stock'");

$whInvCount = (int)$pdo->query("SELECT COUNT(*) FROM warehouse_inventory")->fetchColumn();
if ($whInvCount === 0) {
    $pdo->exec("
        INSERT INTO warehouse_inventory (warehouse_id, item_type, item_reference_id, sku, item_name, quantity, quantity_on_hand, unit, min_threshold, max_capacity, category, unit_cost, status)
        VALUES 
        (1, 'cylinder', 1, 'SKU-LOX-50L', 'Oxygen Cylinder 50L (200 Bar)', 150.00, 150.00, 'cylinders', 30.00, 300.00, 'Industrial Gas', 75.00, 'In Stock'),
        (1, 'cylinder', 2, 'SKU-LIN-50L', 'Nitrogen High Purity 50L', 120.00, 120.00, 'cylinders', 25.00, 250.00, 'Industrial Gas', 80.00, 'In Stock'),
        (1, 'gas_mixture', 1, 'SKU-ARCO2-40L', 'Argon/CO2 80/20 Welding Cylinder', 90.00, 90.00, 'cylinders', 20.00, 200.00, 'Welding Gas', 110.00, 'In Stock'),
        (2, 'cylinder', 3, 'SKU-LAR-50L', 'Argon Pure Cylinder 50L', 65.00, 65.00, 'cylinders', 15.00, 150.00, 'Specialty Gas', 130.00, 'In Stock')
    ");
    echo "Seeded warehouse_inventory.\n";
} else {
    $pdo->exec("UPDATE warehouse_inventory SET quantity_on_hand = quantity WHERE quantity_on_hand IS NULL OR quantity_on_hand = 0");
}

// 7. MAINTENANCE TICKETS
$maintCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets")->fetchColumn();
if ($maintCount === 0) {
    $pdo->exec("
        INSERT INTO maintenance_tickets (ticket_id, vehicle_id, scheduled_date, service_type, workshop, estimated_cost, odometer, status, instructions)
        VALUES 
        ('MNT-2025-001', 'FL-01', date('now', '-5 days'), 'Scheduled 50,000km Service', 'Mercedes Heavy Fleet Workshop', 3200.00, 49850, 'completed', 'Oil filter, fuel filters, brake pad inspection and hydraulic pressure test'),
        ('MNT-2025-002', 'FL-02', date('now', '+2 days'), 'Cryogenic Tank Valve Calibration', 'Specialized Valve Testing Lab', 4800.00, 62100, 'scheduled', 'Annual pressure relief valve calibration and safety certification renewal'),
        ('MNT-2025-003', 'FL-03', date('now', '-1 day'), 'Tire Replacement & Wheel Alignment', 'Al-Jubail Michelin Center', 5600.00, 78400, 'in_progress', 'Replace 4 rear drive axle tires and perform precision steering alignment')
    ");
    echo "Seeded 3 maintenance_tickets.\n";
}

// 8. VEHICLE LOGS
addColIfNotExists($pdo, 'vehicle_logs', "vehicle_id VARCHAR(50) DEFAULT NULL");
$pdo->exec("UPDATE vehicle_logs SET vehicle_id = fleet_id WHERE vehicle_id IS NULL");
$vLogCount = (int)$pdo->query("SELECT COUNT(*) FROM vehicle_logs")->fetchColumn();
if ($vLogCount === 0) {
    $pdo->exec("
        INSERT INTO vehicle_logs (fleet_id, vehicle_id, event_type, event_date, event_time, odometer, fuel_liters, event_cost, logged_by, description)
        VALUES 
        ('FL-01', 'FL-01', 'fuel', date('now', '-1 day'), '08:30:00', 49850, 120.00, 264.00, 'Super Administrator', 'Fuel refill 120L Diesel at Station 14'),
        ('FL-01', 'FL-01', 'maintenance', date('now', '-5 days'), '10:00:00', 49600, 0.00, 3200.00, 'Super Administrator', '50,000km Scheduled Inspection and Service'),
        ('FL-02', 'FL-02', 'delivery', date('now', '-2 days'), '14:15:00', 62100, 140.00, 308.00, 'Super Administrator', 'Bulk liquid delivery trip completed')
    ");
    echo "Seeded 3 vehicle_logs.\n";
}

// 9. PURCHASE ORDERS & ITEMS
$poCount = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
if ($poCount === 0) {
    $pdo->exec("
        INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, total_amount, currency, notes)
        VALUES 
        ('PO-3001', 1, date('now', '-7 days'), date('now', '+3 days'), 'confirmed', 85000.00, 'SAR', 'Bulk LOX replenishment for Dammam ASU Tank 1'),
        ('PO-3002', 2, date('now', '-3 days'), date('now', '+5 days'), 'draft', 42000.00, 'SAR', 'High-pressure replacement valves and regulator kits')
    ");
    $pdo->exec("
        INSERT INTO purchase_order_items (purchase_order_id, item_name, qty, unit_cost, line_total)
        VALUES 
        (1, 'Liquid Oxygen (LOX) Cryo Bulk', 50.00, 1700.00, 85000.00),
        (2, 'Cryo Manifold Valve Kit 200 Bar', 20.00, 2100.00, 42000.00)
    ");
    echo "Seeded 2 purchase_orders and items.\n";
}

// 10. CLIENT CONTACTS & ACTIVITIES
addColIfNotExists($pdo, 'client_contacts', "contact_name VARCHAR(150) DEFAULT NULL");
$contactCount = (int)$pdo->query("SELECT COUNT(*) FROM client_contacts")->fetchColumn();
if ($contactCount === 0) {
    $pdo->exec("
        INSERT INTO client_contacts (client_id, full_name, contact_name, job_title, email, phone, is_primary)
        VALUES 
        (1, 'Dr. Ibrahim Al-Nuaimi', 'Dr. Ibrahim Al-Nuaimi', 'Procurement Director', 'i.nuaimi@sabic.com', '+966 13 359 0001', 1),
        (1, 'Eng. Sultan Al-Otaibi', 'Eng. Sultan Al-Otaibi', 'Plant Operations Manager', 's.otaibi@sabic.com', '+966 13 359 0002', 0),
        (2, 'Eng. Abdullah Al-Falih', 'Eng. Abdullah Al-Falih', 'Refinery Maintenance Chief', 'a.falih@aramco.com', '+966 13 874 1234', 1)
    ");
    echo "Seeded 3 client_contacts.\n";
}

echo "=== All Schema Alignments and Seeding Completed Successfully! ===\n";
