<?php
/**
 * I-GAS ENTERPRISE — INDUSTRIAL UI & GAS IDENTITY HELPERS
 * "Pressure & Purity" Visual Identity System
 *
 * Implements:
 * - gas_chip(): Chemical formula chips with cylinder color coding
 * - cylinder_status_chip(): Status chips (Full, In Transit, At Customer, etc.)
 * - hazard_badge(): GHS-style diamond badges for industrial gases
 * - gauge_kpi(): Semicircular pressure/capacity KPI card
 * - level_bar(): Horizontal tank-fill level bar with 10% tick marks
 * - icon(): Industrial SVG icon vocabulary + Lucide bridge
 * - pipe_divider(): Engineering pipeline section divider
 * - tag_plate(): Monospace instrument/serial label plate
 */

if (!function_exists('get_gas_catalog')) {
  function get_gas_catalog(): array {
    return [
      'O2' => [
        'formula'  => 'O₂',
        'name_en'  => 'Oxygen',
        'name_ar'  => 'أكسجين',
        'color'   => '#0066CC',
        'color_cap' => '#FFFFFF',
        'hazard'   => 'oxidizer',
        'purity_std' => '99.995%',
      ],
      'N2' => [
        'formula'  => 'N₂',
        'name_en'  => 'Nitrogen',
        'name_ar'  => 'نيتروجين',
        'color'   => '#1F2937',
        'color_cap' => '#1F2937',
        'hazard'   => 'compressed_gas',
        'purity_std' => '99.999%',
      ],
      'AR' => [
        'formula'  => 'Ar',
        'name_en'  => 'Argon',
        'name_ar'  => 'أرجون',
        'color'   => '#047857',
        'color_cap' => '#047857',
        'hazard'   => 'compressed_gas',
        'purity_std' => '99.998%',
      ],
      'CO2' => [
        'formula'  => 'CO₂',
        'name_en'  => 'Carbon Dioxide',
        'name_ar'  => 'ثاني أكسيد الكربون',
        'color'   => '#6B7280',
        'color_cap' => '#6B7280',
        'hazard'   => 'compressed_gas',
        'purity_std' => '99.9%',
      ],
      'C2H2' => [
        'formula'  => 'C₂H₂',
        'name_en'  => 'Acetylene',
        'name_ar'  => 'أسيتيلين',
        'color'   => '#881337',
        'color_cap' => '#881337',
        'hazard'   => 'flammable',
        'purity_std' => '98.5%',
      ],
      'H2' => [
        'formula'  => 'H₂',
        'name_en'  => 'Hydrogen',
        'name_ar'  => 'هيدروجين',
        'color'   => '#DC2626',
        'color_cap' => '#DC2626',
        'hazard'   => 'flammable',
        'purity_std' => '99.999%',
      ],
      'HE' => [
        'formula'  => 'He',
        'name_en'  => 'Helium',
        'name_ar'  => 'هيليوم',
        'color'   => '#78350F',
        'color_cap' => '#78350F',
        'hazard'   => 'compressed_gas',
        'purity_std' => '99.999%',
      ],
      'AIR' => [
        'formula'  => 'Air',
        'name_en'  => 'Compressed Air',
        'name_ar'  => 'هواء مضغوط',
        'color'   => '#374151',
        'color_cap' => '#FFFFFF',
        'hazard'   => 'compressed_gas',
        'purity_std' => 'Medical/Ind',
      ],
      'LPG' => [
        'formula'  => 'LPG',
        'name_en'  => 'Liquefied Gas',
        'name_ar'  => 'غاز بترولي مسال',
        'color'   => '#EA580C',
        'color_cap' => '#EA580C',
        'hazard'   => 'flammable',
        'purity_std' => 'Commercial',
      ],
      'N2O' => [
        'formula'  => 'N₂O',
        'name_en'  => 'Nitrous Oxide',
        'name_ar'  => 'أكسيد النيتروز',
        'color'   => '#2563EB',
        'color_cap' => '#2563EB',
        'hazard'   => 'oxidizer',
        'purity_std' => '99.5%',
      ],
    ];
  }
}

/**
 * Format chemical formula with proper subscripts
 */
