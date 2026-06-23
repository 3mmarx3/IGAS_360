<?php
$active_page = 'suppliers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Suppliers Directory', 'New Supplier'];

$new_supplier_id = 'SUP-' . rand(5010, 5999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Supplier | I-GAS Enterprise</title>
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

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }
        .form-input:disabled { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }
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
                    <div class="flex items-center gap-2 mb-2">
                        <a href="suppliers_directory.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Suppliers Directory</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Registration</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register New Supplier</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Add a new vendor to the procurement database and configure compliance details.</p>
                </div>
                <div class="flex gap-3">
                    <a href="suppliers_directory.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Supplier Profile
                    </button>
                </div>
            </div>

            <form action="suppliers_directory.php" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute);"></i>Company Information
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Full Company Name / Legal Entity</label>
                                    <input type="text" class="form-input" placeholder="Enter official company name" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">General Email Address</label>
                                    <input type="email" class="form-input mono" placeholder="info@company.com">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Main Office Phone</label>
                                    <input type="tel" class="form-input mono num" placeholder="+966 1X XXX XXXX">
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Registered Office Address</label>
                                    <textarea class="form-input" rows="3" placeholder="Building, Street, District, City, Country..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="users" class="w-4 h-4" style="color: var(--mute);"></i>Primary Contact Details
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Contact Person Name</label>
                                    <input type="text" class="form-input" placeholder="Full name">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Job Title / Position</label>
                                    <input type="text" class="form-input" placeholder="e.g. Sales Manager">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Direct Email Address</label>
                                    <input type="email" class="form-input mono" placeholder="contact@company.com">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-input mono num" placeholder="+966 5X XXX XXXX">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="tags" class="w-4 h-4" style="color: var(--mute);"></i>System Categorization
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Supplier ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $new_supplier_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Supply Category</label>
                                    <select class="form-select">
                                        <option value="" selected disabled>Select primary category...</option>
                                        <option value="Raw Materials">Raw Materials</option>
                                        <option value="Cylinders">Cylinders & Containment</option>
                                        <option value="Chemicals">Chemicals & Additives</option>
                                        <option value="Transportation">Transportation & Logistics</option>
                                        <option value="Spare Parts">Fleet Spare Parts</option>
                                        <option value="Office">Office Supplies & Services</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Initial Status</label>
                                    <select class="form-select">
                                        <option value="active">Active (Approved)</option>
                                        <option value="pending" selected>Pending Verification</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="file-text" class="w-4 h-4" style="color: var(--mute);"></i>Financial & Legal
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Tax ID (VAT Number)</label>
                                    <input type="text" class="form-input mono num" placeholder="15-digit Tax Identification">
                                </div>
                                <div>
                                    <label class="form-label">Commercial Registry (CR)</label>
                                    <input type="text" class="form-input mono num" placeholder="CR Document Number">
                                </div>
                                <div>
                                    <label class="form-label">Agreed Payment Terms</label>
                                    <select class="form-select">
                                        <option value="Net 15 Days">Net 15 Days</option>
                                        <option value="Net 30 Days" selected>Net 30 Days</option>
                                        <option value="Net 60 Days">Net 60 Days</option>
                                        <option value="Cash in Advance">Cash in Advance (CIA)</option>
                                        <option value="Cash on Delivery">Cash on Delivery (COD)</option>
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