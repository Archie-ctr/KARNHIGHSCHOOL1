<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','registrar']);

$pageTitle   = 'Semesters & Periods';
$activeAdmin = 'academic_years';
$pdo  = db();
$ayId = (int)($_GET['ay_id'] ?? currentAcademicYearId());
$ay   = $pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();

// ── Window type config (order = progression) ─────────────────
$WINDOWS = [
    'notes_open'   => ['label'=>'Note Period',       'icon'=>'📝', 'color'=>'#1d4ed8','bg'=>'#eff6ff','border'=>'#bfdbfe','desc'=>'Teachers distribute/give notes to students'],
    'notes_review' => ['label'=>'Note Review',       'icon'=>'🔍', 'color'=>'#7c3aed','bg'=>'#f5f3ff','border'=>'#ddd6fe','desc'=>'Teachers review notes with students'],
    'test'         => ['label'=>'Test / Exam',       'icon'=>'📋', 'color'=>'#b45309','bg'=>'#fffbeb','border'=>'#fde68a','desc'=>'Test or examination is conducted'],
    'marks_entry'  => ['label'=>'Marks Submission',  'icon'=>'✏️', 'color'=>'#065f46','bg'=>'#f0fdf4','border'=>'#a7f3d0','desc'=>'Teachers enter and submit marks for approval'],
];

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $perId  = (int)($_POST['per_id']  ?? 0);
    $semId  = (int)($_POST['sem_id']  ?? 0);

    // ── Set current semester ──────────────────────────────────
    if ($action === 'set_current_sem' && $semId) {
        $pdo->prepare("UPDATE semesters SET is_current=0 WHERE academic_year_id=?")->execute([$ayId]);
        $pdo->prepare("UPDATE semesters SET is_current=1 WHERE id=?")->execute([$semId]);
        auditLog('set_current','semesters','semester',$semId,'','AY:'.$ayId);
        flash('success','Current semester updated.');
    }

    // ── Open a window ─────────────────────────────────────────
    elseif ($action === 'open_window' && $perId) {
        $winType = $_POST['window_type'] ?? '';
        if (!array_key_exists($winType, $WINDOWS)) {
            flash('error','Invalid window type.');
        } else {
            // Mark this period as current (is_current) for the AY
            $pdo->prepare("UPDATE periods SET is_current=0 WHERE semester_id IN (SELECT id FROM semesters WHERE academic_year_id=?)")->execute([$ayId]);
            $pdo->prepare("UPDATE periods SET is_current=1 WHERE id=?")->execute([$perId]);

            // If opening marks_entry: activate assessment_config for this period, close all others
            if ($winType === 'marks_entry') {
                $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE academic_year_id=?")->execute([$ayId]);
                $pdo->prepare("UPDATE assessment_configs SET is_active=1 WHERE period_id=? AND academic_year_id=?")->execute([$perId,$ayId]);
            }

            // Open window (closes all other windows on this period first)
            openPeriodWindow($perId, $winType);

            $perName = $pdo->query("SELECT name FROM periods WHERE id=$perId")->fetchColumn() ?: 'Period';
            $winLabel = $WINDOWS[$winType]['label'];
            auditLog('open_window','semesters','period',$perId,'','Window:'.$winType.' Period:'.$perName.' AY:'.$ayId);

            // Notify all teachers
            try {
                $teachers = $pdo->query("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='teacher' AND u.is_active=1")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($teachers as $tid) {
                    notify((int)$tid, 'window_'.$winType,
                        "$winLabel Open: $perName",
                        "The $winLabel window for $perName ($ay) is now open.",
                        BASE_URL.'/admin/marks_entry.php');
                }
            } catch (Throwable $e) {}

            flash('success', "\"$winLabel\" opened for $perName. Teachers notified.");
        }
    }

    // ── Close a specific window ───────────────────────────────
    elseif ($action === 'close_window' && $perId) {
        $winType = $_POST['window_type'] ?? '';
        if (array_key_exists($winType, $WINDOWS)) {
            $col = 'window_'.$winType;
            $pdo->prepare("UPDATE periods SET `$col`=0 WHERE id=?")->execute([$perId]);
            // If closing marks_entry: deactivate the assessment_config
            if ($winType === 'marks_entry') {
                $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE period_id=? AND academic_year_id=?")->execute([$perId,$ayId]);
            }
            try {
                $pdo->prepare("INSERT INTO period_activity_log (period_id,window_type,action,done_by) VALUES (?,?,?,?)")
                    ->execute([$perId,$winType,'close',currentUserId()]);
            } catch (Throwable $e) {}
            $perName  = $pdo->query("SELECT name FROM periods WHERE id=$perId")->fetchColumn() ?: 'Period';
            $winLabel = $WINDOWS[$winType]['label'];
            auditLog('close_window','semesters','period',$perId,'','Window:'.$winType.' Period:'.$perName);
            flash('warning', "\"$winLabel\" closed for $perName.");
        }
    }

    // ── Close ALL windows on a period ─────────────────────────
    elseif ($action === 'close_all_period' && $perId) {
        closeAllPeriodWindows($perId);
        $pdo->prepare("UPDATE periods SET is_current=0 WHERE id=?")->execute([$perId]);
        $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE period_id=? AND academic_year_id=?")->execute([$perId,$ayId]);
        $perName = $pdo->query("SELECT name FROM periods WHERE id=$perId")->fetchColumn() ?: 'Period';
        auditLog('close_all_period','semesters','period',$perId,'','All windows closed');
        flash('warning', "All windows closed for $perName.");
    }

    // ── Lock everything (emergency) ───────────────────────────
    elseif ($action === 'lock_all') {
        $pdo->prepare("UPDATE periods SET is_current=0, window_notes_open=0, window_notes_review=0, window_test=0, window_marks_entry=0 WHERE semester_id IN (SELECT id FROM semesters WHERE academic_year_id=?)")->execute([$ayId]);
        $pdo->prepare("UPDATE assessment_configs SET is_active=0 WHERE academic_year_id=?")->execute([$ayId]);
        auditLog('lock_all','semesters','academic_year',$ayId,'','Emergency lock');
        flash('warning','All periods and windows locked for '.$ay.'.');
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
        "SELECT p.*,
                ac.id cfg_id, ac.is_active cfg_active, ac.name cfg_name, ac.max_marks
         FROM periods p
         LEFT JOIN assessment_configs ac ON ac.period_id=p.id AND ac.academic_year_id=$ayId
         WHERE p.semester_id={$sem['id']}
         ORDER BY p.sequence"
    )->fetchAll();
}
unset($sem);

