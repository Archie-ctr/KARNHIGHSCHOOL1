<?php
require_once dirname(__DIR__).'/config/db.php';
requirePermission('approvals.act');

$pdo   = db();
$ayId  = currentAcademicYearId();
$role  = currentRole();

// ── Handle approval actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $reqId  = (int)($_POST['req_id']  ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['decision_note'] ?? '');
    $allowed= ['approve','return','reject'];

    if ($reqId && in_array($action,$allowed,true)) {
        $req = $pdo->query("SELECT * FROM approval_requests WHERE id=$reqId")->fetch();
        if ($req && $req['status']==='pending') {
            $newStatus= match($action){'approve'=>'approved','reject'=>'rejected',default=>'returned'};
            $pdo->prepare("UPDATE approval_requests SET status=?,decision_by=?,decision_at=NOW(),decision_note=?,updated_at=NOW() WHERE id=?")
               ->execute([$newStatus,currentUserId(),$note?:null,$reqId]);

            // Act on the underlying record
            if ($req['module']==='marks') {
                if ($action==='approve') {
                    $pdo->prepare("UPDATE assessment_scores SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?")
                       ->execute([currentUserId(),$req['record_id']]);
                } elseif ($action==='return') {
                    $pdo->prepare("UPDATE assessment_scores SET status='returned',updated_at=NOW() WHERE id=?")
                       ->execute([$req['record_id']]);
                } elseif ($action==='reject') {
                    $pdo->prepare("UPDATE assessment_scores SET status='rejected',updated_at=NOW() WHERE id=?")
                       ->execute([$req['record_id']]);
                }
            } elseif ($req['module']==='admissions') {
                if ($action==='approve') {
                    $pdo->prepare("UPDATE applications SET status='Approved for entrance',entrance_letter_ref=IFNULL(entrance_letter_ref,CONCAT('KEL-',YEAR(NOW()),'-',LPAD(id,5,'0'))),reviewed_by=?,reviewed_at=NOW() WHERE id=?")
                       ->execute([currentUserId(),$req['record_id']]);
                } elseif ($action==='reject') {
                    $pdo->prepare("UPDATE applications SET status='Rejected',decision_by=?,decision_at=NOW() WHERE id=?")->execute([currentUserId(),$req['record_id']]);
                }
            } elseif ($req['module']==='attendance') {
                if ($action==='approve') {
                    $pdo->prepare("UPDATE attendance SET session_status='approved' WHERE id=?")->execute([$req['record_id']]);
                } elseif ($action==='return') {
                    $pdo->prepare("UPDATE attendance SET session_status='draft' WHERE id=?")->execute([$req['record_id']]);
                }
            } elseif ($req['module']==='discipline') {
                if ($action==='approve') {
                    $pdo->prepare("UPDATE discipline_records SET resolved=1 WHERE id=?")->execute([$req['record_id']]);
                }
            }

            auditLog($action.'_approval','approvals','approval_request',$reqId,$req['status'],$newStatus);
            flash('success','Request '.ucfirst($action).'d: '.$req['title']);
        }
    }
    redirect(BASE_URL.'/admin/approval_center.php');
}

// ── Fetch pending requests ─────────────────────────────────────
$module  = trim($_GET['module'] ?? '');
$wsql    = $module ? "WHERE ar.module=? AND ar.status='pending'" : "WHERE ar.status='pending'";
$wparams = $module ? [$module] : [];
$pending = $pdo->prepare("SELECT ar.*,u.name requester_name,r.label role_label FROM approval_requests ar JOIN users u ON u.id=ar.requested_by JOIN roles r ON r.id=u.role_id $wsql ORDER BY ar.priority DESC, ar.created_at ASC LIMIT 60");
$pending->execute($wparams); $pending=$pending->fetchAll();

// Module counts
$counts=$pdo->query("SELECT module, COUNT(*) cnt FROM approval_requests WHERE status='pending' GROUP BY module ORDER BY cnt DESC")->fetchAll();
$total=array_sum(array_column($counts,'cnt'));