if (!function_exists('format_gas_formula')) {
  function format_gas_formula(string $text): string {
    $subscripts = [
      '0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄',
      '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉'
    ];
    return preg_replace_callback('/([A-Za-z]+)(\d+)/', function($m) use ($subscripts) {
      $digits = strtr($m[2], $subscripts);
      return $m[1] . $digits;
    }, $text);
  }
}

/**
 * Renders a Gas Chemical Formula Chip with its industrial cylinder color block
 */
if (!function_exists('gas_chip')) {
  function gas_chip(string $gasCode, string $format = 'badge', string $extraClasses = ''): string {
    $catalog = get_gas_catalog();
    $codeKey = strtoupper(trim(str_replace([' ', '-', '_'], '', $gasCode)));
    
    // Direct or fuzzy lookup
    $gas = $catalog[$codeKey] ?? null;
    if (!$gas) {
      foreach ($catalog as $key => $info) {
        if (stripos($gasCode, $info['name_en']) !== false || stripos($gasCode, $key) !== false) {
          $gas = $info;
          break;
        }
      }
    }

    if (!$gas) {
      $gas = [
        'formula'  => format_gas_formula($gasCode ?: 'GAS'),
        'name_en'  => $gasCode ?: 'Industrial Gas',
        'name_ar'  => $gasCode ?: 'غاز صناعي',
        'color'   => '#4A6375',
        'color_cap' => '#4A6375',
        'hazard'   => 'compressed_gas',
        'purity_std' => 'Std',
      ];
    }

    $color = htmlspecialchars($gas['color']);
    $formula = htmlspecialchars($gas['formula']);
    $nameEn = htmlspecialchars($gas['name_en']);
    $nameAr = htmlspecialchars($gas['name_ar']);

    if ($format === 'formula_only') {
      return sprintf(
        '<span class="gas-chip %s" title="%s / %s">' .
        '<span class="gas-chip-color" style="background-color: %s;"></span>' .
        '<span class="gas-formula">%s</span>' .
        '</span>',
        htmlspecialchars($extraClasses),
        $nameEn, $nameAr,
        $color,
        $formula
      );
    }

    if ($format === 'dot') {
      return sprintf(
        '<span class="inline-flex items-center gap-1.5 %s" title="%s">' .
        '<span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background-color: %s;"></span>' .
        '<span class="font-mono text-xs font-semibold">%s</span>' .
        '</span>',
        htmlspecialchars($extraClasses),
        $nameEn,
        $color,
        $formula
      );
    }

    // Default: Full Badge with formula and bilingual name
    return sprintf(
      '<span class="gas-chip %s">' .
      '<span class="gas-chip-color" style="background-color: %s;"></span>' .
      '<span class="gas-formula">%s</span>' .
      '<span class="text-[11px] text-[var(--text-secondary)] font-normal ms-1" data-en="%s" data-ar="%s">%s</span>' .
      '</span>',
      htmlspecialchars($extraClasses),
      $color,
      $formula,
      $nameEn, $nameAr, $nameEn
    );
  }
}

/**
 * Renders Cylinder Status Chip (Full, In Transit, At Customer, Empty, Under Test, Maintenance)
 */
