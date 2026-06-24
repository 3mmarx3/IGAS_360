<?php
$system_version = 'v2.4.1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Account Type | I-GAS Enterprise</title>
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
        
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-primary:disabled { background: var(--mute-soft); border-color: var(--mute-soft); cursor: not-allowed; opacity: 0.7; }
        
        .role-card { position: relative; display: flex; flex-direction: column; padding: 24px; border: 1px solid var(--line); border-radius: 4px; cursor: pointer; transition: all 0.2s ease; background: var(--paper); overflow: hidden; }
        .role-card:hover { border-color: var(--mute-soft); background: var(--paper-dim); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        
        input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        
        .role-card::after { content: ''; position: absolute; inset: 0; border: 2px solid var(--ink); border-radius: 4px; opacity: 0; transition: opacity 0.2s ease; pointer-events: none; }
        input[type="radio"]:checked + .role-card { background: var(--paper); }
        input[type="radio"]:checked + .role-card::after { opacity: 1; }
        
        .radio-circle { position: absolute; top: 24px; right: 24px; width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid var(--mute-soft); display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .radio-dot { width: 8px; height: 8px; border-radius: 50%; background: white; opacity: 0; transition: opacity 0.2s ease; }
        
        input[type="radio"]:checked + .role-card .radio-circle { border-color: var(--ink); background: var(--ink); }
        input[type="radio"]:checked + .role-card .radio-dot { opacity: 1; }

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
            background: radial-gradient(circle, rgba(154, 123, 46, 0.07) 0%, transparent 70%);
            pointer-events: none;
        }
    </style>
</head>
<body class="w-full h-screen flex overflow-hidden">

    <section class="w-full lg:w-[50%] xl:w-[45%] bg-[var(--paper)] border-r border-[var(--line-soft)] flex flex-col justify-between p-8 sm:p-12 lg:px-16 flex-shrink-0 z-10 overflow-y-auto">
        
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="w-8 h-8 flex items-center justify-center text-white font-semibold text-[13px] mono border" style="border-color: #3A3A3A; background: #1A1A1A;">IG</div>
            <div class="leading-none">
                <span class="text-[16px] font-bold tracking-tight block">I-GAS</span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] block mt-0.5" style="color: var(--mute);">Enterprise Systems</span>
            </div>
        </div>

        <div class="w-full my-auto py-10">
            <div class="mb-10">
                <h1 class="text-[26px] font-semibold tracking-tight" style="color: var(--ink);">Join the Network</h1>
                <p class="text-[14px] mt-2.5 max-w-md" style="color: var(--mute);">Select your enterprise profile to initialize the appropriate onboarding sequence and access privileges.</p>
            </div>

            <form action="register.php" method="GET" class="flex flex-col gap-5">
                
                <label class="relative block w-full">
                    <input type="radio" name="type" value="client" required onchange="document.getElementById('continue-btn').disabled = false;">
                    <div class="role-card">
                        <div class="radio-circle"><div class="radio-dot"></div></div>
                        <div class="w-12 h-12 rounded-sm border flex items-center justify-center mb-4 bg-white" style="border-color: var(--line-soft);">
                            <i data-lucide="building-2" class="w-6 h-6" style="color: var(--ink);"></i>
                        </div>
                        <h3 class="text-[16px] font-semibold tracking-tight" style="color: var(--ink);">Corporate Client</h3>
                        <p class="text-[13px] mt-1.5 pr-8" style="color: var(--mute);">I want to procure bulk industrial gases, request quotations, and manage ongoing supply contracts.</p>
                    </div>
                </label>

                <label class="relative block w-full">
                    <input type="radio" name="type" value="supplier" required onchange="document.getElementById('continue-btn').disabled = false;">
                    <div class="role-card">
                        <div class="radio-circle"><div class="radio-dot"></div></div>
                        <div class="w-12 h-12 rounded-sm border flex items-center justify-center mb-4 bg-white" style="border-color: var(--line-soft);">
                            <i data-lucide="container" class="w-6 h-6" style="color: var(--ink);"></i>
                        </div>
                        <h3 class="text-[16px] font-semibold tracking-tight" style="color: var(--ink);">Industrial Supplier</h3>
                        <p class="text-[13px] mt-1.5 pr-8" style="color: var(--mute);">I want to supply raw materials, chemical components, or logistics services to the I-GAS network.</p>
                    </div>
                </label>

                <button type="submit" id="continue-btn" disabled class="btn-primary w-full py-3.5 rounded-sm text-[14px] font-medium flex items-center justify-center gap-2 mt-4">
                    Continue to Registration <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                </button>
            </form>

            <p class="text-center text-[13px] mt-8" style="color: var(--mute);">
                Already registered in the system? 
                <a href="login.php" class="font-semibold transition-colors hover:underline" style="color: var(--ink);">Sign In</a>
            </p>
        </div>

        <div class="flex justify-between items-center text-[11px] mono uppercase tracking-wide flex-shrink-0" style="color: var(--mute-soft);">
            <span>Gateway Portal</span>
            <span><?= $system_version ?></span>
        </div>

    </section>

    <section class="hidden lg:flex flex-1 relative h-full industrial-grid items-center justify-center overflow-hidden">
        <div class="glow-effect"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0A] via-transparent to-transparent opacity-80"></div>

        <div class="relative z-10 text-center max-w-lg px-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-sm border mb-6 bg-[#1A1A1A]/50 backdrop-blur-md" style="border-color: rgba(255,255,255,0.1);">
                <i data-lucide="globe-2" class="w-5 h-5 text-white"></i>
            </div>
            <h2 class="text-white text-[28px] font-semibold tracking-tight leading-tight">Unified Digital Ecosystem</h2>
            <p class="text-[14px] mt-3 mx-auto max-w-sm" style="color: var(--mute-soft);">Connecting leading industrial suppliers with top-tier corporate clients through a centralized, secure, and automated supply chain matrix.</p>
        </div>

        <div class="absolute bottom-6 right-8 flex items-center gap-4 text-[11px] mono uppercase tracking-wider" style="color: rgba(255,255,255,0.2);">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Systems Online</span>
            </div>
            <span>·</span>
            <span>Enterprise B2B</span>
        </div>
    </section>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>