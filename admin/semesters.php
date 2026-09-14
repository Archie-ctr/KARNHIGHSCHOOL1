<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','registrar']);

$pageTitle   = 'Semesters & Periods';
$activeAdmin = 'academic_years';
$pdo  = db();
$ayId = (int)($_GET['ay_id'] ?? currentAcademicYearId());
$ay   = $pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Set current semester
    if ($action === 'set_current_sem') {
        $id = (int)($_POST['sem_id'] ?? 0);
        $pdo->prepare("UPDATE semesters SET is_current=0 WHERE academic_year_id=?")->execute([$ayId]);
        $pdo->prepare("UPDATE semesters SET is_current=1 WHERE id=?")->execute([$id]);
        auditLog('set_current','semesters','semester',$id,'','AY:'.$ayId);
        flash('success', 'Current semester updated.');
    }

    // Set current period AND open its assessment_config for entry
    elseif ($action === 'open_period') {
        $perId = (int)($_POST['per_id'] ?? 0);
        if ($perId) {
            // 1. Mark only this period as current (across the whole AY)
            $pdo->prepare("UPDATE periods SET is_current=0 WHERE semester_id IN (SELECT id FROM semesters WHERE academic_year_id=?)")->execute([$ayId]);
            $pdo->prepare("UPDATE periods SET is_current=1 WHERE id=?")->execute([$perId]);

            // 2. Deactivate ALL assessment_configs for this AY (close all windows)
            $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE academic_year_id=?")->execute([$ayId]);

            // 3. Activate ONLY the config(s) linked to this period
            $pdo->prepare("UPDATE assessment_configs SET is_active=1 WHERE period_id=? AND academic_year_id=?")->execute([$perId, $ayId]);

            $perName = $pdo->query("SELECT name FROM periods WHERE id=$perId")->fetchColumn() ?: 'Period';
            auditLog('open_period','semesters','period',$perId,'','Opened for marks entry: '.$perName.' AY:'.$ayId);

            // Notify all teachers
            try {
                $teachers = $pdo->query("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='teacher' AND u.is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($teachers as $tid) {
                    notify((int)$tid, 'period_opened',
                        "Marks Entry Open: $perName",
                        "The marks entry window for $perName has been opened. Please log in to enter student marks.",
                        BASE_URL.'/admin/marks_entry.php');
                }
            } catch (Throwable $e) { /* notifications optional */ }

            flash('success', "Period \"$perName\" is now open for marks entry. All other periods closed.");
        }
    }

    // Close all periods (lock marks entry)
    elseif ($action === 'close_all') {
        $pdo->prepare("UPDATE periods SET is_current=0 WHERE semester_id IN (SELECT id FROM semesters WHERE academic_year_id=?)")->execute([$ayId]);
        $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE academic_year_id=?")->execute([$ayId]);
        auditLog('close_all_periods','semesters','academic_year',$ayId,'','All periods closed');
        flash('warning', 'All periods closed. Marks entry is now locked for all teachers.');
    }

    redirect(BASE_URL.'/admin/semesters.php?ay_id='.$ayId);
}

// ── Load data ─────────────────────────────────────────────────
$sems = $pdo->query(
    "SELECT s.*, (SELECT COUNT(*) FROM periods p WHERE p.semester_id=s.id) pc
     FROM semesters s WHERE s.academic_year_id=$ayId ORDER BY s.sequence"
)->fetchAll();
foreach ($sems as &$sem) {
    $sem['periods'] = $pdo->query(
        "SELECT p.*, ac.id cfg_id, ac.is_active cfg_is_active, ac.name cfg_name, ac.max_marks
         FROM periods p
         LEFT JOIN assessment_configs ac ON ac.period_id=p.id AND ac.academic_year_id=$ayId
         WHERE p.semester_id={$sem['id']}
         ORDER BY p.sequence"
    )->fetchAll();
}
unset($sem);

// Check if any period is currently open
$anyOpen = $pdo->query("SELECT COUNT(*) FROM periods p JOIN semesters s ON s.id=p.semester_id WHERE s.academic_year_id=$ayId AND p.is_current=1")->fetchColumn();
$openPerName = $anyOpen ? $pdo->query("SELECT p.name FROM periods p JOIN semesters s ON s.id=p.semester_id WHERE s.academic_year_id=$ayId AND p.is_current=1 LIMIT 1")->fetchColumn() : null;

require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <a href="<?=BASE_URL?>/admin/academic_years.php" class="text-link" style="margin-bottom:8px;display:flex">← Academic Years</a>
    <div class="eyebrow">Academic Year <span></span></div>
    <h1>Semesters &amp; Periods</h1>
    <p><?=e($ay)?></p>
  </div>
  <?php if ($anyOpen): ?>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <span style="background:#d1fae5;color:#065f46;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:700;border:1px solid #a7f3d0">
      🟢 Open: <?=e($openPerName)?>
    </span>
    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="action" value="close_all"/>
      <button type="submit" class="button"
              style="background:#dc2626;color:#fff;border:none;padding:8px 16px;font-weight:700"
              onclick="return confirm('Close all periods and lock marks entry for all teachers?')">
        🔒 Lock All Entry
      </button>
    </form>
  </div>
  <?php else: ?>
  <span style="background:#fef3c7;color:#92400e;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:700;border:1px solid #fde68a">
    🔒 All periods closed
  </span>
  <?php endif; ?>
