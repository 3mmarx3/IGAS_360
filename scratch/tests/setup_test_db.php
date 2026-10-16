<?php
declare(strict_types=1);

$dbPath = __DIR__ . '/test_crm.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO("sqlite:{$dbPath}", null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pdo->exec("PRAGMA foreign_keys = ON;");

// Create schema
$pdo->exec("
CREATE TABLE roles (
    id INTEGER PRIMARY KEY,
    role_key TEXT NOT NULL UNIQUE,
    role_name TEXT NOT NULL,
    description TEXT,
    is_system INTEGER DEFAULT 0
);

CREATE TABLE permissions (
    id INTEGER PRIMARY KEY,
    module TEXT NOT NULL,
    permission_key TEXT NOT NULL UNIQUE,
    permission_name TEXT NOT NULL,
    description TEXT
);

CREATE TABLE role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_permissions (
    user_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    granted INTEGER DEFAULT 1,
    PRIMARY KEY (user_id, permission_id)
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    emp_id TEXT UNIQUE,
    role_id INTEGER,
    role TEXT NOT NULL,
    permission_level TEXT,
    username TEXT UNIQUE,
    email TEXT UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    phone TEXT,
    status TEXT DEFAULT 'Active'
);

CREATE TABLE partners (
    id INTEGER PRIMARY KEY,
    reference_id TEXT UNIQUE,
    partner_type TEXT NOT NULL,
    company_name TEXT NOT NULL,
    cr_number TEXT,
    entity_type TEXT DEFAULT 'Corporate',
    sales_rep_id TEXT,
    assigned_to INTEGER,
    status TEXT DEFAULT 'approved',
    balance_due REAL DEFAULT 0,
    password_hash TEXT,
    contact_first_name TEXT,
    contact_last_name TEXT,
    email TEXT,
    last_order_date TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quotations (
    id INTEGER PRIMARY KEY,
    quotation_number TEXT UNIQUE,
    client_id INTEGER,
    sales_rep_id INTEGER,
    total_value REAL DEFAULT 0,
    status TEXT DEFAULT 'draft',
    specs TEXT DEFAULT 'Standard',
    issue_date TEXT,
    expiry_date TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE purchase_orders (
    id INTEGER PRIMARY KEY,
    order_number TEXT UNIQUE,
    client_id INTEGER,
    sales_rep_id TEXT,
    status TEXT DEFAULT 'processing',
    total_value REAL DEFAULT 0,
    order_date TEXT
);

CREATE TABLE purchase_order_items (
    id INTEGER PRIMARY KEY,
    purchase_order_id INTEGER,
    item_name TEXT,
    qty REAL,
    unit TEXT,
    unit_price REAL,
    line_total REAL
);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY,
    invoice_number TEXT UNIQUE,
    client_id INTEGER,
    sales_rep_id INTEGER,
    po_reference TEXT DEFAULT 'PO-3001',
    amount REAL DEFAULT 0,
    status TEXT DEFAULT 'unpaid',
    issue_date TEXT,
    due_date TEXT
);

CREATE TABLE vehicle_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fleet_id TEXT,
    event_type TEXT,
    event_date TEXT,
    event_time TEXT,
    odometer INTEGER,
    fuel_liters REAL,
    event_cost REAL,
    logged_by TEXT,
    description TEXT
);

CREATE TABLE vehicles (
    fleet_id TEXT PRIMARY KEY,
    plate_number TEXT,
    make_model TEXT
);

CREATE TABLE drivers (
    driver_id TEXT PRIMARY KEY,
    full_name TEXT,
    mobile_number TEXT
);

CREATE TABLE dispatches (
    id INTEGER PRIMARY KEY,
    manifest_id TEXT UNIQUE,
    order_ref TEXT,
    vehicle_id TEXT,
    driver_id TEXT,
    status TEXT DEFAULT 'in_transit'
);

CREATE TABLE system_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT,
    category TEXT
);
");

