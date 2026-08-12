<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_followup') {
        $stmt = $pdo->prepare("UPDATE customer_followups SET status=?, last_contact=?, next_followup=?, owner=?, notes=? WHERE id=?");
        $stmt->execute([
            $_POST['status'],
            $_POST['last_contact'],
            $_POST['next_followup'],
            $_POST['owner'],
            $_POST['notes'],
            $_POST['followup_id']
        ]);
        header("Location: followup_reminders.php");
        exit;
    } elseif ($_POST['action'] === 'delete_followup') {
        $stmt = $pdo->prepare("DELETE FROM customer_followups WHERE id=?");
        $stmt->execute([$_POST['followup_id']]);
        header("Location: followup_reminders.php");
        exit;
    }
}

$active_page = 'followup_reminders';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Follow-up Reminders'];

$stmt = $pdo->query("
    SELECT cf.*, p.company_name, p.contact_first_name, p.phone 
    FROM customer_followups cf 
    JOIN partners p ON cf.client_id = p.id 
    ORDER BY cf.next_followup ASC
");
$records = $stmt->fetchAll();

$today = strtotime(date('Y-m-d'));
$counts = ['Overdue' => 0, 'Due Today' => 0, 'Due Soon' => 0, 'On Track' => 0];
$reminders = [];

foreach ($records as $row) {
    if (!$row['next_followup'] || in_array($row['status'], ['Won', 'Lost'])) {
        continue;
    }

    $target = strtotime($row['next_followup']);
    $diff = round(($target - $today) / 86400);
    
    $alert = '';
    if ($diff < 0) {
        $alert = 'Overdue';
        $counts['Overdue']++;
    } elseif ($diff == 0) {
        $alert = 'Due Today';
        $counts['Due Today']++;
    } elseif ($diff <= 3) {
        $alert = 'Due Soon';
        $counts['Due Soon']++;
    } else {
        $alert = 'On Track';
        $counts['On Track']++;
    }

    $reminders[] = [
        'id' => $row['id'],
        'company' => $row['company_name'],
        'contact' => $row['contact_first_name'],
        'phone' => $row['phone'],
        'status' => $row['status'],
        'last_contact' => $row['last_contact'],
        'next_followup' => $row['next_followup'],
        'owner' => $row['owner'],
        'notes' => $row['notes'],
        'alert' => $alert,
        'diff' => $diff
    ];
}

usort($reminders, function($a, $b) {
    $order = ['Overdue' => 1, 'Due Today' => 2, 'Due Soon' => 3, 'On Track' => 4];
    if ($order[$a['alert']] === $order[$b['alert']]) {
        return $a['diff'] <=> $b['diff'];
    }
    return $order[$a['alert']] <=> $order[$b['alert']];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follow-up Reminders | I-GAS Enterprise</title>
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
        body { font-family: 'IBM Plex Sans', sans-serif; background-color: var(--paper-dim); color: var(--ink); font-feature-settings: "tnum" 1; }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        .nav-row { position: relative; border-left: 2px solid transparent; transition: 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: 0.15s ease; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: none; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: 0.15s ease; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--ink) !important; }
        .pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.04em; }
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(2px); z-index: 50; display: none; align-items: center; justify-content: center; }
        .modal-bg.open { display: flex; }
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
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">
            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">CRM & Sales</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Follow-up Reminders</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Track and prioritize client communications and sales pipeline tasks.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #B02418;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Overdue</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num text-red-700"><?= $counts['Overdue'] ?></h3>
                </div>
                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #B9770E;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Due Today</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num text-amber-700"><?= $counts['Due Today'] ?></h3>
                </div>
                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #1F6F78;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Due Soon (≤ 3 Days)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num text-teal-700"><?= $counts['Due Soon'] ?></h3>
                </div>
                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #1E7B45;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">On Track</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num text-green-700"><?= $counts['On Track'] ?></h3>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Action Items</h3>
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                        <input type="text" placeholder="Search client..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-64" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b bg-gray-50" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">Alert</th>
                                <th class="px-3 py-3 font-medium">Customer</th>
                                <th class="px-3 py-3 font-medium">Owner</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="px-3 py-3 font-medium">Next Follow-up</th>
                                <th class="px-3 py-3 font-medium text-right">Days</th>
                                <th class="px-3 py-3 font-medium">Notes</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($reminders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-[13px]" style="color: var(--mute);">No open follow-ups. Every account is up to date.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($reminders as $r): ?>
                                <?php
                                    $alertStyle = '';
                                    if ($r['alert'] === 'Overdue') $alertStyle = 'background: #B02418; color: #FFF;';
                                    elseif ($r['alert'] === 'Due Today') $alertStyle = 'background: #B9770E; color: #FFF;';
                                    elseif ($r['alert'] === 'Due Soon') $alertStyle = 'background: #FCEFCF; color: #B9770E;';
                                    else $alertStyle = 'background: #E0F0E4; color: #1E7B45;';

                                    $statusStyle = '';
                                    if ($r['status'] === 'New') $statusStyle = 'background: #DEE9F6; color: #1F5C99;';
                                    elseif ($r['status'] === 'Follow-up') $statusStyle = 'background: #FCEFCF; color: #B9770E;';
                                ?>
                                <tr class="transition-colors hover:bg-gray-50">
                                    <td class="pl-6 pr-3 py-3.5">
                                        <span class="pill" style="<?= $alertStyle ?>"><?= $r['alert'] ?></span>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <div class="font-semibold" style="color: var(--ink);"><?= htmlspecialchars($r['company']) ?></div>
                                        <div class="text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($r['contact']) ?> · <?= htmlspecialchars($r['phone']) ?></div>
                                    </td>
                                    <td class="px-3 py-3.5" style="color: var(--ink);"><?= htmlspecialchars($r['owner']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="<?= $statusStyle ?>"><?= htmlspecialchars($r['status']) ?></span>
                                    </td>
                                    <td class="px-3 py-3.5 mono text-[12.5px]" style="color: var(--mute);"><?= date('d M Y', strtotime($r['next_followup'])) ?></td>
                                    <td class="px-3 py-3.5 text-right font-semibold num" style="color: var(--ink);"><?= $r['diff'] ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] max-w-[200px] truncate" style="color: var(--mute);" title="<?= htmlspecialchars($r['notes']) ?>"><?= htmlspecialchars($r['notes']) ?></td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick='editFollowup(<?= json_encode($r) ?>)' class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors hover:bg-gray-100" style="border-color: var(--line); color: var(--mute);" title="Edit Follow-up">
                                                <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <form method="POST" action="" onsubmit="return confirm('Delete this follow-up reminder?');" class="inline">
                                                <input type="hidden" name="action" value="delete_followup">
                                                <input type="hidden" name="followup_id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors hover:bg-red-50" style="border-color: var(--line); color: #963B33;" title="Delete">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="followupModal" class="modal-bg">
        <div class="bg-white rounded-md shadow-2xl w-[500px] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50" style="border-color: var(--line-soft);">
                <h3 class="text-[16px] font-semibold" style="color: var(--ink);">Update Follow-up</h3>
                <button onclick="closeModal('followupModal')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="edit_followup">
                <input type="hidden" name="followup_id" id="f_id" value="">
                
                <div class="mb-4 pb-4 border-b" style="border-color: var(--line-soft);">
                    <p class="text-[12px] uppercase tracking-wider mb-1" style="color: var(--mute);">Client</p>
                    <p class="text-[15px] font-semibold" style="color: var(--ink);" id="f_company_name"></p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Status</label>
                        <select name="status" id="f_status" class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                            <option value="New">New</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Won">Won</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Account Owner</label>
                        <input type="text" name="owner" id="f_owner" required class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Last Contact</label>
                        <input type="date" name="last_contact" id="f_last_contact" class="w-full px-3 py-2 border rounded-sm text-[13.5px] mono" style="border-color: var(--line);">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Next Follow-up</label>
                        <input type="date" name="next_followup" id="f_next_followup" required class="w-full px-3 py-2 border rounded-sm text-[13.5px] mono" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Notes</label>
                    <textarea name="notes" id="f_notes" rows="3" class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('followupModal')" class="btn-secondary px-5 py-2 rounded-sm text-[13px] font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-sm text-[13px] font-medium">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function editFollowup(r) {
            document.getElementById('f_id').value = r.id;
            document.getElementById('f_company_name').textContent = r.company;
            document.getElementById('f_status').value = r.status;
            document.getElementById('f_owner').value = r.owner;
            document.getElementById('f_last_contact').value = r.last_contact;
            document.getElementById('f_next_followup').value = r.next_followup;
            document.getElementById('f_notes').value = r.notes;
            
            document.getElementById('followupModal').classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }
    </script>
</body>
</html>