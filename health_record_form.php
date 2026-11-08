<?php
/**
 * health_record_form.php
 * Form to fill and generate Health_Record.pdf for a specific patient.
 * Accessible to admin and front_desk.
 */
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk']);

// ── Lookup options ─────────────────────────────────────────────────────────
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
$educationLevels = [
    'No formal education', 'Elementary', 'High School',
    'Vocational/Technical', 'College', 'Post-Graduate', 'Unknown',
];
$philhealthMemberships = ['Member', 'Dependent', 'Indigent', 'None', 'Unknown'];

// Fields stored in the patients table (new HR columns)
$hrFields = [
    'family_serial_no', 'blood_type', 'education_level', 'occupation',
    'spouse_first_name', 'spouse_middle_name', 'spouse_last_name',
    'spouse_blood_type', 'spouse_education_level', 'spouse_occupation',
    'philhealth_membership', 'weight_kg', 'height_cm',
];

// ── Load patient or show picker ────────────────────────────────────────────
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    $search  = trim($_GET['q'] ?? '');
    $results = [];
    if ($search !== '') {
        $st = db()->prepare(
            'SELECT id, first_name, middle_name, last_name, birthdate, philhealth_no
             FROM patients
             WHERE last_name LIKE ? OR first_name LIKE ? OR philhealth_no = ?
             ORDER BY last_name, first_name LIMIT 20'
        );
        $like = '%' . $search . '%';
        $st->execute([$like, $like, $search]);
        $results = $st->fetchAll();
    }
    shell_start($u, 'Health Record – Select Patient');
    ?>
    <p class="text-secondary mb-3">Search for a patient to fill in the Health Record form.</p>
    <form method="get" class="d-flex gap-2 mb-4" novalidate>
        <input class="form-control" style="max-width:340px" name="q"
               value="<?= e($search) ?>" placeholder="Name or PhilHealth number…" autofocus>
        <button class="btn btn-primary">Search</button>
    </form>
    <?php if ($search !== '' && empty($results)): ?>
        <p class="text-secondary">No patients found for "<strong><?= e($search) ?></strong>".</p>
    <?php endif; ?>
    <?php if ($results): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr>
                <th>Name</th><th>Birthdate</th><th>PhilHealth No.</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= e(compose_name($r['first_name'], $r['middle_name'], $r['last_name'])) ?></td>
                    <td><?= e($r['birthdate']) ?></td>
                    <td><?= e($r['philhealth_no'] ?? '—') ?></td>
                    <td>
                        <a href="health_record_form.php?id=<?= $r['id'] ?>"
                           class="btn btn-sm btn-outline-primary">Select</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
    shell_end();
    exit;
}

// ── Fetch patient row ──────────────────────────────────────────────────────
$st = db()->prepare('SELECT * FROM patients WHERE id = ?');
$st->execute([$id]);
$patient = $st->fetch();
if (!$patient) { http_response_code(404); exit('404 – Patient not found.'); }

// ── Build data array ───────────────────────────────────────────────────────
$data = [];
foreach ($hrFields as $f) $data[$f] = (string)($patient[$f] ?? '');

// ── Validation ─────────────────────────────────────────────────────────────
function valid_hr_name(string $s): bool {
    if ($s === '') return true;
    return valid_name($s);
}

