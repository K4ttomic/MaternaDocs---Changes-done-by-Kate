<?php
// Patient list + search (admin & front desk).
// Adding and editing a patient happens in patient_form.php.
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk']);

// message left by patient_form.php after a save
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

// search by first name, surname or PhilHealth number
$q = trim($_GET['q'] ?? '');
$like = '%' . addcslashes($q, '%_\\') . '%';
$st = db()->prepare("SELECT p.id, p.first_name, p.middle_name, p.last_name, p.birthdate, p.philhealth_no, p.contact_no, p.edd, u.username
  FROM patients p LEFT JOIN users u ON u.id = p.user_id
  WHERE (? = '' OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.philhealth_no LIKE ?)
  ORDER BY p.last_name, p.first_name LIMIT 100");
$st->execute([$q, $like, $like, $like]);
$rows = $st->fetchAll();

shell_start($u, 'Patient Records'); ?>
<?php if ($flash): ?><div class="alert alert-success py-2"><?= e($flash) ?></div><?php endif; ?>

<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
  <form class="d-flex gap-2" method="get" style="max-width:460px;flex:1">
    <input name="q" class="form-control" placeholder="Search name or PhilHealth number" value="<?= e($q) ?>">
    <button class="btn btn-primary">Search</button>
  </form>
  <a href="patient_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add patient</a>
</div>

<div class="card-soft" style="height:auto"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Name</th><th>Birthdate</th><th>PhilHealth no.</th><th>Mobile</th><th>EDD</th><th>Portal username</th><th class="text-end"></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= e(compose_name($r['first_name'], $r['middle_name'], $r['last_name'])) ?></td>
  <td><?= e($r['birthdate']) ?></td>
  <td><?= e((string)$r['philhealth_no']) ?: '—' ?></td>
  <td><?= e((string)$r['contact_no']) ?: '—' ?></td>
  <td><?= e((string)$r['edd']) ?: '—' ?></td>
  <td class="text-secondary"><?= e((string)$r['username']) ?: '—' ?></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="patient_form.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-secondary">No patients found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php shell_end();