// Seed Roles
$roles = [
    [1, 'admin', 'Super Administrator', 'Full unrestricted enterprise-wide control', 1],
    [2, 'sales_manager', 'Sales Manager', 'Manages sales team, approvals, pricing', 1],
    [3, 'sales_rep', 'Sales Representative', 'Direct management of assigned clients', 1],
    [4, 'plant_manager', 'Plant & Production Manager', 'Full control over plant output', 1],
    [5, 'shift_supervisor', 'Production Shift Supervisor', 'Daily shift logs, inspections', 1],
    [6, 'logistics_manager', 'Logistics & Fleet Manager', 'Oversees dispatch schedule', 1],
    [7, 'driver', 'Fleet Driver & Courier', 'Trip execution, delivery verification', 1],
    [8, 'warehouse_manager', 'Warehouse & Custody Manager', 'Oversees warehouse inventory', 1],
    [9, 'finance', 'Finance & Accountant', 'Manages cash flow, collections', 1],
    [10, 'compliance_officer', 'Compliance & Safety Auditor', 'Auditing, reporting, safety', 1]
];

$stmtR = $pdo->prepare("INSERT INTO roles (id, role_key, role_name, description, is_system) VALUES (?, ?, ?, ?, ?)");
foreach ($roles as $r) {
    $stmtR->execute($r);
}

// Seed Permissions
$permissions = [
    [1, 'users', 'users.view'],
    [2, 'users', 'users.create'],
    [3, 'users', 'users.edit'],
    [4, 'users', 'users.delete'],
    [5, 'users', 'roles.manage'],
    [6, 'crm', 'clients.view'],
    [7, 'crm', 'clients.create'],
    [8, 'crm', 'clients.edit'],
    [9, 'crm', 'clients.delete'],
    [10, 'crm', 'clients.all'],
    [11, 'crm', 'quotes.view'],
    [12, 'crm', 'quotes.create'],
    [13, 'crm', 'quotes.edit'],
    [14, 'crm', 'quotes.delete'],
    [15, 'crm', 'orders.view'],
    [16, 'crm', 'orders.create'],
    [17, 'crm', 'orders.edit'],
    [18, 'crm', 'orders.delete'],
    [19, 'finance', 'invoices.view'],
    [20, 'finance', 'invoices.manage'],
    [21, 'finance', 'finance.view'],
    [22, 'logistics', 'logistics.view'],
    [23, 'logistics', 'logistics.create'],
    [24, 'logistics', 'logistics.edit'],
    [25, 'logistics', 'logistics.delete'],
    [26, 'production', 'production.view'],
    [27, 'warehouse', 'warehouse.view'],
    [28, 'reports', 'reports.view']
];

$stmtP = $pdo->prepare("INSERT INTO permissions (id, module, permission_key, permission_name, description) VALUES (?, ?, ?, ?, '')");
foreach ($permissions as $p) {
    $stmtP->execute([$p[0], $p[1], $p[2], $p[2]]);
}

// Seed Role Permissions:
// Admin (role 1) has all
$stmtRP = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
for ($pid = 1; $pid <= 28; $pid++) {
    $stmtRP->execute([1, $pid]);
}
// Sales Manager (role 2) has clients.*, quotes.*, orders.*, invoices.*, reports.view
foreach ([6,7,8,9,10, 11,12,13,14, 15,16,17,18, 19,20, 28] as $pid) {
    $stmtRP->execute([2, $pid]);
}
// Sales Rep (role 3) has clients.view, clients.create, clients.edit, quotes.view, quotes.create, orders.view, orders.create, invoices.view
foreach ([6,7,8, 11,12, 15,16, 19] as $pid) {
    $stmtRP->execute([3, $pid]);
}
// Logistics Manager (role 6) has logistics.*
foreach ([22, 23, 24, 25] as $pid) {
    $stmtRP->execute([6, $pid]);
}

