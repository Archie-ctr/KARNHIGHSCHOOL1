<?php
// ── Marks Approval endpoint (called by approval_center.php and standalone page)
require_once dirname(__DIR__).'/config/db.php';
requirePermission('marks.review');

$pdo  = db();
$ayId = currentAcademicYearId();
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (stripos($_SERVER['CONTENT_TYPE']??'','multipart')!==false && isset($_POST['action']));

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action   = $_POST['action'] ?? '';
    $clsId    = (int)($_POST['class_id']  ?? 0);
    $subId    = (int)($_POST['subject_id'] ?? 0);
    $cfgId    = (int)($_POST['config_id'] ?? 0);
    $reason   = trim($_POST['reason'] ?? $_POST['decision_note'] ?? '');

    if ($action==='approve' && $clsId && $subId && $cfgId) {
        $scores=$pdo->prepare("SELECT * FROM assessment_scores WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status IN ('submitted','resubmitted')");
        $scores->execute([$clsId,$subId,$cfgId,$ayId]); $scores=$scores->fetchAll();
        foreach ($scores as $sc) {
            $pdo->prepare("UPDATE assessment_scores SET status='approved',approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([currentUserId(),$sc['id']]);
            recordMarksHistory($sc['id'],$sc['student_id'],$sc['subject_id'],$sc['assessment_config_id'],$sc['academic_year_id'],$sc['marks_obtained'],$sc['marks_obtained'],$sc['status'],'approved',$reason);
        }
        auditLog('approve_marks','marks','assessment_score',$clsId,'submitted','approved');
        if ($isAjax) json_out(['ok'=>true,'count'=>count($scores)]);
        flash('success',count($scores).' mark records approved.');

    } elseif ($action==='return' && $clsId && $subId && $cfgId) {
        $scores=$pdo->prepare("SELECT * FROM assessment_scores WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status IN ('submitted','resubmitted')");
        $scores->execute([$clsId,$subId,$cfgId,$ayId]); $scores=$scores->fetchAll();
        foreach ($scores as $sc) {
            $pdo->prepare("UPDATE assessment_scores SET status='returned',updated_at=NOW() WHERE id=?")->execute([$sc['id']]);
            recordMarksHistory($sc['id'],$sc['student_id'],$sc['subject_id'],$sc['assessment_config_id'],$sc['academic_year_id'],$sc['marks_obtained'],$sc['marks_obtained'],$sc['status'],'returned',$reason);
        }
        auditLog('return_marks','marks','assessment_score',$clsId,'submitted','returned');
        if ($isAjax) json_out(['ok'=>true]);
        flash('warning','Marks returned to teacher for correction.');

    } elseif ($action==='reject' && $clsId && $subId && $cfgId) {
        $pdo->prepare("UPDATE assessment_scores SET status='rejected',updated_at=NOW() WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status IN ('submitted','resubmitted')")
           ->execute([$clsId,$subId,$cfgId,$ayId]);
        auditLog('reject_marks','marks','assessment_score',$clsId,'submitted','rejected');
        if ($isAjax) json_out(['ok'=>true]);
        flash('error','Marks rejected.');
    }
    redirect(BASE_URL.'/admin/marks_approval.php');
}

// ── View: all submitted mark batches ─────────────────────────
$batches=$pdo->prepare("SELECT asc2.class_id,asc2.subject_id,asc2.assessment_config_id,c.name cname,s.name sname,ac.name cfg_name,asc2.status,COUNT(*) student_count,SUM(asc2.marks_obtained) total_marks,AVG(asc2.marks_obtained/asc2.max_marks*100) avg_pct,MAX(asc2.updated_at) last_updated,u.name entered_by FROM assessment_scores asc2 JOIN classes c ON c.id=asc2.class_id JOIN subjects s ON s.id=asc2.subject_id JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id LEFT JOIN users u ON u.id=asc2.entered_by WHERE asc2.status IN ('submitted','resubmitted') AND asc2.academic_year_id=? GROUP BY asc2.class_id,asc2.subject_id,asc2.assessment_config_id ORDER BY last_updated ASC");
$batches->execute([$ayId]); $batches=$batches->fetchAll();

$pageTitle   = 'Marks Approval';
$activeAdmin = 'marks_approval';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Assessment Workflow <span></span></div>
    <h1>Marks Approval</h1>
    <p>Review and approve submitted teacher marks.</p>
  </div>
  <?php if (!empty($batches)): ?>
  <span class="status new-s" style="font-size:14px;padding:8px 16px"><?=count($batches)?> batch<?=count($batches)!=1?'es':''?> awaiting review</span>
  <?php endif; ?>
</div>

<!-- Workflow legend -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;padding:14px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <span style="font-size:12.5px;font-weight:700;color:var(--ink-soft)">WORKFLOW:</span>
  <?php foreach([STATUS_DRAFT=>'Teacher drafts',STATUS_SUBMITTED=>'Teacher submits',STATUS_REVIEW=>'VP reviews',STATUS_APPROVED=>'Approved',STATUS_RETURNED=>'Returned for correction',STATUS_RESUBMIT=>'Teacher resubmits',STATUS_PUBLISHED=>'Published'] as $s=>$label): ?>
  <span><?=workflowBadge($s)?> <span style="font-size:11px;color:var(--ink-faint)"><?=$label?></span></span>
  <?php endforeach; ?>
</div>

