<?php
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk', 'patient']);
$isPatient = $u['role'] === 'patient';
shell_start($u, $isPatient ? 'My Records' : 'Dashboard'); ?>
<div class="row g-3 mb-4">
<?php $cards = $isPatient
  ? [['Expected delivery date', '—'], ['Next visit', '—'], ['Documents available', '—']]
  : [['Registered patients', '—'], ['New this month', '—'], ['Documents generated', '—']];
foreach ($cards as [$l, $v]): ?>
  <div class="col-md-4"><div class="stat"><div class="text-secondary small"><?= $l ?></div><div class="v"><?= $v ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="card-soft"><h5><?= $isPatient ? 'Visit history' : 'Recent activity' ?></h5>
  <p class="text-secondary mb-0">Placeholder. This fills with live data once patient records are connected.</p></div>
<?php shell_end();
