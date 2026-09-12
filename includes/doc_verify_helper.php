<?php
// ============================================================
// Document Verification Helper
// Usage (at top of any letter PHP, after student is loaded):
//
//   require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
//   $qrStrip = docVerifyStrip(
//       'transcript',           // doc_type
//       $student['id'],         // student DB id
//       $student['first_name'].' '.$student['last_name'],
//       $student['grade_name'] ?? '',
//       currentAcademicYearName(),
//       $refNumber,             // ref number shown on doc
//       currentUserId(),        // issued_by
//       null,                   // purpose (null for non-recommendation)
//       '+1 year'               // expiry: strtotime modifier, or null
//   );
//
// Then echo $qrStrip inside the letter's HTML just before </body>.
// ============================================================

if (!function_exists('docVerifyStrip')) {

/**
 * Creates (or retrieves existing) verification record and returns
 * the full HTML strip to embed at the bottom of every document.
 *
 * The QR code points to:
 *   http(s)://host/KARNHIGHSCHOOL/verify.php?t=TOKEN
 *
 * QR image is generated via Google Charts API (no server-side
 * GD/Imagick needed). Falls back gracefully if network unavailable.
 */
function docVerifyStrip(
    string  $docType,
    int     $studentId,
    string  $studentName,
    string  $gradeName,
    string  $academicYear,
    string  $refNumber,
    ?int    $issuedBy   = null,
    ?string $purpose    = null,
    ?string $expirySpec = '+2 years'   // strtotime() modifier or null
): string {

    $pdo = db();

    // ── Build a deterministic token so re-printing = same QR ──
    // Token = SHA-256 of docType + studentId + refNumber + date(Y-m-d)
    // This ensures the same document printed multiple times on the
    // same day gets the same QR code, but a new issue date = new token.
    $seed  = $docType.'|'.$studentId.'|'.$refNumber.'|'.date('Y-m-d');
    $token = hash('sha256', $seed);

    // ── Upsert the verification record ────────────────────────
    $expires = $expirySpec ? date('Y-m-d H:i:s', strtotime($expirySpec)) : null;

    try {
        // Check if already exists
        $existing = $pdo->prepare("SELECT id FROM document_verifications WHERE token=? LIMIT 1");
        $existing->execute([$token]);
        if (!$existing->fetchColumn()) {
            $pdo->prepare(
                "INSERT INTO document_verifications
                 (token, doc_type, student_id, issued_by, purpose, ref_number,
                  student_name, grade_name, academic_year, issued_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)"
            )->execute([
                $token, $docType, $studentId, $issuedBy, $purpose, $refNumber,
                $studentName, $gradeName, $academicYear, $expires
            ]);
        }
    } catch (Throwable $e) {
        // Silently fail — don't break document rendering
    }

    // ── Build verification URL ─────────────────────────────────
    $verifyUrl = BASE_URL.'/verify.php?t='.$token;

    // ── QR code via Google Charts API ─────────────────────────
    // Size 120x120 px, encoded URL
    $qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=120x120&chl='
           . urlencode($verifyUrl)
           . '&choe=UTF-8&chld=M|2';

    // Short token display (first 16 chars)
    $shortToken = strtoupper(substr($token, 0, 8).'-'.substr($token, 8, 8));
    $school     = setting('school_name', 'KARN HIGH SCHOOL');
    $issueDate  = date('d M Y');

    // ── Return the HTML strip ─────────────────────────────────
    return '
<!-- ═══════════════════ QR VERIFICATION STRIP ═══════════════════ -->
<div class="qr-verify-strip" style="
    margin-top: 24px;
    padding: 10px 14px;
    border: 1.5px solid #1a2744;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 16px;
    background: #f7f9fc;
    page-break-inside: avoid;
">
  <!-- QR Code -->
  <div style="flex-shrink:0;text-align:center">
    <img src="'.e($qrUrl).'"
         alt="Verification QR Code"
         width="88" height="88"
         style="display:block;border:1px solid #ccd"
         onerror="this.style.display=\'none\'"/>
    <div style="font-size:6.5pt;color:#666;margin-top:3px;font-family:monospace;letter-spacing:.04em">
      '.e($shortToken).'
    </div>
  </div>
  <!-- Text -->
  <div style="flex:1;min-width:0">
    <div style="font-size:8pt;font-weight:900;color:#1a2744;text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px">
      🔒 Authenticated Document
    </div>
    <div style="font-size:8pt;color:#444;line-height:1.55">
      This document was issued by <strong>'.e($school).'</strong> and is
      protected by a unique verification code. Scan the QR code or
      visit <strong>'.e(BASE_URL.'/verify.php').'</strong> and enter the
      code below to confirm authenticity.
    </div>
    <div style="margin-top:5px;font-family:monospace;font-size:8pt;
                background:#fff;border:1px solid #ccd;border-radius:4px;
                padding:3px 8px;display:inline-block;color:#1a2744;
                letter-spacing:.06em;word-break:break-all">
      '.e($shortToken).'
    </div>
    <div style="margin-top:4px;font-size:7.5pt;color:#888">
      Issued: '.e($issueDate).'
      '.($expires ? ' &nbsp;&bull;&nbsp; Valid until: '.date('d M Y', strtotime($expires)) : '').'
      &nbsp;&bull;&nbsp; Ref: '.e($refNumber).'
    </div>
  </div>
</div>
<!-- ══════════════════════════════════════════════════════════════ -->
';
}

} // end function_exists guard
