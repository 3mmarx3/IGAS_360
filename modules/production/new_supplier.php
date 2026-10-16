<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('suppliers.create');

$error_message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  require_csrf();
  $reference_id = trim($_POST['reference_id'] ?? '');
  $company_name = trim($_POST['company_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');
  
  $contact_name = trim($_POST['contact_name'] ?? '');
  $parts = explode(' ', $contact_name, 2);
  $contact_first_name = $parts[0] ?: 'Unknown';
  $contact_last_name = $parts[1] ?? '';
  
  $job_title = trim($_POST['job_title'] ?? '');
  $segment = trim($_POST['segment'] ?? 'Raw Materials');
  $status = trim($_POST['status'] ?? 'pending');
  $tax_id = trim($_POST['tax_id'] ?? '');
  $cr_number = trim($_POST['cr_number'] ?? '');
  $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30 Days');

  $country = 'Saudi Arabia';
  $city = 'Default City';
  $partner_type = 'supplier';
  $temp_pass = bin2hex(random_bytes(8));
  $password_hash = password_hash($temp_pass, PASSWORD_DEFAULT);

  try {
    $stmt = $pdo->prepare("INSERT INTO partners (
      reference_id, partner_type, company_name, cr_number, country, city, address, tax_id, 
      contact_first_name, contact_last_name, job_title, email, phone, status, segment, payment_terms, password_hash
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
      $reference_id, $partner_type, $company_name, $cr_number, $country, $city, $address, $tax_id,
      $contact_first_name, $contact_last_name, $job_title, $email, $phone, $status, $segment, $payment_terms, $password_hash
    ]);

    redirect('modules/production/suppliers_directory.php?created=1');
  } catch (PDOException $e) {
    if ($e->getCode() == 23000) {
      $error_message = "This Email or CR Number is already registered in the system.";
    } else {
      $error_message = "Unable to create supplier record. Please check the entered data.";
    }
  }
}

$active_page = 'suppliers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Suppliers Directory', 'New Supplier'];

