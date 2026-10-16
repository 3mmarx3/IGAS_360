<?php
require_once __DIR__ . '/auth.php';

$current_file = basename($_SERVER['PHP_SELF']);
$p = $active_page ?? '';

// Enterprise active-state hierarchical mapping
$is_clients_active  = in_array($current_file, ['clients_directory.php', 'client_profile.php', 'new_client.php', 'edit_client.php', 'client_activity.php', 'client_transactions.php']) || in_array($p, ['clients_directory', 'client_profile', 'new_client', 'edit_client', 'client_activity']);
$is_contracts_active = in_array($current_file, ['client_contracts.php', 'contract_details.php', 'draft_contract.php', 'renew_contract.php', 'new_calloff.php']) || in_array($p, ['client_contracts', 'contract_details', 'draft_contract', 'renew_contract', 'new_calloff']);
$is_quotes_active  = in_array($current_file, ['quotations.php', 'quotation_details.php', 'new_quotation.php', 'view_quotation.php']) || in_array($p, ['quotations', 'quotation_details', 'new_quotation', 'view_quotation']);
$is_orders_active  = in_array($current_file, ['purchase_orders.php', 'order_details.php', 'new_order.php', 'view_order.php']) || in_array($p, ['purchase_orders', 'orders', 'order_details', 'new_order', 'view_order']);
$is_invoices_active = in_array($current_file, ['collections_invoices.php', 'view_invoice.php']) || in_array($p, ['collections_invoices', 'view_invoice']);
$is_reps_active   = in_array($current_file, ['sales_reps.php', 'rep_profile.php']) || in_array($p, ['sales_reps', 'rep_profile']);
$is_followups_active = in_array($current_file, ['followup_reminders.php', 'follow_ups.php', 'add_followup.php']) || in_array($p, ['followups', 'followup_reminders', 'add_followup']);

$is_suppliers_active = in_array($current_file, ['suppliers_directory.php', 'supplier_profile.php', 'new_supplier.php', 'edit_supplier.php', 'new_purchase_order.php']) || in_array($p, ['suppliers_directory', 'supplier_profile', 'new_supplier', 'edit_supplier']);
$is_raw_mat_active  = in_array($current_file, ['raw_materials.php', 'material_details.php', 'new_material.php']) || in_array($p, ['raw_materials', 'material_details', 'new_material']);
$is_liquid_active  = in_array($current_file, ['liquid_tracking.php', 'liquid_profile.php', 'liquid_transaction.php']) || in_array($p, ['liquid_tracking', 'liquid_profile', 'liquid_transaction']);
$is_cylinders_active = in_array($current_file, ['cylinders_inventory.php', 'cylinder_details.php', 'new_cylinder.php']) || in_array($p, ['cylinders_inventory', 'cylinder_details', 'new_cylinder']);
$is_tanks_active   = in_array($current_file, ['customer_tanks.php', 'tank_details.php', 'new_tank.php']) || in_array($p, ['customer_tanks', 'tank_details', 'new_tank']);
$is_mixtures_active = in_array($current_file, ['gases_mixtures.php', 'gas_details.php', 'new_mixture.php']) || in_array($p, ['gases_mixtures', 'gas_details', 'new_mixture']);
$is_shifts_active  = in_array($current_file, ['daily_log_shifts.php', 'shift_details.php', 'new_shift_log.php']) || in_array($p, ['daily_log_shifts', 'shift_details', 'new_shift_log']);

$is_warehouses_active= in_array($current_file, ['warehouses.php', 'warehouse_profile.php', 'new_warehouse.php', 'new_warehouse_item.php']) || in_array($p, ['warehouses', 'warehouse_profile', 'new_warehouse']);
$is_drivers_active  = in_array($current_file, ['drivers_directory.php', 'driver_profile.php', 'new_driver.php', 'edit_driver.php']) || in_array($p, ['drivers_directory', 'driver_profile', 'new_driver', 'edit_driver']);
$is_vehicles_active = in_array($current_file, ['vehicles_fleet.php', 'vehicle_logs.php', 'new_vehicle.php', 'new_log_entry.php']) || in_array($p, ['vehicles_fleet', 'vehicle_logs', 'new_vehicle', 'new_log_entry']);
$is_dispatch_active = in_array($current_file, ['dispatch_delivery.php', 'dispatch_details.php', 'new_dispatch.php', 'manual_entry.php']) || in_array($p, ['dispatch_delivery', 'dispatch_details', 'new_dispatch']);
$is_maint_active   = in_array($current_file, ['maintenance_fuel.php', 'maintenance_details.php', 'new_maintenance.php']) || in_array($p, ['maintenance_fuel', 'maintenance_details', 'new_maintenance']);

