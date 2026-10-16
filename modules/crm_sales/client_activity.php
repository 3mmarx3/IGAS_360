<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission($pdo, 'clients.view');

$active_page = 'client_activities';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Client Activity Log'];

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  require_permission($pdo, 'clients.edit');

  $client_id   = $_POST['client_id'] ?? '';
  $activity_text = trim($_POST['activity_text'] ?? '');
  $author    = $_SESSION['user_name'] ?? 'System Admin'; 

  if (empty($client_id) || !is_numeric($client_id)) {
    $errors[] = "Please select a valid client account.";
  }
  if (empty($activity_text)) {
    $errors[] = "Activity description cannot be empty.";
  }

  if (empty($errors)) {
    try {
      $ins = $pdo->prepare("
        INSERT INTO client_activities (client_id, activity_text, activity_time, author)
        VALUES (:client_id, :activity_text, CURRENT_TIMESTAMP, :author)
      ");
      $ins->execute([
        'client_id'   => (int)$client_id,
        'activity_text' => $activity_text,
        'author'    => $author
      ]);
      $success_message = "Interaction telemetry logged successfully.";
    } catch (PDOException $e) {
      $errors[] = "A database error occurred while saving the activity.";
    }
  }
}

$clientsStmt = $pdo->query("SELECT id, reference_id, company_name FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$activitiesStmt = $pdo->query("
  SELECT ca.*, p.company_name, p.reference_id 
  FROM client_activities ca
  JOIN partners p ON ca.client_id = p.id
  ORDER BY ca.activity_time DESC
  LIMIT 100
");
$activities = $activitiesStmt->fetchAll(PDO::FETCH_ASSOC);

$total_activities = count($activities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Client Activity Log | I-GAS Enterprise" data-ar="سجل نشاط العملاء | I-GAS Enterprise">Client Activity Log | I-GAS Enterprise</title>
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
        <span data-en="Activity Telemetry Active" data-ar="سجل التفاعلات نشط">Activity Telemetry Active</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Total Logged Records:" data-ar="إجمالي السجلات:">Total Logged Records:</span> <span class="text-[var(--ink)] font-semibold"><?= $total_activities ?></span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Protocol:" data-ar="البروتوكول:">Protocol:</span> <span class="text-[var(--cryo-teal)] font-semibold">CRM Audit Stream</span>
      </span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">ISO 9001:2015</span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">

      <!-- Breadcrumb Navigation Back Link -->
      <div class="mb-4">
        <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-mono text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors">
          <?= icon('arrow-left', 'w-3.5 h-3.5 rtl:rotate-180') ?>
          <span data-en="Back to Clients Directory" data-ar="العودة إلى دليل العملاء">Back to Clients Directory</span>
        </a>
      </div>

      <!-- Page Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate('CRM-ACT-01', 'teal') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Customer Communications & Site Visits" data-ar="اتصالات العملاء والزيارات الميدانية">Customer Communications & Site Visits</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]" data-en="Client Interaction Activity Log" data-ar="سجل نشاط وتفاعلات العملاء">Client Interaction Activity Log</h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono" data-en="Chronological ledger of contract discussions, cryogenic site visits, and commercial follow-ups." data-ar="سجل زمني لمناقشات العقود وزيارات المواقع والمتابعات التجارية.">Chronological ledger of contract discussions, cryogenic site visits, and commercial follow-ups.</p>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--safety-orange)] bg-[var(--surface-1)] border-s-4 border-s-[var(--safety-orange)] flex items-start gap-2.5 text-xs font-mono text-[var(--ink)]">
        <?= icon('alert-triangle', 'w-4 h-4 text-[var(--safety-orange)] flex-shrink-0 mt-0.5') ?>
        <ul class="m-0 list-disc list-inside">
          <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($success_message): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--cryo-teal)] bg-[var(--surface-1)] border-s-4 border-s-[var(--cryo-teal)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <span class="text-[10px] text-[var(--cryo-teal)] uppercase">Status 200 OK</span>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left: Log New Interaction Form -->
        <div class="lg:col-span-1 flex flex-col gap-6">
          <div class="card-notched bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--safety-orange)] rounded-sm p-6">
            <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
              <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Log Customer Interaction" data-ar="تسجيل تفاعل جديد">Log Customer Interaction</h2>
              <?= icon('message-square', 'w-4 h-4 text-[var(--safety-orange)]') ?>
            </div>
            
            <form action="<?= app_url('modules/crm_sales/client_activity.php') ?>" method="POST" class="flex flex-col gap-4">
              <?= csrf_field() ?>
              <div>
                <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Target Client Account" data-ar="حساب العميل">
                  Target Client Account <span class="text-[var(--safety-orange)]">*</span>
                </label>
                <select name="client_id" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer" required>
                  <option value="" disabled selected data-en="-- Select Client --" data-ar="-- اختر العميل --">-- Select Client --</option>
                  <?php foreach ($clients as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['reference_id']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div>
                <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Interaction Telemetry Details" data-ar="تفاصيل التفاعل">
                  Interaction Telemetry Details <span class="text-[var(--safety-orange)]">*</span>
                </label>
                <textarea name="activity_text" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" rows="5" required placeholder="e.g. Conducted site safety inspection at Yanbu plant; customer requested quote for 50x liquid oxygen cylinders..."></textarea>
              </div>
              
              <button type="submit" class="btn-cta-orange w-full py-2.5 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center justify-center gap-2 transition-colors cursor-pointer mt-1">
                <?= icon('save', 'w-4 h-4') ?>
                <span data-en="Commit to Audit Log" data-ar="حفظ في سجل التدقيق">Commit to Audit Log</span>
              </button>
            </form>
          </div>
        </div>

        <!-- Right: Chronological Activity Feed -->
        <div class="lg:col-span-2">
          <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-[var(--line)] flex items-center justify-between bg-[var(--surface-1)]">
              <div class="flex items-center gap-2">
                <?= icon('history', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Chronological Interaction Stream" data-ar="السجل الزمني للتفاعلات">Chronological Interaction Stream</h2>
              </div>
              <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">
                <?= $total_activities ?> EVENTS ARCHIVED
              </span>
            </div>
            
            <div class="p-5 flex flex-col gap-3">
              <?php if (empty($activities)): ?>
                <div class="text-center py-12 text-[var(--ink-muted)] font-mono text-xs">
                  <?= icon('inbox', 'w-8 h-8 mx-auto mb-2 text-[var(--ink-muted)] opacity-60') ?>
                  <p data-en="No interaction telemetry recorded yet." data-ar="لم يتم تسجيل أي أنشطة بعد.">No interaction telemetry recorded yet.</p>
                </div>
              <?php else: ?>
                <?php foreach ($activities as $act): ?>
                <div class="p-3.5 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] transition-colors text-xs font-mono">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-2">
                    <div class="flex items-center gap-2">
                      <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($act['reference_id'])) ?>" class="font-semibold text-sm text-[var(--ink)] hover:text-[var(--cryo-teal)] transition-colors">
                        <?= htmlspecialchars($act['company_name']) ?>
                      </a>
                      <?= tag_plate($act['reference_id'], 'teal') ?>
                    </div>
                    <span class="text-[11px] text-[var(--ink-muted)] tabular-nums">
                      <?= date('d M Y · H:i:s', strtotime($act['activity_time'])) ?> UTC
                    </span>
                  </div>
                  <p class="text-xs text-[var(--ink)] leading-relaxed font-sans mb-2.5">
                    <?= nl2br(htmlspecialchars($act['activity_text'])) ?>
                  </p>
                  <div class="flex items-center gap-2 pt-2 border-t border-[var(--line-soft)] text-[10px] text-[var(--ink-muted)] uppercase">
                    <?= icon('user', 'w-3 h-3 text-[var(--cryo-teal)]') ?>
                    <span>Logged by: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($act['author']) ?></span></span>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') && el.hasAttribute('placeholder')) {
          el.placeholder = transText;
        } else if (el.tagName === 'OPTION') {
          if(el.getAttribute('data-en') && el.value === "") el.innerText = transText; 
        } else {
          el.innerText = transText;
        }
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
      if (lang === 'ar') {
        document.body.classList.add('arabic-mode');
      } else {
        document.body.classList.remove('arabic-mode');
      }
    }

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>