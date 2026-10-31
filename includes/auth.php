<?php
// Session + RBAC helpers. Users live in MySQL (see database/schema.sql).
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/db.php';

const ROLE_LABELS = ['admin' => 'Administrator', 'front_desk' => 'Front Desk Staff', 'patient' => 'Patient'];

// Sidebar per role (matches the proposal's modules).
const NAV = [  // [label, icon, href or null if not built yet]
  'admin' => [
    ['Dashboard', 'bi-grid', 'dashboard.php'], ['Patient Records', 'bi-people', 'patients.php'],
    ['Forms & Documents', 'bi-file-earmark-text', null], ['Templates', 'bi-files', null],
    ['User Accounts', 'bi-person-gear', 'users.php'], ['Audit Log', 'bi-shield-check', null],
  ],
  'front_desk' => [
    ['Dashboard', 'bi-grid', 'dashboard.php'], ['Patient Records', 'bi-people', 'patients.php'],
    ['Forms & Documents', 'bi-file-earmark-text', null],
  ],
  'patient' => [
    ['My Records', 'bi-heart-pulse', 'dashboard.php'], ['My Documents', 'bi-download', null],
  ],
];

function log_action(?int $userId, string $action, ?string $detail = null): void {
  db()->prepare('INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?,?,?,?)')
      ->execute([$userId, $action, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_role(array $roles): array {
  $u = current_user();
  if (!$u) { header('Location: login.php'); exit; }
  if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('403 – You do not have access to this page.'); }
  return $u;
}

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_ok(?string $t): bool { return $t && hash_equals($_SESSION['csrf'] ?? '', $t); }
function valid_username(string $s): bool { return (bool)preg_match('/^[a-z0-9._]{3,30}$/', $s); }
function valid_name(string $s): bool { return (bool)preg_match('/^\p{L}[\p{L} .\x27’-]{0,79}$/u', $s); }
function compose_name(?string $f, ?string $m, ?string $l): string { return trim(preg_replace('/\s+/', ' ', "$f $m $l")); }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function page_head(string $title): void { ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · MaternaDocs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head><body>
<?php }
function page_foot(): void { ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/hidden.js"></script>
</body></html>
<?php }

// Shared logged-in layout. Pages call shell_start($u, 'Title') ... shell_end().
function shell_start(array $u, string $title): void {
  $cur = basename($_SERVER['SCRIPT_NAME']);
  page_head($title); ?>
<div class="d-flex shell">
  <aside class="side">
    <a class="brand d-block mb-4 px-2" href="index.php"><i class="bi bi-heart-pulse-fill"></i> MaternaDocs</a>
    <?php foreach (NAV[$u['role']] as [$label, $icon, $href]): ?>
      <a class="item <?= $href === $cur ? 'active' : ($href ? '' : 'soon') ?>" href="<?= $href ?? '#' ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?>
        <?php if (!$href): ?><small class="ms-auto">soon</small><?php endif; ?></a>
    <?php endforeach; ?>
  </aside>
  <main class="flex-grow-1 p-4 p-lg-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div><h2 class="mb-1"><?= e($title) ?></h2><span class="role-pill"><?= e(ROLE_LABELS[$u['role']]) ?></span></div>
      <div class="d-flex align-items-center gap-2"><span class="text-secondary d-none d-sm-inline me-1"><?= e($u['name']) ?></span>
        <a href="settings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i> Settings</a>
        <a href="logout.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-right"></i> Sign out</a></div>
    </div>
<?php }
function shell_end(): void { ?>
  </main>
</div>
<?php page_foot(); }
