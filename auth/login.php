<?php
session_start();
require_once '../config/db.php';

$system_version = 'v2.4.1';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT id, reference_id, partner_type, company_name, contact_first_name, contact_last_name, password_hash, status FROM partners WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'pending') {
                    $error_message = "Account Pending: Your registration is still under review by administration.";
                } elseif ($user['status'] === 'suspended' || $user['status'] === 'rejected') {
                    $error_message = "Access Denied: Your account has been suspended or rejected.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['reference_id'] = $user['reference_id'];
                    $_SESSION['partner_type'] = $user['partner_type'];
                    $_SESSION['user_name'] = $user['contact_first_name'] . ' ' . $user['contact_last_name'];
                    $_SESSION['company_name'] = $user['company_name'];
                    
                    header("Location: ../main/command_center.php");
                    exit;
                }
            } else {
                $error_message = "Authentication Failed: Invalid corporate email or password.";
            }
        } catch (PDOException $e) {
            $error_message = "System Error: Unable to authenticate at this moment.";
        }
    } else {
        $error_message = "Validation Error: Please provide both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | I-GAS Enterprise</title>
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
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: var(--paper-dim);
            color: var(--ink);
            font-feature-settings: "tnum" 1;
        }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        
        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 10px 12px; font-size: 14px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }

        .input-group { position: relative; display: flex; align-items: center; width: 100%; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 38px; }

        .check-item { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
        .check-box { width: 15px; height: 15px; border: 1.5px solid var(--line); border-radius: 2px; display: flex; align-items: center; justify-content: center; transition: all 0.15s ease; background: var(--paper); }
        input[type="checkbox"] { display: none; }
        input[type="checkbox"]:checked + .check-box { background: var(--ink); border-color: var(--ink); color: white; }

        .industrial-grid {
            background-color: #111111;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            background-position: center;
        }
        .glow-effect {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(154, 123, 46, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }
    </style>
</head>
<body class="w-full h-screen flex overflow-hidden">

    <section class="w-full md:w-[45%] lg:w-[40%] xl:w-[35%] bg-[var(--paper)] border-r border-[var(--line-soft)] flex flex-col justify-between p-8 sm:p-12 lg:p-16 flex-shrink-0 z-10">
        
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 flex items-center justify-center text-white font-semibold text-[13px] mono border" style="border-color: #3A3A3A; background: #1A1A1A;">IG</div>
            <div class="leading-none">
                <span class="text-[16px] font-bold tracking-tight block">I-GAS</span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] block mt-0.5" style="color: var(--mute);">Enterprise Systems</span>
            </div>
        </div>

        <div class="w-full my-auto py-12">
            <div class="mb-8">
                <h1 class="text-[24px] font-semibold tracking-tight" style="color: var(--ink);">Command Center Access</h1>
                <p class="text-[13.5px] mt-2" style="color: var(--mute);">Provide your authorized enterprise credentials to initialize session terminal.</p>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 border rounded-sm flex items-start gap-3" style="background: #F8E9E7; border-color: #E2BDBA; color: #963B33;">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <span class="text-[13px] font-medium"><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <form action="" method="POST" class="flex flex-col gap-5">
                <div>
                    <label class="form-label">Corporate Email</label>
                    <div class="input-group">
                        <i data-lucide="mail" class="w-4 h-4 input-icon"></i>
                        <input type="email" name="email" class="form-input has-icon mono" placeholder="username@i-gas.com.sa" required autocomplete="username">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="form-label m-0">Security Password</label>
                        <a href="#" class="text-[12px] font-medium transition-colors hover:underline" style="color: var(--accent);">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <i data-lucide="lock" class="w-4 h-4 input-icon"></i>
                        <input type="password" name="password" class="form-input has-icon mono" placeholder="••••••••••••" required autocomplete="current-password">
                    </div>
                </div>

                <div class="flex items-center justify-between mt-1">
                    <label class="check-item">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                        <span class="text-[13px]" style="color: var(--ink-soft);">Trust this workstation</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-2.5 rounded-sm text-[14px] font-medium flex items-center justify-center gap-2 mt-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>Authenticate Access
                </button>
            </form>
        </div>

        <div class="flex justify-between items-center text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">
            <span>Secure Terminal</span>
            <span><?= $system_version ?></span>
        </div>

    </section>

    <section class="hidden md:flex flex-1 relative h-full industrial-grid items-center justify-center overflow-hidden">
        <div class="glow-effect"></div>
        
        <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0A] via-transparent to-transparent opacity-80"></div>

        <div class="relative z-10 text-center max-w-lg px-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-sm border mb-6 bg-[#1A1A1A]/50 backdrop-blur-md" style="border-color: rgba(255,255,255,0.1);">
                <i data-lucide="factory" class="w-5 h-5 text-white"></i>
            </div>
            <h2 class="text-white text-[28px] font-semibold tracking-tight leading-tight">Jeddah Industrial Node</h2>
            <p class="text-[14px] mt-3 mx-auto max-w-sm" style="color: var(--mute-soft);">Real-time control matrix for bulk gas processing, automated cylinder tracking, and fleet distribution networks.</p>
        </div>

        <div class="absolute bottom-6 right-8 flex items-center gap-4 text-[11px] mono uppercase tracking-wider" style="color: rgba(255,255,255,0.2);">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Node Active</span>
            </div>
            <span>·</span>
            <span>SSL Encrypted</span>
        </div>
    </section>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>