<?php
require_once dirname(__DIR__).'/config/db.php';
requirePermission('marks.create');

$pdo   = db();
$ayId  = currentAcademicYearId();
$uid   = currentUserId();
$isTeacher = isTeacher();
$teacher   = currentTeacher();

// ── POST: save/submit/resubmit ────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action  = $_POST['action'] ?? '';
    $clsId   = (int)($_POST['class_id']   ?? 0);
    $subId   = (int)($_POST['subject_id'] ?? 0);
    $cfgId   = (int)($_POST['config_id']  ?? 0);
    $reason  = trim($_POST['reason'] ?? '');

    if (in_array($action,['save','submit','resubmit'],true) && $clsId && $subId && $cfgId) {
        // Teachers can only enter marks for their own classes+subjects
        if ($isTeacher && $teacher) {
            $ok=$pdo->prepare("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND academic_year_id=?");
            $ok->execute([$teacher['id'],$clsId,$subId,$ayId]);
            if (!(int)$ok->fetchColumn()) { flash('error','You are not assigned to this class and subject.'); redirect(BASE_URL.'/admin/marks_entry.php'); }
        }

        $maxM=(float)($pdo->query("SELECT max_marks FROM assessment_configs WHERE id=$cfgId")->fetchColumn()??100);
        $newStatus=match($action){'submit'=>STATUS_SUBMITTED,'resubmit'=>STATUS_RESUBMIT,default=>STATUS_DRAFT};

        foreach (($_POST['marks']??[]) as $stdId=>$val) {
            $stdId=(int)$stdId;
            $val  = $val===''?null:min((float)$val,$maxM);

            // Get existing score
            $existing=$pdo->prepare("SELECT * FROM assessment_scores WHERE student_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=?");
            $existing->execute([$stdId,$subId,$cfgId,$ayId]); $existing=$existing->fetch();

            // Cannot edit approved/locked marks
            if ($existing && in_array($existing['status'],['approved',STATUS_LOCKED,STATUS_PUBLISHED],true)) continue;

            // Cannot edit submitted marks if just saving draft
            if ($existing && $existing['status']===STATUS_SUBMITTED && $action==='save') continue;

            $oldStatus=$existing['status']??STATUS_DRAFT;
            $oldMarks =$existing['marks_obtained']??null;

            $pdo->prepare("INSERT INTO assessment_scores
                (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,status)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                marks_obtained=VALUES(marks_obtained),status=VALUES(status),
                entered_by=VALUES(entered_by),submitted_at=VALUES(submitted_at),updated_at=NOW()")
               ->execute([$stdId,$clsId,$subId,$cfgId,$ayId,$val,$maxM,$uid,
                           in_array($action,['submit','resubmit'])?date('Y-m-d H:i:s'):null,
                           $newStatus]);

            $scoreId=$existing?(int)$existing['id']:(int)$pdo->lastInsertId();
            if ($val!=$oldMarks || $newStatus!==$oldStatus) {
                recordMarksHistory($scoreId,$stdId,$subId,$cfgId,$ayId,$oldMarks,$val,$oldStatus,$newStatus,$reason);
            }
        }

        $msgs=['save'=>'Marks saved as draft.','submit'=>'Marks submitted for approval.','resubmit'=>'Marks resubmitted for review.'];
        flash('success',$msgs[$action]);
        if (in_array($action,['submit','resubmit'])) auditLog($action.'_marks','marks','assessment',$clsId,'','Class:'.$clsId.' Sub:'.$subId.' Cfg:'.$cfgId);
    }
    redirect(BASE_URL.'/admin/marks_entry.php?class_id='.$clsId.'&subject_id='.$subId.'&config_id='.$cfgId);
}

// ── Build dropdowns ───────────────────────────────────────────
$clsIds = $isTeacher ? myClassIds() : null;
if ($isTeacher && empty($clsIds)) {
    $pageTitle='Enter Marks'; $activeAdmin='marks_entry';
    require_once dirname(__DIR__).'/includes/admin_header.php';
    echo '<div class="alert alert-warning">You have no classes assigned for the current academic year. Contact the Academic Coordinator.</div>';
    require_once dirname(__DIR__).'/includes/admin_footer.php'; exit;
}

