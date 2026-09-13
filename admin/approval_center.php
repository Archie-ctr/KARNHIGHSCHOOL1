<?php
require_once dirname(__DIR__).'/config/db.php';
requirePermission('approvals.act');

$pdo  = db();
$ayId = currentAcademicYearId();
$role = currentRole();

// ── Handle approval actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['decision_note'] ?? '');

    // ── Approval-request based (general queue) ────────────────
    $reqId = (int)($_POST['req_id'] ?? 0);
    if ($reqId && in_array($action, ['approve','return','reject'], true)) {
        $req = $pdo->query("SELECT * FROM approval_requests WHERE id=$reqId")->fetch();
        if ($req && $req['status'] === 'pending') {
            $newStatus = match($action) { 'approve'=>'approved','reject'=>'rejected', default=>'returned' };
            $pdo->prepare("UPDATE approval_requests SET status=?,decision_by=?,decision_at=NOW(),decision_note=?,updated_at=NOW() WHERE id=?")
               ->execute([$newStatus, currentUserId(), $note ?: null, $reqId]);

            if ($req['module'] === 'marks') {
                if ($action === 'approve')
                    $pdo->prepare("UPDATE assessment_scores SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?")->execute([currentUserId(),$req['record_id']]);
                elseif ($action === 'return')
                    $pdo->prepare("UPDATE assessment_scores SET status='returned',updated_at=NOW() WHERE id=?")->execute([$req['record_id']]);
                elseif ($action === 'reject')
                    $pdo->prepare("UPDATE assessment_scores SET status='rejected',updated_at=NOW() WHERE id=?")->execute([$req['record_id']]);
            } elseif ($req['module'] === 'admissions') {
                if ($action === 'approve')
                    $pdo->prepare("UPDATE applications SET status='Approved for entrance',entrance_letter_ref=IFNULL(entrance_letter_ref,CONCAT('KEL-',YEAR(NOW()),'-',LPAD(id,5,'0'))),reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([currentUserId(),$req['record_id']]);
                elseif ($action === 'reject')
                    $pdo->prepare("UPDATE applications SET status='Rejected',decision_by=?,decision_at=NOW() WHERE id=?")->execute([currentUserId(),$req['record_id']]);
            } elseif ($req['module'] === 'attendance') {
                if ($action === 'approve')
                    $pdo->prepare("UPDATE attendance SET session_status='approved' WHERE id=?")->execute([$req['record_id']]);
                elseif ($action === 'return')
                    $pdo->prepare("UPDATE attendance SET session_status='draft' WHERE id=?")->execute([$req['record_id']]);
            } elseif ($req['module'] === 'discipline') {
                if ($action === 'approve')
                    $pdo->prepare("UPDATE discipline_records SET resolved=1 WHERE id=?")->execute([$req['record_id']]);
            }
            auditLog($action.'_approval','approvals','approval_request',$reqId,$req['status'],$newStatus);
            flash('success','Request '.ucfirst($action).'d: '.$req['title']);
        }
    }

    // ── Marks group approval/return ────────────────────────────
    elseif (in_array($action, ['approve_marks_group','return_marks_group'], true)) {
        $classId  = (int)($_POST['class_id']  ?? 0);
        $subId    = (int)($_POST['subject_id'] ?? 0);
        $cfgId    = (int)($_POST['config_id']  ?? 0);
        if ($classId && $subId && $cfgId) {
            if ($action === 'approve_marks_group') {
                $pdo->prepare("UPDATE assessment_scores SET status='approved',approved_by=?,approved_at=NOW() WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status IN ('submitted','resubmitted')")
                   ->execute([currentUserId(),$classId,$subId,$cfgId,$ayId]);
                flash('success','Marks approved for group.');
            } else {
                $pdo->prepare("UPDATE assessment_scores SET status='returned',updated_at=NOW() WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status IN ('submitted','resubmitted')")
                   ->execute([$classId,$subId,$cfgId,$ayId]);
                flash('warning','Marks returned to teacher. Note: '.$note);
            }
        }
    }
    redirect(BASE_URL.'/admin/approval_center.php');
}

