<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission($pdo, 'clients.create');

$active_page = 'clients';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'New Client'];

$new_client_id = 'CLI-' . rand(1050, 9999);
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $company_name = trim($_POST['company_name'] ?? '');
  $tax_id    = trim($_POST['tax_id'] ?? '');
  $cr_number  = trim($_POST['cr_number'] ?? ''); 

  $entity_type = trim($_POST['entity_type'] ?? 'Corporate');
  $segment   = trim($_POST['segment'] ?? 'General');

  $full_name = trim($_POST['full_name'] ?? '');
  $name_parts = explode(' ', $full_name, 2);
  $first_name = $name_parts[0] ?? '';
  $last_name = $name_parts[1] ?? '';
  $job_title = trim($_POST['job_title'] ?? '');

  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  $country   = trim($_POST['country'] ?? 'Saudi Arabia'); 
  $city    = trim($_POST['city'] ?? '');
  $postal_code = trim($_POST['postal_code'] ?? '');
  $address   = trim($_POST['address'] ?? '');

  $status    = trim($_POST['status'] ?? 'approved');
  $credit_limit = floatval($_POST['credit_limit'] ?? 0);
  $payment_terms = trim($_POST['payment_terms'] ?? 'cod');

  $sales_rep_raw = $_POST['sales_rep_id'] ?? [];
  $sales_rep_id = is_array($sales_rep_raw) ? implode(',', $sales_rep_raw) : $sales_rep_raw;

  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  if (empty($password) || $password !== $confirm_password) {
    $error_message = "Registration Failed: Passwords do not match or are empty.";
  } else {
    $partner_type   = 'client';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
      $sql = "INSERT INTO partners (
          reference_id, partner_type, company_name, cr_number, tax_id, 
          entity_type, segment, contact_first_name, contact_last_name, 
          job_title, email, phone, country, city, postal_code, address, 
          status, credit_limit, payment_terms, password_hash, sales_rep_id
        ) VALUES (
          ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        $new_client_id, $partner_type, $company_name, $cr_number, $tax_id,
        $entity_type, $segment, $first_name, $last_name,
        $job_title, $email, $phone, $country, $city, $postal_code, $address,
        $status, $credit_limit, $payment_terms, $hashed_password, $sales_rep_id
      ]);

      header("Location: clients_directory.php");
      exit;
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        $error_message = "Registration Failed: CR Number, Email, or Customer ID is already registered in the system.";
      } else {
        $error_message = "Database Error: " . $e->getMessage();
      }
    }
  }
}

