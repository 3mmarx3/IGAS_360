<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ui_helpers.php';

header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Clear corrupted or partial session states
if (isset($_SESSION['account_type']) && empty($_SESSION['user_id'])) {
  session_unset();
}

// Redirect already authenticated users
if (is_authenticated()) {
  if (is_staff()) {
    redirect('index.php');
  } elseif (is_partner()) {
    if (isset($_SESSION['partner_type']) && $_SESSION['partner_type'] === 'client') {
      redirect('modules/crm_sales/client_profile.php?id=' . urlencode($_SESSION['reference_id'] ?? ''));
    } else {
      redirect('index.php');
    }
  }
}

$system_version = 'v3.0.0 (P&ID)';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Brute force protection: track attempts
  $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
  $_SESSION['last_attempt_time'] = $_SESSION['last_attempt_time'] ?? time();

  if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_time']) < 300) {
    $remaining = 300 - (time() - $_SESSION['last_attempt_time']);
    $error_message = "Too many failed attempts. Please wait {$remaining} seconds before trying again.";
  } else {
    if (!verify_csrf_token()) {
      $error_message = "Security Error: Invalid or expired session token. Please refresh.";
    } else {
      $rawInput = trim($_POST['email'] ?? '');
      $loginInput = strtolower($rawInput);
      $password = $_POST['password'] ?? '';
      $authenticated = false;

      if (!empty($loginInput) && !empty($password)) {
        try {
          $stmtUser = $pdo->prepare("SELECT id, emp_id, full_name, role, permission_level, password_hash, status FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1");
          $stmtUser->execute([$loginInput, $loginInput]);
          $staff = $stmtUser->fetch();

          if ($staff && password_verify($password, $staff['password_hash'])) {
            $authenticated = true;
            if ($staff['status'] === 'Suspended') {
              $error_message = "Access Denied: Your staff account has been suspended.";
            } else {
              session_regenerate_id(true);
              $_SESSION['login_attempts'] = 0;
              
              $_SESSION['account_type']   = 'staff';
              $_SESSION['user_id']     = (int)$staff['id'];
              $_SESSION['emp_id']      = $staff['emp_id'];
              $_SESSION['user_name']    = $staff['full_name'];
              $_SESSION['role']       = $staff['role'];
              $_SESSION['permission_level'] = $staff['permission_level'];
              
              redirect('index.php');
            }
          }
        } catch (PDOException $e) {
          error_log("Database Error in Staff Login: " . $e->getMessage());
        }

        if (!$authenticated && empty($error_message)) {
          try {
            $stmtPartner = $pdo->prepare("SELECT id, reference_id, partner_type, company_name, contact_first_name, contact_last_name, password_hash, status FROM partners WHERE LOWER(email) = ? OR LOWER(reference_id) = ? LIMIT 1");
            $stmtPartner->execute([$loginInput, $loginInput]);
            $partner = $stmtPartner->fetch();

            if ($partner && password_verify($password, $partner['password_hash'])) {
              if ($partner['status'] === 'pending') {
                $error_message = "Account Pending: Your registration is still under review by administration.";
              } elseif ($partner['status'] === 'suspended' || $partner['status'] === 'rejected') {
                $error_message = "Access Denied: Your account has been suspended or rejected.";
              } else {
                session_regenerate_id(true);
                $_SESSION['login_attempts'] = 0;
                
                $_SESSION['account_type']  = 'partner';
                $_SESSION['user_id']    = (int)$partner['id'];
                $_SESSION['reference_id']  = $partner['reference_id'];
                $_SESSION['partner_type']  = $partner['partner_type'];
                $_SESSION['user_name']   = trim($partner['contact_first_name'] . ' ' . $partner['contact_last_name']);
                $_SESSION['company_name']  = $partner['company_name'];
                
                if ($partner['partner_type'] === 'client') {
                  redirect('modules/crm_sales/client_profile.php?id=' . urlencode($partner['reference_id']));
                } else {
                  redirect('index.php');
                }
              }
            } else {
              $_SESSION['login_attempts']++;
              $_SESSION['last_attempt_time'] = time();
              $error_message = "Authentication Failed: Invalid credentials provided.";
              usleep(250000); // 250ms anti-timing pause
            }
          } catch (PDOException $e) {
            error_log("Database Error in Partner Login: " . $e->getMessage());
            $error_message = "System Error: Unable to authenticate at this moment.";
          }
        }
      } else {
        $error_message = "Validation Error: Please provide both username and password.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Command Center Login | I-GAS Enterprise</title>
  <meta name="description" content="Secure authentication terminal for I-GAS Enterprise industrial gas operations.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700;800&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
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
      const savedTheme = localStorage.getItem('app_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      void document.documentElement.offsetHeight;
      requestAnimationFrame(function() {
        document.documentElement.classList.add('layout-ready');
      });
    })();
  </script>
</head>
<body class="w-full h-screen flex overflow-hidden bg-[var(--surface-0)] text-[var(--text-primary)] antialiased">

  <!-- LEFT: Ink-Colored Brand & Engineering Panel (P&ID Blueprint Grid) -->
  <section class="hidden lg:flex w-[48%] xl:w-[50%] h-full flex-col justify-between p-12 relative overflow-hidden flex-shrink-0 blueprint-grid" style="background-color: var(--sidebar-bg); border-inline-end: 1px solid var(--sidebar-line);">
    
    <!-- Header Lockup -->
    <div class="relative z-10 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 flex items-center justify-center text-white font-mono font-bold text-sm border rounded-sm" style="border-color: #2E5065; background: #0F2A3B;">
          <?= icon('cylinder', 'w-5 h-5 text-[var(--primary-bright)]') ?>
        </div>
        <div>
          <span class="text-white font-display font-bold text-lg tracking-tight block">I-GAS</span>
          <span class="text-[10px] font-mono font-semibold uppercase tracking-[0.16em] block" style="color: var(--primary-bright);">Enterprise Systems</span>
        </div>
      </div>
      <span class="tag-plate font-mono text-[10px] whitespace-nowrap" style="background: #0E2230; color: #9EB6C6; border-color: #1F3A4A;">P&ID CONTROLS</span>
    </div>

    <!-- Center Engineering Line-Art & Tagline -->
    <div class="relative z-10 max-w-lg my-auto py-8">
      <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-sm border mb-6" style="background: rgba(25, 181, 201, 0.08); border-color: rgba(25, 181, 201, 0.25);">
        <span class="w-2 h-2 rounded-full bg-[var(--accent)]"></span>
        <span class="font-mono text-xs font-semibold text-[var(--primary-bright)] tracking-wider uppercase" data-en="Pressure & Purity Directive" data-ar="توجيه: الضغط والنقاء">Pressure & Purity Directive</span>
      </div>

      <h1 class="text-white font-display text-3xl xl:text-4xl font-bold tracking-tight leading-tight" data-en="Industrial Gas Plant Control & Logistics Matrix" data-ar="منظومة التحكم بالغازات الصناعية وسلاسل الإمداد">
        Industrial Gas Plant Control & Logistics Matrix
      </h1>

      <p class="text-sm mt-4 leading-relaxed" style="color: #9EB6C6;" data-en="Cryogenic distillation tracking, multi-gas manifold telemetry, cylinder fleet retest verification, and ADR hazmat dispatch." data-ar="متابعة التقطير المبرد، قياسات مشعبات الغاز المتعددة، فحص واختبار الأسطوانات، وتوزيع شحنات المواد الخطرة.">
        Cryogenic distillation tracking, multi-gas manifold telemetry, cylinder fleet retest verification, and ADR hazmat dispatch.
      </p>

      <!-- Technical Line-Art Schematic (SVG P&ID Gas Manifold) -->
      <div class="mt-8 p-4 rounded-sm border" style="background: #0D1F2B; border-color: #162C3D;">
        <div class="flex items-center justify-between text-[11px] font-mono mb-3" style="color: #7E96A6;">
          <span>MANIFOLD LOOP #01</span>
          <span class="text-[var(--status-success)]">● PURGED & ACTIVE</span>
        </div>
        <svg viewBox="0 0 400 60" fill="none" class="w-full h-12" style="color: var(--primary-bright);">
          <line x1="10" y1="30" x2="390" y2="30" stroke="currentColor" stroke-width="2"/>
          <circle cx="60" cy="30" r="8" fill="#0D1F2B" stroke="currentColor" stroke-width="2"/>
          <circle cx="160" cy="30" r="8" fill="#0D1F2B" stroke="currentColor" stroke-width="2"/>
          <circle cx="260" cy="30" r="8" fill="#0D1F2B" stroke="currentColor" stroke-width="2"/>
          <circle cx="360" cy="30" r="8" fill="#0D1F2B" stroke="currentColor" stroke-width="2"/>
          <path d="M 60 22 L 60 10 M 160 22 L 160 10 M 260 22 L 260 10 M 360 22 L 360 10" stroke="currentColor" stroke-width="1.5"/>
          <rect x="52" y="6" width="16" height="5" rx="1" fill="#FF6B1A"/>
          <rect x="152" y="6" width="16" height="5" rx="1" fill="#FF6B1A"/>
          <rect x="252" y="6" width="16" height="5" rx="1" fill="#FF6B1A"/>
          <rect x="352" y="6" width="16" height="5" rx="1" fill="#FF6B1A"/>
        </svg>
        <div class="flex justify-between text-[10px] font-mono mt-1" style="color: #648092;">
          <span>O₂ [200 BAR]</span>
          <span>N₂ [200 BAR]</span>
          <span>Ar [200 BAR]</span>
          <span>CO₂ [60 BAR]</span>
        </div>
      </div>
    </div>

    <!-- Live-Looking System Status Strip -->
    <div class="relative z-10 flex items-center justify-between text-[11px] font-mono border-t pt-4" style="border-color: var(--sidebar-line); color: #7E96A6;">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full" style="background: var(--status-success);"></span>
        <span class="text-white">SYSTEM TELEMETRY NOMINAL</span>
      </div>
      <span>AES-256 ENCRYPTED</span>
      <span>PORT 8000</span>
    </div>
  </section>

  <!-- RIGHT: Clean Authentication Terminal Form -->
  <section class="w-full lg:w-[52%] xl:w-[50%] h-full flex flex-col justify-between p-6 sm:p-12 xl:p-16 overflow-y-auto bg-[var(--surface-1)]">
    
    <!-- Top Toolbar with Language & Theme Switcher -->
    <div class="flex items-center justify-between w-full pb-4 border-b border-[var(--line)]">
      <div class="flex items-center gap-2 lg:hidden">
        <div class="w-7 h-7 flex items-center justify-center text-white font-mono font-bold text-xs border rounded-sm" style="border-color: #2E5065; background: #0F2A3B;">
          <?= icon('cylinder', 'w-4 h-4 text-[var(--primary-bright)]') ?>
        </div>
        <span class="font-display font-bold text-base text-[var(--text-primary)]">I-GAS</span>
      </div>
      <div class="flex items-center gap-2 ms-auto">
        <button type="button" onclick="toggleAppLanguage()" class="btn-teal-outline text-xs px-2.5 py-1" title="Switch Language / تبديل اللغة">
          <i data-lucide="globe" class="w-3.5 h-3.5"></i>
          <span id="login-lang-label" class="tracking-wide">العربية</span>
        </button>
        <button type="button" onclick="toggleAppTheme()" class="btn-teal-outline text-xs p-1.5" title="Toggle Theme (Light / Dark)">
          <i data-lucide="moon" id="login-theme-icon" class="w-3.5 h-3.5"></i>
        </button>
      </div>
    </div>

    <!-- Center Login Card -->
    <div class="w-full max-w-md mx-auto my-auto py-8">
      <div class="mb-6">
        <span class="section-overline" data-en="CONTROL ROOM ACCESS" data-ar="تسجيل دخول غرفة التحكم">CONTROL ROOM ACCESS</span>
        <h2 class="font-display text-2xl font-bold text-[var(--text-primary)] mt-1" data-en="Command Center Terminal" data-ar="محطة مركز القيادة">Command Center Terminal</h2>
        <p class="text-xs text-[var(--text-secondary)] mt-1.5" data-en="Provide authorized credentials to initialize session terminal." data-ar="أدخل بياناتك المعتمدة لبدء جلسة العمليات.">
          Provide authorized credentials to initialize session terminal.
        </p>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="mb-5 p-3.5 border rounded-sm flex items-start gap-2.5" style="background: rgba(217, 54, 54, 0.08); border-color: var(--status-danger); color: var(--status-danger);">
        <i data-lucide="alert-octagon" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
        <span class="text-xs font-semibold"><?= htmlspecialchars($error_message) ?></span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div>
          <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1" data-en="Corporate Email / Operator ID" data-ar="البريد الإلكتروني / معرف المشغل">Corporate Email / Operator ID</label>
          <div class="relative flex items-center">
            <span class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none text-[var(--text-muted)]">
              <i data-lucide="user" class="w-4 h-4"></i>
            </span>
            <input type="text" name="email" class="w-full ps-9 pe-3 py-2 text-xs font-mono rounded-sm border border-[var(--line)] bg-[var(--surface-0)] text-[var(--text-primary)] focus:bg-[var(--surface-1)] transition-colors" placeholder="admin or operator@igas.com" required autocomplete="username">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[var(--text-primary)] mb-1" data-en="Security Key / Password" data-ar="كلمة المرور المشفرة">Security Key / Password</label>
          <div class="relative flex items-center">
            <span class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none text-[var(--text-muted)]">
              <i data-lucide="lock" class="w-4 h-4"></i>
            </span>
            <input type="password" id="password_input" name="password" class="w-full ps-9 pe-10 py-2 text-xs font-mono rounded-sm border border-[var(--line)] bg-[var(--surface-0)] text-[var(--text-primary)] focus:bg-[var(--surface-1)] transition-colors" placeholder="••••••••••••" required autocomplete="current-password">
            <button type="button" id="toggle_password" class="absolute inset-y-0 end-0 flex items-center pe-3 text-[var(--text-muted)] hover:text-[var(--text-primary)] cursor-pointer" aria-label="Toggle password visibility">
              <i data-lucide="eye" class="w-4 h-4" id="eye_icon"></i>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-xs pt-1">
          <label class="inline-flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" name="remember_me" class="w-4 h-4 rounded-sm border-[var(--line)] text-[var(--primary)] focus:ring-0">
            <span class="text-[var(--text-secondary)]" data-en="Trust workstation for 30 days" data-ar="توثيق محطة العمل لـ 30 يوماً">Trust workstation for 30 days</span>
          </label>
        </div>

        <button type="submit" class="btn-cta-orange w-full py-2.5 mt-2">
          <i data-lucide="shield-check" class="w-4 h-4"></i>
          <span data-en="Authenticate & Access Terminal" data-ar="تسجيل الدخول وبدء الجلسة">Authenticate & Access Terminal</span>
        </button>
      </form>
    </div>

    <!-- Terminal Footer Metadata -->
    <div class="flex justify-between items-center text-[11px] font-mono text-[var(--text-muted)] border-t border-[var(--line)] pt-3">
      <span>SECURE TERMINAL ACTIVE</span>
      <span><?= $system_version ?></span>
    </div>

  </section>

  <script>
    lucide.createIcons();

    // Password Show/Hide Toggle
    const togglePassword = document.getElementById('toggle_password');
    const passwordInput = document.getElementById('password_input');
    const eyeIcon = document.getElementById('eye_icon');

    if (togglePassword && passwordInput && eyeIcon) {
      togglePassword.addEventListener('click', function () {
        const isPass = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPass ? 'text' : 'password');
        eyeIcon.setAttribute('data-lucide', isPass ? 'eye-off' : 'eye');
        lucide.createIcons();
      });
    }

    // Language & Theme Toggle support on Login
    function toggleAppLanguage() {
      const cur = localStorage.getItem('app_lang') || 'en';
      const next = cur === 'en' ? 'ar' : 'en';
      localStorage.setItem('app_lang', next);
      document.documentElement.lang = next;
      document.documentElement.dir = next === 'ar' ? 'rtl' : 'ltr';
      if (next === 'ar') {
        document.documentElement.classList.add('arabic-mode');
      } else {
        document.documentElement.classList.remove('arabic-mode');
      }
      document.querySelectorAll('[data-en]').forEach(el => {
        el.innerText = el.getAttribute('data-' + next) || el.getAttribute('data-en');
      });
      const lbl = document.getElementById('login-lang-label');
      if (lbl) lbl.textContent = next === 'ar' ? 'English' : 'العربية';
    }

    function toggleAppTheme() {
      const isDark = document.documentElement.classList.contains('dark');
      const next = isDark ? 'light' : 'dark';
      localStorage.setItem('app_theme', next);
      if (next === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      const icon = document.getElementById('login-theme-icon');
      if (icon) {
        icon.setAttribute('data-lucide', next === 'dark' ? 'sun' : 'moon');
        lucide.createIcons();
      }
    }
  </script>
</body>
</html>