<?php
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk', 'patient']);
$isPatient   = $u['role'] === 'patient';
$isAdmin     = $u['role'] === 'admin';
$isFrontDesk = $u['role'] === 'front_desk';

$pdo = db();

// ── Patient Portal: Personal Record ─────────────────────────────────────────
if ($isPatient) {
    $st = $pdo->prepare('SELECT * FROM patients WHERE user_id = ?');
    $st->execute([$u['id']]);
    $pat = $st->fetch();

    shell_start($u, 'My Records');
    ?>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat anim-fade-up anim-delay-1">
          <div class="text-secondary small">Expected Delivery Date</div>
          <div class="v fs-4"><?= $pat && $pat['edd'] ? e((new DateTime($pat['edd']))->format('M d, Y')) : '—' ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat anim-fade-up anim-delay-2">
          <div class="text-secondary small">PhilHealth Number</div>
          <div class="v fs-4"><?= $pat && $pat['philhealth_no'] ? e($pat['philhealth_no']) : '—' ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat anim-fade-up anim-delay-3">
          <div class="text-secondary small">Available Documents</div>
          <div class="v fs-4"><?= $pat ? '1 (Health Record)' : '0' ?></div>
        </div>
      </div>
    </div>

    <?php if ($pat): ?>
      <div class="card-soft mb-4" style="height:auto">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h5 class="mb-1"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Health Record During Pregnancy</h5>
            <p class="text-secondary small mb-0">
              Official birthing home document for <?= e(compose_name($pat['first_name'], $pat['middle_name'], $pat['last_name'])) ?>
            </p>
          </div>
          <div class="d-flex gap-2">
            <a href="health_record_pdf.php" target="_blank" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-eye me-1"></i> View Document
            </a>
            <a href="health_record_pdf.php?download=1" class="btn btn-primary btn-sm">
              <i class="bi bi-download me-1"></i> Download PDF
            </a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i> No patient health record is connected to your user account yet. Front desk staff can link your medical profile.
      </div>
    <?php endif; ?>
    <?php
    shell_end();
    exit;
}

// ── Admin & Staff Dashboard Metrics ─────────────────────────────────────────
$totalPatients   = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$newThisMonth    = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$dueSoonCount    = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE edd IS NOT NULL AND edd BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$docsGenerated   = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE '%pdf%'")->fetchColumn();
$activeStaff     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'front_desk' AND is_active = 1")->fetchColumn();
$philHealthCount = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE philhealth_no IS NOT NULL AND philhealth_no != ''")->fetchColumn();

