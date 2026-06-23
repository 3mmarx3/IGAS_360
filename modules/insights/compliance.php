<?php
$active_page = 'compliance';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Insights', 'Compliance & Regulations'];

$compliance_score = 98.5;
$active_certs     = 14;
$pending_audits   = 2;
$open_violations  = 0;

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
    'valid'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Valid & Compliant'],
    'expiring' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Expiring Soon'],
    'expired'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Expired / Action Req.'],
];

$auditStyles = [
    'passed'   => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
    'resolved' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'pending'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance & Regulations | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A;
            --ink-soft: #2E2E2E;
            --paper: #FFFFFF;
            --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC;
            --line: #D8D6D1;
            --line-soft: #E7E5E1;
            --accent: #9A7B2E;
            --accent-soft: #FBF3DF;
            --mute: #767470;
            --mute-soft: #A6A39D;
            --sidebar: #1A1A1A;
            --sidebar-line: #2E2E2E;
            --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; }
        html { font-size: 16px; }
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: var(--paper-dim);
            color: var(--ink);
            font-feature-settings: "tnum" 1;
        }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        a, button { -webkit-tap-highlight-color: transparent; }

        .nav-row { position: relative; border-left: 2px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }

        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); transition: width 0.3s ease; }

        th, td { vertical-align: middle; }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .timeline-rail { position: relative; }
        .timeline-rail::before { content: ''; position: absolute; left: 19.5px; top: 4px; bottom: 4px; width: 1px; background: var(--line-soft); }
        .timeline-dot { width: 7px; height: 7px; border-radius: 50%; border: 2px solid var(--mute-soft); background: var(--paper); flex-shrink: 0; position: relative; z-index: 1; }
        .timeline-dot.passed { border-color: #45663F; background: #45663F; }
        .timeline-dot.resolved { border-color: #2A6B8A; background: #2A6B8A; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

        <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
            <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
                <span class="status-dot" style="background: #5C8A5C;"></span>System Nominal
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Enterprise Insights</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Compliance &amp; Regulations</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor ISO certifications, HSE metrics, regulatory permits, and internal audit scores.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="w-4 h-4"></i>Schedule Audit
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Compliance Report
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Overall Compliance Score</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $compliance_score ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $compliance_score ?>%; background: #45663F;"></div>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Certifications</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $active_certs ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">ISO &amp; Local Authorities</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Pending Audits / Reviews</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $pending_audits ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="calendar-clock" class="w-3 h-3"></i>Scheduled within 30 days
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Open HSE Violations</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $open_violations > 0 ? '#963B33' : 'var(--ink)' ?>;"><?= $open_violations ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="shield-check" class="w-3 h-3"></i>Zero active incidents
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Certifications & Permits Registry</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search document or authority..." class="pl-8 pr-3 py-1 bg-white border rounded-sm text-[12px] w-56" style="border-color: var(--line);">
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                    <th class="pl-6 py-3 font-medium">Document ID & Name</th>
                                    <th class="px-3 py-3 font-medium">Issuing Authority</th>
                                    <th class="px-3 py-3 font-medium">Category</th>
                                    <th class="px-3 py-3 font-medium text-right">Valid Until</th>
                                    <th class="pr-6 py-3 font-medium text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($certifications as $cert): ?>
                                <?php 
                                    $ss = $statusStyles[$cert['status']]; 
                                    $isExpired = $cert['status'] === 'expired';
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 py-3.5">
                                        <div class="flex flex-col">
                                            <span class="font-medium" style="color: <?= $isExpired ? 'var(--mute)' : 'var(--ink)' ?>;"><?= htmlspecialchars($cert['name']) ?></span>
                                            <span class="text-[11px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($cert['id']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px]" style="color: var(--ink);"><?= htmlspecialchars($cert['authority']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px]" style="color: var(--mute);"><?= htmlspecialchars($cert['category']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium mono num" style="color: <?= $isExpired ? '#963B33' : 'var(--ink)' ?>;">
                                        <?= htmlspecialchars(date('d M Y', strtotime($cert['expiry']))) ?>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <span class="pill" style="background: <?= $ss['bg'] ?>; color: <?= $ss['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $ss['dot'] ?>;"></span><?= $ss['label'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Audits & Inspections</h3>
                    </div>
                    <div class="p-6 timeline-rail flex-1 overflow-y-auto">
                        <?php foreach ($audit_logs as $index => $audit): ?>
                        <div class="flex gap-3 pb-6 relative">
                            <div class="timeline-dot mt-1.5 <?= $audit['status'] ?>"></div>
                            <div class="w-full">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="text-[13.5px] font-semibold" style="color: var(--ink);"><?= htmlspecialchars($audit['title']) ?></p>
                                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-sm" style="background: <?= $auditStyles[$audit['status']]['bg'] ?>; color: <?= $auditStyles[$audit['status']]['fg'] ?>;">
                                        <?= ucfirst($audit['status']) ?>
                                    </span>
                                </div>
                                <p class="text-[12px]" style="color: var(--mute);">Conducted by: <?= htmlspecialchars($audit['auditor']) ?></p>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-[11px] mono" style="color: var(--mute-soft);"><i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i><?= htmlspecialchars($audit['date']) ?></span>
                                    <span class="text-[11px] mono" style="color: <?= $audit['findings'] > 0 ? '#9A7B2E' : 'var(--mute-soft)' ?>;">
                                        <i data-lucide="file-warning" class="w-3 h-3 inline mr-1"></i><?= $audit['findings'] ?> Findings
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="border-t p-3" style="border-color: var(--line-soft); background: var(--paper-dim);">
                        <button class="w-full text-center text-[12px] font-medium py-1.5 transition-colors" style="color: var(--ink);">View Master Audit Log →</button>
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