if ($isTeacher) {
    $in=implode(',',array_map('intval',$clsIds));
    $classes=$pdo->query("SELECT id,name FROM classes WHERE id IN ($in) ORDER BY name")->fetchAll();
} else {
    $classes=$pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId])?$pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll():[];
}
$subjects=$pdo->query("SELECT id,name FROM subjects WHERE is_active=1 ORDER BY name")->fetchAll();
$configs =$pdo->prepare("SELECT id,name,max_marks FROM assessment_configs WHERE academic_year_id=? AND is_active=1 ORDER BY sequence");
$configs->execute([$ayId]); $configs=$configs->fetchAll();

$selClass=(int)($_GET['class_id']??0);
$selSub  =(int)($_GET['subject_id']??0);
$selCfg  =(int)($_GET['config_id']??0);
$students=[]; $scoreRows=[]; $cfgData=null; $batchStatus=null;

if ($selClass&&$selSub&&$selCfg) {
    // Teacher access check
    if ($isTeacher&&$teacher) {
        $ok=$pdo->prepare("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND academic_year_id=?");
        $ok->execute([$teacher['id'],$selClass,$selSub,$ayId]);
        if (!(int)$ok->fetchColumn()) { $selClass=$selSub=$selCfg=0; }
    }
    if ($selClass) {
        $cfgData=$pdo->query("SELECT * FROM assessment_configs WHERE id=$selCfg")->fetch();
        $sts=$pdo->prepare("SELECT id,student_id,first_name,last_name FROM students WHERE current_class_id=? AND status='Active' ORDER BY last_name,first_name");
        $sts->execute([$selClass]); $students=$sts->fetchAll();
        $sc=$pdo->prepare("SELECT * FROM assessment_scores WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=?");
        $sc->execute([$selClass,$selSub,$selCfg,$ayId]); $scoreRows=array_column($sc->fetchAll(),null,'student_id');
        // Overall batch status (worst-case: if any are draft, batch is draft)
        $statuses=array_column($scoreRows,'status');
        if (in_array(STATUS_RETURNED,$statuses)) $batchStatus=STATUS_RETURNED;
        elseif (in_array(STATUS_APPROVED,$statuses)||in_array(STATUS_LOCKED,$statuses)||in_array(STATUS_PUBLISHED,$statuses)) $batchStatus=STATUS_APPROVED;
        elseif (in_array(STATUS_SUBMITTED,$statuses)||in_array(STATUS_RESUBMIT,$statuses)) $batchStatus=STATUS_SUBMITTED;
        elseif (empty($statuses)) $batchStatus=null;
        else $batchStatus=STATUS_DRAFT;
    }
}

$pageTitle='Enter Marks'; $activeAdmin='marks_entry';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Assessment <span></span></div>
    <h1>Enter Marks</h1>
    <p><?=$isTeacher?'Enter marks for your assigned classes. Approved marks cannot be changed.':'Enter or review student marks.'?></p>
  </div>
  <?php if (can('marks.review')): ?>
  <a href="<?=BASE_URL?>/admin/marks_approval.php" class="button button-secondary">🔍 Marks Approval</a>
  <?php endif; ?>
</div>

<!-- Workflow banner -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;padding:12px 16px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);font-size:12.5px;align-items:center">
  <span style="font-weight:700;color:var(--ink-soft)">STATUS FLOW:</span>
  <?=workflowBadge(STATUS_DRAFT)?> → <?=workflowBadge(STATUS_SUBMITTED)?> → <?=workflowBadge(STATUS_REVIEW)?> → <?=workflowBadge(STATUS_APPROVED)?>
  <span style="margin-left:8px;color:var(--ink-faint)">or</span> <?=workflowBadge(STATUS_RETURNED)?> → <?=workflowBadge(STATUS_RESUBMIT)?>
</div>

