<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $activePage='graduation';
$ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

$student=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.user_id=? LIMIT 1");
$student->execute([$user['id']]); $student=$student->fetch();
if(!$student) redirect(BASE_URL.'/portal/student/');

$studentId=$student['id'];

// Graduation record
try{$gradRecord=$pdo->query("SELECT * FROM graduation_records WHERE student_id=$studentId ORDER BY graduation_date DESC LIMIT 1")->fetch();}catch(Throwable $e){$gradRecord=null;}

// Eligibility checks
$checks=[];

// 1. Attendance eligibility (≥75%)
try{
    $att=$pdo->query("SELECT SUM(status='Present') p,COUNT(*) t FROM attendance WHERE student_id=$studentId AND academic_year_id=$ayId")->fetch();
    $attPct=$att['t']>0?round($att['p']/$att['t']*100,1):null;
    $checks[]=['Attendance',
               $attPct!==null?$attPct.'%':'—',
               $attPct===null?'unknown':($attPct>=75?'pass':'fail'),
               $attPct>=75?'Meets minimum 75% attendance requirement':'Below minimum 75% — see attendance office'];
}catch(Throwable $e){$checks[]=['Attendance','—','unknown','Unable to retrieve attendance data'];}

// 2. Academic standing — overall average ≥50%
try{
    $avg=$pdo->query("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=$studentId AND academic_year_id=$ayId AND status IN ('approved','published') AND max_marks>0")->fetchColumn();
    $checks[]=['Academic Average',
               $avg?$avg.'%':'—',
               $avg===null?'unknown':($avg>=50?'pass':'fail'),
               $avg>=50?'Meets minimum passing average':'Academic average below 50% — subject to review'];
}catch(Throwable $e){$checks[]=['Academic Average','—','unknown','Unable to retrieve marks'];}

// 3. Fees cleared
try{
    $unpaid=$pdo->query("SELECT COALESCE(SUM(fs.amount),0)-(SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$studentId AND academic_year_id=$ayId) balance FROM fee_structures fs WHERE fs.academic_year_id=$ayId AND fs.is_active=1 AND (fs.grade_id IS NULL OR fs.grade_id={$student['current_grade_id']})")->fetchColumn();
    $unpaid=max(0,(float)$unpaid);
    $checks[]=['Fees Cleared',
               $unpaid>0?'GHS '.number_format($unpaid,2).' outstanding':'Cleared',
               $unpaid>0?'fail':'pass',
               $unpaid>0?'Outstanding balance — clear fees before graduation':'All fees cleared'];
}catch(Throwable $e){$checks[]=['Fees Cleared','—','unknown','Unable to retrieve fee data'];}

// 4. Library books returned
try{
    $overdue=(int)$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE student_id=$studentId AND status='Issued'")->fetchColumn();
    $checks[]=['Library Books',
               $overdue>0?$overdue.' book'.($overdue!=1?'s':'').' on loan':'All returned',
               $overdue>0?'warn':'pass',
               $overdue>0?'Books currently on loan — return before graduation':'No outstanding library books'];
}catch(Throwable $e){$checks[]=['Library Books','—','unknown','Unable to retrieve library data'];}

// 5. Discipline record
try{
    $discOpen=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE student_id=$studentId AND academic_year_id=$ayId AND status NOT IN ('resolved','closed')")->fetchColumn();
    $checks[]=['Discipline',
               $discOpen>0?$discOpen.' open case'.($discOpen!=1?'s':''):'Clear',
               $discOpen>0?'warn':'pass',
               $discOpen>0?'Open discipline case(s) — must be resolved before graduation':'No outstanding discipline issues'];
}catch(Throwable $e){$checks[]=['Discipline','—','unknown','Unable to retrieve discipline data'];}

$allPass=count(array_filter($checks,fn($c)=>$c[2]==='fail'))===0;
$hasWarnings=count(array_filter($checks,fn($c)=>$c[2]==='warn'))>0;
$overallStatus=$gradRecord?ucfirst($gradRecord['status']??'pending'):($allPass?($hasWarnings?'Eligible (Warnings)':'Eligible'):'Not Yet Eligible');

// School graduation info
try{$gradInfo=$pdo->query("SELECT * FROM school_settings WHERE setting_key IN ('graduation_date','graduation_venue','graduation_ceremony','graduation_requirements') ORDER BY setting_key")->fetchAll(PDO::FETCH_KEY_PAIR);}catch(Throwable $e){$gradInfo=[];}