// Active window summary across the whole AY
$activeRows = $pdo->query(
    "SELECT p.id, p.name pname, s.name sname,
            p.window_notes_open, p.window_notes_review, p.window_test, p.window_marks_entry
     FROM periods p JOIN semesters s ON s.id=p.semester_id
     WHERE s.academic_year_id=$ayId
       AND (p.window_notes_open=1 OR p.window_notes_review=1 OR p.window_test=1 OR p.window_marks_entry=1)
     ORDER BY p.id"
)->fetchAll();

// Recent activity log
try {
    $actLog = $pdo->query(
        "SELECT pal.*, p.name pname, u.name uname
         FROM period_activity_log pal
         JOIN periods p ON p.id=pal.period_id
         LEFT JOIN users u ON u.id=pal.done_by
         WHERE p.semester_id IN (SELECT id FROM semesters WHERE academic_year_id=$ayId)
         ORDER BY pal.done_at DESC LIMIT 12"
    )->fetchAll();
} catch (Throwable $e) { $actLog = []; }

require_once dirname(__DIR__).'/includes/admin_header.php';

// Helper: renders one window button row
function winBtn(array $p, string $wtype, array $wcfg, int $ayId, bool $isActive): string {
    $col   = 'window_'.$wtype;
    $label = $wcfg['label'];
    $icon  = $wcfg['icon'];
    $color = $wcfg['color'];
    $bg    = $wcfg['bg'];
    $bdr   = $wcfg['border'];
    $csrf  = csrfField();
    $pid   = $p['id'];

    if ($isActive) {
        // Show close button
        return '<form method="post" style="display:inline" onsubmit="return confirm(\'Close '.$label.' window?\')">
            '.$csrf.'
            <input type="hidden" name="action" value="close_window"/>
            <input type="hidden" name="per_id" value="'.$pid.'"/>
            <input type="hidden" name="window_type" value="'.$wtype.'"/>
            <button type="submit" style="background:'.$color.';color:#fff;border:none;border-radius:6px;
                    padding:5px 12px;font-size:11.5px;font-weight:700;cursor:pointer;
                    display:inline-flex;align-items:center;gap:4px">
              '.$icon.' '.$label.' <span style="opacity:.7;font-size:10px">▼ Close</span>
            </button>
        </form>';
    } else {
        // Show open button only if not another window is already open on this period
        return '<form method="post" style="display:inline"
                onsubmit="return confirm(\'Open '.addslashes($label).' for '.addslashes($p['name']).'?\\nThis closes any other active window on this period.\')">
            '.$csrf.'
            <input type="hidden" name="action" value="open_window"/>
            <input type="hidden" name="per_id" value="'.$pid.'"/>
            <input type="hidden" name="window_type" value="'.$wtype.'"/>
            <button type="submit" style="background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;
                    border-radius:6px;padding:5px 12px;font-size:11.5px;cursor:pointer;
                    display:inline-flex;align-items:center;gap:4px">
              '.$icon.' '.$label.'
            </button>
        </form>';
    }
}
?>

