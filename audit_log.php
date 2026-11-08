<?php
/**
 * audit_log.php
 * Administrator Audit Log Viewer.
 * Displays all logged actions across the system with filtering and search.
 */
require 'includes/auth.php';
$u = require_role(['admin']);

$pdo = db();

// Helper to format audit actions
function audit_meta(string $act): array {
    return match ($act) {
        'login'                        => ['Signed In', 'bi-box-arrow-in-right', 'bg-success-subtle text-success border border-success-subtle'],
        'logout'                       => ['Signed Out', 'bi-box-arrow-right', 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
        'patient_created'              => ['New Patient Registered', 'bi-person-plus-fill', 'bg-primary-subtle text-primary border border-primary-subtle'],
        'patient_updated'              => ['Patient Record Updated', 'bi-person-check-fill', 'bg-info-subtle text-info-emphasis border border-info-subtle'],
        'health_record_updated'        => ['Health Record Saved', 'bi-file-earmark-medical-fill', 'bg-success-subtle text-success border border-success-subtle'],
        'health_record_pdf_generated'  => ['Health Record PDF Generated', 'bi-file-earmark-pdf-fill', 'bg-danger-subtle text-danger border border-danger-subtle'],
        'health_record_pdf_downloaded' => ['PDF Downloaded', 'bi-download', 'bg-success-subtle text-success border border-success-subtle'],
        'user_created'                 => ['User Account Created', 'bi-person-gear', 'bg-primary-subtle text-primary border border-primary-subtle'],
        'user_status_changed'          => ['User Status Changed', 'bi-toggle-on', 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
        'password_reset'               => ['Password Reset', 'bi-key-fill', 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
        'account_unlocked'             => ['Account Unlocked', 'bi-unlock-fill', 'bg-success-subtle text-success border border-success-subtle'],
        default                        => [ucwords(str_replace('_', ' ', $act)), 'bi-activity', 'bg-light text-dark border'],
    };
}

// ── Filters & Search ───────────────────────────────────────────────────────
$actionFilter = trim($_GET['action'] ?? '');
$search       = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($actionFilter !== '') {
    $where[] = 'a.action = ?';
    $params[] = $actionFilter;
}

if ($search !== '') {
    $where[] = '(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR a.detail LIKE ? OR a.ip LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countSt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id $whereSql");
$countSt->execute($params);
$totalLogs = (int)$countSt->fetchColumn();

// Fetch records (limit 150)
$st = $pdo->prepare("
    SELECT a.id, a.user_id, a.action, a.detail, a.ip, a.created_at,
           u.username, u.first_name, u.last_name, u.role
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    $whereSql
    ORDER BY a.id DESC
    LIMIT 150
");
$st->execute($params);
$logs = $st->fetchAll();

// Distinct actions for dropdown
$actionsSt = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action ASC');
$availableActions = $actionsSt->fetchAll(PDO::FETCH_COLUMN);

shell_start($u, 'Audit Log');
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0">System Activity Log</h4>
        <span class="text-secondary small">
            Showing <?= count($logs) ?> of <?= number_format($totalLogs) ?> recorded events
        </span>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-soft mb-4" style="height:auto">
    <form method="get" class="row g-2 align-items-center" novalidate>
        <div class="col-md-5">
            <input name="q" class="form-control form-control-sm"
                   placeholder="Search username, details, or IP address…"
                   value="<?= e($search) ?>" autofocus>
        </div>
        <div class="col-md-4">
            <select name="action" class="form-select form-select-sm">
                <option value="">All Actions</option>
                <?php foreach ($availableActions as $act):
                    [$label] = audit_meta($act);
                ?>
                    <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>>
                        <?= e($label) ?> (<?= e($act) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i> Filter</button>
            <?php if ($search !== '' || $actionFilter !== ''): ?>
                <a href="audit_log.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="card-soft" style="height:auto">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="small text-secondary">
                    <th style="width:170px">Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Detail</th>
                    <th style="width:130px">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l):
                    [$label, $icon, $badgeClass] = audit_meta($l['action']);
                    $userName = $l['username']
                        ? compose_name($l['first_name'], null, $l['last_name']) . ' (' . $l['username'] . ')'
                        : 'System / Guest';
                    $roleLabel = $l['role'] ? (ROLE_LABELS[$l['role']] ?? $l['role']) : null;
                    $dt = new DateTime($l['created_at']);
                ?>
                    <tr>
                        <td class="small text-secondary" style="white-space:nowrap">
                            <div><?= $dt->format('M d, Y') ?></div>
                            <div class="text-muted" style="font-size:0.75rem"><?= $dt->format('h:i:s A') ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold small"><?= e($userName) ?></div>
                            <?php if ($roleLabel): ?>
                                <span class="badge bg-light text-secondary border" style="font-size:0.7rem">
                                    <?= e($roleLabel) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?> d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size:0.75rem">
                                <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
                            </span>
                        </td>
                        <td class="small text-secondary">
                            <?= e((string)$l['detail']) ?: '—' ?>
                        </td>
                        <td class="small text-muted font-monospace" style="font-size:0.8rem">
                            <?= e((string)$l['ip']) ?: '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="5" class="text-secondary text-center py-4">
                            No audit log records match your search criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php shell_end();