$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Guest User';
$initials = strtoupper(substr($user_name, 0, 2));

if (!function_exists('hasAccess')) {
  function hasAccess($allowed_roles) {
    global $pdo;
    $allowed = (array)$allowed_roles;
    if (in_array('Admin', $allowed) === false) {
      $allowed[] = 'Admin';
      $allowed[] = 'Super Admin';
    }
    if (isset($pdo) && function_exists('has_role')) {
      foreach ($allowed as $r) {
        if (has_role($pdo, $r)) return true;
      }
      return false;
    }
    $user_role = $_SESSION['role'] ?? '';
    if (strcasecmp($user_role, 'Admin') === 0 || strcasecmp($user_role, 'Super Admin') === 0) return true;
    return in_array($user_role, $allowed);
  }
}
?>

<link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
<link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">

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

    document.documentElement.classList.add('dark');
    
    void document.documentElement.offsetHeight;
    
    requestAnimationFrame(function() {
      document.documentElement.classList.add('layout-ready');
    });
  })();
</script>

<style>
html {
  visibility: hidden;
  opacity: 0;
}

html.layout-ready {
  visibility: visible;
  opacity: 1;
  transition: opacity 0.2s ease-in-out;
}

html.arabic-mode body,
html.arabic-mode #main-sidebar {
  font-family: 'Cairo', sans-serif !important;
}

#main-sidebar .custom-scrollbar {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

#main-sidebar .custom-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden transition-opacity cursor-pointer" onclick="toggleMobileMenu()"></div>