function validate_hr(array &$d, array $bt, array $el, array $pm): array {
    $err = [];

    // Family serial no
    if ($d['family_serial_no'] !== '' &&
        !preg_match('/^[A-Za-z0-9\- ]{1,30}$/', $d['family_serial_no'])) {
        $err['family_serial_no'] = 'Family serial no. must be letters, digits, dashes, or spaces (max 30).';
    }

    // Blood type
    if ($d['blood_type'] !== '' && !in_array($d['blood_type'], $bt, true)) {
        $err['blood_type'] = 'Choose a valid blood type.';
    }

    // Education level
    if ($d['education_level'] !== '' && !in_array($d['education_level'], $el, true)) {
        $err['education_level'] = 'Choose a valid education level.';
    }

    // Occupation
    if ($d['occupation'] !== '' && strlen($d['occupation']) > 100) {
        $err['occupation'] = 'Occupation must be 100 characters or fewer.';
    }

    // Spouse names — both first+last required, or neither
    $hasFirst = $d['spouse_first_name'] !== '';
    $hasLast  = $d['spouse_last_name']  !== '';
    if ($hasFirst && !valid_hr_name($d['spouse_first_name'])) {
        $err['spouse_first_name'] = 'Spouse first name has invalid characters.';
    }
    if ($d['spouse_middle_name'] !== '' && !valid_hr_name($d['spouse_middle_name'])) {
        $err['spouse_middle_name'] = 'Spouse middle name has invalid characters.';
    }
    if ($hasLast && !valid_hr_name($d['spouse_last_name'])) {
        $err['spouse_last_name'] = 'Spouse surname has invalid characters.';
    }
    if ($hasFirst && !$hasLast) {
        $err['spouse_last_name'] = 'Please enter the spouse\'s surname too.';
    }
    if ($hasLast && !$hasFirst) {
        $err['spouse_first_name'] = 'Please enter the spouse\'s first name too.';
    }

    // Spouse details only allowed when spouse is named
    $hasSpouse = $hasFirst && $hasLast;
    if (!$hasSpouse) {
        if ($d['spouse_blood_type'] !== '' || $d['spouse_education_level'] !== '' || $d['spouse_occupation'] !== '') {
            if (!isset($err['spouse_first_name'])) {
                $err['spouse_first_name'] = 'Enter the spouse\'s name before filling in spouse details.';
            }
        }
    }
    if ($d['spouse_blood_type'] !== '' && !in_array($d['spouse_blood_type'], $bt, true)) {
        $err['spouse_blood_type'] = 'Choose a valid blood type for the spouse.';
    }
    if ($d['spouse_education_level'] !== '' && !in_array($d['spouse_education_level'], $el, true)) {
        $err['spouse_education_level'] = 'Choose a valid education level for the spouse.';
    }
    if ($d['spouse_occupation'] !== '' && strlen($d['spouse_occupation']) > 100) {
        $err['spouse_occupation'] = 'Spouse occupation must be 100 characters or fewer.';
    }

    // PhilHealth membership
    if ($d['philhealth_membership'] !== '' && !in_array($d['philhealth_membership'], $pm, true)) {
        $err['philhealth_membership'] = 'Choose a valid PhilHealth membership type.';
    }

    // Weight: 20–200 kg
    if ($d['weight_kg'] !== '') {
        if (!is_numeric($d['weight_kg']) || (float)$d['weight_kg'] < 20 || (float)$d['weight_kg'] > 200) {
            $err['weight_kg'] = 'Weight must be between 20 and 200 kg.';
        } else {
            $d['weight_kg'] = number_format((float)$d['weight_kg'], 2, '.', '');
        }
    }

    // Height: 50–250 cm
    if ($d['height_cm'] !== '') {
        if (!is_numeric($d['height_cm']) || (float)$d['height_cm'] < 50 || (float)$d['height_cm'] > 250) {
            $err['height_cm'] = 'Height must be between 50 and 250 cm.';
        } else {
            $d['height_cm'] = number_format((float)$d['height_cm'], 2, '.', '');
        }
    }

    return $err;
}

