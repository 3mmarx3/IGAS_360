<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');
require_once __DIR__ . '/../../config/db.php';

$active_page = 'compliance';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Insights & Intelligence', 'Compliance & Regulations'];

$compliance_score = 98.5;
$active_certs   = 14;
$pending_audits  = 2;
$open_violations = 0;

$certifications = [
  ['id' => 'CERT-9001', 'name' => 'ISO 9001:2015', 'authority' => 'International Organization for Standardization', 'category' => 'Quality Management', 'expiry' => '2027-11-15', 'status' => 'valid'],
  ['id' => 'CERT-14001', 'name' => 'ISO 14001:2015', 'authority' => 'International Organization for Standardization', 'category' => 'Environmental', 'expiry' => '2027-08-20', 'status' => 'valid'],
  ['id' => 'CERT-45001', 'name' => 'ISO 45001:2018', 'authority' => 'International Organization for Standardization', 'category' => 'Occupational Health', 'expiry' => '2026-09-10', 'status' => 'expiring'],
  ['id' => 'PRM-CIV-01', 'name' => 'Civil Defense Safety Permit', 'authority' => 'Saudi Civil Defense', 'category' => 'Facility Safety', 'expiry' => '2026-07-05', 'status' => 'expiring'],
  ['id' => 'PRM-MOE-04', 'name' => 'Industrial Operations License', 'authority' => 'Ministry of Energy (MOE)', 'category' => 'Operational', 'expiry' => '2028-02-28', 'status' => 'valid'],
  ['id' => 'PRM-ENV-11', 'name' => 'Environmental Compliance Cert.', 'authority' => 'National Center for Environmental Compliance', 'category' => 'Environmental', 'expiry' => '2025-12-10', 'status' => 'expired'],
];

$audit_logs = [
  ['date' => '2026-06-15', 'title' => 'Q2 Internal Safety Audit', 'auditor' => 'HSE Department', 'findings' => 2, 'status' => 'resolved'],
  ['date' => '2026-05-20', 'title' => 'Civil Defense Inspection', 'auditor' => 'External Agency', 'findings' => 0, 'status' => 'passed'],
  ['date' => '2026-04-10', 'title' => 'ISO 9001 Surveillance Audit', 'auditor' => 'TÜV Rheinland', 'findings' => 1, 'status' => 'resolved'],
  ['date' => '2026-02-28', 'title' => 'Fleet Emissions Check', 'auditor' => 'NCEC', 'findings' => 0, 'status' => 'passed'],
];

