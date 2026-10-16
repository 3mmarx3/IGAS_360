<?php
require_once __DIR__ . '/../../includes/auth.php';
require_staff();

$active_page = 'profile';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'User Management', 'My Profile'];

$flash_success = '';
$flash_error = '';

// Fetch current user from database
$user_id = $_SESSION['user_id'] ?? 1;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$db_user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $full_name = trim($_POST['full_name'] ?? '');
  $phone   = trim($_POST['phone'] ?? '');

  if (!empty($full_name)) {
    if ($db_user) {
      $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $update_stmt->execute([$full_name, $phone, $user_id]);
    }
    $_SESSION['user_name'] = $full_name;
    $flash_success = 'Operator profile specifications updated successfully.';
    // Refresh
    $stmt->execute([$user_id]);
    $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
  } else {
    $flash_error = 'Full name is required.';
  }
}

$user = [
  'emp_id'   => $db_user['emp_id'] ?? ($_SESSION['emp_id'] ?? 'OP-0482'),
  'full_name' => $db_user['full_name'] ?? ($_SESSION['user_name'] ?? 'Super Administrator'),
  'username'  => $db_user['username'] ?? ($_SESSION['username'] ?? 'admin'),
  'email'   => $db_user['email'] ?? ($_SESSION['user_email'] ?? 'admin@admin.com'),
  'phone'   => $db_user['phone'] ?? '+966 50 000 0001',
  'role'    => ucfirst(str_replace('_', ' ', $db_user['role'] ?? ($_SESSION['role'] ?? 'Administrator'))),
  'permission' => strtoupper($db_user['permission_level'] ?? ($_SESSION['permission_level'] ?? 'Super Admin')),
  'department' => 'Operations & Cryogenics',
  'location'  => 'Jeddah Industrial Plant 01',
  'timezone'  => 'Asia/Riyadh (GMT+3)',
  'language'  => 'English (US)',
  '2fa_active' => true,
  'last_login' => date('Y-m-d H:i') . ' UTC',
  'status'   => $db_user['status'] ?? 'Active'
];