if (!function_exists('cylinder_status_chip')) {
  function cylinder_status_chip(string $status, string $extraClasses = ''): string {
    $statusClean = strtolower(trim(str_replace([' ', '-'], '_', $status)));
    
    $config = [
      'full' => [
        'color'  => 'var(--status-success)',
        'bg'   => 'rgba(31, 169, 113, 0.1)',
        'border' => 'rgba(31, 169, 113, 0.3)',
        'icon'  => 'check-circle-2',
        'en'   => 'Full (Purged)',
        'ar'   => 'ممتلئ (جاهز)'
      ],
      'in_plant' => [
        'color'  => 'var(--primary)',
        'bg'   => 'rgba(11, 110, 138, 0.1)',
        'border' => 'rgba(11, 110, 138, 0.3)',
        'icon'  => 'gauge',
        'en'   => 'In Plant',
        'ar'   => 'بالمصنع'
      ],
      'in_transit' => [
        'color'  => 'var(--primary-bright)',
        'bg'   => 'rgba(25, 181, 201, 0.1)',
        'border' => 'rgba(25, 181, 201, 0.3)',
        'icon'  => 'truck',
        'en'   => 'In Transit',
        'ar'   => 'قيد النقل'
      ],
      'at_customer' => [
        'color'  => '#2F80ED',
        'bg'   => 'rgba(47, 128, 237, 0.1)',
        'border' => 'rgba(47, 128, 237, 0.3)',
        'icon'  => 'building-2',
        'en'   => 'At Customer',
        'ar'   => 'لدى العميل'
      ],
      'empty' => [
        'color'  => 'var(--text-muted)',
        'bg'   => 'var(--surface-2)',
        'border' => 'var(--line)',
        'icon'  => 'circle',
        'en'   => 'Empty / Depleted',
        'ar'   => 'فارغ'
      ],
      'under_test' => [
        'color'  => 'var(--status-warning)',
        'bg'   => 'rgba(242, 176, 30, 0.1)',
        'border' => 'rgba(242, 176, 30, 0.3)',
        'icon'  => 'activity',
        'en'   => 'Under Test',
        'ar'   => 'تحت الفحص'
      ],
      'maintenance' => [
        'color'  => 'var(--status-danger)',
        'bg'   => 'rgba(217, 54, 54, 0.1)',
        'border' => 'rgba(217, 54, 54, 0.3)',
        'icon'  => 'wrench',
        'en'   => 'Maintenance',
        'ar'   => 'صيانة'
      ]
    ];

    $c = $config[$statusClean] ?? [
      'color'  => 'var(--text-secondary)',
      'bg'   => 'var(--surface-2)',
      'border' => 'var(--line)',
      'icon'  => 'help-circle',
      'en'   => ucfirst($status),
      'ar'   => $status
    ];

    return sprintf(
      '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm text-[11px] font-medium %s" style="color: %s; background-color: %s; border: 1px solid %s;">' .
      '<i data-lucide="%s" class="w-3.5 h-3.5 flex-shrink-0"></i>' .
      '<span data-en="%s" data-ar="%s">%s</span>' .
      '</span>',
      htmlspecialchars($extraClasses),
      $c['color'],
      $c['bg'],
      $c['border'],
      htmlspecialchars($c['icon']),
      htmlspecialchars($c['en']),
      htmlspecialchars($c['ar']),
      htmlspecialchars($c['en'])
    );
  }
}

/**
 * Renders GHS Safety Hazard Diamond Badge
 */
if (!function_exists('hazard_badge')) {
  function hazard_badge(string $hazardType, string $size = 'md'): string {
    $typeClean = strtolower(trim($hazardType));

    $hazardConfigs = [
      'flammable' => [
        'title' => 'Flammable Gas / سريع الاشتعال',
        'icon' => '<path d="M12 2c-.5 2.5-3 5-3 8 0 3.3 2.7 6 6 6s6-2.7 6-6c0-3-2.5-5.5-3-8-1.5 2-3 2.5-4 2 1-1.5 1-3.5 0-2z" fill="currentColor"/>',
        'code' => 'GHS02',
      ],
      'oxidizer' => [
        'title' => 'Oxidizing Gas / غاز مؤكسد',
        'icon' => '<circle cx="12" cy="14" r="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2c-.5 2.5-2 4-2 6 0 1.5 1 3 2 3s2-1.5 2-3c0-2-1.5-3.5-2-6z" fill="currentColor"/>',
        'code' => 'GHS03',
      ],
      'compressed_gas' => [
        'title' => 'Gas Under Pressure / غاز مضغوط',
        'icon' => '<rect x="8" y="7" width="8" height="13" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><rect x="10" y="3" width="4" height="3" fill="currentColor"/>',
        'code' => 'GHS04',
      ],
      'toxic' => [
        'title' => 'Toxic Gas / غاز سام',
        'icon' => '<circle cx="12" cy="9" r="6" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9.5" cy="8.5" r="1.5" fill="currentColor"/><circle cx="14.5" cy="8.5" r="1.5" fill="currentColor"/><path d="M10 18l4-4m-4 0l4 4" stroke="currentColor" stroke-width="2"/>',
        'code' => 'GHS06',
      ],
      'cryogenic' => [
        'title' => 'Cryogenic Liquid / سوائل مبردة فائقاً',
        'icon' => '<path d="M12 2v20M2 12h20M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2"/>',
        'code' => 'CRYO',
      ],
      'corrosive' => [
        'title' => 'Corrosive / مادة مسببة للتآكل',
        'icon' => '<path d="M5 4l5 8h4l5-8M9 17h6v3H9z" fill="none" stroke="currentColor" stroke-width="2"/>',
        'code' => 'GHS05',
      ]
    ];

    $h = $hazardConfigs[$typeClean] ?? $hazardConfigs['compressed_gas'];

    $dim = $size === 'sm' ? 24 : ($size === 'lg' ? 40 : 30);
    $iconDim = $size === 'sm' ? 14 : ($size === 'lg' ? 22 : 18);

    return sprintf(
      '<div class="ghs-diamond-wrap" title="%s">' .
      '<div class="ghs-diamond" style="width:%dpx; height:%dpx;">' .
      '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" style="color: #0A1B26;">%s</svg>' .
      '</div>' .
      '<span class="font-mono text-[9px] font-bold tracking-wider uppercase text-[var(--text-muted)]">%s</span>' .
      '</div>',
      htmlspecialchars($h['title']),
      $dim, $dim,
      $iconDim, $iconDim,
      $h['icon'],
      htmlspecialchars($h['code'])
    );
  }
}

