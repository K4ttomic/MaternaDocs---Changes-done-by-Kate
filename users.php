<?php
require 'includes/auth.php';
$u = require_role(['admin']);

function temp_password(): string {
  $c = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; $p = '';
  for ($i = 0; $i < 10; $i++) $p .= $c[random_int(0, strlen($c) - 1)];
  return $p . '7';
}
function flash(string $msg, ?string $pw = null, string $type = 'success'): void { $_SESSION['flash'] = compact('msg', 'pw', 'type'); header('Location: users.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok($_POST['csrf'] ?? null)) flash('Session expired. Please try again.', null, 'danger');
  $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
  if ($act === 'create') {
    [$fn, $mn, $ln] = [trim($_POST['first_name'] ?? ''), trim($_POST['middle_name'] ?? ''), trim($_POST['last_name'] ?? '')]; $username = strtolower(trim($_POST['username'] ?? '')); $role = $_POST['role'] ?? '';
    if (!valid_name($fn) || !valid_name($ln) || ($mn !== '' && !valid_name($mn)) || !valid_username($username) || !isset(ROLE_LABELS[$role])) flash('Enter a first and last name (middle name optional), a role and a username (3-30 characters: letters, numbers, dot or underscore).', null, 'danger');
    $pw = temp_password();
    try {
      db()->prepare('INSERT INTO users (username, password_hash, first_name, middle_name, last_name, role) VALUES (?,?,?,?,?,?)')
          ->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $fn, $mn ?: null, $ln, $role]);
    } catch (PDOException $e) { flash($e->getCode() === '23000' ? 'That username is already taken.' : 'Could not create the account.', null, 'danger'); }
    log_action($u['id'], 'user_created', $username . ' (' . $role . ')');
    flash("Account created for $username. Give them this temporary password (shown once):", $pw);
  }
  if ($id === $u['id']) flash('Use Settings to change your own password, and you cannot deactivate yourself.', null, 'danger');
  if ($act === 'reset') {
    $pw = temp_password();
    db()->prepare('UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
    log_action($u['id'], 'password_reset', "user #$id");
    flash('Password reset. Temporary password (shown once):', $pw);
  }
  if ($act === 'toggle') {
    db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    log_action($u['id'], 'user_toggled', "user #$id"); flash('Account status updated.');
  }
  if ($act === 'unlock') {
    db()->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?')->execute([$id]);
    log_action($u['id'], 'user_unlocked', "user #$id"); flash('Account unlocked.');
  }
}

$fl = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$users = db()->query('SELECT id, first_name, middle_name, last_name, username, role, is_active, (locked_until IS NOT NULL AND locked_until > NOW()) AS locked, last_login_at FROM users ORDER BY role, last_name, first_name')->fetchAll();
function act(string $a, int $id, string $label, string $cls = 'outline-primary'): void { ?>
  <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= $id ?>">
  <button class="btn btn-sm btn-<?= $cls ?>" <?= $a === 'reset' ? 'onclick="return confirm(\'Reset this password?\')"' : '' ?>><?= $label ?></button></form>
<?php }
shell_start($u, 'User Accounts'); ?>
<?php if ($fl): ?><div class="alert alert-<?= $fl['type'] ?>"><?= e($fl['msg']) ?><?php if ($fl['pw']): ?> <code class="fs-5 user-select-all"><?= e($fl['pw']) ?></code><div class="small mt-1">They can change it anytime under Settings.</div><?php endif; ?></div><?php endif; ?>

<div class="card-soft mb-4" style="height:auto"><h5>Add account</h5>
<form method="post" class="row g-2 align-items-end">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="create">
  <div class="col-md-3"><label class="form-label small" for="fn">First name</label><input id="fn" name="first_name" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label small" for="mn">Middle name <span class="text-secondary">(optional)</span></label><input id="mn" name="middle_name" class="form-control"></div>
  <div class="col-md-3"><label class="form-label small" for="ln">Surname</label><input id="ln" name="last_name" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label small" for="em">Username</label><input id="em" name="username" type="text" class="form-control" required autocapitalize="none" pattern="[A-Za-z0-9._]{3,30}" title="3-30 characters: letters, numbers, dot or underscore"></div>
  <div class="col-md-3"><label class="form-label small" for="rl">Role</label><select id="rl" name="role" class="form-select">
    <?php foreach (ROLE_LABELS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
</form></div>

<div class="card-soft" style="height:auto"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($users as $r): $me = (int)$r['id'] === $u['id']; ?>
<tr><td><?= e(compose_name($r['first_name'], $r['middle_name'], $r['last_name'])) ?><div class="small text-secondary"><?= e($r['username']) ?></div></td>
  <td><span class="role-pill"><?= e(ROLE_LABELS[$r['role']]) ?></span></td>
  <td><?= $r['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-secondary">Inactive</span>' ?><?= $r['locked'] ? ' <span class="badge text-bg-warning">Locked</span>' : '' ?></td>
  <td class="small text-secondary"><?= $r['last_login_at'] ? e($r['last_login_at']) : 'Never' ?></td>
  <td class="text-end text-nowrap"><?php if ($me): ?><span class="small text-secondary">You</span><?php else:
    if ($r['locked']) act('unlock', (int)$r['id'], 'Unlock', 'warning');
    act('reset', (int)$r['id'], 'Reset password');
    act('toggle', (int)$r['id'], $r['is_active'] ? 'Deactivate' : 'Activate', $r['is_active'] ? 'outline-danger' : 'outline-primary');
  endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php shell_end();
