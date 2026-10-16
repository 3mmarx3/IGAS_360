<?php
/**
 * I-GAS ENTERPRISE — "PRESSURE & PURITY" VISUAL IDENTITY & STYLE GUIDE
 * Visual Identity Directive Style-Tile Page (Admin-Only)
 *
 * Implements:
 * - Complete token & color system display (Light & Dark mode preview)
 * - Gas-specific chemical formula chips & cylinder color coding
 * - GHS-style hazard diamond badges & safety signage stripes
 * - Technical pressure-gauge KPI cards & 10% tick tank-fill level bars
 * - Corner-notched cards, pipeline dividers, and serial label plates
 * - Custom industrial gas SVG icon vocabulary
 * - Bilingual typography testing (Oxanium, Reem Kufi, IBM Plex Sans & Mono)
 * - Interactive Theme & RTL toggles for live visual verification
 */

require_once __DIR__ . '/../../includes/auth.php';
require_admin(); // Strict Admin-Only access control
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

$active_page = 'style_guide';
$breadcrumb = ['I-GAS', 'Engineering & Tools', 'Visual Identity Style Guide'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Visual Identity Style Guide | I-GAS Enterprise</title>
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
<body class="flex h-screen overflow-hidden antialiased blueprint-grid">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[var(--surface-0)]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Control Room Status Sub-bar -->
    <div class="h-9 border-b flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px]" style="background: var(--surface-2); border-color: var(--line);">
      <span class="flex items-center gap-2 font-mono font-medium tracking-wide flex-shrink-0" style="color: var(--text-primary);">
        <span class="status-dot" style="background: var(--status-success);"></span>
        <span data-en="Visual Identity Spec Nominal" data-ar="مواصفات الهوية البصرية منتظمة">Visual Identity Spec Nominal</span>
      </span>
      <span class="w-px h-3 bg-[var(--line)] flex-shrink-0"></span>
      <span class="font-mono text-[var(--text-secondary)] uppercase tracking-wide flex-shrink-0" data-en="Directive: Pressure & Purity" data-ar="التوجيه: الضغط والنقاء">Directive: Pressure & Purity</span>
      <span class="w-px h-3 bg-[var(--line)] flex-shrink-0"></span>
      <span class="font-mono text-[var(--text-muted)] flex-shrink-0" data-en="Zero Shadows Certified" data-ar="معتمد بدون ظلال">Zero Shadows Certified</span>
      <span class="ms-auto font-mono text-[var(--text-muted)] flex-shrink-0">P&ID v3.0</span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-10 py-6 md:py-8 space-y-8">
      
      <!-- Page Header & Live Workbench Controls -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-4 border-b border-[var(--line)]">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-sm" style="background: var(--accent);"></span>
            <p class="section-overline" data-en="Industrial Gas Design System" data-ar="نظام التصميم للغازات الصناعية">Industrial Gas Design System</p>
          </div>
          <h1 class="font-display text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)]" data-en="Concept: Pressure & Purity" data-ar="المفهوم: الضغط والنقاء">Concept: Pressure & Purity</h1>
          <p class="text-sm text-[var(--text-secondary)] mt-1.5 max-w-3xl leading-relaxed" data-en="Plant control room aesthetic combining P&ID engineering diagrams, cryogenic frost tones, and strict safety signage. Absolute zero shadows, engineered surfaces, and chemical cylinder coding." data-ar="طابع غرفة التحكم بالمصنع يجمع بين المخططات الهندسية P&ID، ودرجات برودة الغاز المسال، ولافتات السلامة الصارمة بدون ظلال إطلاقاً.">
            Plant control room aesthetic combining P&ID engineering diagrams, cryogenic frost tones, and strict safety signage. Absolute zero shadows, engineered surfaces, and chemical cylinder coding.
          </p>
        </div>

        <!-- Interactive Style Workbench Switchers -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <button type="button" onclick="toggleAppTheme()" class="btn-teal-outline text-xs px-3 py-2">
            <i data-lucide="moon" class="w-4 h-4"></i>
            <span data-en="Toggle Dark / Light" data-ar="تبديل الوضع الليلي / النهاري">Toggle Dark / Light</span>
          </button>
          <button type="button" onclick="toggleAppLanguage()" class="btn-teal-outline text-xs px-3 py-2">
            <i data-lucide="globe" class="w-4 h-4"></i>
            <span data-en="Toggle RTL / LTR" data-ar="تبديل اللغة والاتجاه">Toggle RTL / LTR</span>
          </button>
          <button type="button" onclick="document.body.classList.toggle('blueprint-grid')" class="btn-teal-outline text-xs px-3 py-2">
            <i data-lucide="grid" class="w-4 h-4"></i>
            <span data-en="Toggle 24px Grid" data-ar="تبديل شبكة الرسم الهندسي">Toggle 24px Grid</span>
          </button>
        </div>
      </div>

      <!-- Warning / Safety Stripe Banner -->
      <div class="hazard-stripes p-1 rounded-sm">
        <div class="bg-[var(--surface-1)] p-4 rounded-sm flex items-center justify-between flex-wrap gap-4 border border-[var(--line)]">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-sm flex items-center justify-center text-white flex-shrink-0" style="background: var(--accent);">
              <i data-lucide="shield-alert" class="w-5 h-5"></i>
            </div>
            <div>
              <p class="font-bold text-sm text-[var(--text-primary)]" data-en="MANDATORY PLANT RULE: ZERO SHADOWS & WCAG AA COMPLIANCE" data-ar="قاعدة تشغيلية إلزامية: لا ظلال على الإطلاق ومطابقة معايير التباين">MANDATORY PLANT RULE: ZERO SHADOWS & WCAG AA COMPLIANCE</p>
              <p class="text-xs text-[var(--text-secondary)] mt-0.5" data-en="Depth is established strictly through 1px hairlines, 3 tonal surface layers, blueprint grid texture, and solid color blocks." data-ar="يتم إبراز العمق فقط عبر خطوط بسمك 1 بكسل، و3 طبقات أسطح متناسقة، وشبكة P&ID الهندسية.">Depth is established strictly through 1px hairlines, 3 tonal surface layers, blueprint grid texture, and solid color blocks.</p>
            </div>
          </div>
          <span class="tag-plate font-mono text-[10px] whitespace-nowrap">SAFETY PROTOCOL: ISO-14001 / CGA</span>
        </div>
      </div>

      <!-- SECTION 1: COLOR PALETTE SYSTEM -->
      <div>
        <?= pipe_divider('1. COLOR SYSTEM & TONAL HIERARCHY', 'PAL-01') ?>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
          <!-- Ink Swatch -->
          <div class="card-notched border-l-4 border-l-[#f97316] p-4">
            <div class="h-16 rounded-sm mb-3 border border-[var(--line)] flex items-end p-2" style="background: #0A1B26;">
              <span class="font-mono text-xs text-white font-bold">#0A1B26</span>
            </div>
            <h4 class="font-display font-semibold text-sm text-[var(--text-primary)]">Liquid Nitrogen Ink</h4>
            <p class="text-xs text-[var(--text-muted)] font-mono">--ink · Primary text & sidebar</p>
          </div>

          <!-- Cryo Teal Swatch -->
          <div class="card-notched border-l-4 border-l-[#f97316] p-4">
            <div class="h-16 rounded-sm mb-3 border border-[var(--line)] flex items-end p-2" style="background: #0B6E8A;">
              <span class="font-mono text-xs text-white font-bold">#0B6E8A</span>
            </div>
            <h4 class="font-display font-semibold text-sm text-[var(--text-primary)]">Cryo Teal</h4>
            <p class="text-xs text-[var(--text-muted)] font-mono">--primary · Structural framing</p>
          </div>

          <!-- Frost Cyan Swatch -->
          <div class="card-notched border-l-4 border-l-[#f97316] p-4">
            <div class="h-16 rounded-sm mb-3 border border-[var(--line)] flex items-end p-2" style="background: #19B5C9;">
              <span class="font-mono text-xs text-[#060d13] font-bold">#19B5C9</span>
            </div>
            <h4 class="font-display font-semibold text-sm text-[var(--text-primary)]">Frost Cyan</h4>
            <p class="text-xs text-[var(--text-muted)] font-mono">--primary-bright · Active states</p>
          </div>

          <!-- Safety Valve Orange Swatch -->
          <div class="card-notched border-l-4 border-l-[#f97316] p-4">
            <div class="h-16 rounded-sm mb-3 border border-[var(--line)] flex items-end p-2" style="background: #FF6B1A;">
              <span class="font-mono text-xs text-white font-bold">#FF6B1A</span>
            </div>
            <h4 class="font-display font-semibold text-sm text-[var(--text-primary)]">Safety Valve Orange</h4>
            <p class="text-xs text-[var(--text-muted)] font-mono">--accent · Primary action CTA</p>
          </div>
        </div>

        <!-- Tonal Surfaces & Status Scale -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <!-- Surface Scale -->
          <div class="card-accent-teal p-5">
            <span class="section-overline" data-en="Engineered Surface Layers" data-ar="طبقات الأسطح المعايرة">Engineered Surface Layers</span>
            <div class="grid grid-cols-3 gap-3 mt-3 text-center">
              <div class="p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-0)]">
                <span class="font-mono text-xs block font-bold">Surface 0</span>
                <span class="text-[10px] text-[var(--text-muted)]">Canvas Layer</span>
              </div>
              <div class="p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-1)]">
                <span class="font-mono text-xs block font-bold">Surface 1</span>
                <span class="text-[10px] text-[var(--text-muted)]">Card Layer</span>
              </div>
              <div class="p-3 border border-[var(--line)] rounded-sm bg-[var(--surface-2)]">
                <span class="font-mono text-xs block font-bold">Surface 2</span>
                <span class="text-[10px] text-[var(--text-muted)]">Panel/Header</span>
              </div>
            </div>
          </div>

          <!-- Status Scale -->
          <div class="card-accent-rule p-5">
            <span class="section-overline" data-en="Safety & Plant Status Scale" data-ar="مقياس حالات الأمان والتشغيل">Safety & Plant Status Scale</span>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 text-center">
              <div class="p-2.5 rounded-sm border" style="background: rgba(31, 169, 113, 0.1); border-color: rgba(31, 169, 113, 0.3); color: var(--status-success);">
                <span class="font-mono text-xs font-bold block">#1FA971</span>
                <span class="text-[10px]">Purged / OK</span>
              </div>
              <div class="p-2.5 rounded-sm border" style="background: rgba(242, 176, 30, 0.1); border-color: rgba(242, 176, 30, 0.3); color: var(--status-warning);">
                <span class="font-mono text-xs font-bold block">#F2B01E</span>
                <span class="text-[10px]">Caution</span>
              </div>
              <div class="p-2.5 rounded-sm border" style="background: rgba(217, 54, 54, 0.1); border-color: rgba(217, 54, 54, 0.3); color: var(--status-danger);">
                <span class="font-mono text-xs font-bold block">#D93636</span>
                <span class="text-[10px]">Hazard</span>
              </div>
              <div class="p-2.5 rounded-sm border" style="background: rgba(47, 128, 237, 0.1); border-color: rgba(47, 128, 237, 0.3); color: var(--status-info);">
                <span class="font-mono text-xs font-bold block">#2F80ED</span>
                <span class="text-[10px]">Notice</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 2: GAS CHEMICAL FORMULA CHIPS & CYLINDER STANDARDS -->
      <div>
        <?= pipe_divider('2. GAS-SPECIFIC COLOR CODING & CYLINDER STANDARDS', 'GAS-02') ?>

        <div class="card-notched p-6 mt-4">
          <div class="mb-4">
            <span class="section-overline" data-en="Standard Industrial Gas Palette" data-ar="ألوان وترميز الغازات الصناعية المعتمدة">Standard Industrial Gas Palette</span>
            <p class="text-xs text-[var(--text-secondary)] mt-1" data-en="Every gas displays its chemical formula with authentic subscripts and standard cylinder shoulder/body color coding." data-ar="يعرض كل منتج صيغته الكيميائية مع أرقام منخفضة صحيحة ولون الأسطوانة القياسي.">
              Every gas displays its chemical formula with authentic subscripts and standard cylinder shoulder/body color coding.
            </p>
          </div>

          <div class="flex flex-wrap gap-2.5">
            <?= gas_chip('O2', 'badge') ?>
            <?= gas_chip('N2', 'badge') ?>
            <?= gas_chip('AR', 'badge') ?>
            <?= gas_chip('CO2', 'badge') ?>
            <?= gas_chip('C2H2', 'badge') ?>
            <?= gas_chip('H2', 'badge') ?>
            <?= gas_chip('HE', 'badge') ?>
            <?= gas_chip('AIR', 'badge') ?>
            <?= gas_chip('LPG', 'badge') ?>
            <?= gas_chip('N2O', 'badge') ?>
          </div>

          <div class="border-t border-[var(--line)] pt-4 mt-6">
            <span class="section-overline" data-en="Cylinder Lifecycle Status Matrix" data-ar="مصفوفة دورة حياة الأسطوانات">Cylinder Lifecycle Status Matrix (Never Color-Only)</span>
            <div class="flex flex-wrap gap-2.5 mt-3">
              <?= cylinder_status_chip('full') ?>
              <?= cylinder_status_chip('in_plant') ?>
              <?= cylinder_status_chip('in_transit') ?>
              <?= cylinder_status_chip('at_customer') ?>
              <?= cylinder_status_chip('empty') ?>
              <?= cylinder_status_chip('under_test') ?>
              <?= cylinder_status_chip('maintenance') ?>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: PRESSURE GAUGE KPIS & TANK FILL LEVEL BARS -->
      <div>
        <?= pipe_divider('3. TECHNICAL GAUGES & LEVEL MONITORS', 'INST-03') ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
          <?= gauge_kpi('BULK O₂ STORAGE', '18.4', 'BAR', 18.4, 0, 25, 'nominal', ['trend' => 'Normal Pressure', 'sub' => 'Line 1 Active']) ?>
          <?= gauge_kpi('FILLING MANIFOLD B', '88', 'CYL/H', 88, 0, 120, 'nominal', ['trend' => '+14% vs Shift A']) ?>
          <?= gauge_kpi('RETEST DUE ALERTS', '6', 'CYL', 6, 0, 20, 'warning', ['trend' => 'Inspection Req.']) ?>
          <?= gauge_kpi('CRYO N₂ PURITY', '99.999', '%', 99.999, 95, 100, 'info', ['trend' => 'Medical Grade']) ?>
        </div>

        <!-- Horizontal Tank-Fill Level Gauges (with 10% Tick Marks) -->
        <div class="card-notched p-6 mt-4">
          <span class="section-overline" data-en="Horizontal Tank-Fill Level Gauges (10% Ticks)" data-ar="مؤشرات امتلاء الخزانات مع علامات مدرجة">Horizontal Tank-Fill Level Gauges (10% Ticks)</span>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
              <?= level_bar(18500, 20000, ' LTR', ['label' => 'Cryogenic Liquid Argon (TK-101)', 'color' => 'fill-success']) ?>
            </div>
            <div>
              <?= level_bar(7200, 20000, ' LTR', ['label' => 'Liquid Nitrogen Cryo Tank (TK-102)', 'color' => 'fill-warning']) ?>
            </div>
            <div>
              <?= level_bar(2100, 20000, ' LTR', ['label' => 'Liquid CO₂ Pressure Vessel (TK-103)', 'color' => 'fill-danger']) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: GHS HAZARD BADGES & SAFETY PROTOCOLS -->
      <div>
        <?= pipe_divider('4. GHS SAFETY SIGNAGE & HAZARD BADGES', 'GHS-04') ?>

        <div class="card-notched p-6 mt-4">
          <span class="section-overline" data-en="GHS Classified Gas Hazard Diamonds" data-ar="شارات الماسات التحذيرية المعتمدة عالمياً">GHS Classified Gas Hazard Diamonds</span>
          <p class="text-xs text-[var(--text-secondary)] mt-1 mb-5" data-en="Mandatory 45-degree diamond badges with safety borders and industrial SVG pictograms on delivery notes, manifests, and product sheets." data-ar="شارات تحذيرية إلزامية على مذكرات التسليم ومنافيست التحميل وبطاقات المنتجات.">
            Mandatory 45-degree diamond badges with safety borders and industrial SVG pictograms on delivery notes, manifests, and product sheets.
          </p>

          <div class="flex items-center justify-around flex-wrap gap-6 py-2">
            <?= hazard_badge('flammable', 'lg') ?>
            <?= hazard_badge('oxidizer', 'lg') ?>
            <?= hazard_badge('compressed_gas', 'lg') ?>
            <?= hazard_badge('toxic', 'lg') ?>
            <?= hazard_badge('cryogenic', 'lg') ?>
            <?= hazard_badge('corrosive', 'lg') ?>
          </div>
        </div>
      </div>

      <!-- SECTION 5: CUSTOM INDUSTRIAL GAS SVG ICON SET -->
      <div>
        <?= pipe_divider('5. INDUSTRIAL GAS VOCABULARY ICON SET', 'ICON-05') ?>

        <div class="card-notched p-6 mt-4">
          <span class="section-overline" data-en="Custom Gas Plant SVGs & Lucide Bridge" data-ar="أيقونات مصنع الغازات المخصصة ومكتبة Lucide">Custom Gas Plant SVGs & Lucide Bridge</span>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 mt-4">
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('cylinder', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">cylinder</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('valve', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">valve</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('regulator', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">regulator</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('pressure_gauge', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">pressure_gauge</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('tanker_truck', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">tanker_truck</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('cryo_tank', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">cryo_tank</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('flame', 'w-6 h-6 text-[var(--accent)]') ?>
              <span class="font-mono text-[11px] font-semibold">flame</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('molecule', 'w-6 h-6 text-[var(--primary-bright)]') ?>
              <span class="font-mono text-[11px] font-semibold">molecule</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('pipeline', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">pipeline</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('filling_station', 'w-6 h-6 text-[var(--primary)]') ?>
              <span class="font-mono text-[11px] font-semibold">filling_station</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('test_stamp', 'w-6 h-6 text-[var(--status-warning)]') ?>
              <span class="font-mono text-[11px] font-semibold">test_stamp</span>
            </div>
            <div class="p-3 border border-[var(--line)] rounded-sm flex flex-col items-center gap-2 text-center bg-[var(--surface-0)]">
              <?= icon('shield-check', 'w-6 h-6 text-[var(--status-success)]') ?>
              <span class="font-mono text-[11px] font-semibold">shield-check</span>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 6: TYPOGRAPHY, BUTTONS & DENSE DATA TABLE -->
      <div>
        <?= pipe_divider('6. CONTROLS, BUTTONS & DENSE DATA TABLE', 'TABLE-06') ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
          <!-- Typography Showcase -->
          <div class="card-notched p-5">
            <span class="section-overline" data-en="Technical Typography Pairing" data-ar="التدرج الطباعي المزدوج">Technical Typography Pairing</span>
            <div class="mt-3 space-y-3">
              <div>
                <span class="text-[10px] font-mono text-[var(--text-muted)]">DISPLAY / HEADINGS (Oxanium & Reem Kufi)</span>
                <h3 class="font-display font-bold text-lg text-[var(--text-primary)]">PRESSURE VESSEL 400 BAR</h3>
              </div>
              <div>
                <span class="text-[10px] font-mono text-[var(--text-muted)]">BODY / UI (IBM Plex Sans & Arabic)</span>
                <p class="text-sm text-[var(--text-secondary)]">Manifold pressure stability maintained across all active distribution lines.</p>
              </div>
              <div>
                <span class="text-[10px] font-mono text-[var(--text-muted)]">NUMERICS (IBM Plex Mono Tabular)</span>
                <p class="font-mono text-sm font-bold num text-[var(--text-primary)]">QTY: 1,480 · WT: 28,450.00 KG · FLOW: 340 L/M</p>
              </div>
            </div>
          </div>

          <!-- Buttons & CTAs -->
          <div class="card-notched p-5">
            <span class="section-overline" data-en="Engineered Button Styles" data-ar="أنماط الأزرار وإجراءات النظام">Engineered Button Styles</span>
            <div class="mt-3 space-y-3">
              <div>
                <button type="button" class="btn-cta-orange w-full">
                  <i data-lucide="zap" class="w-4 h-4"></i>
                  <span data-en="Authorize Gas Fill Run" data-ar="اعتماد دورة تعبئة الغاز">Authorize Gas Fill Run</span>
                </button>
                <span class="text-[10px] font-mono text-[var(--text-muted)] block mt-1">Single Primary CTA per screen (Orange)</span>
              </div>
              <div>
                <button type="button" class="btn-teal-outline w-full">
                  <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                  <span data-en="Export Inspection Protocol" data-ar="تصدير بروتوكول الفحص">Export Inspection Protocol</span>
                </button>
                <span class="text-[10px] font-mono text-[var(--text-muted)] block mt-1">Secondary Action (Teal Outline)</span>
              </div>
            </div>
          </div>

          <!-- Input Controls & Focus Outlines -->
          <div class="card-notched p-5">
            <span class="section-overline" data-en="Form Inputs with 2px Teal Focus" data-ar="حقول الإدخال مع تحديد التركيز 2 بكسل">Form Inputs with 2px Focus</span>
            <div class="mt-3 space-y-2.5">
              <div>
                <label class="text-xs font-semibold text-[var(--text-primary)] block mb-1">Batch Run Serial</label>
                <input type="text" class="w-full px-3 py-1.5 text-xs font-mono rounded-sm border border-[var(--line)] bg-[var(--surface-1)] text-[var(--text-primary)]" value="RUN-O2-2026-B841" placeholder="Enter batch code">
              </div>
              <div>
                <label class="text-xs font-semibold text-[var(--text-primary)] block mb-1">Manifold Target Pressure (BAR)</label>
                <input type="number" class="w-full px-3 py-1.5 text-xs font-mono rounded-sm border border-[var(--line)] bg-[var(--surface-1)] text-[var(--text-primary)]" value="200" placeholder="Target bar">
              </div>
            </div>
          </div>
        </div>

        <!-- Dense Engineered Table Preview -->
        <div class="card-notched overflow-hidden mt-6">
          <div class="px-5 py-4 border-b border-[var(--line)] flex justify-between items-center bg-[var(--surface-2)]">
            <div>
              <span class="section-overline">LIVE PURITY & BATCH REGISTER</span>
              <h3 class="font-display font-bold text-base text-[var(--text-primary)]">Manifold Filling Logs (Real-time Manifest)</h3>
            </div>
            <span class="tag-plate font-mono text-[10px] whitespace-nowrap">MONITOR: READY</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[var(--surface-2)] text-[var(--text-secondary)] font-mono text-[11px] uppercase tracking-wider border-b border-[var(--line)]">
                <tr>
                  <th class="py-3 px-4">Serial / Tag</th>
                  <th class="py-3 px-4">Gas / Product</th>
                  <th class="py-3 px-4">GHS Hazard</th>
                  <th class="py-3 px-4">Pressure</th>
                  <th class="py-3 px-4">Purity Std</th>
                  <th class="py-3 px-4">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--line-soft)] text-[var(--text-primary)] bg-[var(--surface-1)]">
                <tr>
                  <td class="py-3 px-4"><?= tag_plate('CYL-O2-9011') ?></td>
                  <td class="py-3 px-4"><?= gas_chip('O2', 'badge') ?></td>
                  <td class="py-3 px-4"><?= hazard_badge('oxidizer', 'sm') ?></td>
                  <td class="py-3 px-4 font-mono num font-semibold">200.4 BAR</td>
                  <td class="py-3 px-4 font-mono">99.995%</td>
                  <td class="py-3 px-4"><?= cylinder_status_chip('full') ?></td>
                </tr>
                <tr>
                  <td class="py-3 px-4"><?= tag_plate('CYL-N2-3104') ?></td>
                  <td class="py-3 px-4"><?= gas_chip('N2', 'badge') ?></td>
                  <td class="py-3 px-4"><?= hazard_badge('compressed_gas', 'sm') ?></td>
                  <td class="py-3 px-4 font-mono num font-semibold">198.8 BAR</td>
                  <td class="py-3 px-4 font-mono">99.999%</td>
                  <td class="py-3 px-4"><?= cylinder_status_chip('in_transit') ?></td>
                </tr>
                <tr>
                  <td class="py-3 px-4"><?= tag_plate('CYL-C2H2-054') ?></td>
                  <td class="py-3 px-4"><?= gas_chip('C2H2', 'badge') ?></td>
                  <td class="py-3 px-4"><?= hazard_badge('flammable', 'sm') ?></td>
                  <td class="py-3 px-4 font-mono num font-semibold">18.2 BAR</td>
                  <td class="py-3 px-4 font-mono">98.5%</td>
                  <td class="py-3 px-4"><?= cylinder_status_chip('under_test') ?></td>
                </tr>
                <tr>
                  <td class="py-3 px-4"><?= tag_plate('CYL-AR-8832') ?></td>
                  <td class="py-3 px-4"><?= gas_chip('AR', 'badge') ?></td>
                  <td class="py-3 px-4"><?= hazard_badge('compressed_gas', 'sm') ?></td>
                  <td class="py-3 px-4 font-mono num font-semibold">205.1 BAR</td>
                  <td class="py-3 px-4 font-mono">99.998%</td>
                  <td class="py-3 px-4"><?= cylinder_status_chip('at_customer') ?></td>
                </tr>
              </tbody>
            </table>
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
