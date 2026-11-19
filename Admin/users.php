<?php
/* ============================================================
   PULSEMED ESSENTIALS - ADMIN USER MANAGEMENT
   Save as: C:\xampp\htdocs\fresh_pulsemed\admin\users.php
============================================================ */
session_start();
require_once __DIR__ . '/../database.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['users_csrf'];
$message = '';
$error = '';
$editing = null;

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function db_has_column(mysqli $conn, string $table, string $column): bool {
    $table = str_replace('`', '', $table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}
$hasPhone = db_has_column($conn, 'users', 'phone');
$hasCreatedAt = db_has_column($conn, 'users', 'created_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $postedToken)) {
        $error = 'Your session verification failed. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_user') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $role = (string)($_POST['role'] ?? 'customer');
            $password = (string)($_POST['password'] ?? '');

            if ($name === '' || mb_strlen($name) > 150) {
                $error = 'Enter a valid name (maximum 150 characters).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                $error = 'Enter a valid email address.';
            } elseif (!in_array($role, ['customer', 'admin'], true)) {
                $error = 'Select a valid user role.';
            } elseif ($hasPhone && $phone !== '' && strlen($phone) > 30) {
                $error = 'Phone number is too long.';
            } elseif ($id === 0 && strlen($password) < 8) {
                $error = 'A password of at least 8 characters is required for a new user.';
            } elseif ($password !== '' && strlen($password) < 8) {
                $error = 'If changing the password, use at least 8 characters.';
            } elseif ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $error = 'You cannot remove your own administrator role.';
            } else {
                $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                if (!$check) {
                    $error = 'Could not validate the email address. Check the users table structure.';
                } else {
                    $check->bind_param('si', $email, $id);
                    $check->execute();
                    $duplicate = $check->get_result()->num_rows > 0;
                    $check->close();
                    if ($duplicate) {
                        $error = 'That email address is already registered.';
                    } else {
                        if ($id > 0) {
                            $exists = $conn->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
                            $exists->bind_param('i', $id);
                            $exists->execute();
                            $oldUser = $exists->get_result()->fetch_assoc();
                            $exists->close();
                            if (!$oldUser) {
                                $error = 'The selected user could not be found.';
                            } elseif ($oldUser['role'] === 'admin' && $role !== 'admin') {
                                $admins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
                                $adminCount = $admins ? (int)$admins->fetch_assoc()['total'] : 0;
                                if ($adminCount <= 1) $error = 'You cannot remove the role from the last administrator.';
                            }
                        }
                        if ($error === '') {
                            $phoneValue = $hasPhone ? $phone : '';
                            if ($id > 0) {
                                if ($password !== '') {
                                    $hash = password_hash($password, PASSWORD_DEFAULT);
                                    if ($hasPhone) $stmt = $conn->prepare('UPDATE users SET name=?, email=?, phone=?, role=?, password=? WHERE id=?');
                                    else $stmt = $conn->prepare('UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?');
                                    if ($stmt) {
                                        if ($hasPhone) $stmt->bind_param('sssssi', $name, $email, $phoneValue, $role, $hash, $id);
                                        else $stmt->bind_param('ssssi', $name, $email, $role, $hash, $id);
                                    }
                                } else {
                                    if ($hasPhone) $stmt = $conn->prepare('UPDATE users SET name=?, email=?, phone=?, role=? WHERE id=?');
                                    else $stmt = $conn->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?');
                                    if ($stmt) {
                                        if ($hasPhone) $stmt->bind_param('ssssi', $name, $email, $phoneValue, $role, $id);
                                        else $stmt->bind_param('sssi', $name, $email, $role, $id);
                                    }
                                }
                                if (isset($stmt) && $stmt && $stmt->execute()) {
                                    $message = 'User details updated successfully.';
                                    $stmt->close();
                                    if ($id === (int)$_SESSION['user_id']) $_SESSION['user_name'] = $name;
                                } else {
                                    $error = 'Could not update the user. ' . ($conn->error ?: 'Please try again.');
                                    if (isset($stmt) && $stmt) $stmt->close();
                                }
                            } else {
                                $hash = password_hash($password, PASSWORD_DEFAULT);
                                if ($hasPhone) $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
                                else $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                                if ($stmt) {
                                    if ($hasPhone) $stmt->bind_param('sssss', $name, $email, $phoneValue, $hash, $role);
                                    else $stmt->bind_param('ssss', $name, $email, $hash, $role);
                                    if ($stmt->execute()) $message = 'User created successfully.';
                                    else $error = 'Could not create the user. ' . $stmt->error;
                                    $stmt->close();
                                } else $error = 'Could not prepare the user record. ' . $conn->error;
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'delete_user') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            if ($id < 1) {
                $error = 'Invalid user selected.';
            } elseif ($id === (int)$_SESSION['user_id']) {
                $error = 'You cannot delete the administrator account currently in use.';
            } else {
                $target = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
                $target->bind_param('i', $id);
                $target->execute();
                $targetUser = $target->get_result()->fetch_assoc();
                $target->close();
                if (!$targetUser) $error = 'User not found.';
                elseif ($targetUser['role'] === 'admin') {
                    $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='admin'");
                    $adminCount = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;
                    if ($adminCount <= 1) $error = 'The last administrator cannot be deleted.';
                }
                if ($error === '') {
                    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute()) $message = 'User deleted successfully.';
                    else $error = 'Could not delete the user. The account may be linked to existing orders.';
                    $stmt->close();
                }
            }
        }
    }
}

