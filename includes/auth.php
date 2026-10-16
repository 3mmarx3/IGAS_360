<?php
/**
 * I-GAS Enterprise — Centralized Authentication, Authorization & RBAC Engine
 *
 * Implements:
 * - Secure session management & cookie lifecycle
 * - Role-Based Access Control (RBAC) with granular permissions
 * - Cross-Site Request Forgery (CSRF) protection
 * - Record-level ownership checks (IDOR prevention)
 * - Dynamic domain / environment portable URL routing
 */

// 1. Session Initialization
function init_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $currentParams = session_get_cookie_params();
    session_set_cookie_params([
      'lifetime' => 0,
      'path'   => '/',
      'domain'  => $currentParams['domain'] ?? '',
      'secure'  => $isHttps,
      'httponly' => true,
      'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
  }
}

init_session();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ui_helpers.php';

// 2. Portable URL & Redirection Helpers
function app_base_url(): string {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  // Identify project root from script name
  // If inside /modules/... or /auth/... or /includes/...
  $parts = explode('/', trim($script, '/'));
  $baseParts = [];
  foreach ($parts as $part) {
    if (in_array($part, ['modules', 'auth', 'includes', 'config', 'tests', 'database'])) {
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

// 3. CSRF Protection
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
        echo json_encode(['error' => 'Security Error: Invalid or missing CSRF token.']);
      } else {
        render_error_page(403, 'CSRF Validation Failed', 'The security token for this form submission is missing or invalid. Please refresh the page and try again.');
      }
      exit;
    }
  }
}

// 4. Authentication Verification & Session Lifecycles
function is_authenticated(): bool {
  return !empty($_SESSION['user_id']) && !empty($_SESSION['account_type']);
}

function get_current_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function get_current_account_type(): ?string {
  return $_SESSION['account_type'] ?? null;
}

function is_staff(): bool {
  return is_authenticated() && get_current_account_type() === 'staff';
}

function is_partner(): bool {
  return is_authenticated() && get_current_account_type() === 'partner';
}

function get_current_user_role(): string {
  return $_SESSION['role'] ?? '';
}

function is_admin(): bool {
  if (!is_staff()) return false;
  $role = strtolower(str_replace([' ', '-'], '_', get_current_user_role()));
  return in_array($role, ['admin', 'super_admin', 'superadmin', 'administrator'], true);
}

function is_logged_in(): bool {
  return is_authenticated();
}

/**
 * Re-verifies user status against database on protected requests.
 * Destroys session if account was suspended or deleted.
 */
function refresh_user_status(PDO $pdo): bool {
  if (!is_authenticated()) {
    return false;
  }

  $userId = get_current_user_id();
  $accountType = get_current_account_type();

  try {
    if ($accountType === 'staff') {
      $stmt = $pdo->prepare("SELECT id, role, permission_level, status FROM users WHERE id = ? LIMIT 1");
      $stmt->execute([$userId]);
      $user = $stmt->fetch();

      if (!$user || $user['status'] === 'Suspended') {
        session_unset();
        session_destroy();
        return false;
      }

      // Sync live role changes
      $_SESSION['role'] = $user['role'];
      $_SESSION['permission_level'] = $user['permission_level'];
      return true;
    } elseif ($accountType === 'partner') {
      $stmt = $pdo->prepare("SELECT id, status FROM partners WHERE id = ? LIMIT 1");
      $stmt->execute([$userId]);
      $partner = $stmt->fetch();

      if (!$partner || in_array($partner['status'], ['suspended', 'rejected'])) {
        session_unset();
        session_destroy();
        return false;
      }
      return true;
    }
  } catch (\PDOException $e) {
    error_log("Status check query failed: " . $e->getMessage());
  }

  return true;
}

function require_auth(string $returnTo = ''): void {
  if (!is_authenticated()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['error' => 'Authentication required']);
      exit;
    }
    redirect('auth/login.php');
  }
}

function require_authenticated(): void {
  require_auth();
}

