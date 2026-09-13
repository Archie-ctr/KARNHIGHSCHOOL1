<?php
// ── POST must run BEFORE admin_header outputs HTML ────────────
require_once dirname(__DIR__).'/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    verifyCsrf();
    $pdo    = db();
    $action = $_POST['action'] ?? '';
    $stdId  = (int)($_POST['student_id'] ?? 0);

    if ($action === 'upload' && $stdId) {
        $uploaded = 0; $failed = 0;
        $files = $_FILES['doc'] ?? [];
        if (!empty($files['name'])) {
            $fileList = is_array($files['name'])
                ? array_map(fn($i) => ['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]], array_keys($files['name']))
                : [$files];
            $docType = $_POST['doc_type'] ?? 'other';
            foreach ($fileList as $file) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
                $path = uploadFile($file, 'student_docs/'.$stdId);
                if ($path) {
                    $pdo->prepare("INSERT INTO student_documents (student_id,doc_type,file_name,file_path,file_size,mime_type,uploaded_by) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$stdId,$docType,$file['name'],$path,$file['size'],$file['type'],currentUser()['id']]);
                    $uploaded++;
                } else { $failed++; }
            }
        }
        if ($uploaded > 0 && $failed === 0)
            flash('success', $uploaded === 1 ? 'Document uploaded successfully.' : "$uploaded documents uploaded.");
        elseif ($uploaded > 0)
            flash('warning', "$uploaded uploaded, $failed failed — PDF/JPG/PNG only, max 5 MB.");
        else
            flash('error', 'Upload failed. Only PDF, JPG and PNG files up to 5 MB are accepted.');

    } elseif ($action === 'delete' && $stdId) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId) {
            $doc = $pdo->query("SELECT file_path,file_name FROM student_documents WHERE id=$docId AND student_id=$stdId")->fetch();
            if ($doc) {
                $pdo->prepare("DELETE FROM student_documents WHERE id=?")->execute([$docId]);
                @unlink(UPLOAD_DIR.'/'.$doc['file_path']);
                flash('success', 'Document deleted.');
            }
        }
    }
    redirect(BASE_URL.'/admin/documents.php?student_id='.$stdId);
}

// ── Now output the page ───────────────────────────────────────
$pageTitle   = 'Student Documents';
$activeAdmin = 'documents';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['registrar','principal','super_admin','school_admin','vice_principal']);

// ── GET data ──────────────────────────────────────────────────
$pdo   = db();
$stdId = (int)($_GET['student_id'] ?? 0);
$students = $pdo->query(
    "SELECT s.id, s.student_id sid, CONCAT(s.first_name,' ',s.last_name) name,
            g.name grade_name
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.status='Active'
     ORDER BY s.first_name,s.last_name"
)->fetchAll();

$student = null;
$docs    = [];
if ($stdId) {
    $student = $pdo->query(
        "SELECT s.*, g.name grade_name, c.name class_name
         FROM students s
         LEFT JOIN grades g  ON g.id=s.current_grade_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE s.id=$stdId LIMIT 1"
    )->fetch();
    if ($student) {
        $docs = $pdo->query(
            "SELECT d.*, u.name uploaded_by_name
             FROM student_documents d
             LEFT JOIN users u ON u.id=d.uploaded_by
             WHERE d.student_id=$stdId
             ORDER BY d.created_at DESC"
        )->fetchAll();
    }
}

// ── Helpers ───────────────────────────────────────────────────
$docTypeLabels = [
    'birth_certificate' => 'Birth Certificate',
    'report_card'       => 'Report Card',
    'passport_photo'    => 'Passport Photo',
    'id_card'           => 'ID Card',
    'transcript'        => 'Transcript',
    'health_record'     => 'Health Record',
    'guardian_id'       => 'Guardian ID',
    'school_leaving'    => 'School Leaving Certificate',
    'recommendation'    => 'Recommendation Letter',
    'other'             => 'Other',
];

$docTypeIcons = [
    'birth_certificate' => '🪪',
    'report_card'       => '📊',
    'passport_photo'    => '🖼️',
    'id_card'           => '🪪',
    'transcript'        => '📋',
    'health_record'     => '🏥',
    'guardian_id'       => '👨‍👩‍👧',
    'school_leaving'    => '🎓',
    'recommendation'    => '✉️',
    'other'             => '📄',
];

