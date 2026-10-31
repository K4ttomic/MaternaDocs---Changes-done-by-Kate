<?php
require 'includes/auth.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$portal = ($_GET['portal'] ?? $_POST['portal'] ?? 'patient') === 'staff' ? 'staff' : 'patient';
$allowed = $portal === 'patient' ? ['patient'] : ['admin', 'front_desk'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = strtolower(trim($_POST['username'] ?? ''));
  $stmt = db()->prepare('SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked FROM users WHERE username = ?');
  $stmt->execute([$username]);
  $user = $stmt->fetch();
  if (!csrf_ok($_POST['csrf'] ?? null)) $error = 'Session expired. Please try again.';
  elseif ($user && $user['is_locked']) $error = 'Too many failed attempts. Please try again in 15 minutes.';
  elseif (!$user || !$user['is_active'] || !password_verify($_POST['password'] ?? '', $user['password_hash']) || !in_array($user['role'], $allowed, true)) {
    if ($user) db()->prepare('UPDATE users SET failed_attempts = failed_attempts + 1,
        locked_until = IF(failed_attempts >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until) WHERE id = ?')->execute([$user['id']]);
    log_action($user['id'] ?? null, 'login_failed', $username);
    $error = 'Invalid credentials for this portal.'; // generic on purpose
  } else {
    db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'name' => compose_name($user['first_name'], $user['middle_name'], $user['last_name']), 'role' => $user['role']];
    log_action((int)$user['id'], 'login', $portal);
    header('Location: dashboard.php'); exit;
  }
}
page_head('Sign in'); ?>
<div class="row g-0 auth-wrap">
  <div class="col-lg-5 auth-side d-none d-lg-flex flex-column justify-content-between">
    <a class="brand" href="index.php"><i class="bi bi-heart-pulse-fill"></i> MaternaDocs</a>
    <div><h2 class="display-6">Less rewriting.<br>Fewer rejected forms.</h2>
    <p class="opacity-75 mt-3">Bahay Paanakan's patient records and document automation, in one secure place.</p></div>
    <small class="opacity-75">Authorized users only. Activity is logged.</small>
  </div>
  <div class="col-lg-7 d-flex align-items-center justify-content-center p-4">
    <div class="auth-card">
      <a class="brand d-lg-none d-block mb-4" href="index.php"><i class="bi bi-heart-pulse-fill"></i> MaternaDocs</a>
      <?php if ($portal === 'staff'): ?>
      <h2 class="mb-1">Staff sign-in</h2>
      <p class="text-secondary mb-4">Authorized clinic personnel only.</p>
      <?php else: ?>
      <h2 class="mb-1" data-secret-link="login.php?portal=staff">Welcome back</h2>
      <p class="text-secondary mb-4">Sign in to view your records.</p>
      <?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="portal" value="<?= $portal ?>">
        <div class="mb-3"><label class="form-label" for="username">Username</label>
          <input id="username" name="username" type="text" class="form-control" required autofocus autocapitalize="none" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></div>
        <div class="mb-4"><label class="form-label" for="pw">Password</label>
          <div class="input-group"><input id="pw" name="password" type="password" class="form-control" required autocomplete="current-password">
          <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
        <button class="btn btn-primary w-100 py-2">Sign in</button>
      </form>
      <p class="small text-secondary mt-3 mb-4">
        <?= $portal === 'patient' ? 'No account yet? Ask the front desk to set up your portal access.' : 'Forgot your password? Contact the clinic administrator. <a href="login.php">Patient sign-in</a>' ?></p>
          </div>
  </div>
</div>
<?php page_foot(); ?>