<!-- Selector -->
<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
    <option value="">Select Class…</option>
    <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$selClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?>
  </select>
  <select name="subject_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
    <option value="">Select Subject…</option>
    <?php foreach($subjects as $s):
      if ($isTeacher&&$selClass) {
          $tid=$teacher?$teacher['id']:0;
          $chk=$pdo->prepare("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND academic_year_id=?");
          $chk->execute([$tid,$selClass,$s['id'],$ayId]);
          if(!(int)$chk->fetchColumn()) continue;
      }
    ?>
    <option value="<?=$s['id']?>" <?=$selSub==$s['id']?'selected':''?>><?=e($s['name'])?></option>
    <?php endforeach; ?>
  </select>
  <select name="config_id" class="filter-button" onchange="this.form.submit()" style="min-width:200px">
    <option value="">Select Assessment…</option>
    <?php foreach($configs as $cfg): ?><option value="<?=$cfg['id']?>" <?=$selCfg==$cfg['id']?'selected':''?>><?=e($cfg['name'])?> (Max: <?=$cfg['max_marks']?>)</option><?php endforeach; ?>
  </select>
</form>

<?php if ($selClass&&$selSub&&$selCfg&&$cfgData): ?>
<?php
  $isLocked=in_array($batchStatus,[STATUS_APPROVED,STATUS_LOCKED,STATUS_PUBLISHED]);
  $isSubmitted=in_array($batchStatus,[STATUS_SUBMITTED,STATUS_RESUBMIT]);
  $isReturned=$batchStatus===STATUS_RETURNED;
  $canEdit=!$isLocked&&!$isSubmitted;
?>

