<?php
$pageTitle   = 'Student ID Cards';
$activeAdmin = 'student_idcards';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$schoolName    = setting('school_name','KARN HIGH SCHOOL');
$schoolAddress = setting('school_address','Karnplay, Nimba County, Liberia');
$schoolPhone   = setting('school_phone','+231 886 417 711');

// ── Filters ───────────────────────────────────────────────────
$gradeF  = (int)($_GET['grade_id']  ?? 0);
$classF  = (int)($_GET['class_id']  ?? 0);
$statusF = trim($_GET['status']     ?? 'Active');
$q       = trim($_GET['q']          ?? '');
$mode    = $_GET['mode'] ?? 'list'; // list | print | single

$where = ['s.academic_year_id=?']; $params = [$ayId];
if ($q)      { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like); }
if ($gradeF) { $where[] = 's.current_grade_id=?'; $params[] = $gradeF; }
if ($classF) { $where[] = 's.current_class_id=?'; $params[] = $classF; }
if ($statusF){ $where[] = 's.status=?';            $params[] = $statusF; }
$wsql = 'WHERE '.implode(' AND ',$where);

$cnt  = $pdo->prepare("SELECT COUNT(*) FROM students s $wsql"); $cnt->execute($params);
$total = (int)$cnt->fetchColumn();

// Single student
$singleId = (int)($_GET['student_id'] ?? 0);
$singleStudent = null;
if ($singleId) {
    $singleStudent = $pdo->prepare(
        "SELECT s.*,g.name grade_name,c.name class_name
         FROM students s
         LEFT JOIN grades g ON g.id=s.current_grade_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE s.id=? LIMIT 1"
    );
    $singleStudent->execute([$singleId]);
    $singleStudent = $singleStudent->fetch();
}

// List for batch print
$students = $pdo->prepare(
    "SELECT s.*,g.name grade_name,c.name class_name
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     $wsql ORDER BY s.last_name,s.first_name LIMIT 200"
);
$students->execute($params);
$students = $students->fetchAll();

$grades  = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$classes = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name");
$classes->execute([$ayId]); $classes = $classes->fetchAll();
?>

<?php if ($mode === 'print' || $singleStudent): ?>
<!-- ════════════════════════════════════════════
  PRINT VIEW — no navigation, pure ID cards
