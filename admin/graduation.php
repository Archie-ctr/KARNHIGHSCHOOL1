<?php
$pageTitle   = 'Graduation';
$activeAdmin = 'graduation';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$canApprove = isPrincipal();

// ── Ensure table exists ───────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS graduation_records (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id      INT UNSIGNED NOT NULL UNIQUE,
        academic_year_id INT UNSIGNED NOT NULL,
        graduation_date DATE NOT NULL,
        status          ENUM('eligible','approved','graduated','withheld') NOT NULL DEFAULT 'eligible',
        overall_average DECIMAL(5,2) NULL,
        certificate_number VARCHAR(40) NULL UNIQUE,
        approved_by     INT UNSIGNED NULL,
        approved_at     DATETIME NULL,
        notes           TEXT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_grad_ay (academic_year_id),
        INDEX idx_grad_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_list') {
        // Find all Grade 12 students with 'Graduated' status from promotion
        $grade12 = $pdo->query(
            "SELECT id FROM grades WHERE sequence=(SELECT MAX(sequence) FROM grades WHERE is_active=1) LIMIT 1"
        )->fetchColumn();

        if ($grade12) {
            $eligible = $pdo->prepare(
                "SELECT s.id, ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
                 FROM students s
                 LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
                   AND asc2.academic_year_id=? AND asc2.status IN ('approved','published')
                   AND asc2.max_marks > 0
                 WHERE s.academic_year_id=? AND s.current_grade_id=? AND s.status='Active'
                 GROUP BY s.id"
            );
            $eligible->execute([$ayId, $ayId, $grade12]);
            $eligible = $eligible->fetchAll();
            $inserted = 0;
            foreach ($eligible as $e) {
                try {
                    $pdo->prepare(
                        "INSERT IGNORE INTO graduation_records
                         (student_id,academic_year_id,graduation_date,overall_average,status)
                         VALUES (?,?,CURDATE(),?,'eligible')"
                    )->execute([$e['id'], $ayId, $e['avg_pct']]);
                    $inserted++;
                } catch (Throwable $ex) {}
            }
            auditLog('create','graduation','graduation_list',0,'','Generated '.$inserted.' records for '.$ay);
            flash('success','Graduation list generated — '.$inserted.' students added.');
        }

    } elseif ($action === 'approve_student' && $canApprove) {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid) {
            $cert = 'KHS-CERT-'.date('Y').'-'.str_pad($sid, 5, '0', STR_PAD_LEFT);
            $pdo->prepare(
                "UPDATE graduation_records
                 SET status='approved', approved_by=?, approved_at=NOW(), certificate_number=?
                 WHERE student_id=?"
            )->execute([currentUserId(), $cert, $sid]);
            auditLog('approve','graduation','student',$sid,'eligible','approved');
            flash('success','Student graduation approved. Certificate: '.$cert);
        }

    } elseif ($action === 'approve_all' && $canApprove) {
        $rows = $pdo->prepare(
            "SELECT gr.student_id FROM graduation_records gr
             WHERE gr.academic_year_id=? AND gr.status='eligible'"
        );
        $rows->execute([$ayId]);
        $count = 0;
        foreach ($rows->fetchAll() as $r) {
            $cert = 'KHS-CERT-'.date('Y').'-'.str_pad($r['student_id'], 5, '0', STR_PAD_LEFT);
            $pdo->prepare(
                "UPDATE graduation_records
                 SET status='approved', approved_by=?, approved_at=NOW(), certificate_number=?
                 WHERE student_id=? AND status='eligible'"
            )->execute([currentUserId(), $cert, $r['student_id']]);
            $count++;
        }
        auditLog('approve_all','graduation','graduation_records',0,'','Approved '.$count.' students');
        flash('success','All eligible students approved ('.$count.').');

    } elseif ($action === 'graduate_student' && $canApprove) {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid) {
            $pdo->prepare("UPDATE graduation_records SET status='graduated' WHERE student_id=?")->execute([$sid]);
            $pdo->prepare("UPDATE students SET status='Graduated', graduation_date=CURDATE() WHERE id=?")->execute([$sid]);
            auditLog('graduate','graduation','student',$sid,'approved','graduated');
            flash('success','Student marked as graduated.');
        }

    } elseif ($action === 'withhold' && $canApprove) {
        $sid  = (int)($_POST['student_id'] ?? 0);
        $note = trim($_POST['notes'] ?? '');
        if ($sid) {
            $pdo->prepare("UPDATE graduation_records SET status='withheld', notes=? WHERE student_id=?")->execute([$note ?: null, $sid]);
            auditLog('withhold','graduation','student',$sid,'','Withheld: '.$note);
            flash('warning','Graduation withheld for student.');
        }
    }
    redirect(BASE_URL.'/admin/graduation.php?tab='.urlencode($_POST['tab'] ?? 'list'));
}

// ── Data ──────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'list';

// Get grade 12 ID
$grade12Id = $pdo->query(
    "SELECT id FROM grades WHERE sequence=(SELECT MAX(sequence) FROM grades WHERE is_active=1) LIMIT 1"
)->fetchColumn();
$grade12Name = $grade12Id ? $pdo->query("SELECT name FROM grades WHERE id=$grade12Id")->fetchColumn() : 'Grade 12';