<!-- ══ PAGE HEADING ════════════════════════════════════════════ -->
<div class="page-heading">
  <div>
    <a href="<?=BASE_URL?>/admin/academic_years.php" class="text-link" style="margin-bottom:8px;display:flex">← Academic Years</a>
    <div class="eyebrow">Academic Year <span></span></div>
    <h1>Semesters &amp; Periods</h1>
    <p><?=e($ay)?></p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?php if (!empty($activeRows)): ?>
      <?php foreach ($activeRows as $ar):
        $wt = $ar['window_marks_entry'] ? 'marks_entry' : ($ar['window_test'] ? 'test' : ($ar['window_notes_review'] ? 'notes_review' : 'notes_open'));
        $wc = $WINDOWS[$wt];
      ?>
      <span style="background:<?=$wc['bg']?>;color:<?=$wc['color']?>;padding:7px 14px;border-radius:20px;
                   font-size:12.5px;font-weight:700;border:1px solid <?=$wc['border']?>">
        <?=$wc['icon']?> <?=e($ar['pname'])?> — <?=$wc['label']?>
      </span>
      <?php endforeach; ?>
    <?php else: ?>
      <span style="background:#fef3c7;color:#92400e;padding:7px 14px;border-radius:20px;font-size:12.5px;font-weight:700;border:1px solid #fde68a">
        🔒 All windows closed
      </span>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Lock ALL periods and windows for <?=e(addslashes($ay))?>? Teachers will not be able to enter anything.')">
      <?=csrfField()?>
      <input type="hidden" name="action" value="lock_all"/>
      <button type="submit" class="button"
              style="background:#dc2626;color:#fff;border:none;padding:8px 16px;font-weight:700;font-size:13px">
        🔒 Emergency Lock All
      </button>
    </form>
  </div>
</div>

<!-- ══ HOW IT WORKS BANNER ════════════════════════════════════ -->
<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:var(--radius);padding:14px 18px;margin-bottom:22px">
  <div style="font-size:13px;font-weight:800;color:#0369a1;margin-bottom:8px">🔑 How the 4-Phase Activity Window Works</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px">
    <?php foreach ($WINDOWS as $wt => $wc): ?>
    <div style="background:<?=$wc['bg']?>;border:1px solid <?=$wc['border']?>;border-radius:8px;padding:9px 12px">
      <div style="font-size:12.5px;font-weight:800;color:<?=$wc['color']?>"><?=$wc['icon']?> <?=$wc['label']?></div>
      <div style="font-size:11px;color:<?=$wc['color']?>;opacity:.8;margin-top:2px"><?=$wc['desc']?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <p style="font-size:12px;color:#0369a1;margin-top:10px">
    ➡ Open windows in sequence: <strong>Note Period → Note Review → Test → Marks Submission</strong>.
    Only one window can be active per period at a time.
    Marks Submission is the only window that unlocks teacher mark entry.
  </p>
</div>

