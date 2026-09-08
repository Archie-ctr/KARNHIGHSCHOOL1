<?php
$pageTitle   = 'Transfers & Withdrawals';
$activeAdmin = 'student_transfers';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$canApprove = isPrincipal() || isSchoolAdmin();

// ── Ensure table exists ───────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_movements (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id      INT UNSIGNED NOT NULL,
        movement_type   ENUM('transfer_out','transfer_in','withdrawal','re_enrollment') NOT NULL,
        effective_date  DATE        NOT NULL,
        destination_school VARCHAR(200) NULL,
        reason          TEXT        NOT NULL,
        status          ENUM('pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending',
        requested_by    INT UNSIGNED NOT NULL,
        approved_by     INT UNSIGNED NULL,
        approved_at     DATETIME    NULL,
        notes           TEXT        NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        created_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sm_student (student_id),
        INDEX idx_sm_ay      (academic_year_id),
        INDEX idx_sm_type    (movement_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $retTab = $_POST['tab'] ?? 'list';

    if ($action === 'add_movement') {
        $sid    = (int)($_POST['student_id'] ?? 0);
        $type   = $_POST['movement_type']   ?? '';
        $date   = $_POST['effective_date']  ?? date('Y-m-d');
        $dest   = trim($_POST['destination_school'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $allowed = ['transfer_out','transfer_in','withdrawal','re_enrollment'];
        if ($sid && in_array($type,$allowed) && $reason) {
            $pdo->prepare(
                "INSERT INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,requested_by,academic_year_id)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$sid,$type,$date,$dest?:null,$reason,currentUserId(),$ayId]);
            auditLog('create','students','student_movement',(int)$pdo->lastInsertId(),'','Type: '.$type.' for student #'.$sid);
            flash('success','Movement request submitted.');
        }

    } elseif ($action === 'approve_movement' && $canApprove) {
        $id = (int)($_POST['movement_id'] ?? 0);
        if ($id) {
            $mov = $pdo->query("SELECT * FROM student_movements WHERE id=$id")->fetch();
            if ($mov) {
                $pdo->prepare(
                    "UPDATE student_movements SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?"
                )->execute([currentUserId(),$id]);
                // Apply the status change to the student record
                $newStatus = match($mov['movement_type']) {
                    'transfer_out' => 'Transferred',
                    'withdrawal'   => 'Withdrawn',
                    're_enrollment'=> 'Active',
                    default        => null,
                };
                if ($newStatus) {
                    $pdo->prepare("UPDATE students SET status=?,updated_at=NOW() WHERE id=?")->execute([$newStatus,$mov['student_id']]);
                }
                auditLog('approve','students','student_movement',$id,'pending','approved');
                flash('success','Movement approved. Student status updated.');
            }
        }

    } elseif ($action === 'complete_movement' && $canApprove) {
        $id = (int)($_POST['movement_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE student_movements SET status='completed' WHERE id=?")->execute([$id]);
            flash('success','Movement marked as completed.');
        }

    } elseif ($action === 'cancel_movement') {
        $id = (int)($_POST['movement_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE student_movements SET status='cancelled' WHERE id=? AND status='pending'")->execute([$id]);
            flash('warning','Movement request cancelled.');
        }
    }
    redirect(BASE_URL.'/admin/student_transfers.php?tab='.$retTab);
}

// ── Data ──────────────────────────────────────────────────────
$tab  = $_GET['tab']  ?? 'list';
$typeF= $_GET['type'] ?? '';

$whereType = $typeF ? "AND sm.movement_type='" . addslashes($typeF) . "'" : '';
$movements = $pdo->prepare(
    "SELECT sm.*,
            CONCAT(s.first_name,' ',s.last_name) student_name,
            s.student_id student_code, s.status current_status,
            g.name grade_name,
            u.name  requester_name,
            ua.name approver_name
     FROM student_movements sm
     JOIN students s  ON s.id  = sm.student_id
     LEFT JOIN grades g ON g.id = s.current_grade_id
     LEFT JOIN users  u  ON u.id  = sm.requested_by
     LEFT JOIN users  ua ON ua.id = sm.approved_by
     WHERE sm.academic_year_id=? $whereType
     ORDER BY sm.created_at DESC"
);
$movements->execute([$ayId]);
$movements = $movements->fetchAll();

// Summary
$typeCounts = [];
foreach ($movements as $m) $typeCounts[$m['movement_type']] = ($typeCounts[$m['movement_type']] ?? 0) + 1;
$pendingCount = count(array_filter($movements, fn($m) => $m['status']==='pending'));

$allStudents = $pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status IN ('Active','Transferred','Withdrawn') ORDER BY first_name")->fetchAll();

$typeLabels = [
    'transfer_out'  => '➡️ Transfer Out',
    'transfer_in'   => '⬅️ Transfer In',
    'withdrawal'    => '❌ Withdrawal',
    're_enrollment' => '🔄 Re-enrollment',
];
$statusColors = ['pending'=>'pending','approved'=>'new-s','completed'=>'approved','cancelled'=>'warning'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Student Management <span></span></div>
    <h1>Transfers &amp; Withdrawals</h1>
    <p><?= e($ay) ?></p>
  </div>
  <?php if ($pendingCount > 0 && $canApprove): ?>
  <span class="status warning" style="font-size:14px;padding:8px 16px"><?= $pendingCount ?> pending approval</span>
  <?php endif; ?>
</div>

<!-- Summary metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <?php foreach ([
    ['transfer_out'  => '➡️', 'Transfer Out'],
    ['transfer_in'   => '⬅️', 'Transfer In'],
    ['withdrawal'    => '❌', 'Withdrawals'],
    ['re_enrollment' => '🔄', 'Re-enrollments'],
  ] as $entry):
    $type = array_key_first($entry);
    [$ico, $label] = array_values($entry);
  ?>
  <div class="metric-card">
    <div class="metric-top"><span><?= $label ?></span><div class="metric-icon"><?= $ico ?></div></div>
    <strong><?= $typeCounts[$type] ?? 0 ?></strong><small><i></i><?= e($ay) ?></small>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=list"  class="tab-btn <?= $tab==='list' ?'active':'' ?>">📋 All Movements</a>
  <a href="?tab=new"   class="tab-btn <?= $tab==='new'  ?'active':'' ?>">➕ New Request</a>
</div>

<?php if ($tab === 'list'): ?>
<!-- ── FILTER ─────────────────────────────────────────────── -->
<form method="get" class="filter-row" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="list"/>
  <select name="type" class="filter-button" onchange="this.form.submit()">
    <option value="">All types</option>
    <?php foreach ($typeLabels as $key => $lbl): ?>
    <option value="<?= $key ?>" <?= $typeF===$key?'selected':'' ?>><?= $lbl ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($typeF): ?><a href="?tab=list" class="filter-button">Clear</a><?php endif; ?>
</form>

<!-- ── TABLE ──────────────────────────────────────────────── -->
<?php if (empty($movements)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📋</div>
  <p style="color:var(--ink-soft)">No movements recorded for <?= e($ay) ?>.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Student</th><th>Grade</th><th>Type</th><th>Effective Date</th><th>Destination/Reason</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($movements as $m): ?>
      <tr>
        <td>
          <strong><?= e($m['student_name']) ?></strong>
          <div style="font-size:11px;color:var(--ink-faint)"><?= e($m['student_code']) ?></div>
        </td>
        <td class="muted"><?= e($m['grade_name'] ?? '—') ?></td>
        <td><span class="badge badge-grey"><?= $typeLabels[$m['movement_type']] ?? e($m['movement_type']) ?></span></td>
        <td class="muted"><?= date('M d, Y', strtotime($m['effective_date'])) ?></td>
        <td style="max-width:180px;white-space:normal;font-size:12.5px">
          <?php if ($m['destination_school']): ?>
          <strong><?= e($m['destination_school']) ?></strong><br>
          <?php endif; ?>
          <?= e(mb_substr($m['reason'],0,60)).(mb_strlen($m['reason'])>60?'…':'') ?>
        </td>
        <td><span class="status <?= $statusColors[$m['status']]??'new-s' ?>"><?= ucfirst($m['status']) ?></span></td>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if ($m['status']==='pending' && $canApprove): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="approve_movement"/>
              <input type="hidden" name="movement_id" value="<?= $m['id'] ?>"/>
              <input type="hidden" name="tab" value="list"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">✓ Approve</button>
            </form>
            <form method="post" onsubmit="return confirm('Cancel this request?')" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="cancel_movement"/>
              <input type="hidden" name="movement_id" value="<?= $m['id'] ?>"/>
              <input type="hidden" name="tab" value="list"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">Cancel</button>
            </form>
            <?php elseif ($m['status']==='approved' && $canApprove): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="complete_movement"/>
              <input type="hidden" name="movement_id" value="<?= $m['id'] ?>"/>
              <input type="hidden" name="tab" value="list"/>
              <button type="submit" class="filter-button button-sm">✓ Complete</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;justify-content:flex-end;margin-top:10px">
  <a href="<?= BASE_URL ?>/admin/reports.php?export=csv&type=transfers&ay_id=<?= $ayId ?>"
     class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<?php endif; ?>

<?php elseif ($tab === 'new'): ?>
<!-- ── NEW MOVEMENT REQUEST ───────────────────────────────── -->
<div class="panel" style="padding:28px;max-width:560px">
  <h3 style="font-weight:700;margin-bottom:16px">New Student Movement Request</h3>
  <form method="post">
    <?= csrfField() ?><input type="hidden" name="action" value="add_movement"/><input type="hidden" name="tab" value="list"/>
    <div class="form-group">
      <label>Student *
        <select name="student_id" required>
          <option value="">Select student…</option>
          <?php foreach ($allStudents as $s): ?>
          <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['student_id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Movement Type *
          <select name="movement_type" required id="movType" onchange="toggleDest()">
            <option value="">Select type…</option>
            <?php foreach ($typeLabels as $k => $l): ?>
            <option value="<?= $k ?>"><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-group">
        <label>Effective Date *<input type="date" name="effective_date" required value="<?= date('Y-m-d') ?>"/></label>
      </div>
    </div>
    <div class="form-group" id="destField" style="display:none">
      <label>Destination School<input type="text" name="destination_school" placeholder="e.g. ABC School, Monrovia"/></label>
    </div>
    <div class="form-group">
      <label>Reason *<textarea name="reason" rows="3" required placeholder="Reason for transfer/withdrawal…"></textarea></label>
    </div>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px">
      ℹ️ This request will be submitted for principal approval. The student's status will be updated automatically upon approval.
    </p>
    <button type="submit" class="button button-primary">Submit Request</button>
  </form>
</div>
<script>
function toggleDest(){
  var t=document.getElementById('movType').value;
  document.getElementById('destField').style.display=(t==='transfer_out'||t==='transfer_in')?'block':'none';
}
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
