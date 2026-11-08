<?php
/**
 * health_record_pdf.php
 * Generates an auto-filled Health_Record.pdf for a given patient.
 * Uses FPDF to place text directly on top of the Health Record template.
 *
 * All coordinates are calibrated to land precisely on the black underlines.
 * Uses black font color (#000000).
 *
 * Usage: health_record_pdf.php?id=<patient_id>
 */
require 'includes/auth.php';
$u = require_role(['admin', 'front_desk', 'patient']);
require_once 'lib/FPDF-master/fpdf.php';

// ── Load patient record with strict RBAC boundary ───────────────────────────
if ($u['role'] === 'patient') {
    // Patients are strictly confined to their own linked record
    $st = db()->prepare('SELECT * FROM patients WHERE user_id = ?');
    $st->execute([$u['id']]);
    $pat = $st->fetch();
    if (!$pat) {
        http_response_code(403);
        exit('403 – No patient health record is linked to your account yet. Please contact the front desk.');
    }
    $id = (int)$pat['id'];
} else {
    // Admin and front desk can access requested patient
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { header('Location: documents.php'); exit; }
    $st = db()->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$id]);
    $pat = $st->fetch();
    if (!$pat) { http_response_code(404); exit('404 – Patient record not found.'); }
}

log_action($u['id'], 'health_record_pdf_downloaded', "patient #$id");

// ── Coordinate mapping ─────────────────────────────────────────────────────
// The template image is 1240 × 881 px.
// In the PDF, it is drawn across 595.0 pt wide and 422.976 pt high,
// positioned vertically at Y = 209.512 pt from the top of the A4 page (842 pt).
define('IMG_W_PX',  1240.0);
define('IMG_H_PX',  881.0);
define('PDF_IMG_W', 595.0);
define('PDF_IMG_H', 422.976);
define('PDF_IMG_Y', 209.512);

// Converts pixel X coordinate on the image to FPDF X (in points)
function px2x(float $px): float {
    return ($px + 3.0) * (PDF_IMG_W / IMG_W_PX);
}

// Converts underline pixel Y on the image to FPDF baseline Y (in points from top of page)
// Text baseline sits 1.5 pixels above the underline center
function px2baseline(float $py_underline): float {
    return PDF_IMG_Y + ($py_underline - 1.5) * (PDF_IMG_H / IMG_H_PX);
}

// ── Compose patient and spouse data (no redundancy) ────────────────────────
$patientFullName = mb_strtoupper(compose_name($pat['first_name'], $pat['middle_name'], $pat['last_name']));

$spouseName = '';
if (!empty($pat['spouse_first_name']) || !empty($pat['spouse_last_name'])) {
    $spouseName = mb_strtoupper(compose_name(
        $pat['spouse_first_name'] ?? '',
        $pat['spouse_middle_name'] ?? '',
        $pat['spouse_last_name'] ?? ''
    ));
}

// PhilHealth display
$philStr = '';
if (!empty($pat['philhealth_membership'])) {
    $philStr = mb_strtoupper($pat['philhealth_membership']);
    if (!empty($pat['philhealth_no'])) {
        $philStr .= ' (' . $pat['philhealth_no'] . ')';
    }
} elseif (!empty($pat['philhealth_no'])) {
    $philStr = $pat['philhealth_no'];
}

// Address (uppercase for clean document appearance)
$address = !empty($pat['address']) ? mb_strtoupper($pat['address']) : '';

// Age derived from birthdate
$age = '';
if (!empty($pat['birthdate'])) {
    $age = (string)(new DateTime($pat['birthdate']))->diff(new DateTime())->y;
}

// Pregnancy fields
$weightStr = !empty($pat['weight_kg']) ? $pat['weight_kg'] . ' kg' : '';
$heightStr = !empty($pat['height_cm']) ? $pat['height_cm'] . ' cm' : '';
$lmpFmt    = !empty($pat['lmp'])  ? (new DateTime($pat['lmp']))->format('m/d/Y')  : '';
$eddFmt    = !empty($pat['edd'])  ? (new DateTime($pat['edd']))->format('m/d/Y')  : '';
$gravida   = ($pat['gravida'] !== null && $pat['gravida'] !== '') ? (string)$pat['gravida'] : '';

// ── Initialize PDF (Portrait A4: 595 x 842 points) ─────────────────────────
$pdf = new FPDF('P', 'pt', [595, 842]);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// ── Embed template JPEG ────────────────────────────────────────────────────
// Extract JPEG from PDF stream or use decoded asset
$pdfContent = file_get_contents(__DIR__ . '/templates/Health_Record.pdf');
$streamPos = strpos($pdfContent, "stream\n", 200);
if ($streamPos === false) $streamPos = strpos($pdfContent, "stream\r\n", 200);
$dataStart = $streamPos + 7;
$endStream = strpos($pdfContent, "endstream", $dataStart);
$rawStream = substr($pdfContent, $dataStart, $endStream - $dataStart);

$jpegData = @gzuncompress($rawStream);
if ($jpegData === false) $jpegData = @gzinflate(substr($rawStream, 2));
if ($jpegData === false && file_exists(__DIR__ . '/assets/stream_0_decoded.jpg')) {
    $jpegData = file_get_contents(__DIR__ . '/assets/stream_0_decoded.jpg');
}

$tmpJpeg = sys_get_temp_dir() . '/hr_tpl_' . session_id() . '_' . $id . '.jpg';
file_put_contents($tmpJpeg, $jpegData);

// Place background template
$pdf->Image($tmpJpeg, 0, PDF_IMG_Y, PDF_IMG_W, PDF_IMG_H, 'JPG');

