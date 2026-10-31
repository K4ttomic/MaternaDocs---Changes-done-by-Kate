<?php
// Add or edit a patient (admin & front desk).
// Every entry is checked on the server BEFORE it is saved:
// required fields, date logic, value ranges, ID/phone formats, and duplicate detection.
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk']);

// Limits we validate against (easy to change in one place)
const MIN_AGE = 10;       // youngest patient age accepted
const MAX_AGE = 60;       // oldest patient age accepted
const MAX_PREGNANCY_DAYS = 300;  // EDD can be at most this many days after LMP
const MAX_COUNT = 20;     // highest allowed gravida / para

$fields = ['first_name', 'middle_name', 'last_name', 'birthdate', 'civil_status', 'philhealth_no',
           'address', 'contact_no', 'emergency_contact_name', 'emergency_contact_no',
           'lmp', 'edd', 'gravida', 'para'];
$civilOptions = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'other' => 'Other'];

/* ---------- small helper functions ---------- */

// true if $s is a real date written as YYYY-MM-DD (rejects 2026-02-31)
function valid_date(string $s): bool {
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  return $dt && $dt->format('Y-m-d') === $s;
}

// Accepts 09171234567 or +639171234567 (spaces/dashes allowed). Returns 09XXXXXXXXX, or null if invalid.
function clean_phone(string $s): ?string {
  $s = preg_replace('/[\s\-()]/', '', $s);
  if (strpos($s, '+63') === 0) $s = '0' . substr($s, 3);
  return preg_match('/^09\d{9}$/', $s) ? $s : null;
}

// empty string -> NULL, so the database stores a real "no value"
function nn(string $v): ?string { return $v === '' ? null : $v; }

// Checks all fields. Returns an array of errors like ['birthdate' => 'message'].
// $d is passed by reference so cleaned values (phone, PhilHealth no.) are saved in the clean format.
function validate_patient(array &$d, int $id): array {
  global $civilOptions;
  $err = [];
  $today = date('Y-m-d');

  // 1. Names
  if (!valid_name($d['first_name'])) $err['first_name'] = 'Enter a valid first name (letters only).';
  if ($d['middle_name'] !== '' && !valid_name($d['middle_name'])) $err['middle_name'] = 'Middle name has invalid characters.';
  if (!valid_name($d['last_name'])) $err['last_name'] = 'Enter a valid surname (letters only).';

  // 2. Birthdate: required, real date, not in the future, sensible age
  if ($d['birthdate'] === '') $err['birthdate'] = 'Birthdate is required.';
  elseif (!valid_date($d['birthdate'])) $err['birthdate'] = 'Enter a valid date.';
  elseif ($d['birthdate'] > $today) $err['birthdate'] = 'Birthdate cannot be in the future.';
  else {
    $age = (new DateTime($d['birthdate']))->diff(new DateTime($today))->y;
    if ($age < MIN_AGE || $age > MAX_AGE) $err['birthdate'] = "This makes the patient $age years old. Accepted age is " . MIN_AGE . '-' . MAX_AGE . '. Please check the date.';
  }

  // 3. Civil status (optional, but must be one of the choices)
  if ($d['civil_status'] !== '' && !isset($civilOptions[$d['civil_status']])) $err['civil_status'] = 'Choose a civil status from the list.';

  // 4. PhilHealth number: 12 digits, saved as XX-XXXXXXXXX-X
  if ($d['philhealth_no'] !== '') {
    $digits = preg_replace('/\D/', '', $d['philhealth_no']);
    if (!preg_match('/^[\d\- ]+$/', $d['philhealth_no']) || strlen($digits) !== 12) {
      $err['philhealth_no'] = 'PhilHealth number must be 12 digits (example: 01-234567890-1).';
    } else {
      $d['philhealth_no'] = substr($digits, 0, 2) . '-' . substr($digits, 2, 9) . '-' . substr($digits, 11, 1);
    }
  }

  // 5. Address length
  if (strlen($d['address']) > 255) $err['address'] = 'Address is too long (255 characters max).';

  // 6. Contact numbers
  if ($d['contact_no'] !== '') {
    $p = clean_phone($d['contact_no']);
    if ($p === null) $err['contact_no'] = 'Enter a mobile number like 09171234567.';
    else $d['contact_no'] = $p;
  }
  if ($d['emergency_contact_no'] !== '') {
    $p = clean_phone($d['emergency_contact_no']);
    if ($p === null) $err['emergency_contact_no'] = 'Enter a mobile number like 09171234567.';
    else $d['emergency_contact_no'] = $p;
  }

  // 7. Emergency contact: give both name and number, or neither
  if ($d['emergency_contact_name'] !== '' && !preg_match('/^\p{L}[\p{L} .\x27’-]{0,149}$/u', $d['emergency_contact_name'])) $err['emergency_contact_name'] = 'Name has invalid characters.';
  if ($d['emergency_contact_name'] !== '' && $d['emergency_contact_no'] === '') $err['emergency_contact_no'] = 'Add a number for this emergency contact.';
  if ($d['emergency_contact_no'] !== '' && $d['emergency_contact_name'] === '') $err['emergency_contact_name'] = 'Add a name for this emergency contact.';

  // 8. LMP (last menstrual period): real date, not in the future, after her birthdate
  if ($d['lmp'] !== '') {
    if (!valid_date($d['lmp'])) $err['lmp'] = 'Enter a valid date.';
    elseif ($d['lmp'] > $today) $err['lmp'] = 'LMP cannot be in the future.';
    elseif (!isset($err['birthdate']) && $d['birthdate'] !== '' && $d['lmp'] <= $d['birthdate']) $err['lmp'] = 'LMP must be after the birthdate.';
  }

  // 9. EDD (expected delivery date): must come after LMP, and not unreasonably far after it
  if ($d['edd'] !== '') {
    if (!valid_date($d['edd'])) $err['edd'] = 'Enter a valid date.';
    elseif ($d['lmp'] !== '' && !isset($err['lmp'])) {
      $days = (strtotime($d['edd']) - strtotime($d['lmp'])) / 86400;
      if ($days <= 0) $err['edd'] = 'EDD must be after the LMP.';
      elseif ($days > MAX_PREGNANCY_DAYS) $err['edd'] = 'EDD is more than ' . MAX_PREGNANCY_DAYS . ' days after the LMP. Please check both dates.';
    }
  }

  // 10. Gravida / para: whole numbers 0-20, and para cannot be more than gravida
  foreach (['gravida' => 'Gravida', 'para' => 'Para'] as $k => $label) {
    if ($d[$k] !== '' && (!ctype_digit($d[$k]) || (int)$d[$k] > MAX_COUNT)) $err[$k] = "$label must be a whole number from 0 to " . MAX_COUNT . '.';
  }
  if (!isset($err['gravida']) && !isset($err['para']) && $d['gravida'] !== '' && $d['para'] !== '' && (int)$d['para'] > (int)$d['gravida']) {
    $err['para'] = 'Para cannot be more than gravida.';
  }

  // 11. Duplicate detection (only runs if the fields it uses are already valid)
  if (!array_intersect_key($err, ['first_name' => 1, 'last_name' => 1, 'birthdate' => 1])) {
    $st = db()->prepare('SELECT id FROM patients WHERE id <> ? AND first_name = ? AND last_name = ? AND birthdate = ?');
    $st->execute([$id, $d['first_name'], $d['last_name'], $d['birthdate']]);
    $dup = $st->fetchColumn();
    if ($dup) $err['last_name'] = "A patient with the same name and birthdate already exists (record #$dup). Search the list before adding again.";
  }
  if (!isset($err['philhealth_no']) && $d['philhealth_no'] !== '') {
    $st = db()->prepare('SELECT id FROM patients WHERE id <> ? AND philhealth_no = ?');
    $st->execute([$id, $d['philhealth_no']]);
    $dup = $st->fetchColumn();
    if ($dup) $err['philhealth_no'] = "This PhilHealth number already belongs to another patient (record #$dup).";
  }

  return $err;
}