<!-- ══ SEMESTERS & PERIODS ════════════════════════════════════ -->
<?php foreach ($sems as $sem): ?>
<div class="form-section" style="margin-bottom:24px">
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

    <?php foreach ($sem['periods'] as $p):
    $hasConfig = !empty($p['cfg_id']);
    $anyWinOpen = $p['window_notes_open'] || $p['window_notes_review'] || $p['window_test'] || $p['window_marks_entry'];
    $rowBorder = $anyWinOpen ? 'border:1.5px solid #6ee7b7;' : 'border:1px solid var(--line);';
    $headerBg  = $anyWinOpen ? '#f0fdf4' : 'var(--bg)';
    $headerBdr = $anyWinOpen ? '#bbf7d0' : 'var(--line-soft)';
  ?>
  <div style="<?=$rowBorder?> border-radius:var(--radius);margin-bottom:14px;overflow:hidden">

    <!-- Period header -->
    <div style="display:flex;justify-content:space-between;align-items:center;
                flex-wrap:wrap;gap:10px;padding:12px 16px;
                background:<?=$headerBg?>;
                border-bottom:1px solid <?=$headerBdr?>">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <strong style="font-size:14px"><?=e($p['name'])?></strong>
        <span class="status <?=$p['type']==='exam'?'warning':'new-s'?>" style="font-size:11px">
          <?=e(ucfirst($p['type']))?>
        </span>
        <?php if ($hasConfig): ?>
          <span style="font-size:11px;color:var(--ink-faint);background:var(--bg2);padding:2px 8px;border-radius:10px">
            Config: <?=e($p['cfg_name'])?> · Max: <?=$p['max_marks']?>
          </span>
        <?php else: ?>
          <span style="font-size:11px;color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:10px;border:1px solid #fecaca">
            ⚠ No assessment config linked
          </span>
        <?php endif; ?>
        <?php if ($anyWinOpen): ?>
          <?php
            $activeWin = $p['window_marks_entry'] ? 'marks_entry' : ($p['window_test'] ? 'test' : ($p['window_notes_review'] ? 'notes_review' : 'notes_open'));
            $awc = $WINDOWS[$activeWin];
          ?>
          <span style="background:<?=$awc['bg']?>;color:<?=$awc['color']?>;border:1px solid <?=$awc['border']?>;
                       padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:700">
            <?=$awc['icon']?> <?=$awc['label']?> OPEN
          </span>
        <?php endif; ?>
      </div>
      <!-- Close all button for this period -->
      <?php if ($anyWinOpen): ?>
      <form method="post" style="display:inline"
            onsubmit="return confirm('Close all windows for <?=e(addslashes($p['name']))?>')">
        <?=csrfField()?>
        <input type="hidden" name="action" value="close_all_period"/>
        <input type="hidden" name="per_id" value="<?=$p['id']?>"/>
        <button type="submit" class="filter-button button-sm"
                style="color:#dc2626;border-color:#fecaca;font-size:11.5px">
          🔒 Close All Windows
        </button>
      </form>
      <?php endif; ?>
    </div>

    <!-- 4 window controls in a row -->
    <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">

      <?php foreach ($WINDOWS as $wtype => $wcfg):
        $col      = 'window_'.$wtype;
        $isActive = (bool)$p[$col];
        $canOpen  = $hasConfig || $wtype !== 'marks_entry'; // non-marks windows don't need config
      ?>
      <div style="background:<?=$isActive?$wcfg['bg']:'#f8fafc'?>;
                  border:1.5px solid <?=$isActive?$wcfg['border']:'#e2e8f0'?>;
                  border-radius:10px;padding:10px 14px;min-width:160px;flex:1">

        <!-- Window label + status -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-size:12.5px;font-weight:800;color:<?=$isActive?$wcfg['color']:'#475569'?>">
            <?=$wcfg['icon']?> <?=$wcfg['label']?>
          </span>
          <?php if ($isActive): ?>
            <span style="width:8px;height:8px;border-radius:50%;background:#10b981;
                         box-shadow:0 0 0 2px #a7f3d0;display:inline-block"></span>
          <?php else: ?>
            <span style="width:8px;height:8px;border-radius:50%;background:#cbd5e1;display:inline-block"></span>
          <?php endif; ?>
        </div>

        <!-- Description -->
        <div style="font-size:10.5px;color:#94a3b8;margin-bottom:8px;line-height:1.3">
          <?=$wcfg['desc']?>
        </div>

        <!-- Timestamp if active -->
        <?php
          $atCol = 'window_'.$wtype.'_at';
          if ($isActive && !empty($p[$atCol])):
        ?>
        <div style="font-size:10px;color:<?=$wcfg['color']?>;margin-bottom:6px">
          Opened: <?=date('d M H:i', strtotime($p[$atCol]))?>
        </div>
        <?php endif; ?>

        <!-- Action button -->
        <?php if ($canOpen): ?>
          <?= winBtn($p, $wtype, $wcfg, $ayId, $isActive) ?>
        <?php else: ?>
          <span style="font-size:10.5px;color:#dc2626">Link config first</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div><!-- /window controls -->
  </div><!-- /period card -->
  <?php endforeach; ?>
</div><!-- /semester section -->
<?php endforeach; ?>