<aside id="main-sidebar" class="w-64 h-full flex flex-col flex-shrink-0 z-50 lg:relative" style="background: var(--sidebar); color: var(--sidebar-text);">
  <div class="flex-1 overflow-y-auto custom-scrollbar">

    <div class="h-16 flex items-center px-6 border-b flex-shrink-0" style="border-color: var(--sidebar-line);">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 flex items-center justify-center text-cyan-400 font-semibold text-[13px] mono border rounded-md" style="border-color: #1e3a50; background: #102230;">IG</div>
        <div class="leading-none">
          <span class="text-white font-semibold text-[15px] tracking-tight block">I-GAS</span>
          <span class="text-[10px] font-medium uppercase tracking-[0.14em] block mt-0.5 mono" style="color: var(--mute-soft);" data-en="Enterprise Systems" data-ar="Enterprise Systems">Enterprise Systems</span>
        </div>
      </div>
      <div class="ml-auto rtl:mr-auto rtl:ml-0 flex items-center gap-3">
        <button class="lg:hidden text-white hover:text-orange-400 transition-colors p-1" onclick="toggleMobileMenu()">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
    </div>

    <button class="w-full flex items-center justify-between px-6 py-3 border-b transition-colors" style="border-color: var(--sidebar-line);" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
      <div class="flex items-center gap-2.5 min-w-0">
        <span class="w-5 h-5 flex items-center justify-center text-[9px] font-semibold text-white flex-shrink-0 rounded-sm" style="background:#2E2E2E;">JI</span>
        <span class="text-[13px] font-medium text-white truncate" data-en="Jeddah Industrial — HQ" data-ar="Jeddah Industrial — HQ">Jeddah Industrial — HQ</span>
      </div>
      <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 flex-shrink-0" style="color: var(--mute-soft);"></i>
    </button>

    <div class="pt-6 space-y-2">

      <?php if (isset($_SESSION['user_id'])): ?>
      <div class="section-group">
        <button onclick="toggleSection('sec-overview')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Overview" data-ar="نظرة عامة">Overview</span>
          <i data-lucide="chevron-down" id="icon-sec-overview" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-overview">
          <a href="<?= app_url('index.php') ?>" class="nav-row <?= ($p === 'dashboard' || $current_file === 'index.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="layout-dashboard" class="w-[16px] h-[16px]"></i><span data-en="Dashboard" data-ar="لوحة التحكم">Dashboard</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Sales Manager', 'Sales Rep']) || (isset($pdo) && has_permission($pdo, 'clients.view'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-crm')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="CRM & Sales" data-ar="إدارة العلاقات والمبيعات">CRM & Sales</span>
          <i data-lucide="chevron-down" id="icon-sec-crm" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-crm">
          <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="nav-row <?= $is_clients_active ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <span class="flex items-center gap-3"><i data-lucide="users" class="w-[16px] h-[16px]"></i><span data-en="Clients Directory" data-ar="دليل العملاء">Clients Directory</span></span>
          </a>
          <a href="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="nav-row <?= $is_contracts_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="file-check" class="w-[16px] h-[16px]"></i><span data-en="Contracts & SLAs" data-ar="العقود واتفاقيات الخدمة">Contracts & SLAs</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/sales_reps.php') ?>" class="nav-row <?= $is_reps_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="contact" class="w-[16px] h-[16px]"></i><span data-en="Sales Team Directory" data-ar="دليل فريق المبيعات">Sales Team Directory</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="nav-row <?= $is_quotes_active ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <span class="flex items-center gap-3"><i data-lucide="file-text" class="w-[16px] h-[16px]"></i><span data-en="Quotations" data-ar="عروض الأسعار">Quotations</span></span>
          </a>
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="nav-row <?= $is_orders_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="shopping-cart" class="w-[16px] h-[16px]"></i><span data-en="Purchase Orders (PO)" data-ar="أوامر الشراء">Purchase Orders (PO)</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/collections_invoices.php') ?>" class="nav-row <?= $is_invoices_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="wallet" class="w-[16px] h-[16px]"></i><span data-en="Collections & Invoices" data-ar="التحصيلات والفواتير">Collections & Invoices</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/sales_performance.php') ?>" class="nav-row <?= ($p === 'sales_performance' || $current_file === 'sales_performance.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="trending-up" class="w-[16px] h-[16px]"></i><span data-en="Sales Performance" data-ar="أداء المبيعات">Sales Performance</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/sales_reports.php') ?>" class="nav-row <?= ($p === 'sales_reports' || $current_file === 'sales_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="bar-chart-2" class="w-[16px] h-[16px]"></i><span data-en="Sales Reports" data-ar="تقارير المبيعات">Sales Reports</span>
          </a>
          <a href="<?= app_url('modules/crm_sales/followup_reminders.php') ?>" class="nav-row <?= $is_followups_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="bell-ring" class="w-[16px] h-[16px]"></i><span data-en="Follow-up Reminders" data-ar="تذكيرات المتابعة">Follow-up Reminders</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Production Manager', 'Production Supervisor']) || (isset($pdo) && has_permission($pdo, 'production.view'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-production')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Production" data-ar="الإنتاج">Production</span>
          <i data-lucide="chevron-down" id="icon-sec-production" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-production">
          <a href="<?= app_url('modules/production/suppliers_directory.php') ?>" class="nav-row <?= $is_suppliers_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="users-round" class="w-[16px] h-[16px]"></i><span data-en="Suppliers Directory" data-ar="دليل الموردين">Suppliers Directory</span>
          </a>
          <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="nav-row <?= $is_raw_mat_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="flask-conical" class="w-[16px] h-[16px]"></i><span data-en="Raw Materials" data-ar="المواد الخام">Raw Materials</span>
          </a>
          <a href="<?= app_url('modules/production/liquid_tracking.php') ?>" class="nav-row <?= $is_liquid_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="droplet" class="w-[16px] h-[16px]"></i><span data-en="Liquid Tracking & Recon" data-ar="تتبع السوائل والمطابقة">Liquid Tracking & Recon</span>
          </a>
          <a href="<?= app_url('modules/production/cylinders_inventory.php') ?>" class="nav-row <?= $is_cylinders_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="cylinder" class="w-[16px] h-[16px]"></i><span data-en="Cylinders Inventory" data-ar="مخزون الأسطوانات">Cylinders Inventory</span>
          </a>
          <a href="<?= app_url('modules/production/customer_tanks.php') ?>" class="nav-row <?= $is_tanks_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="database" class="w-[16px] h-[16px]"></i><span data-en="Customer Tanks & IoT" data-ar="خزانات العملاء والتحكم">Customer Tanks & IoT</span>
          </a>
          <a href="<?= app_url('modules/production/gases_mixtures.php') ?>" class="nav-row <?= $is_mixtures_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="test-tubes" class="w-[16px] h-[16px]"></i><span data-en="Gases & Mixtures" data-ar="الغازات والمخاليط">Gases & Mixtures</span>
          </a>
          <a href="<?= app_url('modules/production/daily_log_shifts.php') ?>" class="nav-row <?= $is_shifts_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="factory" class="w-[16px] h-[16px]"></i><span data-en="Daily Log & Shifts" data-ar="السجل اليومي والورديات">Daily Log & Shifts</span>
          </a>
          <a href="<?= app_url('modules/production/monthly_yearly_production.php') ?>" class="nav-row <?= ($p === 'monthly_yearly_production' || $current_file === 'monthly_yearly_production.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="calendar-days" class="w-[16px] h-[16px]"></i><span data-en="Monthly & Yearly Prod" data-ar="الإنتاج الشهري والسنوي">Monthly & Yearly Prod</span>
          </a>
          <a href="<?= app_url('modules/production/yield_loss_reports.php') ?>" class="nav-row <?= ($p === 'yield_loss_reports' || $current_file === 'yield_loss_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="scale" class="w-[16px] h-[16px]"></i><span data-en="Yield & Loss Reports" data-ar="تقارير الإنتاج والهدر">Yield & Loss Reports</span>
          </a>
          <a href="<?= app_url('modules/production/production_reports.php') ?>" class="nav-row <?= ($p === 'production_reports' || $current_file === 'production_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="file-spreadsheet" class="w-[16px] h-[16px]"></i><span data-en="Production Reports" data-ar="تقارير الإنتاج">Production Reports</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Admin', 'Warehouse Manager', 'Production Manager']) || (isset($pdo) && has_permission($pdo, 'warehouse.view'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-warehouse')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Warehouse & Inventory" data-ar="المخازن والمستودعات">Warehouse & Inventory</span>
          <i data-lucide="chevron-down" id="icon-sec-warehouse" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-warehouse">
          <a href="<?= app_url('modules/warehouse/warehouses.php') ?>" class="nav-row <?= $is_warehouses_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="boxes" class="w-[16px] h-[16px]"></i><span data-en="Warehouses Directory" data-ar="دليل المستودعات">Warehouses Directory</span>
          </a>
          <a href="<?= app_url('modules/warehouse/withdraw.php') ?>" class="nav-row <?= ($p === 'withdraw' || $current_file === 'withdraw.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="package-minus" class="w-[16px] h-[16px]"></i><span data-en="Withdraw from Warehouse" data-ar="استيراد من المخزن">Withdraw from Warehouse</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Admin', 'Finance']) || (isset($pdo) && has_permission($pdo, 'finance.view'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-finance')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Financials & Revenue" data-ar="المالية والإيرادات">Financials & Revenue</span>
          <i data-lucide="chevron-down" id="icon-sec-finance" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-finance">
          <a href="<?= app_url('modules/finance/cash_flow.php') ?>" class="nav-row <?= ($p === 'cash_flow' || $current_file === 'cash_flow.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="activity" class="w-[16px] h-[16px]"></i><span data-en="Cash Flow Statement" data-ar="بيان التدفق النقدي">Cash Flow Statement</span>
          </a>
          <a href="<?= app_url('modules/production/cost_profit.php') ?>" class="nav-row <?= ($p === 'cost_profit' || $current_file === 'cost_profit.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="circle-dollar-sign" class="w-[16px] h-[16px]"></i><span data-en="Cost & Profit Analysis" data-ar="تحليل التكلفة والربح">Cost & Profit Analysis</span>
          </a>
          <a href="<?= app_url('modules/finance/financial_reports.php') ?>" class="nav-row <?= ($p === 'financial_reports' || $current_file === 'financial_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="pie-chart" class="w-[16px] h-[16px]"></i><span data-en="Financial Reports" data-ar="التقارير المالية">Financial Reports</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Logistics Manager', 'Logistics Assistant']) || (isset($pdo) && has_permission($pdo, 'logistics.view'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-logistics')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Logistics & Fleet" data-ar="الخدمات اللوجستية والأسطول">Logistics & Fleet</span>
          <i data-lucide="chevron-down" id="icon-sec-logistics" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-logistics">
          <a href="<?= app_url('modules/logistics_fleet/drivers_directory.php') ?>" class="nav-row <?= $is_drivers_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="users-2" class="w-[16px] h-[16px]"></i><span data-en="Drivers Directory" data-ar="دليل السائقين">Drivers Directory</span>
          </a>
          <a href="<?= app_url('modules/logistics_fleet/vehicles_fleet.php') ?>" class="nav-row <?= $is_vehicles_active ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <span class="flex items-center gap-3"><i data-lucide="truck" class="w-[16px] h-[16px]"></i><span data-en="Vehicles Fleet" data-ar="أسطول المركبات">Vehicles Fleet</span></span>
          </a>
          <a href="<?= app_url('modules/logistics_fleet/dispatch_delivery.php') ?>" class="nav-row <?= $is_dispatch_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="map-pin" class="w-[16px] h-[16px]"></i><span data-en="Dispatch & Delivery" data-ar="الإرسال والتسليم">Dispatch & Delivery</span>
          </a>
          <a href="<?= app_url('modules/logistics_fleet/maintenance_fuel.php') ?>" class="nav-row <?= $is_maint_active ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="wrench" class="w-[16px] h-[16px]"></i><span data-en="Maintenance & Fuel" data-ar="الصيانة والوقود">Maintenance & Fuel</span>
          </a>
          <a href="<?= app_url('modules/logistics_fleet/licenses_alerts.php') ?>" class="nav-row <?= ($p === 'licenses_alerts' || $current_file === 'licenses_alerts.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="file-warning" class="w-[16px] h-[16px]"></i><span data-en="Licenses Alerts" data-ar="تنبيهات التراخيص">Licenses Alerts</span>
          </a>
          <a href="<?= app_url('modules/logistics_fleet/logistics_reports.php') ?>" class="nav-row <?= ($p === 'logistics_reports' || $current_file === 'logistics_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="navigation" class="w-[16px] h-[16px]"></i><span data-en="Logistics Reports" data-ar="التقارير اللوجستية">Logistics Reports</span>
          </a>
        </nav>
      </div>
      <?php endif; ?>

      <?php if (hasAccess(['Admin']) || (isset($pdo) && has_permission($pdo, 'users.manage'))): ?>
      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-insights')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Insights & Admin" data-ar="التحليلات والإدارة">Insights & Admin</span>
          <i data-lucide="chevron-down" id="icon-sec-insights" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3" id="sec-insights">
          <a href="<?= app_url('modules/insights/analytics.php') ?>" class="nav-row <?= ($p === 'analytics' || $current_file === 'analytics.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="bar-chart-3" class="w-[16px] h-[16px]"></i><span data-en="Analytics Cockpit" data-ar="غرفة التحليلات">Analytics Cockpit</span>
          </a>
          <a href="<?= app_url('modules/insights/compliance.php') ?>" class="nav-row <?= ($p === 'compliance' || $current_file === 'compliance.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="shield-check" class="w-[16px] h-[16px]"></i><span data-en="Compliance & Safety" data-ar="الامتثال والسلامة">Compliance & Safety</span>
          </a>
          <a href="<?= app_url('modules/insights/partner_approvals.php') ?>" class="nav-row <?= ($p === 'partner_approvals' || $current_file === 'partner_approvals.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="user-check" class="w-[16px] h-[16px]"></i><span data-en="Partner Approvals" data-ar="موافقات الشركاء">Partner Approvals</span>
          </a>
          <a href="<?= app_url('modules/insights/team_access.php') ?>" class="nav-row <?= ($p === 'team_access' || $current_file === 'team_access.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="user-cog" class="w-[16px] h-[16px]"></i><span data-en="Team & Access (RBAC)" data-ar="الفريق والصلاحيات">Team & Access (RBAC)</span>
          </a>
          <a href="<?= app_url('modules/insights/system_settings.php') ?>" class="nav-row <?= ($p === 'system_settings' || $current_file === 'system_settings.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="settings" class="w-[16px] h-[16px]"></i><span data-en="System Settings" data-ar="إعدادات النظام">System Settings</span>
          </a>
        </nav>
      </div>

      <div class="section-group mt-7">
        <button onclick="toggleSection('sec-tools')" class="w-full flex items-center justify-between px-6 mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] hover:text-white transition-colors" style="color: #555350;">
          <span data-en="Tools" data-ar="الأدوات">Tools</span>
          <i data-lucide="chevron-down" id="icon-sec-tools" class="w-3.5 h-3.5 transition-transform"></i>
        </button>
        <nav class="px-3 pb-8" id="sec-tools">
          <a href="<?= app_url('modules/tools/unit_converter.php') ?>" class="nav-row <?= ($p === 'unit_converter' || $current_file === 'unit_converter.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="ruler" class="w-[16px] h-[16px]"></i><span data-en="Unit Converter" data-ar="محول الوحدات">Unit Converter</span>
          </a>
          <a href="<?= app_url('modules/tools/gas_converter.php') ?>" class="nav-row <?= ($p === 'gas_converter' || $current_file === 'gas_converter.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
            <i data-lucide="repeat" class="w-[16px] h-[16px]"></i><span data-en="Gas Converter" data-ar="محول الغازات">Gas Converter</span>
          </a>
          <?php if (is_admin()): ?>
          <a href="<?= app_url('modules/tools/style_guide.php') ?>" class="nav-row <?= ($p === 'style_guide' || $current_file === 'style_guide.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
            <i data-lucide="palette" class="w-[16px] h-[16px]"></i><span data-en="Visual Identity (P&ID)" data-ar="دليل الهوية البصرية">Visual Identity (P&ID)</span>
          </a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <div class="p-4 border-t flex-shrink-0" style="border-color: var(--sidebar-line);">
    <a href="<?= app_url('auth/logout.php') ?>" class="flex items-center gap-3 px-2 py-2 rounded-lg transition-colors hover:bg-[rgba(255,255,255,0.05)]">
      <div class="w-9 h-9 rounded-full bg-neutral-800 border border-neutral-700 flex items-center justify-center text-white font-bold text-[13px]">
        <?= htmlspecialchars($initials) ?>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-[13px] font-medium text-white truncate"><?= htmlspecialchars($user_name) ?></p>
        <p class="text-[11px] truncate mono uppercase tracking-wide text-rose-400" data-en="Sign out" data-ar="تسجيل الخروج">Sign out</p>
      </div>
      <i data-lucide="log-out" class="w-[17px] h-[17px]" style="color: var(--mute-soft);"></i>
    </a>
  </div>