// ── Handle POST ────────────────────────────────────────────────────────────
$err = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $err['form'] = 'Session expired. Please try again.';
    } else {
        foreach ($hrFields as $f) $data[$f] = trim($_POST[$f] ?? '');
        $err = validate_hr($data, $bloodTypes, $educationLevels, $philhealthMemberships);

        if (!$err) {
            db()->prepare(
                'UPDATE patients SET
                    family_serial_no       = ?,
                    blood_type             = ?,
                    education_level        = ?,
                    occupation             = ?,
                    spouse_first_name      = ?,
                    spouse_middle_name     = ?,
                    spouse_last_name       = ?,
                    spouse_blood_type      = ?,
                    spouse_education_level = ?,
                    spouse_occupation      = ?,
                    philhealth_membership  = ?,
                    weight_kg              = ?,
                    height_cm              = ?
                 WHERE id = ?'
            )->execute([
                $data['family_serial_no']       ?: null,
                $data['blood_type']             ?: null,
                $data['education_level']        ?: null,
                $data['occupation']             ?: null,
                $data['spouse_first_name']      ?: null,
                $data['spouse_middle_name']     ?: null,
                $data['spouse_last_name']       ?: null,
                $data['spouse_blood_type']      ?: null,
                $data['spouse_education_level'] ?: null,
                $data['spouse_occupation']      ?: null,
                $data['philhealth_membership']  ?: null,
                $data['weight_kg']              ?: null,
                $data['height_cm']              ?: null,
                $id,
            ]);
            log_action($u['id'], 'health_record_updated', "patient #$id");
            $success = true;

            if (isset($_POST['generate_pdf'])) {
                header('Location: health_record_pdf.php?id=' . $id);
                exit;
            }
        }
    }
}

// ── UI helpers ─────────────────────────────────────────────────────────────
$patientName = compose_name($patient['first_name'], $patient['middle_name'], $patient['last_name']);

function select_field(string $name, string $label, array $options, array $d, array $err): void {
    $bad = isset($err[$name]);
    echo '<label class="form-label" for="' . $name . '">' . e($label) . '</label>';
    echo '<select id="' . $name . '" name="' . $name . '" class="form-select' . ($bad ? ' is-invalid' : '') . '">';
    echo '<option value="">— select —</option>';
    foreach ($options as $opt) {
        $sel = $d[$name] === $opt ? ' selected' : '';
        echo '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
    }
    echo '</select>';
    if ($bad) echo '<div class="invalid-feedback">' . e($err[$name]) . '</div>';
}

function text_field(string $name, string $label, array $d, array $err, string $type = 'text', string $hint = '', string $extra = ''): void {
    $bad = isset($err[$name]);
    echo '<label class="form-label" for="' . $name . '">' . e($label) . '</label>';
    echo '<input id="' . $name . '" name="' . $name . '" type="' . $type . '" class="form-control' . ($bad ? ' is-invalid' : '') . '" value="' . e($d[$name]) . '" ' . $extra . '>';
    if ($bad) echo '<div class="invalid-feedback">' . e($err[$name]) . '</div>';
    elseif ($hint !== '') echo '<div class="form-text">' . e($hint) . '</div>';
}

shell_start($u, 'Health Record Form');
?>

<?php if ($success): ?>
<div class="alert alert-success py-2">
    <i class="bi bi-check-circle me-1"></i> Health record information saved for
    <strong><?= e($patientName) ?></strong>.
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="alert alert-danger py-2">
    <?= e($err['form'] ?? 'Please fix the highlighted fields and try again.') ?>
</div>
<?php endif; ?>

<!-- Read-only patient banner -->
<div class="card-soft mb-4" style="height:auto">
    <div class="d-flex flex-wrap gap-3 align-items-center">
        <div>
            <div class="text-secondary small">Patient</div>
            <div class="fw-semibold fs-5"><?= e($patientName) ?></div>
        </div>
        <?php if ($patient['philhealth_no']): ?>
        <div>
            <div class="text-secondary small">PhilHealth No.</div>
            <div><?= e($patient['philhealth_no']) ?></div>
        </div>
        <?php endif; ?>
        <div>
            <div class="text-secondary small">Address</div>
            <div><?= e($patient['address'] ?: '—') ?></div>
        </div>
        <a href="patient_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="bi bi-pencil"></i> Edit core info
        </a>
    </div>
</div>

