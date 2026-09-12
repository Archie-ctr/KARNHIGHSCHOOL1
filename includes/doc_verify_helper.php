<?php
// ============================================================
// Document Verification Helper
// Uses splitbrain/php-qrcode (MIT) from assets/php-qrcode-master
// Generates inline SVG QR — no GD, no network calls needed.
// ============================================================

require_once dirname(__DIR__).'/assets/php-qrcode-master/src/QRCode.php';

use splitbrain\phpQRCode\QRCode as PhpQR;

if (!function_exists('docVerifyStrip')) {

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
    // Same doc + same ref + same date = same QR (stable reprints)
    $seed  = $docType.'|'.$studentId.'|'.$refNumber.'|'.date('Y-m-d');
    $token = hash('sha256', $seed);

    // ── Upsert verification record ────────────────────────────
    $expires = $expirySpec ? date('Y-m-d H:i:s', strtotime($expirySpec)) : null;
    try {
        $ex = $pdo->prepare("SELECT id FROM document_verifications WHERE token=? LIMIT 1");
        $ex->execute([$token]);
        if (!$ex->fetchColumn()) {
            $pdo->prepare(
                "INSERT INTO document_verifications
                 (token,doc_type,student_id,issued_by,purpose,ref_number,
                  student_name,grade_name,academic_year,issued_at,expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)"
            )->execute([
                $token, $docType, $studentId, $issuedBy, $purpose, $refNumber,
                $studentName, $gradeName, $academicYear, $expires
            ]);
        }
    } catch (Throwable $e) { /* never crash the document */ }

    // ── Verification URL ──────────────────────────────────────
    $verifyUrl = BASE_URL.'/verify.php?t='.$token;

    // ── Generate inline SVG QR (pure PHP, no GD needed) ───────
    $qrSvg = '';
    try {
        $rawSvg = PhpQR::svg($verifyUrl, ['s' => 'qrm']); // ECC level M
        // Wrap with sizing + white background + black fill
        $qrSvg  = str_replace(
            '<svg xmlns="http://www.w3.org/2000/svg"',
            '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' style="width:84px;height:84px;display:block;'
            . 'background:#fff;border:2px solid #1a2744;'
            . 'border-radius:3px;padding:3px;box-sizing:border-box"',
            $rawSvg
        );
        // Ensure rects render black
        $qrSvg = str_replace('<rect ', '<rect style="fill:#000000" ', $qrSvg);
    } catch (Throwable $e) {
        // Fallback placeholder if something goes wrong
        $qrSvg = '<div style="width:84px;height:84px;border:2px solid #1a2744;'
               . 'display:flex;align-items:center;justify-content:center;'
               . 'font-size:8pt;color:#999;text-align:center;background:#f8f8f8">'
               . 'QR<br>Code</div>';
    }

    // ── Display token (readable chunks) ──────────────────────
    $displayToken = strtoupper(
        substr($token,0,8).'-'.substr($token,8,8).'-'.substr($token,16,8)
    );
    $school    = setting('school_name', 'KARN HIGH SCHOOL');
    $issueDate = date('d M Y');
    $expiryStr = $expires ? date('d M Y', strtotime($expires)) : '';

    // ── Return inline HTML strip ──────────────────────────────
    return '
<!-- ══ QR VERIFICATION STRIP ══ -->
<div style="margin-top:18px;border-top:1.5px solid #1a2744;padding-top:11px;
            display:flex;align-items:flex-start;gap:14px;page-break-inside:avoid;
            background:#f8faff;border-radius:0 0 4px 4px;padding-left:6px;padding-right:6px;padding-bottom:8px">

  <!-- Text block -->
  <div style="flex:1;min-width:0;font-size:8pt;color:#333;line-height:1.65">
    <div style="font-weight:900;font-size:8.5pt;color:#1a2744;
                text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px">
      🔒 Document Authenticity Verification
    </div>
    <div style="margin-bottom:5px">
      This is an official document of <strong>'.e($school).'</strong>.
      Scan the QR code or visit
      <strong>'.e(rtrim(BASE_URL,'/')).'/verify.php</strong>
      and enter the verification code below to confirm authenticity.
    </div>
    <div style="font-family:monospace;font-size:8pt;letter-spacing:.1em;
                background:#fff;border:1px solid #c8d0e8;border-radius:3px;
                padding:3px 9px;display:inline-block;color:#1a2744;
                word-break:break-all;margin-bottom:5px">
      '.e($displayToken).'
    </div>
    <div style="font-size:7.5pt;color:#888;line-height:1.6">
      <strong>Ref:</strong> '.e($refNumber).'
      &nbsp;&bull;&nbsp; <strong>Type:</strong> '.e(ucwords(str_replace('_',' ',$docType))).'
      &nbsp;&bull;&nbsp; <strong>Issued:</strong> '.e($issueDate).'
      '.($expiryStr ? '&nbsp;&bull;&nbsp; <strong>Valid until:</strong> '.e($expiryStr) : '').'
    </div>
  </div>

  <!-- QR Code SVG -->
  <div style="flex-shrink:0;text-align:center;padding-top:2px">
    '.$qrSvg.'
    <div style="font-size:6pt;color:#888;margin-top:3px;font-weight:700;
                text-transform:uppercase;letter-spacing:.06em">
      SCAN TO VERIFY
    </div>
  </div>

</div>
<!-- ══ END QR STRIP ══ -->
';
}

} // end function_exists guard
