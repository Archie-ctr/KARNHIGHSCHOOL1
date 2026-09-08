<?php
// ============================================================
// Applicant Portal — Upload Documents
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('applicant');

$activePage = 'documents';
$user       = currentUser();
$error      = '';
$success    = '';

// Load application
try {
    $app = db()->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $app->execute([$user['id']]);
    $app = $app->fetch();
} catch (Throwable $e) { $app = null; }

// Block uploads on finalised statuses
$readOnly = $app && in_array($app['status'], ['Admitted', 'Rejected']);

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $app && !$readOnly) {
    verifyCsrf();
    $docType = trim($_POST['doc_type'] ?? '');
    $allowed = ['report_card','birth_certificate','passport_photo','other'];
    if (!in_array($docType, $allowed)) {
        $error = 'Invalid document type.';
    } elseif (empty($_FILES['document']['name']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a file to upload.';
    } else {
        $path = uploadFile($_FILES['document'], 'applications/'.$app['id']);
        if ($path) {
            try {
                db()->prepare(
                    "INSERT INTO application_documents
                     (application_id, doc_type, file_name, file_path, file_size, mime_type)
                     VALUES (?,?,?,?,?,?)"
                )->execute([
                    $app['id'], $docType,
                    $_FILES['document']['name'],
                    $path,
                    $_FILES['document']['size'],
                    $_FILES['document']['type'],
                ]);
                auditLog('create','applications','document',$app['id'],'','Applicant uploaded: '.$docType);
                $success = 'Document uploaded successfully.';
            } catch (Throwable $e) {
                $error = 'Failed to save document. Please try again.';
            }
        } else {
            $error = 'Upload failed. Allowed: PDF, JPG, PNG. Max 5 MB.';
        }
    }
    redirect(BASE_URL.'/portal/applicant/documents.php'.($success?'?ok=1':('?err='.urlencode($error))));
}

if (isset($_GET['ok']))  $success = 'Document uploaded successfully.';
if (isset($_GET['err'])) $error   = urldecode($_GET['err']);

// Load uploaded docs
$docs = [];
if ($app) {
    try {
        $d = db()->prepare("SELECT * FROM application_documents WHERE application_id=? ORDER BY created_at DESC");
        $d->execute([$app['id']]);
        $docs = $d->fetchAll();
    } catch (Throwable $e) {}
}

$docLabels = [
    'report_card'        => '📋 Previous Report Card',
    'birth_certificate'  => '📄 Birth Certificate',
    'passport_photo'     => '🖼️ Passport Photo',
    'other'              => '📎 Other Document',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Documents — Applicant Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Upload Documents</h1><p>Supporting documents for your application</p></div>
    <?php if ($app): ?><span class="status badge-grey"><?= e($app['application_number']) ?></span><?php endif; ?>
  </div>

  <?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

  <?php if (!$app): ?>
  <div class="alert alert-warning">No application found. <a href="<?= BASE_URL ?>/apply.php">Start an application</a>.</div>

  <?php else: ?>

  <?php if ($app['status'] === 'Documents needed'): ?>
  <div class="alert alert-warning" style="margin-bottom:20px">
    <strong>📎 Action Required:</strong> The admissions office has requested additional documents. Please upload them below.
  </div>
  <?php endif; ?>

  <?php if (!$readOnly): ?>
  <!-- Upload form -->
  <div class="panel" style="padding:22px;margin-bottom:24px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">Upload New Document</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">
      Accepted formats: <strong>PDF, JPG, PNG</strong> &nbsp;·&nbsp; Maximum size: <strong>5 MB</strong>
    </p>
    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="doc-upload-grid">
        <div class="form-group">
          <label style="display:block;font-size:13px;font-weight:600;color:var(--ink-soft);margin-bottom:6px">
            Document Type <span style="color:var(--error)">*</span>
          </label>
          <select name="doc_type" required style="width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit">
            <option value="">Select type…</option>
            <?php foreach ($docLabels as $val => $lbl): ?>
            <option value="<?= $val ?>"><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="display:block;font-size:13px;font-weight:600;color:var(--ink-soft);margin-bottom:6px">
            File <span style="color:var(--error)">*</span>
          </label>
          <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png"
                 style="width:100%;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:13px"/>
        </div>
      </div>
      <button type="submit" class="button button-primary" style="margin-top:14px">Upload Document →</button>
    </form>
  </div>
  <?php else: ?>
  <div class="alert alert-info" style="margin-bottom:20px">
    Document uploads are closed for applications with status: <strong><?= e($app['status']) ?></strong>.
  </div>
  <?php endif; ?>

  <!-- Uploaded documents list -->
  <div class="panel" style="padding:22px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">Uploaded Documents (<?= count($docs) ?>)</h3>
    <?php if (empty($docs)): ?>
    <p style="color:var(--ink-faint);font-size:13px;text-align:center;padding:20px">
      No documents uploaded yet. Please upload your supporting documents above.
    </p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Document Type</th><th>File Name</th><th>Size</th><th>Uploaded</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($docs as $doc): ?>
          <tr>
            <td><strong><?= $docLabels[$doc['doc_type']] ?? e($doc['doc_type']) ?></strong></td>
            <td class="muted" style="font-size:12px"><?= e($doc['file_name']) ?></td>
            <td class="muted" style="white-space:nowrap">
              <?= $doc['file_size'] ? round($doc['file_size']/1024, 1).' KB' : '—' ?>
            </td>
            <td class="muted"><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
            <td>
              <?php $vs = $doc['is_verified'] ?? 0; ?>
              <span class="status <?= $vs ? 'approved' : 'pending' ?>">
                <?= $vs ? 'Verified' : 'Pending review' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div>
</div>
<style>@media(max-width:580px){.doc-upload-grid{grid-template-columns:1fr !important}}</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
