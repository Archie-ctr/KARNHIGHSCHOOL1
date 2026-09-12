<?php
// ============================================================
// Document Verification Helper
// Usage: call docVerifyStrip() INSIDE the document's page div,
// just before the footer line. It returns an HTML block with
// the QR code embedded inline on the document itself.
// ============================================================

if (!function_exists('docVerifyStrip')) {

/**
 * Creates (or retrieves existing) verification record and returns
 * a compact inline HTML block with QR code + token to embed
 * INSIDE the document page (not after it).
 *
 * QR image via Google Charts API — no GD/Imagick needed.
 *
 * Token = SHA-256(docType|studentId|refNumber|date)
 * Same document reprinted same day → same QR code.
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
    ?string $expirySpec = '+2 years'
): string {

    $pdo = db();

    // ── Deterministic token ───────────────────────────────────
    $seed  = $docType.'|'.$studentId.'|'.$refNumber.'|'.date('Y-m-d');
    $token = hash('sha256', $seed);

    // ── Upsert verification record ────────────────────────────
    $expires = $expirySpec ? date('Y-m-d H:i:s', strtotime($expirySpec)) : null;
    try {
        $exists = $pdo->prepare("SELECT id FROM document_verifications WHERE token=? LIMIT 1");
        $exists->execute([$token]);
        if (!$exists->fetchColumn()) {
            $pdo->prepare(
                "INSERT INTO document_verifications
                 (token,doc_type,student_id,issued_by,purpose,ref_number,
                  student_name,grade_name,academic_year,issued_at,expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)"
            )->execute([
                $token,$docType,$studentId,$issuedBy,$purpose,$refNumber,
                $studentName,$gradeName,$academicYear,$expires
            ]);
        }
    } catch (Throwable $e) { /* never crash the document */ }

    // ── Verification URL & QR ─────────────────────────────────
    $verifyUrl = BASE_URL.'/verify.php?t='.$token;
    $qrUrl     = 'https://chart.googleapis.com/chart?cht=qr&chs=96x96&chl='
               . urlencode($verifyUrl).'&choe=UTF-8&chld=M|1';

    // Display token split into readable chunks
    $displayToken = strtoupper(
        substr($token,0,8).'-'.substr($token,8,8).'-'.substr($token,16,8)
    );
    $school    = setting('school_name','KARN HIGH SCHOOL');
    $issueDate = date('d M Y');
    $expiryStr = $expires ? date('d M Y', strtotime($expires)) : '';

    // ── Inline HTML strip (inside the document) ───────────────
    // Uses only inline styles — safe for print/PDF
    return '
<!-- ══ QR VERIFICATION (inline on document) ══ -->
<div style="
    margin-top:18px;
    border-top:1.5px solid #1a2744;
    padding-top:10px;
    display:flex;
    align-items:flex-start;
    gap:14px;
    page-break-inside:avoid;
">
  <!-- Left: text -->
  <div style="flex:1;min-width:0;font-size:8pt;color:#333;line-height:1.6">
    <div style="font-weight:900;font-size:8.5pt;color:#1a2744;
                text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px">
      🔒 Document Authenticity Verification
    </div>
    <div>
      This is an official document of <strong>'.e($school).'</strong>.
      To verify its authenticity, scan the QR code or visit
      <strong>'.e(rtrim(BASE_URL,'/').'/').'verify.php</strong>
      and enter the verification code:
    </div>
    <div style="margin-top:5px;font-family:monospace;font-size:8pt;
                background:#f0f4ff;border:1px solid #c8d0e8;
                border-radius:3px;padding:3px 8px;display:inline-block;
                color:#1a2744;letter-spacing:.1em;word-break:break-all">
      '.e($displayToken).'
    </div>
    <div style="margin-top:4px;font-size:7.5pt;color:#888">
      Ref: '.e($refNumber).'
      &nbsp;&bull;&nbsp; Issued: '.e($issueDate).'
      '.($expiryStr ? '&nbsp;&bull;&nbsp; Valid until: '.e($expiryStr) : '').'
      &nbsp;&bull;&nbsp; Doc type: '.e(ucwords(str_replace('_',' ',$docType))).'
    </div>
  </div>
  <!-- Right: QR code -->
  <div style="flex-shrink:0;text-align:center">
    <img src="'.e($qrUrl).'"
         alt="Scan to verify"
         width="80" height="80"
         style="display:block;border:1.5px solid #1a2744;border-radius:4px;padding:2px"
         onerror="this.style.display=\'none\'"/>
    <div style="font-size:6.5pt;color:#888;margin-top:2px;font-family:monospace">
      SCAN TO VERIFY
    </div>
  </div>
</div>
<!-- ══ END QR STRIP ══ -->
';
}

} // end function_exists guard
