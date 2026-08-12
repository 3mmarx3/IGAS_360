<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $stmt = $pdo->prepare("INSERT INTO users (emp_id, full_name, username, email, phone, role, permission_level, status, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['emp_id'], $_POST['full_name'], $_POST['username'], $_POST['email'], $_POST['phone'], 
                $_POST['role'], $_POST['permission_level'], $_POST['status'], password_hash($_POST['password'], PASSWORD_DEFAULT)
            ]);
            header("Location: team_access.php");
            exit;
        } elseif ($_POST['action'] === 'edit_user') {
            $stmt = $pdo->prepare("UPDATE users SET emp_id=?, full_name=?, username=?, email=?, phone=?, role=?, permission_level=?, status=? WHERE id=?");
            $stmt->execute([
                $_POST['emp_id'], $_POST['full_name'], $_POST['username'], $_POST['email'], $_POST['phone'], 
                $_POST['role'], $_POST['permission_level'], $_POST['status'], $_POST['user_id']
            ]);
            header("Location: team_access.php");
            exit;
        } elseif ($_POST['action'] === 'delete_user') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$_POST['user_id']]);
            header("Location: team_access.php");
            exit;
        } elseif ($_POST['action'] === 'reset_password') {
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $_POST['user_id']]);
            header("Location: team_access.php");
            exit;
        }
    }
}

$active_page = 'team_access';
$breadcrumb  = ['I-GAS', 'Insights & Admin', 'Team & Access'];

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$total_users = count($users);
$active_users = 0;
$admin_users = 0;
$suspended_users = 0;

