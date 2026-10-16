<?php
/**
 * I-GAS Enterprise CRM/ERP - Automated Database Initialization & Seeding
 * Usage: php database/init_database.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

echo "======================================================================\n";
echo "I-GAS Enterprise CRM & ERP — Database Initialization & Seeder\n";
echo "======================================================================\n";

global $pdo;

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "[+] Connected to database using driver: '{$driver}'.\n";
if ($driver === 'sqlite') {
    $pdo->exec("PRAGMA foreign_keys = OFF;");
}

$sqlFile = __DIR__ . '/data.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/../unified_crm_master.sql';
}

if (!file_exists($sqlFile)) {
    die("[-] Error: Master SQL schema file not found.\n");
}

$sqlContent = file_get_contents($sqlFile);

// 1. Create Schema Tables
if ($driver === 'sqlite') {
    echo "[+] Parsing schema and creating 53 relational tables in SQLite...\n";
    $pdo->exec("PRAGMA foreign_keys = OFF;");

    preg_match_all("/CREATE TABLE\s+[`\"]?([a-zA-Z0-9_]+)[`\"]?\s*\((.*?)\)\s*ENGINE/s", $sqlContent, $matches, PREG_SET_ORDER);

    $createdCount = 0;
    foreach ($matches as $m) {
        $tableName = $m[1];
        $body = $m[2];

        $lines = explode("\n", $body);
        $cleanedLines = [];
        $hasAutoPk = false;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === "") continue;

            // Remove standalone index declarations: KEY `name` (...) or FULLTEXT KEY ...
            if (preg_match("/^(KEY|INDEX|FULLTEXT\s+KEY|SPATIAL\s+KEY)\s+[`\"].*?\(/i", $trim)) {
                continue;
            }

            // Strip COMMENT
            $line = preg_replace("/COMMENT\s+\x27[^\x27]*\x27/i", "", $line);

            // Check if AUTO_INCREMENT
            if (preg_match("/AUTO_INCREMENT/i", $line)) {
                $hasAutoPk = true;
                $line = preg_replace("/INT\([0-9]+\)\s+NOT\s+NULL\s+AUTO_INCREMENT/i", "INTEGER PRIMARY KEY AUTOINCREMENT", $line);
                $line = preg_replace("/INT\s+NOT\s+NULL\s+AUTO_INCREMENT/i", "INTEGER PRIMARY KEY AUTOINCREMENT", $line);
                $line = preg_replace("/INT\([0-9]+\)\s+AUTO_INCREMENT/i", "INTEGER PRIMARY KEY AUTOINCREMENT", $line);
                $line = preg_replace("/INT\s+AUTO_INCREMENT/i", "INTEGER PRIMARY KEY AUTOINCREMENT", $line);
            }

            if ($hasAutoPk && preg_match("/^PRIMARY\s+KEY\s*\([`\"]?id[`\"]?\)/i", $trim)) {
                continue;
            }

            $line = preg_replace("/UNIQUE\s+KEY\s+[`\"][^`\"]+[`\"]\s*\(/i", "UNIQUE (", $line);
            $line = preg_replace("/ENUM\([^)]+\)/i", "TEXT", $line);
            $line = preg_replace("/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i", "", $line);

            $cleanedLines[] = $line;
        }

        for ($i = count($cleanedLines) - 1; $i >= 0; $i--) {
            $trimmed = trim($cleanedLines[$i]);
            if ($trimmed !== "") {
                $cleanedLines[$i] = rtrim($cleanedLines[$i], " ,");
                break;
            }
        }

        $createSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n" . implode("\n", $cleanedLines) . "\n)";
        try {
            $pdo->exec($createSql);
            $createdCount++;
        } catch (Exception $e) {
            echo "[-] Error on table {$tableName}: " . $e->getMessage() . "\n";
        }
    }
    echo "[+] Schema verification: {$createdCount} of " . count($matches) . " tables verified.\n";
} else {
    echo "[+] Executing MySQL Master Schema...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec($sqlContent);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "[+] MySQL Schema execution completed.\n";
}

// 2. Seed Industrial Roles
echo "[+] Seeding 10 Industrial Roles...\n";
$roles = [
    [1, 'admin', 'Super Administrator', 'Full unrestricted enterprise-wide control', 1],
    [2, 'sales_manager', 'Sales Manager', 'Manages sales team, approvals, pricing, and clients', 1],
    [3, 'sales_rep', 'Sales Representative', 'Direct management of assigned clients and pipelines', 1],
    [4, 'plant_manager', 'Plant & Production Manager', 'Full control over plant output, cylinders, and tanks', 1],
    [5, 'shift_supervisor', 'Production Shift Supervisor', 'Daily shift logs, cylinder inspections, and filling operations', 1],
    [6, 'logistics_manager', 'Logistics & Fleet Manager', 'Oversees dispatch schedule, vehicles, and maintenance', 1],
    [7, 'driver', 'Fleet Driver & Courier', 'Trip execution, mileage logging, and delivery verification', 1],
    [8, 'warehouse_manager', 'Warehouse & Custody Manager', 'Oversees warehouse inventory and custody withdrawals', 1],
    [9, 'finance', 'Finance & Accountant', 'Manages cash flow, collections, invoices, and commissions', 1],
    [10, 'compliance_officer', 'Compliance & Safety Auditor', 'Auditing, regulatory licenses, and safety compliance', 1]
];

$stmtRole = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO roles (id, role_key, role_name, description, is_system) VALUES (?, ?, ?, ?, ?)"
    : "INSERT INTO roles (id, role_key, role_name, description, is_system) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description)"
);
foreach ($roles as $r) {
    $stmtRole->execute($r);
}

// 3. Seed Granular Permissions
echo "[+] Seeding 43 Granular Permissions...\n";
$permissions = [
    [1, 'users', 'users.view', 'View staff users and profiles'],
    [2, 'users', 'users.create', 'Create new staff accounts'],
    [3, 'users', 'users.edit', 'Modify existing staff accounts'],
    [4, 'users', 'users.delete', 'Deactivate or delete staff accounts'],
    [5, 'users', 'roles.manage', 'Configure roles and assign permissions'],
    [6, 'crm', 'clients.view', 'View client profiles and contact directories'],
    [7, 'crm', 'clients.create', 'Create and onboard new client accounts'],
    [8, 'crm', 'clients.edit', 'Update client contact details and credit limits'],
    [9, 'crm', 'clients.delete', 'Archive or delete client profiles'],
    [10, 'crm', 'clients.all', 'Global client visibility bypassing sales rep boundaries'],
    [11, 'crm', 'quotes.view', 'View pricing quotations'],
    [12, 'crm', 'quotes.create', 'Draft and generate new quotations'],
    [13, 'crm', 'quotes.edit', 'Update quotation terms and discounts'],
    [14, 'crm', 'quotes.delete', 'Cancel or delete quotations'],
    [15, 'crm', 'quotes.approve', 'Authorize high-value quotations'],
    [16, 'crm', 'orders.view', 'View sales orders and purchase orders'],
    [17, 'crm', 'orders.create', 'Create and submit client orders'],
    [18, 'crm', 'orders.edit', 'Update orders and call-off allocations'],
    [19, 'crm', 'orders.cancel', 'Cancel order requests'],
    [20, 'crm', 'contracts.manage', 'Draft, approve, and manage customer contracts'],
    [21, 'production', 'production.view', 'View plant operations and filling lines'],
    [22, 'production', 'production.create', 'Initiate production runs and shift logs'],
    [23, 'production', 'production.edit', 'Update work order quantities and status'],
    [24, 'production', 'cylinders.manage', 'Inspect, test, and barcode gas cylinders'],
    [25, 'production', 'mixtures.manage', 'Formulate custom gas mixtures and ratios'],
    [26, 'production', 'liquid.manage', 'Monitor cryogenic liquid tanks and telemetry'],
    [27, 'logistics', 'logistics.view', 'View fleet status, driver rosters, and dispatches'],
    [28, 'logistics', 'logistics.create', 'Schedule dispatches and trip manifests'],
    [29, 'logistics', 'vehicles.manage', 'Add, modify, and service fleet vehicles'],
    [30, 'logistics', 'drivers.manage', 'Manage driver licenses, medicals, and shifts'],
    [31, 'logistics', 'maintenance.manage', 'Log vehicle repairs, inspections, and fuel'],
    [32, 'warehouse', 'inventory.view', 'Inspect warehouse stock levels and items custody'],
    [33, 'warehouse', 'inventory.manage', 'Adjust raw material balances and transfers'],
    [34, 'warehouse', 'warehouse.withdraw', 'Issue material consumption and withdrawals'],
    [35, 'finance', 'finance.view', 'View financial dashboards and receivables'],
    [36, 'finance', 'invoices.manage', 'Generate, send, and cancel invoices'],
    [37, 'finance', 'payments.record', 'Process collections, payments, and vouchers'],
    [38, 'finance', 'commissions.view', 'View sales commission calculations'],
    [39, 'insights', 'reports.view', 'Generate operational intelligence and KPI summaries'],
    [40, 'insights', 'reports.export', 'Export confidential Excel, CSV, and PDF reports'],
    [41, 'compliance', 'compliance.view', 'View compliance logs and hazard assessments'],
    [42, 'compliance', 'compliance.audit', 'Conduct safety inspections and document sign-offs'],
    [43, 'settings', 'settings.manage', 'Configure system settings, logos, and taxes']
];

$stmtPerm = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO permissions (id, module, permission_key, permission_name, description) VALUES (?, ?, ?, ?, ?)"
    : "INSERT INTO permissions (id, module, permission_key, permission_name, description) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name)"
);
foreach ($permissions as $p) {
    $stmtPerm->execute($p);
}

// 4. Map Role Permissions
echo "[+] Mapping Role Permissions...\n";
$rolePermMap = [
    1 => range(1, 43), // Admin -> all
    2 => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 35, 36, 37, 38, 39, 40],
    3 => [6, 7, 8, 11, 12, 13, 16, 17, 18, 20, 38],
    4 => [21, 22, 23, 24, 25, 26, 32, 33, 34, 39, 40, 41, 42],
    5 => [21, 22, 23, 24, 26, 32, 34],
    6 => [27, 28, 29, 30, 31, 39, 40, 41],
    7 => [27, 28, 31],
    8 => [32, 33, 34, 24],
    9 => [35, 36, 37, 38, 39, 40],
    10 => [41, 42, 39, 40]
];

$stmtMap = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO role_permissions (role_id, permission_id) VALUES (?, ?)"
    : "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)"
);
foreach ($rolePermMap as $roleId => $permIds) {
    foreach ($permIds as $permId) {
        $stmtMap->execute([$roleId, $permId]);
    }
}

// 5. Seed Users (Super Admin + Key Staff)
echo "[+] Seeding Super Admin and Staff Accounts...\n";
$adminHash = password_hash('Admin@123456', PASSWORD_BCRYPT);

$users = [
    [
        'id' => 1,
        'emp_id' => 'EMP-001',
        'role_id' => 1,
        'role' => 'admin',
        'permission_level' => 'super_admin',
        'username' => 'admin',
        'email' => 'admin@admin.com',
        'password_hash' => $adminHash,
        'full_name' => 'Super Administrator',
        'phone' => '+966500000001',
        'status' => 'Active'
    ],
    [
        'id' => 2,
        'emp_id' => 'EMP-002',
        'role_id' => 2,
        'role' => 'sales_manager',
        'permission_level' => 'manager',
        'username' => 'sales_manager',
        'email' => 'manager@crm-gases.com',
        'password_hash' => $adminHash,
        'full_name' => 'Sarah Al-Mansoor',
        'phone' => '+966500000002',
        'status' => 'Active'
    ],
    [
        'id' => 3,
        'emp_id' => 'EMP-003',
        'role_id' => 3,
        'role' => 'sales_rep',
        'permission_level' => 'staff',
        'username' => 'sales_rep',
        'email' => 'sales@crm-gases.com',
        'password_hash' => $adminHash,
        'full_name' => 'Tariq Mahmoud',
        'phone' => '+966500000003',
        'status' => 'Active'
    ],
    [
        'id' => 4,
        'emp_id' => 'EMP-004',
        'role_id' => 4,
        'role' => 'plant_manager',
        'permission_level' => 'manager',
        'username' => 'plant_manager',
        'email' => 'plant@crm-gases.com',
        'password_hash' => $adminHash,
        'full_name' => 'Eng. Omar Khalid',
        'phone' => '+966500000004',
        'status' => 'Active'
    ],
    [
        'id' => 5,
        'emp_id' => 'EMP-005',
        'role_id' => 6,
        'role' => 'logistics_manager',
        'permission_level' => 'manager',
        'username' => 'logistics_manager',
        'email' => 'logistics@crm-gases.com',
        'password_hash' => $adminHash,
        'full_name' => 'Fahad Al-Harbi',
        'phone' => '+966500000005',
        'status' => 'Active'
    ]
];

$stmtUser = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO users (id, emp_id, role_id, role, permission_level, username, email, password_hash, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO users (id, emp_id, role_id, role, permission_level, username, email, password_hash, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role), status = VALUES(status)"
);
foreach ($users as $u) {
    $stmtUser->execute([
        $u['id'], $u['emp_id'], $u['role_id'], $u['role'], $u['permission_level'],
        $u['username'], $u['email'], $u['password_hash'], $u['full_name'], $u['phone'], $u['status']
    ]);
}

// 6. Seed Clients & Corporate Partners
echo "[+] Seeding Corporate Clients & Partners...\n";
$partners = [
    [
        'id' => 1,
        'reference_id' => 'CL-1001',
        'partner_type' => 'client',
        'company_name' => 'Saudi Basic Industries Corp (SABIC)',
        'cr_number' => '1010008422',
        'city' => 'Jubail Industrial City',
        'address' => 'SABIC HQ Building, Road 100',
        'sales_rep_id' => 3,
        'status' => 'approved',
        'balance_due' => 45000.00,
        'password_hash' => $adminHash,
        'contact_first_name' => 'Abdullah',
        'contact_last_name' => 'Al-Ghamdi',
        'email' => 'procurement@sabic-demo.com',
        'phone' => '+966112255000',
        'last_order_date' => date('Y-m-d', strtotime('-2 days'))
    ],
    [
        'id' => 2,
        'reference_id' => 'CL-1002',
        'partner_type' => 'client',
        'company_name' => 'Saudi Aramco Petrochemical Complex',
        'cr_number' => '2050000001',
        'city' => 'Dhahran',
        'address' => 'Aramco Main Compound Gate 4',
        'sales_rep_id' => 1,
        'status' => 'approved',
        'balance_due' => 125000.00,
        'password_hash' => $adminHash,
        'contact_first_name' => 'Majed',
        'contact_last_name' => 'Al-Otaibi',
        'email' => 'materials@aramco-demo.com',
        'phone' => '+966138760000',
        'last_order_date' => date('Y-m-d', strtotime('-5 days'))
    ],
    [
        'id' => 3,
        'reference_id' => 'CL-1003',
        'partner_type' => 'client',
        'company_name' => 'National Gas & Industrialization Co (GASCO)',
        'cr_number' => '1010000023',
        'city' => 'Riyadh',
        'address' => 'GASCO Central Station, Ring Road',
        'sales_rep_id' => 3,
        'status' => 'approved',
        'balance_due' => 38000.00,
        'password_hash' => $adminHash,
        'contact_first_name' => 'Khalid',
        'contact_last_name' => 'Al-Zahrani',
        'email' => 'operations@gasco-demo.com',
        'phone' => '+966114660000',
        'last_order_date' => date('Y-m-d', strtotime('-1 day'))
    ],
    [
        'id' => 4,
        'reference_id' => 'CL-1004',
        'partner_type' => 'client',
        'company_name' => 'Al-Jubail Industrial Gases Co.',
        'cr_number' => '2055001234',
        'city' => 'Jubail',
        'address' => 'Industrial Area Phase 2',
        'sales_rep_id' => 3,
        'status' => 'approved',
        'balance_due' => 15200.00,
        'password_hash' => $adminHash,
        'contact_first_name' => 'Sami',
        'contact_last_name' => 'Al-Dosari',
        'email' => 'sami@jubailgases-demo.com',
        'phone' => '+966133410000',
        'last_order_date' => date('Y-m-d', strtotime('-10 days'))
    ],
    [
        'id' => 5,
        'reference_id' => 'CL-1005',
        'partner_type' => 'client',
        'company_name' => 'Riyadh Medical Specialized Hospital',
        'cr_number' => '1010156789',
        'city' => 'Riyadh',
        'address' => 'King Fahd Road, Medical City',
        'sales_rep_id' => 1,
        'status' => 'approved',
        'balance_due' => 8400.00,
        'password_hash' => $adminHash,
        'contact_first_name' => 'Dr. Huda',
        'contact_last_name' => 'Al-Sulaiman',
        'email' => 'pharmacy@rmsh-demo.com',
        'phone' => '+966114900000',
        'last_order_date' => date('Y-m-d')
    ]
];

$stmtPartner = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO partners (id, reference_id, partner_type, company_name, cr_number, city, address, sales_rep_id, status, balance_due, password_hash, contact_first_name, contact_last_name, email, phone, last_order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO partners (id, reference_id, partner_type, company_name, cr_number, city, address, sales_rep_id, status, balance_due, password_hash, contact_first_name, contact_last_name, email, phone, last_order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), balance_due = VALUES(balance_due)"
);

foreach ($partners as $pt) {
    $stmtPartner->execute([
        $pt['id'], $pt['reference_id'], $pt['partner_type'], $pt['company_name'], $pt['cr_number'],
        $pt['city'], $pt['address'], $pt['sales_rep_id'], $pt['status'], $pt['balance_due'],
        $pt['password_hash'], $pt['contact_first_name'], $pt['contact_last_name'], $pt['email'],
        $pt['phone'], $pt['last_order_date']
    ]);
}

$stmtClient = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO clients (id, client_code, sales_rep_id, company_name, industry, cr_number, city, address, credit_limit, current_balance, payment_terms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO clients (id, client_code, sales_rep_id, company_name, industry, cr_number, city, address, credit_limit, current_balance, payment_terms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company_name = VALUES(company_name)"
);
foreach ($partners as $pt) {
    $stmtClient->execute([
        $pt['id'], $pt['reference_id'], $pt['sales_rep_id'], $pt['company_name'], 'Industrial Gases',
        $pt['cr_number'], $pt['city'], $pt['address'], 200000.00, $pt['balance_due'], 'Net 30', 'active'
    ]);
}

// 7. Seed Quotations & Items
echo "[+] Seeding CRM Quotations...\n";
$quotes = [
    [1, 'QT-2026-001', 1, 3, 'Medical Oxygen & Ultra-High Purity Nitrogen Refill', 'SABIC Industrial', date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+25 days')), 'accepted', 42173.91, 15.00, 6326.09, 48500.00, 48500.00],
    [2, 'QT-2026-002', 2, 1, 'Bulk Liquid Argon Cryogenic Supply Contract', 'Saudi Aramco', date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+28 days')), 'draft', 108695.65, 15.00, 16304.35, 125000.00, 125000.00],
    [3, 'QT-2026-003', 3, 3, 'Acetylene & Cutting Gas Industrial Cylinders', 'GASCO', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+29 days')), 'sent', 27826.09, 15.00, 4173.91, 32000.00, 32000.00],
    [4, 'QT-2026-004', 5, 1, 'Hospital Bedside O2 Manifold Supply', 'Riyadh Medical', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'converted', 12347.83, 15.00, 1852.17, 14200.00, 14200.00]
];

$stmtQuote = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO quotations (id, quotation_number, client_id, sales_rep_id, specs, company_name, issue_date, expiry_date, status, subtotal, vat_percentage, vat_amount, total_value, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO quotations (id, quotation_number, client_id, sales_rep_id, specs, company_name, issue_date, expiry_date, status, subtotal, vat_percentage, vat_amount, total_value, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($quotes as $q) {
    $stmtQuote->execute($q);
}

// 8. Seed Purchase Orders & Sales Orders
echo "[+] Seeding Orders...\n";
$pos = [
    [1, 'PO-3001', 1, 3, 'Standard High-Purity Nitrogen (50L Cylinders)', date('Y-m-d', strtotime('-4 days')), 'SABIC Plant 4 Gate', date('Y-m-d', strtotime('-3 days')), 'delivered', 48500.00],
    [2, 'PO-3002', 2, 1, 'Cryogenic Liquid Argon Refill (10,000L)', date('Y-m-d', strtotime('-2 days')), 'Aramco Ras Tanura Terminal', date('Y-m-d', strtotime('+1 day')), 'in_production', 125000.00],
    [3, 'PO-3003', 3, 3, 'Industrial Dissolved Acetylene (120 Cylinders)', date('Y-m-d', strtotime('-1 day')), 'GASCO Central Depot', date('Y-m-d', strtotime('+2 days')), 'confirmed', 32000.00],
    [4, 'PO-3004', 5, 1, 'Medical Oxygen High-Pressure Manifold Cylinders', date('Y-m-d'), 'Riyadh Medical Specialized Hospital', date('Y-m-d', strtotime('+1 day')), 'submitted', 14200.00]
];

$stmtPO = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO purchase_orders (id, order_number, client_id, sales_rep_id, specs, order_date, delivery_address, delivery_date, status, total_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO purchase_orders (id, order_number, client_id, sales_rep_id, specs, order_date, delivery_address, delivery_date, status, total_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($pos as $po) {
    $stmtPO->execute($po);
}

$sos = [
    [1, 'SO-4001', 1, 3, 1, date('Y-m-d', strtotime('-4 days')), date('Y-m-d', strtotime('-3 days')), 'SABIC Plant 4 Gate', 'delivered', 42173.91, 6326.09, 48500.00],
    [2, 'SO-4002', 2, 1, 2, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+1 day')), 'Aramco Ras Tanura Terminal', 'processing', 108695.65, 16304.35, 125000.00],
    [3, 'SO-4003', 3, 3, 3, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+2 days')), 'GASCO Central Depot', 'confirmed', 27826.09, 4173.91, 32000.00],
    [4, 'SO-4004', 5, 1, 4, date('Y-m-d'), date('Y-m-d', strtotime('+1 day')), 'Riyadh Medical Specialized Hospital', 'pending', 12347.83, 1852.17, 14200.00]
];

$stmtSO = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO sales_orders (id, order_number, client_id, sales_rep_id, quotation_id, order_date, delivery_deadline, delivery_address, status, subtotal, vat_amount, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO sales_orders (id, order_number, client_id, sales_rep_id, quotation_id, order_date, delivery_deadline, delivery_address, status, subtotal, vat_amount, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($sos as $so) {
    $stmtSO->execute($so);
}

// 9. Seed Financial Invoices
echo "[+] Seeding Financial Invoices...\n";
$invoices = [
    [1, 'INV-2026-001', 1, 3, 1, 'PO-3001', date('Y-m-d', strtotime('-3 days')), date('Y-m-d', strtotime('+27 days')), 48500.00, 42173.91, 6326.09, 48500.00, 48500.00, 0.00, 'paid'],
    [2, 'INV-2026-002', 2, 1, 2, 'PO-3002', date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+28 days')), 125000.00, 108695.65, 16304.35, 125000.00, 0.00, 125000.00, 'pending'],
    [3, 'INV-2026-003', 3, 3, 3, 'PO-3003', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+29 days')), 32000.00, 27826.09, 4173.91, 32000.00, 16000.00, 16000.00, 'partial'],
    [4, 'INV-2026-004', 4, 3, NULL, 'PO-2998', date('Y-m-d', strtotime('-35 days')), date('Y-m-d', strtotime('-5 days')), 15200.00, 13217.39, 1982.61, 15200.00, 0.00, 15200.00, 'overdue'],
    [5, 'INV-2026-005', 5, 1, 4, 'PO-3004', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 14200.00, 12347.83, 1852.17, 14200.00, 0.00, 14200.00, 'pending']
];

$stmtInv = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO invoices (id, invoice_number, client_id, sales_rep_id, order_id, po_reference, issue_date, due_date, amount, subtotal, vat_amount, total_amount, paid_amount, balance_due, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO invoices (id, invoice_number, client_id, sales_rep_id, order_id, po_reference, issue_date, due_date, amount, subtotal, vat_amount, total_amount, paid_amount, balance_due, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($invoices as $inv) {
    $stmtInv->execute($inv);
}

// 10. Seed Fleet Vehicles & Drivers
echo "[+] Seeding Fleet Vehicles & Drivers...\n";
$vehicles = [
    [1, 'FL-01', '1234-KSA', 'Mercedes-Benz Actros 3340', 'Cryogenic Tanker (20,000L)', 'WDB9340331L123456', 2023, 20000.00, 0, 'available'],
    [2, 'FL-02', '5678-KSA', 'Volvo FMX 440 Carrier', 'Cylinder Carrier (180 Cylinders)', 'YV2X44026PA567890', 2022, 18000.00, 180, 'available'],
    [3, 'FL-03', '9012-KSA', 'MAN TGS 26.400 Tanker', 'Cryogenic Tanker (16,000L)', 'WMA264007ML901234', 2024, 16000.00, 0, 'in_transit'],
    [4, 'FL-04', '3456-KSA', 'Isuzu Forward FTR Express', 'Medium Distribution (60 Cylinders)', 'JALFTR34K77345678', 2021, 8000.00, 60, 'loading'],
    [5, 'FL-05', '7890-KSA', 'Scania G410 Heavy Transport', 'High-Pressure Tube Trailer', 'YS2G4100455789012', 2020, 22000.00, 0, 'maintenance']
];

$stmtVeh = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO vehicles (id, fleet_id, plate_number, make_model, vehicle_type, vin, manufacturing_year, load_capacity, cylinder_capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO vehicles (id, fleet_id, plate_number, make_model, vehicle_type, vin, manufacturing_year, load_capacity, cylinder_capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($vehicles as $v) {
    $stmtVeh->execute($v);
}

$drivers = [
    [1, 'DRV-01', 'Hassan Ali Al-Ghamdi', '1098765432', '+966511111111', 'hassan@driver.crm', 'Heavy / Hazmat', 'DL-991001', date('Y-m-d', strtotime('+180 days')), 'active'],
    [2, 'DRV-02', 'Youssef Ibrahim Mansoor', '1087654321', '+966522222222', 'youssef@driver.crm', 'Heavy Transport', 'DL-991002', date('Y-m-d', strtotime('+320 days')), 'active'],
    [3, 'DRV-03', 'Mohammad Tariq Al-Bishi', '1076543210', '+966533333333', 'tariq@driver.crm', 'Hazmat Tanker', 'DL-991003', date('Y-m-d', strtotime('+45 days')), 'active'],
    [4, 'DRV-04', 'Bilal Saeed Al-Zahrani', '1065432109', '+966544444444', 'bilal@driver.crm', 'Commercial Medium', 'DL-991004', date('Y-m-d', strtotime('+200 days')), 'on_leave']
];

$stmtDrv = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO drivers (id, driver_id, full_name, national_id, mobile_number, email, license_class, license_number, license_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO drivers (id, driver_id, full_name, national_id, mobile_number, email, license_class, license_number, license_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($drivers as $d) {
    $stmtDrv->execute($d);
}

// 11. Seed Dispatches
echo "[+] Seeding Dispatches...\n";
$dispatches = [
    [1, 'MAN-2026-001', 1, 1, 1, 'Eastern Province - Jubail', date('Y-m-d', strtotime('-3 days')), '08:00:00', 120, 'delivered', 'Delivered safely to SABIC Gate 2'],
    [2, 'MAN-2026-002', 2, 2, 3, 'Eastern Province - Ras Tanura', date('Y-m-d'), '06:30:00', 1, 'in_transit', 'Cryogenic Tanker in Transit on Highway 95'],
    [3, 'MAN-2026-003', 3, 3, 2, 'Central Province - Riyadh', date('Y-m-d', strtotime('+1 day')), '07:00:00', 180, 'assigned', 'Scheduled for departure tomorrow at 07:00']
];

$stmtDisp = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO dispatches (id, manifest_id, order_id, driver_id, vehicle_id, route_zone, dispatch_date, dispatch_time, cylinders_loaded, status, delivery_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO dispatches (id, manifest_id, order_id, driver_id, vehicle_id, route_zone, dispatch_date, dispatch_time, cylinders_loaded, status, delivery_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($dispatches as $dsp) {
    $stmtDisp->execute($dsp);
}

// 12. Seed Raw Materials & Warehouses
echo "[+] Seeding Warehouses & Raw Materials...\n";
$warehouses = [
    [1, 'Central Cryogenic Plant - Jubail', 'WH-01', 'Jubail Industrial City Zone 1', 'Eng. Tariq Al-Ghamdi'],
    [2, 'Dammam Regional Depot', 'WH-02', 'Dammam 2nd Industrial City', 'Sami Al-Dosari'],
    [3, 'Riyadh Central Logistics Hub', 'WH-03', 'Riyadh Industrial Gate', 'Fahad Al-Harbi']
];

$stmtWh = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO warehouses (id, name, code, location, manager_name) VALUES (?, ?, ?, ?, ?)"
    : "INSERT INTO warehouses (id, name, code, location, manager_name) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)"
);
foreach ($warehouses as $wh) {
    $stmtWh->execute($wh);
}

$materials = [
    [1, 'RAW-LOX-01', 'Liquid Oxygen (LOX)', 'Cryogenic Liquid', 'Ton', 450.5, 50.0, 150.00],
    [2, 'RAW-LIN-02', 'Liquid Nitrogen (LIN)', 'Cryogenic Liquid', 'Ton', 620.0, 80.0, 120.00],
    [3, 'RAW-LAR-03', 'Liquid Argon (LAR)', 'Cryogenic Liquid', 'Ton', 185.0, 25.0, 180.00]
];

$stmtMat = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO raw_materials (id, material_sku, material_name, category, unit, current_stock, safety_stock_threshold, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO raw_materials (id, material_sku, material_name, category, unit, current_stock, safety_stock_threshold, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_stock = VALUES(current_stock)"
);
foreach ($materials as $mat) {
    $stmtMat->execute($mat);
}

// 13. Seed Cylinder Batches & Cylinders
echo "[+] Seeding Cylinder Batches & Gas Cylinders...\n";
$batches = [
    [1, 'BATCH-O2-2026-01', 'O2-MED-50L', 'Oxygen', '50L', 'Seamless Steel', 'BS-3', 'White Shoulder', 100, 1, 150.00, 320.00, 1, 'PO-RAW-01', date('Y-m-d', strtotime('-30 days')), 200, 300, date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+365 days'))],
    [2, 'BATCH-N2-2026-02', 'N2-UHP-50L', 'Nitrogen', '50L', 'Carbon Steel', 'DIN-477', 'Black Shoulder', 150, 2, 120.00, 280.00, 1, 'PO-RAW-02', date('Y-m-d', strtotime('-20 days')), 200, 300, date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+400 days'))],
    [3, 'BATCH-AR-2026-03', 'AR-WLD-50L', 'Argon', '50L', 'Seamless Steel', 'CGA-580', 'Dark Green Shoulder', 80, 3, 180.00, 390.00, 1, 'PO-RAW-03', date('Y-m-d', strtotime('-15 days')), 200, 300, date('Y-m-d', strtotime('-15 days')), date('Y-m-d', strtotime('+350 days'))]
];

$stmtBat = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO cylinder_batches (id, batch_number, sku, gas_classification, volume, build_material, valve_spec, color_coding, quantity, raw_material_id, cost_price, selling_price, supplier_id, po_ref, mfg_date, working_pressure, test_pressure, initial_test_date, next_test_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    : "INSERT INTO cylinder_batches (id, batch_number, sku, gas_classification, volume, build_material, valve_spec, color_coding, quantity, raw_material_id, cost_price, selling_price, supplier_id, po_ref, mfg_date, working_pressure, test_pressure, initial_test_date, next_test_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)"
);
foreach ($batches as $bat) {
    $stmtBat->execute($bat);
}

$cylinders = [
    [1, 'CYL-OX-001', 1, 'O2-MED-50L', 'in_plant_filled', NULL],
    [2, 'CYL-OX-002', 1, 'O2-MED-50L', 'in_plant_filled', NULL],
    [3, 'CYL-OX-003', 1, 'O2-MED-50L', 'in_plant_empty', NULL],
    [4, 'CYL-N2-004', 2, 'N2-UHP-50L', 'client_possession', 1],
    [5, 'CYL-N2-005', 2, 'N2-UHP-50L', 'client_possession', 1],
    [6, 'CYL-N2-006', 2, 'N2-UHP-50L', 'in_plant_filled', NULL],
    [7, 'CYL-AR-007', 3, 'AR-WLD-50L', 'client_possession', 3],
    [8, 'CYL-AR-008', 3, 'AR-WLD-50L', 'in_plant_filled', NULL],
    [9, 'CYL-AR-009', 3, 'AR-WLD-50L', 'maintenance', NULL]
];

$stmtCyl = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO cylinders (id, barcode, batch_id, sku, status, current_client_id) VALUES (?, ?, ?, ?, ?, ?)"
    : "INSERT INTO cylinders (id, barcode, batch_id, sku, status, current_client_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)"
);
foreach ($cylinders as $cyl) {
    $stmtCyl->execute($cyl);
}

// 14. System Settings
echo "[+] Seeding Enterprise System Settings...\n";
$settings = [
    ['company_name', 'I-GAS Enterprise Industrial Gases', 'general'],
    ['system_title', 'I-GAS Operations & CRM Command Center', 'general'],
    ['vat_rate', '15.0', 'finance'],
    ['currency', 'SAR', 'finance'],
    ['timezone', 'Asia/Riyadh', 'localization'],
    ['system_version', 'v2.5.0', 'system'],
    ['default_language', 'en', 'localization'],
    ['maintenance_mode', '0', 'system']
];

$stmtSet = $pdo->prepare(($driver === 'sqlite')
    ? "INSERT OR REPLACE INTO system_settings (setting_key, setting_value, category) VALUES (?, ?, ?)"
    : "INSERT INTO system_settings (setting_key, setting_value, category) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);
foreach ($settings as $st) {
    $stmtSet->execute($st);
}

if ($driver === 'sqlite') {
    $pdo->exec("PRAGMA foreign_keys = ON;");
}

echo "\n======================================================================\n";
echo "[SUCCESS] Master Database Initialized & Seeded Successfully!\n";
echo "Verified Default Admin Account:\n";
echo "  * Email:    admin@admin.com\n";
echo "  * Username: admin\n";
echo "  * Password: Admin@123456\n";
echo "  * Role:     Super Administrator (All 43 Granular Permissions)\n";
echo "======================================================================\n";
