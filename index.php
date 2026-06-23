<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-GAS Enterprise | Command Center</title>
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

        /* sidebar nav: quiet left rule on active/hover, no glow */
        .nav-row { position: relative; border-left: 2px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .ticket {
            background: var(--paper); border: 1px solid var(--line-soft);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .ticket:hover { border-color: var(--mute-soft); box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .ticket:focus-visible { outline: 2px solid var(--ink); outline-offset: -2px; }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }

        input:focus { outline: none; border-color: var(--ink) !important; }
        th, td { vertical-align: middle; }

        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px;
            height: 2px; background: transparent; transition: background 0.15s ease;
        }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }

        .avatar-sq {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px;
        }

        .timeline-rail { position: relative; }
        .timeline-rail::before {
            content: ''; position: absolute; left: 19.5px; top: 4px; bottom: 4px;
            width: 1px; background: var(--line-soft);
        }
        .timeline-dot {
            width: 7px; height: 7px; border-radius: 50%; border: 2px solid var(--mute-soft); background: var(--paper);
            flex-shrink: 0; position: relative; z-index: 1;
        }
        .timeline-dot.current { border-color: var(--ink); background: var(--ink); }

        .checkbox-sq {
            width: 15px; height: 15px; border: 1.5px solid var(--mute-soft); border-radius: 2px;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
            cursor: pointer; transition: border-color 0.15s ease;
        }
        .checkbox-sq:hover { border-color: var(--ink); }

        .crumb { color: var(--mute); }
        .crumb-sep { color: var(--mute-soft); }
        .crumb-current { color: var(--ink); font-weight: 500; }

        .pill {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
            padding: 3px 9px; border-radius: 3px; line-height: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

    <!-- SIDEBAR -->
<?php include './includes/aside.php'; ?>

    <!-- MAIN -->
    <main class="flex-1 flex flex-col min-w-0">

    <?php include './includes/header.php'; ?>

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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Overview</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Dashboard</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Enterprise-wide view across production, fleet, and accounts receivable.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Report
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>New Order
                    </button>
                </div>
            </div>

            <!-- METRIC CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <div class="flex justify-between items-start mb-3">
                        <p class="text-[11px] font-medium uppercase tracking-[0.1em]" style="color: var(--mute);">Revenue — MTD</p>
                        <svg width="52" height="18" viewBox="0 0 52 18"><polyline points="0,15 7,13 14,14 21,9 28,10 35,5 42,6 52,1" fill="none" stroke="#A6A39D" stroke-width="1.4"/></svg>
                    </div>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">482,500<span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>8.2%
                        </span>
                        <span class="ml-2" style="color: var(--mute);">vs last month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <div class="flex justify-between items-start mb-3">
                        <p class="text-[11px] font-medium uppercase tracking-[0.1em]" style="color: var(--mute);">Active Production</p>
                        <svg width="52" height="18" viewBox="0 0 52 18"><polyline points="0,9 7,11 14,7 21,12 28,8 35,10 42,6 52,8" fill="none" stroke="#A6A39D" stroke-width="1.4"/></svg>
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">3,142</h3>
                        <span class="text-[12px] font-medium" style="color: var(--mute);">cylinders</span>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-[11px] mono">
                        <span class="font-medium px-1.5 py-1 rounded-sm border" style="border-color: var(--line); color: var(--ink);">O₂ 1,200</span>
                        <span class="font-medium px-1.5 py-1 rounded-sm border" style="border-color: var(--line); color: var(--ink);">C₂H₂ 850</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fleet Status</p>
                    <div class="flex items-baseline gap-1.5">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">24</h3>
                        <span class="text-[12px] font-medium" style="color: var(--mute);">/ 28 vehicles</span>
                    </div>
                    <div class="mt-4 meter-bar h-1.5 w-full flex overflow-hidden">
                        <div class="meter-fill h-full" style="width: 70%"></div>
                        <div class="h-full" style="width: 15%; background: var(--mute-soft);"></div>
                        <div class="h-full" style="width: 15%; background: var(--line-soft);"></div>
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] font-medium uppercase tracking-wide mono" style="color: var(--mute);">
                        <span>On route</span><span>Loading</span><span>Maint.</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Pending Invoices</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">94,200<span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="alert-circle" class="w-3 h-3"></i>12 overdue
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <!-- MANIFEST / ORDERS -->
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Manifest</h3>
                            <div class="flex items-center gap-4">
                                <button class="transition-colors" style="color: var(--mute);"><i data-lucide="filter" class="w-4 h-4"></i></button>
                                <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                    Master List<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-6 text-[13px] font-medium">
                            <span class="tab-item active">All <span class="num text-[11px]" style="color: var(--mute-soft);">312</span></span>
                            <span class="tab-item">Processing <span class="num text-[11px]" style="color: var(--mute-soft);">48</span></span>
                            <span class="tab-item">In Transit <span class="num text-[11px]" style="color: var(--mute-soft);">21</span></span>
                            <span class="tab-item">Delivered <span class="num text-[11px]" style="color: var(--mute-soft);">237</span></span>
                            <span class="tab-item">Draft <span class="num text-[11px]" style="color: var(--mute-soft);">6</span></span>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                    <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                    <th class="px-3 py-3 font-medium">Order</th>
                                    <th class="px-3 py-3 font-medium">Client Profile</th>
                                    <th class="px-3 py-3 font-medium">Specs</th>
                                    <th class="px-3 py-3 font-medium">Status</th>
                                    <th class="px-6 py-3 font-medium text-right">Value</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);">7742</td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="avatar-sq" style="background:#1A1A1A; color:#fff;">SP</span>
                                            <span class="font-medium" style="color: var(--ink);">SABIC Petrochemicals</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);">LIQ. O₂ / 20T</td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: #EAEAE8; color: #3D3C3A;">
                                            <span class="status-dot" style="background:#3D3C3A;"></span>Processing
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-medium num" style="color: var(--ink);">45,000</td>
                                </tr>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);">7741</td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="avatar-sq" style="background:#3D3C3A; color:#fff;">AP</span>
                                            <span class="font-medium" style="color: var(--ink);">Air Product Co.</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);">C₂H₂ 40L / ×200</td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                                            <span class="status-dot" style="background:#9A7B2E;"></span>In Transit
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-medium num" style="color: var(--ink);">18,400</td>
                                </tr>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);">7740</td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="avatar-sq" style="background:#EFEEEC; color:#5C5A56; border:1px solid #DEDCD7;">AH</span>
                                            <span class="font-medium" style="color: var(--ink);">Abdullah Al-Hashim</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);">AR 50L / ×50</td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: #F2F1EF; color: var(--mute);">
                                            <span class="status-dot" style="background:#A6A39D;"></span>Delivered
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-medium num" style="color: var(--ink);">7,250</td>
                                </tr>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: var(--mute-soft);">7739</td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="avatar-sq" style="background:#F7F7F6; color:#A6A39D; border:1px solid #E7E5E1;">NC</span>
                                            <span class="font-medium" style="color: var(--mute-soft);">National Contracting</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute-soft);">MIXED / ×120</td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: transparent; color: var(--mute-soft); border: 1px solid var(--line);">
                                            <span class="status-dot" style="background:transparent; border:1px solid var(--mute-soft);"></span>Draft
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-right font-medium num" style="color: var(--mute-soft);">11,000</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                        <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–4 of 312</span>
                        <div class="flex items-center gap-1.5">
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                            <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">2</button>
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">3</button>
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                </div>

                <!-- ACTIVITY / TIMELINE -->
                <div class="card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Activity Feed</h3>
                        <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                    </div>
                    <div class="p-5 timeline-rail flex-1 overflow-y-auto">
                        <div class="flex gap-3 pb-5 relative">
                            <div class="timeline-dot current mt-1"></div>
                            <div>
                                <p class="text-[13.5px]" style="color: var(--ink);"><span class="font-semibold">SABIC Petrochemicals</span> order moved to <span class="font-semibold">Processing</span></p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);">Today · 14:22 — R. Fahad</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pb-5 relative">
                            <div class="timeline-dot mt-1"></div>
                            <div>
                                <p class="text-[13.5px]" style="color: var(--ink);">Invoice <span class="font-semibold num">#INV-2291</span> sent to Air Product Co.</p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);">Today · 11:05 — System</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pb-5 relative">
                            <div class="timeline-dot mt-1"></div>
                            <div>
                                <p class="text-[13.5px]" style="color: var(--ink);">Vehicle <span class="font-semibold num">FL-08</span> dispatched for ORD-7740</p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);">Yesterday · 17:40 — M. Aziz</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pb-5 relative">
                            <div class="timeline-dot mt-1"></div>
                            <div>
                                <p class="text-[13.5px]" style="color: var(--ink);">New account created — <span class="font-semibold">National Contracting</span></p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);">Yesterday · 09:12 — S. Noura</p>
                            </div>
                        </div>
                        <div class="flex gap-3 relative">
                            <div class="timeline-dot mt-1"></div>
                            <div>
                                <p class="text-[13.5px]" style="color: var(--ink);">Production batch <span class="font-semibold num">#B-4471</span> closed — 1,200 units</p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);">Mon · 22:00 — Line 2</p>
                            </div>
                        </div>
                    </div>
                    <div class="border-t p-3" style="border-color: var(--line-soft);">
                        <button class="w-full text-center text-[12px] font-medium py-1.5 transition-colors" style="color: var(--mute);">View all activity</button>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS STRIP -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                <button class="ticket flex items-center gap-3 p-4 text-left rounded-md">
                    <div class="w-9 h-9 flex items-center justify-center border rounded-sm flex-shrink-0" style="border-color: var(--line);"><i data-lucide="file-plus" class="w-[16px] h-[16px]" style="color: var(--ink);"></i></div>
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-medium truncate" style="color: var(--ink);">Create Quotation</p>
                        <p class="text-[11.5px] truncate" style="color: var(--mute);">Draft a new price estimate</p>
                    </div>
                </button>
                <button class="ticket flex items-center gap-3 p-4 text-left rounded-md">
                    <div class="w-9 h-9 flex items-center justify-center border rounded-sm flex-shrink-0" style="border-color: var(--line);"><i data-lucide="flask-conical" class="w-[16px] h-[16px]" style="color: var(--ink);"></i></div>
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-medium truncate" style="color: var(--ink);">Log Production</p>
                        <p class="text-[11.5px] truncate" style="color: var(--mute);">Record daily shift output</p>
                    </div>
                </button>
                <button class="ticket flex items-center gap-3 p-4 text-left rounded-md">
                    <div class="w-9 h-9 flex items-center justify-center border rounded-sm flex-shrink-0" style="border-color: var(--line);"><i data-lucide="truck" class="w-[16px] h-[16px]" style="color: var(--ink);"></i></div>
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-medium truncate" style="color: var(--ink);">Dispatch Fleet</p>
                        <p class="text-[11.5px] truncate" style="color: var(--mute);">Assign vehicles to orders</p>
                    </div>
                </button>
                <button class="ticket flex items-center gap-3 p-4 text-left rounded-md">
                    <div class="w-9 h-9 flex items-center justify-center border rounded-sm flex-shrink-0" style="border-color: var(--line);"><i data-lucide="users" class="w-[16px] h-[16px]" style="color: var(--ink);"></i></div>
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-medium truncate" style="color: var(--ink);">Client Directory</p>
                        <p class="text-[11.5px] truncate" style="color: var(--mute);">Manage accounts &amp; POs</p>
                    </div>
                </button>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>