<!-- ══ TEACHER VIEW PREVIEW ═══════════════════════════════════ -->
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">👁 Teacher View Preview</div>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:14px">
    This is exactly what teachers see on their <strong>Marks Entry</strong> page right now.
  </p>
  <?php
  try {
    $visibleConfigs = $pdo->prepare(
        "SELECT ac.name cfg_name, ac.max_marks, p.name pname, p.window_marks_entry,
                p.window_notes_open, p.window_notes_review, p.window_test, s.name sname
         FROM assessment_configs ac
         JOIN periods p ON p.id=ac.period_id
         JOIN semesters s ON s.id=p.semester_id
         WHERE ac.academic_year_id=? AND ac.is_active=1 AND p.window_marks_entry=1
         ORDER BY ac.sequence"
    );
    $visibleConfigs->execute([$ayId]);
    $visibleConfigs = $visibleConfigs->fetchAll();
  } catch (Throwable $e) { $visibleConfigs = []; }
  ?>
  <?php if (empty($visibleConfigs)): ?>
  <div style="text-align:center;padding:28px;background:#fef3c7;border-radius:var(--radius);border:1px solid #fde68a">
    <div style="font-size:32px;margin-bottom:8px">🔒</div>
    <strong style="color:#92400e;font-size:14px">No Marks Submission window is open.</strong>
    <p style="font-size:13px;color:#78350f;margin-top:4px">
      Teachers see an empty Assessment Period dropdown and cannot enter any marks.
      Open the <strong>Marks Submission</strong> window on a period to unlock entry.
    </p>
  </div>
  <?php else: ?>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php foreach ($visibleConfigs as $vc): ?>
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:12px 18px">
      <div style="font-size:13.5px;font-weight:800;color:#065f46">✏️ <?=e($vc['cfg_name'])?></div>
      <div style="font-size:11.5px;color:#047857;margin-top:4px">
        <?=e($vc['sname'])?> — <?=e($vc['pname'])?> · Max marks: <?=$vc['max_marks']?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Also show any other open windows -->
  <?php
  $otherOpen = $pdo->query(
      "SELECT p.name pname, s.name sname,
              p.window_notes_open, p.window_notes_review, p.window_test
       FROM periods p JOIN semesters s ON s.id=p.semester_id
       WHERE s.academic_year_id=$ayId
         AND (p.window_notes_open=1 OR p.window_notes_review=1 OR p.window_test=1)
       ORDER BY p.id"
  )->fetchAll();
  if (!empty($otherOpen)):
  ?>
  <div style="margin-top:14px">
    <div style="font-size:12.5px;font-weight:700;color:var(--ink-soft);margin-bottom:8px">Other active windows (visible to teachers as status banners):</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($otherOpen as $oo):
        $wt = $oo['window_notes_open'] ? 'notes_open' : ($oo['window_notes_review'] ? 'notes_review' : 'test');
        $wc = $WINDOWS[$wt];
      ?>
      <span style="background:<?=$wc['bg']?>;color:<?=$wc['color']?>;border:1px solid <?=$wc['border']?>;
                   padding:5px 12px;border-radius:12px;font-size:12px;font-weight:700">
        <?=$wc['icon']?> <?=e($oo['pname'])?> — <?=$wc['label']?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>


<!-- ══ RECENT ACTIVITY LOG ════════════════════════════════════ -->
<?php if (!empty($actLog)): ?>
<div class="form-section">
  <div class="form-section-title">📋 Recent Activity Log</div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>Period</th>
          <th>Window</th>
          <th>Action</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($actLog as $log):
          $wc = $WINDOWS[$log['window_type']] ?? ['icon'=>'⚙','label'=>$log['window_type'],'color'=>'var(--ink)','bg'=>'#f8fafc','border'=>'#e2e8f0'];
        ?>
        <tr>
          <td style="font-size:12px;color:var(--ink-soft)"><?=date('d M Y H:i', strtotime($log['done_at']))?></td>
          <td><strong><?=e($log['pname'])?></strong></td>
          <td>
            <span style="background:<?=$wc['bg']?>;color:<?=$wc['color']?>;border:1px solid <?=$wc['border']?>;
                         padding:2px 8px;border-radius:10px;font-size:11.5px;font-weight:700">
              <?=$wc['icon']?> <?=$wc['label']?>
            </span>
          </td>
          <td>
            <?php if ($log['action']==='open'): ?>
              <span class="status approved" style="font-size:11px">Opened</span>
            <?php else: ?>
              <span class="status warning" style="font-size:11px">Closed</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px"><?=e($log['uname']??'System')?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