// Prints one label + input + error message (keeps the form below short)
function input_field(string $name, string $label, array $d, array $err, string $type = 'text', string $hint = '', string $extra = ''): void {
  $bad = isset($err[$name]);
  echo '<label class="form-label" for="' . $name . '">' . e($label) . '</label>';
  echo '<input id="' . $name . '" name="' . $name . '" type="' . $type . '" class="form-control' . ($bad ? ' is-invalid' : '') . '" value="' . e($d[$name]) . '" ' . $extra . '>';
  if ($bad) echo '<div class="invalid-feedback">' . e($err[$name]) . '</div>';
  elseif ($hint !== '') echo '<div class="form-text">' . e($hint) . '</div>';
}

/* ---------- load the patient (edit) or start blank (add) ---------- */

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$data = array_fill_keys($fields, '');

if ($isEdit) {
  $st = db()->prepare('SELECT * FROM patients WHERE id = ?');
  $st->execute([$id]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); exit('404 – Patient not found.'); }
  foreach ($fields as $f) $data[$f] = (string)$row[$f];
}

/* ---------- handle the form being submitted ---------- */

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $err['form'] = 'Session expired. Please try again.';
  } else {
    $err = validate_patient($data, $id);
  }

  if (!$err) {
    // If LMP is given but EDD is empty, fill EDD using Naegele's rule (LMP + 280 days)
    if ($data['lmp'] !== '' && $data['edd'] === '') {
      $data['edd'] = date('Y-m-d', strtotime($data['lmp'] . ' +280 days'));
    }

    $values = [$data['first_name'], nn($data['middle_name']), $data['last_name'], $data['birthdate'],
               nn($data['civil_status']), nn($data['philhealth_no']), nn($data['address']), nn($data['contact_no']),
               nn($data['emergency_contact_name']), nn($data['emergency_contact_no']),
               nn($data['lmp']), nn($data['edd']), nn($data['gravida']), nn($data['para'])];

    $pdo = db();
    $pdo->beginTransaction();
    if ($isEdit) {
      $pdo->prepare('UPDATE patients SET first_name=?, middle_name=?, last_name=?, birthdate=?, civil_status=?, philhealth_no=?,
          address=?, contact_no=?, emergency_contact_name=?, emergency_contact_no=?, lmp=?, edd=?, gravida=?, para=? WHERE id=?')
          ->execute(array_merge($values, [$id]));
      // keep the patient's portal account name in sync
      $pdo->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id = (SELECT user_id FROM patients WHERE id=?)')
          ->execute([$data['first_name'], nn($data['middle_name']), $data['last_name'], $id]);
      log_action($u['id'], 'patient_updated', "patient #$id");
      $_SESSION['flash'] = 'Patient record updated.';
    } else {
      $pdo->prepare('INSERT INTO patients (first_name, middle_name, last_name, birthdate, civil_status, philhealth_no,
          address, contact_no, emergency_contact_name, emergency_contact_no, lmp, edd, gravida, para, created_by)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
          ->execute(array_merge($values, [$u['id']]));
      $newId = (int)$pdo->lastInsertId();
      log_action($u['id'], 'patient_created', "patient #$newId");
      $_SESSION['flash'] = 'Patient added.';
    }
    $pdo->commit();
    header('Location: patients.php'); exit;
  }
}

