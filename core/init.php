<?php
declare(strict_types=1);

/**
 * ============================================================================
 * I-GAS ENTERPRISE CRM & ERP — CENTRALIZED APPLICATION GATEWAY
 * ============================================================================
 * File: core/init.php (and core/db.php)
 * 
 * Responsibilities:
 * 1. Secure Database Connection (PDO with strict exceptions & UTF-8 mb4)
 * 2. Hardened Session Lifecycle (HttpOnly, SameSite, Secure flags)
 * 3. Centralized RBAC Engine (Roles, Granular Permissions, Custom Overrides)
 * 4. Strict Row-Level Security & Anti-IDOR (Sales Rep Client Isolation)
 * 5. Cryptographic CSRF Defense Engine
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// 1. HARDENED SESSION MANAGEMENT
// ----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ----------------------------------------------------------------------------
// 2. SECURE PDO DATABASE CONNECTION FACTORY
// ----------------------------------------------------------------------------

function get_db(): PDO {
    static $pdoInstance = null;

    if ($pdoInstance instanceof PDO) {
        return $pdoInstance;
    }

    $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
    $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'u596103429_crm_gases');
    $dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
    $dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

    $mysqlAttrInit = defined('\\Pdo\\Mysql::ATTR_INIT_COMMAND')
        ? \Pdo\Mysql::ATTR_INIT_COMMAND
        : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        $mysqlAttrInit               => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdoInstance = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdoInstance;
    } catch (PDOException $e) {
        // Fallback to unified system database configuration
        require_once dirname(__DIR__) . '/config/db.php';
        $pdoInstance = $GLOBALS['pdo'] ?? null;
        if (!$pdoInstance instanceof PDO) {
            $sqlitePath = dirname(__DIR__) . '/crm.sqlite';
            $pdoInstance = init_sqlite_pdo($sqlitePath, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $GLOBALS['pdo'] = $pdoInstance;
        }
        return $pdoInstance;
    }
}

// Export singleton reference for global procedural usage
$pdo = get_db();

// ----------------------------------------------------------------------------
// 3. CRYPTOGRAPHIC CSRF PROTECTION
// ----------------------------------------------------------------------------

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Security Error: CSRF token verification failed.']);
            } else {
                render_error_page(403, 'CSRF Verification Failed', 'The security token for this form submission is missing or invalid. Please refresh and try again.');
            }
            exit;
        }
    }
}

// ----------------------------------------------------------------------------
// 4. AUTHENTICATION & IDENTITY HELPERS
// ----------------------------------------------------------------------------

function is_authenticated(): bool {
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function get_current_user_id(): ?int {
    return is_authenticated() ? (int)$_SESSION['user_id'] : null;
}

function get_current_user_name(): string {
    return $_SESSION['user_name'] ?? 'Authorized User';
}

function get_current_user_email(): string {
    return $_SESSION['user_email'] ?? '';
}

function get_current_user_role(): string {
    return strtolower($_SESSION['role'] ?? '');
}

function require_authenticated(): void {
    if (!is_authenticated()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        $loginUrl = app_url('auth/login.php');
        header("Location: {$loginUrl}");
        exit;
    }
}

function is_admin(?PDO $pdo = null): bool {
    $role = get_current_user_role();
    return in_array($role, ['admin', 'super_admin', 'super admin', 'administrator'], true);
}

function is_sales_manager(?PDO $pdo = null): bool {
    $role = get_current_user_role();
    return is_admin($pdo) || in_array($role, ['sales_manager', 'sales manager', 'sales vp'], true);
}

function is_sales_rep(?PDO $pdo = null): bool {
    $role = get_current_user_role();
    return in_array($role, ['sales_rep', 'sales rep', 'sales representative'], true);
}

function has_role($roles, ?PDO $pdo = null): bool {
    if (is_admin($pdo)) {
        return true;
    }
    $userRole = get_current_user_role();
    $rolesArray = is_array($roles) ? array_map('strtolower', $roles) : [strtolower((string)$roles)];
    return in_array($userRole, $rolesArray, true);
}

function require_role($roles, ?PDO $pdo = null): void {
    require_authenticated();
    if (!has_role($roles, $pdo)) {
        http_response_code(403);
        render_error_page(403, 'Access Denied', 'Your user role does not permit access to this operational feature.');
        exit;
    }
}

// ----------------------------------------------------------------------------
// 5. ROLE-BASED ACCESS CONTROL (RBAC) PERMISSION GUARDS
// ----------------------------------------------------------------------------

function has_permission(string $permission, ?PDO $pdo = null): bool {
    if (!is_authenticated()) {
        return false;
    }
    if (is_admin($pdo)) {
        return true;
    }

    $userId = get_current_user_id();
    $pdo = $pdo ?? get_db();

    // 1. Check direct user-level permission override
    try {
        $stmtUser = $pdo->prepare("
            SELECT up.granted
            FROM user_permissions up
            INNER JOIN permissions p ON p.id = up.permission_id
            WHERE up.user_id = ? AND p.permission_key = ?
            LIMIT 1
        ");
        $stmtUser->execute([$userId, $permission]);
        $override = $stmtUser->fetchColumn();
        if ($override !== false) {
            return (bool)$override;
        }

        // 2. Check role-assigned permissions
        $stmtRole = $pdo->prepare("
            SELECT 1
            FROM users u
            INNER JOIN role_permissions rp ON rp.role_id = u.role_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE u.id = ? AND p.permission_key = ?
            LIMIT 1
        ");
        $stmtRole->execute([$userId, $permission]);
        return (bool)$stmtRole->fetchColumn();
    } catch (PDOException $e) {
        error_log("[RBAC] Permission lookup failed: " . $e->getMessage());
        return false;
    }
}

function require_permission($permOrPdo, ?string $possiblePerm = null): void {
    require_authenticated();
    
    $permission = is_string($permOrPdo) ? $permOrPdo : (string)$possiblePerm;
    $pdo = ($permOrPdo instanceof PDO) ? $permOrPdo : null;

    if (!has_permission($permission, $pdo)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => "Access denied: Missing required permission '{$permission}'"]);
        } else {
            render_error_page(403, 'Permission Denied', "You do not have the required permission ('{$permission}') to perform this operation.");
        }
        exit;
    }
}

// ----------------------------------------------------------------------------
// 6. STRICT ROW-LEVEL SECURITY & IDOR PREVENTION (Sales Rep Client Isolation)
// ----------------------------------------------------------------------------

/**
 * Checks if the current authenticated user owns or is authorized to access a given client.
 * 
 * Rules:
 * - Administrators and Sales Managers can view/access ALL clients.
 * - Sales Representatives can ONLY view/access clients where clients.sales_rep_id == their user_id.
 *
 * @param int|string $clientId The ID of the client being inspected
 * @param PDO|null $pdo Database instance
 * @return bool True if authorized, False otherwise
 */