$new_supplier_id = 'SUP-' . rand(5010, 5999);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Supplier | I-GAS Enterprise" data-ar="مورد جديد | I-GAS Enterprise">New Supplier | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

  <?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400">
      <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
          <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
          <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="FEEDSTOCK PROCUREMENT ONBOARDING" data-ar="تسجيل مورد مواد خام جديد">FEEDSTOCK PROCUREMENT ONBOARDING</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ASSIGNED ID: <?= htmlspecialchars($new_supplier_id) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb Navigation & Header Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <div class="flex items-center gap-2 mb-1.5 text-xs font-mono text-neutral-500">
            <a href="<?= app_url('modules/production/suppliers_directory.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider" data-en="Suppliers Directory" data-ar="دليل الموردين">
              Suppliers Directory
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-neutral-400 rtl:rotate-180"></i>
            <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold" data-en="Registration" data-ar="تسجيل جديد">Registration</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Register New Supplier" data-ar="تسجيل مورد جديد">
            Register New Supplier
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Add a certified feedstock vendor, cryo vessel supplier, or equipment partner to the plant database." data-ar="إضافة مورد معتمد للغازات المسالة أو أوعية التبريد أو المعدات إلى قاعدة بيانات المصنع.">
            Add a certified feedstock vendor, cryo vessel supplier, or equipment partner to the plant database.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= app_url('modules/production/suppliers_directory.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <button type="submit" form="newSupplierForm" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="save" class="w-3.5 h-3.5"></i>
            <span data-en="Save Supplier Profile" data-ar="حفظ ملف المورد">Save Supplier Profile</span>
          </button>
        </div>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="p-3.5 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-sm flex items-center gap-3 text-xs font-mono">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
      </div>
      <?php endif; ?>

      <form id="newSupplierForm" action="" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="reference_id" value="<?= htmlspecialchars($new_supplier_id) ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left 2 Cols: Company & Contact -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Company Info Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316] space-y-5">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="building-2" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="1. Corporate & Plant Entity" data-ar="1. الكيان المؤسسي والمصنع">1. Corporate & Plant Entity</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Full Company Name / Legal Entity *" data-ar="اسم الشركة القانوني *">
                    Full Company Name / Legal Entity *
                  </label>
                  <input type="text" name="company_name" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Air Liquide Middle East Industrial Gases" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Procurement Email *" data-ar="البريد الإلكتروني التجاري *">
                      Procurement Email *
                    </label>
                    <input type="email" name="email" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="procurement@vendor.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                  </div>
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Main Phone *" data-ar="رقم الهاتف الرئيسي *">
                      Main Phone *
                    </label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="+966 12 600 0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Registered Plant / Office Address *" data-ar="عنوان المصنع / المكتب المسجل *">
                    Registered Plant / Office Address *
                  </label>
                  <textarea name="address" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" rows="3" placeholder="Building, Industrial Area, District, City, Postal Code..." required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
              </div>
            </div>

            <!-- Contact Details Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-5 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="2. Key Account Representative" data-ar="2. مسؤول الحساب المعتمد">2. Key Account Representative</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Contact Person Name *" data-ar="اسم مسؤول الاتصال *">
                    Contact Person Name *
                  </label>
                  <input type="text" name="contact_name" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Eng. Tarek Mansour" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Job Title / Position *" data-ar="المسمى الوظيفي *">
                    Job Title / Position *
                  </label>
                  <input type="text" name="job_title" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Regional Key Account Manager" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>" required>
                </div>
              </div>
            </div>
          </div>

          <!-- Right 1 Col: Categorization & Regulatory -->
          <div class="space-y-6">
            <!-- Categorization Card -->
            <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="tags" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Classification" data-ar="تصنيف المورد">Classification</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Generated ID" data-ar="معرف المورد">
                    Generated ID
                  </label>
                  <input type="text" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#112433] border border-[#1e3a50] rounded-sm text-slate-400 font-semibold cursor-not-allowed" value="<?= htmlspecialchars($new_supplier_id) ?>" disabled>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Supply Category *" data-ar="فئة التوريد *">
                    Supply Category *
                  </label>
                  <select name="segment" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="" <?= empty($_POST['segment']) ? 'selected' : '' ?> disabled data-en="Select category..." data-ar="اختر الفئة...">Select category...</option>
                    <option value="Raw Materials" <?= ($_POST['segment'] ?? '') == 'Raw Materials' ? 'selected' : '' ?> data-en="Raw Materials / Bulk Liquid Gases" data-ar="مواد خام / غازات سائلة سائبة">Raw Materials / Bulk Liquid Gases</option>
                    <option value="Cylinders" <?= ($_POST['segment'] ?? '') == 'Cylinders' ? 'selected' : '' ?> data-en="Cylinders & Cryogenic Vessels" data-ar="أسطوانات وأوعية تبريد">Cylinders & Cryogenic Vessels</option>
                    <option value="Chemicals" <?= ($_POST['segment'] ?? '') == 'Chemicals' ? 'selected' : '' ?> data-en="Chemicals & Purge Agents" data-ar="مواد كيميائية وعوامل تطهير">Chemicals & Purge Agents</option>
                    <option value="Transportation" <?= ($_POST['segment'] ?? '') == 'Transportation' ? 'selected' : '' ?> data-en="Cryo Road Transport & Logistics" data-ar="نقل وتوزيع مبرد">Cryo Road Transport & Logistics</option>
                    <option value="Spare Parts" <?= ($_POST['segment'] ?? '') == 'Spare Parts' ? 'selected' : '' ?> data-en="Valves, Regulators & Spares" data-ar="صمامات ومنظمات وقطع غيار">Valves, Regulators & Spares</option>
                    <option value="Office" <?= ($_POST['segment'] ?? '') == 'Office' ? 'selected' : '' ?> data-en="Industrial Plant Services" data-ar="خدمات المصنع الصناعية">Industrial Plant Services</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Onboarding State *" data-ar="حالة التسجيل *">
                    Onboarding State *
                  </label>
                  <select name="status" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="approved" <?= ($_POST['status'] ?? '') == 'approved' ? 'selected' : '' ?> data-en="Active (Approved)" data-ar="نشط (معتمد)">Active (Approved)</option>
                    <option value="pending" <?= ($_POST['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?> data-en="Pending Quality Audit" data-ar="قيد تدقيق الجودة">Pending Quality Audit</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Legal & Billing Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="file-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Compliance & Tax" data-ar="الامتثال والضرائب">Compliance & Tax</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Tax ID / VAT Number *" data-ar="الرقم الضريبي *">
                    Tax ID / VAT Number *
                  </label>
                  <input type="text" name="tax_id" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="15-digit ZATCA VAT Number" value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>" required>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Commercial Registry (CR) *" data-ar="السجل التجاري *">
                    Commercial Registry (CR) *
                  </label>
                  <input type="text" name="cr_number" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="10-digit CR Number" value="<?= htmlspecialchars($_POST['cr_number'] ?? '') ?>" required>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Agreed Payment Terms *" data-ar="شروط السداد *">
                    Agreed Payment Terms *
                  </label>
                  <select name="payment_terms" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="Net 15 Days" <?= ($_POST['payment_terms'] ?? '') == 'Net 15 Days' ? 'selected' : '' ?>>Net 15 Days</option>
                    <option value="Net 30 Days" <?= ($_POST['payment_terms'] ?? 'Net 30 Days') == 'Net 30 Days' ? 'selected' : '' ?>>Net 30 Days</option>
                    <option value="Net 60 Days" <?= ($_POST['payment_terms'] ?? '') == 'Net 60 Days' ? 'selected' : '' ?>>Net 60 Days</option>
                    <option value="Cash in Advance" <?= ($_POST['payment_terms'] ?? '') == 'Cash in Advance' ? 'selected' : '' ?>>Cash in Advance (CIA)</option>
                    <option value="Cash on Delivery" <?= ($_POST['payment_terms'] ?? '') == 'Cash on Delivery' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
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
  </script>
</body>
</html>