function require_staff(): void {
  require_auth();
  if (!is_staff()) {
    http_response_code(403);
    render_error_page(403, 'Staff Access Required', 'This portal section is restricted to company personnel.');
    exit;
  }
}

// 5. Role-Based Access Control (RBAC) & Granular Permissions
function has_permission($pdoOrPermission, ?string $permission = null, ?int $userId = null): bool {
  global $pdo;

  if ($pdoOrPermission instanceof PDO) {
    $db = $pdoOrPermission;
    $perm = (string)$permission;
  } elseif ($permission !== null && $pdoOrPermission === null) {
    $db = $pdo;
    $perm = (string)$permission;
  } else {
    $db = $pdo;
    $perm = (string)$pdoOrPermission;
    if (is_int($permission)) {
      $userId = $permission;
    }
  }

  if (empty($perm)) {
    return false;
  }

  if (!is_authenticated()) {
    return false;
  }

  // Super Admin bypass
  if (is_admin()) {
    return true;
  }

  // External partners do not possess staff permissions
  if (!is_staff()) {
    return false;
  }

  $uid = $userId ?: get_current_user_id();
  if (!$uid) {
    return false;
  }

  static $permCache = [];
  if (isset($permCache[$uid][$perm])) {
    return $permCache[$uid][$perm];
  }

  if ($db instanceof PDO) {
    try {
      // Check user-specific override first
      $stmtUser = $db->prepare("
        SELECT up.granted
        FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = ? AND p.permission_key = ?
        LIMIT 1
      ");
      $stmtUser->execute([$uid, $perm]);
      $userOverride = $stmtUser->fetchColumn();

      if ($userOverride !== false) {
        $permCache[$uid][$perm] = (bool)$userOverride;
        return (bool)$userOverride;
      }

      // Check role permissions via database
      $stmtRole = $db->prepare("
        SELECT 1
        FROM users u
        JOIN roles r ON (u.role_id = r.id OR u.role = r.role_key)
        JOIN role_permissions rp ON rp.role_id = r.id
        JOIN permissions p ON p.id = rp.permission_id
        WHERE u.id = ? AND p.permission_key = ?
        LIMIT 1
      ");
      $stmtRole->execute([$uid, $perm]);
      if ($stmtRole->fetchColumn()) {
        $permCache[$uid][$perm] = true;
        return true;
      }
    } catch (\PDOException $e) {
      // Fallback to role-based mapping if tables are missing
    }
  }

  $role = get_current_user_role();
  $allowed = fallback_permission_check($role, $perm);
  $permCache[$uid][$perm] = $allowed;
  return $allowed;
}

function fallback_permission_check(string $role, string $permission): bool {
  $normRole = strtolower(str_replace([' ', '-'], '_', $role));
  if (in_array($normRole, ['admin', 'super_admin', 'superadmin', 'administrator'], true)) return true;

  $map = [
    'sales_manager' => ['clients.*', 'quotations.*', 'orders.*', 'invoices.*', 'finance.view', 'reports.*', 'commissions.approve'],
    'sales_rep'   => ['clients.view', 'clients.create', 'clients.edit', 'quotations.view', 'quotations.create', 'quotations.edit', 'orders.view', 'orders.create', 'invoices.view', 'reports.view'],
    'production_manager' => ['production.*', 'warehouse.view', 'warehouse.withdraw', 'reports.*'],
    'production_supervisor' => ['production.view', 'production.create', 'production.edit', 'warehouse.view'],
    'logistics_manager' => ['logistics.*', 'orders.view', 'reports.*'],
    'logistics_assistant' => ['logistics.view', 'logistics.create', 'orders.view'],
    'warehouse_manager' => ['warehouse.*', 'production.view', 'reports.view'],
    'finance'    => ['invoices.*', 'finance.*', 'commissions.approve', 'clients.view', 'orders.view', 'reports.*'],
    'read_only'   => ['clients.view', 'quotations.view', 'orders.view', 'invoices.view', 'production.view', 'warehouse.view', 'logistics.view', 'reports.view']
  ];

  if (!isset($map[$normRole])) return false;

  foreach ($map[$normRole] as $pat) {
    if ($pat === $permission) return true;
    if (str_ends_with($pat, '.*')) {
      $prefix = substr($pat, 0, -1);
      if (str_starts_with($permission, $prefix)) return true;
    }
  }
  return false;
}

function require_permission($pdoOrPermission, ?string $permission = null): void {
  global $pdo;

  if ($pdoOrPermission instanceof PDO) {
    $db = $pdoOrPermission;
    $perm = (string)$permission;
  } elseif ($permission !== null && $pdoOrPermission === null) {
    $db = $pdo;
    $perm = (string)$permission;
  } else {
    $db = $pdo;
    $perm = (string)$pdoOrPermission;
  }

  require_staff();

  if ($db instanceof PDO && !refresh_user_status($db)) {
    redirect('auth/login.php');
  }

  if (!has_permission($db, $perm)) {
    http_response_code(403);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
      header('Content-Type: application/json');
      echo json_encode(['error' => "Access denied: Missing required permission [{$perm}]"]);
    } else {
      render_error_page(403, 'Permission Denied', "You do not have the required permission (<code>{$perm}</code>) to access this feature.");
    }
    exit;
  }
}

function has_role($pdoOrRoles, $rolesOrNone = null): bool {
  $roles = ($pdoOrRoles instanceof PDO) ? $rolesOrNone : $pdoOrRoles;
  if (!is_staff()) return false;
  if (is_admin()) return true;

  $currentRole = strtolower(str_replace([' ', '-'], '_', get_current_user_role()));
  $allowed = is_array($roles) ? $roles : [$roles];
  foreach ($allowed as $r) {
    $norm = strtolower(str_replace([' ', '-'], '_', (string)$r));
    if ($currentRole === $norm) return true;
  }
  return false;
}

function require_role($pdoOrRoles, $rolesOrNone = null): void {
  require_staff();
  if (!has_role($pdoOrRoles, $rolesOrNone)) {
    http_response_code(403);
    render_error_page(403, 'Access Denied', 'Your assigned role is not permitted to perform this action.');
    exit;
  }
}

function require_admin(): void {
  require_staff();
  if (!is_admin()) {
    http_response_code(403);
    render_error_page(403, 'Access Denied', 'Administrator privileges are required to view this area.');
    exit;
  }
}

// 6. Record-Level Ownership (IDOR Prevention)
function check_record_ownership($pdoOrEntity, $entityOrRecordId, $recordIdOrOwnerCol = null, $userIdOrIdCol = null): bool {
  global $pdo;

  if ($pdoOrEntity instanceof PDO) {
    $db = $pdoOrEntity;
    $entity = (string)$entityOrRecordId;
    $recordId = $recordIdOrOwnerCol;
    $userId = is_int($userIdOrIdCol) ? $userIdOrIdCol : null;
  } else {
    $db = $pdo;
    $entity = (string)$pdoOrEntity;
    $recordId = $entityOrRecordId;
    $userId = is_int($recordIdOrOwnerCol) ? $recordIdOrOwnerCol : null;
  }

  if (is_admin()) {
    return true;
  }

  if (!$db instanceof PDO) {
    return true;
  }

  $uid = $userId ?: get_current_user_id();
  $role = get_current_user_role();
  $normRole = strtolower(str_replace([' ', '-'], '_', $role));
  $accountType = get_current_account_type();

  try {
    if ($entity === 'client_profile' || $entity === 'clients' || $entity === 'partners') {
      if ($accountType === 'partner') {
        $stmt = $db->prepare("SELECT id, reference_id FROM partners WHERE id = ?");
        $stmt->execute([$uid]);
        $partnerRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($partnerRow) {
          return ($partnerRow['id'] == $recordId || $partnerRow['reference_id'] == $recordId);
        }
        return false;
      }
      if ($normRole === 'sales_rep') {
        $stmt = $db->prepare("SELECT sales_rep_id, assigned_to FROM partners WHERE reference_id = ? OR id = ?");
        $stmt->execute([$recordId, $recordId]);
        $partnerRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($partnerRow) {
          $repIds = array_filter(array_map('trim', explode(',', (string)($partnerRow['sales_rep_id'] ?? ''))));
          if (in_array((string)$uid, $repIds)) return true;
          if (!empty($partnerRow['assigned_to']) && (int)$partnerRow['assigned_to'] === $uid) return true;
        }
        return false;
      }
      return true;
    }

    if ($entity === 'invoices') {
      $stmt = $db->prepare("SELECT client_id, sales_rep_id FROM invoices WHERE id = ?");
      $stmt->execute([$recordId]);
      $invRow = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$invRow) return false;

      if ($accountType === 'partner') {
        return ((int)$invRow['client_id'] === $uid);
      }
      if ($normRole === 'sales_rep') {
        if (!empty($invRow['sales_rep_id']) && (int)$invRow['sales_rep_id'] === $uid) {
          return true;
        }
        // Check if client is assigned to this rep
        $stmtClient = $db->prepare("SELECT sales_rep_id, assigned_to FROM partners WHERE id = ?");
        $stmtClient->execute([$invRow['client_id']]);
        $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if ($clientRow) {
          $repIds = array_filter(array_map('trim', explode(',', (string)($clientRow['sales_rep_id'] ?? ''))));
          if (in_array((string)$uid, $repIds)) return true;
          if (!empty($clientRow['assigned_to']) && (int)$clientRow['assigned_to'] === $uid) return true;
        }
        return false;
      }
      return true;
    }

    if ($entity === 'quotations') {
      $stmt = $db->prepare("SELECT client_id, sales_rep_id FROM quotations WHERE id = ? OR quotation_number = ?");
      $stmt->execute([$recordId, $recordId]);
      $q = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$q) return false;

      if ($accountType === 'partner') {
        return ((int)$q['client_id'] === $uid);
      }
      if ($normRole === 'sales_rep') {
        return ((int)$q['sales_rep_id'] === $uid);
      }
      return true;
    }

    if ($entity === 'orders' || $entity === 'purchase_orders') {
      $stmt = $db->prepare("SELECT client_id, sales_rep_id FROM purchase_orders WHERE id = ? OR order_number = ?");
      $stmt->execute([$recordId, $recordId]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$order) return false;

      if ($accountType === 'partner') {
        return ((int)$order['client_id'] === $uid);
      }
      if ($normRole === 'sales_rep') {
        $reps = explode(',', (string)$order['sales_rep_id']);
        return in_array((string)$uid, $reps);
      }
      return true;
    }
  } catch (\PDOException $e) {
    error_log("Ownership check error: " . $e->getMessage());
    return false;
  }

  return true;
}