// ── Fetch data ────────────────────────────────────────────────
$module  = trim($_GET['module'] ?? '');
$wsql    = $module ? "WHERE ar.module=? AND ar.status='pending'" : "WHERE ar.status='pending'";
$wparams = $module ? [$module] : [];
$pending = $pdo->prepare("SELECT ar.*,u.name requester_name,r.label role_label FROM approval_requests ar JOIN users u ON u.id=ar.requested_by JOIN roles r ON r.id=u.role_id $wsql ORDER BY ar.priority DESC, ar.created_at ASC LIMIT 60");
$pending->execute($wparams);
$pending = $pending->fetchAll();

$counts = $pdo->query("SELECT module,COUNT(*) cnt FROM approval_requests WHERE status='pending' GROUP BY module ORDER BY cnt DESC")->fetchAll();
$total  = array_sum(array_column($counts,'cnt'));

$pendingMarks = $pendingAdmissions = $pendingDiscipline = [];
if (can('marks.review')) {
    $pendingMarks = $pdo->query(
        "SELECT asc2.*,s.first_name,s.last_name,s.student_id sid,
                sub.name sname,c.name cname,ac.name cfg_name,u.name entered_by_name
         FROM assessment_scores asc2
         JOIN students s ON s.id=asc2.student_id
         JOIN subjects sub ON sub.id=asc2.subject_id
         JOIN classes c ON c.id=asc2.class_id
         JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
         LEFT JOIN users u ON u.id=asc2.entered_by
         WHERE asc2.status IN ('submitted','resubmitted') AND asc2.academic_year_id=$ayId
         ORDER BY asc2.updated_at ASC LIMIT 30"
    )->fetchAll();
}
if (canAny(['admissions.approve','admissions.recommend'])) {
    $pendingAdmissions = $pdo->query("SELECT * FROM applications WHERE status IN ('Application Submitted','Under Review','Approved for entrance') ORDER BY created_at DESC LIMIT 20")->fetchAll();
}
if (can('discipline.approve')) {
    $pendingDiscipline = $pdo->query("SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname FROM discipline_records d JOIN students s ON s.id=d.student_id WHERE d.resolved=0 ORDER BY d.incident_date DESC LIMIT 20")->fetchAll();
}

$pageTitle   = 'Approval Center';
$activeAdmin = 'approval_center';
require_once dirname(__DIR__).'/includes/admin_header.php';

$priorityColor = ['high'=>'var(--error)','normal'=>'var(--blue)','low'=>'var(--ink-faint)'];
$moduleIcon    = ['marks'=>'✏️','admissions'=>'📋','attendance'=>'📆','discipline'=>'⚖️','finance'=>'💰','promotion'=>'⬆️','graduation'=>'🎓'];
$priorityBg    = ['high'=>'#fef2f2','normal'=>'#eff6ff','low'=>'#f9fafb'];
?>

<!-- ══ PAGE HEADING ═════════════════════════════════════════ -->
<div class="page-heading">
  <div>
    <div class="eyebrow">Workflow <span></span></div>
    <h1>Approval Center</h1>
    <p>Items awaiting your review and decision.</p>
  </div>
  <?php if ($total > 0): ?>
  <span style="background:var(--error);color:#fff;padding:8px 18px;border-radius:20px;font-size:15px;font-weight:800">
    <?= $total ?> pending
  </span>
  <?php endif; ?>
</div>

<!-- Module filter pills -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
  <a href="?" class="filter-button"
     style="<?= !$module ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : '' ?>">
    All (<?= $total ?>)
  </a>
  <?php foreach ($counts as $c): ?>
  <a href="?module=<?= e($c['module']) ?>" class="filter-button"
     style="<?= $module===$c['module'] ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : '' ?>">
    <?= $moduleIcon[$c['module']] ?? '📋' ?> <?= e(ucfirst($c['module'])) ?>
    <span style="background:var(--error);color:#fff;border-radius:12px;padding:1px 7px;font-size:11px;margin-left:4px">
      <?= $c['cnt'] ?>
    </span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══ MARKS APPROVAL ══════════════════════════════════════ -->