</aside>

<script>
function toggleSection(sectionId) {
  const section = document.getElementById(sectionId);
  const icon = document.getElementById('icon-' + sectionId);
  if (section.style.display === 'none') {
    section.style.display = 'block';
    if (icon) icon.style.transform = 'rotate(0deg)';
    localStorage.setItem(sectionId, 'open');
  } else {
    section.style.display = 'none';
    if (icon) icon.style.transform = 'rotate(-90deg)';
    localStorage.setItem(sectionId, 'closed');
  }
}

function toggleMobileMenu() {
  const sidebar = document.getElementById('main-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (sidebar) {
    sidebar.classList.toggle('mobile-open');
  }
  if (overlay) {
    overlay.classList.toggle('hidden');
  }
}

function applyLanguage(lang) {
  document.querySelectorAll('[data-en]').forEach(el => {
    const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
    if (el.tagName === 'INPUT' && (el.type === 'button' || el.type === 'submit')) {
      el.value = transText;
    } else if (el.hasAttribute('placeholder')) {
      el.setAttribute('placeholder', transText);
    } else {
      el.innerText = transText;
    }
  });
  const langBtnText = document.getElementById('lang-switch-text');
  if (langBtnText) {
    langBtnText.textContent = lang === 'ar' ? 'English' : 'العربية';
  }
}

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
  applyLanguage(next);
}