// Also fetch pending marks specifically for VP/Principal
$pendingMarks=[]; $pendingAdmissions=[]; $pendingDiscipline=[];
if (can('marks.review')) {
    $pendingMarks=$pdo->prepare("SELECT asc2.*,s.first_name,s.last_name,s.student_id sid,sub.name sname,c.name cname,ac.name cfg_name,u.name entered_by_name FROM assessment_scores asc2 JOIN students s ON s.id=asc2.student_id JOIN subjects sub ON sub.id=asc2.subject_id JOIN classes c ON c.id=asc2.class_id JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id LEFT JOIN users u ON u.id=asc2.entered_by WHERE asc2.status IN ('submitted','resubmitted') AND asc2.academic_year_id=? ORDER BY asc2.updated_at ASC LIMIT 30")
        ->execute([$ayId]) ? $pdo->query("SELECT asc2.*,s.first_name,s.last_name,s.student_id sid,sub.name sname,c.name cname,ac.name cfg_name,u.name entered_by_name FROM assessment_scores asc2 JOIN students s ON s.id=asc2.student_id JOIN subjects sub ON sub.id=asc2.subject_id JOIN classes c ON c.id=asc2.class_id JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id LEFT JOIN users u ON u.id=asc2.entered_by WHERE asc2.status IN ('submitted','resubmitted') AND asc2.academic_year_id=$ayId ORDER BY asc2.updated_at ASC LIMIT 30")->fetchAll() : [];
}
if (can('admissions.approve')) {
    $pendingAdmissions=$pdo->query("SELECT * FROM applications WHERE status IN ('Application Submitted','Under Review','Approved for entrance') ORDER BY created_at DESC LIMIT 20")->fetchAll();
}
if (can('discipline.approve')) {
    $pendingDiscipline=$pdo->query("SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname FROM discipline_records d JOIN students s ON s.id=d.student_id WHERE d.resolved=0 ORDER BY d.incident_date DESC LIMIT 20")->fetchAll();
}

$pageTitle   = 'Approval Center';
$activeAdmin = 'approval_center';
require_once dirname(__DIR__).'/includes/admin_header.php';

