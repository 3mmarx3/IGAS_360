<?php
/**
 * Database Migration Runner
 * Usage: php database/migrate.php
 */

require_once __DIR__ . '/../config/db.php';

echo "Running I-GAS Enterprise RBAC & Schema Migration...\n";

try {
    $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pkType = ($driverName === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

    // 1. Roles Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `roles` (
            `id` {$pkType},
            `role_key` VARCHAR(50) NOT NULL UNIQUE,
            `role_name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "[+] Table 'roles' verified.\n";

    // 2. Permissions Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `permissions` (
            `id` {$pkType},
            `module` VARCHAR(50) NOT NULL,
            `permission_key` VARCHAR(100) NOT NULL UNIQUE,
            `permission_name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "[+] Table 'permissions' verified.\n";

    // 3. Role Permissions Pivot Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`)
        )
    ");
    echo "[+] Table 'role_permissions' verified.\n";

    // 4. User Permissions Overrides Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_permissions` (
            `user_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            `granted` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`user_id`, `permission_id`)
        )
    ");
    echo "[+] Table 'user_permissions' verified.\n";

    // 5. Add role_id to users if not present
    $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driverName === 'mysql') {
        $checkCol = $pdo->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'users' 
              AND COLUMN_NAME = 'role_id'
        ")->fetchColumn();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT(11) NULL AFTER role");
            echo "[+] Added column 'role_id' to 'users'.\n";
        }
    }

    // 6. Seed Roles
    $roles = [
        ['Admin', 'Super Administrator', 'Full unrestricted administrative access to all CRM & ERP modules', 1],
        ['Sales Manager', 'Sales Manager', 'Manages sales team, approvals, pricing, contracts, and clients', 1],
        ['Sales Rep', 'Sales Representative', 'Manages assigned clients, issues quotations, and tracks pipeline', 1],
        ['Production Manager', 'Production Manager', 'Full control over plant output, formulations, raw materials, and tanks', 1],
        ['Production Supervisor', 'Production Supervisor', 'Daily shift logs, cylinder inspections, and filling operations', 1],
        ['Logistics Manager', 'Logistics & Fleet Manager', 'Oversees dispatch schedule, vehicles, drivers, and maintenance', 1],
        ['Logistics Assistant', 'Logistics Assistant', 'Trip execution, mileage logging, and delivery verification', 1],
        ['Warehouse Manager', 'Warehouse & Custody Manager', 'Oversees warehouse inventory, stock receipts, and custody withdrawals', 1],
        ['Finance', 'Finance & Accountant', 'Manages cash flow, collections, invoices, and sales commissions', 1],
        ['Read Only', 'Read-Only Auditor', 'View-only access for auditing, reporting, and operational monitoring', 1]
    ];

    if ($driverName === 'sqlite') {
        $stmtRole = $pdo->prepare("INSERT OR IGNORE INTO roles (role_key, role_name, description, is_system) VALUES (?, ?, ?, ?)");
    } else {
        $stmtRole = $pdo->prepare("INSERT INTO roles (role_key, role_name, description, is_system) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description)");
    }
    foreach ($roles as $r) {
        $stmtRole->execute($r);
    }
    echo "[+] Seeded " . count($roles) . " system roles.\n";

    // 7. Seed Permissions
    $permissions = [
        // Users & Roles
        ['users', 'users.view', 'View Users', 'View staff and user accounts'],
        ['users', 'users.create', 'Create Users', 'Create new staff members'],
        ['users', 'users.edit', 'Edit Users', 'Edit staff profiles and permissions'],
        ['users', 'users.delete', 'Delete Users', 'Delete staff accounts'],
        ['users', 'roles.manage', 'Manage Roles & RBAC', 'Assign roles and configure system permissions'],

        // Clients & CRM
        ['clients', 'clients.view', 'View Clients', 'View client directory and profiles'],
        ['clients', 'clients.view_all', 'View All Clients', 'View all clients unrestricted by sales rep assignment'],
        ['clients', 'clients.create', 'Create Clients', 'Register new client accounts'],
        ['clients', 'clients.edit', 'Edit Clients', 'Edit existing client information and terms'],
        ['clients', 'clients.delete', 'Delete Clients', 'Remove client accounts'],

        // Quotations
        ['quotations', 'quotations.view', 'View Quotations', 'View quotations list and details'],
        ['quotations', 'quotations.create', 'Create Quotations', 'Create and send new price quotations'],
        ['quotations', 'quotations.edit', 'Edit Quotations', 'Edit existing draft quotations'],
        ['quotations', 'quotations.delete', 'Delete Quotations', 'Remove quotations from system'],
        ['quotations', 'quotations.approve', 'Approve Quotations', 'Approve and accept quotations for PO conversion'],

        // Orders
        ['orders', 'orders.view', 'View Orders', 'View purchase and sales orders'],
        ['orders', 'orders.create', 'Create Orders', 'Create new purchase orders or call-offs'],
        ['orders', 'orders.edit', 'Edit Orders', 'Update purchase orders and delivery details'],
        ['orders', 'orders.delete', 'Delete Orders', 'Cancel or delete orders'],
        ['orders', 'orders.approve', 'Approve Orders', 'Approve orders for production and dispatch'],

        // Invoices & Collections
        ['invoices', 'invoices.view', 'View Invoices', 'View invoices and account statements'],
        ['invoices', 'invoices.create', 'Create Invoices', 'Generate billing invoices'],
        ['invoices', 'invoices.edit', 'Edit Invoices', 'Modify invoice line items and status'],
        ['invoices', 'invoices.manage', 'Manage Collections', 'Record payments and reconcile receipt vouchers'],

        // Production & Gas Mixtures
        ['production', 'production.view', 'View Production', 'View raw materials, formulations, and shift logs'],
        ['production', 'production.create', 'Create Production Logs', 'Log shifts, register cylinder batches, create formulations'],
        ['production', 'production.edit', 'Edit Production Records', 'Edit shift logs, materials, and batch parameters'],
        ['production', 'production.delete', 'Delete Production Records', 'Delete formulations, batches, or raw material records'],

        // Warehouse & Custody
        ['warehouse', 'warehouse.view', 'View Warehouses', 'View warehouses and inventory balances'],
        ['warehouse', 'warehouse.create', 'Create Warehouses', 'Add new warehouse or custody locations'],
        ['warehouse', 'warehouse.edit', 'Edit Warehouse Items', 'Add inventory items and edit locations'],
        ['warehouse', 'warehouse.delete', 'Delete Warehouses', 'Delete warehouses and inventory entries'],
        ['warehouse', 'warehouse.withdraw', 'Withdraw & Transfer', 'Perform inventory withdrawals and inter-warehouse transfers'],

        // Logistics & Fleet
        ['logistics', 'logistics.view', 'View Fleet & Logistics', 'View vehicles, drivers, dispatches, and maintenance'],
        ['logistics', 'logistics.create', 'Create Logistics Records', 'Add vehicles, drivers, dispatches, and tickets'],
        ['logistics', 'logistics.edit', 'Edit Logistics Records', 'Update vehicle/driver details and renew documents'],
        ['logistics', 'logistics.delete', 'Delete Logistics Records', 'Delete vehicles, drivers, tickets, or dispatches'],

        // Finance & Reporting
        ['finance', 'finance.view', 'View Financial Reports', 'View cash flow, P&L, and balance metrics'],
        ['finance', 'finance.manage', 'Manage Financial Operations', 'Configure payment terms and cash flow settings'],
        ['finance', 'commissions.approve', 'Approve Commissions', 'Approve and pay sales team commissions'],
        ['reports', 'reports.view', 'View Reports', 'Access module reports and analytical dashboards'],
        ['reports', 'reports.export', 'Export Reports', 'Export reports to CSV / Excel'],

        // System Administration
        ['settings', 'settings.manage', 'Manage System Settings', 'Configure system parameters, backups, and security']
    ];

    if ($driverName === 'sqlite') {
        $stmtPerm = $pdo->prepare("INSERT OR IGNORE INTO permissions (module, permission_key, permission_name, description) VALUES (?, ?, ?, ?)");
    } else {
        $stmtPerm = $pdo->prepare("INSERT INTO permissions (module, permission_key, permission_name, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE permission_name = VALUES(permission_name), description = VALUES(description)");
    }
    foreach ($permissions as $p) {
        $stmtPerm->execute($p);
    }
    echo "[+] Seeded " . count($permissions) . " granular permissions.\n";

    // 8. Map Role Permissions
    $rolePermMap = [
        'Admin' => array_column($permissions, 1), // All permissions
        'Sales Manager' => [
            'clients.view', 'clients.view_all', 'clients.create', 'clients.edit', 'clients.delete',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete', 'quotations.approve',
            'orders.view', 'orders.create', 'orders.edit', 'orders.approve',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.manage',
            'finance.view', 'commissions.approve', 'reports.view', 'reports.export'
        ],
        'Sales Rep' => [
            'clients.view', 'clients.create', 'clients.edit',
            'quotations.view', 'quotations.create', 'quotations.edit',
            'orders.view', 'orders.create',
            'invoices.view', 'reports.view'
        ],
        'Production Manager' => [
            'production.view', 'production.create', 'production.edit', 'production.delete',
            'warehouse.view', 'warehouse.withdraw',
            'reports.view', 'reports.export'
        ],
        'Production Supervisor' => [
            'production.view', 'production.create', 'production.edit',
            'warehouse.view'
        ],
        'Logistics Manager' => [
            'logistics.view', 'logistics.create', 'logistics.edit', 'logistics.delete',
            'orders.view', 'reports.view', 'reports.export'
        ],
        'Logistics Assistant' => [
            'logistics.view', 'logistics.create', 'orders.view'
        ],
        'Warehouse Manager' => [
            'warehouse.view', 'warehouse.create', 'warehouse.edit', 'warehouse.delete', 'warehouse.withdraw',
            'production.view', 'reports.view', 'reports.export'
        ],
        'Finance' => [
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.manage',
            'finance.view', 'finance.manage', 'commissions.approve',
            'clients.view', 'orders.view', 'reports.view', 'reports.export'
        ],
        'Read Only' => [
            'clients.view', 'quotations.view', 'orders.view', 'invoices.view',
            'production.view', 'warehouse.view', 'logistics.view', 'reports.view'
        ]
    ];

    $roleRows = $pdo->query("SELECT id, role_key FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    $permRows = $pdo->query("SELECT permission_key, id FROM permissions")->fetchAll(PDO::FETCH_KEY_PAIR);

    $insRP = $pdo->prepare("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    if ($driverName === 'mysql') {
        $insRP = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    }

    foreach ($rolePermMap as $roleKey => $keys) {
        if (!isset($roleRows[$roleKey])) continue;
        $roleId = $roleRows[$roleKey];
        foreach ($keys as $k) {
            if (isset($permRows[$k])) {
                $insRP->execute([$roleId, $permRows[$k]]);
            }
        }
    }
    echo "[+] Mapped role permissions.\n";

    // 9. Backfill users role_id
    if ($driverName === 'mysql') {
        $pdo->exec("
            UPDATE users u
            JOIN roles r ON r.role_key = u.role
            SET u.role_id = r.id
            WHERE u.role_id IS NULL
        ");
        echo "[+] Synchronized users.role_id with roles table.\n";
    }

    echo "\n=== Migration completed successfully! ===\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
