<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('teacher');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$teacher=$pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1"); $teacher->execute([$user['id']]); $teacherId=(int)($pdo->query("SELECT id FROM teachers WHERE user_id={$user['id']} LIMIT 1")->fetchColumn()??0);

if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='save_marks'){
    verifyCsrf();
    $cfgId=(int)($_POST['config_id']??0); $clsId=(int)($_POST['class_id']??0); $subId=(int)($_POST['subject_id']??0); $submit=$_POST['submit_marks']==='1';
    if($cfgId&&$clsId&&$subId){
        $maxM=$pdo->query("SELECT max_marks FROM assessment_configs WHERE id=$cfgId")->fetchColumn()??100;
        foreach(($_POST['marks']??[]) as $sid=>$val){
            $sid=(int)$sid; $val=$val===''?null:min((float)$val,(float)$maxM); $st=$submit?'submitted':'draft';
            $pdo->prepare("INSERT INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,status) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained),status=VALUES(status),entered_by=VALUES(entered_by),submitted_at=VALUES(submitted_at),updated_at=NOW()")->execute([$sid,$clsId,$subId,$cfgId,$ayId,$val,$maxM,$user['id'],$submit?date('Y-m-d H:i:s'):null,$st]);
        }
        flash('success',$submit?'Marks submitted for approval.':'Marks saved as draft.');
    }
    redirect(BASE_URL.'/portal/teacher/enter_marks.php?class_id='.$clsId.'&subject_id='.$subId.'&config_id='.$cfgId);
}

$myAssign=$pdo->prepare("SELECT DISTINCT ta.class_id,ta.subject_id,c.name cname,s.name sname FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name,s.name");
$myAssign->execute([$teacherId,$ayId]); $assignments=$myAssign->fetchAll();

$configs=$pdo->prepare("SELECT id,name,max_marks FROM assessment_configs WHERE academic_year_id=? AND is_active=1 ORDER BY sequence"); $configs->execute([$ayId]); $configs=$configs->fetchAll();

$selClass=(int)($_GET['class_id']??0); $selSub=(int)($_GET['subject_id']??0); $selCfg=(int)($_GET['config_id']??0);
$students=[]; $existScores=[]; $cfgData=null;
if($selClass&&$selSub&&$selCfg){
    // Verify this teacher is assigned
    $ok=$pdo->prepare("SELECT 1 FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND academic_year_id=? LIMIT 1"); $ok->execute([$teacherId,$selClass,$selSub,$ayId]);
    if($ok->fetchColumn()){
        $sts=$pdo->prepare("SELECT id,student_id,first_name,last_name FROM students WHERE current_class_id=? AND status='Active' ORDER BY last_name,first_name"); $sts->execute([$selClass]); $students=$sts->fetchAll();
        $cfgData=$pdo->query("SELECT * FROM assessment_configs WHERE id=$selCfg")->fetch();
        $sc=$pdo->prepare("SELECT student_id,marks_obtained,status FROM assessment_scores WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=?"); $sc->execute([$selClass,$selSub,$selCfg,$ayId]); $existScores=array_column($sc->fetchAll(),null,'student_id');
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Enter Marks — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Teacher Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/teacher/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php">🏫 My Classes</a>
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php" class="active">✏️ Enter Marks</a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php">🎓 Students</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>Enter Marks</h1><p><?=e($ay)?></p></div></div>
  <?=renderFlash()?>

  <form method="get" class="filter-row" style="margin-bottom:20px">
    <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
      <option value="">Select Class…</option>
      <?php $done=[];foreach($assignments as $a):if(!isset($done[$a['class_id']])){$done[$a['class_id']]=1;?>
      <option value="<?=$a['class_id']?>" <?=$selClass==$a['class_id']?'selected':''?>><?=e($a['cname'])?></option>
      <?php }endforeach;?>
    </select>
    <?php if($selClass):?>
    <select name="subject_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
      <option value="">Select Subject…</option>
      <?php foreach($assignments as $a):if($a['class_id']==$selClass):?>
      <option value="<?=$a['subject_id']?>" <?=$selSub==$a['subject_id']?'selected':''?>><?=e($a['sname'])?></option>
      <?php endif;endforeach;?>
    </select>
    <?php endif;?>
    <?php if($selClass&&$selSub):?>
    <select name="config_id" class="filter-button" onchange="this.form.submit()" style="min-width:200px">
      <option value="">Select Assessment…</option>
      <?php foreach($configs as $cfg):?><option value="<?=$cfg['id']?>" <?=$selCfg==$cfg['id']?'selected':''?>><?=e($cfg['name'])?> (Max: <?=$cfg['max_marks']?>)</option><?php endforeach;?>
    </select>
    <?php endif;?>
  </form>

  <?php if($cfgData&&!empty($students)):?>
  <div class="form-section">
    <div class="form-section-title">Marks — <?=e($cfgData['name'])?> &nbsp;(Max: <?=$cfgData['max_marks']?>)</div>
    <form method="post">
      <?=csrfField()?><input type="hidden" name="action" value="save_marks"/>
      <input type="hidden" name="class_id" value="<?=$selClass?>"/><input type="hidden" name="subject_id" value="<?=$selSub?>"/>
      <input type="hidden" name="config_id" value="<?=$selCfg?>"/><input type="hidden" name="submit_marks" id="submitFlag" value="0"/>
      <div class="table-wrap" style="margin-bottom:14px"><table>
        <thead><tr><th>#</th><th>Student</th><th>ID</th><th>Marks /<?=$cfgData['max_marks']?></th><th>%</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($students as $i=>$st): $sc=$existScores[$st['id']]??null; $locked=($sc&&$sc['status']==='approved'); $pct=$sc&&$sc['marks_obtained']!==null&&$cfgData['max_marks']>0?round($sc['marks_obtained']/$cfgData['max_marks']*100,1):'—';?>
          <tr>
            <td class="muted"><?=$i+1?></td>
            <td><strong><?=e($st['first_name'].' '.$st['last_name'])?></strong></td>
            <td class="muted"><?=e($st['student_id'])?></td>
            <td><?php if($locked):?><span class="status approved"><?=fmtMark((float)$sc['marks_obtained'])?></span><input type="hidden" name="marks[<?=$st['id']?>]" value="<?=$sc['marks_obtained']?>"/>
            <?php else:?><input type="number" name="marks[<?=$st['id']?>]" value="<?=$sc?$sc['marks_obtained']:''?>" min="0" max="<?=$cfgData['max_marks']?>" step="0.5" style="width:80px;padding:6px;border:1px solid var(--line);border-radius:6px;font-size:14px"/><?php endif;?></td>
            <td><?=$pct?>%</td>
            <td><?=statusBadge($sc?$sc['status']:'draft')?></td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table></div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="submit" class="button button-secondary" onclick="document.getElementById('submitFlag').value='0'">💾 Save Draft</button>
        <button type="submit" class="button button-primary" onclick="document.getElementById('submitFlag').value='1'">📤 Submit for Approval</button>
      </div>
    </form>
  </div>
  <?php elseif($selClass&&$selSub&&$selCfg):?>
  <div class="alert alert-warning">You are not assigned to this class/subject combination, or no students found.</div>
  <?php else:?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center"><div style="font-size:36px;margin-bottom:12px">✏️</div><p style="color:var(--ink-soft)">Select class, subject and assessment period above.</p></div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