// Graduation records
$gradRecords = $pdo->prepare(
    "SELECT gr.*,
            s.student_id student_code, s.first_name, s.last_name, s.gender, s.date_of_birth, s.phone,
            g.name grade_name, c.name class_name,
            u.name approved_by_name
     FROM graduation_records gr
     JOIN students s ON s.id=gr.student_id
     LEFT JOIN grades g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     LEFT JOIN users u ON u.id=gr.approved_by
     WHERE gr.academic_year_id=?
     ORDER BY gr.status, s.last_name, s.first_name"
);
$gradRecords->execute([$ayId]);
$gradRecords = $gradRecords->fetchAll();

// Summary counts
$statusCounts = [];
foreach ($gradRecords as $r) $statusCounts[$r['status']] = ($statusCounts[$r['status']] ?? 0) + 1;

// Grade 12 students not yet in graduation list
$notListed = $grade12Id ? $pdo->prepare(
    "SELECT s.id, s.student_id student_code, s.first_name, s.last_name, s.gender,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
     FROM students s
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
       AND asc2.academic_year_id=? AND asc2.status IN ('approved','published') AND asc2.max_marks>0
     WHERE s.academic_year_id=? AND s.current_grade_id=? AND s.status='Active'
       AND s.id NOT IN (SELECT student_id FROM graduation_records WHERE academic_year_id=?)
     GROUP BY s.id ORDER BY s.last_name"
) : null;
if ($notListed) { $notListed->execute([$ayId,$ayId,$grade12Id,$ayId]); $notListed = $notListed->fetchAll(); }
else $notListed = [];

$statusColor = ['eligible'=>'new-s','approved'=>'approved','graduated'=>'approved','withheld'=>'warning'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Student Management <span></span></div>
    <h1>Graduation</h1>
    <p><?= e($ay) ?> &mdash; <?= e($grade12Name) ?> Graduation Management</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canApprove && !empty($gradRecords)): ?>
    <form method="post" onsubmit="return confirm('Approve ALL eligible students?')" style="display:inline">
      <?= csrfField() ?><input type="hidden" name="action" value="approve_all"/>
      <input type="hidden" name="tab" value="list"/>
      <button type="submit" class="button button-secondary">✅ Approve All Eligible</button>
    </form>
    <?php endif; ?>
    <form method="post" style="display:inline">
      <?= csrfField() ?><input type="hidden" name="action" value="generate_list"/>
      <input type="hidden" name="tab" value="list"/>
      <button type="submit" class="button button-primary">🔄 Generate Graduation List</button>
    </form>
  </div>
</div>

<!-- Summary metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Eligible</span><div class="metric-icon">🎓</div></div>
    <strong><?= $statusCounts['eligible'] ?? 0 ?></strong><small><i></i>Awaiting approval</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Approved</span><div class="metric-icon" style="background:var(--green-soft);color:var(--green)">✓</div></div>
    <strong style="color:var(--green)"><?= $statusCounts['approved'] ?? 0 ?></strong><small><i></i>Principal approved</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Graduated</span><div class="metric-icon">🏅</div></div>
    <strong><?= $statusCounts['graduated'] ?? 0 ?></strong><small><i></i>Confirmed</small>
  </div>
  <div class="metric-card <?= ($statusCounts['withheld']??0)>0 ? 'finance-metrics':'' ?>">
    <div class="metric-top"><span>Withheld</span><div class="metric-icon" style="background:var(--warning-soft);color:var(--warning)">⚠️</div></div>
    <strong style="color:<?= ($statusCounts['withheld']??0)>0?'var(--warning)':'inherit' ?>"><?= $statusCounts['withheld'] ?? 0 ?></strong><small><i></i>Not graduating</small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=list"       class="tab-btn <?= $tab==='list'      ?'active':'' ?>">🎓 Graduation List (<?= count($gradRecords) ?>)</a>
  <a href="?tab=not_listed" class="tab-btn <?= $tab==='not_listed'?'active':'' ?>">⚠️ Not Yet Listed (<?= count($notListed) ?>)</a>
  <a href="?tab=certificates" class="tab-btn <?= $tab==='certificates'?'active':'' ?>">📜 Certificates</a>
</div>