/* ---------- show the page ---------- */

shell_start($u, $isEdit ? 'Edit Patient' : 'Add Patient'); ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err['form'] ?? 'Please fix the highlighted fields and try again.') ?></div><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="card-soft mb-4" style="height:auto"><h5 class="mb-3">Personal details</h5>
    <div class="row g-3">
      <div class="col-md-4"><?php input_field('first_name', 'First name', $data, $err, 'text', '', 'required'); ?></div>
      <div class="col-md-4"><?php input_field('middle_name', 'Middle name (optional)', $data, $err); ?></div>
      <div class="col-md-4"><?php input_field('last_name', 'Surname', $data, $err, 'text', '', 'required'); ?></div>
      <div class="col-md-4"><?php input_field('birthdate', 'Birthdate', $data, $err, 'date', '', 'required max="' . date('Y-m-d') . '"'); ?></div>
      <div class="col-md-4">
        <label class="form-label" for="civil_status">Civil status (optional)</label>
        <select id="civil_status" name="civil_status" class="form-select<?= isset($err['civil_status']) ? ' is-invalid' : '' ?>">
          <option value="">Select...</option>
          <?php foreach ($civilOptions as $k => $label): ?>
            <option value="<?= $k ?>" <?= $data['civil_status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($err['civil_status'])): ?><div class="invalid-feedback"><?= e($err['civil_status']) ?></div><?php endif; ?>
      </div>
      <div class="col-md-4"><?php input_field('philhealth_no', 'PhilHealth number (optional)', $data, $err, 'text', '12 digits, e.g. 01-234567890-1'); ?></div>
    </div>
  </div>

  <div class="card-soft mb-4" style="height:auto"><h5 class="mb-3">Contact</h5>
    <div class="row g-3">
      <div class="col-md-8"><?php input_field('address', 'Address (optional)', $data, $err); ?></div>
      <div class="col-md-4"><?php input_field('contact_no', 'Mobile number (optional)', $data, $err, 'text', 'e.g. 09171234567'); ?></div>
      <div class="col-md-8"><?php input_field('emergency_contact_name', 'Emergency contact name (optional)', $data, $err); ?></div>
      <div class="col-md-4"><?php input_field('emergency_contact_no', 'Emergency contact number', $data, $err, 'text', 'e.g. 09171234567'); ?></div>
    </div>
  </div>

  <div class="card-soft mb-4" style="height:auto"><h5 class="mb-3">Pregnancy</h5>
    <div class="row g-3">
      <div class="col-md-3"><?php input_field('lmp', 'LMP (last menstrual period)', $data, $err, 'date', '', 'max="' . date('Y-m-d') . '"'); ?></div>
      <div class="col-md-3"><?php input_field('edd', 'EDD (expected delivery date)', $data, $err, 'date', 'Leave blank to compute from LMP'); ?></div>
      <div class="col-md-3"><?php input_field('gravida', 'Gravida', $data, $err, 'number', 'Number of pregnancies', 'min="0" max="' . MAX_COUNT . '"'); ?></div>
      <div class="col-md-3"><?php input_field('para', 'Para', $data, $err, 'number', 'Number of deliveries', 'min="0" max="' . MAX_COUNT . '"'); ?></div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Add patient' ?></button>
    <a href="patients.php" class="btn btn-outline-primary">Cancel</a>
  </div>
</form>
<?php shell_end();