function docIcon(string $type, array $icons): string {
    return $icons[$type] ?? '📄';
}

function fileIcon(string $mime, string $name): string {
    if (str_contains($mime, 'pdf'))   return '📕';
    if (str_contains($mime, 'image')) return '🖼️';
    return '📄';
}

function fmtBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes/1048576,1).' MB';
    if ($bytes >= 1024)    return round($bytes/1024,1).' KB';
    return $bytes.' B';
}
?>

<!-- ── Page heading ── -->
<div class="page-heading">
  <div>
    <div class="eyebrow">Students <span></span></div>
    <h1>Student Documents</h1>
    <p>Upload, view and manage official documents for each student.</p>
  </div>
</div>

<!-- ── Student selector ── -->
<div class="list-content" style="margin-bottom:20px">
  <form method="get" class="filter-row">
    <div class="table-search">🔍
      <input type="search" id="studentSearch" placeholder="Type name or student ID to filter…" autocomplete="off"/>
    </div>
    <select name="student_id" id="studentSelect" class="filter-button" style="min-width:280px" onchange="this.form.submit()">
      <option value="">— Select student —</option>
      <?php foreach ($students as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $stdId == $s['id'] ? 'selected' : '' ?>>
        <?= e($s['name']) ?> (<?= e($s['sid']) ?>)<?= $s['grade_name'] ? ' — '.$s['grade_name'] : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($stdId): ?>
    <a href="<?= BASE_URL ?>/admin/documents.php" class="filter-button">✕ Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!$student): ?>
<!-- ── No student selected ── -->
<div style="text-align:center;padding:64px 20px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:48px;margin-bottom:14px">📂</div>
  <h3 style="font-weight:700;margin-bottom:6px">Select a Student</h3>
  <p style="color:var(--ink-soft)">Choose a student from the dropdown above to view or upload documents.</p>
</div>

<?php else: ?>

<!-- ── Student info banner ── -->
<div style="display:flex;align-items:center;gap:16px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px">
  <div class="avatar" style="width:46px;height:46px;font-size:15px;flex-shrink:0;background:var(--primary);color:#fff">
    <?= strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)) ?>
  </div>
  <div style="flex:1;min-width:0">
    <strong style="font-size:15px"><?= e($student['first_name'].' '.$student['last_name']) ?></strong>
    <div style="font-size:12px;color:var(--ink-soft);margin-top:2px">
      ID: <strong><?= e($student['student_id']) ?></strong>
      &nbsp;&bull;&nbsp; <?= e($student['grade_name'] ?? '—') ?>
      <?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?>
      &nbsp;&bull;&nbsp; <?= count($docs) ?> document<?= count($docs) !== 1 ? 's' : '' ?> on file
    </div>
  </div>
  <div style="font-size:11px;color:var(--ink-faint)"><?= e($student['status'] ?? 'Active') ?></div>
</div>

<!-- ── Upload section ── -->
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
    <span>📤 Upload Document(s)</span>
    <span style="font-size:11px;font-weight:400;color:var(--ink-faint)">PDF, JPG, PNG — max 5 MB each</span>
  </div>

  <form method="post" enctype="multipart/form-data" id="uploadForm">
    <?= csrfField() ?>
    <input type="hidden" name="action"     value="upload"/>
    <input type="hidden" name="student_id" value="<?= $stdId ?>"/>

    <!-- Drag-drop upload zone -->
    <div id="dropZone" style="
        border:2.5px dashed var(--primary-light,#c7b8ff);
        border-radius:var(--radius);
        background:var(--primary-soft);
        padding:28px 20px;
        text-align:center;
        cursor:pointer;
        transition:all .2s;
        margin-bottom:14px;
        position:relative;
    " onclick="document.getElementById('fileInput').click()"
       ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--bg-soft)'"
       ondragleave="this.style.borderColor='';this.style.background='var(--primary-soft)'"
       ondrop="handleDrop(event)">
      <div style="font-size:32px;margin-bottom:8px">📁</div>
      <div style="font-weight:700;color:var(--primary);font-size:14px">
        Click to choose files or drag &amp; drop here
      </div>
      <div style="font-size:12px;color:var(--ink-soft);margin-top:4px">
        PDF, JPG, PNG — multiple files allowed
      </div>
      <input type="file" id="fileInput" name="doc[]" multiple accept=".pdf,.jpg,.jpeg,.png"
             style="display:none" onchange="showFilePreview(this.files)"/>
    </div>

    <!-- File preview list (filled by JS) -->
    <div id="filePreviewList" style="margin-bottom:12px"></div>

    <!-- Document type + submit row -->
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
        <label style="font-size:13px;font-weight:600">Document Type
          <select name="doc_type" style="margin-top:4px">
            <?php foreach ($docTypeLabels as $val => $label): ?>
            <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <button type="submit" class="button button-primary" id="uploadBtn" style="padding:10px 28px">
        📤 Upload
      </button>
    </div>
  </form>
