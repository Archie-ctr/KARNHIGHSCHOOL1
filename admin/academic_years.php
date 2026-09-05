<?php
$pageTitle   = 'Academic Years';
$activeAdmin = 'academic_years';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action==='add') {
        $name  = trim($_POST['name']??'');
        $start = $_POST['start_date']??'';
        $end   = $_POST['end_date']??'';
        if ($name && $start && $end) {
            $pdo->prepare("INSERT INTO academic_years (name,start_date,end_date,status) VALUES (?,?,?,'upcoming')")->execute([$name,$start,$end]);
            $ayId = (int)$pdo->lastInsertId();
            // Auto-create 2 semesters
            $pdo->prepare("INSERT INTO semesters (academic_year_id,name,sequence) VALUES (?,?,?)")->execute([$ayId,'Semester 1',1]);
            $s1 = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO semesters (academic_year_id,name,sequence) VALUES (?,?,?)")->execute([$ayId,'Semester 2',2]);
            $s2 = (int)$pdo->lastInsertId();
            // Auto-create periods
            foreach ([[$s1,1,'1st Period','period'],[$s1,2,'2nd Period','period'],[$s1,3,'3rd Period','period'],[$s1,4,'Semester 1 Examination','exam'],
                      [$s2,1,'4th Period','period'],[$s2,2,'5th Period','period'],[$s2,3,'6th Period','period'],[$s2,4,'Semester 2 Examination','exam']] as [$sid,$seq,$pname,$ptype]) {
                $pdo->prepare("INSERT INTO periods (semester_id,name,sequence,type) VALUES (?,?,?,?)")->execute([$sid,$pname,$seq,$ptype]);
            }
            flash('success','Academic year '.$name.' created with semesters and periods.');
        }
    } elseif ($action==='set_current') {
        $id=(int)($_POST['ay_id']??0);
        if ($id) { $pdo->exec("UPDATE academic_years SET is_current=0"); $pdo->prepare("UPDATE academic_years SET is_current=1,status='active' WHERE id=?")->execute([$id]); flash('success','Current academic year updated.'); }
    } elseif ($action==='close') {
        $id=(int)($_POST['ay_id']??0);
        if ($id) { $pdo->prepare("UPDATE academic_years SET status='closed' WHERE id=?")->execute([$id]); flash('success','Academic year closed.'); }
    }
    redirect(BASE_URL.'/admin/academic_years.php');
}

$years = $pdo->query("SELECT ay.*,(SELECT COUNT(*) FROM students s WHERE s.academic_year_id=ay.id) student_count FROM academic_years ay ORDER BY ay.start_date DESC")->fetchAll();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Academics <span></span></div>
    <h1>Academic Years</h1>
    <p>Manage academic years, semesters and periods.</p>
  </div>
  <button class="button button-primary" onclick="document.getElementById('addAYModal').style.display='flex'">+ New Academic Year</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Academic Year</th><th>Start Date</th><th>End Date</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($years as $ay): ?>
      <tr>
        <td><strong><?= e($ay['name']) ?></strong><?= $ay['is_current']?'<span class="status approved" style="margin-left:8px;font-size:11px">Current</span>':'' ?></td>
        <td class="muted"><?= date('M d, Y',strtotime($ay['start_date'])) ?></td>
        <td class="muted"><?= date('M d, Y',strtotime($ay['end_date'])) ?></td>
        <td><?= number_format((int)$ay['student_count']) ?></td>
        <td><?= statusBadge($ay['status']) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if (!$ay['is_current'] && $ay['status']!=='closed'): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="set_current"/><input type="hidden" name="ay_id" value="<?= $ay['id'] ?>"/>
              <button type="submit" class="filter-button button-sm">Set Current</button>
            </form>
            <?php endif; ?>
            <?php if ($ay['status']==='active'): ?>
            <form method="post" onsubmit="return confirm('Close this academic year?')" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="close"/><input type="hidden" name="ay_id" value="<?= $ay['id'] ?>"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--warning)">Close Year</button>
            </form>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/semesters.php?ay_id=<?= $ay['id'] ?>" class="filter-button button-sm">Semesters →</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add AY Modal -->
<div id="addAYModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:440px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">New Academic Year</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Year name (e.g. 2027/2028) *<input name="name" required placeholder="2027/2028"/></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Start Date *<input type="date" name="start_date" required/></label></div>
        <div class="form-group"><label>End Date *<input type="date" name="end_date" required/></label></div>
      </div>
      <p style="font-size:12.5px;color:var(--ink-faint);margin:10px 0">Semesters and periods will be created automatically.</p>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addAYModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Create →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
