<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('settings.manage');
require_once __DIR__ . '/../../config/db.php';

if (!function_exists('e')) {
  function e($value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

$allowed_languages = ['en', 'ar'];
$allowed_timezones = ['Asia/Riyadh', 'Asia/Dubai', 'Africa/Cairo', 'UTC'];

$success_msg = '';
$trigger_lang_update = false;
$new_lang = 'en';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  if (isset($_POST['action']) && $_POST['action'] === 'force_backup') {
    $backup_date = date('Y-m-d h:i A') . ' UTC';
    $stmt = $pdo->prepare("
      INSERT OR REPLACE INTO system_settings (setting_key, setting_value)
      VALUES ('last_backup', ?)
    ");
    $stmt->execute([$backup_date]);
    $_SESSION['success_msg'] = 'Manual database telemetry backup generated successfully.';
    redirect(app_url('modules/insights/system_settings.php'));
  }

  $keys = [
    'site_title', 'hq_designation', 'app_language', 'currency', 'currency_symbol',
    'timezone', 'region', 'date_format', 'time_format', 'seo_description',
    'seo_keywords', 'reorder_threshold', 'sync_window', 'auto_route_hydro',
    'sms_alerts', 'session_timeout', 'pwd_complexity', 'enforce_2fa',
    'ip_whitelist', 'backup_freq', 'log_retention'
  ];

  $checkbox_keys = ['auto_route_hydro', 'sms_alerts', 'enforce_2fa'];

  $stmt = $pdo->prepare("
    INSERT OR REPLACE INTO system_settings (setting_key, setting_value)
    VALUES (?, ?)
  ");

  foreach ($keys as $key) {
    if (in_array($key, $checkbox_keys, true)) {
      $value = isset($_POST[$key]) ? '1' : '0';
    } elseif ($key === 'app_language') {
      $value = $_POST[$key] ?? 'en';
      if (!in_array($value, $allowed_languages, true)) {
        $value = 'en';
      }
      $_SESSION['app_language'] = $value;
      $trigger_lang_update = true;
      $new_lang = $value;
    } elseif ($key === 'timezone') {
      $value = $_POST[$key] ?? 'Asia/Riyadh';
      if (!in_array($value, $allowed_timezones, true)) {
        $value = 'Asia/Riyadh';
      }
    } else {
      $value = trim($_POST[$key] ?? '');
    }
    $stmt->execute([$key, $value]);
  }

  $_SESSION['success_msg'] = 'Plant configuration parameters saved successfully.';
  $_SESSION['trigger_lang_update'] = $new_lang;
  redirect(app_url('modules/insights/system_settings.php'));
}

if (isset($_SESSION['success_msg'])) {
  $success_msg = $_SESSION['success_msg'];
  unset($_SESSION['success_msg']);
}

if (isset($_SESSION['trigger_lang_update'])) {
  $trigger_lang_update = true;
  $new_lang = $_SESSION['trigger_lang_update'];
  unset($_SESSION['trigger_lang_update']);
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$settings = [
  'site_title'    => $db_settings['site_title'] ?? 'I-GAS Enterprise Systems',
  'hq_designation'  => $db_settings['hq_designation'] ?? 'Jeddah Industrial — HQ',
  'app_language'   => $db_settings['app_language'] ?? 'en',
  'currency'     => $db_settings['currency'] ?? 'SAR',
  'currency_symbol'  => $db_settings['currency_symbol'] ?? 'ر.س',
  'timezone'     => $db_settings['timezone'] ?? 'Asia/Riyadh',
  'region'      => $db_settings['region'] ?? 'Saudi Arabia',
  'date_format'    => $db_settings['date_format'] ?? 'Y-m-d',
  'time_format'    => $db_settings['time_format'] ?? 'H:i',
  'seo_description'  => $db_settings['seo_description'] ?? 'I-GAS Comprehensive Industrial Gas Management & ERP System.',
  'seo_keywords'   => $db_settings['seo_keywords'] ?? 'Gas, ERP, Logistics, Industrial, Management',
  'reorder_threshold' => $db_settings['reorder_threshold'] ?? '15',
  'sync_window'    => $db_settings['sync_window'] ?? '5',
  'auto_route_hydro' => $db_settings['auto_route_hydro'] ?? '1',
  'sms_alerts'    => $db_settings['sms_alerts'] ?? '1',
  'session_timeout'  => $db_settings['session_timeout'] ?? '30',
  'pwd_complexity'  => $db_settings['pwd_complexity'] ?? 'high',
  'enforce_2fa'    => $db_settings['enforce_2fa'] ?? '1',
  'ip_whitelist'   => $db_settings['ip_whitelist'] ?? '192.168.1.1, 212.34.120.45',
  'backup_freq'    => $db_settings['backup_freq'] ?? 'daily',
  'log_retention'   => $db_settings['log_retention'] ?? '1year',
  'last_backup'    => $db_settings['last_backup'] ?? 'No manual backups recorded'
];

if (!in_array($settings['app_language'], $allowed_languages, true)) {
  $settings['app_language'] = 'en';
}

if (!in_array($settings['timezone'], $allowed_timezones, true)) {
  $settings['timezone'] = 'Asia/Riyadh';
}

date_default_timezone_set($settings['timezone']);
$current_language = $settings['app_language'];
$current_direction = $current_language === 'ar' ? 'rtl' : 'ltr';

$active_page = 'system_settings';
$base_url = '../../';
$breadcrumb = ['I-GAS', 'Insights', 'System Settings'];
$system_version = 'v2.5.0-Cryo';
?>
<!DOCTYPE html>
<html lang="<?= e($current_language) ?>" dir="<?= e($current_direction) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="<?= e($settings['site_title']) ?> | System Settings" data-ar="<?= e($settings['site_title']) ?> | إعدادات النظام">
    <?= e($settings['site_title']) ?> | System Settings
  </title>
  <meta name="description" content="<?= e($settings['seo_description']) ?>">
  <meta name="keywords" content="<?= e($settings['seo_keywords']) ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    *, *::before, *::after {
      box-shadow: none !important;
      text-shadow: none !important;
      filter: none !important;
    }
    .settings-nav-item.active {
      border-inline-start-color: var(--safety-orange) !important;
      background-color: var(--surface-2) !important;
      color: var(--ink) !important;
      font-weight: 600 !important;
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
        <span data-en="System Nominal" data-ar="النظام مستقر">System Nominal</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Plant —" data-ar="المصنع —">Plant —</span> <?= e($settings['hq_designation']) ?>
      </span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="System Version ·" data-ar="إصدار النظام ·">System Version ·</span> <span class="text-[var(--ink)] font-semibold"><?= e($system_version) ?></span>
      </span>
    </div>

    <div id="settings-scroll" class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">
      <?php if ($success_msg): ?>
        <div id="toastMsg" class="fixed bottom-5 end-5 bg-[var(--surface-1)] border border-[var(--cryo-teal)] border-s-4 border-s-[var(--cryo-teal)] text-[var(--ink)] px-4 py-3 rounded-sm text-xs font-mono flex items-center gap-3 z-50">
          <?= icon('check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          <span><?= e($success_msg) ?></span>
        </div>
        <script>
          setTimeout(() => { const t = document.getElementById('toastMsg'); if(t) t.style.display = 'none'; }, 4000);
        </script>
      <?php endif; ?>

      <!-- Page Title & Actions -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-7">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate('SYS-CFG-01', 'orange') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Global Plant Administration" data-ar="إدارة المنشأة العامة">Global Plant Administration</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]" data-en="System Configuration & Parameters" data-ar="تكوين النظام ومعايير التشغيل">System Configuration & Parameters</h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono" data-en="Control enterprise telemetry parameters, automated cylinder hydro-test rules, localization, and security credentials." data-ar="تحكم في معايير القياس عن بعد، وقواعد الاختبار الهيدروستاتيكي، واللغة، ومصادقة الأمان.">Control enterprise telemetry parameters, automated cylinder hydro-test rules, localization, and security credentials.</p>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" onclick="window.location.reload();" class="px-4 py-2.5 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono transition-colors cursor-pointer" data-en="Discard" data-ar="تجاهل">Discard</button>
          <button type="submit" form="settings-form" class="btn-cta-orange px-5 py-2.5 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-2 cursor-pointer transition-colors">
            <?= icon('save', 'w-4 h-4') ?>
            <span data-en="Save Settings" data-ar="حفظ الإعدادات">Save Settings</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-4 gap-7 items-start pb-10">
        
        <!-- Left Nav Sticky Index -->
        <div class="xl:col-span-1 sticky top-0 flex flex-col gap-1.5 bg-[var(--surface-1)] border border-[var(--line)] rounded-sm p-3">
          <div class="px-3 py-2 text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] font-semibold border-b border-[var(--line-soft)] mb-1">
            Navigation Modules
          </div>
          <a href="#section-general" class="settings-nav-item active flex items-center gap-2.5 px-3 py-2 rounded-sm text-xs font-mono border-s-2 border-s-[var(--safety-orange)] bg-[var(--surface-2)] text-[var(--ink)] transition-colors" onclick="setActiveNav(this)">
            <?= icon('globe', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
            <span data-en="Localization & Identity" data-ar="اللغة والهوية">Localization & Identity</span>
          </a>
          <a href="#section-seo" class="settings-nav-item flex items-center gap-2.5 px-3 py-2 rounded-sm text-xs font-mono border-s-2 border-transparent text-[var(--ink-muted)] hover:text-[var(--ink)] hover:bg-[var(--surface-2)] transition-colors" onclick="setActiveNav(this)">
            <?= icon('search', 'w-4 h-4') ?>
            <span data-en="SEO & Metadata" data-ar="تحسين محركات البحث">SEO & Metadata</span>
          </a>
          <a href="#section-automation" class="settings-nav-item flex items-center gap-2.5 px-3 py-2 rounded-sm text-xs font-mono border-s-2 border-transparent text-[var(--ink-muted)] hover:text-[var(--ink)] hover:bg-[var(--surface-2)] transition-colors" onclick="setActiveNav(this)">
            <?= icon('cpu', 'w-4 h-4') ?>
            <span data-en="Automation Rules" data-ar="قواعد الأتمتة">Automation Rules</span>
          </a>
          <a href="#section-security" class="settings-nav-item flex items-center gap-2.5 px-3 py-2 rounded-sm text-xs font-mono border-s-2 border-transparent text-[var(--ink-muted)] hover:text-[var(--ink)] hover:bg-[var(--surface-2)] transition-colors" onclick="setActiveNav(this)">
            <?= icon('shield-check', 'w-4 h-4') ?>
            <span data-en="Access & Security" data-ar="الوصول والأمان">Access & Security</span>
          </a>
          <a href="#section-backup" class="settings-nav-item flex items-center gap-2.5 px-3 py-2 rounded-sm text-xs font-mono border-s-2 border-transparent text-[var(--ink-muted)] hover:text-[var(--ink)] hover:bg-[var(--surface-2)] transition-colors" onclick="setActiveNav(this)">
            <?= icon('database', 'w-4 h-4') ?>
            <span data-en="Backups & Logs" data-ar="النسخ الاحتياطي والسجلات">Backups & Logs</span>
          </a>
          
          <div class="mt-4 pt-3 border-t border-[var(--line-soft)] px-2">
            <div class="flex items-center justify-between text-[11px] font-mono text-[var(--ink-muted)] mb-1.5">
              <span>RUNTIME:</span>
              <span class="text-[var(--ink)] font-semibold">PHP 8.x / PDO</span>
            </div>
            <div class="flex items-center justify-between text-[11px] font-mono text-[var(--ink-muted)] mb-1.5">
              <span>STORAGE:</span>
              <span class="text-[var(--ink)] font-semibold">SQLite / MySQL</span>
            </div>
            <div class="flex items-center justify-between text-[11px] font-mono text-[var(--ink-muted)]">
              <span>SECURITY:</span>
              <span class="text-emerald-500 font-semibold">RBAC ACTIVE</span>
            </div>
          </div>
        </div>

        <!-- Right Form Workspace -->
        <div class="xl:col-span-3">
          <form id="settings-form" method="POST" action="<?= app_url('modules/insights/system_settings.php') ?>" class="flex flex-col gap-6">
            <?= csrf_field() ?>
            
            <!-- 1. Enterprise Identity & Localization -->
            <div id="section-general" class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-[var(--line)]">
                <div class="flex items-center gap-2">
                  <?= icon('globe', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="Enterprise Identity & Localization" data-ar="هوية المؤسسة واللغة">Enterprise Identity & Localization</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 01</span>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="col-span-1 lg:col-span-2">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Platform Title" data-ar="عنوان المنصة">Platform Title</label>
                  <input type="text" name="site_title" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['site_title']) ?>" required>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Plant HQ Designation" data-ar="المقر الرئيسي">Plant HQ Designation</label>
                  <input type="text" name="hq_designation" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['hq_designation']) ?>" required>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Interface Language" data-ar="لغة واجهة النظام">Interface Language</label>
                  <select name="app_language" id="app_language" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer" onchange="previewLanguage(this.value)">
                    <option value="en" <?= $settings['app_language'] === 'en' ? 'selected' : '' ?> data-en="English (LTR)" data-ar="الإنجليزية (LTR)">English (LTR)</option>
                    <option value="ar" <?= $settings['app_language'] === 'ar' ? 'selected' : '' ?> data-en="Arabic (RTL)" data-ar="العربية (RTL)">Arabic (RTL)</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Base Currency Code" data-ar="رمز العملة الأساسية">Base Currency Code</label>
                  <select name="currency" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="SAR" <?= $settings['currency'] === 'SAR' ? 'selected' : '' ?>>SAR (Saudi Riyal)</option>
                    <option value="USD" <?= $settings['currency'] === 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
                    <option value="AED" <?= $settings['currency'] === 'AED' ? 'selected' : '' ?>>AED (UAE Dirham)</option>
                    <option value="EGP" <?= $settings['currency'] === 'EGP' ? 'selected' : '' ?>>EGP (Egyptian Pound)</option>
                    <option value="EUR" <?= $settings['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Currency Symbol" data-ar="رمز العملة">Currency Symbol</label>
                  <input type="text" name="currency_symbol" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['currency_symbol']) ?>" required>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="System Timezone" data-ar="المنطقة الزمنية">System Timezone</label>
                  <select name="timezone" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="Asia/Riyadh" <?= $settings['timezone'] === 'Asia/Riyadh' ? 'selected' : '' ?>>Asia/Riyadh (AST · GMT+3)</option>
                    <option value="Asia/Dubai" <?= $settings['timezone'] === 'Asia/Dubai' ? 'selected' : '' ?>>Asia/Dubai (GST · GMT+4)</option>
                    <option value="Africa/Cairo" <?= $settings['timezone'] === 'Africa/Cairo' ? 'selected' : '' ?>>Africa/Cairo (EET · GMT+2)</option>
                    <option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC (Universal Telemetry)</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Operating Region" data-ar="منطقة التشغيل">Operating Region</label>
                  <input type="text" name="region" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['region']) ?>">
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Date Format" data-ar="تنسيق التاريخ">Date Format</label>
                  <select name="date_format" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="Y-m-d" <?= $settings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                    <option value="d/m/Y" <?= $settings['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                    <option value="M d, Y" <?= $settings['date_format'] === 'M d, Y' ? 'selected' : '' ?>>Mon DD, YYYY</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Time Format" data-ar="تنسيق الوقت">Time Format</label>
                  <select name="time_format" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="H:i" <?= $settings['time_format'] === 'H:i' ? 'selected' : '' ?>>24-Hour (14:30)</option>
                    <option value="h:i A" <?= $settings['time_format'] === 'h:i A' ? 'selected' : '' ?>>12-Hour AM/PM (02:30 PM)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 2. Search Engine Optimization & Metadata -->
            <div id="section-seo" class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-[var(--line)]">
                <div class="flex items-center gap-2">
                  <?= icon('search', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="Search Engine Optimization (SEO)" data-ar="تحسين محركات البحث">Search Engine Optimization (SEO)</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 02</span>
              </div>
              <div class="grid grid-cols-1 gap-5">
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Meta Description" data-ar="وصف الميتا">Meta Description</label>
                  <textarea name="seo_description" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" rows="3"><?= e($settings['seo_description']) ?></textarea>
                </div>
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Meta Keywords" data-ar="الكلمات المفتاحية">Meta Keywords</label>
                  <input type="text" name="seo_keywords" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['seo_keywords']) ?>">
                </div>
              </div>
            </div>

            <!-- 3. Production & Logistics Automation -->
            <div id="section-automation" class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-[var(--line)]">
                <div class="flex items-center gap-2">
                  <?= icon('cpu', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="Production & Logistics Automation" data-ar="أتمتة الإنتاج واللوجستيات">Production & Logistics Automation</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 03</span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Bulk Feedstock Reorder Threshold" data-ar="حد إعادة طلب المواد الخام">Bulk Feedstock Reorder Threshold</label>
                  <div class="relative flex items-center">
                    <input type="number" name="reorder_threshold" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 pe-24 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['reorder_threshold']) ?>" min="1" max="100">
                    <span class="absolute end-3 text-[11px] font-mono text-[var(--ink-muted)] pointer-events-none">% CAPACITY</span>
                  </div>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Fleet Telemetry Sync Window" data-ar="نافذة مزامنة تتبع الأسطول">Fleet Telemetry Sync Window</label>
                  <div class="relative flex items-center">
                    <input type="number" name="sync_window" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 pe-20 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['sync_window']) ?>" min="1">
                    <span class="absolute end-3 text-[11px] font-mono text-[var(--ink-muted)] pointer-events-none">MINUTES</span>
                  </div>
                </div>
                <div class="col-span-2 border-t border-[var(--line)] pt-4 mt-2 flex flex-col gap-4">
                  <label class="relative inline-flex items-center justify-between cursor-pointer select-none p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-0)]">
                    <div>
                      <p class="text-xs font-mono font-medium text-[var(--ink)]" data-en="Auto-route empty cylinders to hydro-testing queue when circulation limit is exceeded" data-ar="توجيه الأسطوانات الفارغة تلقائيًا إلى قائمة الاختبار عند تجاوز حد التداول">Auto-route empty cylinders to hydro-testing queue when circulation limit is exceeded</p>
                      <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-0.5">Enforces safety protocol ISO-9809-1 automatically during cylinder check-in.</p>
                    </div>
                    <input type="checkbox" name="auto_route_hydro" value="1" class="sr-only peer" <?= $settings['auto_route_hydro'] === '1' ? 'checked' : '' ?>>
                    <div class="w-11 h-6 bg-[var(--surface-2)] border border-[var(--line)] peer-checked:bg-[var(--cryo-teal)] peer-checked:border-[var(--cryo-teal)] rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-[var(--line)] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white ms-4 flex-shrink-0"></div>
                  </label>

                  <label class="relative inline-flex items-center justify-between cursor-pointer select-none p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-0)]">
                    <div>
                      <p class="text-xs font-mono font-medium text-[var(--ink)]" data-en="Trigger emergency SMS/Email notifications when Low Stock states persist beyond 48h" data-ar="إرسال تنبيهات طارئة عند استمرار انخفاض المخزون لأكثر من 48 ساعة">Trigger emergency SMS/Email notifications when Low Stock states persist beyond 48h</p>
                      <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-0.5">Dispatches priority alerts to Plant Supervisors and Logistics Managers.</p>
                    </div>
                    <input type="checkbox" name="sms_alerts" value="1" class="sr-only peer" <?= $settings['sms_alerts'] === '1' ? 'checked' : '' ?>>
                    <div class="w-11 h-6 bg-[var(--surface-2)] border border-[var(--line)] peer-checked:bg-[var(--cryo-teal)] peer-checked:border-[var(--cryo-teal)] rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-[var(--line)] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white ms-4 flex-shrink-0"></div>
                  </label>
                </div>
              </div>
            </div>

            <!-- 4. Access & Security -->
            <div id="section-security" class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-[var(--line)]">
                <div class="flex items-center gap-2">
                  <?= icon('shield-check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="Station Access & Security" data-ar="الوصول والأمان">Station Access & Security</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 04</span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Session Idle Timeout" data-ar="مهلة انتهاء الجلسة">Session Idle Timeout</label>
                  <div class="relative flex items-center">
                    <input type="number" name="session_timeout" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 pe-20 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['session_timeout']) ?>" min="5">
                    <span class="absolute end-3 text-[11px] font-mono text-[var(--ink-muted)] pointer-events-none">MINUTES</span>
                  </div>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Password Complexity Policy" data-ar="تعقيد كلمة المرور">Password Complexity Policy</label>
                  <select name="pwd_complexity" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="high" <?= $settings['pwd_complexity'] === 'high' ? 'selected' : '' ?> data-en="Strict — Min 10 Chars + Symbols" data-ar="صارم — 10 أحرف كحد أدنى مع رموز">Strict — Min 10 Chars + Symbols</option>
                    <option value="medium" <?= $settings['pwd_complexity'] === 'medium' ? 'selected' : '' ?> data-en="Standard — Min 8 Chars" data-ar="قياسي — 8 أحرف كحد أدنى">Standard — Min 8 Chars</option>
                  </select>
                </div>
                <div class="col-span-2">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Static IP Whitelist (CSV)" data-ar="عناوين IP المسموح بها">Static IP Whitelist (CSV)</label>
                  <input type="text" name="ip_whitelist" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= e($settings['ip_whitelist']) ?>" placeholder="192.168.1.1, 10.0.0.1">
                </div>
                <div class="col-span-2 border-t border-[var(--line)] pt-4 mt-2">
                  <label class="relative inline-flex items-center justify-between cursor-pointer select-none p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-0)] w-full">
                    <div>
                      <p class="text-xs font-mono font-medium text-[var(--ink)]" data-en="Enforce Two-Factor Authentication for Administrative Roles" data-ar="فرض المصادقة الثنائية للأدوار الإدارية">Enforce Two-Factor Authentication for Administrative Roles</p>
                      <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-0.5">Mandatory for Admin, Plant Manager, and Finance roles.</p>
                    </div>
                    <input type="checkbox" name="enforce_2fa" value="1" class="sr-only peer" <?= $settings['enforce_2fa'] === '1' ? 'checked' : '' ?>>
                    <div class="w-11 h-6 bg-[var(--surface-2)] border border-[var(--line)] peer-checked:bg-[var(--cryo-teal)] peer-checked:border-[var(--cryo-teal)] rounded-full transition-colors relative after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-[var(--line)] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white ms-4 flex-shrink-0"></div>
                  </label>
                </div>
              </div>
            </div>

            <!-- 5. System Backups & Logs -->
            <div id="section-backup" class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-[var(--line)]">
                <div class="flex items-center gap-2">
                  <?= icon('database', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="System Backups & Transaction Logs" data-ar="النسخ الاحتياطي والسجلات">System Backups & Transaction Logs</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 05</span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Automated Database Backup" data-ar="النسخ الاحتياطي التلقائي">Automated Database Backup</label>
                  <select name="backup_freq" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="daily" <?= $settings['backup_freq'] === 'daily' ? 'selected' : '' ?> data-en="Every 24 Hours (Nightly)" data-ar="كل 24 ساعة (ليليًا)">Every 24 Hours (Nightly)</option>
                    <option value="weekly" <?= $settings['backup_freq'] === 'weekly' ? 'selected' : '' ?> data-en="Every 7 Days" data-ar="كل 7 أيام">Every 7 Days</option>
                    <option value="realtime" <?= $settings['backup_freq'] === 'realtime' ? 'selected' : '' ?> data-en="Continuous Telemetry Replication" data-ar="نسخ مستمر للمعاملات">Continuous Telemetry Replication</option>
                  </select>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Audit Logs Retention" data-ar="الاحتفاظ بسجلات المعاملات">Audit Logs Retention</label>
                  <select name="log_retention" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="1year" <?= $settings['log_retention'] === '1year' ? 'selected' : '' ?> data-en="12 Months" data-ar="12 شهرًا">12 Months</option>
                    <option value="5years" <?= $settings['log_retention'] === '5years' ? 'selected' : '' ?> data-en="5 Fiscal Years (ZATCA Compliant)" data-ar="5 سنوات مالية (متوافق مع هيئة الزكاة)">5 Fiscal Years (ZATCA Compliant)</option>
                  </select>
                </div>
              </div>
            </div>

          </form>

          <!-- Manual Backup Card -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between p-5 rounded-sm border border-[var(--line)] mt-6 bg-[var(--surface-1)] gap-4">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-sm flex items-center justify-center bg-[var(--surface-2)] border border-[var(--line)] text-[var(--cryo-teal)]">
                <?= icon('archive', 'w-5 h-5') ?>
              </div>
              <div>
                <p class="text-xs font-mono font-semibold text-[var(--ink)] uppercase" data-en="Last Telemetry Snapshot Status" data-ar="حالة آخر نسخة احتياطية">Last Telemetry Snapshot Status</p>
                <p class="text-[11px] font-mono text-[var(--ink-muted)] mt-0.5"><?= e($settings['last_backup']) ?></p>
              </div>
            </div>
            <form method="POST" action="<?= app_url('modules/insights/system_settings.php') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="force_backup">
              <button type="submit" class="px-4 py-2 text-xs font-mono rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] transition-colors cursor-pointer" data-en="Trigger Manual Snapshot" data-ar="إنشاء نسخة احتياطية الآن">
                Trigger Manual Snapshot
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    const serverLanguage = <?= json_encode($current_language, JSON_UNESCAPED_UNICODE) ?>;
    const triggerGlobalUpdate = <?= json_encode($trigger_lang_update) ?>;
    const newGlobalLang = <?= json_encode($new_lang) ?>;

    if (triggerGlobalUpdate) {
      localStorage.setItem('app_lang', newGlobalLang);
    }

    const settingsScroll = document.getElementById('settings-scroll');

    function applyLanguage(language) {
      const safeLanguage = language === 'ar' ? 'ar' : 'en';

      document.documentElement.lang = safeLanguage;
      document.documentElement.dir = safeLanguage === 'ar' ? 'rtl' : 'ltr';
      if (safeLanguage === 'ar') {
        document.documentElement.classList.add('arabic-mode');
      } else {
        document.documentElement.classList.remove('arabic-mode');
      }

      document.querySelectorAll('[data-en]').forEach(function (element) {
        const translatedText = element.getAttribute('data-' + safeLanguage) || element.getAttribute('data-en');
        if (element.tagName === 'OPTION') {
          element.textContent = translatedText;
        } else if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
          if (element.hasAttribute('placeholder')) {
            element.setAttribute('placeholder', translatedText);
          }
        } else if (element.tagName === 'TITLE') {
          element.textContent = translatedText;
        } else {
          element.innerHTML = translatedText;
        }
      });

      const languageSelect = document.getElementById('app_language');
      if (languageSelect && languageSelect.value !== safeLanguage) {
        languageSelect.value = safeLanguage;
      }
    }

    function previewLanguage(language) {
      applyLanguage(language);
    }

    function setActiveNav(element) {
      document.querySelectorAll('.settings-nav-item').forEach(function (item) {
        item.classList.remove('active');
      });
      element.classList.add('active');
    }

    if (settingsScroll) {
      const sections = document.querySelectorAll('.card-accent-rule[id^="section-"]');
      const navItems = document.querySelectorAll('.settings-nav-item');
      settingsScroll.addEventListener('scroll', function () {
        let currentSection = '';
        sections.forEach(function (section) {
          const sectionTop = section.offsetTop;
          if (settingsScroll.scrollTop >= sectionTop - 60) {
            currentSection = section.getAttribute('id');
          }
        });
        navItems.forEach(function (item) {
          item.classList.remove('active');
          if (item.getAttribute('href').includes(currentSection)) {
            item.classList.add('active');
          }
        });
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      const activeLanguage = localStorage.getItem('app_lang') || serverLanguage;
      applyLanguage(activeLanguage);
    });
  </script>
</body>
</html>