</div>

<!-- ── Documents list ── -->
<div class="form-section">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
    <span>📋 Documents on File</span>
    <span style="font-size:12px;font-weight:400;color:var(--ink-soft)"><?= count($docs) ?> total</span>
  </div>

  <?php if (empty($docs)): ?>
  <div style="text-align:center;padding:40px 20px;color:var(--ink-faint)">
    <div style="font-size:40px;margin-bottom:12px">📭</div>
    <strong>No documents uploaded yet.</strong><br>
    <span style="font-size:13px">Use the upload area above to add documents for this student.</span>
  </div>

  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
    <?php foreach ($docs as $d):
      $ext  = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
      $isImg= in_array($ext, ['jpg','jpeg','png']);
      $isPdf= $ext === 'pdf';
      $fileUrl = BASE_URL.'/uploads/'.e($d['file_path']);
      $docLabel = $docTypeLabels[$d['doc_type']] ?? ucfirst(str_replace('_',' ',$d['doc_type']));
      $dIcon = docIcon($d['doc_type'], $docTypeIcons);
    ?>
    <div style="
        background:var(--surface);
        border:1.5px solid var(--line);
        border-radius:var(--radius);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        transition:box-shadow .15s;
    " onmouseenter="this.style.boxShadow='var(--shadow)'" onmouseleave="this.style.boxShadow='none'">

      <!-- Preview area -->
      <div style="
          height:140px;background:var(--bg);
          display:flex;align-items:center;justify-content:center;
          border-bottom:1px solid var(--line);
          position:relative;overflow:hidden;cursor:pointer;
      "
           data-url="<?= e($fileUrl) ?>"
           data-name="<?= e($d['file_name']) ?>"
           data-type="<?= $isImg ? 'image' : ($isPdf ? 'pdf' : 'other') ?>"
           data-label="<?= e($docLabel) ?>"
           data-icon="<?= e($dIcon) ?>"
           data-size="<?= $d['file_size'] ? e(fmtBytes((int)$d['file_size'])) : '—' ?>"
           data-date="<?= e(date('d M Y H:i', strtotime($d['created_at']))) ?>"
           data-uploader="<?= e($d['uploaded_by_name'] ?? '—') ?>"
           class="doc-view-btn">
        <?php if ($isImg): ?>
          <img src="<?= $fileUrl ?>"
               alt="<?= e($d['file_name']) ?>"
               style="max-width:100%;max-height:100%;object-fit:contain"
               onerror="this.outerHTML='<span style=font-size:52px>🖼️</span>'"/>
        <?php elseif ($isPdf): ?>
          <div style="text-align:center">
            <div style="font-size:52px">📕</div>
            <div style="font-size:11px;color:var(--ink-soft);margin-top:4px">PDF Document</div>
          </div>
        <?php else: ?>
          <div style="font-size:52px">📄</div>
        <?php endif; ?>
        <!-- Hover overlay -->
        <div style="
            position:absolute;inset:0;
            background:rgba(0,0,0,0);
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:13px;font-weight:700;
            transition:background .15s;
        " onmouseenter="this.style.background='rgba(0,0,0,.45)'"
           onmouseleave="this.style.background='rgba(0,0,0,0)'">
          <span style="opacity:0;transition:opacity .15s" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
            👁 View
          </span>
        </div>
      </div>

      <!-- Info -->
      <div style="padding:12px 14px;flex:1;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;align-items:center;gap:6px">
          <span style="font-size:18px"><?= $dIcon ?></span>
          <div style="min-width:0">
            <div style="font-size:12px;font-weight:700;color:var(--primary)"><?= e($docLabel) ?></div>
            <div style="font-size:11px;color:var(--ink-faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($d['file_name']) ?>">
              <?= e($d['file_name']) ?>
            </div>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--ink-faint)">
          <span><?= $d['file_size'] ? fmtBytes((int)$d['file_size']) : '—' ?></span>
          <span><?= date('d M Y', strtotime($d['created_at'])) ?></span>
        </div>
        <?php if ($d['uploaded_by_name']): ?>
        <div style="font-size:10.5px;color:var(--ink-faint)">
          Uploaded by: <?= e($d['uploaded_by_name']) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div style="
          display:flex;gap:0;border-top:1px solid var(--line);
      ">
        <a href="<?= $fileUrl ?>"
           data-url="<?= e($fileUrl) ?>"
           data-name="<?= e($d['file_name']) ?>"
           data-type="<?= $isImg ? 'image' : ($isPdf ? 'pdf' : 'other') ?>"
           data-label="<?= e($docLabel) ?>"
           data-icon="<?= e($dIcon) ?>"
           data-size="<?= $d['file_size'] ? e(fmtBytes((int)$d['file_size'])) : '—' ?>"
           data-date="<?= e(date('d M Y H:i', strtotime($d['created_at']))) ?>"
           data-uploader="<?= e($d['uploaded_by_name'] ?? '—') ?>"
           class="doc-view-btn"
           style="flex:1;padding:9px;text-align:center;font-size:12px;font-weight:600;
                  color:var(--primary);text-decoration:none;border-right:1px solid var(--line);
                  transition:background .15s"
           onmouseenter="this.style.background='var(--primary-soft)'"
           onmouseleave="this.style.background=''">
          👁 View
        </a>
        <a href="<?= $fileUrl ?>" download="<?= e($d['file_name']) ?>"
           style="flex:1;padding:9px;text-align:center;font-size:12px;font-weight:600;
                  color:var(--ink-soft);text-decoration:none;border-right:1px solid var(--line);
                  transition:background .15s"
           onmouseenter="this.style.background='var(--bg)'"
           onmouseleave="this.style.background=''">
          ⬇ Download
        </a>
        <form method="post" style="flex:1" onsubmit="return confirm('Delete «<?= e(addslashes($d['file_name'])) ?>»?')">
          <?= csrfField() ?>
          <input type="hidden" name="action"     value="delete"/>
          <input type="hidden" name="doc_id"     value="<?= $d['id'] ?>"/>
          <input type="hidden" name="student_id" value="<?= $stdId ?>"/>
          <button type="submit" style="
              width:100%;padding:9px;font-size:12px;font-weight:600;
              color:var(--error);background:none;border:none;cursor:pointer;
              transition:background .15s;
          " onmouseenter="this.style.background='#fff0f0'"
             onmouseleave="this.style.background=''">
            🗑 Delete
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- ── Add another document card ── -->
    <div onclick="document.getElementById('fileInput').click()"
         style="
             background:var(--primary-soft);
             border:2px dashed var(--primary-light,#c7b8ff);
             border-radius:var(--radius);
             display:flex;flex-direction:column;
             align-items:center;justify-content:center;
             min-height:200px;cursor:pointer;
             color:var(--primary);
             transition:all .15s;
             gap:8px;
         "
         onmouseenter="this.style.background='var(--bg-soft)';this.style.borderColor='var(--primary)'"
         onmouseleave="this.style.background='var(--primary-soft)';this.style.borderColor=''">
      <div style="font-size:36px">➕</div>
      <div style="font-size:13px;font-weight:700">Add Document</div>
      <div style="font-size:11px;color:var(--ink-soft)">Click to upload</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<script>
// ── Active tab tracker ────────────────────────────────────────
let _currentDocTab = 'preview';

function switchDocTab(tab) {
  _currentDocTab = tab;
  const tabs    = ['preview','details'];
  const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6c3cff';

  tabs.forEach(t => {
    const btn  = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
    const pane = document.getElementById('docPane' + t.charAt(0).toUpperCase() + t.slice(1));
    if (!btn || !pane) return;
    const active = t === tab;
    btn.style.borderBottomColor = active ? (primary || '#6c3cff') : 'transparent';
    btn.style.color = active ? (primary || '#6c3cff') : '#888';
    pane.style.display = active ? 'flex' : 'none';
  });
}

// ── Open modal ────────────────────────────────────────────────
function openDocModal(d) {
  const modal = document.getElementById('docViewerModal');
  if (!modal) return;

  // Populate header
  document.getElementById('docModalIcon').textContent  = d.icon  || '📄';
  document.getElementById('docModalTitle').textContent = d.name  || 'Document';
  document.getElementById('docModalMeta').textContent  = (d.label || '') + (d.size ? '  ·  ' + d.size : '');
  document.getElementById('docModalOpenBtn').href      = d.url;
  document.getElementById('docModalDownloadBtn').href  = d.url;
  document.getElementById('docModalDownloadBtn').setAttribute('download', d.name || 'document');
  document.getElementById('docModalFooterRight').textContent = d.date ? 'Uploaded ' + d.date + (d.uploader && d.uploader !== '—' ? '  by  ' + d.uploader : '') : '';

  // ── Preview pane ──────────────────────────────────────────
  const preview = document.getElementById('docPanePreview');
  preview.innerHTML = '';

  if (d.type === 'image') {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'padding:16px;display:flex;align-items:center;justify-content:center;width:100%';
    const img  = document.createElement('img');
    img.src    = d.url;
    img.alt    = d.name;
    img.style.cssText = 'max-width:100%;max-height:72vh;object-fit:contain;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.2)';
    img.onerror = () => { wrap.innerHTML = '<div style="text-align:center;color:#888"><div style="font-size:52px;margin-bottom:12px">🖼️</div><div>Image could not be loaded.</div></div>'; };
    wrap.appendChild(img);
    preview.appendChild(wrap);

  } else if (d.type === 'pdf') {
    const embed = document.createElement('embed');
    embed.src   = d.url + '#toolbar=1&navpanes=1&scrollbar=1';
    embed.type  = 'application/pdf';
    embed.style.cssText = 'width:100%;height:72vh;border:none;display:block';
    const fallback = document.createElement('div');
    fallback.style.cssText = 'padding:16px;font-size:12px;color:#666;text-align:center;border-top:1px solid #ddd;background:#fafafa';
    fallback.innerHTML = 'PDF not rendering? <a href="'+d.url+'" target="_blank" style="color:var(--primary);font-weight:700">Open in new tab ↗</a>';
    preview.style.flexDirection = 'column';
    preview.style.alignItems    = 'stretch';
    preview.appendChild(embed);
    preview.appendChild(fallback);

  } else {
    preview.innerHTML =
      '<div style="text-align:center;padding:52px 24px">' +
        '<div style="font-size:56px;margin-bottom:16px">📄</div>' +
        '<p style="font-size:14px;color:#555;margin-bottom:20px">No preview available for this file type.</p>' +
        '<a href="'+d.url+'" target="_blank" ' +
           'style="display:inline-block;padding:10px 28px;background:#1a2744;' +
                  'color:#fff;border-radius:8px;font-weight:700;text-decoration:none;font-size:13px">↗ Open File</a>' +
      '</div>';
  }

  // ── Details pane ─────────────────────────────────────────
  const tbody = document.getElementById('docDetailsTable');
  tbody.innerHTML = '';
  const rows = [
    ['File Name',     d.name    || '—'],
    ['Document Type', d.label   || '—'],
    ['File Size',     d.size    || '—'],
    ['Date Uploaded', d.date    || '—'],
    ['Uploaded By',   d.uploader|| '—'],
    ['File URL',      '<a href="'+d.url+'" target="_blank" style="color:var(--primary);word-break:break-all">'+d.url+'</a>'],
  ];
  rows.forEach(([label, value]) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td style="padding:10px 12px;font-weight:700;color:#555;white-space:nowrap;width:140px;' +
           'border-bottom:1px solid #eee;vertical-align:top;font-size:12px">'+label+'</td>'+
      '<td style="padding:10px 12px;color:#222;border-bottom:1px solid #eee;font-size:13px">'+value+'</td>';
    tbody.appendChild(tr);
  });

  // Show preview tab by default
  switchDocTab('preview');

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

// ── Close modal ───────────────────────────────────────────────
function closeDocModal() {
  const modal = document.getElementById('docViewerModal');
  if (!modal) return;
  modal.style.display = 'none';
  const preview = document.getElementById('docPanePreview');
  if (preview) { preview.innerHTML = ''; preview.style.flexDirection = ''; preview.style.alignItems = ''; }
  document.body.style.overflow = '';
}

// ── Event delegation for .doc-view-btn ───────────────────────
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.doc-view-btn');
  if (!btn) return;
  e.preventDefault();
  openDocModal({
    url:      btn.dataset.url      || '',
    name:     btn.dataset.name     || 'Document',
    type:     btn.dataset.type     || 'other',
    label:    btn.dataset.label    || '',
    icon:     btn.dataset.icon     || '📄',
    size:     btn.dataset.size     || '',
    date:     btn.dataset.date     || '',
    uploader: btn.dataset.uploader || '',
  });
});