function is_owner($clientId, ?PDO $pdo = null): bool {
    if (!is_authenticated()) {
        return false;
    }
    
    // Administrative and managerial bypass
    if (is_admin($pdo) || is_sales_manager($pdo) || has_permission('clients.all', $pdo)) {
        return true;
    }

    $userId = get_current_user_id();
    $clientId = (int)$clientId;
    if ($clientId <= 0 || $userId === null) {
        return false;
    }

    $pdo = $pdo ?? get_db();
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM clients WHERE id = ? AND sales_rep_id = ? LIMIT 1");
        $stmt->execute([$clientId, $userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("[Row-Level Security] Ownership check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Strictly enforces that the logged-in user has authorization to access the given client.
 * If unauthorized, immediately terminates the request with HTTP 403 Forbidden.
 */
function require_client_access($clientId, ?PDO $pdo = null): void {
    require_authenticated();
    if (!is_owner($clientId, $pdo)) {
        http_response_code(403);
        $userId = get_current_user_id();
        error_log("[SECURITY ALERT] IDOR Attempt blocked: User ID {$userId} tried unauthorized access to Client ID {$clientId}.");

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access Denied: You are not authorized to view or manage this client account.']);
        } else {
            render_error_page(403, 'Unauthorized Record Access', 'Security Exception: You do not have permission to access records belonging to another Sales Representative.');
        }
        exit;
    }
}

/**
 * Multi-entity ownership verification for Quotations, Sales Orders, Consumptions, and Invoices.
 */
function check_record_ownership(PDO $pdo, string $entity, $recordId, ?int $userId = null): bool {
    $userId = $userId ?? get_current_user_id();
    if ($userId === null) {
        return false;
    }
    if (is_admin($pdo) || is_sales_manager($pdo)) {
        return true;
    }

    $tableMap = [
        'client'      => ['table' => 'clients',      'rep_col' => 'sales_rep_id', 'pk' => 'id'],
        'quotation'   => ['table' => 'quotations',   'rep_col' => 'sales_rep_id', 'pk' => 'id'],
        'sales_order' => ['table' => 'sales_orders', 'rep_col' => 'sales_rep_id', 'pk' => 'id'],
        'consumption' => ['table' => 'consumptions', 'rep_col' => 'sales_rep_id', 'pk' => 'id'],
        'invoice'     => ['table' => 'invoices',     'rep_col' => 'sales_rep_id', 'pk' => 'id']
    ];

    if (!isset($tableMap[$entity])) {
        return false;
    }

    $config = $tableMap[$entity];
    $stmt = $pdo->prepare("SELECT 1 FROM {$config['table']} WHERE {$config['pk']} = ? AND {$config['rep_col']} = ? LIMIT 1");
    $stmt->execute([$recordId, $userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Returns an array of client IDs assigned to the current user, or NULL if unrestricted (Admin/Manager).
 */
function get_accessible_client_ids(?PDO $pdo = null): ?array {
    if (is_admin($pdo) || is_sales_manager($pdo) || has_permission('clients.all', $pdo)) {
        return null; // Null indicates unrestricted access
    }

    $userId = get_current_user_id();
    if ($userId === null) {
        return [];
    }

    $pdo = $pdo ?? get_db();
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE sales_rep_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ----------------------------------------------------------------------------
// 7. PORTABLE URL & ROUTING UTILITIES
// ----------------------------------------------------------------------------

function app_base_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $parts = explode('/', trim($script, '/'));
    $baseParts = [];
    foreach ($parts as $part) {
        if (in_array($part, ['modules', 'auth', 'includes', 'config', 'core', 'tests', 'database'])) {
            break;
        }
        if (substr($part, -4) === '.php') {
            break;
        }
        $baseParts[] = $part;
    }
    $basePath = !empty($baseParts) ? '/' . implode('/', $baseParts) : '';
    return rtrim($basePath, '/');
}

function app_url(string $path = ''): string {
    $base = app_base_url();
    $cleanPath = ltrim($path, '/');
    return empty($cleanPath) ? ($base ?: '/') : ($base ? "{$base}/{$cleanPath}" : "/{$cleanPath}");
}

function redirect(string $path): void {
    $target = (preg_match('#^https?://#i', $path)) ? $path : app_url($path);
    header("Location: " . $target);
    exit;
}

// ----------------------------------------------------------------------------
// 8. ERROR TEMPLATE RENDERER
// ----------------------------------------------------------------------------

function render_error_page(int $statusCode, string $title, string $message): void {
    http_response_code($statusCode);
    ?>
    <!DOCTYPE html>
    <html lang="en" class="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | I-GAS Security</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
        <style>body { font-family: 'IBM Plex Sans', sans-serif; background: #071018; color: #f1f5f9; }</style>
    </head>
    <body class="flex items-center justify-center min-h-screen p-6 bg-[#071018] text-slate-100">
        <div class="max-w-md w-full bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-md p-8 text-center shadow-lg">
            <div class="w-12 h-12 rounded-full bg-rose-950/40 border border-rose-800/60 text-rose-400 flex items-center justify-center mx-auto mb-4 font-mono font-bold text-lg">
                <?= $statusCode ?>
            </div>
            <h1 class="text-xl font-bold mb-2 tracking-tight text-white"><?= htmlspecialchars($title) ?></h1>
            <p class="text-sm text-slate-400 mb-6 leading-relaxed"><?= htmlspecialchars($message) ?></p>
            <div class="flex gap-3 justify-center">
                <a href="<?= app_url() ?>" class="px-4 py-2 bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold uppercase tracking-wider rounded-sm transition-colors">
                    Dashboard
                </a>
                <button onclick="history.back()" class="px-4 py-2 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 text-xs font-semibold uppercase tracking-wider rounded-sm transition-colors">
                    Back
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
}