// Upcoming Deliveries (within next 45 days or overdue)
$stDue = $pdo->query("
    SELECT id, first_name, middle_name, last_name, edd, contact_no, philhealth_no, DATEDIFF(edd, CURDATE()) AS days_left
    FROM patients
    WHERE edd IS NOT NULL AND edd <= DATE_ADD(CURDATE(), INTERVAL 45 DAY)
    ORDER BY edd ASC
    LIMIT 6
");
$duePatients = $stDue->fetchAll();

// Recently Registered Patients
$stRecent = $pdo->query("
    SELECT id, first_name, middle_name, last_name, edd, birthdate, created_at, philhealth_no
    FROM patients
    ORDER BY id DESC
    LIMIT 5
");
$recentPatients = $stRecent->fetchAll();

shell_start($u, 'Clinic Dashboard');
?>

<!-- ── Welcome & Quick Action Bar ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h4 class="mb-0">Clinic Overview</h4>
    <span class="text-secondary small">Local clinic time: <?= date('F j, Y') ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="patient_form.php" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus-fill me-1"></i> Add Patient
    </a>
    <a href="documents.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-file-earmark-text me-1"></i> Forms &amp; Documents
    </a>
    <?php if ($isAdmin): ?>
      <a href="audit_log.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-shield-check me-1"></i> Audit Log
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ── Stat Cards ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat anim-fade-up anim-delay-1 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-secondary small">Total Patients</div>
        <div class="v"><?= number_format($totalPatients) ?></div>
        <div class="text-secondary small"><i class="bi bi-check-circle-fill text-success me-1"></i>Registered records</div>
      </div>
      <div class="ico mb-0"><i class="bi bi-people-fill"></i></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat anim-fade-up anim-delay-2 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-secondary small">Due in 30 Days</div>
        <div class="v text-primary"><?= number_format($dueSoonCount) ?></div>
        <div class="text-secondary small"><i class="bi bi-clock-history text-primary me-1"></i>Upcoming deliveries</div>
      </div>
      <div class="ico mb-0" style="background:#e8f5e9"><i class="bi bi-heart-pulse-fill text-success"></i></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat anim-fade-up anim-delay-3 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-secondary small">New This Month</div>
        <div class="v"><?= number_format($newThisMonth) ?></div>
        <div class="text-secondary small"><i class="bi bi-calendar-event me-1"></i><?= date('F Y') ?></div>
      </div>
      <div class="ico mb-0"><i class="bi bi-calendar-check-fill"></i></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat anim-fade-up anim-delay-4 d-flex align-items-center justify-content-between">
      <div>
        <div class="text-secondary small"><?= $isAdmin ? 'Active Staff' : 'PDFs Generated' ?></div>
        <div class="v"><?= $isAdmin ? number_format($activeStaff) : number_format($docsGenerated) ?></div>
        <div class="text-secondary small">
          <i class="bi <?= $isAdmin ? 'bi-person-check text-success' : 'bi-file-earmark-check text-success' ?> me-1"></i>
          <?= $isAdmin ? 'Front desk staff' : 'Document downloads' ?>
        </div>
      </div>
      <div class="ico mb-0"><i class="bi <?= $isAdmin ? 'bi-person-badge-fill' : 'bi-file-earmark-arrow-down-fill' ?>"></i></div>
    </div>
  </div>
</div>

<!-- ── Main Dashboard Content ──────────────────────────────────────────── -->
<div class="row g-4">
  <!-- Left Column: Upcoming Deliveries & Recent Patients -->
  <div class="col-lg-8">
    <!-- Upcoming Deliveries -->
    <div class="card-soft mb-4" style="height:auto">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-calendar2-heart text-danger me-2"></i>Upcoming Deliveries (Due Soon)</h5>
        <a href="patients.php" class="small text-decoration-none">View all</a>
      </div>
      <?php if ($duePatients): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-secondary">
                <th>Patient</th>
                <th>Expected Delivery</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($duePatients as $dp):
                $days = (int)$dp['days_left'];
                $dpName = compose_name($dp['first_name'], $dp['middle_name'], $dp['last_name']);
              ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= e($dpName) ?></div>
                    <small class="text-secondary"><?= e($dp['contact_no'] ?: 'No mobile') ?></small>
                  </td>
                  <td>
                    <div><?= e((new DateTime($dp['edd']))->format('M d, Y')) ?></div>
                  </td>
                  <td>
                    <?php if ($days < 0): ?>
                      <span class="badge bg-danger badge-pulse">Overdue by <?= abs($days) ?>d</span>
                    <?php elseif ($days === 0): ?>
                      <span class="badge bg-danger badge-pulse">Due Today</span>
                    <?php elseif ($days <= 7): ?>
                      <span class="badge bg-warning text-dark badge-pulse-warn">Due in <?= $days ?>d</span>
                    <?php else: ?>
                      <span class="badge bg-light text-dark border"><?= $days ?> days left</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a href="health_record_pdf.php?id=<?= $dp['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View PDF">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="health_record_form.php?id=<?= $dp['id'] ?>" class="btn btn-sm btn-outline-success" title="Health Record">
                      <i class="bi bi-file-earmark-medical"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-secondary small mb-0 py-2">No patients with upcoming delivery dates recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- Recently Registered Patients -->
    <div class="card-soft" style="height:auto">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>Recent Patient Registrations</h5>
        <a href="patients.php" class="small text-decoration-none">All patients</a>
      </div>
      <?php if ($recentPatients): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr class="small text-secondary">
                <th>Name</th>
                <th>PhilHealth</th>
                <th>Registered</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPatients as $rp):
                $rpName = compose_name($rp['first_name'], $rp['middle_name'], $rp['last_name']);
              ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= e($rpName) ?></div>
                  </td>
                  <td>
                    <small><?= e($rp['philhealth_no'] ?: '—') ?></small>
                  </td>
                  <td>
                    <small class="text-secondary"><?= e((new DateTime($rp['created_at']))->format('M d, Y')) ?></small>
                  </td>
                  <td class="text-end">
                    <a href="health_record_form.php?id=<?= $rp['id'] ?>" class="btn btn-sm btn-outline-success" title="Health Record Form">
                      <i class="bi bi-file-earmark-medical"></i> Form
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-secondary small mb-0 py-2">No patients added yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Column: Shortcuts & Quick Insights -->
  <div class="col-lg-4">
    <!-- Quick Shortcuts Card -->
    <div class="card-soft mb-4" style="height:auto">
      <h5 class="mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Clinic Shortcuts</h5>
      <div class="d-grid gap-2">
        <a href="patient_form.php" class="btn btn-outline-primary d-flex align-items-center text-start p-2">
          <i class="bi bi-person-plus fs-5 me-2 text-primary"></i>
          <div>
            <div class="fw-semibold">Add New Patient</div>
            <div class="text-secondary small">Register mother &amp; pregnancy</div>
          </div>
        </a>
        <a href="documents.php" class="btn btn-outline-primary d-flex align-items-center text-start p-2">
          <i class="bi bi-file-earmark-pdf fs-5 me-2 text-danger"></i>
          <div>
            <div class="fw-semibold">Forms &amp; Documents</div>
            <div class="text-secondary small">Auto-fill &amp; download PDFs</div>
          </div>
        </a>
        <a href="patients.php" class="btn btn-outline-primary d-flex align-items-center text-start p-2">
          <i class="bi bi-people fs-5 me-2 text-info"></i>
          <div>
            <div class="fw-semibold">Patient Directory</div>
            <div class="text-secondary small">Search and edit records</div>
          </div>
        </a>
        <?php if ($isAdmin): ?>
          <a href="users.php" class="btn btn-outline-primary d-flex align-items-center text-start p-2">
            <i class="bi bi-person-gear fs-5 me-2 text-success"></i>
            <div>
              <div class="fw-semibold">User Accounts</div>
              <div class="text-secondary small">Staff logins, roles, resets</div>
            </div>
          </a>
          <a href="audit_log.php" class="btn btn-outline-primary d-flex align-items-center text-start p-2">
            <i class="bi bi-shield-check fs-5 me-2 text-dark"></i>
            <div>
              <div class="fw-semibold">Audit Log</div>
              <div class="text-secondary small">View system activity &amp; events</div>
            </div>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Coverage Summary Card -->
    <div class="card-soft" style="height:auto">
      <h5 class="mb-3"><i class="bi bi-clipboard2-pulse me-2 text-success"></i>Coverage Insights</h5>
      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <span class="text-secondary small">PhilHealth Verified</span>
        <span class="fw-bold"><?= number_format($philHealthCount) ?> <small class="text-secondary fw-normal">/ <?= $totalPatients ?></small></span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <span class="text-secondary small">PhilHealth Coverage</span>
        <span class="fw-bold"><?= $totalPatients > 0 ? round(($philHealthCount / $totalPatients) * 100) : 0 ?>%</span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <span class="text-secondary small">Due in Next 30 Days</span>
        <span class="fw-bold text-primary"><?= number_format($dueSoonCount) ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center pt-2">
        <span class="text-secondary small">Generated Documents</span>
        <span class="fw-bold"><?= number_format($docsGenerated) ?></span>
      </div>
    </div>
  </div>
</div>

<?php shell_end();