<?php if (!empty($pendingMarks) && (!$module || $module === 'marks')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">
    ✏️ Submitted Marks
    <span class="status new-s" style="margin-left:8px"><?= count($pendingMarks) ?> records</span>
  </div>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:14px">
    Marks submitted by teachers awaiting approval.
  </p>
  <?php
  $grouped = [];
  foreach ($pendingMarks as $m) {
      $key = $m['class_id'].':'.$m['subject_id'].':'.$m['assessment_config_id'];
      $grouped[$key]['meta']     = $m;
      $grouped[$key]['scores'][] = $m;
  }
  ?>
  <?php foreach ($grouped as $key => $group):
    $meta    = $group['meta'];
    $scores  = $group['scores'];
    $groupId = 'mg_'.md5($key);
    $groupLabel = $meta['cname'].' — '.$meta['sname'].' ('.$meta['cfg_name'].')';
  ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);margin-bottom:14px;overflow:hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;
                padding:14px 18px;background:var(--bg);border-bottom:1px solid var(--line-soft);
                flex-wrap:wrap;gap:8px">
      <div>
        <strong style="font-size:14.5px"><?= e($meta['cname']) ?> — <?= e($meta['sname']) ?></strong>
        <span style="margin-left:10px;font-size:12px;color:var(--ink-faint)"><?= e($meta['cfg_name']) ?></span>
        <?= workflowBadge($meta['status']) ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <span style="font-size:12px;color:var(--ink-faint)">By: <?= e($meta['entered_by_name'] ?? '—') ?></span>
        <button class="filter-button button-sm"
                onclick="toggleDetail('<?= $groupId ?>')">👁 Scores</button>
        <!-- Approve group → modal -->
        <button class="button button-sm"
                style="background:#059669;color:#fff;border:none"
                onclick="openApprovalModal({
                  type:'marks_group',
                  label: <?= json_encode($groupLabel) ?>,
                  class_id:<?= $meta['class_id'] ?>,
                  subject_id:<?= $meta['subject_id'] ?>,
                  config_id:<?= $meta['assessment_config_id'] ?>,
                  count:<?= count($scores) ?>
                }, 'approve')">
          ✅ Approve (<?= count($scores) ?>)
        </button>
        <!-- Return group → modal -->
        <button class="button button-sm button-secondary"
                onclick="openApprovalModal({
                  type:'marks_group',
                  label: <?= json_encode($groupLabel) ?>,
                  class_id:<?= $meta['class_id'] ?>,
                  subject_id:<?= $meta['subject_id'] ?>,
                  config_id:<?= $meta['assessment_config_id'] ?>,
                  count:<?= count($scores) ?>
                }, 'return')">
          ↩ Return
        </button>
      </div>
    </div>
    <div id="<?= $groupId ?>" style="display:none">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--bg)">
            <th style="padding:8px 14px;text-align:left">Student</th>
            <th style="padding:8px 14px;text-align:center">Marks</th>
            <th style="padding:8px 14px;text-align:center">Max</th>
            <th style="padding:8px 14px;text-align:center">%</th>
            <th style="padding:8px 14px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scores as $sc):
            $pct = $sc['max_marks'] > 0 ? round($sc['marks_obtained']/$sc['max_marks']*100,1) : 0;
          ?>
          <tr style="border-top:1px solid var(--line-soft)">
            <td style="padding:8px 14px">
              <strong><?= e($sc['first_name'].' '.$sc['last_name']) ?></strong>
              <div style="font-size:11px;color:var(--ink-faint)"><?= e($sc['sid']) ?></div>
            </td>
            <td style="text-align:center;padding:8px 14px;font-weight:700"><?= fmtMark($sc['marks_obtained']) ?></td>
            <td style="text-align:center;padding:8px 14px;color:var(--ink-faint)"><?= $sc['max_marks'] ?></td>
            <td style="text-align:center;padding:8px 14px"><?= fmtPct($pct) ?></td>
            <td style="padding:8px 14px"><?= workflowBadge($sc['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ ADMISSIONS PENDING ════════════════════════════════════ -->
<?php if (!empty($pendingAdmissions) && (!$module || $module === 'admissions')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">
    📋 Pending Admissions
    <span class="status pending" style="margin-left:8px"><?= count($pendingAdmissions) ?></span>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead>
        <tr>
          <th>Applicant</th><th>App #</th><th>Grade</th>
          <th>Status</th><th>Submitted</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingAdmissions as $app):
          $ini = strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1));
        ?>
        <tr>
          <td>
            <div class="person">
              <div class="avatar-sm"><?= e($ini) ?></div>
              <div>
                <strong><?= e($app['first_name'].' '.$app['last_name']) ?></strong>
                <div style="font-size:11px;color:var(--ink-faint)"><?= e($app['phone'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td class="muted" style="font-size:12px"><?= e($app['application_number']) ?></td>
          <td><?= e($app['grade_applying_for']) ?></td>
          <td><?= statusBadge($app['status']) ?></td>
          <td class="muted" style="font-size:12px"><?= date('d M Y', strtotime($app['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <!-- View full application in modal -->
              <button class="filter-button button-sm"
                      style="background:#1a2744;color:#fff;border-color:#1a2744"
                      onclick="openAppQuickView(<?= htmlspecialchars(json_encode([
                        'id'        => $app['id'],
                        'name'      => $app['first_name'].' '.($app['middle_name']??'').' '.$app['last_name'],
                        'ini'       => $ini,
                        'app_no'    => $app['application_number'],
                        'grade'     => $app['grade_applying_for'],
                        'status'    => $app['status'],
                        'dob'       => $app['date_of_birth'] ?? '—',
                        'gender'    => $app['gender'] ?? '—',
                        'phone'     => $app['phone']  ?? '—',
                        'email'     => $app['email']  ?? '—',
                        'county'    => $app['county'] ?? '—',
                        'guardian'  => $app['guardian_name'] ?? '—',
                        'g_phone'   => $app['guardian_phone'] ?? '—',
                        'prev_school'=> $app['previous_school'] ?? '—',
                        'submitted' => date('d M Y', strtotime($app['created_at'])),
                      ]), ENT_QUOTES) ?>)">
                👁 View
              </button>
              <a href="<?= BASE_URL ?>/admin/applications.php?q=<?= urlencode($app['application_number']) ?>"
                 class="filter-button button-sm">Full Review →</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ══ DISCIPLINE PENDING ════════════════════════════════════ -->
<?php if (!empty($pendingDiscipline) && (!$module || $module === 'discipline')): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">
    ⚖️ Open Discipline Cases
    <span class="status warning" style="margin-left:8px"><?= count($pendingDiscipline) ?></span>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Student</th><th>Date</th><th>Category</th><th>Action</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($pendingDiscipline as $d): ?>
        <tr>
          <td><strong><?= e($d['sname']) ?></strong></td>
          <td class="muted"><?= date('d M Y', strtotime($d['incident_date'])) ?></td>
          <td><span class="status pending"><?= e($d['category']) ?></span></td>
          <td><span class="status <?= $d['action_taken']==='Suspension'?'warning':'new-s' ?>"><?= e($d['action_taken']) ?></span></td>
          <td>
            <button class="filter-button button-sm"
                    style="background:#059669;color:#fff;border-color:#059669"
                    onclick="openApprovalModal({
                      type:'discipline',
                      label:<?= json_encode('Discipline: '.$d['sname'].' — '.($d['category']??'')) ?>,
                      discipline_id:<?= $d['id'] ?>
                    }, 'approve')">
              ✓ Resolve
            </button>
            <a href="<?= BASE_URL ?>/admin/discipline.php" class="filter-button button-sm">Details →</a>
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
  <div class="form-section-title">📋 Other Pending Requests (<?= count($pending) ?>)</div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($pending as $req): ?>
    <div style="
        background:var(--surface);
        border:1.5px solid var(--line);
        border-left:4px solid <?= $priorityColor[$req['priority']] ?? 'var(--line)' ?>;
        border-radius:var(--radius);
        padding:16px 20px;
    ">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <!-- Info -->
        <div style="flex:1;min-width:220px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
            <span style="font-size:18px"><?= $moduleIcon[$req['module']] ?? '📋' ?></span>
            <span style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;
                         color:<?= $priorityColor[$req['priority']] ?? 'var(--ink-soft)' ?>">
              <?= $req['priority'] ?> · <?= e($req['module']) ?>
            </span>
            <?= workflowBadge($req['status']) ?>
          </div>
          <div style="font-size:15px;font-weight:700;margin-bottom:4px"><?= e($req['title']) ?></div>
          <?php if ($req['description']): ?>
          <p style="font-size:13px;color:var(--ink-soft);margin-bottom:6px;line-height:1.5">
            <?= e(mb_substr($req['description'], 0, 160)).(mb_strlen($req['description'])>160?'…':'') ?>
          </p>
          <?php endif; ?>
          <div style="font-size:12px;color:var(--ink-faint)">
            By <strong><?= e($req['requester_name']) ?></strong>
            (<?= e($req['role_label']) ?>)
            · <?= date('d M Y H:i', strtotime($req['created_at'])) ?>
          </div>
        </div>

        <!-- Action buttons → all open modal -->
        <div style="display:flex;gap:6px;align-items:flex-start;flex-wrap:wrap;flex-shrink:0">
          <button class="button button-sm"
                  style="background:#059669;color:#fff;border:none;padding:8px 16px;font-weight:700"
                  onclick="openApprovalModal({type:'request',req_id:<?= $req['id'] ?>,label:<?= json_encode($req['title']) ?>},'approve')">
            ✅ Approve
          </button>
          <button class="button button-sm button-secondary"
                  style="padding:8px 16px;font-weight:700"
                  onclick="openApprovalModal({type:'request',req_id:<?= $req['id'] ?>,label:<?= json_encode($req['title']) ?>},'return')">
            ↩ Return
          </button>
          <button class="button button-sm"
                  style="background:var(--error);color:#fff;border:none;padding:8px 16px;font-weight:700"
                  onclick="openApprovalModal({type:'request',req_id:<?= $req['id'] ?>,label:<?= json_encode($req['title']) ?>},'reject')">
            ❌ Reject
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Empty state -->
<?php if (empty($pendingMarks) && empty($pendingAdmissions) && empty($pendingDiscipline) && empty($pending)): ?>
<div style="text-align:center;padding:72px 20px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:52px;margin-bottom:16px">✅</div>
  <h3 style="font-weight:800;margin-bottom:8px">All clear!</h3>
  <p style="color:var(--ink-soft)">No pending approvals at this time.</p>