// Load an account into the edit form when requested.
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT id, name, email, ' . ($hasPhone ? 'phone' : "'' AS phone") . ', role FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? '');
$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?' . ($hasPhone ? ' OR phone LIKE ?' : '') . ')';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $types .= 'ss';
    if ($hasPhone) { $params[] = $like; $types .= 's'; }
}
if (in_array($roleFilter, ['admin', 'customer'], true)) {
    $where[] = 'role = ?'; $params[] = $roleFilter; $types .= 's';
}
$sql = 'SELECT id, name, email, ' . ($hasPhone ? 'phone' : "'' AS phone") . ', role, ' . ($hasCreatedAt ? 'created_at' : 'NULL AS created_at') . ' FROM users';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';
$users = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $users[] = $row;
    $stmt->close();
} else $error = $error ?: 'Could not load users. Please confirm the users table columns.';

$stats = ['total' => 0, 'customers' => 0, 'admins' => 0];
$statsResult = $conn->query("SELECT COUNT(*) total, SUM(role='customer') customers, SUM(role='admin') admins FROM users");
if ($statsResult && ($s = $statsResult->fetch_assoc())) {
    $stats['total'] = (int)$s['total']; $stats['customers'] = (int)$s['customers']; $stats['admins'] = (int)$s['admins'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Management | PulseMed Essentials</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--teal:#087f8c;--teal-dark:#066873;--green:#24a77b;--ink:#123f46;--muted:#6c8589;--bg:#eef8f6;--line:#e2eeec}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.layout{display:flex;min-height:100vh}.sidebar{width:245px;background:#fff;border-right:1px solid var(--line);padding:25px 17px;flex-shrink:0}.brand{display:flex;align-items:center;gap:11px;padding:0 9px 28px;font-weight:800;font-size:17px}.brand img{width:43px;height:43px;object-fit:contain}.navlink{display:block;text-decoration:none;color:#577176;padding:12px 14px;border-radius:12px;margin:4px 0;font-weight:600}.navlink:hover,.navlink.active{background:#e8f7f4;color:var(--teal)}.main{padding:30px;width:calc(100% - 245px);min-width:0}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:25px}.eyebrow{font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:var(--teal);font-weight:800}.title{font-size:clamp(25px,3vw,34px);font-weight:800;margin:5px 0}.sub{color:var(--muted)}.cardx{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;box-shadow:0 5px 20px rgba(18,63,70,.035);margin-bottom:22px}.stat{display:flex;align-items:center;justify-content:space-between}.stat small{color:var(--muted);font-weight:700}.stat strong{display:block;font-size:30px;margin-top:5px}.staticon{height:46px;width:46px;border-radius:14px;display:grid;place-items:center;background:#e8f7f4;color:var(--teal);font-size:22px}.form-label{font-weight:700;font-size:13px}.form-control,.form-select{border-color:#dbe9e7;border-radius:10px;padding:11px 12px}.form-control:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 .2rem #087f8c20}.btn-primary{background:var(--teal);border-color:var(--teal);border-radius:10px;font-weight:700;padding:10px 16px}.btn-primary:hover{background:var(--teal-dark);border-color:var(--teal-dark)}.btn-light{border:1px solid var(--line);border-radius:10px}.table-wrap{overflow-x:auto}.table{vertical-align:middle;margin:0}.table thead th{font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#71898d;background:#f7fbfa;border-bottom:1px solid var(--line);padding:14px}.table tbody td{padding:15px 14px;border-color:#edf3f2}.person{display:flex;align-items:center;gap:11px;min-width:180px}.avatar{width:38px;height:38px;border-radius:12px;background:#e8f7f4;color:var(--teal);display:grid;place-items:center;font-weight:800}.badge-role{display:inline-block;border-radius:30px;padding:6px 10px;font-size:12px;font-weight:800}.role-admin{background:#fff2dc;color:#97610a}.role-customer{background:#e8f7f0;color:#177653}.muted{color:var(--muted)}.section-title{font-size:18px;font-weight:800;margin:0}.actions{display:flex;gap:7px;align-items:center}.actions form{margin:0}.smallbtn{border:1px solid var(--line);background:#fff;border-radius:8px;padding:7px 10px;text-decoration:none;color:var(--ink);font-size:13px;font-weight:700}.smallbtn.delete{color:#b42318}.alert{border-radius:12px}.footer-note{font-size:12px;color:var(--muted);padding:10px 0}.mobile-menu{display:none}
@media(max-width:900px){.sidebar{width:200px}.main{width:calc(100% - 200px);padding:22px}}@media(max-width:700px){.layout{display:block}.sidebar{width:100%;padding:13px;display:flex;align-items:center;gap:6px;overflow-x:auto}.brand{padding:0 8px 0 0;white-space:nowrap}.brand img{width:35px;height:35px}.navlink{white-space:nowrap;padding:9px 11px;font-size:13px}.main{width:100%;padding:17px}.topbar{align-items:flex-start}.cardx{padding:17px}.topbar .btn{white-space:nowrap}}
@media print{.sidebar,.no-print,.btn,.actions{display:none!important}.main{width:100%;padding:0}body{background:#fff}.cardx{box-shadow:none;border:1px solid #ddd}.layout{display:block}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar no-print">
  <div class="brand"><img src="../assets/images/pulsemed-logo.png" alt="PulseMed logo" onerror="this.style.display='none'"><span>PulseMed <span style="color:var(--teal)">Essentials</span></span></div>
  <a class="navlink" href="dashboard.php">▦ &nbsp; Dashboard</a>
  <a class="navlink" href="products.php">▣ &nbsp; Products</a>
  <a class="navlink" href="orders.php">▤ &nbsp; Orders</a>
  <a class="navlink active" href="users.php">♙ &nbsp; Users</a>
  <a class="navlink" href="reports.php">▥ &nbsp; Reports</a>
  <div style="height:20px"></div><a class="navlink" href="../shop.php">↗ &nbsp; View Public Shop</a>
</aside>
<main class="main">
  <header class="topbar"><div><div class="eyebrow">Administration</div><h1 class="title">User Management</h1><div class="sub">Manage administrator and customer accounts securely.</div></div><button class="btn btn-light no-print" onclick="window.print()">Print list</button></header>
  <?php if ($message !== ''): ?><div class="alert alert-success" role="alert"><?=h($message)?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?=h($error)?></div><?php endif; ?>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4"><div class="cardx stat"><div><small>Total accounts</small><strong><?=number_format($stats['total'])?></strong></div><div class="staticon">♙</div></div></div>
    <div class="col-12 col-md-4"><div class="cardx stat"><div><small>Customers</small><strong><?=number_format($stats['customers'])?></strong></div><div class="staticon">♡</div></div></div>
    <div class="col-12 col-md-4"><div class="cardx stat"><div><small>Administrators</small><strong><?=number_format($stats['admins'])?></strong></div><div class="staticon">✓</div></div></div>
  </div>
  <section class="cardx" id="user-form">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h2 class="section-title"><?=$editing ? 'Edit user account' : 'Add a user'?></h2><?php if ($editing): ?><a class="btn btn-light btn-sm" href="users.php">Cancel edit</a><?php endif; ?></div>
    <form method="post" action="users.php<?= $editing ? '?edit='.(int)$editing['id'] : '' ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?=h($editing['id'] ?? 0)?>">
      <div class="row g-3">
        <div class="col-12 col-md-6"><label class="form-label" for="name">Full name *</label><input class="form-control" id="name" name="name" maxlength="150" required value="<?=h($editing['name'] ?? '')?>" placeholder="Enter full name"></div>
        <div class="col-12 col-md-6"><label class="form-label" for="email">Email address *</label><input class="form-control" type="email" id="email" name="email" maxlength="190" required value="<?=h($editing['email'] ?? '')?>" placeholder="name@example.com"></div>
        <?php if ($hasPhone): ?><div class="col-12 col-md-6"><label class="form-label" for="phone">Phone number</label><input class="form-control" id="phone" name="phone" maxlength="30" value="<?=h($editing['phone'] ?? '')?>" placeholder="Phone number"></div><?php endif; ?>
        <div class="col-12 col-md-6"><label class="form-label" for="role">Account role *</label><select class="form-select" id="role" name="role" required><option value="customer" <?=($editing['role'] ?? 'customer')==='customer'?'selected':''?>>Customer</option><option value="admin" <?=($editing['role'] ?? '')==='admin'?'selected':''?>>Administrator</option></select></div>
        <div class="col-12 col-md-6"><label class="form-label" for="password">Password <?=$editing ? '(leave blank to keep current)' : '*'?></label><input class="form-control" type="password" id="password" name="password" minlength="8" <?=$editing ? '' : 'required'?> autocomplete="new-password" placeholder="At least 8 characters"><div class="form-text">Passwords are stored securely using PHP password hashing.</div></div>
        <div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><?=$editing ? 'Save changes' : 'Create user'?></button><?php if ($editing): ?><a class="btn btn-light" href="users.php">Cancel</a><?php endif; ?></div>
      </div>
    </form>
  </section>
  <section class="cardx">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><h2 class="section-title">All user accounts</h2><span class="muted small"><?=count($users)?> account(s) shown</span></div>
    <form class="row g-2 mb-3 no-print" method="get" action="users.php">
      <div class="col-12 col-md"><input class="form-control" name="q" value="<?=h($search)?>" placeholder="Search name, email<?= $hasPhone ? ' or phone' : '' ?>..."></div>
      <div class="col-7 col-md-3"><select class="form-select" name="role"><option value="">All roles</option><option value="customer" <?=$roleFilter==='customer'?'selected':''?>>Customers</option><option value="admin" <?=$roleFilter==='admin'?'selected':''?>>Administrators</option></select></div>
      <div class="col-5 col-md-auto"><button class="btn btn-primary w-100" type="submit">Search</button></div>
      <div class="col-12 col-md-auto"><a class="btn btn-light w-100" href="users.php">Clear</a></div>
    </form>
    <div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>User</th><?php if ($hasPhone): ?><th>Phone</th><?php endif; ?><th>Role</th><th>Joined</th><th class="no-print">Actions</th></tr></thead><tbody>
    <?php if (!$users): ?><tr><td colspan="<?= $hasPhone ? 6 : 5 ?>" class="text-center py-5 muted">No user accounts matched your search.</td></tr><?php else: foreach ($users as $u): ?>
      <tr><td class="muted">#<?= (int)$u['id'] ?></td><td><div class="person"><div class="avatar"><?=h(strtoupper(mb_substr(trim((string)$u['name']),0,1)))?></div><div><div class="fw-bold"><?=h($u['name'])?><?=((int)$u['id']===(int)$_SESSION['user_id'])?' <span class="muted small">(You)</span>':''?></div><div class="small muted"><?=h($u['email'])?></div></div></div></td>
      <?php if ($hasPhone): ?><td><?=h($u['phone'] ?: '—')?></td><?php endif; ?><td><span class="badge-role <?=$u['role']==='admin'?'role-admin':'role-customer'?>"><?=h(ucfirst($u['role']))?></span></td><td><?=h(!empty($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '—')?></td><td class="no-print"><div class="actions"><a class="smallbtn" href="users.php?edit=<?=(int)$u['id']?>#user-form">Edit</a><?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?><form method="post" onsubmit="return confirm('Delete this user account? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><button class="smallbtn delete" type="submit">Delete</button></form><?php endif; ?></div></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <div class="footer-note">Administrator accounts have access to management features. Only create admin accounts for trusted staff.</div>
  </section>
</main>
</div>
</body></html>