/**
 * Renders Horizontal Tank-Fill Level Bar with 10% tick marks
 */
if (!function_exists('level_bar')) {
  function level_bar(float $current, float $max = 100, string $unit = '%', array $options = []): string {
    $pct = $max > 0 ? max(0, min(100, round(($current / $max) * 100))) : 0;
    
    $colorClass = 'fill-primary';
    if ($pct < 20) {
      $colorClass = 'fill-danger';
    } elseif ($pct < 40) {
      $colorClass = 'fill-warning';
    } elseif ($pct >= 80) {
      $colorClass = 'fill-success';
    }

    if (!empty($options['color'])) {
      $colorClass = $options['color'];
    }

    $showTicks = $options['show_ticks'] ?? true;
    $showLabel = $options['show_label'] ?? true;
    $label = $options['label'] ?? '';

    $html = '<div class="level-bar-container my-1">';
    if ($showLabel && $label) {
      $html .= sprintf(
        '<div class="flex justify-between items-center text-xs font-mono mb-1">' .
        '<span class="text-[var(--text-secondary)]">%s</span>' .
        '<span class="font-bold text-[var(--text-primary)]">%s%s <span class="font-normal text-[var(--text-muted)]">(%d%%)</span></span>' .
        '</div>',
        htmlspecialchars($label),
        number_format($current),
        htmlspecialchars($unit),
        $pct
      );
    }

    $html .= '<div class="level-bar-track">';
    $html .= sprintf('<div class="level-bar-fill %s" style="width: %d%%;"></div>', htmlspecialchars($colorClass), $pct);
    $html .= '</div>';

    if ($showTicks) {
      $html .= '<div class="level-bar-ticks">';
      $html .= '<span>0</span><span>20</span><span>40</span><span>60</span><span>80</span><span>100%</span>';
      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }
}

/**
 * Renders a Semicircular Technical Pressure Gauge KPI Card
 */
if (!function_exists('gauge_kpi')) {
  function gauge_kpi(string $title, $value, string $unit, float $current = 50, float $min = 0, float $max = 100, string $status = 'nominal', array $options = []): string {
    $pct = $max > $min ? max(0, min(100, (($current - $min) / ($max - $min)) * 100)) : 0;
    // Gauge angle: 180 degrees total (from 180 to 360/0)
    $rotation = -90 + ($pct * 1.8); // -90deg is full left, +90deg is full right

    $colorMap = [
      'nominal' => 'var(--status-success)',
      'warning' => 'var(--status-warning)',
      'danger' => 'var(--status-danger)',
      'info'  => 'var(--primary-bright)',
    ];
    $barColor = $colorMap[$status] ?? 'var(--primary)';

    $sub = $options['sub'] ?? '';
    $trend = $options['trend'] ?? '';

    return sprintf(
      '<div class="card-notched p-5 flex flex-col justify-between">' .
      ' <div class="flex justify-between items-start mb-2">' .
      '  <div>' .
      '   <span class="section-overline">%s</span>' .
      '   <h4 class="font-display font-bold text-xl text-[var(--text-primary)] mt-0.5">%s <span class="font-mono text-xs font-normal text-[var(--text-secondary)]">%s</span></h4>' .
      '  </div>' .
      '  <span class="status-dot mt-1.5" style="background: %s;" title="Status: %s"></span>' .
      ' </div>' .
      ' <div class="my-3 flex items-center justify-center">' .
      '  <svg width="120" height="65" viewBox="0 0 120 65" class="overflow-visible">' .
      '   <!-- Gauge Background Track -->' .
      '   <path d="M 10 60 A 50 50 0 0 1 110 60" fill="none" stroke="var(--surface-2)" stroke-width="8" stroke-linecap="round"/>' .
      '   <!-- Danger zones or active segment -->' .
      '   <path d="M 10 60 A 50 50 0 0 1 110 60" fill="none" stroke="%s" stroke-width="8" stroke-dasharray="157" stroke-dashoffset="%f" stroke-linecap="round"/>' .
      '   <!-- Center Pivot -->' .
      '   <circle cx="60" cy="60" r="5" fill="var(--ink)"/>' .
      '   <!-- Needle -->' .
      '   <line x1="60" y1="60" x2="60" y2="18" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" transform="rotate(%f 60 60)"/>' .
      '  </svg>' .
      ' </div>' .
      ' <div class="flex justify-between items-center text-[10px] font-mono text-[var(--text-muted)] border-t border-[var(--line-soft)] pt-2 mt-1">' .
      '  <span>%s %s</span>' .
      '  <span>%s</span>' .
      '  <span>%s %s</span>' .
      ' </div>' .
      '</div>',
      htmlspecialchars($title),
      htmlspecialchars((string)$value),
      htmlspecialchars($unit),
      $barColor,
      htmlspecialchars($status),
      $barColor,
      157 - (157 * ($pct / 100)),
      $rotation,
      number_format($min), htmlspecialchars($unit),
      htmlspecialchars($trend ?: ($sub ?: number_format($current) . ' ' . $unit)),
      number_format($max), htmlspecialchars($unit)
    );
  }
}

/**
 * Engineering Pipeline Section Divider with SVG Joint/Valve Node
 */
if (!function_exists('pipe_divider')) {
  function pipe_divider(string $label = '', string $nodeCode = ''): string {
    return sprintf(
      '<div class="pipe-divider">' .
      ' <span class="pipe-node">' .
      '  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="flex-shrink-0">' .
      '   <circle cx="12" cy="12" r="3"/>' .
      '   <path d="M3 12h6m6 0h6M12 3v6m0 6v6"/>' .
      '  </svg>' .
      '  <span>%s</span>' .
      '  %s' .
      ' </span>' .
      '</div>',
      htmlspecialchars($label ?: 'P&ID PIPELINE'),
      $nodeCode ? '<span class="text-[var(--text-muted)] font-normal">[' . htmlspecialchars($nodeCode) . ']</span>' : ''
    );
  }
}

/**
 * Serial / Instrument Label Plate Tag
 */
if (!function_exists('tag_plate')) {
  function tag_plate(string $code, string $prefix = ''): string {
    return sprintf(
      '<span class="tag-plate whitespace-nowrap">' .
      '%s<span class="font-bold">%s</span>' .
      '</span>',
      $prefix ? '<span class="text-[var(--text-muted)] uppercase text-[9px]">' . htmlspecialchars($prefix) . ':</span>' : '',
      htmlspecialchars($code)
    );
  }
}

/**
 * Industrial Gas Icons and Lucide Bridge
 */
if (!function_exists('icon')) {
  function icon(string $name, string $classes = 'w-4 h-4', array $options = []): string {
    $ariaHidden = empty($options['aria_label']) ? 'aria-hidden="true"' : '';
    $ariaLabel = !empty($options['aria_label']) ? 'aria-label="' . htmlspecialchars($options['aria_label']) . '"' : '';

    // Custom Industrial Gas SVG Icons
    $customIcons = [
      'cylinder' => '<path d="M8 7a4 4 0 0 1 8 0v12a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V7z" stroke="currentColor" stroke-width="1.75"/><rect x="10" y="3" width="4" height="4" rx="1" fill="currentColor"/>',
      'valve' => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/><path d="M4 12h5m6 0h5M12 4v5m0 6v5M8 6l8 0" stroke="currentColor" stroke-width="1.75"/>',
      'regulator' => '<circle cx="12" cy="10" r="6" stroke="currentColor" stroke-width="1.75"/><line x1="12" y1="10" x2="15" y2="7" stroke="currentColor" stroke-width="1.75"/><path d="M6 16v4h12v-4" stroke="currentColor" stroke-width="1.75"/>',
      'pressure_gauge' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.75"/><line x1="12" y1="12" x2="16" y2="8" stroke="currentColor" stroke-width="2"/><path d="M8 12a4 4 0 0 1 8 0" stroke="currentColor" stroke-dasharray="2 2"/>',
      'tanker_truck' => '<rect x="2" y="7" width="13" height="10" rx="3" stroke="currentColor" stroke-width="1.75"/><path d="M15 11h4l3 3v3h-7v-6z" stroke="currentColor" stroke-width="1.75"/><circle cx="6" cy="18" r="2" stroke="currentColor" stroke-width="1.75"/><circle cx="18" cy="18" r="2" stroke="currentColor" stroke-width="1.75"/>',
      'cryo_tank' => '<rect x="4" y="6" width="16" height="12" rx="4" stroke="currentColor" stroke-width="1.75"/><path d="M2 18h20M7 18v3M17 18v3M12 3v3" stroke="currentColor" stroke-width="1.75"/>',
      'flame' => '<path d="M12 2c-.5 2.5-3 5-3 8 0 3.3 2.7 6 6 6s6-2.7 6-6c0-3-2.5-5.5-3-8-1.5 2-3 2.5-4 2 1-1.5 1-3.5 0-2z" stroke="currentColor" stroke-width="1.75" fill="none"/>',
      'molecule' => '<circle cx="6" cy="6" r="3" stroke="currentColor" stroke-width="1.75"/><circle cx="18" cy="6" r="3" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="18" r="3" stroke="currentColor" stroke-width="1.75"/><line x1="8" y1="8" x2="16" y2="8" stroke="currentColor" stroke-width="1.75"/><line x1="7" y1="9" x2="11" y2="16" stroke="currentColor" stroke-width="1.75"/><line x1="17" y1="9" x2="13" y2="16" stroke="currentColor" stroke-width="1.75"/>',
      'pipeline' => '<path d="M2 12h20M6 8v8M18 8v8" stroke="currentColor" stroke-width="1.75"/>',
      'filling_station' => '<path d="M4 4h8v16H4z" stroke="currentColor" stroke-width="1.75"/><path d="M12 9h4a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-1" stroke="currentColor" stroke-width="1.75"/><circle cx="8" cy="9" r="2" stroke="currentColor" stroke-width="1.75"/>',
      'test_stamp' => '<polygon points="12,2 15,8 22,9 17,14 18,21 12,18 6,21 7,14 2,9 9,8" stroke="currentColor" stroke-width="1.75" fill="none"/>'
    ];

    $iconKey = strtolower(trim(str_replace('-', '_', $name)));
    if (isset($customIcons[$iconKey])) {
      return sprintf(
        '<svg data-icon="%s" class="inline-block flex-shrink-0 %s" viewBox="0 0 24 24" fill="none" %s %s>%s</svg>',
        htmlspecialchars($iconKey),
        htmlspecialchars($classes),
        $ariaHidden,
        $ariaLabel,
        $customIcons[$iconKey]
      );
    }

    // Bridge to Lucide standard icon
    $lucideName = str_replace('_', '-', $name);
    return sprintf(
      '<i data-lucide="%s" class="%s flex-shrink-0" %s %s></i>',
      htmlspecialchars($lucideName),
      htmlspecialchars($classes),
      $ariaHidden,
      $ariaLabel
    );
  }
}
