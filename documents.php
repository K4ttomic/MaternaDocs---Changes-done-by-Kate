<?php
/**
 * documents.php
 * Unified Documents module for all accounts.
 *
 * - Patients: View & download ONLY their own documents (enforced by user_id).
 * - Admin & Front Desk: Search and generate/download documents for any patient.
 */
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk', 'patient']);
$isPatient = $u['role'] === 'patient';

if ($isPatient) {
    // ── Patient Portal: Only her own record ────────────────────────────────
    $st = db()->prepare('SELECT * FROM patients WHERE user_id = ?');
    $st->execute([$u['id']]);
    $pat = $st->fetch();

    shell_start($u, 'My Documents');
    ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft mb-4" style="height:auto">
                <h5 class="mb-3"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> Health Record Document</h5>
                <?php if ($pat): ?>
                    <p class="text-secondary mb-3">
                        Your birthing home health record has been filled out by clinic staff. You can view it in your browser or download it as a PDF file anytime.
                    </p>
                    <div class="border rounded p-3 bg-white d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-bold fs-6 mb-1">
                                <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                                Health Record During Pregnancy
                            </div>
                            <small class="text-secondary">
                                Patient: <?= e(compose_name($pat['first_name'], $pat['middle_name'], $pat['last_name'])) ?> ·
                                PhilHealth: <?= e($pat['philhealth_no'] ?: 'Not recorded') ?>
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="health_record_pdf.php" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> View PDF
                            </a>
                            <a href="health_record_pdf.php?download=1" class="btn btn-primary btn-sm">
                                <i class="bi bi-download me-1"></i> Download PDF
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        No medical record is linked to your user account yet. Please visit or contact our front desk staff to link your patient profile.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft anim-fade-up anim-delay-1" style="height:auto">
                <h6 class="text-secondary text-uppercase small mb-3">Privacy &amp; Data Security</h6>
                <p class="small text-secondary mb-2">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i>
                    All health records are encrypted and protected under clinic confidentiality.
                </p>
                <p class="small text-secondary mb-0">
                    You can only view documents associated with your verified identity. If you spot any incorrect information on your document, please inform our front desk staff on your next visit.
                </p>
            </div>
        </div>
    </div>
    <?php
    shell_end();
    exit;
}

// ── Admin & Front Desk: Search and manage patient documents ────────────────
$search = trim($_GET['q'] ?? '');
$results = [];

$sql = "SELECT p.id, p.first_name, p.middle_name, p.last_name, p.birthdate, p.philhealth_no, p.contact_no, p.edd, u.username
        FROM patients p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE (? = '' OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.philhealth_no LIKE ?)
        ORDER BY p.last_name, p.first_name LIMIT 100";
$like = '%' . addcslashes($search, '%_\\') . '%';
$st = db()->prepare($sql);
$st->execute([$search, $like, $like, $like]);
$results = $st->fetchAll();

shell_start($u, 'Forms & Documents');
?>

<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <form class="d-flex gap-2" method="get" style="max-width:460px;flex:1">
        <input name="q" class="form-control" placeholder="Search name or PhilHealth number…" value="<?= e($search) ?>" autofocus>
        <button class="btn btn-primary">Search</button>
    </form>
</div>

<div class="card-soft" style="height:auto">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Birthdate</th>
                    <th>PhilHealth No.</th>
                    <th>Portal User</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r):
                $fullName = compose_name($r['first_name'], $r['middle_name'], $r['last_name']);
            ?>
                <tr>
                    <td class="fw-semibold"><?= e($fullName) ?></td>
                    <td><?= e($r['birthdate']) ?></td>
                    <td><?= e((string)$r['philhealth_no']) ?: '—' ?></td>
                    <td>
                        <?php if ($r['username']): ?>
                            <span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i><?= e($r['username']) ?></span>
                        <?php else: ?>
                            <span class="text-secondary small">Unlinked</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="health_record_pdf.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-outline-primary" title="View PDF">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a href="health_record_pdf.php?id=<?= $r['id'] ?>&download=1" class="btn btn-outline-secondary" title="Download PDF directly">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <a href="health_record_form.php?id=<?= $r['id'] ?>" class="btn btn-outline-success" title="Edit Health Record details">
                                <i class="bi bi-pencil-square"></i> Edit Form
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$results): ?>
                <tr>
                    <td colspan="5" class="text-secondary py-3 text-center">
                        No patients found<?= $search !== '' ? ' matching "<strong>' . e($search) . '</strong>"' : '' ?>.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php shell_end();