</div>

<!-- Info banner -->
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;font-size:13.5px">
  <strong>🔑 How it works:</strong>
  Click <strong>Open for Entry</strong> on a period to unlock marks entry for that period only.
  Teachers will immediately see only that period in their marks entry dropdown.
  All other periods are automatically closed.
  Use <strong>Lock All Entry</strong> to close marks entry entirely.
</div>

<?php foreach ($sems as $sem): ?>
<div class="form-section" style="margin-bottom:20px">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <span style="display:flex;align-items:center;gap:10px">
      <?=e($sem['name'])?>
      <?php if ($sem['is_current']): ?>
        <span class="status approved" style="font-size:11px">Current Semester</span>
      <?php endif; ?>
    </span>
    <?php if (!$sem['is_current']): ?>
    <form method="post" style="display:inline">
      <?=csrfField()?>
      <input type="hidden" name="action" value="set_current_sem"/>
      <input type="hidden" name="sem_id" value="<?=$sem['id']?>"/>
      <button type="submit" class="filter-button button-sm">Set as Current Semester</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="table-wrap" style="border:none">
    <table>
      <thead>
        <tr>
          <th style="width:26%">Period</th>
          <th>Type</th>
          <th>Assessment Config</th>
          <th>Max Marks</th>
          <th style="text-align:center">Entry Status</th>
          <th style="text-align:center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sem['periods'] as $p):
          $isOpen   = (bool)$p['is_current'];
          $hasConfig= !empty($p['cfg_id']);
        ?>
        <tr style="<?=$isOpen ? 'background:#f0fdf4;' : ''?>">
          <td>
            <strong><?=e($p['name'])?></strong>
          </td>
          <td>
            <span class="status <?=$p['type']==='exam'?'warning':'new-s'?>">
              <?=e(ucfirst($p['type']))?>
            </span>
          </td>
          <td>
            <?php if ($hasConfig): ?>
              <span style="font-size:12px"><?=e($p['cfg_name'])?></span>
            <?php else: ?>
              <span style="color:var(--ink-faint);font-size:12px">— no config linked —</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-size:12px">
            <?=$hasConfig ? $p['max_marks'] : '—'?>
          </td>
          <td style="text-align:center">
            <?php if ($isOpen): ?>
              <span style="display:inline-flex;align-items:center;gap:5px;background:#d1fae5;color:#065f46;
                           padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid #a7f3d0">
                🟢 Open
              </span>
            <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:5px;background:#f3f4f6;color:#6b7280;
                           padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #e5e7eb">
                🔒 Closed
              </span>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <?php if ($isOpen): ?>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Close this period and lock marks entry?')">
                <?=csrfField()?>
                <input type="hidden" name="action" value="close_all"/>
                <button type="submit" class="button button-sm"
                        style="background:#dc2626;color:#fff;border:none;font-size:12px;padding:5px 12px">
                  🔒 Close
                </button>
              </form>
            <?php elseif ($hasConfig): ?>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Open \'<?=addslashes($p['name'])?>\' for marks entry?\n\nThis will close all other periods.')">
                <?=csrfField()?>
                <input type="hidden" name="action" value="open_period"/>
                <input type="hidden" name="per_id" value="<?=$p['id']?>"/>
                <button type="submit" class="button button-sm"
                        style="background:#059669;color:#fff;border:none;font-size:12px;padding:5px 12px;font-weight:700">
                  🟢 Open for Entry
                </button>
              </form>
            <?php else: ?>
              <span style="font-size:12px;color:var(--ink-faint)">Link config first</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<!-- Teacher visibility preview -->
<div class="form-section" style="background:#f8fafc">
  <div class="form-section-title">👁 Teacher View Preview</div>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">
    This is what teachers currently see in the <strong>Assessment Period</strong> dropdown on the Marks Entry page.
  </p>
  <?php
  $visibleConfigs = $pdo->prepare(
      "SELECT ac.name, ac.max_marks, ac.type, p.name period_name, s.name sem_name
       FROM assessment_configs ac
       JOIN periods p ON p.id=ac.period_id
       JOIN semesters s ON s.id=p.semester_id
       WHERE ac.academic_year_id=? AND ac.is_active=1
       ORDER BY ac.sequence"
  );
  $visibleConfigs->execute([$ayId]);
  $visibleConfigs = $visibleConfigs->fetchAll();
  ?>
  <?php if (empty($visibleConfigs)): ?>
  <div style="text-align:center;padding:24px;background:#fef3c7;border-radius:var(--radius);border:1px solid #fde68a">
    <div style="font-size:28px;margin-bottom:8px">🔒</div>
    <strong style="color:#92400e">No period is currently open.</strong>
    <p style="font-size:13px;color:#92400e;margin-top:4px">Teachers cannot enter any marks right now.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach ($visibleConfigs as $vc): ?>
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:10px 16px;font-size:13px">
      <strong><?=e($vc['name'])?></strong>
      <div style="font-size:11px;color:#065f46;margin-top:3px">
        <?=e($vc['sem_name'])?> · Max: <?=$vc['max_marks']?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