$priorityColor=['high'=>'var(--error)','normal'=>'var(--blue)','low'=>'var(--ink-faint)'];
$moduleIcon=['marks'=>'✏️','admissions'=>'📋','attendance'=>'📆','discipline'=>'⚖️','finance'=>'💰','promotion'=>'⬆️','graduation'=>'🎓'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Workflow <span></span></div>
    <h1>Approval Center</h1>
    <p>Items awaiting your review and decision.</p>
  </div>
  <?php if ($total>0): ?>
  <span style="background:var(--error);color:#fff;padding:8px 18px;border-radius:20px;font-size:15px;font-weight:800"><?=$total?> pending</span>
  <?php endif; ?>
</div>

<!-- Module filter pills -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
  <a href="?" class="filter-button" style="<?=!$module?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">All (<?=$total?>)</a>
  <?php foreach($counts as $c):
    $ico=$moduleIcon[$c['module']]??'📋';
  ?>
  <a href="?module=<?=e($c['module'])?>" class="filter-button" style="<?=$module===$c['module']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">
    <?=$ico?> <?=e(ucfirst($c['module']))?> <span style="background:var(--error);color:#fff;border-radius:12px;padding:1px 7px;font-size:11px;margin-left:4px"><?=$c['cnt']?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══ MARKS APPROVAL (detailed per-score view) ══════════════ -->
<?php if (!empty($pendingMarks) && (!$module || $module==='marks')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">
    ✏️ Submitted Marks <span class="status new-s" style="margin-left:8px"><?=count($pendingMarks)?> records</span>
  </div>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:14px">These marks have been submitted by teachers and are awaiting your approval.</p>

  <?php
  // Group by class + subject + assessment config
  $grouped=[];
  foreach ($pendingMarks as $m) {
      $key=$m['class_id'].':'.$m['subject_id'].':'.$m['assessment_config_id'];
      $grouped[$key]['meta']=$m;
      $grouped[$key]['scores'][]=$m;
  }
  ?>
  <?php foreach($grouped as $key=>$group):
    $meta=$group['meta'];
    $scores=$group['scores'];
    $groupId='mg_'.md5($key);
  ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);margin-bottom:14px;overflow:hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:var(--bg);border-bottom:1px solid var(--line-soft);flex-wrap:wrap;gap:8px">
      <div>
        <strong style="font-size:14.5px"><?=e($meta['cname'])?> — <?=e($meta['sname'])?></strong>
        <span style="margin-left:10px;font-size:12px;color:var(--ink-faint)"><?=e($meta['cfg_name'])?></span>
        <?=workflowBadge($meta['status'])?>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:12.5px;color:var(--ink-faint)">Entered by: <?=e($meta['entered_by_name']??'—')?></span>
        <button class="filter-button button-sm" onclick="toggleDetail('<?=$groupId?>')">👁 View scores</button>

        <!-- Approve all in this group -->
        <form method="post" style="display:inline" onsubmit="return confirm('Approve all marks for <?=e(addslashes($meta['cname'].' '.$meta['cfg_name']))?>')">
          <?=csrfField()?>
          <input type="hidden" name="action"  value="approve_marks_group"/>
          <input type="hidden" name="class_id"   value="<?=$meta['class_id']?>"/>
          <input type="hidden" name="subject_id" value="<?=$meta['subject_id']?>"/>
          <input type="hidden" name="config_id"  value="<?=$meta['assessment_config_id']?>"/>
          <?php
            // Inline approve group (re-use existing endpoint)
          ?>
          <button type="button" onclick="approveGroup(<?=$meta['class_id']?>,<?=$meta['subject_id']?>,<?=$meta['assessment_config_id']?>)" class="button button-success button-sm">✅ Approve</button>
        </form>
        <!-- Return group -->
        <button class="button button-sm button-secondary" onclick="returnGroup(<?=$meta['class_id']?>,<?=$meta['subject_id']?>,<?=$meta['assessment_config_id']?>,'<?=e(addslashes($meta['cname'].' — '.$meta['sname']))?>')">↩ Return</button>
      </div>
    </div>

    <div id="<?=$groupId?>" style="display:none">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:var(--bg)"><th style="padding:8px 14px;text-align:left">Student</th><th style="padding:8px 14px;text-align:center">Marks</th><th style="padding:8px 14px;text-align:center">Max</th><th style="padding:8px 14px;text-align:center">%</th><th style="padding:8px 14px">Status</th></tr></thead>
        <tbody>
          <?php foreach($scores as $sc):
            $pct=$sc['max_marks']>0?round($sc['marks_obtained']/$sc['max_marks']*100,1):0;
          ?>
          <tr style="border-top:1px solid var(--line-soft)">
            <td style="padding:8px 14px"><strong><?=e($sc['first_name'].' '.$sc['last_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($sc['sid'])?></div></td>
            <td style="text-align:center;padding:8px 14px;font-weight:700"><?=fmtMark($sc['marks_obtained'])?></td>
            <td style="text-align:center;padding:8px 14px;color:var(--ink-faint)"><?=$sc['max_marks']?></td>
            <td style="text-align:center;padding:8px 14px"><?=fmtPct($pct)?></td>
            <td style="padding:8px 14px"><?=workflowBadge($sc['status'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ ADMISSIONS PENDING ═══════════════════════════════════ -->
<?php if (!empty($pendingAdmissions) && (!$module || $module==='admissions')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">📋 Pending Admissions <span class="status pending" style="margin-left:8px"><?=count($pendingAdmissions)?></span></div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Applicant</th><th>App #</th><th>Grade</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($pendingAdmissions as $app):
          $ini=strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1));
        ?>
        <tr>
          <td><div class="person"><div class="avatar-sm"><?=e($ini)?></div><strong><?=e($app['first_name'].' '.$app['last_name'])?></strong></div></td>
          <td class="muted"><?=e($app['application_number'])?></td>
          <td><?=e($app['grade_applying_for'])?></td>
          <td><?=statusBadge($app['status'])?></td>
          <td class="muted"><?=date('M d, Y',strtotime($app['created_at']))?></td>
          <td>
            <a href="<?=BASE_URL?>/admin/applications.php?q=<?=urlencode($app['application_number'])?>" class="filter-button button-sm">Review →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ══ DISCIPLINE PENDING ═══════════════════════════════════ -->
<?php if (!empty($pendingDiscipline) && (!$module || $module==='discipline')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">⚖️ Open Discipline Cases <span class="status warning" style="margin-left:8px"><?=count($pendingDiscipline)?></span></div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Student</th><th>Date</th><th>Category</th><th>Action Recommended</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($pendingDiscipline as $d): ?>
        <tr>
          <td><strong><?=e($d['sname'])?></strong></td>
          <td class="muted"><?=date('M d, Y',strtotime($d['incident_date']))?></td>
          <td><span class="status pending"><?=e($d['category'])?></span></td>
          <td><span class="status <?=$d['action_taken']==='Suspension'?'warning':'new-s'?>"><?=e($d['action_taken'])?></span></td>
          <td>
            <a href="<?=BASE_URL?>/admin/discipline.php" class="filter-button button-sm">Review →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ══ GENERAL APPROVAL REQUESTS ════════════════════════════ -->
<?php if (!empty($pending)): ?>
<div class="form-section">
  <div class="form-section-title">📋 Other Pending Requests (<?=count($pending)?>)</div>
  <?php foreach($pending as $req): ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?=$priorityColor[$req['priority']]?>'><?=$req['priority']?> priority · <?=e($req['module'])?></span>
        <h3 style="font-size:15px;font-weight:700;margin:4px 0 6px"><?=e($req['title'])?></h3>
        <p style="font-size:13.5px;color:var(--ink-soft)"><?=e($req['description']??'')?></p>
        <div style="margin-top:8px;font-size:12px;color:var(--ink-faint)">
          Requested by: <strong><?=e($req['requester_name'])?></strong> (<?=e($req['role_label'])?>)
          · <?=date('M d, Y H:i',strtotime($req['created_at']))?>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:180px">
        <form method="post">
          <?=csrfField()?>
          <input type="hidden" name="req_id" value="<?=$req['id']?>"/>
          <input type="hidden" name="action" value="approve"/>
          <button type="submit" class="button button-success button-sm full">✅ Approve</button>
        </form>
        <button class="button button-secondary button-sm" onclick="document.getElementById('return_<?=$req['id']?>').style.display='block'">↩ Return</button>
        <form method="post" onsubmit="return confirm('Reject this request?')">
          <?=csrfField()?>
          <input type="hidden" name="req_id" value="<?=$req['id']?>"/>
          <input type="hidden" name="action" value="reject"/>
          <button type="submit" class="button button-danger button-sm full">❌ Reject</button>
        </form>
        <div id="return_<?=$req['id']?>" style="display:none;margin-top:6px">
          <form method="post">
            <?=csrfField()?>
            <input type="hidden" name="req_id" value="<?=$req['id']?>"/>
            <input type="hidden" name="action" value="return"/>
            <textarea name="decision_note" placeholder="Reason for return…" rows="2" style="width:100%;padding:7px;border:1px solid var(--line);border-radius:6px;font-size:13px;resize:vertical"></textarea>
            <button type="submit" class="button button-secondary button-sm full" style="margin-top:6px">Send Back</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($pendingMarks)&&empty($pendingAdmissions)&&empty($pendingDiscipline)&&empty($pending)): ?>
<div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:48px;margin-bottom:14px">✅</div>
  <h3 style="margin-bottom:8px">All clear!</h3>
  <p style="color:var(--ink-soft)">No pending approvals at this time. Come back later.</p>
</div>
<?php endif; ?>

<script>
function toggleDetail(id) {
  const el=document.getElementById(id);
  if(el) el.style.display=el.style.display==='none'?'block':'none';
}

async function approveGroup(classId, subjectId, configId) {
  if (!confirm('Approve all submitted marks for this group?')) return;
  const fd=new FormData();
  fd.append('csrf_token','<?=csrfToken()?>');
  fd.append('action','approve');
  fd.append('class_id',classId);
  fd.append('subject_id',subjectId);
  fd.append('config_id',configId);
  const res=await fetch('<?=BASE_URL?>/admin/marks_approval.php',{method:'POST',body:fd});
  if(res.ok) { Toast.success('Marks approved.'); setTimeout(()=>location.reload(),1200); }
  else Toast.error('Failed. Please try again.');
}

function returnGroup(classId, subjectId, configId, label) {
  const reason=prompt('Return marks for: '+label+'\n\nEnter reason for teacher:');
  if (reason===null) return;
  const fd=new FormData();
  fd.append('csrf_token','<?=csrfToken()?>');
  fd.append('action','return');
  fd.append('class_id',classId);
  fd.append('subject_id',subjectId);
  fd.append('config_id',configId);
  fd.append('reason',reason);
  fetch('<?=BASE_URL?>/admin/marks_approval.php',{method:'POST',body:fd}).then(()=>{
    Toast.success('Marks returned to teacher.');
    setTimeout(()=>location.reload(),1200);
  });
}
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
