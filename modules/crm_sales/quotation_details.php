<?php
session_start();
require_once '../../config/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: quotations.php");
    exit;
}

$quotation_id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT q.*, p.company_name, p.email, p.phone, p.address, p.city, p.country, p.cr_number 
    FROM quotations q 
    LEFT JOIN partners p ON q.client_id = p.id 
    WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$quote = $stmt->fetch();

if (!$quote) {
    header("Location: quotations.php");
    exit;
}

$stmt_items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt_items->execute([$quotation_id]);
$items = $stmt_items->fetchAll();

$active_page = 'quotations';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Sales & Quotations', 'Quotation Details'];

$statusStyles = [
    'draft'    => ['bg' => 'transparent', 'fg' => '#A6A39D', 'dot' => 'transparent', 'label' => 'Draft', 'dotBorder' => 'border:1px solid var(--mute-soft);'],
    'sent'     => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Sent', 'dotBorder' => ''],
    'accepted' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Accepted', 'dotBorder' => ''],
    'declined' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Declined', 'dotBorder' => ''],
    'expired'  => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Expired', 'dotBorder' => ''],
];

$current_status = $statusStyles[$quote['status']] ?? $statusStyles['draft'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quote['quotation_number']) ?> Details | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A; --ink-soft: #2E2E2E; --paper: #FFFFFF; --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC; --line: #D8D6D1; --line-soft: #E7E5E1; --accent: #9A7B2E;
            --accent-soft: #FBF3DF; --mute: #767470; --mute-soft: #A6A39D;
            --sidebar: #1A1A1A; --sidebar-line: #2E2E2E; --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; }
        html { font-size: 16px; }
        body { font-family: 'IBM Plex Sans', sans-serif; background-color: var(--paper-dim); color: var(--ink); font-feature-settings: "tnum" 1; }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        a, button { -webkit-tap-highlight-color: transparent; }
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: none; cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; text-transform: uppercase; letter-spacing: 0.05em; }
        .label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 4px; }
        .value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
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
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-start mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="quotations.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Quotations</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Details</span>
                    </div>
                    <div class="flex items-center gap-4 mt-1">
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none num" style="color: var(--ink);"><?= htmlspecialchars($quote['quotation_number']) ?></h2>
                        <span class="pill mt-1" style="background: <?= $current_status['bg'] ?>; color: <?= $current_status['fg'] ?>;">
                            <span class="status-dot" style="background:<?= $current_status['dot'] ?>;<?= $current_status['dotBorder'] ?>"></span><?= $current_status['label'] ?>
                        </span>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print / PDF
                    </button>
                    <?php if($quote['status'] === 'draft' || $quote['status'] === 'sent'): ?>
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2 text-[#963B33] border-[#963B33] hover:bg-[#F8E9E7]">
                        <i data-lucide="x" class="w-4 h-4"></i>Decline
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2 bg-[#45663F] hover:bg-[#3b5735]">
                        <i data-lucide="check" class="w-4 h-4"></i>Mark Accepted
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                
                <div class="card rounded-md p-6 lg:col-span-2">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Client Information</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-4">
                        <div>
                            <span class="label">Company Name</span>
                            <span class="value"><?= htmlspecialchars($quote['company_name'] ?? 'N/A') ?></span>
                        </div>
                        <div>
                            <span class="label">CR Number</span>
                            <span class="value mono"><?= htmlspecialchars($quote['cr_number'] ?? 'N/A') ?></span>
                        </div>
                        <div>
                            <span class="label">Email</span>
                            <span class="value"><?= htmlspecialchars($quote['email'] ?? 'N/A') ?></span>
                        </div>
                        <div>
                            <span class="label">Phone</span>
                            <span class="value mono"><?= htmlspecialchars($quote['phone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="md:col-span-2">
                            <span class="label">Address</span>
                            <span class="value"><?= htmlspecialchars(($quote['address'] ?? '') . ', ' . ($quote['city'] ?? '') . ', ' . ($quote['country'] ?? '')) ?></span>
                        </div>
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Quotation Details</h3>
                    <div class="flex flex-col gap-5">
                        <div class="flex justify-between items-center">
                            <span class="label mb-0">Issue Date</span>
                            <span class="value mono text-right"><?= htmlspecialchars(date('d M Y', strtotime($quote['issue_date']))) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="label mb-0">Expiry Date</span>
                            <span class="value mono text-right"><?= htmlspecialchars(date('d M Y', strtotime($quote['expiry_date']))) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="label mb-0">General Specs</span>
                            <span class="value text-right max-w-[150px] truncate" title="<?= htmlspecialchars($quote['specs']) ?>"><?= htmlspecialchars($quote['specs'] ?? 'Standard') ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card rounded-md mb-6 overflow-hidden">
                <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Line Items & Pricing</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="background: var(--paper-deep); color: var(--mute); border-color: var(--line-soft);">
                                <th class="px-6 py-3 font-medium">Description</th>
                                <th class="px-6 py-3 font-medium text-right">Qty</th>
                                <th class="px-6 py-3 font-medium">Unit</th>
                                <th class="px-6 py-3 font-medium text-right">Unit Price (SAR)</th>
                                <th class="px-6 py-3 font-medium text-right">Total (SAR)</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if(empty($items)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-6 text-[13px]" style="color: var(--mute);">No line items found for this quotation.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 font-medium" style="color: var(--ink);"><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="px-6 py-4 text-right mono num" style="color: var(--ink);"><?= number_format($item['qty'], 2) ?></td>
                                    <td class="px-6 py-4 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($item['unit']) ?></td>
                                    <td class="px-6 py-4 text-right mono num" style="color: var(--ink);"><?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="px-6 py-4 text-right mono num font-medium" style="color: var(--ink);"><?= number_format($item['line_total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <div class="card rounded-md p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-[15px] font-semibold tracking-tight mb-4 pb-3 border-b" style="color: var(--ink); border-color: var(--line-soft);">Logistics & Notes</h3>
                        <p class="text-[13px] leading-relaxed" style="color: var(--mute);">
                            <?= nl2br(htmlspecialchars($quote['notes'] ?? 'No special instructions or notes provided.')) ?>
                        </p>
                    </div>
                </div>

                <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Calculation Summary</h3>
                    <ul class="text-[13.5px] flex flex-col gap-4 mb-2">
                        <li class="flex justify-between items-center text-[13px]" style="color: var(--mute);">
                            <span>Subtotal</span>
                            <span class="mono num" style="color: var(--ink);"><?= number_format($quote['subtotal'], 2) ?> SAR</span>
                        </li>
                        <li class="flex justify-between items-center text-[13px]" style="color: var(--mute);">
                            <span>VAT (15%)</span>
                            <span class="mono num" style="color: var(--ink);"><?= number_format($quote['vat_amount'], 2) ?> SAR</span>
                        </li>
                        <li class="flex justify-between items-center pt-4 border-t" style="border-color: var(--line-soft);">
                            <span class="font-semibold text-[15px]" style="color: var(--ink);">Total Value</span>
                            <span class="mono num text-[18px] font-bold" style="color: var(--ink);"><?= number_format($quote['total_value'], 2) ?> SAR</span>
                        </li>
                    </ul>
                </div>

            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>