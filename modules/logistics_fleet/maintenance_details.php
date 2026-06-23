<?php
$active_page = 'maintenance_fuel';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel', 'Ticket Details'];

$ticket_id = $_GET['id'] ?? 'MNT-4021';

// Mock data for the specific ticket
$ticket = [
    'id'            => $ticket_id,
    'vehicle_id'    => 'FLT-001',
    'vehicle_model' => 'Mercedes Actros',
    'plate'         => 'T S A 1234',
    'odometer'      => '142,500',
    'type'          => 'Preventive Maintenance',
    'status'        => 'in_progress',
    'date'          => '2026-06-22',
    'workshop'      => 'Jeddah Main Garage (Internal)',
    'mechanic'      => 'Tariq Hassan',
    'total_cost'    => '1,250.00',
    'parts_cost'    => '850.00',
    'labor_cost'    => '400.00',
    'notes'         => 'Perform standard 10,000 KM preventive maintenance. Check cryogenic valve seals, inspect braking system, and replace primary fuel filter.'
];

$tasks = [
    ['task' => 'Engine oil and filter replacement', 'status' => 'done'],
    ['task' => 'Cryogenic valve seal inspection', 'status' => 'done'],
    ['task' => 'Brake pad thickness measurement', 'status' => 'pending'],
    ['task' => 'Primary fuel filter replacement', 'status' => 'pending'],
    ['task' => 'Tire pressure & alignment check', 'status' => 'pending'],
];

$statusStyles = [
    'scheduled'   => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'Scheduled'],
    'in_progress' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In Workshop'],
    'completed'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Completed'],
    'overdue'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Overdue'],
];

$ts = $statusStyles[$ticket['status']] ?? $statusStyles['scheduled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= htmlspecialchars($ticket['id']) ?> | I-GAS Enterprise</title>
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
        .btn-success { background: #EAF1E7; color: #45663F; border: 1px solid #45663F; transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; font-weight: 500; }
        .btn-success:hover { background: #DDE8D9; }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .info-group { margin-bottom: 20px; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .info-value { font-size: 14px; color: var(--ink); font-weight: 500; }

        .task-row { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--line-soft); }
        .task-row:last-child { border-bottom: none; padding-bottom: 0; }
        .task-checkbox { width: 16px; height: 16px; border: 1.5px solid var(--mute-soft); border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
        .task-checkbox.done { background: var(--ink); border-color: var(--ink); color: var(--paper); }
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

            <a href="maintenance_fuel.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Maintenance Registry
            </a>

            <div class="flex justify-between items-start mb-7">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none mono" style="color: var(--ink);"><?= htmlspecialchars($ticket['id']) ?></h2>
                        <span class="pill" style="background: <?= $ts['bg'] ?>; color: <?= $ts['fg'] ?>;">
                            <span class="status-dot" style="background:<?= $ts['dot'] ?>;"></span><?= $ts['label'] ?>
                        </span>
                    </div>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Scheduled on <?= htmlspecialchars(date('d M Y', strtotime($ticket['date']))) ?></p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Job Card
                    </button>
                    <?php if($ticket['status'] !== 'completed'): ?>
                    <button class="btn-success px-4 py-2.5 rounded-sm text-[13.5px] flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>Mark as Completed
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                <div class="xl:col-span-2 flex flex-col gap-6">
                    
                    <div class="card rounded-md p-6">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                            <i data-lucide="info" class="w-4 h-4" style="color: var(--mute);"></i>Service Overview
                        </h3>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="info-group">
                                <p class="info-label">Service Type</p>
                                <p class="info-value"><?= htmlspecialchars($ticket['type']) ?></p>
                            </div>
                            <div class="info-group">
                                <p class="info-label">Assigned Workshop</p>
                                <p class="info-value"><?= htmlspecialchars($ticket['workshop']) ?></p>
                            </div>
                            <div class="info-group">
                                <p class="info-label">Lead Mechanic</p>
                                <p class="info-value flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4" style="color: var(--mute);"></i>
                                    <?= htmlspecialchars($ticket['mechanic']) ?>
                                </p>
                            </div>
                            <div class="info-group">
                                <p class="info-label">Diagnostic Notes & Instructions</p>
                                <p class="text-[13.5px]" style="color: var(--ink-soft); line-height: 1.5;"><?= htmlspecialchars($ticket['notes']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>Asset Identification
                            </h3>
                            
                            <div class="info-group">
                                <p class="info-label">Fleet Unit</p>
                                <p class="info-value"><?= htmlspecialchars($ticket['vehicle_model']) ?></p>
                                <p class="text-[12px] mono mt-1" style="color: var(--mute);"><?= htmlspecialchars($ticket['vehicle_id']) ?> — <?= htmlspecialchars($ticket['plate']) ?></p>
                                <a href="vehicle_logs.php?id=<?= $ticket['vehicle_id'] ?>" class="text-[11px] font-medium underline mt-2 inline-block" style="color: var(--ink);">View Vehicle Profile</a>
                            </div>

                            <div class="info-group mb-0 mt-6 pt-6 border-t" style="border-color: var(--line-soft);">
                                <p class="info-label">Current Odometer</p>
                                <p class="info-value num"><?= htmlspecialchars($ticket['odometer']) ?> <span class="text-[12px] font-normal" style="color: var(--mute);">KM</span></p>
                            </div>
                        </div>

                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="receipt" class="w-4 h-4" style="color: var(--mute);"></i>Cost Breakdown (Est.)
                            </h3>
                            
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-[13.5px]" style="color: var(--mute);">Spare Parts</span>
                                <span class="text-[13.5px] num font-medium" style="color: var(--ink);">SAR <?= htmlspecialchars($ticket['parts_cost']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-[13.5px]" style="color: var(--mute);">Labor Fees</span>
                                <span class="text-[13.5px] num font-medium" style="color: var(--ink);">SAR <?= htmlspecialchars($ticket['labor_cost']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-5">
                                <span class="text-[13.5px]" style="color: var(--mute);">Taxes (VAT 15%)</span>
                                <span class="text-[13.5px] num font-medium" style="color: var(--ink);">Included</span>
                            </div>

                            <div class="flex justify-between items-center pt-4 border-t" style="border-color: var(--line);">
                                <span class="text-[12px] font-semibold uppercase tracking-wide" style="color: var(--ink);">Total Cost</span>
                                <span class="text-[18px] num font-semibold" style="color: var(--ink);">SAR <?= htmlspecialchars($ticket['total_cost']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-1 flex flex-col gap-6">
                    <div class="card rounded-md p-6 flex-1">
                        <div class="flex justify-between items-center mb-6 pb-4 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);">
                                <i data-lucide="check-square" class="w-4 h-4" style="color: var(--mute);"></i>Task Checklist
                            </h3>
                            <span class="text-[11px] font-medium" style="color: var(--mute);">2/5 Done</span>
                        </div>
                        
                        <div class="flex flex-col">
                            <?php foreach ($tasks as $t): ?>
                                <div class="task-row">
                                    <?php if($t['status'] === 'done'): ?>
                                        <div class="task-checkbox done"><i data-lucide="check" class="w-3 h-3"></i></div>
                                        <span class="text-[13.5px]" style="color: var(--mute); text-decoration: line-through;"><?= htmlspecialchars($t['task']) ?></span>
                                    <?php else: ?>
                                        <div class="task-checkbox"></div>
                                        <span class="text-[13.5px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($t['task']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="w-full mt-6 btn-secondary py-2 rounded-sm text-[12px] font-medium border-dashed">
                            + Add Custom Task
                        </button>
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