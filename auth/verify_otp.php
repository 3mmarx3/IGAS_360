<?php
$system_version = 'v2.4.1';
$masked_contact = 'i.al•••••@i-gas.com.sa'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification | I-GAS Enterprise</title>
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
        .btn-secondary { background: transparent; color: var(--ink); border: 1px solid var(--line); transition: all 0.15s ease; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-deep); border-color: var(--mute-soft); }
        
        .otp-input { width: 48px; height: 56px; border: 1px solid var(--line); border-radius: 4px; font-size: 24px; font-weight: 600; text-align: center; color: var(--ink); background: var(--paper); transition: all 0.15s ease; font-family: 'IBM Plex Mono', monospace; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .otp-input:focus { border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); transform: translateY(-1px); }
        .otp-input::placeholder { color: var(--line); }

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
                <div class="w-12 h-12 rounded-full bg-[var(--paper-deep)] flex items-center justify-center mb-5 border" style="border-color: var(--line-soft);">
                    <i data-lucide="fingerprint" class="w-6 h-6" style="color: var(--ink);"></i>
                </div>
                <h1 class="text-[24px] font-semibold tracking-tight" style="color: var(--ink);">Identity Verification</h1>
                <p class="text-[13.5px] mt-2.5 leading-relaxed" style="color: var(--mute);">
                    To secure your session, please enter the 6-digit authorization code sent to your registered device.
                    <br><span class="font-medium mt-1 inline-block mono text-[12.5px]" style="color: var(--ink);"><?= $masked_contact ?></span>
                </p>
            </div>

            <form action="../main/command_center.php" method="POST" class="flex flex-col gap-6">
                
                <div class="flex justify-between items-center gap-2" id="otp-container">
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off" autofocus>
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off">
                    <span class="w-3 h-[2px] rounded-full mx-1" style="background: var(--line);"></span>
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input num" placeholder="•" autocomplete="off">
                </div>

                <div class="flex flex-col gap-3 mt-4">
                    <button type="submit" class="btn-primary w-full py-3 rounded-sm text-[14px] font-medium flex items-center justify-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>Verify &amp; Authenticate
                    </button>
                    
                    <button type="button" class="btn-secondary w-full py-3 rounded-sm text-[13px] font-medium flex items-center justify-center gap-2">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5" style="color: var(--mute);"></i>Resend Code <span class="mono text-[11px] ml-1" style="color: var(--mute-soft);">(00:59)</span>
                    </button>
                </div>
            </form>
            
            <p class="text-center text-[12.5px] mt-8" style="color: var(--mute);">
                Having trouble? <a href="#" class="font-medium underline transition-colors" style="color: var(--ink);">Contact IT Support</a>
            </p>
        </div>

        <div class="flex justify-between items-center text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">
            <span>Secure Protocol (2FA)</span>
            <span><?= $system_version ?></span>
        </div>

    </section>

    <section class="hidden md:flex flex-1 relative h-full industrial-grid items-center justify-center overflow-hidden">
        <div class="glow-effect"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#0A0A0A] via-transparent to-transparent opacity-80"></div>

        <div class="relative z-10 text-center max-w-lg px-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full border mb-6 bg-[#1A1A1A]/50 backdrop-blur-md" style="border-color: rgba(255,255,255,0.1);">
                <i data-lucide="shield" class="w-7 h-7 text-white"></i>
            </div>
            <h2 class="text-white text-[28px] font-semibold tracking-tight leading-tight">Zero-Trust Architecture</h2>
            <p class="text-[14px] mt-3 mx-auto max-w-sm" style="color: var(--mute-soft);">I-GAS Enterprise Systems employ military-grade encryption and multi-factor authentication to ensure absolute data integrity.</p>
        </div>

        <div class="absolute bottom-6 right-8 flex items-center gap-4 text-[11px] mono uppercase tracking-wider" style="color: rgba(255,255,255,0.2);">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Session Locked</span>
            </div>
            <span>·</span>
            <span>AES-256</span>
        </div>
    </section>

    <script>
        lucide.createIcons();
        
        const inputs = document.querySelectorAll('.otp-input');
        inputs.forEach((input, index) => {
            input.addEventListener('keyup', (e) => {
                if (e.key >= 0 && e.key <= 9) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                } else if (e.key === 'Backspace') {
                    if (index > 0) {
                        inputs[index - 1].focus();
                    }
                }
            });
        });
    </script>
</body>
</html>