</div>
<?php endif; ?>


<!-- ══ UNIFIED APPROVAL DECISION MODAL ══════════════════════ -->
<div id="approvalModal" style="
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(10,10,20,.78);
    align-items:center;justify-content:center;padding:16px">
  <div style="
      background:var(--surface);border-radius:12px;
      width:100%;max-width:500px;
      box-shadow:0 24px 80px rgba(0,0,0,.45);
      overflow:hidden">

    <!-- Header -->
    <div id="aModalHeader" style="
        background:#1a2744;padding:16px 20px;
        display:flex;align-items:center;gap:12px">
      <span id="aModalIcon" style="font-size:24px">📋</span>
      <div style="flex:1;min-width:0">
        <div id="aModalTitle"
             style="font-size:15px;font-weight:800;color:#fff;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
        <div id="aModalSubtitle"
             style="font-size:11.5px;color:rgba(255,255,255,.55);margin-top:1px"></div>
      </div>
      <button onclick="closeApprovalModal()"
              style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
                     border-radius:6px;color:#fff;width:30px;height:30px;cursor:pointer;
                     display:flex;align-items:center;justify-content:center;font-size:16px">✕</button>
    </div>

    <!-- Body -->
    <div style="padding:22px 24px">

      <!-- Action indicator -->
      <div id="aModalActionBanner" style="
          display:flex;align-items:center;gap:10px;
          padding:12px 16px;border-radius:8px;margin-bottom:18px;
          font-size:13.5px;font-weight:700">
        <span id="aModalActionIcon" style="font-size:22px"></span>
        <span id="aModalActionText"></span>
      </div>

      <form id="aModalForm" method="post">
        <?= csrfField() ?>

        <!-- Hidden fields (populated by JS) -->
        <input type="hidden" id="aModalAction"      name="action"/>
        <input type="hidden" id="aModalReqId"       name="req_id"     value=""/>
        <input type="hidden" id="aModalClassId"     name="class_id"   value=""/>
        <input type="hidden" id="aModalSubjectId"   name="subject_id" value=""/>
        <input type="hidden" id="aModalConfigId"    name="config_id"  value=""/>
        <input type="hidden" id="aModalDisciplineId" name="discipline_id" value=""/>

        <!-- Notes (required for return/reject) -->
        <div id="aModalNotesWrap">
          <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);
                         text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px"
                 id="aModalNotesLabel">Note / Reason</label>
          <textarea id="aModalNotes" name="decision_note" rows="3"
                    style="width:100%;padding:10px 12px;border:1.5px solid var(--line);
                           border-radius:8px;font-family:inherit;font-size:13px;
                           line-height:1.6;resize:vertical"
                    placeholder="Enter a note or reason…"></textarea>
        </div>

        <!-- Confirm buttons -->
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
          <button type="button" onclick="closeApprovalModal()"
                  class="button button-secondary">Cancel</button>
          <button type="submit" id="aModalConfirmBtn"
                  class="button button-primary"
                  style="padding:10px 28px;font-weight:700">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ APPLICATION QUICK-VIEW MODAL ═════════════════════════ -->