// ── Set font and text color ────────────────────────────────────────────────
// Black font color (#000000)
$pdf->SetTextColor(0, 0, 0);

/**
 * Writes text that automatically fits inside the underline [px1..px2].
 * Scales font size down smoothly so it never exceeds the underline length.
 */
function writeFitField(FPDF &$pdf, string $text, float $px1, float $px2, float $py_underline, float $targetSize = 6.0, float $minSize = 3.0, bool $bold = false): void {
    $text = trim($text);
    if ($text === '') return;

    // Available width on the underline in points with 3px right margin
    $maxW = ($px2 - $px1 - 3.0) * (PDF_IMG_W / IMG_W_PX);
    $style = $bold ? 'B' : '';
    $fontSize = $targetSize;

    $pdf->SetFont('Helvetica', $style, $fontSize);
    while ($pdf->GetStringWidth($text) > $maxW && $fontSize > $minSize) {
        $fontSize -= 0.1;
        $pdf->SetFont('Helvetica', $style, $fontSize);
    }

    $x = px2x($px1);
    $y = px2baseline($py_underline);
    $pdf->Text($x, $y, $text);
}

// ══════════════════════════════════════════════════════════════════════════
// LEFT SIDE — auto-filled fields on the black underlines
// (Measured directly from the high-resolution scanned template)
// ══════════════════════════════════════════════════════════════════════════

// 1. FAMILY SERIAL NO: underline X=240..321, Y=93
if (!empty($pat['family_serial_no'])) {
    writeFitField($pdf, (string)$pat['family_serial_no'], 240, 321, 93, 6.5, 3.5, true);
}

// 2. NAME OF PATIENT: underline X=269..431, Y=370 (fits cleanly within underline)
if (!empty($patientFullName)) {
    writeFitField($pdf, $patientFullName, 269, 431, 370, 5.5, 3.0, true);
}

// 3. BLOOD TYPE (Patient): underline X=544..582, Y=369
if (!empty($pat['blood_type'])) {
    writeFitField($pdf, (string)$pat['blood_type'], 544, 582, 369, 6.5, 4.0, true);
}

// 4. EDUCATION LEVEL (Patient): underline X=270..385, Y=444
if (!empty($pat['education_level'])) {
    writeFitField($pdf, mb_strtoupper((string)$pat['education_level']), 270, 385, 444, 5.5, 3.0, false);
}

// 5. OCCUPATION (Patient): underline X=503..578, Y=444
if (!empty($pat['occupation'])) {
    writeFitField($pdf, mb_strtoupper((string)$pat['occupation']), 503, 578, 444, 5.5, 3.0, false);
}

// 6. NAME OF SPOUSE: underline X=270..432, Y=517 (fits cleanly within underline)
if (!empty($spouseName)) {
    writeFitField($pdf, $spouseName, 270, 432, 517, 5.5, 3.0, true);
}

// 7. BLOOD TYPE (Spouse): underline X=544..580, Y=516
if (!empty($pat['spouse_blood_type'])) {
    writeFitField($pdf, (string)$pat['spouse_blood_type'], 544, 580, 516, 6.5, 4.0, true);
}

// 8. EDUCATION LEVEL (Spouse): underline X=272..387, Y=590
if (!empty($pat['spouse_education_level'])) {
    writeFitField($pdf, mb_strtoupper((string)$pat['spouse_education_level']), 272, 387, 590, 5.5, 3.0, false);
}

// 9. OCCUPATION (Spouse): underline X=504..578, Y=591
if (!empty($pat['spouse_occupation'])) {
    writeFitField($pdf, mb_strtoupper((string)$pat['spouse_occupation']), 504, 578, 591, 5.5, 3.0, false);
}

// 10. PHILHEALTH MEMBERSHIP: underline X=340..581, Y=664
if (!empty($philStr)) {
    writeFitField($pdf, $philStr, 340, 581, 664, 6.0, 3.5, false);
}

// 11. ADDRESS: underline X=202..562, Y=738
if (!empty($address)) {
    writeFitField($pdf, $address, 202, 562, 738, 6.0, 3.5, false);
}

// ══════════════════════════════════════════════════════════════════════════
// RIGHT SIDE — pregnancy summary fields
// ══════════════════════════════════════════════════════════════════════════

// AGE (YRS OLD): underline Y=300, X=772..965
if ($age !== '') {
    writeFitField($pdf, $age, 772, 965, 300, 6.5, 4.0, true);
}

// WEIGHT (KGS): underline Y=336, X=769..971
if ($weightStr !== '') {
    writeFitField($pdf, $weightStr, 769, 971, 336, 6.5, 4.0, false);
}

// HEIGHT (CMS): underline Y=371, X=769..971
if ($heightStr !== '') {
    writeFitField($pdf, $heightStr, 769, 971, 371, 6.5, 4.0, false);
}

// LMP: underline Y=407, X=695..970
if ($lmpFmt !== '') {
    writeFitField($pdf, $lmpFmt, 695, 970, 407, 6.5, 4.0, false);
}

// EDD: underline Y=443, X=693..968
if ($eddFmt !== '') {
    writeFitField($pdf, $eddFmt, 693, 968, 443, 6.5, 4.0, false);
}

// This is my ___ pregnancy: blank Y=479, X=728..774
if ($gravida !== '') {
    writeFitField($pdf, $gravida, 728, 774, 479, 6.5, 4.0, true);
}

// ── Clean up temp file & output PDF ────────────────────────────────────────
@unlink($tmpJpeg);
$filename = 'HealthRecord_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pat['last_name'] . '_' . $pat['first_name']) . '.pdf';
$dest = (isset($_GET['download']) && $_GET['download'] === '1') ? 'D' : 'I';
$pdf->Output($dest, $filename);