$stmt_reps = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Sales Rep', 'Sales Manager', 'Admin') AND status = 'Active' ORDER BY full_name ASC");
$sales_reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Client Account | I-GAS Enterprise" data-ar="تسجيل عميل جديد | I-GAS Enterprise">New Client Account | I-GAS Enterprise</title>
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
        <span data-en="Account Onboarding Engine" data-ar="محرك تسجيل الحسابات">Account Onboarding Engine</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Allocated ID: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($new_client_id) ?></span></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Verification: <span class="text-[var(--cryo-teal)] font-semibold">ZATCA Compliant</span></span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">Security: TLS 1.3 / AES-256</span>
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
            <?= tag_plate('CRM-REG-01', 'orange') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Client Onboarding Registration" data-ar="تسجيل حساب عميل جديد">Client Onboarding Registration</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]" data-en="Register New Client Account" data-ar="تسجيل حساب عميل جديد">Register New Client Account</h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono" data-en="Bind commercial entity, configure supply credit parameters, and assign dedicated sales representation." data-ar="ربط الكيان التجاري، وتعيين معايير الائتمان، وتخصيص مسؤول المبيعات المعتمد.">Bind commercial entity, configure supply credit parameters, and assign dedicated sales representation.</p>
        </div>
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="px-4 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono transition-colors" data-en="Cancel" data-ar="إلغاء">
            Cancel
          </a>
          <button type="submit" form="newClientForm" class="btn-cta-orange px-5 py-2 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-2 transition-colors cursor-pointer">
            <?= icon('check', 'w-4 h-4') ?>
            <span data-en="Save Account" data-ar="حفظ العميل">Save Account</span>
          </button>
        </div>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="mb-6 p-4 rounded-sm border border-[var(--safety-orange)] bg-[var(--surface-1)] border-s-4 border-s-[var(--safety-orange)] flex items-center justify-between text-xs font-mono text-[var(--ink)]">
        <div class="flex items-center gap-2.5">
          <?= icon('alert-triangle', 'w-4 h-4 text-[var(--safety-orange)]') ?>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <span class="text-[10px] text-[var(--safety-orange)] uppercase font-semibold">Validation Fault</span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" id="newClientForm">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          
          <!-- Left: Main Form Fields -->
          <div class="lg:col-span-2 flex flex-col gap-6">
            
            <!-- Section 1: Company Information -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('building-2', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="1. Corporate & Tax Entity" data-ar="1. الكيان التجاري والضريبي">1. Corporate & Tax Entity</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">LEGAL ENTITY</span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Company Legal Name" data-ar="اسم الشركة القانوني">
                    Company Legal Name <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="company_name" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="e.g. Saudi Aramco Lubricants Co." required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="VAT / Tax Number (ZATCA)" data-ar="الرقم الضريبي">VAT / Tax Number (ZATCA)</label>
                  <input type="text" name="tax_id" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="300000000000003">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Commercial Registration (CR)" data-ar="السجل التجاري">
                    Commercial Registration (CR) <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="cr_number" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="1010000000" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Customer Entity Type" data-ar="نوع الكيان">Customer Entity Type</label>
                  <select name="entity_type" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer" required>
                    <option value="Corporate" data-en="Corporate Enterprise" data-ar="شركة كبرى">Corporate Enterprise</option>
                    <option value="SME" data-en="SME / Industrial Workshop" data-ar="منشأة متوسطة وصغيرة">SME / Industrial Workshop</option>
                    <option value="Government" data-en="Government / Institutional" data-ar="جهة حكومية">Government / Institutional</option>
                    <option value="Individual" data-en="Individual Proprietorship" data-ar="مؤسسة فردية">Individual Proprietorship</option>
                  </select>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Industrial Gas Segment" data-ar="قطاع الصناعة">Industrial Gas Segment</label>
                  <select name="segment" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer">
                    <option value="General" data-en="General Industrial" data-ar="صناعي عام">General Industrial</option>
                    <option value="Industrial Gas" data-en="Cryogenic & Bulk Gases" data-ar="غازات صناعية ومبردة">Cryogenic & Bulk Gases</option>
                    <option value="Medical" data-en="Healthcare & Medical Gas" data-ar="غازات طبية ومستشفيات">Healthcare & Medical Gas</option>
                    <option value="Food & Beverage" data-en="Food Grade & Freezing" data-ar="أغذية وتجميد">Food Grade & Freezing</option>
                    <option value="Manufacturing" data-en="Heavy Manufacturing & Welding" data-ar="تصنيع ولحام">Heavy Manufacturing & Welding</option>
                    <option value="Construction" data-en="Civil & Petrochemical Engineering" data-ar="بتروكيماويات وإنشاءات">Civil & Petrochemical Engineering</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Section 2: Contact Details -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('user-check', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="2. Primary Contact & Plant Officer" data-ar="2. مسؤول الاتصال والمنشأة">2. Primary Contact & Plant Officer</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">PERSONNEL</span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Officer Full Name" data-ar="الاسم الكامل">
                    Officer Full Name <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="full_name" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="e.g. Eng. Khalid Al-Otaibi" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Designation / Job Title" data-ar="المسمى الوظيفي">Designation / Job Title</label>
                  <input type="text" name="job_title" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="e.g. Plant Procurement Director">
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Official Email" data-ar="البريد الإلكتروني">
                    Official Email <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="email" name="email" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="khalid@client.com.sa" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Emergency Mobile Phone" data-ar="رقم الجوال">
                    Emergency Mobile Phone <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="tel" name="phone" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="+966 50 123 4567" required>
                </div>
              </div>
            </div>

            <!-- Section 3: Delivery Facility Address -->
            <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <div class="flex items-center gap-2">
                  <?= icon('map-pin', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                  <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="3. Gas Delivery Site & Logistics Coordinates" data-ar="3. موقع التوريد والإحداثيات اللوجستية">3. Gas Delivery Site & Logistics Coordinates</h2>
                </div>
                <span class="text-[10px] font-mono text-[var(--ink-muted)] uppercase tracking-wider">DISPATCH LOCATION</span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Country" data-ar="الدولة">Country</label>
                  <input type="text" name="country" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" value="Saudi Arabia" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="City / Industrial Zone" data-ar="المدينة / المنطقة الصناعية">
                    City / Industrial Zone <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <input type="text" name="city" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="e.g. Yanbu Industrial City" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Postal / National Address Code" data-ar="الرمز البريدي">Postal / National Address Code</label>
                  <input type="text" name="postal_code" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="21423">
                </div>
                <div class="col-span-2">
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Complete Street & Tank Unloading Bay Address" data-ar="العنوان التفصيلي وموقع تفريغ الخزانات">
                    Complete Street & Tank Unloading Bay Address <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <textarea name="address" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" rows="2" placeholder="Sector 4, Gate 12, Heavy Cryo Tank Farm Bay 2" required></textarea>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column: Account Configuration & Quotas -->
          <div class="lg:col-span-1 flex flex-col gap-6">
            
            <div class="card-notched bg-[var(--surface-1)] border border-[var(--line)] rounded-sm p-6">
              <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-5">
                <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Account Credentials & Quotas" data-ar="بيانات الاعتماد والحصص">Account Credentials & Quotas</h2>
                <?= icon('shield', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
              </div>
              
              <div class="flex flex-col gap-4">
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Assigned Customer ID" data-ar="معرف العميل المخصص">Assigned Customer ID</label>
                  <input type="text" class="w-full bg-[var(--surface-2)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink-muted)] cursor-not-allowed" value="<?= htmlspecialchars($new_client_id) ?>" disabled>
                </div>
                
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Portal Access Password" data-ar="كلمة مرور البوابة">
                    Portal Access Password <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <div class="relative flex items-center">
                    <input type="password" id="password_input" name="password" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 pe-10 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="••••••••" required>
                    <button type="button" class="toggle-password absolute end-2.5 text-[var(--ink-muted)] hover:text-[var(--ink)] focus:outline-none cursor-pointer" data-target="password_input">
                      <?= icon('eye', 'w-4 h-4') ?>
                    </button>
                  </div>
                </div>

                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Confirm Password" data-ar="تأكيد كلمة المرور">
                    Confirm Password <span class="text-[var(--safety-orange)]">*</span>
                  </label>
                  <div class="relative flex items-center">
                    <input type="password" id="confirm_password_input" name="confirm_password" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 pe-10 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="••••••••" required>
                    <button type="button" class="toggle-password absolute end-2.5 text-[var(--ink-muted)] hover:text-[var(--ink)] focus:outline-none cursor-pointer" data-target="confirm_password_input">
                      <?= icon('eye', 'w-4 h-4') ?>
                    </button>
                  </div>
                </div>
                
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Account Operational Status" data-ar="حالة الحساب">Account Operational Status</label>
                  <select name="status" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer" required>
                    <option value="approved" selected data-en="Active / Approved" data-ar="نشط وموافق عليه">Active / Approved</option>
                    <option value="suspended" data-en="Suspended / Inactive" data-ar="معلق / غير نشط">Suspended / Inactive</option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Assigned Sales Officer(s)" data-ar="مندوبو المبيعات المعينون">Assigned Sales Officer(s)</label>
                  <div class="border border-[var(--line)] rounded-sm p-3 bg-[var(--surface-0)] overflow-y-auto max-h-36">
                    <?php if(empty($sales_reps)): ?>
                      <span class="text-xs font-mono text-[var(--ink-muted)]">No active sales officers registered.</span>
                    <?php else: ?>
                      <div class="flex flex-col gap-2">
                        <?php foreach($sales_reps as $rep): ?>
                          <label class="flex items-center gap-2 text-xs font-mono cursor-pointer hover:text-[var(--ink)] transition-colors text-[var(--ink-muted)]">
                            <input type="checkbox" name="sales_rep_id[]" value="<?= htmlspecialchars($rep['id']) ?>" class="rounded-xs border border-[var(--line)]">
                            <span class="text-[var(--ink)] font-medium"><?= htmlspecialchars($rep['full_name']) ?></span> 
                            <span class="text-[10px] text-[var(--ink-muted)]">(<?= htmlspecialchars($rep['role']) ?>)</span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1">Multi-rep allocation supported.</p>
                </div>
                
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Credit Limit (SAR)" data-ar="الحد الائتماني (ريال)">Credit Limit (SAR)</label>
                  <input type="number" name="credit_limit" step="0.01" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono tabular-nums text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors" placeholder="0.00" value="0.00" required>
                </div>
                
                <div>
                  <label class="block text-[11px] font-mono uppercase font-semibold text-[var(--ink-muted)] mb-1.5" data-en="Commercial Payment Terms" data-ar="شروط الدفع">Commercial Payment Terms</label>
                  <select name="payment_terms" class="w-full bg-[var(--surface-0)] border border-[var(--line)] rounded-sm px-3 py-2 text-xs font-mono text-[var(--ink)] focus:border-[var(--frost-cyan)] focus:outline-none transition-colors cursor-pointer" required>
                    <option value="cod" selected data-en="Cash on Delivery (COD)" data-ar="الدفع عند الاستلام">Cash on Delivery (COD)</option>
                    <option value="prepaid" data-en="100% Prepaid" data-ar="دفع مسبق 100%">100% Prepaid</option>
                    <option value="net30" data-en="Net 30 Days (Supply Agreement)" data-ar="آجل 30 يوم">Net 30 Days (Supply Agreement)</option>
                    <option value="net60" data-en="Net 60 Days (Enterprise Contract)" data-ar="آجل 60 يوم">Net 60 Days (Enterprise Contract)</option>
                  </select>
                </div>
              </div>
            </div>

          </div>
        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();

    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        
        if (input.type === 'password') {
          input.type = 'text';
          this.innerHTML = '<i data-lucide="eye-off" class="w-4 h-4"></i>';
        } else {
          input.type = 'password';
          this.innerHTML = '<i data-lucide="eye" class="w-4 h-4"></i>';
        }
        
        lucide.createIcons();
      });
    });

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
          el.placeholder = transText;
        } else if (el.tagName === 'OPTION') {
          if (el.getAttribute('data-en') && el.value === "") {
            el.innerText = transText;
          } else {
            el.innerText = transText;
          }
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

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>