<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'attendance';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId();
$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

if ($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='save') {
    verifyCsrf();
    $clsId=(int)($_POST['class_id']??0); $date=$_POST['att_date']??'';
    if ($clsId && $date) {
        foreach (($_POST['status']??[]) as $sid => $st) {
            $sid=(int)$sid; $st=in_array($st,['Present','Absent','Late','Excused'],true)?$st:'Present';
            $pdo->prepare("INSERT INTO attendance (student_id,class_id,academic_year_id,date,status,remarks,recorded_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),recorded_by=VALUES(recorded_by)")
               ->execute([$sid,$clsId,$ayId,$date,$st,trim($_POST['remarks'][$sid]??'')?:null,$user['id']]);
        }
        flash('success','Attendance saved for '.date('M d, Y',strtotime($date)).'.');
    }
    redirect(BASE_URL.'/portal/teacher/take_attendance.php?class_id='.$clsId.'&att_date='.$date);
}

$myClasses = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
if (!($selClass=(int)($_GET['class_id']??0)) && count($myClasses)===1) $selClass=$myClasses[0]['id'];
$selDate = $_GET['att_date'] ?? date('Y-m-d');

$students = []; $existing = [];
if ($selClass) {
    $sts = $pdo->prepare("SELECT id,student_id,first_name,last_name FROM students WHERE current_class_id=? AND status='Active' ORDER BY last_name,first_name");
    $sts->execute([$selClass]); $students=$sts->fetchAll();
    $ex = $pdo->prepare("SELECT student_id,status,remarks FROM attendance WHERE class_id=? AND date=? AND academic_year_id=?");
    $ex->execute([$selClass,$selDate,$ayId]); $existing=array_column($ex->fetchAll(),null,'student_id');
}
$alreadySaved = !empty($existing);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Attendance — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>Take Attendance</h1><p><?= $selDate ? date('l, F d, Y',strtotime($selDate)) : 'Select a class and date' ?></p></div>
    <?php if ($alreadySaved): ?><span class="status approved">Already recorded</span><?php endif; ?>
  </div>
  <?= renderFlash() ?>
  <form method="get" class="filter-row" style="margin-bottom:20px">
    <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
      <option value="">Select class…</option>
      <?php foreach($myClasses as $c):?><option value="<?=$c['id']?>" <?=$selClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?>
    </select>
    <input type="date" name="att_date" value="<?=e($selDate)?>" class="filter-button" max="<?=date('Y-m-d')?>" onchange="this.form.submit()"/>
  </form>
  <?php if ($selClass && !empty($students)): ?>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="save"/>
    <input type="hidden" name="class_id" value="<?=$selClass?>"/>
    <input type="hidden" name="att_date" value="<?=e($selDate)?>"/>
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <button type="button" class="button button-sm button-secondary" onclick="document.querySelectorAll('.att-sel').forEach(s=>s.value='Present')">✓ All Present</button>
      <button type="button" class="button button-sm button-secondary" onclick="document.querySelectorAll('.att-sel').forEach(s=>s.value='Absent')">✗ All Absent</button>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Student</th><th>ID</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach($students as $i=>$st): $cur=$existing[$st['id']]['status']??'Present'; $rem=$existing[$st['id']]['remarks']??'';?>
        <tr>
          <td class="muted"><?=$i+1?></td>
          <td><strong><?=e($st['first_name'].' '.$st['last_name'])?></strong></td>
          <td class="muted"><?=e($st['student_id'])?></td>
          <td>
            <select name="status[<?=$st['id']?>]" class="att-sel" style="padding:6px 10px;border:1.5px solid var(--line);border-radius:6px;font-size:12.5px;font-family:inherit;background:var(--bg)">
              <?php foreach(['Present','Absent','Late','Excused'] as $opt):?><option value="<?=$opt?>" <?=$cur===$opt?'selected':''?>><?=$opt?></option><?php endforeach;?>
            </select>
          </td>
          <td><input type="text" name="remarks[<?=$st['id']?>]" value="<?=e($rem)?>" placeholder="Optional" style="padding:6px 10px;border:1.5px solid var(--line);border-radius:6px;font-size:12.5px;font-family:inherit;width:100%"/></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table></div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button type="submit" class="button button-primary">💾 <?= $alreadySaved ? 'Update' : 'Save' ?> Attendance</button>
    </div>
  </form>
  <?php elseif ($selClass): ?>
  <p style="color:var(--ink-faint);text-align:center;padding:32px">No active students in this class.</p>
  <?php else: ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📆</div><p style="color:var(--ink-soft)">Select a class to take attendance.</p>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
