<?php
// ============================================================
// KHSMIS — Application Submission Endpoint
// Handles: JSON POST (modal) + multipart POST (apply.php form)
// ============================================================
require_once __DIR__.'/config/db.php';

$isJson = (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

if ($isJson) {
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isJson) json_out(['success'=>false,'message'=>'Method not allowed.'], 405);
    redirect(BASE_URL.'/apply.php');
}

// ── Parse input ──────────────────────────────────────────────
if ($isJson) {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
} else {
    verifyCsrf();
    $data = $_POST;
}

$g = fn(string $k) => trim((string)($data[$k] ?? ''));

// ── Required field validation ────────────────────────────────
$required = [
    'firstName'           => 'First name',
    'lastName'            => 'Last name',
    'dateOfBirth'         => 'Date of birth',
    'gender'              => 'Gender',
    'phone'               => 'Phone number',
    'address'             => 'Current address',
    'guardianName'        => 'Guardian name',
    'guardianRelationship'=> 'Guardian relationship',
    'guardianPhone'       => 'Guardian phone',
    'grade'               => 'Grade applying for',
];
$missing = [];
foreach ($required as $field => $label) {
    if ($g($field) === '') $missing[] = $label;
}
if (!empty($missing)) {
    $msg = 'Please fill in: '.implode(', ', $missing).'.';
    if ($isJson) json_out(['success'=>false,'message'=>$msg]);
    flash('error', $msg);
    redirect(BASE_URL.'/apply.php');
}

// ── Validate date of birth ────────────────────────────────────
$dob = $g('dateOfBirth');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || strtotime($dob) > time()) {
    $msg = 'Invalid date of birth.';
    if ($isJson) json_out(['success'=>false,'message'=>$msg]);
    flash('error', $msg);
    redirect(BASE_URL.'/apply.php');
}

// ── Validate grade ────────────────────────────────────────────
$gradeNames = db()->query("SELECT name FROM grades WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($g('grade'), $gradeNames, true)) {
    $msg = 'Invalid grade selected.';
    if ($isJson) json_out(['success'=>false,'message'=>$msg]);
    flash('error', $msg);
    redirect(BASE_URL.'/apply.php');
}

// ── Generate unique application number ───────────────────────
$appNumber = generateApplicationNumber();

// ── Lookup grade_id and academic_year_id ─────────────────────
$gradeRow = db()->prepare("SELECT id FROM grades WHERE name=? LIMIT 1");
$gradeRow->execute([$g('grade')]);
$gradeId = $gradeRow->fetchColumn() ?: null;

$ay = currentAcademicYear();
$ayId = $ay['id'] ?? null;
$ayName = $g('academicYear') ?: currentAcademicYearName();

// ── Insert application ────────────────────────────────────────
try {
    $stmt = db()->prepare('
        INSERT INTO applications
            (application_number, first_name, middle_name, last_name, date_of_birth,
             gender, nationality, phone, email, current_address, community,
             county, district, previous_school, last_grade_completed,
             grade_applying_for, grade_id, academic_year_id, academic_year,
             guardian_name, guardian_relationship, guardian_phone, emergency_contact)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $appNumber,
        $g('firstName'),
        $g('middleName')  ?: null,
        $g('lastName'),
        $dob,
        $g('gender'),
        $g('nationality') ?: 'Liberian',
        $g('phone'),
        $g('email')       ?: null,
        $g('address'),
        $g('community')   ?: null,
        $g('county')      ?: 'Nimba',
        $g('district')    ?: null,
        $g('previousSchool') ?: null,
        $g('lastGrade')   ?: null,
        $g('grade'),
        $gradeId,
        $ayId,
        $ayName,
        $g('guardianName'),
        $g('guardianRelationship'),
        $g('guardianPhone'),
        $g('emergencyContact') ?: null,
    ]);
    $appId = (int)db()->lastInsertId();

    // Log status history
    db()->prepare('INSERT INTO application_status_history (application_id,new_status,notes) VALUES (?,?,?)')
       ->execute([$appId,'Application Submitted','Application submitted online']);

    // Audit log
    auditLog('create','applications','application',$appId,'','Application submitted: '.$appNumber);

} catch (PDOException $e) {
    error_log('Application insert error: '.$e->getMessage());
    $msg = 'We could not save your application. Please try again.';
    if ($isJson) json_out(['success'=>false,'message'=>$msg], 500);
    flash('error', $msg);
    redirect(BASE_URL.'/apply.php');
}

// ── Handle file uploads (apply.php form only) ─────────────────
if (!$isJson && !empty($_FILES)) {
    $docMap = [
        'doc_report_card' => 'report_card',
        'doc_birth_cert'  => 'birth_certificate',
        'doc_photo'       => 'passport_photo',
        'doc_other'       => 'other',
    ];
    foreach ($docMap as $field => $docType) {
        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $path = uploadFile($_FILES[$field], 'applications/'.$appId);
            if ($path) {
                db()->prepare(
                    'INSERT INTO application_documents (application_id,doc_type,file_name,file_path,file_size,mime_type) VALUES (?,?,?,?,?,?)'
                )->execute([
                    $appId, $docType,
                    $_FILES[$field]['name'],
                    $path,
                    $_FILES[$field]['size'],
                    $_FILES[$field]['type'],
                ]);
            }
        }
    }
}

// ── Success response ──────────────────────────────────────────
if ($isJson) {
    json_out(['success'=>true,'application_number'=>$appNumber,'message'=>'Application submitted successfully.']);
} else {
    flash('success','Your application has been submitted. Application number: '.$appNumber);
    redirect(BASE_URL.'/application-status.php?submitted=1&num='.urlencode($appNumber));
}
