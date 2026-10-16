<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('suppliers.edit');

$active_page = 'suppliers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Suppliers Directory', 'Edit Supplier'];

$supplier_ref = $_GET['id'] ?? null;
$supplier = null;

if ($supplier_ref) {
  $stmt = $pdo->prepare("SELECT * FROM partners WHERE (reference_id = ? OR CAST(id AS CHAR) = ?) AND partner_type = 'supplier'");
  $stmt->execute([$supplier_ref, $supplier_ref]);
  $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$supplier) {
  $stmt = $pdo->query("SELECT * FROM partners WHERE partner_type = 'supplier' ORDER BY id ASC LIMIT 1");
  $supplier = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
  if ($supplier) {
    $supplier_ref = $supplier['reference_id'];
  }
}

if (!$supplier) {
  redirect('modules/production/suppliers_directory.php');
}

$error_message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  require_csrf();
  $company_name = trim($_POST['company_name'] ?? '');
  $tax_id    = trim($_POST['tax_id'] ?? '');
  $cr_number  = trim($_POST['cr_number'] ?? ''); 
  
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
  $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30 Days');

  try {
    $sql = "UPDATE partners SET 
          company_name = ?, cr_number = ?, tax_id = ?, 
          segment = ?, contact_first_name = ?, contact_last_name = ?, 
          job_title = ?, email = ?, phone = ?, country = ?, city = ?, 
          postal_code = ?, address = ?, status = ?, payment_terms = ?
        WHERE reference_id = ? AND partner_type = 'supplier'";
        
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([
      $company_name, $cr_number, $tax_id,
      $segment, $first_name, $last_name,
      $job_title, $email, $phone, $country, $city, 
      $postal_code, $address, $status, $payment_terms,
      $supplier_ref
    ]);

    redirect('modules/production/supplier_profile.php?id=' . urlencode($supplier_ref) . '&updated=1');
  } catch (PDOException $e) {
    if ($e->getCode() == 23000) {
      $error_message = "Update Failed: CR Number or Email is already registered to another partner.";
    } else {
      $error_message = "Unable to update supplier profile. Please check the entered data.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Edit Supplier Profile | I-GAS Enterprise" data-ar="تعديل ملف المورد | I-GAS Enterprise">Edit Supplier Profile | I-GAS Enterprise</title>
  
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
        <span data-en="FEEDSTOCK VENDOR CONFIGURATION" data-ar="تكوين بيانات مورد المواد الخام">FEEDSTOCK VENDOR CONFIGURATION</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ID: <?= htmlspecialchars($supplier_ref) ?></span>
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
            <a href="<?= app_url('modules/production/supplier_profile.php?id=' . urlencode($supplier_ref)) ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider">
              <?= htmlspecialchars($supplier_ref) ?>
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-neutral-400 rtl:rotate-180"></i>
            <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold" data-en="Edit Profile" data-ar="تعديل الملف">Edit Profile</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Edit Supplier Profile" data-ar="تعديل ملف المورد">
            Edit Supplier Profile
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1">
            <?= htmlspecialchars($supplier['company_name'] ?? '') ?> · <?= htmlspecialchars($supplier_ref) ?>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= app_url('modules/production/supplier_profile.php?id=' . urlencode($supplier_ref)) ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <button type="submit" form="editSupplierForm" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="save" class="w-3.5 h-3.5"></i>
            <span data-en="Save Changes" data-ar="حفظ التعديلات">Save Changes</span>
          </button>
        </div>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="p-3.5 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-sm flex items-center gap-3 text-xs font-mono">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
      </div>
      <?php endif; ?>

      <form id="editSupplierForm" action="" method="POST">
        <?= csrf_field() ?>

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
                  <input type="text" name="company_name" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>" required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Tax ID (VAT Number)" data-ar="الرقم الضريبي">
                      Tax ID (VAT Number)
                    </label>
                    <input type="text" name="tax_id" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['tax_id'] ?? '') ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Commercial Registry (CR) *" data-ar="السجل التجاري *">
                      Commercial Registry (CR) *
                    </label>
                    <input type="text" name="cr_number" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['cr_number'] ?? '') ?>" required>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Supply Category *" data-ar="فئة التوريد *">
                    Supply Category *
                  </label>
                  <select name="segment" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <option value="Raw Materials" <?= ($supplier['segment'] == 'Raw Materials') ? 'selected' : '' ?>>Raw Materials / Bulk Liquid Gases</option>
                    <option value="Cylinders & Containment" <?= (strpos($supplier['segment'], 'Cylinder') !== false) ? 'selected' : '' ?>>Cylinders & Cryogenic Vessels</option>
                    <option value="Chemicals & Additives" <?= (strpos($supplier['segment'], 'Chemical') !== false) ? 'selected' : '' ?>>Chemicals & Purge Agents</option>
                    <option value="Transportation & Logistics" <?= (strpos($supplier['segment'], 'Transport') !== false) ? 'selected' : '' ?>>Cryo Road Transport & Logistics</option>
                    <option value="Fleet Spare Parts" <?= (strpos($supplier['segment'], 'Spare') !== false) ? 'selected' : '' ?>>Valves, Regulators & Spares</option>
                    <option value="Office Supplies & Services" <?= (strpos($supplier['segment'], 'Office') !== false) ? 'selected' : '' ?>>Industrial Plant Services</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Contact Details Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-5 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="2. Key Contact Details" data-ar="2. تفاصيل جهة الاتصال">2. Key Contact Details</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Contact Person Name *" data-ar="اسم جهة الاتصال *">
                    Contact Person Name *
                  </label>
                  <input type="text" name="full_name" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars(trim(($supplier['contact_first_name'] ?? '') . ' ' . ($supplier['contact_last_name'] ?? ''))) ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Job Title / Position" data-ar="المسمى الوظيفي">
                    Job Title / Position
                  </label>
                  <input type="text" name="job_title" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['job_title'] ?? '') ?>">
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Procurement Email *" data-ar="البريد الإلكتروني *">
                    Procurement Email *
                  </label>
                  <input type="email" name="email" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Main Phone *" data-ar="رقم الهاتف *">
                    Main Phone *
                  </label>
                  <input type="tel" name="phone" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" required>
                </div>
              </div>
            </div>

            <!-- Address Details Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-5 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="3. Plant & Office Address" data-ar="3. عنوان المصنع والمكتب">3. Plant & Office Address</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Country *" data-ar="الدولة *">
                    Country *
                  </label>
                  <input type="text" name="country" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['country'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="City *" data-ar="المدينة *">
                    City *
                  </label>
                  <input type="text" name="city" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['city'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Postal Code" data-ar="الرمز البريدي">
                    Postal Code
                  </label>
                  <input type="text" name="postal_code" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($supplier['postal_code'] ?? '') ?>">
                </div>
                <div class="sm:col-span-3">
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Complete Street Address *" data-ar="العنوان التفصيلي *">
                    Complete Street Address *
                  </label>
                  <textarea name="address" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" rows="3" required><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Right 1 Col: Status & Terms -->
          <div class="space-y-6">
            <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="sliders" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Account Controls" data-ar="إعدادات الحساب">Account Controls</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Supplier ID" data-ar="معرف المورد">
                    Supplier ID
                  </label>
                  <input type="text" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#112433] border border-[#1e3a50] rounded-sm text-slate-400 font-semibold cursor-not-allowed" value="<?= htmlspecialchars($supplier_ref) ?>" disabled>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Account Status *" data-ar="حالة الحساب *">
                    Account Status *
                  </label>
                  <select name="status" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="approved" <?= ($supplier['status'] == 'approved') ? 'selected' : '' ?>>Active (Approved)</option>
                    <option value="pending" <?= ($supplier['status'] == 'pending') ? 'selected' : '' ?>>Pending Verification</option>
                    <option value="suspended" <?= ($supplier['status'] == 'suspended') ? 'selected' : '' ?>>Suspended</option>
                    <option value="rejected" <?= ($supplier['status'] == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Payment Terms *" data-ar="شروط السداد *">
                    Payment Terms *
                  </label>
                  <select name="payment_terms" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="Net 15 Days" <?= ($supplier['payment_terms'] == 'Net 15 Days' || $supplier['payment_terms'] == 'net15') ? 'selected' : '' ?>>Net 15 Days</option>
                    <option value="Net 30 Days" <?= ($supplier['payment_terms'] == 'Net 30 Days' || $supplier['payment_terms'] == 'net30') ? 'selected' : '' ?>>Net 30 Days</option>
                    <option value="Net 60 Days" <?= ($supplier['payment_terms'] == 'Net 60 Days' || $supplier['payment_terms'] == 'net60') ? 'selected' : '' ?>>Net 60 Days</option>
                    <option value="Cash in Advance" <?= ($supplier['payment_terms'] == 'Cash in Advance' || $supplier['payment_terms'] == 'cia') ? 'selected' : '' ?>>Cash in Advance (CIA)</option>
                    <option value="Cash on Delivery" <?= ($supplier['payment_terms'] == 'Cash on Delivery' || $supplier['payment_terms'] == 'cod') ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
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