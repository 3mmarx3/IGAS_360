<?php
require_once '../../config/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_id = $_POST['reference_id'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $contact_name = $_POST['contact_name'] ?? '';
    $parts = explode(' ', trim($contact_name), 2);
    $contact_first_name = $parts[0] ?: 'Unknown';
    $contact_last_name = $parts[1] ?? '';
    
    $job_title = $_POST['job_title'] ?? '';
    $segment = $_POST['segment'] ?? 'Raw Materials';
    $status = $_POST['status'] ?? 'pending';
    $tax_id = $_POST['tax_id'] ?? '';
    $cr_number = $_POST['cr_number'] ?? '';
    $payment_terms = $_POST['payment_terms'] ?? 'Net 30 Days';

    $country = 'Saudi Arabia';
    $city = 'Default City';
    $partner_type = 'supplier';
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO partners (
            reference_id, partner_type, company_name, cr_number, country, city, address, tax_id, 
            contact_first_name, contact_last_name, job_title, email, phone, status, segment, payment_terms, password_hash
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $reference_id, $partner_type, $company_name, $cr_number, $country, $city, $address, $tax_id,
            $contact_first_name, $contact_last_name, $job_title, $email, $phone, $status, $segment, $payment_terms, $password_hash
        ]);

        header("Location: suppliers_directory.php");
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error_message = "This Email or CR Number is already registered in the system.";
        } else {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

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
                    <button type="submit" form="newSupplierForm" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Supplier Profile
                    </button>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 rounded-md" style="background-color: #F8E9E7; border: 1px solid #963B33;">
                <p class="text-[13.5px] font-medium flex items-center gap-2" style="color: #963B33;">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <?= htmlspecialchars($error_message) ?>
                </p>
            </div>
            <?php endif; ?>

            <form id="newSupplierForm" action="" method="POST">
                <input type="hidden" name="reference_id" value="<?= htmlspecialchars($new_supplier_id) ?>">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute);"></i>Company Information
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Full Company Name / Legal Entity</label>
                                    <input type="text" name="company_name" class="form-input" placeholder="Enter official company name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">General Email Address</label>
                                    <input type="email" name="email" class="form-input mono" placeholder="info@company.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Main Office Phone</label>
                                    <input type="tel" name="phone" class="form-input mono num" placeholder="+966 1X XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Registered Office Address</label>
                                    <textarea name="address" class="form-input" rows="3" placeholder="Building, Street, District, City, Country..." required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
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
                                    <input type="text" name="contact_name" class="form-input" placeholder="Full name" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Job Title / Position</label>
                                    <input type="text" name="job_title" class="form-input" placeholder="e.g. Sales Manager" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>" required>
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
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($new_supplier_id) ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Supply Category</label>
                                    <select name="segment" class="form-select" required>
                                        <option value="" <?= empty($_POST['segment']) ? 'selected' : '' ?> disabled>Select primary category...</option>
                                        <option value="Raw Materials" <?= ($_POST['segment'] ?? '') == 'Raw Materials' ? 'selected' : '' ?>>Raw Materials</option>
                                        <option value="Cylinders" <?= ($_POST['segment'] ?? '') == 'Cylinders' ? 'selected' : '' ?>>Cylinders & Containment</option>
                                        <option value="Chemicals" <?= ($_POST['segment'] ?? '') == 'Chemicals' ? 'selected' : '' ?>>Chemicals & Additives</option>
                                        <option value="Transportation" <?= ($_POST['segment'] ?? '') == 'Transportation' ? 'selected' : '' ?>>Transportation & Logistics</option>
                                        <option value="Spare Parts" <?= ($_POST['segment'] ?? '') == 'Spare Parts' ? 'selected' : '' ?>>Fleet Spare Parts</option>
                                        <option value="Office" <?= ($_POST['segment'] ?? '') == 'Office' ? 'selected' : '' ?>>Office Supplies & Services</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Initial Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?= ($_POST['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active (Approved)</option>
                                        <option value="pending" <?= ($_POST['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>Pending Verification</option>
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
                                    <input type="text" name="tax_id" class="form-input mono num" placeholder="15-digit Tax Identification" value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Commercial Registry (CR)</label>
                                    <input type="text" name="cr_number" class="form-input mono num" placeholder="CR Document Number" value="<?= htmlspecialchars($_POST['cr_number'] ?? '') ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Agreed Payment Terms</label>
                                    <select name="payment_terms" class="form-select" required>
                                        <option value="Net 15 Days" <?= ($_POST['payment_terms'] ?? '') == 'Net 15 Days' ? 'selected' : '' ?>>Net 15 Days</option>
                                        <option value="Net 30 Days" <?= ($_POST['payment_terms'] ?? 'Net 30 Days') == 'Net 30 Days' ? 'selected' : '' ?>>Net 30 Days</option>
                                        <option value="Net 60 Days" <?= ($_POST['payment_terms'] ?? '') == 'Net 60 Days' ? 'selected' : '' ?>>Net 60 Days</option>
                                        <option value="Cash in Advance" <?= ($_POST['payment_terms'] ?? '') == 'Cash in Advance' ? 'selected' : '' ?>>Cash in Advance (CIA)</option>
                                        <option value="Cash on Delivery" <?= ($_POST['payment_terms'] ?? '') == 'Cash on Delivery' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
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