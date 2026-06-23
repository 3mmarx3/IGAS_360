<?php
$active_page = 'clients';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'Edit Profile'];

$client_id = isset($_GET['id']) ? $_GET['id'] : 'ACC-1042';

$client = [
    'id'           => $client_id,
    'name'         => 'SABIC Petrochemicals',
    'initials'     => 'SP',
    'type'         => 'Corporate',
    'segment'      => 'Industrial Gas',
    'status'       => 'active',
    'since'        => '2019-03-11',
    'contact_name' => 'Faisal Al-Rashid',
    'contact_role' => 'Procurement Manager',
    'email'        => 'f.alrashid@sabic-procure.com',
    'phone'        => '+966 11 401 2200',
    'address'      => 'Industrial City 2, Jubail, Saudi Arabia',
    'tax_id'       => '300012345600003'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?= htmlspecialchars($client['name']) ?> | I-GAS Enterprise</title>
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
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .btn-danger {
            background: var(--paper); color: #963B33; border: 1px solid var(--line);
            transition: all 0.15s ease; display: inline-flex; justify-content: center; align-items: center;
        }
        .btn-danger:hover { background: #F8E9E7; border-color: #963B33; }

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 6px; }
        .form-input {
            width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 2px;
            padding: 8px 12px; font-size: 13.5px; color: var(--ink); transition: border-color 0.15s ease;
        }
        .form-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
        .form-input:disabled, .form-input.readonly { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }
        
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
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="client_profile.php?id=<?= htmlspecialchars($client['id']) ?>" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Client Profile</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Account Settings</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Edit Profile: <?= htmlspecialchars($client['name']) ?></h2>
                </div>
                <div class="flex gap-3">
                    <a href="client_profile.php?id=<?= htmlspecialchars($client['id']) ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Changes
                    </button>
                </div>
            </div>

            <form action="#" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Company Information</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Company / Client Name</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($client['name']) ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($client['id']) ?>" disabled>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Tax / VAT ID</label>
                                    <input type="text" class="form-input mono num" value="<?= htmlspecialchars($client['tax_id']) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client Type</label>
                                    <select class="form-input mono">
                                        <option value="Corporate" <?= $client['type'] === 'Corporate' ? 'selected' : '' ?>>Corporate</option>
                                        <option value="SME" <?= $client['type'] === 'SME' ? 'selected' : '' ?>>SME</option>
                                        <option value="Government" <?= $client['type'] === 'Government' ? 'selected' : '' ?>>Government</option>
                                        <option value="Individual" <?= $client['type'] === 'Individual' ? 'selected' : '' ?>>Individual</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Industry Segment</label>
                                    <select class="form-input mono">
                                        <option value="Industrial Gas" <?= $client['segment'] === 'Industrial Gas' ? 'selected' : '' ?>>Industrial Gas</option>
                                        <option value="Medical" <?= $client['segment'] === 'Medical' ? 'selected' : '' ?>>Medical</option>
                                        <option value="Food & Beverage" <?= $client['segment'] === 'Food & Beverage' ? 'selected' : '' ?>>Food & Beverage</option>
                                        <option value="Manufacturing" <?= $client['segment'] === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Primary Contact Details</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($client['contact_name']) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Job Role / Title</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($client['contact_role']) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-input mono" value="<?= htmlspecialchars($client['email']) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-input mono num" value="<?= htmlspecialchars($client['phone']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Location & Billing Address</h3>
                            
                            <div class="grid grid-cols-1 gap-5">
                                <div>
                                    <label class="form-label">Complete Address</label>
                                    <textarea class="form-input" rows="3"><?= htmlspecialchars($client['address']) ?></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Account Status</h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Operational Status</label>
                                    <select class="form-input mono">
                                        <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="on_hold" <?= $client['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold (Finance)</option>
                                        <option value="suspended" <?= $client['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Client Since</label>
                                    <input type="date" class="form-input mono num" value="<?= htmlspecialchars($client['since']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6 border-l-4" style="border-left-color: #963B33; background: var(--paper-deep);">
                            <h3 class="text-[13.5px] font-semibold tracking-tight mb-3" style="color: #963B33;">Danger Zone</h3>
                            <p class="text-[12px] mb-5" style="color: var(--mute);">Deleting this client will archive all related historical orders, contracts, and financial logs. This action requires Admin privileges.</p>
                            
                            <button type="button" class="w-full btn-danger py-2.5 rounded-sm text-[13.5px] font-medium">
                                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>Archive Client Account
                            </button>
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