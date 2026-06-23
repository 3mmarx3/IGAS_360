<?php
$system_version = 'v2.4.1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Registration | I-GAS Enterprise</title>
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
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 10px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }

        .input-group { position: relative; display: flex; align-items: center; width: 100%; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 38px; }

        .check-item { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; user-select: none; }
        .check-box { width: 15px; height: 15px; border: 1.5px solid var(--line); border-radius: 2px; display: flex; align-items: center; justify-content: center; transition: all 0.15s ease; background: var(--paper); flex-shrink: 0; margin-top: 2px; }
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
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(154, 123, 46, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }
    </style>
</head>
<body class="w-full h-screen flex overflow-hidden">

    <section class="w-full lg:w-[50%] xl:w-[45%] bg-[var(--paper)] border-r border-[var(--line-soft)] flex flex-col justify-between p-8 sm:p-12 lg:px-16 overflow-y-auto z-10">
        
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="w-8 h-8 flex items-center justify-center text-white font-semibold text-[13px] mono border" style="border-color: #3A3A3A; background: #1A1A1A;">IG</div>
            <div class="leading-none">
                <span class="text-[16px] font-bold tracking-tight block">I-GAS</span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] block mt-0.5" style="color: var(--mute);">Enterprise Systems</span>
            </div>
        </div>

        <div class="w-full my-10">
            <div class="mb-8">
                <h1 class="text-[24px] font-semibold tracking-tight" style="color: var(--ink);">Enterprise Registration</h1>
                <p class="text-[13.5px] mt-2" style="color: var(--mute);">Apply for a corporate account to integrate with the I-GAS supply chain network as a verified client or supplier.</p>
            </div>

            <form action="onboarding_success.php" method="POST" class="flex flex-col gap-6">
                
                <div>
                    <h3 class="text-[12px] font-bold uppercase tracking-[0.08em] pb-2 border-b mb-4" style="color: var(--ink); border-color: var(--line-soft);">Entity Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Account Classification</label>
                            <select class="form-select" required>
                                <option value="" selected disabled>Select entity type...</option>
                                <option value="client">Corporate Client (Buyer)</option>
                                <option value="supplier">Industrial Supplier (Vendor)</option>
                                <option value="logistics">Logistics Partner</option>
                            </select>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">CR / Tax ID Number</label>
                            <input type="text" class="form-input mono" placeholder="e.g. 1010XXXXXX" required>
                        </div>
                        <div class="col-span-2">
                            <label class="form-label">Legal Company Name</label>
                            <div class="input-group">
                                <i data-lucide="building-2" class="w-4 h-4 input-icon"></i>
                                <input type="text" class="form-input has-icon" placeholder="Registered Enterprise Name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-[12px] font-bold uppercase tracking-[0.08em] pb-2 border-b mb-4" style="color: var(--ink); border-color: var(--line-soft);">Primary Contact</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input" placeholder="Authorized Person" required>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input" placeholder="Family Name" required>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Corporate Email</label>
                            <div class="input-group">
                                <i data-lucide="mail" class="w-4 h-4 input-icon"></i>
                                <input type="email" class="form-input has-icon mono" placeholder="work@company.com" required>
                            </div>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Direct Phone</label>
                            <div class="input-group">
                                <i data-lucide="phone" class="w-4 h-4 input-icon"></i>
                                <input type="tel" class="form-input has-icon mono" placeholder="+966 5X XXX XXXX" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-[12px] font-bold uppercase tracking-[0.08em] pb-2 border-b mb-4" style="color: var(--ink); border-color: var(--line-soft);">Security Protocols</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Secure Password</label>
                            <div class="input-group">
                                <i data-lucide="lock" class="w-4 h-4 input-icon"></i>
                                <input type="password" class="form-input has-icon mono" placeholder="••••••••••••" required>
                            </div>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <i data-lucide="lock-keyhole" class="w-4 h-4 input-icon"></i>
                                <input type="password" class="form-input has-icon mono" placeholder="••••••••••••" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="check-item">
                        <input type="checkbox" required>
                        <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                        <span class="text-[12.5px] leading-tight" style="color: var(--mute);">I acknowledge and agree to the I-GAS <a href="#" class="font-medium underline" style="color: var(--ink);">B2B Terms of Service</a> and <a href="#" class="font-medium underline" style="color: var(--ink);">Data Compliance Policy</a>.</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-3 rounded-sm text-[14px] font-medium flex items-center justify-center gap-2 mt-2">
                    <i data-lucide="send" class="w-4 h-4"></i>Submit Registration Request
                </button>
            </form>
            
            <p class="text-center text-[13px] mt-8" style="color: var(--mute);">
                Already have a verified account? 
                <a href="login.php" class="font-semibold transition-colors hover:underline" style="color: var(--ink);">Initialize Session</a>
            </p>
        </div>

        <div class="flex justify-between items-center text-[11px] mono uppercase tracking-wide flex-shrink-0" style="color: var(--mute-soft);">
            <span>Partner Gateway</span>
            <span><?= $system_version ?></span>
        </div>

    </section>

    <section class="hidden lg:flex flex-1 relative h-full industrial-grid items-center justify-center overflow-hidden">
        <div class="glow-effect"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0A] via-transparent to-transparent opacity-80"></div>

        <div class="relative z-10 text-center max-w-lg px-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-sm border mb-6 bg-[#1A1A1A]/50 backdrop-blur-md" style="border-color: rgba(255,255,255,0.1);">
                <i data-lucide="network" class="w-5 h-5 text-white"></i>
            </div>
            <h2 class="text-white text-[28px] font-semibold tracking-tight leading-tight">Supply Chain Integration</h2>
            <p class="text-[14px] mt-3 mx-auto max-w-sm" style="color: var(--mute-soft);">Join the I-GAS verified ecosystem. Streamline your procurement, manage active contracts, and monitor bulk gas logistics in real-time.</p>
        </div>

        <div class="absolute bottom-6 right-8 flex items-center gap-4 text-[11px] mono uppercase tracking-wider" style="color: rgba(255,255,255,0.2);">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Portal Active</span>
            </div>
            <span>·</span>
            <span>B2B Network</span>
        </div>
    </section>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>