// Close on backdrop click
document.getElementById('docViewerModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeDocModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDocModal();
});

// ── Live filter for student select ────────────────────────────
const searchInput = document.getElementById('studentSearch');
const select      = document.getElementById('studentSelect');
if (searchInput && select) {
  const allOptions = Array.from(select.options).map(o => o.cloneNode(true));
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    select.innerHTML = '';
    allOptions.forEach(opt => {
      if (!q || opt.text.toLowerCase().includes(q)) select.appendChild(opt.cloneNode(true));
    });
    if (!select.options.length) {
      const empty = document.createElement('option');
      empty.text = 'No matches found';
      select.appendChild(empty);
    }
  });
}

// ── File preview before upload ────────────────────────────────
function showFilePreview(files) {
  const list = document.getElementById('filePreviewList');
  if (!list) return;
  list.innerHTML = '';
  if (!files || files.length === 0) return;

  const wrap = document.createElement('div');
  wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;padding:12px;background:var(--bg);border:1px solid var(--line);border-radius:var(--radius)';

  Array.from(files).forEach(file => {
    const ext  = file.name.split('.').pop().toLowerCase();
    const icon = ext==='pdf' ? '📕' : ['jpg','jpeg','png'].includes(ext) ? '🖼️' : '📄';
    const size = file.size>=1048576 ? (file.size/1048576).toFixed(1)+' MB'
               : file.size>=1024    ? (file.size/1024).toFixed(0)+' KB'
               : file.size+' B';
    const item = document.createElement('div');
    item.style.cssText = 'display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:8px 12px;font-size:12px;max-width:280px';
    item.innerHTML = '<span style="font-size:20px">'+icon+'</span><div style="min-width:0"><div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:190px" title="'+file.name+'">'+file.name+'</div><div style="color:var(--ink-faint)">'+size+'</div></div>';
    if (['jpg','jpeg','png'].includes(ext)) {
      const img = document.createElement('img');
      img.style.cssText = 'width:36px;height:36px;object-fit:cover;border-radius:4px;flex-shrink:0;order:-1';
      const r = new FileReader();
      r.onload = ev => { img.src = ev.target.result; };
      r.readAsDataURL(file);
      item.prepend(img);
    }
    wrap.appendChild(item);
  });

  const note = document.createElement('div');
  note.style.cssText = 'font-size:12px;color:var(--primary);font-weight:700;padding:4px 0 0';
  note.textContent = files.length+' file'+(files.length>1?'s':'')+' selected — ready to upload';
  list.appendChild(wrap);
  list.appendChild(note);

  const btn = document.getElementById('uploadBtn');
  if (btn) btn.textContent = '📤 Upload '+files.length+' file'+(files.length>1?'s':'');
}

