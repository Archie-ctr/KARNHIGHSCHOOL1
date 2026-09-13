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
<div style="max-width:620px">
  <div style="background:var(--surface);border:1.5px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)">

    <!-- Form header -->
    <div style="background:#1a2744;padding:18px 24px;display:flex;align-items:center;gap:12px">
      <div style="width:38px;height:38px;border-radius:8px;background:rgba(255,255,255,.12);
                  display:flex;align-items:center;justify-content:center;font-size:18px">➡️</div>
      <div>
        <div style="font-size:15px;font-weight:800;color:#fff">New Student Movement Request</div>
        <div style="font-size:11.5px;color:rgba(255,255,255,.5);margin-top:1px">Transfers, withdrawals and re-enrolments</div>
      </div>
    </div>

    <form method="post" style="padding:24px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_movement"/>
      <input type="hidden" name="tab"    value="list"/>

      <!-- Student -->
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
          Student <span style="color:var(--error)">*</span>
        </label>
        <select name="student_id" required
                style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;
                       font-size:13px;background:var(--bg);color:var(--ink)">
          <option value="">— Select student —</option>
          <?php foreach ($allStudents as $s): ?>
          <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> &nbsp; (<?= e($s['student_id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Movement Type + Effective Date -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
            Movement Type <span style="color:var(--error)">*</span>
          </label>
          <select name="movement_type" required id="movType" onchange="toggleDest()"
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;
                         font-size:13px;background:var(--bg);color:var(--ink)">
            <option value="">— Select type —</option>
            <?php foreach ($typeLabels as $k => $l): ?>
            <option value="<?= $k ?>"><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
            Effective Date <span style="color:var(--error)">*</span>
          </label>
          <input type="date" name="effective_date" required value="<?= date('Y-m-d') ?>"
                 style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;
                        font-size:13px;background:var(--bg);color:var(--ink)"/>
        </div>
      </div>

      <!-- Destination School (conditional) -->
      <div id="destField" style="display:none;margin-bottom:18px">
        <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
          Destination School
        </label>
        <input type="text" name="destination_school"
               placeholder="e.g. ABC School, Monrovia"
               style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;
                      font-size:13px;background:var(--bg);color:var(--ink)"/>
      </div>

      <!-- Reason -->
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
          Reason <span style="color:var(--error)">*</span>
        </label>
        <textarea name="reason" rows="4" required
                  placeholder="Provide the reason for this movement request…"
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;
                         font-size:13px;background:var(--bg);color:var(--ink);
                         font-family:inherit;line-height:1.6;resize:vertical"></textarea>
      </div>

      <!-- Info note -->
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
                  padding:12px 14px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
        <span style="font-size:16px;flex-shrink:0">ℹ️</span>
        <div style="font-size:12.5px;color:#1e40af;line-height:1.6">
          This request will be submitted for <strong>principal approval</strong>.
          The student's status will be updated automatically upon approval.
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <a href="?tab=list" class="button button-secondary">Cancel</a>
        <button type="submit" class="button button-primary" style="padding:10px 28px">
          ➡️ Submit Request
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleDest(){
  var t=document.getElementById('movType').value;
  document.getElementById('destField').style.display=(t==='transfer_out'||t==='transfer_in')?'block':'none';
}
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