<div id="appQuickModal" style="
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(10,10,20,.78);
    align-items:center;justify-content:center;padding:16px">
  <div style="
      background:var(--surface);border-radius:12px;
      width:100%;max-width:580px;max-height:88vh;
      box-shadow:0 24px 80px rgba(0,0,0,.45);
      display:flex;flex-direction:column;overflow:hidden">

    <!-- Header -->
    <div style="background:#1a2744;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0">
      <div id="aqmAvatar"
           style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);
                  color:#fff;font-size:14px;font-weight:800;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>
      <div style="flex:1;min-width:0">
        <div id="aqmName" style="font-size:15px;font-weight:800;color:#fff"></div>
        <div id="aqmSub"  style="font-size:11.5px;color:rgba(255,255,255,.55);margin-top:1px"></div>
      </div>
      <button onclick="document.getElementById('appQuickModal').style.display='none';document.body.style.overflow=''"
              style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
                     border-radius:6px;color:#fff;width:30px;height:30px;cursor:pointer;
                     display:flex;align-items:center;justify-content:center;font-size:16px">✕</button>
    </div>

    <!-- Body -->
    <div style="flex:1;overflow-y:auto;padding:20px 24px">
      <div id="aqmGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px"></div>
      <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line);display:flex;gap:8px;flex-wrap:wrap">
        <a id="aqmLink" href="#" class="button button-primary button-sm">Open Full Application →</a>
      </div>
    </div>
  </div>