<div class="form-section">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span><?=e($cfgData['name'])?> — Max marks: <strong><?=$cfgData['max_marks']?></strong></span>
    <div style="display:flex;align-items:center;gap:10px">
      <?php if($batchStatus): echo workflowBadge($batchStatus); endif; ?>
      <span class="status new-s"><?=count($students)?> students</span>
    </div>
  </div>

  <!-- Status-specific banners -->
  <?php if ($isReturned): ?>
  <div class="alert alert-warning">
    ↩ <strong>These marks were returned for correction.</strong>
    <?php
    $reason=$pdo->query("SELECT change_reason FROM marks_history WHERE assessment_config_id=$selCfg AND new_status='returned' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    if($reason) echo 'Reason: <em>'.e($reason).'</em>';
    ?> — Correct and resubmit.
  </div>
  <?php elseif ($isSubmitted): ?>
  <div class="alert alert-info">📤 Marks have been submitted and are awaiting review. You cannot edit them now.</div>
  <?php elseif ($isLocked): ?>
  <div class="alert alert-success">🔒 These marks are approved and locked. Contact the Academic Dean to request changes.</div>
  <?php endif; ?>

  <form method="post" id="marksForm">
    <?=csrfField()?>
    <input type="hidden" name="action"      value="save" id="marksAction"/>
    <input type="hidden" name="class_id"    value="<?=$selClass?>"/>
    <input type="hidden" name="subject_id"  value="<?=$selSub?>"/>
    <input type="hidden" name="config_id"   value="<?=$selCfg?>"/>
    <?php if($isReturned): ?><input type="hidden" name="reason" value="Corrected after return"/><?php endif; ?>

    <div class="table-wrap" style="margin-bottom:16px">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Marks / <?=$cfgData['max_marks']?></th><th>Percentage</th><th>Grade</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($students as $i=>$st):
            $sc=$scoreRows[$st['id']]??null;
            $rowLocked=$sc&&in_array($sc['status'],[STATUS_APPROVED,STATUS_LOCKED,STATUS_PUBLISHED,STATUS_SUBMITTED,STATUS_RESUBMIT],true);
            $pct=($sc&&$sc['marks_obtained']!==null&&$cfgData['max_marks']>0)?round($sc['marks_obtained']/$cfgData['max_marks']*100,1):null;
            $gl=$pct!==null?gradeLetter($pct,$ayId):'—';
            $rowStatus=$sc?$sc['status']:STATUS_DRAFT;
          ?>
          <tr>
            <td class="muted"><?=$i+1?></td>
            <td><strong><?=e($st['first_name'].' '.$st['last_name'])?></strong></td>
            <td class="muted"><?=e($st['student_id'])?></td>
            <td>
              <?php if ($rowLocked&&!$isReturned): ?>
                <span class="status approved"><?=fmtMark($sc['marks_obtained'])?></span>
                <input type="hidden" name="marks[<?=$st['id']?>]" value="<?=$sc['marks_obtained']?>"/>
              <?php else: ?>
                <input type="number"
                       name="marks[<?=$st['id']?>]"
                       value="<?=$sc?$sc['marks_obtained']:''?>"
                       min="0" max="<?=$cfgData['max_marks']?>" step="0.5"
                       <?=$canEdit||$isReturned?'':'readonly'?>
                       style="width:90px;padding:7px;border:1px solid var(--line);border-radius:6px;font-size:14px;<?=(!$canEdit&&!$isReturned)?'background:var(--bg-soft);color:var(--ink-faint)':''?>"
                       placeholder="—"
                       oninput="updateRow(this,<?=$cfgData['max_marks']?>,<?=$ayId?>,'row_<?=$st['id']?>')"/>
              <?php endif; ?>
            </td>
            <td id="pct_<?=$st['id']?>"><?=$pct!==null?fmtPct($pct):'—'?></td>
            <td id="grade_<?=$st['id']?>"><span class="status <?=in_array($gl,['A','B','C'])?'approved':($gl==='D'?'new-s':'warning')?>"><?=e($gl)?></span></td>
            <td><?=workflowBadge($rowStatus)?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($canEdit||$isReturned): ?>
    <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap">
      <button type="button" class="button button-secondary" onclick="setAction('save')">💾 Save Draft</button>
      <button type="button" class="button button-primary"
              onclick="<?=$isReturned?"setAction('resubmit')":"setAction('submit')"?>;if(confirm('<?=$isReturned?'Resubmit corrected marks for review?':'Submit marks for approval? You will not be able to edit them until reviewed.'?>'))document.getElementById('marksForm').submit()">
        📤 <?=$isReturned?'Resubmit for Review':'Submit for Approval'?>
      </button>
    </div>
    <?php elseif ($isSubmitted): ?>
    <div class="alert alert-info" style="margin-top:0">Marks submitted. Awaiting review by <?=isVicePrincipal()?'Principal':'Vice Principal/Principal'?>.</div>
    <?php endif; ?>
  </form>

  <!-- Version history -->
  <?php
  if (!empty($scoreRows)) {
      $anyScoreId=reset($scoreRows)['id']??0;
      if ($anyScoreId) {
          $history=$pdo->query("SELECT mh.*,u.name changer FROM marks_history mh LEFT JOIN users u ON u.id=mh.changed_by WHERE mh.assessment_config_id=$selCfg AND mh.student_id IN (".implode(',',array_keys($scoreRows)).") ORDER BY mh.created_at DESC LIMIT 20")->fetchAll();
          if (!empty($history) && can('marks.history')):
  ?>
  <div style="margin-top:20px;border-top:1px solid var(--line-soft);padding-top:16px">
    <strong style="font-size:13px;color:var(--ink-soft)">📋 Change History (last 20)</strong>
    <div style="margin-top:8px;font-size:12.5px">
      <?php foreach($history as $h): ?>
      <div style="padding:6px 0;border-bottom:1px solid var(--line-soft);display:flex;gap:12px">
        <span class="muted"><?=date('M d H:i',strtotime($h['created_at']))?></span>
        <span><?=workflowBadge($h['old_status'])?> → <?=workflowBadge($h['new_status'])?></span>
        <span>Marks: <?=fmtMark($h['old_marks'])?> → <strong><?=fmtMark($h['new_marks'])?></strong></span>
        <span class="muted">by <?=e($h['changer']??'System')?></span>
        <?php if($h['change_reason']): ?><span style="color:var(--warning)">↩ <?=e($h['change_reason'])?></span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; }} ?>
</div>
<?php else: ?>
<div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center">
  <div style="font-size:36px;margin-bottom:12px">✏️</div>
  <p style="color:var(--ink-soft)">Select class, subject, and assessment period above to enter marks.</p>
</div>
<?php endif; ?>

<script>
function setAction(a){ document.getElementById('marksAction').value=a; }
function updateRow(input, max, ayId, rowId) {
  const v=parseFloat(input.value);
  const pctEl=document.getElementById('pct_'+input.name.match(/\[(\d+)\]/)[1]);
  const grEl =document.getElementById('grade_'+input.name.match(/\[(\d+)\]/)[1]);
  if(isNaN(v)||!pctEl) return;
  const pct=Math.round((v/max)*1000)/10;
  pctEl.textContent=pct+'%';
}
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
