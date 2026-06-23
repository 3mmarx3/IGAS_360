<?php
$active_page = 'system_settings';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Insights', 'System Settings'];

$system_version = 'v2.4.1-Build880';
$last_backup    = '2026-06-23 03:00 AM';

$settings_sections = [
    'general'    => 'Enterprise & Localization',
    'production' => 'Production & Logistics Automation',
    'security'   => 'Security & Access Protocols',
    'maintenance'=> 'System Integrity & Backups'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | I-GAS Enterprise</title>
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

        .input-group { position: relative; display: flex; align-items: center; }
        .input-suffix { position: absolute; right: 12px; font-size: 11px; color: var(--mute); pointer-events: none; font-family: 'IBM Plex Mono', monospace; font-weight: 500; }
        .has-suffix { padding-right: 48px; }

        .toggle-container { display: inline-flex; align-items: center; cursor: pointer; -webkit-user-select: none; -moz-user-select: none; user-select: none; }
        .toggle-switch { position: relative; width: 36px; height: 20px; background-color: var(--line); border-radius: 10px; transition: background-color 0.15s ease; margin-right: 10px; }
        .toggle-switch::after { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; background-color: white; top: 2px; left: 2px; transition: transform 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        input[type="checkbox"]:checked + .toggle-switch { background-color: var(--ink); }
        input[type="checkbox"]:checked + .toggle-switch::after { transform: translateX(16px); }

        .settings-nav { position: sticky; top: 0; }
        .settings-nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; font-size: 13.5px; font-weight: 500; color: var(--mute); border-radius: 3px; transition: all 0.15s ease; cursor: pointer; }
        .settings-nav-item:hover { color: var(--ink); background: rgba(0,0,0,0.02); }
        .settings-nav-item.active { color: var(--ink); background: var(--paper); border: 1px solid var(--line-soft); font-weight: 600; }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">System Version · <?= $system_version ?></span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Global Administration</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">System Settings</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Configure system execution frameworks, deployment variables, security matrices, and scheduled automated pipelines.</p>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium">
                        Discard Changes
                    </button>
                    <button type="submit" form="settings-form" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Settings
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-7 items-start">
                
                <div class="xl:col-span-1 settings-nav flex flex-col gap-1">
                    <div class="settings-nav-item active">
                        <i data-lucide="sliders" class="w-4 h-4"></i>Enterprise Profile
                    </div>
                    <div class="settings-nav-item">
                        <i data-lucide="cpu" class="w-4 h-4"></i>Automation Rules
                    </div>
                    <div class="settings-nav-item">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>Access &amp; Security
                    </div>
                    <div class="settings-nav-item">
                        <i data-lucide="database" class="w-4 h-4"></i>Backups &amp; Logs
                    </div>
                    
                    <div class="card rounded-md p-4 mt-5 bg-[var(--paper-deep)] border-dashed">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em]" style="color: var(--mute);">System Meta</p>
                        <div class="mt-3 flex flex-col gap-2 text-[12.5px]">
                            <div class="flex justify-between"><span style="color: var(--mute);">Engine:</span><span class="mono font-medium">PHP 8.2 / PDO</span></div>
                            <div class="flex justify-between"><span style="color: var(--mute);">Environment:</span><span class="mono font-medium">Production</span></div>
                            <div class="flex justify-between"><span style="color: var(--mute);">Database:</span><span class="mono font-medium">MySQL 8.0</span></div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-3">
                    <form id="settings-form" method="POST" action="system_settings.php" class="flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute);"></i>Enterprise &amp; Localization Configuration
                            </h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">System Platform Banner Title</label>
                                    <input type="text" class="form-input" value="I-GAS Enterprise Systems">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Headquarters Designation</label>
                                    <input type="text" class="form-input" value="Jeddah Industrial — HQ">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Base Reporting Currency</label>
                                    <select class="form-select">
                                        <option value="SAR" selected>Saudi Riyal (SAR)</option>
                                        <option value="USD">US Dollar (USD)</option>
                                        <option value="AED">UAE Dirham (AED)</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">System Timezone Engine</label>
                                    <select class="form-select">
                                        <option value="Asia/Riyadh" selected>Asia/Riyadh (GMT+3)</option>
                                        <option value="UTC">Coordinated Universal Time (UTC)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="git-merge" class="w-4 h-4" style="color: var(--mute);"></i>Production &amp; Logistics Pipelines (Automation)
                            </h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Raw Material Safety Reorder Threshold Trigger</label>
                                    <div class="input-group">
                                        <input type="number" class="form-input has-suffix mono num" value="15" min="1">
                                        <span class="input-suffix">% CAPACITY</span>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Fleet Telematics Sync Window</label>
                                    <div class="input-group">
                                        <input type="number" class="form-input has-suffix mono num" value="5" min="1">
                                        <span class="input-suffix">MINUTES</span>
                                    </div>
                                </div>
                                <div class="col-span-2">
                                    <label class="toggle-container">
                                        <input type="checkbox" checked>
                                        <div class="toggle-switch"></div>
                                        <span class="check-text font-medium text-[13.5px]">Auto-route empty cylinders to hydro-testing queue when circulation limit is exceeded</span>
                                    </label>
                                </div>
                                <div class="col-span-2">
                                    <label class="toggle-container">
                                        <input type="checkbox" checked>
                                        <div class="toggle-switch"></div>
                                        <span class="check-text font-medium text-[13.5px]">Trigger emergency SMS/Email notifications to Procurement when Low Stock states persist beyond 48h</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="lock" class="w-4 h-4" style="color: var(--mute);"></i>Security Matrices &amp; Encryption Parameters
                            </h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Enterprise Session Timeout Limit</label>
                                    <div class="input-group">
                                        <input type="number" class="form-input has-suffix mono num" value="30">
                                        <span class="input-suffix">MINUTES</span>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Minimum Password Complexity Rules</label>
                                    <select class="form-select">
                                        <option value="high" selected>Strict (Alphanumeric + Special Character, Min 10 Chars)</option>
                                        <option value="medium">Standard (Alphanumeric, Min 8 Chars)</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="toggle-container">
                                        <input type="checkbox" checked>
                                        <div class="toggle-switch"></div>
                                        <span class="check-text font-medium text-[13.5px]">Enforce Two-Factor Authentication (2FA) for Administrative &amp; Board level roles</span>
                                    </label>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Static IP Whitelist Matrix (Comma separated endpoints)</label>
                                    <input type="text" class="form-input mono text-[12.5px]" value="192.168.1.1, 212.34.120.45, 10.0.4.11">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="hard-drive" class="w-4 h-4" style="color: var(--mute);"></i>System Backups &amp; Data Archiving
                            </h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Automated Structural Database Backup Frequency</label>
                                    <select class="form-select">
                                        <option value="daily" selected>Every 24 Hours (Daily @ 03:00 AM)</option>
                                        <option value="weekly">Every 7 Days (Weekly)</option>
                                        <option value="realtime">Continuous Transaction Replication</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">System Transaction Logs Retention Period</label>
                                    <select class="form-select">
                                        <option value="1year" selected>Retain for 12 Months (Recommended)</option>
                                        <option value="5years">Retain for 5 Fiscal Years</option>
                                    </select>
                                </div>
                                <div class="col-span-2 flex items-center justify-between p-3 rounded-sm bg-[var(--paper-deep)] mt-2">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="archive" class="w-5 h-5" style="color: var(--mute);"></i>
                                        <div>
                                            <p class="text-[13px] font-semibold">Last Backup Matrix Check Nominal</p>
                                            <p class="text-[11px] mono" style="color: var(--mute);"><?= $last_backup ?></p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-secondary px-3 py-1.5 text-[12.5px] bg-white">Force Backup Now</button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>