════════════════════════════════════════════ -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>ID Cards — <?= e($schoolName) ?></title>
  <style>
    @page { size: A4; margin: 10mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Arial', sans-serif; background: #f5f5f5; }
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8mm;
      padding: 6mm;
    }
    .id-card {
      width: 85.6mm; height: 54mm;
      background: linear-gradient(135deg, #701422 0%, #3e0c19 100%);
      border-radius: 3mm;
      color: #fff;
      padding: 4mm;
      position: relative;
      overflow: hidden;
      break-inside: avoid;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .id-card::before {
      content: '';
      position: absolute;
      top: -12mm; right: -12mm;
      width: 36mm; height: 36mm;
      background: rgba(255,255,255,.06);
      border-radius: 50%;
    }
    .id-card::after {
      content: '';
      position: absolute;
      bottom: -8mm; left: -8mm;
      width: 24mm; height: 24mm;
      background: rgba(255,255,255,.04);
      border-radius: 50%;
    }
    .card-header {
      display: flex;
      align-items: center;
      gap: 2mm;
      padding-bottom: 2.5mm;
      border-bottom: 0.3mm solid rgba(255,255,255,.25);
      margin-bottom: 2.5mm;
    }
    .school-name { font-size: 5.5pt; font-weight: bold; letter-spacing: .3px; line-height: 1.3; }
    .school-loc  { font-size: 4pt; color: rgba(255,255,255,.65); }
    .card-body   { display: flex; gap: 2.5mm; }
    .avatar-box  {
      width: 18mm; height: 20mm; flex-shrink: 0;
      background: rgba(255,255,255,.15);
      border-radius: 1.5mm;
      display: flex; align-items: center; justify-content: center;
      font-size: 13pt; font-weight: 800; color: rgba(255,255,255,.9);
      overflow: hidden;
    }
    .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
    .info { flex: 1; }
    .student-name { font-size: 7pt; font-weight: 800; line-height: 1.2; margin-bottom: 1mm; }
    .info-row     { font-size: 5.5pt; color: rgba(255,255,255,.78); line-height: 1.5; }
    .info-row span { color: #fff; font-weight: 600; }
    .card-footer  {
      position: absolute; bottom: 2mm; left: 4mm; right: 4mm;
      display: flex; justify-content: space-between; align-items: flex-end;
    }
    .student-id { font-size: 5pt; font-weight: 800; letter-spacing: .5px; color: rgba(255,255,255,.6); }
    .valid-year { font-size: 4.5pt; color: rgba(255,255,255,.55); }
    .back-btn { display: block; margin: 12px; text-decoration: none; color: #333; font-family: sans-serif; font-size: 13px; }
    @media print { .back-btn { display: none; } body { background: white; } }
  </style>
</head>
<body>
<a href="javascript:history.back()" class="back-btn">← Back to list</a>
<a href="javascript:window.print()" class="back-btn" style="color:#ac1f3b;font-weight:700">🖨️ Print / Save PDF</a>

<div class="cards-grid">
  <?php $printList = $singleStudent ? [$singleStudent] : $students;
  foreach ($printList as $st):
    $ini = strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));
    $fullName = trim($st['first_name'].' '.$st['last_name']);
    $hasPhoto = !empty($st['photo']) && file_exists(BASE_PATH.'/uploads/'.$st['photo']);
  ?>
  <div class="id-card">
    <div class="card-header">
      <div>
        <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
        <div class="school-loc">Karnplay, Nimba, Liberia</div>
      </div>
      <div style="margin-left:auto;font-size:7pt;font-weight:800;color:rgba(255,255,255,.5);text-align:right">STUDENT<br>ID CARD</div>
    </div>
    <div class="card-body">
      <div class="avatar-box">
        <?php if ($hasPhoto): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($st['photo']) ?>" alt=""/>
        <?php else: ?><?= htmlspecialchars($ini) ?><?php endif; ?>
      </div>
      <div class="info">
        <div class="student-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="info-row">Grade: <span><?= htmlspecialchars($st['grade_name']??'—') ?></span></div>
        <?php if ($st['class_name']): ?>
        <div class="info-row">Class: <span><?= htmlspecialchars($st['class_name']) ?></span></div>
        <?php endif; ?>
        <?php if ($st['date_of_birth']): ?>
        <div class="info-row">DOB: <span><?= date('d M Y',strtotime($st['date_of_birth'])) ?></span></div>
        <?php endif; ?>
        <div class="info-row">Phone: <span><?= htmlspecialchars($st['phone']??'—') ?></span></div>
      </div>
    </div>
    <div class="card-footer">
      <div class="student-id"><?= htmlspecialchars($st['student_id']) ?></div>
      <div class="valid-year">Valid: <?= htmlspecialchars($ay) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
<?php exit; endif; ?>

<!-- ════════════════════════════════════════════
  LIST VIEW — normal admin layout
════════════════════════════════════════════ -->
<div class="page-heading">
  <div>
    <div class="eyebrow">Student Administration <span></span></div>
    <h1>Student ID Cards</h1>
    <p><?= e($ay) ?> &mdash; <?= number_format($total) ?> student<?= $total!==1?'s':'' ?> matching filters</p>
  </div>
</div>

<!-- Filters -->
<form method="get" class="filter-row" style="margin-bottom:16px">
  <div class="table-search">🔍<input type="search" name="q" placeholder="Name or ID…" value="<?= e($q) ?>"/></div>
  <select name="grade_id" class="filter-button" onchange="this.form.submit()">
    <option value="">All grades</option>
    <?php foreach ($grades as $g): ?><option value="<?= $g['id'] ?>" <?= $gradeF==$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="class_id" class="filter-button" onchange="this.form.submit()">
    <option value="">All classes</option>
    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classF==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <?php if ($q||$gradeF||$classF): ?><a href="<?= BASE_URL ?>/admin/student_idcards.php" class="filter-button">Clear</a><?php endif; ?>
  <a href="?<?= http_build_query(array_filter(['grade_id'=>$gradeF,'class_id'=>$classF,'status'=>$statusF,'q'=>$q])) ?>&mode=print"
     class="button button-primary" target="_blank" style="margin-left:auto">
    🖨️ Print All (<?= number_format(min($total,200)) ?>)
  </a>
</form>

<!-- Cards preview grid -->
<?php if (empty($students)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🪪</div>
  <p style="color:var(--ink-soft)">No students found with the current filters.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Student</th><th>Student ID</th><th>Grade / Class</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($students as $st): $ini = strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1)); ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0"><?= e($ini) ?></div>
            <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>
          </div>
        </td>
        <td class="muted"><?= e($st['student_id']) ?></td>
        <td class="muted"><?= e($st['grade_name']??'—') ?><?= $st['class_name']?' / '.e($st['class_name']):'' ?></td>
        <td><span class="status <?= $st['status']==='Active'?'approved':'warning' ?>"><?= e($st['status']) ?></span></td>
        <td>
          <a href="?student_id=<?= $st['id'] ?>" target="_blank" class="filter-button button-sm">🪪 Print ID</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($total > 200): ?>
<p style="margin-top:10px;font-size:12px;color:var(--ink-soft)">
  ℹ️ Showing first 200 results. Use grade/class filters to narrow down for bulk printing.
</p>
<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