<form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- ── Patient details ──────────────────────────────────────────── -->
    <div class="card-soft mb-4" style="height:auto">
        <h5 class="mb-3">
            <i class="bi bi-person-fill me-1"></i> Patient details
            <small class="text-secondary fw-normal fs-6 ms-2">(left side of Health Record)</small>
        </h5>
        <div class="row g-3">
            <div class="col-md-4">
                <?php text_field('family_serial_no', 'Family Serial No.',
                    $data, $err, 'text', 'From the family folder (e.g. 2024-001)', 'maxlength="30"'); ?>
            </div>
            <div class="col-md-4">
                <?php select_field('blood_type', 'Blood Type (Patient)', $bloodTypes, $data, $err); ?>
            </div>
            <div class="col-md-4">
                <?php select_field('education_level', 'Education Level (Patient)', $educationLevels, $data, $err); ?>
            </div>
            <div class="col-md-4">
                <?php text_field('occupation', 'Occupation (Patient)', $data, $err,
                    'text', 'e.g. Nurse, Teacher, Housewife', 'maxlength="100"'); ?>
            </div>
            <div class="col-md-4">
                <?php select_field('philhealth_membership', 'PhilHealth Membership', $philhealthMemberships, $data, $err); ?>
            </div>
            <div class="col-md-2">
                <?php text_field('weight_kg', 'Weight (kg)', $data, $err,
                    'number', '', 'min="20" max="200" step="0.01"'); ?>
            </div>
            <div class="col-md-2">
                <?php text_field('height_cm', 'Height (cm)', $data, $err,
                    'number', '', 'min="50" max="250" step="0.01"'); ?>
            </div>
        </div>
    </div>

    <!-- ── Spouse details ───────────────────────────────────────────── -->
    <div class="card-soft mb-4" style="height:auto">
        <h5 class="mb-3">
            <i class="bi bi-people-fill me-1"></i> Spouse / Partner
            <small class="text-secondary fw-normal fs-6 ms-2">(optional)</small>
        </h5>
        <div class="row g-3">
            <div class="col-md-4">
                <?php text_field('spouse_first_name', 'Spouse First Name', $data, $err, 'text', '', 'maxlength="80"'); ?>
            </div>
            <div class="col-md-4">
                <?php text_field('spouse_middle_name', 'Spouse Middle Name (optional)', $data, $err, 'text', '', 'maxlength="80"'); ?>
            </div>
            <div class="col-md-4">
                <?php text_field('spouse_last_name', 'Spouse Surname', $data, $err, 'text', '', 'maxlength="80"'); ?>
            </div>
            <div class="col-md-4">
                <?php select_field('spouse_blood_type', 'Blood Type (Spouse)', $bloodTypes, $data, $err); ?>
            </div>
            <div class="col-md-4">
                <?php select_field('spouse_education_level', 'Education Level (Spouse)', $educationLevels, $data, $err); ?>
            </div>
            <div class="col-md-4">
                <?php text_field('spouse_occupation', 'Occupation (Spouse)', $data, $err,
                    'text', 'e.g. Farmer, Driver, OFW', 'maxlength="100"'); ?>
            </div>
        </div>
    </div>

    <!-- ── Actions ──────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" name="save_only" value="1">
            <i class="bi bi-floppy me-1"></i> Save
        </button>
        <button class="btn btn-success" name="generate_pdf" value="1">
            <i class="bi bi-file-earmark-pdf me-1"></i> Save &amp; Generate PDF
        </button>
        <a href="patients.php" class="btn btn-outline-primary">Cancel</a>
    </div>
</form>

<script>
/* Dim spouse detail fields when no spouse name is entered */
(function () {
    var spouseDetailIds = ['spouse_blood_type', 'spouse_education_level', 'spouse_occupation'];
    function checkSpouse() {
        var hasName = document.getElementById('spouse_first_name').value.trim() !== '' ||
                      document.getElementById('spouse_last_name').value.trim()  !== '';
        spouseDetailIds.forEach(function (id) {
            var el  = document.getElementById(id);
            var col = el && el.closest('.col-md-4');
            if (col) {
                el.disabled    = !hasName;
                col.style.opacity = hasName ? '1' : '0.5';
            }
        });
    }
    ['spouse_first_name', 'spouse_last_name'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', checkSpouse);
    });
    checkSpouse();
}());
</script>

<?php shell_end();
