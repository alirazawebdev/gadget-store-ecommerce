<?php
/**
 * Users & roles management.
 */
require_once __DIR__ . '/includes/auth.php';
$adminTitle = 'Users & Roles';
if (!can('users')) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="card"><div class="alert alert-error">Permission denied.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$roles = db()->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$roleIds = array_map('intval', array_column($roles, 'id'));

/* ---------- save user ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid      = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $status   = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || !in_array($role_id, $roleIds, true)) {
        flash('error', 'Name, valid email and role are required.');
        redirect(admin_url('users.php'));
    }
    $dup = db()->prepare("SELECT id FROM users WHERE email = ?" . ($uid ? " AND id != $uid" : ""));
    $dup->execute([$email]);
    if ($dup->fetch()) {
        flash('error', 'This email is already used by another user.');
        redirect(admin_url('users.php'));
    }

    if ($uid > 0) {
        if ($password !== '') {
            db()->prepare("UPDATE users SET name=?, email=?, password=?, role_id=?, status=? WHERE id=?")
              ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role_id, $status, $uid]);
        } else {
            db()->prepare("UPDATE users SET name=?, email=?, role_id=?, status=? WHERE id=?")
              ->execute([$name, $email, $role_id, $status, $uid]);
        }
        log_activity("Updated user: $email (id $uid)");
        flash('success', 'User updated.');
    } else {
        if ($password === '') {
            flash('error', 'Password is required for a new user.');
            redirect(admin_url('users.php'));
        }
        db()->prepare("INSERT INTO users (role_id, name, email, password, status) VALUES (?,?,?,?,?)")
          ->execute([$role_id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $status]);
        log_activity("Added user: $email");
        flash('success', 'User added.');
    }
    redirect(admin_url('users.php'));
}

/* ---------- delete user ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $did = (int)$_POST['delete_user'];
    if ($did === (int)$admin['id']) {
        flash('error', 'You cannot delete your own account.');
    } else {
        $st = db()->prepare("SELECT email FROM users WHERE id = ?");
        $st->execute([$did]);
        if ($row = $st->fetch()) {
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$did]);
            log_activity("Deleted user: {$row['email']} (id $did)");
            flash('success', 'User deleted.');
        }
    }
    redirect(admin_url('users.php'));
}

/* ---------- save role permissions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    $rid = (int)$_POST['role_id'];
    $st = db()->prepare("SELECT * FROM roles WHERE id = ?");
    $st->execute([$rid]);
    $role = $st->fetch();
    if ($role && $role['name'] !== 'Super Admin') {
        $perms = array_values(array_intersect($_POST['perms'] ?? [], array_keys(PERMISSIONS)));
        db()->prepare("UPDATE roles SET permissions = ? WHERE id = ?")
          ->execute([json_encode($perms), $rid]);
        log_activity("Updated permissions for role: {$role['name']}");
        flash('success', 'Role permissions saved.');
    }
    redirect(admin_url('users.php'));
}

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId > 0) {
    $st = db()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$editId]);
    $editUser = $st->fetch();
}

$users = db()->query(
    "SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.id"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <h1>Users & Roles</h1>
  <?php if ($editUser): ?><a class="btn btn-gray" href="<?= admin_url('users.php') ?>">+ New User</a><?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?= $editUser ? 'Edit User' : 'Add User' ?></h3>
    <form method="post" action="">
      <input type="hidden" name="save_user" value="1">
      <input type="hidden" name="id" value="<?= $editUser ? (int)$editUser['id'] : 0 ?>">
      <div class="form-group">
        <label>Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= e($editUser['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required value="<?= e($editUser['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password <?= $editUser ? '<small style="color:#6b7280">(leave blank to keep current)</small>' : '*' ?></label>
        <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select name="role_id" class="form-control">
            <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)($editUser['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= ($editUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($editUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <button class="btn" type="submit"><?= $editUser ? 'Save Changes' : 'Add User' ?></button>
    </form>
  </div>

  <div class="card">
    <h3>All Users</h3>
    <table class="table">
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong><?= (int)$u['id'] === (int)$admin['id'] ? ' <small>(you)</small>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['role_name']) ?></td>
        <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm" href="<?= admin_url('users.php?edit=' . (int)$u['id']) ?>">Edit</a>
          <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
          <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this user?')">
            <input type="hidden" name="delete_user" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">Del</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <h3>Roles & Permissions</h3>
  <?php foreach ($roles as $r): ?>
    <?php $rp = json_decode($r['permissions'] ?? '[]', true); if (!is_array($rp)) $rp = []; ?>
    <div style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:12px">
      <strong><?= e($r['name']) ?></strong>
      <?php if ($r['name'] === 'Super Admin'): ?>
        <span class="badge badge-confirmed">Full access (not editable)</span>
      <?php else: ?>
        <form method="post" action="" style="margin-top:10px">
          <input type="hidden" name="save_role" value="1">
          <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
          <div style="display:flex;gap:14px;flex-wrap:wrap">
            <?php foreach (PERMISSIONS as $pk => $pl): ?>
            <label style="font-size:13px"><input type="checkbox" name="perms[]" value="<?= e($pk) ?>" <?= in_array($pk, $rp, true) ? 'checked' : '' ?>> <?= e($pl) ?></label>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-sm" type="submit" style="margin-top:10px">Save Permissions</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
