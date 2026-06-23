<?php
$active_page = 'profile';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'User Management', 'My Profile'];

$user = [
    'first_name' => 'Ibrahim',
    'last_name'  => 'Al Manea',
    'email'      => 'i.almanea@i-gas.com.sa',
    'phone'      => '+966 50 000 0000',
    'role'       => 'Chairman & CEO',
    'department' => 'Executive Board',
    'location'   => 'Jeddah Industrial — HQ',
    'timezone'   => 'Asia/Riyadh (GMT+3)',
    'language'   => 'English (US)',
    '2fa_active' => true,
    'last_login' => '2026-06-24 08:15 AM'
];

$sessions = [
    ['device' => 'MacBook Air M2', 'ip' => '192.168.1.45', 'location' => 'Jeddah, SA', 'time' => 'Current Session', 'status' => 'active'],
    ['device' => 'iPhone 14 Pro', 'ip' => '212.34.120.88', 'location' => 'Riyadh, SA', 'time' => 'Yesterday, 14:30', 'status' => 'logged_out'],
    ['device' => 'Windows PC (Chrome)', 'ip' => '10.0.4.11', 'location' => 'Jeddah, SA', 'time' => '2026-06-21 09:00', 'status' => 'logged_out'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | I-GAS Enterprise</title>
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
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }

        .toggle-container { display: inline-flex; align-items: center; cursor: pointer; -webkit-user-select: none; -moz-user-select: none; user-select: none; }
        .toggle-switch { position: relative; width: 36px; height: 20px; background-color: var(--line); border-radius: 10px; transition: background-color 0.15s ease; margin-right: 10px; }
        .toggle-switch::after { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; background-color: white; top: 2px; left: 2px; transition: transform 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        input[type="checkbox"]:checked + .toggle-switch { background-color: var(--ink); }
        input[type="checkbox"]:checked + .toggle-switch::after { transform: translateX(16px); }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Access Level · Administrator</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">My Profile</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage your personal information, security preferences, and system access logs.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                
                <div class="xl:col-span-1 flex flex-col gap-6">
                    <div class="card rounded-md p-6 text-center flex flex-col items-center">
                        <div class="relative mb-4">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] . ' ' . $user['last_name']) ?>&background=1A1A1A&color=fff&rounded=true&size=128&bold=true" alt="Profile" class="w-24 h-24 rounded-full border-4 border-white shadow-sm">
                            <button class="absolute bottom-0 right-0 w-8 h-8 bg-white border rounded-full flex items-center justify-center transition-colors hover:bg-gray-50" style="border-color: var(--line); color: var(--ink);">
                                <i data-lucide="camera" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <h3 class="text-[18px] font-semibold tracking-tight" style="color: var(--ink);"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p class="text-[13px] font-medium mt-1" style="color: var(--mute);"><?= htmlspecialchars($user['role']) ?></p>
                        
                        <div class="w-full mt-6 pt-5 border-t text-left flex flex-col gap-3" style="border-color: var(--line-soft);">
                            <div class="flex items-center gap-3 text-[13px]">
                                <i data-lucide="mail" class="w-4 h-4" style="color: var(--mute-soft);"></i>
                                <span style="color: var(--ink);"><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-[13px]">
                                <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute-soft);"></i>
                                <span style="color: var(--ink);"><?= htmlspecialchars($user['department']) ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-[13px]">
                                <i data-lucide="map-pin" class="w-4 h-4" style="color: var(--mute-soft);"></i>
                                <span style="color: var(--ink);"><?= htmlspecialchars($user['location']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Quick Actions</h3>
                        <div class="flex flex-col gap-2">
                            <button class="btn-secondary w-full py-2 rounded-sm text-[13px] font-medium justify-start px-4 bg-white">
                                <i data-lucide="key-round" class="w-4 h-4 mr-2" style="color: var(--mute);"></i>Change Password
                            </button>
                            <button class="btn-secondary w-full py-2 rounded-sm text-[13px] font-medium justify-start px-4 bg-white">
                                <i data-lucide="bell" class="w-4 h-4 mr-2" style="color: var(--mute);"></i>Notification Preferences
                            </button>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-2 flex flex-col gap-6">
                    <form action="profile.php" method="POST" class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Personal Details</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <i data-lucide="mail" class="w-4 h-4 input-icon"></i>
                                        <input type="email" class="form-input has-icon mono" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <i data-lucide="phone" class="w-4 h-4 input-icon"></i>
                                        <input type="tel" class="form-input has-icon mono" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">System Language</label>
                                    <select class="form-select">
                                        <option value="en" <?= $user['language'] === 'English (US)' ? 'selected' : '' ?>>English (US)</option>
                                        <option value="ar">Arabic (Saudi Arabia)</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select">
                                        <option value="Asia/Riyadh" selected><?= htmlspecialchars($user['timezone']) ?></option>
                                        <option value="UTC">UTC</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t flex justify-end" style="border-color: var(--line-soft); background: var(--paper-dim);">
                            <button type="submit" class="btn-primary px-5 py-2 rounded-sm text-[13px] font-medium">
                                Save Changes
                            </button>
                        </div>
                    </form>

                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Security & Authentication</h3>
                        </div>
                        <div class="p-6 flex flex-col gap-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[13.5px] font-semibold" style="color: var(--ink);">Two-Factor Authentication (2FA)</p>
                                    <p class="text-[12.5px] mt-1" style="color: var(--mute);">Add an extra layer of security to your account using an authenticator app.</p>
                                </div>
                                <label class="toggle-container m-0">
                                    <input type="checkbox" <?= $user['2fa_active'] ? 'checked' : '' ?>>
                                    <div class="toggle-switch m-0"></div>
                                </label>
                            </div>
                            
                            <div class="border-t pt-6" style="border-color: var(--line-soft);">
                                <h4 class="text-[13px] font-semibold uppercase tracking-[0.08em] mb-4" style="color: var(--mute);">Recent Access Log</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                                <th class="pb-3 pr-3 font-medium">Device / Browser</th>
                                                <th class="px-3 pb-3 font-medium">IP Address</th>
                                                <th class="px-3 pb-3 font-medium">Time</th>
                                                <th class="pl-3 pb-3 font-medium text-right">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-[13px] divide-y" style="border-color: var(--line-soft);">
                                            <?php foreach ($sessions as $session): ?>
                                            <tr class="transition-colors" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                                <td class="py-3.5 pr-3">
                                                    <p class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($session['device']) ?></p>
                                                    <p class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($session['location']) ?></p>
                                                </td>
                                                <td class="px-3 py-3.5 mono" style="color: var(--mute);"><?= htmlspecialchars($session['ip']) ?></td>
                                                <td class="px-3 py-3.5 mono" style="color: var(--mute);"><?= htmlspecialchars($session['time']) ?></td>
                                                <td class="pl-3 py-3.5 text-right">
                                                    <?php if($session['status'] === 'active'): ?>
                                                        <span class="pill" style="background: #EAF1E7; color: #45663F;">Active Now</span>
                                                    <?php else: ?>
                                                        <span class="text-[12px]" style="color: var(--mute-soft);">Logged Out</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>