$statusStyles = [
  'valid'  => ['border' => 'border-emerald-200 dark:border-emerald-800/60', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'fg' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label' => 'Valid & Compliant', 'label_ar' => 'ساري ومطابق'],
  'expiring' => ['border' => 'border-amber-800 dark:border-amber-800/60', 'bg' => 'bg-amber-950/30 dark:bg-amber-950/40', 'fg' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Expiring Soon', 'label_ar' => 'ينتهي قريباً'],
  'expired' => ['border' => 'border-rose-800 dark:border-rose-800/60', 'bg' => 'bg-rose-950/30 dark:bg-rose-950/40', 'fg' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Expired / Action Req.', 'label_ar' => 'منتهي / يتطلب إجراء'],
];

$auditStyles = [
  'passed'  => ['border' => 'border-emerald-200 dark:border-emerald-800/60', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'fg' => 'text-emerald-700 dark:text-emerald-400'],
  'resolved' => ['border' => 'border-blue-200 dark:border-blue-800/60', 'bg' => 'bg-blue-950/30 dark:bg-blue-950/40', 'fg' => 'text-blue-700 dark:text-blue-400'],
  'pending' => ['border' => 'border-amber-800 dark:border-amber-800/60', 'bg' => 'bg-amber-950/30 dark:bg-amber-950/40', 'fg' => 'text-amber-400 dark:text-amber-400'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Compliance & Safety Regulations | I-GAS Industrial" data-ar="الامتثال واللوائح التنظيمية | I-GAS Industrial">Compliance & Safety Regulations | I-GAS Industrial</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script>
    (function() {
      document.documentElement.classList.add('dark');
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
    })();
  </script>
  <style>
    .font-oxanium { font-family: 'Oxanium', 'Reem Kufi', sans-serif; }
    .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="CGA & ISO COMPLIANCE REGULATORY AUDIT" data-ar="تدقيق الامتثال للوائح جمعية الغازات المضغوطة والأيزو">CGA & ISO COMPLIANCE REGULATORY AUDIT</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="HSE STATUS: ZERO ACCIDENTS" data-ar="حالة السلامة: خالي من الحوادث">HSE STATUS: ZERO ACCIDENTS</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: HSE-COMP-01</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Enterprise Insights" data-ar="رؤى المؤسسة">Enterprise Insights</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Compliance & Regulations" data-ar="الامتثال واللوائح">Compliance & Regulations</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Compliance, Permitting & Safety Audits" data-ar="الامتثال، التراخيص وتدقيق السلامة المهنية">Compliance, Permitting & Safety Audits</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Monitor ISO certifications, HSE metrics, Saudi Civil Defense permits, and regulatory plant audit scores." data-ar="مراقبة شهادات الأيزو، مؤشرات الصحة والسلامة والبيئة، تصاريح الدفاع المدني، وسجلات التدقيق الميداني.">Monitor ISO certifications, HSE metrics, Saudi Civil Defense permits, and regulatory plant audit scores.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <button class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i>
          <span data-en="Schedule Audit" data-ar="جدولة تدقيق">Schedule Audit</span>
        </button>
        <?php if (has_permission('reports.export')): ?>
        <button onclick="exportComplianceReport()" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Compliance Report" data-ar="تصدير تقرير الامتثال">Export Compliance Report</span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Compliance Score -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Overall Compliance Score" data-ar="معدل الامتثال الكلي">Overall Compliance Score</span>
          <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $compliance_score ?></span>
          <span class="text-xs font-mono text-neutral-500">%</span>
        </div>
        <div class="mt-3">
          <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden border border-[#1e3a50]">
            <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: <?= $compliance_score ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Active Certifications -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Active Certifications" data-ar="الشهادات والتراخيص السارية">Active Certifications</span>
          <i data-lucide="award" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $active_certs ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Accredited" data-ar="معتمدة">Accredited</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#1e3a50] bg-[#09151f] text-slate-300 text-[11px] font-mono rounded-xs">
            <span data-en="ISO & Local Authorities" data-ar="أيزو والجهات المحلية">ISO & Local Authorities</span>
          </span>
        </div>
      </div>

      <!-- Pending Audits -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Pending Audits / Reviews" data-ar="عمليات التدقيق المجدولة">Pending Audits / Reviews</span>
          <i data-lucide="calendar-clock" class="w-4 h-4 text-amber-500"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $pending_audits ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Audits" data-ar="تدقيق">Audits</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-amber-800 dark:border-amber-800/60 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="clock" class="w-3 h-3"></i>
            <span data-en="Due within 30 days" data-ar="مستحقة خلال 30 يوماً">Due within 30 days</span>
          </span>
        </div>
      </div>

      <!-- Open Violations -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Open HSE Violations" data-ar="مخالفات السلامة المفتوحة">Open HSE Violations</span>
          <i data-lucide="alert-octagon" class="w-4 h-4 <?= $open_violations > 0 ? 'text-rose-500' : 'text-neutral-400' ?>"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight <?= $open_violations > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>"><?= $open_violations ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Incidents" data-ar="حوادث">Incidents</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="check" class="w-3 h-3"></i>
            <span data-en="Zero Active Incidents" data-ar="سجل نظيف بدون حوادث">Zero Active Incidents</span>
          </span>
        </div>
      </div>

    </div>

    <!-- 2-Column Main Section -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Certifications Registry (2 cols) -->
      <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="file-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Certifications & Regulatory Permits Registry" data-ar="سجل التراخيص والشهادات النظامية">Certifications & Regulatory Permits Registry</span>
            </h2>
          </div>
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-60 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="certSearchInput" placeholder="Search permit or agency..." data-en-placeholder="Search permit or agency..." data-ar-placeholder="البحث برقم التصريح أو الجهة..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse" id="certTable">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Permit / Standard" data-ar="التصريح / المعيار">Permit / Standard</th>
                <th class="px-5 py-3 font-semibold" data-en="Issuing Authority" data-ar="الجهة المانحة">Issuing Authority</th>
                <th class="px-5 py-3 font-semibold" data-en="Category" data-ar="التصنيف">Category</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Valid Until" data-ar="ساري حتى">Valid Until</th>
                <th class="px-5 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] text-xs">
              <?php foreach ($certifications as $cert): ?>
                <?php 
                  $ss = $statusStyles[$cert['status']]; 
                  $isExpired = $cert['status'] === 'expired';
                ?>
                <tr class="hover:bg-[#102332] transition-colors cert-row">
                  <td class="px-5 py-3.5">
                    <span class="font-medium text-white cert-name"><?= htmlspecialchars($cert['name']) ?></span>
                    <span class="block text-[11px] font-mono text-neutral-400 cert-id"><?= htmlspecialchars($cert['id']) ?></span>
                  </td>
                  <td class="px-5 py-3.5 text-slate-300 cert-auth">
                    <?= htmlspecialchars($cert['authority']) ?>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-neutral-500 cert-cat">
                    <?= htmlspecialchars($cert['category']) ?>
                  </td>
                  <td class="px-5 py-3.5 text-right rtl:text-left font-mono font-medium whitespace-nowrap <?= $isExpired ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-200' ?>">
                    <?= htmlspecialchars(date('Y-m-d', strtotime($cert['expiry']))) ?>
                  </td>
                  <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border <?= $ss['border'] ?> <?= $ss['bg'] ?> <?= $ss['fg'] ?> text-[11px] font-mono rounded-xs">
                      <span class="w-1.5 h-1.5 rounded-full <?= $ss['dot'] ?>"></span>
                      <span data-en="<?= $ss['label'] ?>" data-ar="<?= $ss['label_ar'] ?>"><?= $ss['label'] ?></span>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
          <span data-en="Total tracked permits: 6 documents" data-ar="إجمالي التصاريح الموثقة: 6 وثائق">Total tracked permits: 6 documents</span>
          <span>SEC: ISO-COMPLIANT</span>
        </div>
      </div>

      <!-- Recent Audits & Inspections (1 col) -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Recent Audits & Logs" data-ar="عمليات التدقيق والزيارات الأخيرة">Recent Audits & Logs</span>
            </h2>
          </div>
        </div>

        <div class="p-5 flex-1 divide-y divide-[#152a3a] space-y-4">
          <?php foreach ($audit_logs as $index => $audit): ?>
            <div class="<?= $index > 0 ? 'pt-4' : '' ?> space-y-1.5">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-medium text-white"><?= htmlspecialchars($audit['title']) ?></h3>
                <span class="px-1.5 py-0.5 border <?= $auditStyles[$audit['status']]['border'] ?> <?= $auditStyles[$audit['status']]['bg'] ?> <?= $auditStyles[$audit['status']]['fg'] ?> text-[10px] font-mono uppercase rounded-xs">
                  <?= ucfirst($audit['status']) ?>
                </span>
              </div>
              <p class="text-[11px] font-mono text-neutral-500">
                <span data-en="Auditor:" data-ar="المدقق:">Auditor:</span> <?= htmlspecialchars($audit['auditor']) ?>
              </p>
              <div class="flex items-center gap-4 text-[11px] font-mono text-neutral-400 pt-1">
                <span class="flex items-center gap-1">
                  <i data-lucide="calendar" class="w-3 h-3"></i>
                  <?= htmlspecialchars($audit['date']) ?>
                </span>
                <span class="flex items-center gap-1 <?= $audit['findings'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-400' ?>">
                  <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                  <?= $audit['findings'] ?> <span data-en="Findings" data-ar="ملاحظات">Findings</span>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="p-3 border-t border-[#1a3346] bg-[#09151f]">
          <button class="w-full text-center text-xs font-mono uppercase tracking-wider py-1.5 text-neutral-600 hover:text-white text-slate-400 dark:hover:text-neutral-100 transition-colors" data-en="View Master Audit Log →" data-ar="عرض سجل التدقيق الشامل ←">
            View Master Audit Log →
          </button>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('input[data-en-placeholder]').forEach(el => {
      const ph = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
      el.placeholder = ph;
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);

    const certSearchInput = document.getElementById('certSearchInput');
    if (certSearchInput) {
      certSearchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.cert-row');
        rows.forEach(row => {
          const text = row.innerText.toLowerCase();
          row.style.display = text.includes(query) ? '' : 'none';
        });
      });
    }
  });

  function exportComplianceReport() {
    let csv = [];
    csv.push('"Permit / Standard","Document ID","Issuing Authority","Category","Valid Until","Status"');
    let rows = document.querySelectorAll("#certTable tbody tr");
    rows.forEach(row => {
      if (row.style.display === 'none') return;
      let name = row.querySelector('.cert-name')?.innerText.trim() || '';
      let id = row.querySelector('.cert-id')?.innerText.trim() || '';
      let auth = row.querySelector('.cert-auth')?.innerText.trim() || '';
      let cat = row.querySelector('.cert-cat')?.innerText.trim() || '';
      let date = row.querySelectorAll('td')[3]?.innerText.trim() || '';
      let status = row.querySelectorAll('td')[4]?.innerText.trim() || '';
      csv.push(`"${name}","${id}","${auth}","${cat}","${date}","${status}"`);
    });

    let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "Compliance_Report_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>