<?php if ($tab === 'list'): ?>
<!-- ── GRADUATION LIST ─────────────────────────────────── -->
<?php if (empty($gradRecords)): ?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">🎓</div>
  <h3 style="margin-bottom:8px">No graduation list for <?= e($ay) ?></h3>
  <p style="color:var(--ink-soft)">Click "Generate Graduation List" to populate eligible <?= e($grade12Name) ?> students.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Student</th><th>Student ID</th><th>Class</th><th>Average</th><th>Status</th><th>Certificate #</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($gradRecords as $r):
        $name = e($r['first_name'].' '.$r['last_name']);
      ?>
      <tr>
        <td><strong><?= $name ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($r['gender']) ?></div></td>
        <td class="muted"><?= e($r['student_code']) ?></td>
        <td class="muted"><?= e($r['class_name'] ?? '—') ?></td>
        <td>
          <?php if ($r['overall_average']): ?>
          <strong style="color:<?= $r['overall_average']>=70?'var(--green)':($r['overall_average']>=50?'var(--warning)':'var(--error)') ?>">
            <?= $r['overall_average'] ?>%
          </strong>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><span class="status <?= $statusColor[$r['status']]??'new-s' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td class="muted" style="font-size:12px"><?= e($r['certificate_number'] ?? '—') ?></td>
        <td>
          <?php if ($canApprove): ?>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if ($r['status'] === 'eligible'): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="approve_student"/>
              <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>"/>
              <input type="hidden" name="tab" value="list"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">✓ Approve</button>
            </form>
            <button class="filter-button button-sm" style="color:var(--error)"
              onclick="document.getElementById('withholdModal<?= $r['student_id'] ?>').style.display='flex'">✗ Withhold</button>
            <?php elseif ($r['status'] === 'approved'): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="graduate_student"/>
              <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>"/>
              <input type="hidden" name="tab" value="list"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--primary)">🏅 Graduate</button>
            </form>
            <a href="<?= BASE_URL ?>/letters/graduation_cert.php?student_id=<?= $r['student_id'] ?>&ay_id=<?= $ayId ?>"
               class="filter-button button-sm" target="_blank">📜 Certificate</a>
            <?php elseif ($r['status'] === 'graduated'): ?>
            <a href="<?= BASE_URL ?>/letters/graduation_cert.php?student_id=<?= $r['student_id'] ?>&ay_id=<?= $ayId ?>"
               class="filter-button button-sm" target="_blank">📜 Certificate</a>
            <?php endif; ?>
          </div>

          <!-- Withhold modal per student -->
          <div id="withholdModal<?= $r['student_id'] ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
            <div style="background:#fff;border-radius:var(--radius-lg);max-width:420px;width:100%;padding:28px;box-shadow:var(--shadow-lg)">
              <h3 style="margin-bottom:8px">Withhold Graduation</h3>
              <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">Provide a reason for withholding graduation for <strong><?= $name ?></strong>.</p>
              <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="withhold"/>
                <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>"/>
                <input type="hidden" name="tab" value="list"/>
                <textarea name="notes" rows="3" placeholder="Reason (required)" required style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;margin-bottom:12px"></textarea>
                <div style="display:flex;gap:8px;justify-content:flex-end">
                  <button type="button" onclick="document.getElementById('withholdModal<?= $r['student_id'] ?>').style.display='none'" class="button button-secondary">Cancel</button>
                  <button type="submit" class="button" style="background:var(--error);color:#fff">Withhold</button>
                </div>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Export -->
<div style="display:flex;justify-content:flex-end;margin-top:12px">
  <a href="<?= BASE_URL ?>/admin/reports.php?export=csv&type=graduation_list&ay_id=<?= $ayId ?>"
     class="button button-secondary button-sm">📥 Export Graduation List CSV</a>
</div>
<?php endif; ?>

<?php elseif ($tab === 'not_listed'): ?>
<!-- ── NOT YET LISTED ──────────────────────────────────── -->
<?php if (empty($notListed)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">✅</div>
  <p style="color:var(--ink-soft)">All <?= e($grade12Name) ?> students are on the graduation list.</p>
</div>
<?php else: ?>
<div class="alert alert-warn" style="margin-bottom:16px">
  <?= count($notListed) ?> <?= e($grade12Name) ?> student<?= count($notListed)!==1?'s':'' ?> not yet on the graduation list. Click "Generate Graduation List" to add them.
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Student ID</th><th>Average</th></tr></thead>
    <tbody>
      <?php foreach ($notListed as $s): ?>
      <tr>
        <td><strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong></td>
        <td class="muted"><?= e($s['student_code']) ?></td>
        <td><?= $s['avg_pct'] ? '<strong>'.$s['avg_pct'].'%</strong>' : '<span class="muted">No marks</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'certificates'): ?>
<!-- ── CERTIFICATES ────────────────────────────────────── -->
<?php
$certified = array_filter($gradRecords, fn($r) => in_array($r['status'],['approved','graduated']));
?>
<?php if (empty($certified)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📜</div>
  <p style="color:var(--ink-soft)">No approved graduands yet. Approve students first to generate certificates.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Certificate #</th><th>Approved By</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($certified as $r): ?>
      <tr>
        <td><strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($r['student_code']) ?></div></td>
        <td><code style="font-size:12px"><?= e($r['certificate_number']) ?></code></td>
        <td class="muted"><?= e($r['approved_by_name'] ?? '—') ?></td>
        <td><span class="status <?= $r['status']==='graduated'?'approved':'new-s' ?>"><?= ucfirst($r['status']) ?></span></td>
        <td>
          <a href="<?= BASE_URL ?>/letters/graduation_cert.php?student_id=<?= $r['student_id'] ?>&ay_id=<?= $ayId ?>"
             class="filter-button button-sm" target="_blank">📜 View / Print</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