$sessions = [
  ['device' => 'Primary Control Terminal (Station 01)', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'location' => 'Jeddah Plant — Control Room', 'time' => 'Current Session', 'status' => 'active'],
  ['device' => 'Field Tablet (Cryo Deck Alpha)', 'ip' => '10.0.4.18', 'location' => 'Filling Station Bay 3', 'time' => 'Today · 09:15', 'status' => 'logged_out'],
  ['device' => 'Logistics Dispatch Handheld', 'ip' => '10.0.8.42', 'location' => 'Fleet Staging Area', 'time' => 'Yesterday · 16:40', 'status' => 'logged_out'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Operator Profile | I-GAS Enterprise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    (function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
      if (savedLang === 'ar') {
        document.documentElement.classList.add('arabic-mode');
      } else {
        document.documentElement.classList.remove('arabic-mode');
      }
      void document.documentElement.offsetHeight;
      requestAnimationFrame(function() {
        document.documentElement.classList.add('layout-ready');
      });
    })();
  </script>
  <style>
    *, *::before, *::after {
      box-shadow: none !important;
      text-shadow: none !important;
      filter: none !important;
    }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[var(--surface-0)] text-[var(--ink)] font-sans">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[var(--surface-0)] overflow-hidden">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[var(--line)] bg-[var(--surface-1)] flex items-center px-4 md:px-8 gap-3 sm:gap-4 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
      <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-[var(--ink)]">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>System Nominal</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Facility: <?= htmlspecialchars($user['location']) ?></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Operator ID: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($user['emp_id']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Clearance: <span class="text-[var(--cryo-teal)] font-semibold"><?= htmlspecialchars($user['permission']) ?></span></span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">

      <!-- Page Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate('SYS-OPR-01', 'zinc') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]">Operator Identity & Station Clearance</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]">My Operator Profile</h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono">Manage personnel specifications, station credentials, and active terminal telemetry.</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono font-medium rounded-sm border border-[var(--line)] bg-[var(--surface-1)] text-[var(--ink)]">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Status: <?= htmlspecialchars($user['status']) ?>
          </span>
        </div>
      </div>

      <?php if (!empty($flash_success)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--cryo-teal)] bg-[var(--surface-1)] border-s-4 border-s-[var(--cryo-teal)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          <span><?= htmlspecialchars($flash_success) ?></span>
        </div>
        <span class="text-[10px] text-[var(--ink-muted)] uppercase">Status 200 OK</span>
      </div>
      <?php endif; ?>

      <?php if (!empty($flash_error)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--safety-orange)] bg-[var(--surface-1)] border-s-4 border-s-[var(--safety-orange)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('alert-triangle', 'w-4 h-4 text-[var(--safety-orange)]') ?>
          <span><?= htmlspecialchars($flash_error) ?></span>
        </div>
        <span class="text-[10px] text-[var(--safety-orange)] uppercase font-semibold">Validation Fault</span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        
        <!-- Left Column: Operator Badge & Security Clearance -->
        <div class="xl:col-span-1 flex flex-col gap-6">
          
          <!-- Operator Credential Card -->
          <div class="card-notched bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] p-6">
            <div class="flex flex-col items-center text-center">
              <div class="relative mb-4">
                <div class="w-24 h-24 rounded-sm border border-[var(--line)] bg-[var(--surface-2)] p-1 flex items-center justify-center overflow-hidden">
                  <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=0A1B26&color=19B5C9&rounded=false&size=128&bold=true" alt="Profile" class="w-full h-full object-cover">
                </div>
                <span class="absolute -bottom-1.5 -end-1.5 px-1.5 py-0.5 text-[9px] font-mono uppercase font-bold tracking-wider bg-[var(--cryo-teal)] text-white rounded-xs">
                  VERIFIED
                </span>
              </div>

              <h2 class="text-lg font-bold font-heading tracking-tight text-[var(--ink)]"><?= htmlspecialchars($user['full_name']) ?></h2>
              <p class="text-xs font-mono text-[var(--ink-muted)] mt-0.5"><?= htmlspecialchars($user['email']) ?></p>
              
              <div class="flex items-center gap-1.5 mt-3">
                <?= tag_plate($user['role'], 'teal') ?>
                <?= tag_plate($user['permission'], 'zinc') ?>
              </div>
            </div>

            <!-- Technical Spec Sheet -->
            <div class="w-full mt-6 pt-5 border-t border-[var(--line)] flex flex-col gap-2.5 text-xs font-mono">
              <div class="flex items-center justify-between py-1 border-b border-[var(--line-soft)]">
                <span class="text-[var(--ink-muted)] uppercase text-[11px]">Employee ID</span>
                <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($user['emp_id']) ?></span>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-[var(--line-soft)]">
                <span class="text-[var(--ink-muted)] uppercase text-[11px]">Department</span>
                <span class="text-[var(--ink)] font-medium"><?= htmlspecialchars($user['department']) ?></span>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-[var(--line-soft)]">
                <span class="text-[var(--ink-muted)] uppercase text-[11px]">Station / Facility</span>
                <span class="text-[var(--ink)] font-medium"><?= htmlspecialchars($user['location']) ?></span>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-[var(--line-soft)]">
                <span class="text-[var(--ink-muted)] uppercase text-[11px]">Cryo Clear Flag</span>
                <span class="text-emerald-500 font-semibold flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  AUTHORIZED
                </span>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-[var(--ink-muted)] uppercase text-[11px]">Last Sign-in</span>
                <span class="text-[var(--ink)]"><?= htmlspecialchars($user['last_login']) ?></span>
              </div>
            </div>
          </div>

          <!-- Station Security Procedures -->
          <div class="bg-[var(--surface-1)] border border-[var(--line)] rounded-sm p-5">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-[var(--line)]">
              <h3 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]">Security Procedures</h3>
              <?= icon('shield', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
            </div>
            <p class="text-xs text-[var(--ink-muted)] font-mono mb-4 leading-relaxed">
              Industrial workstation controls require standard cryptographic credential rotation every 90 days.
            </p>
            <div class="flex flex-col gap-2">
              <button type="button" class="w-full py-2 px-3 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center justify-between transition-colors">
                <span class="flex items-center gap-2">
                  <?= icon('key', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
                  <span>Rotate Password</span>
                </span>
                <span class="text-[10px] text-[var(--ink-muted)] font-mono">P-SHA256</span>
              </button>
              <button type="button" class="w-full py-2 px-3 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center justify-between transition-colors">
                <span class="flex items-center gap-2">
                  <?= icon('shield-check', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
                  <span>Passkey Security Key</span>
                </span>
                <span class="text-[10px] text-emerald-600 font-mono">CONFIGURED</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Right Column: Profile Form & Active Sessions -->
        <div class="xl:col-span-2 flex flex-col gap-6">
          
          <!-- Profile Form Card -->
          <form action="<?= app_url('modules/main/profile.php') ?>" method="POST" class="bg-[var(--surface-1)] border border-[var(--line)] rounded-sm overflow-hidden">
            <?= csrf_field() ?>
            
            <div class="px-6 py-4 border-b border-[var(--line)] flex items-center justify-between bg-[var(--surface-1)]">
              <div>
                <h2 class="text-sm font-bold font-heading tracking-tight text-[var(--ink)] uppercase">Personal & Workstation Specifications</h2>
                <p class="text-[11px] font-mono text-[var(--ink-muted)] mt-0.5">Primary personnel record and plant communication channels.</p>
              </div>
              <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 01</span>
            </div>

            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Operator Full Name <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="full_name" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Workstation Username
                  </label>
                  <input type="text" class="w-full bg-[var(--surface-2)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink-muted)] cursor-not-allowed" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Enterprise Email Address
                  </label>
                  <div class="relative">
                    <input type="email" class="w-full bg-[var(--surface-2)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink-muted)] cursor-not-allowed" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                  </div>
                  <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1">Managed via Active Directory / Central Identity.</p>
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Emergency Contact Phone
                  </label>
                  <input type="tel" name="phone" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($user['phone']) ?>">
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Interface Language
                  </label>
                  <select name="language" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="en" <?= $user['language'] === 'English (US)' ? 'selected' : '' ?>>English (LTR / Technical)</option>
                    <option value="ar">العربية (RTL / قياسي)</option>
                  </select>
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5">
                    Plant Timezone Reference
                  </label>
                  <select name="timezone" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="Asia/Riyadh" selected>Asia/Riyadh (AST · GMT+3)</option>
                    <option value="UTC">UTC (Universal Telemetry)</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="px-6 py-4 border-t border-[var(--line)] bg-[var(--surface-2)] flex items-center justify-between">
              <span class="text-[11px] font-mono text-[var(--ink-muted)]">All changes logged under audit protocol.</span>
              <button type="submit" class="btn-cta-orange px-5 py-2.5 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider transition-colors cursor-pointer">
                Save Profile Configuration
              </button>
            </div>
          </form>

          <!-- Authentication & Active Terminal Sessions -->
          <div class="bg-[var(--surface-1)] border border-[var(--line)] rounded-sm overflow-hidden">
            
            <div class="px-6 py-4 border-b border-[var(--line)] flex items-center justify-between">
              <div>
                <h2 class="text-sm font-bold font-heading tracking-tight text-[var(--ink)] uppercase">Security & Station Access Logs</h2>
                <p class="text-[11px] font-mono text-[var(--ink-muted)] mt-0.5">Two-factor telemetry authentication and active control terminal sessions.</p>
              </div>
              <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 02</span>
            </div>

            <div class="p-6 flex flex-col gap-6">
              
              <!-- 2FA Control -->
              <div class="flex items-center justify-between p-4 rounded-sm border border-[var(--line)] bg-[var(--surface-0)]">
                <div class="flex items-start gap-3">
                  <div class="p-2 rounded-sm bg-[var(--surface-1)] border border-[var(--line)] text-[var(--cryo-teal)]">
                    <?= icon('shield-check', 'w-5 h-5') ?>
                  </div>
                  <div>
                    <p class="text-xs font-mono font-semibold text-[var(--ink)] uppercase">Two-Factor Authentication (2FA)</p>
                    <p class="text-[11px] font-mono text-[var(--ink-muted)] mt-0.5">Enforces TOTP hardware token verification during plant terminal access.</p>
                  </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer" <?= $user['2fa_active'] ? 'checked' : '' ?>>
                  <div class="w-11 h-6 bg-[var(--surface-2)] border border-[var(--line)] peer-checked:bg-[var(--cryo-teal)] peer-checked:border-[var(--cryo-teal)] rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-[var(--line)] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
                </label>
              </div>

              <!-- Sessions Table -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink-muted)]">Active Workstation Telemetry</h3>
                  <span class="text-[10px] font-mono text-[var(--ink-muted)]"><?= count($sessions) ?> STATIONS RECORDED</span>
                </div>

                <div class="overflow-x-auto border border-[var(--line)] rounded-sm">
                  <table class="w-full text-left rtl:text-right border-collapse">
                    <thead>
                      <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                        <th class="px-4 py-2.5 font-semibold">Device / Terminal</th>
                        <th class="px-4 py-2.5 font-semibold">Network IP</th>
                        <th class="px-4 py-2.5 font-semibold">Last Contact</th>
                        <th class="px-4 py-2.5 font-semibold text-right rtl:text-left">Telemetry Status</th>
                      </tr>
                    </thead>
                    <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                      <?php foreach ($sessions as $session): ?>
                      <tr class="hover:bg-[var(--surface-2)] transition-colors">
                        <td class="px-4 py-3">
                          <p class="font-medium text-[var(--ink)]"><?= htmlspecialchars($session['device']) ?></p>
                          <p class="text-[10px] text-[var(--ink-muted)]"><?= htmlspecialchars($session['location']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-[var(--ink-muted)]"><?= htmlspecialchars($session['ip']) ?></td>
                        <td class="px-4 py-3 text-[var(--ink-muted)]"><?= htmlspecialchars($session['time']) ?></td>
                        <td class="px-4 py-3 text-right rtl:text-left">
                          <?php if($session['status'] === 'active'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 text-[10px] font-semibold">
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                              ACTIVE NOW
                            </span>
                          <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs border border-[var(--line)] bg-[var(--surface-2)] text-[var(--ink-muted)] text-[10px]">
                              DISCONNECTED
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>