<?php if (empty($batches)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">✅</div>
  <h3>No marks awaiting approval</h3>
  <p style="color:var(--ink-soft)">All submitted marks have been processed.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($batches as $b):
    $avgColor=$b['avg_pct']>=70?'var(--green)':($b['avg_pct']>=50?'var(--warning)':'var(--error)');
  ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid var(--line-soft);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <strong style="font-size:16px"><?=e($b['cname'])?> — <?=e($b['sname'])?></strong>
        <span style="margin-left:10px;font-size:13px;color:var(--ink-faint)"><?=e($b['cfg_name'])?></span>
        <?=workflowBadge($b['status'])?>
        <div style="font-size:12px;color:var(--ink-faint);margin-top:4px">
          Entered by: <strong><?=e($b['entered_by']??'—')?></strong>
          · <?=(int)$b['student_count']?> students
          · Avg: <strong style="color:<?=$avgColor?>"><?=round($b['avg_pct']??0,1)?>%</strong>
          · Last updated: <?=date('M d, Y H:i',strtotime($b['last_updated']))?>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button onclick="toggleDetail('bd_<?=md5($b['class_id'].':'.$b['subject_id'].':'.$b['assessment_config_id'])?>');this.textContent=this.textContent.includes('View')?'Hide scores':'👁 View scores'"
                class="filter-button button-sm">👁 View scores</button>
        <form method="post" style="display:inline" onsubmit="return confirm('Approve all marks for this batch?')">
          <?=csrfField()?>
          <input type="hidden" name="action"     value="approve"/>
          <input type="hidden" name="class_id"   value="<?=$b['class_id']?>"/>
          <input type="hidden" name="subject_id" value="<?=$b['subject_id']?>"/>
          <input type="hidden" name="config_id"  value="<?=$b['assessment_config_id']?>"/>
          <button type="submit" class="button button-success button-sm">✅ Approve</button>
        </form>
        <button class="button button-secondary button-sm" onclick="this.nextElementSibling.style.display='block';this.style.display='none'">↩ Return</button>
        <form method="post" style="display:none">
          <?=csrfField()?>
          <input type="hidden" name="action"     value="return"/>
          <input type="hidden" name="class_id"   value="<?=$b['class_id']?>"/>
          <input type="hidden" name="subject_id" value="<?=$b['subject_id']?>"/>
          <input type="hidden" name="config_id"  value="<?=$b['assessment_config_id']?>"/>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" name="reason" placeholder="Reason for return…" required style="padding:7px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px;width:240px"/>
            <button type="submit" class="button button-warning button-sm">Send Back</button>
          </div>
        </form>
        <form method="post" onsubmit="return confirm('Reject these marks? This cannot be undone.')" style="display:inline">
          <?=csrfField()?>
          <input type="hidden" name="action"     value="reject"/>
          <input type="hidden" name="class_id"   value="<?=$b['class_id']?>"/>
          <input type="hidden" name="subject_id" value="<?=$b['subject_id']?>"/>
          <input type="hidden" name="config_id"  value="<?=$b['assessment_config_id']?>"/>
          <button type="submit" class="button button-danger button-sm">❌ Reject</button>
        </form>
      </div>
    </div>
    <!-- Score details (collapsed by default) -->
    <?php
    $detailId='bd_'.md5($b['class_id'].':'.$b['subject_id'].':'.$b['assessment_config_id']);
    $scores=$pdo->prepare("SELECT asc2.*,s.first_name,s.last_name,s.student_id sid FROM assessment_scores asc2 JOIN students s ON s.id=asc2.student_id WHERE asc2.class_id=? AND asc2.subject_id=? AND asc2.assessment_config_id=? AND asc2.academic_year_id=? ORDER BY s.last_name,s.first_name");
    $scores->execute([$b['class_id'],$b['subject_id'],$b['assessment_config_id'],$ayId]); $scores=$scores->fetchAll();
    ?>
    <div id="<?=$detailId?>" style="display:none">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:var(--bg)">
          <th style="padding:8px 16px;text-align:left">Student</th>
          <th style="padding:8px 16px;text-align:center">Marks</th>
          <th style="padding:8px 16px;text-align:center">Max</th>
          <th style="padding:8px 16px;text-align:center">%</th>
          <th style="padding:8px 16px;text-align:center">Grade</th>
          <th style="padding:8px 16px">Status</th>
        </tr></thead>
        <tbody>
          <?php foreach($scores as $sc):
            $pct=$sc['max_marks']>0?round($sc['marks_obtained']/$sc['max_marks']*100,1):0;
            $gl=gradeLetter($pct,$ayId);
          ?>
          <tr style="border-top:1px solid var(--line-soft)">
            <td style="padding:8px 16px"><strong><?=e($sc['first_name'].' '.$sc['last_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($sc['sid'])?></div></td>
            <td style="text-align:center;padding:8px 16px;font-weight:700;font-size:15px"><?=fmtMark($sc['marks_obtained'])?></td>
            <td style="text-align:center;padding:8px 16px;color:var(--ink-faint)"><?=$sc['max_marks']?></td>
            <td style="text-align:center;padding:8px 16px"><?=fmtPct($pct)?></td>
            <td style="text-align:center;padding:8px 16px"><span class="status <?=in_array($gl,['A','B','C'])?'approved':($gl==='D'?'new-s':'warning')?>"><?=e($gl)?></span></td>
            <td style="padding:8px 16px"><?=workflowBadge($sc['status'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
