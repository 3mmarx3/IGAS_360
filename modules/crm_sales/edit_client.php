<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

require_permission($pdo, 'clients.edit');

$active_page = 'clients';
$base_url  = '../../'; 

$client_id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($client_id)) {
  $firstRef = $pdo->query("SELECT reference_id FROM partners WHERE partner_type = 'client' ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstRef) {
    $client_id = $firstRef;
  } else {
    redirect('modules/crm_sales/clients_directory.php');
  }
}

$stmt = $pdo->prepare("SELECT * FROM partners WHERE (reference_id = ? OR CAST(id AS CHAR) = ?) AND partner_type = 'client'");
$stmt->execute([$client_id, $client_id]);
$db_client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_client) {
  redirect('modules/crm_sales/clients_directory.php');
}

if (!check_record_ownership($pdo, 'partners', (int)$db_client['id'], 'sales_rep_id')) {
  http_response_code(403);
  render_error_page(403, 'Access Denied', 'You are not authorized to view or edit this client record.');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();

  $company_name = trim($_POST['company_name'] ?? '');
  $tax_id    = trim($_POST['tax_id'] ?? '');
  $entity_type = trim($_POST['entity_type'] ?? 'Corporate');
  $segment   = trim($_POST['segment'] ?? 'Industrial Gas');
  $contact_name = trim($_POST['contact_name'] ?? '');
  $contact_role = trim($_POST['contact_role'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $phone    = trim($_POST['phone'] ?? '');
  $address   = trim($_POST['address'] ?? '');
  $status    = trim($_POST['status'] ?? 'active');

  $name_parts = explode(' ', $contact_name, 2);
  $first_name = $name_parts[0] ?? '';
  $last_name = $name_parts[1] ?? '';

  try {
    $update = $pdo->prepare("
      UPDATE partners SET 
        company_name = ?, 
        tax_id = ?, 
        entity_type = ?, 
        segment = ?, 
        contact_first_name = ?, 
        contact_last_name = ?, 
        job_title = ?, 
        email = ?, 
        phone = ?, 
        address = ?, 
        status = ?
      WHERE id = ? AND partner_type = 'client'
    ");
    $update->execute([
      $company_name, $tax_id, $entity_type, $segment, 
      $first_name, $last_name, $contact_role, 
      $email, $phone, $address, $status, $db_client['id']
    ]);

    $act_text = "Updated client specifications for " . $company_name;
    $act_stmt = $pdo->prepare("INSERT INTO client_activities (client_id, activity_text, activity_time, author) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
    $act_stmt->execute([$db_client['id'], $act_text, $_SESSION['user_name'] ?? 'System']);

    $success_message = "Client specifications updated successfully.";
    
    $stmt->execute([$client_id, $client_id]);
    $db_client = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $error_message = "Update failed: " . $e->getMessage();
  }
}

$client = [
  'id'      => $db_client['reference_id'] ?? ('CLI-' . $db_client['id']),
  'name'     => $db_client['company_name'] ?? 'Client',
  'type'     => $db_client['entity_type'] ?? 'Corporate',
  'segment'   => $db_client['segment'] ?? 'General',
  'status'    => $db_client['status'] ?? 'active',
  'since'    => $db_client['created_at'] ?? date('Y-m-d'),
  'contact_name' => trim(($db_client['contact_first_name'] ?? '') . ' ' . ($db_client['contact_last_name'] ?? '')),
  'contact_role' => $db_client['job_title'] ?? '',
  'email'    => $db_client['email'] ?? '',
  'phone'    => $db_client['phone'] ?? '',
  'address'   => $db_client['address'] ?? '',
  'tax_id'    => $db_client['tax_id'] ?? ''
];

$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'Edit Profile'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile - <?= htmlspecialchars($client['name']) ?> | I-GAS Enterprise</title>
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
        <span>Account Editor Active</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Client Reference: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($client['id']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Current Status: <span class="text-[var(--cryo-teal)] font-semibold"><?= htmlspecialchars(strtoupper($client['status'])) ?></span></span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">IDOR Protected</span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">

      <!-- Breadcrumb Back Link -->
      <div class="mb-4">
        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($client['id'])) ?>" class="inline-flex items-center gap-1.5 text-xs font-mono text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors">
          <?= icon('arrow-left', 'w-3.5 h-3.5 rtl:rotate-180') ?>
          <span data-en="Back to Client Profile" data-ar="العودة إلى ملف العميل">Back to Client Profile</span>
        </a>
      </div>

      <!-- Page Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate($client['id'], 'teal') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Client Specification Modification" data-ar="تعديل مواصفات الحساب">Client Specification Modification</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]">Edit Client: <?= htmlspecialchars($client['name']) ?></h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono" data-en="Update legal registration, technical representative contacts, and tax IDs." data-ar="تحديث بيانات السجل التجاري، مسؤول الاتصال، والأرقام الضريبية.">Update legal registration, technical representative contacts, and tax IDs.</p>
        </div>
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($client['id'])) ?>" class="px-4 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono transition-colors" data-en="Cancel" data-ar="إلغاء">
            Cancel
          </a>
          <button type="submit" form="editClientForm" class="btn-cta-orange px-5 py-2 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-2 transition-colors cursor-pointer">
            <?= icon('save', 'w-4 h-4') ?>
            <span data-en="Save Changes" data-ar="حفظ التغييرات">Save Changes</span>
          </button>
        </div>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--safety-orange)] bg-[var(--surface-1)] border-s-4 border-s-[var(--safety-orange)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('alert-triangle', 'w-4 h-4 text-[var(--safety-orange)]') ?>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <span class="text-[10px] text-[var(--safety-orange)] uppercase font-semibold">Error</span>
      </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--cryo-teal)] bg-[var(--surface-1)] border-s-4 border-s-[var(--cryo-teal)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <span class="text-[10px] text-[var(--cryo-teal)] uppercase">Status 200 OK</span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" id="editClientForm">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          
          <!-- Left: Main Form Fields -->
          <div class="lg:col-span-2 flex flex-col gap-6">
            
            <!-- 1. Company Information -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('building-2', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="1. Corporate & Tax Entity" data-ar="1. الكيان التجاري والضريبي">1. Corporate & Tax Entity</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 01</span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Company / Client Legal Name" data-ar="اسم الشركة القانوني">
                    Company / Client Legal Name <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="company_name" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['name']) ?>" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Client Reference ID" data-ar="معرف العميل">Client Reference ID</label>
                  <input type="text" class="w-full bg-[var(--surface-2)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink-muted)] cursor-not-allowed" value="<?= htmlspecialchars($client['id']) ?>" disabled>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="ZATCA Tax / VAT ID" data-ar="الرقم الضريبي">ZATCA Tax / VAT ID</label>
                  <input type="text" name="tax_id" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['tax_id']) ?>">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Entity Classification" data-ar="تصنيف الكيان">Entity Classification</label>
                  <select name="entity_type" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="Corporate" <?= $client['type'] === 'Corporate' ? 'selected' : '' ?> data-en="Corporate Enterprise" data-ar="شركة كبرى">Corporate Enterprise</option>
                    <option value="SME" <?= $client['type'] === 'SME' ? 'selected' : '' ?> data-en="SME / Industrial Workshop" data-ar="منشأة صغيرة أو متوسطة">SME / Industrial Workshop</option>
                    <option value="Government" <?= $client['type'] === 'Government' ? 'selected' : '' ?> data-en="Government / Institutional" data-ar="حكومي">Government / Institutional</option>
                    <option value="Individual" <?= $client['type'] === 'Individual' ? 'selected' : '' ?> data-en="Individual Proprietorship" data-ar="مؤسسة فردية">Individual Proprietorship</option>
                  </select>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Industry Gas Segment" data-ar="قطاع الصناعة">Industry Gas Segment</label>
                  <select name="segment" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="Industrial Gas" <?= $client['segment'] === 'Industrial Gas' ? 'selected' : '' ?> data-en="Cryogenic & Bulk Gases" data-ar="غازات صناعية ومبردة">Cryogenic & Bulk Gases</option>
                    <option value="Medical" <?= $client['segment'] === 'Medical' ? 'selected' : '' ?> data-en="Medical & Healthcare" data-ar="غازات طبية">Medical & Healthcare</option>
                    <option value="Food & Beverage" <?= $client['segment'] === 'Food & Beverage' ? 'selected' : '' ?> data-en="Food Grade & Freezing" data-ar="أغذية ومشروبات">Food Grade & Freezing</option>
                    <option value="Manufacturing" <?= $client['segment'] === 'Manufacturing' ? 'selected' : '' ?> data-en="Heavy Manufacturing & Welding" data-ar="تصنيع ولحام">Heavy Manufacturing & Welding</option>
                    <option value="General" <?= $client['segment'] === 'General' ? 'selected' : '' ?> data-en="General Industrial" data-ar="عام">General Industrial</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 2. Primary Contact Details -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('user', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="2. Contact Personnel Specifications" data-ar="2. مواصفات مسؤول الاتصال">2. Contact Personnel Specifications</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 02</span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Contact Full Name" data-ar="الاسم الكامل">Contact Full Name</label>
                  <input type="text" name="contact_name" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['contact_name']) ?>">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Plant Role / Job Title" data-ar="المسمى الوظيفي">Plant Role / Job Title</label>
                  <input type="text" name="contact_role" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['contact_role']) ?>">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Official Email Address" data-ar="البريد الإلكتروني">Official Email Address</label>
                  <input type="email" name="email" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['email']) ?>">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Contact Phone Number" data-ar="رقم الهاتف">Contact Phone Number</label>
                  <input type="tel" name="phone" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="<?= htmlspecialchars($client['phone']) ?>">
                </div>
              </div>
            </div>

            <!-- 3. Delivery & Billing Address -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('map-pin', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="3. Plant Delivery Bay & Address" data-ar="3. موقع التوريد وعنوان المنشأة">3. Plant Delivery Bay & Address</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">SECTION 03</span>
              </div>
              
              <div class="grid grid-cols-1 gap-5">
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Complete Delivery Site Address" data-ar="العنوان الكامل لموقع التوريد">Complete Delivery Site Address</label>
                  <textarea name="address" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" rows="3"><?= htmlspecialchars($client['address']) ?></textarea>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column: Status & Procedures -->
          <div class="lg:col-span-1 flex flex-col gap-6">
            
            <div class="card-notched bg-[var(--surface-1)] border border-[var(--line)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Account Operational Status" data-ar="الحالة التشغيلية للحساب">Account Operational Status</h2>
                <?= icon('activity', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
              </div>
              
              <div class="flex flex-col gap-4">
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Operational Lifecycle Status" data-ar="حالة الدورة التشغيلية">Operational Lifecycle Status</label>
                  <select name="status" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?> data-en="Active · Cleared for Supply" data-ar="نشط ومصرح بالتوريد">Active · Cleared for Supply</option>
                    <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?> data-en="Inactive / Closed" data-ar="غير نشط">Inactive / Closed</option>
                    <option value="on_hold" <?= $client['status'] === 'on_hold' ? 'selected' : '' ?> data-en="On Hold (Credit Review)" data-ar="معلق (مراجعة ائتمانية)">On Hold (Credit Review)</option>
                    <option value="suspended" <?= $client['status'] === 'suspended' ? 'selected' : '' ?> data-en="Suspended (Compliance)" data-ar="موقوف (امتثال)">Suspended (Compliance)</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Account Inception Date" data-ar="تاريخ بدء الحساب">Account Inception Date</label>
                  <input type="text" class="w-full bg-[var(--surface-2)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink-muted)] cursor-not-allowed" value="<?= htmlspecialchars(date('d M Y', strtotime($client['since']))) ?>" disabled>
                </div>
              </div>
            </div>

            <!-- Danger Control Card -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--safety-orange)] rounded-sm p-6">
              <div class="flex items-center gap-2 mb-3">
                <?= icon('alert-triangle', 'w-4 h-4 text-[var(--safety-orange)]') ?>
                <h3 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--safety-orange)]" data-en="Decommission Protocol" data-ar="بروتوكول إلغاء التفعيل">Decommission Protocol</h3>
              </div>
              <p class="text-xs font-mono text-[var(--ink-muted)] leading-relaxed mb-4" data-en="Suspending or archiving this account retains audit records while halting new cryogenic gas dispatches." data-ar="يؤدي تعليق هذا الحساب إلى حفظ سجلات التدقيق مع إيقاف أي طلبات توريد غاز جديدة.">
                Suspending or archiving this account retains audit records while halting new cryogenic gas dispatches.
              </p>
              <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="w-full py-2 px-3 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center justify-center transition-colors">
                Return to Client Ledger
              </a>
            </div>

          </div>
        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
          el.placeholder = transText;
        } else if (el.tagName === 'OPTION') {
          el.innerText = transText;
        } else {
          el.innerHTML = transText;
        }
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
      if (lang === 'ar') {
        document.body.classList.add('arabic-mode');
      } else {
        document.body.classList.remove('arabic-mode');
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>