$checkColors=['pass'=>'var(--green)','fail'=>'var(--error)','warn'=>'var(--warning)','unknown'=>'var(--ink-soft)'];
$checkIcons=['pass'=>'✅','fail'=>'❌','warn'=>'⚠️','unknown'=>'❓'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Graduation — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Graduation</h1>
  <p><?=e($student['grade_name']??'')?> &mdash; <?=e($ay)?></p></div>
</div>

<!-- Status banner -->
<div class="panel" style="padding:24px;margin-bottom:20px;border-top:4px solid <?=$allPass?'var(--green)':($hasWarnings?'var(--warning)':'var(--error)')?>">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:40px"><?=$allPass?'🎓':($hasWarnings?'⚠️':'📋')?></div>
    <div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;color:<?=$allPass?'var(--green)':($hasWarnings?'var(--warning)':'var(--error)')?>"><?=$overallStatus?></h2>
      <p style="color:var(--ink-soft);font-size:13.5px">
        <?=e($student['first_name'].' '.$student['last_name'])?> &middot; <?=e($student['student_id'])?> &middot; <?=e($student['class_name']??'—')?>
      </p>
    </div>
    <?php if($gradRecord&&$gradRecord['graduation_date']):?>
    <div style="margin-left:auto;text-align:right">
      <div style="font-size:12px;color:var(--ink-soft)">Graduation Date</div>
      <div style="font-size:16px;font-weight:800"><?=date('d F Y',strtotime($gradRecord['graduation_date']))?></div>
    </div>
    <?php endif;?>
  </div>
</div>

<!-- Eligibility checklist -->
<div class="panel" style="padding:22px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:15px;margin-bottom:16px">📋 Graduation Eligibility Checklist</h3>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach($checks as [$label,$value,$status,$note]):
      $col=$checkColors[$status]; $icon=$checkIcons[$status];
    ?>
    <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--bg2);border-radius:var(--radius-sm);border-left:4px solid <?=$col?>">
      <span style="font-size:18px;flex-shrink:0"><?=$icon?></span>
      <div style="flex:1">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <strong style="font-size:13.5px"><?=$label?></strong>
          <span style="font-size:13px;font-weight:700;color:<?=$col?>"><?=$value?></span>
        </div>
        <div style="font-size:12.5px;color:var(--ink-soft);margin-top:3px"><?=$note?></div>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php if(!$allPass||$hasWarnings):?>
  <div class="alert alert-info" style="margin-top:16px;margin-bottom:0">
    ℹ️ Please contact your class teacher or the Registrar's office to address any outstanding requirements before graduation.
  </div>
  <?php endif;?>
</div>

<!-- Graduation record details -->
<?php if($gradRecord):?>
<div class="panel" style="padding:22px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:15px;margin-bottom:14px">🎓 Graduation Record</h3>
  <div class="form-grid">
    <div><label>Status</label><p><span class="status <?=$gradRecord['status']==='graduated'?'approved':'new-s'?>"><?=ucfirst($gradRecord['status']??'pending')?></span></p></div>
    <div><label>Graduation Date</label><p><?=$gradRecord['graduation_date']?date('d F Y',strtotime($gradRecord['graduation_date'])):'Not set'?></p></div>
    <div><label>Ceremony</label><p><?=e($gradRecord['ceremony_name']??'—')?></p></div>
    <div><label>Certificate Number</label><p style="font-family:monospace"><?=e($gradRecord['certificate_number']??'—')?></p></div>
    <?php if($gradRecord['honours']??false):?><div><label>Honours</label><p style="color:var(--gold);font-weight:700"><?=e($gradRecord['honours'])?></p></div><?php endif;?>
    <?php if($gradRecord['notes']??false):?><div style="grid-column:1/-1"><label>Notes</label><p><?=e($gradRecord['notes'])?></p></div><?php endif;?>
  </div>
</div>
<?php endif;?>

<!-- School graduation info -->
<?php if(!empty($gradInfo)):?>
<div class="panel" style="padding:22px">
  <h3 style="font-weight:700;font-size:15px;margin-bottom:14px">📅 School Graduation Information</h3>
  <div class="form-grid">
    <?php foreach($gradInfo as $k=>$v):?>
    <div><label><?=ucwords(str_replace('_',' ',$k))?></label><p><?=e($v)?></p></div>
    <?php endforeach;?>
  </div>
</div>
<?php else:?>
<div class="panel" style="padding:22px">
  <h3 style="font-weight:700;font-size:15px;margin-bottom:10px">📅 Graduation Information</h3>
  <p style="color:var(--ink-soft)">Graduation date and ceremony details will be published by the school administration. Check back closer to the end of term.</p>
</div>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