function toggleAppTheme() {
  const isDark = document.documentElement.classList.contains('dark');
  const nextTheme = isDark ? 'light' : 'dark';
  localStorage.setItem('app_theme', nextTheme);
  if (nextTheme === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
  const themeIcon = document.getElementById('header-theme-icon');
  if (themeIcon) {
    themeIcon.setAttribute('data-lucide', nextTheme === 'dark' ? 'sun' : 'moon');
    if (window.lucide) lucide.createIcons();
  }
}

document.addEventListener("DOMContentLoaded", function() {
  const savedLang = localStorage.getItem('app_lang') || 'en';
  applyLanguage(savedLang);

  const savedTheme = localStorage.getItem('app_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  const themeIcon = document.getElementById('header-theme-icon');
  if (themeIcon) {
    themeIcon.setAttribute('data-lucide', savedTheme === 'dark' ? 'sun' : 'moon');
    if (window.lucide) lucide.createIcons();
  }

  ['sec-overview', 'sec-crm', 'sec-production', 'sec-warehouse', 'sec-finance', 'sec-logistics', 'sec-insights', 'sec-tools'].forEach(function(secId) {
    const section = document.getElementById(secId);
    const icon = document.getElementById('icon-' + secId);
    if (section) {
      const state = localStorage.getItem(secId);
      if (state === 'closed') {
        section.style.display = 'none';
        if (icon) icon.style.transform = 'rotate(-90deg)';
      } else {
        section.style.display = 'block';
        if (icon) icon.style.transform = 'rotate(0deg)';
      }
    }
  });
});
</script>