</div>


<script>
// ── Approval modal ────────────────────────────────────────────
var _approvalCtx = null;

var _actionConfig = {
  approve: {
    icon: '✅',
    text: 'Approve this request',
    bg:   '#f0fdf4',
    color:'#166534',
    btnBg:'#059669',
    btnText:'Confirm Approval',
    noteRequired: false,
    noteLabel: 'Note (optional)',
  },
  return: {
    icon: '↩',
    text: 'Return for correction',
    bg:   '#fffbeb',
    color:'#92400e',
    btnBg:'#d97706',
    btnText:'Send Back',
    noteRequired: true,
    noteLabel: 'Reason for return (required)',
  },
  reject: {
    icon: '❌',
    text: 'Reject this request',
    bg:   '#fef2f2',
    color:'#991b1b',
    btnBg:'#dc2626',
    btnText:'Confirm Rejection',
    noteRequired: true,
    noteLabel: 'Reason for rejection (required)',
  },
};

function openApprovalModal(ctx, actionType) {
  _approvalCtx = ctx;
  var cfg = _actionConfig[actionType];

  // Title + subtitle
  document.getElementById('aModalTitle').textContent    = ctx.label || 'Request';
  document.getElementById('aModalSubtitle').textContent =
    ctx.type === 'marks_group'
      ? (ctx.count || '') + ' student score'+(ctx.count!==1?'s':'') + ' · marks group'
      : ctx.type === 'discipline'
        ? 'Discipline case'
        : 'Approval request';
  document.getElementById('aModalIcon').textContent = cfg.icon;

  // Action banner
  var banner = document.getElementById('aModalActionBanner');
  banner.style.background = cfg.bg;
  banner.style.color       = cfg.color;
  document.getElementById('aModalActionIcon').textContent = cfg.icon;
  document.getElementById('aModalActionText').textContent = cfg.text;

  // Note field
  var noteLabel  = document.getElementById('aModalNotesLabel');
  var noteArea   = document.getElementById('aModalNotes');
  noteLabel.textContent    = cfg.noteLabel;
  noteArea.required        = cfg.noteRequired;
  noteArea.placeholder     = cfg.noteRequired ? 'Required…' : 'Optional note…';
  noteArea.value           = '';

  // Confirm button colour
  var btn = document.getElementById('aModalConfirmBtn');
  btn.style.background = cfg.btnBg;
  btn.textContent      = cfg.btnText;

  // Set hidden fields based on request type
  document.getElementById('aModalReqId').value        = '';
  document.getElementById('aModalClassId').value      = '';
  document.getElementById('aModalSubjectId').value    = '';
  document.getElementById('aModalConfigId').value     = '';
  document.getElementById('aModalDisciplineId').value = '';

  if (ctx.type === 'marks_group') {
    document.getElementById('aModalAction').value    = actionType === 'approve' ? 'approve_marks_group' : 'return_marks_group';
    document.getElementById('aModalClassId').value   = ctx.class_id;
    document.getElementById('aModalSubjectId').value = ctx.subject_id;
    document.getElementById('aModalConfigId').value  = ctx.config_id;
  } else if (ctx.type === 'discipline') {
    document.getElementById('aModalAction').value        = 'approve';
    document.getElementById('aModalDisciplineId').value  = ctx.discipline_id;
    // Use req_id for discipline: POST handler uses req_id
    // Actually discipline uses a direct resolve — override action
    document.getElementById('aModalAction').value = 'approve';
    document.getElementById('aModalReqId').value  = '';
  } else {
    // General request
    document.getElementById('aModalAction').value  = actionType;
    document.getElementById('aModalReqId').value   = ctx.req_id || '';
  }

  document.getElementById('approvalModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('aModalNotes').focus(); }, 100);
}

function closeApprovalModal() {
  document.getElementById('approvalModal').style.display = 'none';
  document.body.style.overflow = '';
  _approvalCtx = null;
}

// Close on backdrop click / Escape
document.getElementById('approvalModal').addEventListener('click', function(e) {
  if (e.target === this) closeApprovalModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeApprovalModal();
    document.getElementById('appQuickModal').style.display = 'none';
    document.body.style.overflow = '';
  }
});