// ── Drag-and-drop ─────────────────────────────────────────────
function handleDrop(e) {
  e.preventDefault();
  const zone = document.getElementById('dropZone');
  if (zone) { zone.style.borderColor=''; zone.style.background='var(--primary-soft)'; }
  const dt = e.dataTransfer;
  if (dt && dt.files.length>0) {
    const inp = document.getElementById('fileInput');
    if (inp) { inp.files=dt.files; showFilePreview(dt.files); }
  }
}

// ── Upload spinner ────────────────────────────────────────────
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
  const files = document.getElementById('fileInput')?.files;
  if (!files || files.length===0) { e.preventDefault(); alert('Please select at least one file.'); return; }
  const btn = document.getElementById('uploadBtn');
  if (btn) { btn.disabled=true; btn.textContent='⏳ Uploading…'; }
});
</script>

<!-- ── Document Viewer Modal ── -->
<div id="docViewerModal" style="
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(10,10,20,.82);
    align-items:center;justify-content:center;padding:16px;
">
  <div style="
      background:var(--surface,#fff);
      border-radius:12px;
      width:100%;max-width:860px;
      max-height:94vh;
      display:flex;flex-direction:column;
      box-shadow:0 24px 80px rgba(0,0,0,.6);
      overflow:hidden;
  ">

    <!-- ── Top bar ── -->
    <div style="
        display:flex;align-items:center;gap:10px;
        padding:12px 16px;
        background:#1a2744;
        flex-shrink:0;
    ">
      <!-- File type icon -->
      <span id="docModalIcon" style="font-size:22px;flex-shrink:0">📄</span>
      <!-- File name + type -->
      <div style="flex:1;min-width:0">
        <div id="docModalTitle"
             style="font-weight:700;font-size:13.5px;color:#fff;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          Document
        </div>
        <div id="docModalMeta"
             style="font-size:10.5px;color:rgba(255,255,255,.5);margin-top:1px">
        </div>
      </div>
      <!-- Action buttons -->
      <div style="display:flex;gap:6px;flex-shrink:0">
        <a id="docModalDownloadBtn" href="#" download
           style="display:inline-flex;align-items:center;gap:5px;
                  padding:6px 13px;border-radius:6px;font-size:12px;font-weight:700;
                  background:rgba(255,255,255,.12);color:#fff;
                  text-decoration:none;transition:background .15s;border:1px solid rgba(255,255,255,.15)"
           onmouseenter="this.style.background='rgba(255,255,255,.22)'"
           onmouseleave="this.style.background='rgba(255,255,255,.12)'">
          ⬇ Download
        </a>
        <a id="docModalOpenBtn" href="#" target="_blank"
           style="display:inline-flex;align-items:center;gap:5px;
                  padding:6px 13px;border-radius:6px;font-size:12px;font-weight:700;
                  background:rgba(255,255,255,.12);color:#fff;
                  text-decoration:none;transition:background .15s;border:1px solid rgba(255,255,255,.15)"
           onmouseenter="this.style.background='rgba(255,255,255,.22)'"
           onmouseleave="this.style.background='rgba(255,255,255,.12)'">
          ↗ Open
        </a>
        <button onclick="closeDocModal()"
                style="display:inline-flex;align-items:center;justify-content:center;
                       width:32px;height:32px;border-radius:6px;font-size:18px;
                       background:rgba(255,255,255,.12);color:#fff;
                       border:1px solid rgba(255,255,255,.15);cursor:pointer;
                       transition:background .15s"
                onmouseenter="this.style.background='rgba(220,50,50,.5)'"
                onmouseleave="this.style.background='rgba(255,255,255,.12)'"
                title="Close (Esc)">✕</button>
      </div>
    </div>

    <!-- ── Tab bar ── -->
    <div style="
        display:flex;gap:0;
        background:#f4f5f8;
        border-bottom:2px solid var(--line,#e5e7eb);
        flex-shrink:0;
    ">
      <button id="tabPreview"
              onclick="switchDocTab('preview')"
              style="padding:10px 20px;font-size:13px;font-weight:700;
                     border:none;background:none;cursor:pointer;
                     border-bottom:3px solid var(--primary,#6c3cff);
                     color:var(--primary,#6c3cff);transition:all .15s">
        🔍 Preview
      </button>
      <button id="tabDetails"
              onclick="switchDocTab('details')"
              style="padding:10px 20px;font-size:13px;font-weight:700;
                     border:none;background:none;cursor:pointer;
                     border-bottom:3px solid transparent;
                     color:var(--ink-soft,#888);transition:all .15s">
        📋 Details
      </button>
    </div>

    <!-- ── Preview pane ── -->
    <div id="docPanePreview"
         style="flex:1;overflow:auto;display:flex;align-items:center;
                justify-content:center;background:#e8e8e8;min-height:440px">
    </div>

    <!-- ── Details pane (hidden by default) ── -->
    <div id="docPaneDetails" style="display:none;flex:1;overflow:auto;padding:24px;background:var(--surface,#fff)">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <tbody id="docDetailsTable"></tbody>
      </table>
    </div>

    <!-- ── Footer ── -->
    <div style="
        padding:9px 16px;
        background:#f4f5f8;
        border-top:1px solid var(--line,#e5e7eb);
        display:flex;justify-content:space-between;align-items:center;
        font-size:11.5px;color:var(--ink-soft,#888);
        flex-shrink:0;
    ">
      <span id="docModalFooterLeft">KARN HIGH SCHOOL — Official Document</span>
      <span id="docModalFooterRight"></span>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
