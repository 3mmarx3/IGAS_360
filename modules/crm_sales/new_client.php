<?php
session_start();
require_once '../../config/db.php';

$active_page = 'clients';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'New Client'];

$new_client_id = 'ACC-' . rand(1050, 9999);
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $cr_number = trim($_POST['cr_number'] ?? '');
    $entity_type = trim($_POST['entity_type'] ?? '');
    $segment = trim($_POST['segment'] ?? '');
    
    $full_name = trim($_POST['full_name'] ?? '');
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';
    
    $job_title = trim($_POST['job_title'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $credit_limit = floatval($_POST['credit_limit'] ?? 0);
    $payment_terms = trim($_POST['payment_terms'] ?? 'cod');

    $reference_id = $new_client_id;
    $partner_type = 'client';
    $country = 'Saudi Arabia'; 
    $default_password = password_hash('IGAS' . rand(1000,9999), PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO partners (
                    reference_id, partner_type, company_name, cr_number, tax_id, 
                    entity_type, segment, contact_first_name, contact_last_name, 
                    job_title, email, phone, country, city, postal_code, address, 
                    status, credit_limit, payment_terms, password_hash
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $reference_id, $partner_type, $company_name, $cr_number, $tax_id,
            $entity_type, $segment, $first_name, $last_name,
            $job_title, $email, $phone, $country, $city, $postal_code, $address,
            $status, $credit_limit, $payment_terms, $default_password
        ]);

        header("Location: clients_directory.php");
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error_message = "Registration Failed: CR Number or Email is already registered in the system.";
        } else {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Client | I-GAS Enterprise</title>
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

        .card { background: var(--paper); border: 1px solid var(--line-soft); }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: none; cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 6px; }
        .form-input { width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); transition: border-color 0.15s ease; }
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
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="clients_directory.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Clients Directory</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Registration</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create New Client</h2>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 border rounded-sm flex items-start gap-3" style="background: #F8E9E7; border-color: #E2BDBA; color: #963B33;">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <span class="text-[13px] font-medium"><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <form action="" method="POST" id="newClientForm">
                <div class="flex justify-end gap-3 mb-6">
                    <a href="clients_directory.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>Register Account
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Company Information</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Company / Client Name</label>
                                    <input type="text" name="company_name" class="form-input" placeholder="Enter full legal name" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Tax / VAT ID</label>
                                    <input type="text" name="tax_id" class="form-input mono num" placeholder="e.g. 300000000000003">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Commercial Registration (CR)</label>
                                    <input type="text" name="cr_number" class="form-input mono num" placeholder="e.g. 1010123456" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client Type</label>
                                    <select name="entity_type" class="form-input mono" required>
                                        <option value="" selected disabled>Select type...</option>
                                        <option value="Corporate">Corporate</option>
                                        <option value="SME">SME</option>
                                        <option value="Government">Government</option>
                                        <option value="Individual">Individual</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Industry Segment</label>
                                    <select name="segment" class="form-input mono" required>
                                        <option value="" selected disabled>Select segment...</option>
                                        <option value="Industrial Gas">Industrial Gas</option>
                                        <option value="Medical">Medical</option>
                                        <option value="Food & Beverage">Food & Beverage</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Construction">Construction</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Primary Contact Details</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-input" placeholder="Contact person name" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Job Role / Title</label>
                                    <input type="text" name="job_title" class="form-input" placeholder="e.g. Procurement Manager">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-input mono" placeholder="contact@company.com" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-input mono num" placeholder="+966 5X XXX XXXX" required>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Location & Billing Address</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-input" placeholder="e.g. Jeddah" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="postal_code" class="form-input mono num" placeholder="e.g. 21411">
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Complete Address</label>
                                    <textarea name="address" class="form-input" rows="3" placeholder="Building, Street, District..." required></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Account Setup</h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Client ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $new_client_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Initial Status</label>
                                    <select name="status" class="form-input mono">
                                        <option value="approved">Active</option>
                                        <option value="pending" selected>Pending (Requires Approval)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Credit Limit (SAR)</label>
                                    <input type="number" name="credit_limit" step="0.01" class="form-input mono num" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="form-label">Default Payment Terms</label>
                                    <select name="payment_terms" class="form-input mono">
                                        <option value="net30">Net 30 Days</option>
                                        <option value="net60">Net 60 Days</option>
                                        <option value="cod" selected>Cash on Delivery (COD)</option>
                                        <option value="prepaid">Prepaid</option>
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