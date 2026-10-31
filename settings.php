<?php
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk', 'patient']);
$err = ['name' => [], 'username' => [], 'password' => []]; $ok = ['name' => false, 'username' => false, 'password' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $do = $_POST['do'] ?? ''; $cur = $_POST['current'] ?? '';
  $q = db()->prepare('SELECT password_hash FROM users WHERE id = ?'); $q->execute([$u['id']]);
  $okPw = password_verify($cur, (string)$q->fetchColumn());
  if (!csrf_ok($_POST['csrf'] ?? null)) $err[in_array($do, ['name', 'username']) ? $do : 'password'][] = 'Session expired. Please try again.';
  elseif ($do === 'username') {
    $new = strtolower(trim($_POST['username'] ?? ''));
    if (!$okPw) $err['username'][] = 'Current password is incorrect.';
    if (!valid_username($new)) $err['username'][] = 'Username must be 3-30 characters: letters, numbers, dot or underscore.';
    elseif ($new === $u['username']) $err['username'][] = 'That is already your username.';
    else { $t = db()->prepare('SELECT 1 FROM users WHERE username = ? AND id <> ?'); $t->execute([$new, $u['id']]);
           if ($t->fetchColumn()) $err['username'][] = 'That username is already taken.'; }
    if (!$err['username']) {
      db()->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$new, $u['id']]);
      log_action($u['id'], 'username_changed', $u['username'] . ' -> ' . $new);
      $_SESSION['user']['username'] = $u['username'] = $new; $ok['username'] = true;
    }
  } elseif ($do === 'name' && $u['role'] !== 'patient') {
    $n = array_map(fn($k) => trim($_POST[$k] ?? ''), ['first_name', 'middle_name', 'last_name']);
    if (!valid_name($n[0]) || !valid_name($n[2]) || ($n[1] !== '' && !valid_name($n[1]))) $err['name'][] = 'Enter a valid first name and surname (middle name is optional).';
    else {
      db()->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ? WHERE id = ?')->execute([$n[0], $n[1] ?: null, $n[2], $u['id']]);
      log_action($u['id'], 'name_changed');
      $_SESSION['user']['name'] = $u['name'] = compose_name($n[0], $n[1], $n[2]); $ok['name'] = true;
    }
  } elseif ($do === 'password') {
    $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
    if (!$okPw) $err['password'][] = 'Current password is incorrect.';
    if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $err['password'][] = 'New password needs at least 8 characters, with a letter and a number.';
    if ($new !== $conf) $err['password'][] = 'New password and confirmation do not match.';
    if ($new === $cur) $err['password'][] = 'New password must be different from the current one.';
    if (!$err['password']) {
      db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
      log_action($u['id'], 'password_changed'); session_regenerate_id(true); $ok['password'] = true;
    }
  }
}
$nm = db()->prepare('SELECT first_name, middle_name, last_name FROM users WHERE id = ?'); $nm->execute([$u['id']]); $nm = $nm->fetch();
function alerts(array $errs, bool $done, string $msg): void {
  if ($done) echo '<div class="alert alert-success py-2">' . e($msg) . '</div>';
  foreach ($errs as $x) echo '<div class="alert alert-danger py-2">' . e($x) . '</div>';
}
shell_start($u, 'Settings'); ?>
<div class="card-soft mb-4" style="max-width:480px;height:auto"><h5>Account</h5>
  <div class="text-secondary small">Name</div><div class="mb-2"><?= e($u['name']) ?></div>
  <div class="text-secondary small">Role</div><div><?= e(ROLE_LABELS[$u['role']]) ?></div></div>

<div class="card-soft mb-4" style="max-width:480px;height:auto"><h5>Name</h5>
<?php if ($u['role'] === 'patient'): ?>
  <p class="mb-0 text-secondary">Your name comes from your clinic record. To correct it, please ask the front desk.</p>
<?php else: alerts($err['name'], $ok['name'], 'Name updated.'); ?>
<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="do" value="name">
  <div class="mb-3"><label class="form-label" for="f1">First name</label><input id="f1" name="first_name" class="form-control" required value="<?= e($nm['first_name']) ?>"></div>
  <div class="mb-3"><label class="form-label" for="f2">Middle name <span class="text-secondary">(optional)</span></label><input id="f2" name="middle_name" class="form-control" value="<?= e((string)$nm['middle_name']) ?>"></div>
  <div class="mb-3"><label class="form-label" for="f3">Surname</label><input id="f3" name="last_name" class="form-control" required value="<?= e($nm['last_name']) ?>"></div>
  <button class="btn btn-primary">Update name</button>
</form>
<?php endif; ?></div>

<div class="card-soft mb-4" style="max-width:480px;height:auto"><h5>Username</h5>
<?php alerts($err['username'], $ok['username'], 'Username updated. Use it the next time you sign in.'); ?>
<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="do" value="username">
  <div class="mb-3"><label class="form-label" for="un">Username</label><input id="un" name="username" class="form-control" required autocapitalize="none" value="<?= e($u['username']) ?>">
    <div class="form-text">3-30 characters: letters, numbers, dot or underscore.</div></div>
  <div class="mb-3"><label class="form-label" for="uc">Current password</label><input id="uc" name="current" type="password" class="form-control" required autocomplete="current-password"></div>
  <button class="btn btn-primary">Update username</button>
</form></div>

<div class="card-soft" style="max-width:480px;height:auto"><h5>Change password</h5>
<?php alerts($err['password'], $ok['password'], 'Password updated.'); ?>
<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="do" value="password">
  <div class="mb-3"><label class="form-label" for="c">Current password</label><input id="c" name="current" type="password" class="form-control" required autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label" for="n">New password</label><input id="n" name="new" type="password" class="form-control" required autocomplete="new-password">
    <div class="form-text">At least 8 characters, with a letter and a number.</div></div>
  <div class="mb-4"><label class="form-label" for="f">Confirm new password</label><input id="f" name="confirm" type="password" class="form-control" required autocomplete="new-password"></div>
  <button class="btn btn-primary">Update password</button>
</form></div>
<?php shell_end();