// Seed Users
// 1. Admin (id=1)
// 2. Sales Rep Tariq (id=2, role=sales_rep)
// 3. Sales Rep Ahmed (id=3, role=sales_rep)
// 4. Sales Rep Fahad (id=4, role=sales_rep)
// 5. Suspended Rep (id=5, role=sales_rep, status=Suspended)
$hash = password_hash('Secret123!', PASSWORD_DEFAULT);
$stmtU = $pdo->prepare("INSERT INTO users (id, emp_id, role_id, role, permission_level, username, email, password_hash, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtU->execute([1, 'EMP-ADM-001', 1, 'admin', 'Admin', 'admin', 'admin@igas.com', $hash, 'System Administrator', '+966500000001', 'Active']);
$stmtU->execute([2, 'EMP-REP-001', 3, 'sales_rep', 'Edit', 'tariq', 'tariq@igas.com', $hash, 'Tariq Mansoor', '+966500000002', 'Active']);
$stmtU->execute([3, 'EMP-REP-002', 3, 'sales_rep', 'Edit', 'ahmed', 'ahmed@igas.com', $hash, 'Ahmed Albana', '+966500000003', 'Active']);
$stmtU->execute([4, 'EMP-REP-003', 3, 'sales_rep', 'Edit', 'fahad', 'fahad@igas.com', $hash, 'Fahad Al-Otaibi', '+966500000004', 'Active']);
$stmtU->execute([5, 'EMP-REP-004', 3, 'sales_rep', 'Edit', 'suspended_user', 'suspended@igas.com', $hash, 'Suspended Staff', '+966500000005', 'Suspended']);

// Seed Partners (Clients)
// Client 1 (id=1, owned by rep 3 Ahmed)
// Client 2 (id=2, owned by rep 4 Fahad)
// Partner Client 3 (external partner user, id=10, reference_id='CLI-PARTNER')
$stmtPart = $pdo->prepare("INSERT INTO partners (id, reference_id, partner_type, company_name, sales_rep_id, status, balance_due, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtPart->execute([1, 'CLI-101', 'client', 'Alpha Chemicals', '3', 'approved', 1500.0, $hash]);
$stmtPart->execute([2, 'CLI-102', 'client', 'Beta Petrochemicals', '4', 'approved', 2500.0, $hash]);
$stmtPart->execute([10, 'CLI-PARTNER', 'client', 'Partner Corp', '3', 'approved', 0.0, $hash]);

// Seed Quotations
// Quote 1 owned by Rep 3
// Quote 2 owned by Rep 4
$stmtQ = $pdo->prepare("INSERT INTO quotations (id, quotation_number, client_id, sales_rep_id, total_value, status, issue_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtQ->execute([1, 'QUO-3001', 1, 3, 5000.0, 'sent', '2026-10-01', '2026-10-31']);
$stmtQ->execute([2, 'QUO-4001', 2, 4, 9000.0, 'sent', '2026-10-01', '2026-10-31']);

// Seed Purchase Orders
// Order 1 for Client 1 (Rep 3)
// Order 2 for Client 2 (Rep 4)
$stmtPO = $pdo->prepare("INSERT INTO purchase_orders (id, order_number, client_id, sales_rep_id, status, order_date) VALUES (?, ?, ?, ?, ?, ?)");
$stmtPO->execute([1, 'PO-3001', 1, '3', 'processing', '2026-10-05']);
$stmtPO->execute([2, 'PO-4001', 2, '4', 'processing', '2026-10-06']);

// Seed Invoices
// Inv 1 for Client 1 (Rep 3)
// Inv 2 for Client 2 (Rep 4)
$stmtInv = $pdo->prepare("INSERT INTO invoices (id, invoice_number, client_id, sales_rep_id, amount, status, issue_date, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtInv->execute([1, 'INV-3001', 1, 3, 1500.0, 'unpaid', '2026-10-05', '2026-11-05']);
$stmtInv->execute([2, 'INV-4001', 2, 4, 2500.0, 'unpaid', '2026-10-06', '2026-11-06']);

// Seed Vehicles & Drivers
$pdo->exec("INSERT INTO vehicles (fleet_id, plate_number, make_model) VALUES ('FL-01', '1234-ABC', 'Volvo Tanker')");
$pdo->exec("INSERT INTO drivers (driver_id, full_name, mobile_number) VALUES ('DRV-01', 'Hassan Ali', '+966511111111')");
$pdo->exec("INSERT INTO dispatches (id, manifest_id, order_ref, vehicle_id, driver_id, status) VALUES (1, 'MAN-01', 'PO-3001', 'FL-01', 'DRV-01', 'in_transit')");

echo "Test SQLite database successfully created at: {$dbPath}\n";