// 7. Security UI Error Rendering
function render_error_page(int $statusCode, string $title, string $message): void {
  $dashboardUrl = app_url('index.php');
  $loginUrl = app_url('auth/login.php');
  ?>
  <!DOCTYPE html>
  <html lang="en" dir="ltr" class="dark">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($statusCode . ' — ' . $title) ?> | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
    <style>body { font-family: 'IBM Plex Sans', sans-serif; background: #071018; }</style>
  </head>
  <body class="min-h-screen flex items-center justify-center p-4 bg-[#071018] text-slate-100">
    <div class="max-w-md w-full bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-md p-8 text-center shadow-lg">
      <div class="w-12 h-12 bg-rose-950/40 border border-rose-800/60 text-rose-400 rounded-full flex items-center justify-center mx-auto mb-4 font-mono font-bold text-lg">
        <?= $statusCode ?>
      </div>
      <h1 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($title) ?></h1>
      <p class="text-[13.5px] text-slate-400 mb-6"><?= $message ?></p>
      <div class="flex justify-center gap-3">
        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="px-4 py-2 bg-[#f97316] hover:bg-[#ea580c] text-white text-[13px] font-semibold uppercase tracking-wider rounded-sm transition-colors">Return to Dashboard</a>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="px-4 py-2 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 text-[13px] font-medium rounded-sm transition-colors">Switch Account</a>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// 8. Safe HTML Escaper
function e($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