// ── Toggle score rows ─────────────────────────────────────────
function toggleDetail(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ── Application quick-view modal ──────────────────────────────
function openAppQuickView(d) {
  document.getElementById('aqmAvatar').textContent = d.ini;
  document.getElementById('aqmName').textContent   = d.name.trim();
  document.getElementById('aqmSub').textContent    = d.app_no + '  ·  ' + d.grade + '  ·  ' + d.status;
  document.getElementById('aqmLink').href          = '<?= BASE_URL ?>/admin/applications.php?q=' + encodeURIComponent(d.app_no);

  var fields = [
    ['Date of Birth', d.dob], ['Gender', d.gender],
    ['Phone', d.phone], ['Email', d.email],
    ['County', d.county], ['Guardian', d.guardian],
    ['Guardian Phone', d.g_phone], ['Previous School', d.prev_school],
    ['Submitted', d.submitted], ['Status', d.status],
  ];
  var grid = document.getElementById('aqmGrid');
  grid.innerHTML = '';
  fields.forEach(function(f) {
    var card = document.createElement('div');
    card.style.cssText = 'background:var(--bg);border-radius:8px;padding:10px 12px;border:1px solid var(--line)';
    card.innerHTML =
      '<div style="font-size:10px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px">'+f[0]+'</div>'+
      '<div style="font-size:13px;font-weight:600;color:var(--ink)">'+(f[1]||'—')+'</div>';
    grid.appendChild(card);
  });

  document.getElementById('appQuickModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

document.getElementById('appQuickModal').addEventListener('click', function(e) {
  if (e.target === this) { this.style.display = 'none'; document.body.style.overflow = ''; }
});
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