foreach ($users as $u) {
    if ($u['status'] === 'Active') $active_users++;
    if ($u['status'] === 'Suspended') $suspended_users++;
    if ($u['permission_level'] === 'Admin' && $u['status'] === 'Active') $admin_users++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team & Access | I-GAS Enterprise</title>
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
        input:focus, select:focus { outline: none; border-color: var(--ink) !important; }
        .avatar-sq { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; flex-shrink: 0; border-radius: 4px; background: var(--ink); color: #fff; }
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Insights & Admin</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Team & Access</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage system users, roles, and functional permissions.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Directory
                    </button>
                    <button onclick="openModal('userModal')" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>Add User
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Users</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_users ?></h3>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Accounts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $active_users ?></h3>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Administrators</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $admin_users ?></h3>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Suspended</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num text-red-700"><?= $suspended_users ?></h3>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">User Directory</h3>
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                        <input type="text" placeholder="Search user..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-64" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b bg-gray-50" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">User</th>
                                <th class="px-3 py-3 font-medium">Contact</th>
                                <th class="px-3 py-3 font-medium">Role</th>
                                <th class="px-3 py-3 font-medium">Access Level</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($users as $u): ?>
                            <?php
                                $initials = strtoupper(substr($u['full_name'], 0, 1) . substr(strstr($u['full_name'], ' '), 1, 1));
                                if (strlen($initials) < 2) $initials = strtoupper(substr($u['full_name'], 0, 2));
                                
                                $permColor = '';
                                if ($u['permission_level'] === 'Admin') $permColor = 'background: #EAF1E7; color: #45663F;';
                                elseif ($u['permission_level'] === 'Edit') $permColor = 'background: #E8F1F5; color: #2A6B8A;';
                                else $permColor = 'background: #F2F1EF; color: #767470;';
                            ?>
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="pl-6 pr-3 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-sq"><?= $initials ?></div>
                                        <div>
                                            <div class="font-semibold" style="color: var(--ink);"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="text-[12px] mono" style="color: var(--mute);">@<?= htmlspecialchars($u['username']) ?> · <?= htmlspecialchars($u['emp_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div style="color: var(--ink);"><?= htmlspecialchars($u['email']) ?></div>
                                    <div class="text-[12px] mono" style="color: var(--mute);"><?= htmlspecialchars($u['phone']) ?></div>
                                </td>
                                <td class="px-3 py-3.5" style="color: var(--ink);"><?= htmlspecialchars($u['role']) ?></td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="<?= $permColor ?>"><?= htmlspecialchars($u['permission_level']) ?></span>
                                </td>
                                <td class="px-3 py-3.5">
                                    <?php if ($u['status'] === 'Active'): ?>
                                        <span class="flex items-center gap-1.5 text-[12px] font-medium" style="color: #45663F;">
                                            <span class="status-dot" style="background: #45663F;"></span>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="flex items-center gap-1.5 text-[12px] font-medium text-red-600">
                                            <span class="status-dot bg-red-600"></span>Suspended
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pr-6 py-3.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick='editUser(<?= json_encode($u) ?>)' class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors hover:bg-gray-100" style="border-color: var(--line); color: var(--mute);" title="Edit User">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button onclick='resetPassword(<?= $u['id'] ?>, "<?= htmlspecialchars($u['username']) ?>")' class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors hover:bg-gray-100" style="border-color: var(--line); color: var(--mute);" title="Reset Password">
                                            <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors hover:bg-red-50" style="border-color: var(--line); color: #963B33;" title="Delete User">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="userModal" class="modal-bg">
        <div class="bg-white rounded-md shadow-2xl w-[600px] overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50" style="border-color: var(--line-soft);">
                <h3 id="modalTitle" class="text-[16px] font-semibold" style="color: var(--ink);">Add New User</h3>
                <button onclick="closeModal('userModal')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" action="" class="flex-1 overflow-auto p-6">
                <input type="hidden" name="action" id="modalAction" value="add_user">
                <input type="hidden" name="user_id" id="modalUserId" value="">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Full Name</label>
                        <input type="text" name="full_name" id="f_full_name" required class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Employee ID</label>
                        <input type="text" name="emp_id" id="f_emp_id" required class="w-full px-3 py-2 border rounded-sm text-[13.5px] mono" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Username</label>
                        <input type="text" name="username" id="f_username" required class="w-full px-3 py-2 border rounded-sm text-[13.5px] mono" style="border-color: var(--line);">
                    </div>
                    <div id="pwContainer">
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Initial Password</label>
                        <input type="password" name="password" id="f_password" class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Email Address</label>
                        <input type="email" name="email" id="f_email" required class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Phone Number</label>
                        <input type="text" name="phone" id="f_phone" class="w-full px-3 py-2 border rounded-sm text-[13.5px] mono" style="border-color: var(--line);">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Job Role</label>
                    <input type="text" name="role" id="f_role" required class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);" placeholder="e.g. Sales Representative">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-2">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Permission Level</label>
                        <select name="permission_level" id="f_permission" class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                            <option value="View">View Only</option>
                            <option value="Edit">Edit</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Account Status</label>
                        <select name="status" id="f_status" class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 pt-4 border-t" style="border-color: var(--line-soft);">
                    <button type="button" onclick="closeModal('userModal')" class="btn-secondary px-5 py-2 rounded-sm text-[13px] font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-sm text-[13px] font-medium">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <div id="pwModal" class="modal-bg">
        <div class="bg-white rounded-md shadow-2xl w-[400px] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50" style="border-color: var(--line-soft);">
                <h3 class="text-[16px] font-semibold" style="color: var(--ink);">Reset Password</h3>
                <button onclick="closeModal('pwModal')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="pwUserId" value="">
                
                <p class="text-[13px] mb-4" style="color: var(--mute);">Resetting password for <span class="font-semibold mono text-[12px]" id="pwUsername" style="color: var(--ink);"></span></p>

                <div class="mb-5">
                    <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">New Password</label>
                    <input type="password" name="new_password" required class="w-full px-3 py-2 border rounded-sm text-[13.5px]" style="border-color: var(--line);">
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('pwModal')" class="btn-secondary px-5 py-2 rounded-sm text-[13px] font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-sm text-[13px] font-medium">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openModal(id) {
            document.getElementById(id).classList.add('open');
            if(id === 'userModal') {
                document.getElementById('modalTitle').textContent = 'Add New User';
                document.getElementById('modalAction').value = 'add_user';
                document.getElementById('modalUserId').value = '';
                document.getElementById('f_full_name').value = '';
                document.getElementById('f_emp_id').value = '';
                document.getElementById('f_username').value = '';
                document.getElementById('f_email').value = '';
                document.getElementById('f_phone').value = '';
                document.getElementById('f_role').value = '';
                document.getElementById('f_permission').value = 'View';
                document.getElementById('f_status').value = 'Active';
                document.getElementById('pwContainer').style.display = 'block';
                document.getElementById('f_password').required = true;
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function editUser(u) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('modalAction').value = 'edit_user';
            document.getElementById('modalUserId').value = u.id;
            
            document.getElementById('f_full_name').value = u.full_name;
            document.getElementById('f_emp_id').value = u.emp_id;
            document.getElementById('f_username').value = u.username;
            document.getElementById('f_email').value = u.email;
            document.getElementById('f_phone').value = u.phone;
            document.getElementById('f_role').value = u.role;
            document.getElementById('f_permission').value = u.permission_level;
            document.getElementById('f_status').value = u.status;
            
            document.getElementById('pwContainer').style.display = 'none';
            document.getElementById('f_password').required = false;

            document.getElementById('userModal').classList.add('open');
        }

        function resetPassword(id, username) {
            document.getElementById('pwUserId').value = id;
            document.getElementById('pwUsername').textContent = '@' + username;
            document.getElementById('pwModal').classList.add('open');
        }
    </script>
</body>
</html>