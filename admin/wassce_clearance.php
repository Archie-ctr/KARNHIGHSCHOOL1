<?php
// ============================================================
// WASSCE / WACE School Clearance Management
// Grades: 3, 6, 9, 12
// ============================================================
require_once dirname(__DIR__).'/config/db.php';

// ── POST handler before any output ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    verifyCsrf();
    $pdo    = db();
    $ayId   = currentAcademicYearId();
    $action = $_POST['action'] ?? '';

    $canApprove  = canAny(['admissions.approve','admissions.recommend']) ||
                   hasRole(['principal','vice_principal','registrar','super_admin','sys_admin','school_admin']);
    $canWithhold = hasRole(['principal','vice_principal','super_admin','sys_admin','school_admin']);

    // ── Bulk generate pending records for eligible students ──
    if ($action === 'generate_all') {
        $gradeIds = [4, 7, 10, 13]; // Grade 3, 6, 9, 12
        $inserted = 0;
        foreach ($gradeIds as $gid) {
            $students = $pdo->prepare(
                "SELECT s.id FROM students s
                 WHERE s.current_grade_id=? AND s.status='Active' AND s.academic_year_id=?
                   AND s.id NOT IN (
                     SELECT student_id FROM student_clearances WHERE academic_year_id=?
                   )"
            );
            $students->execute([$gid, $ayId, $ayId]);
            foreach ($students->fetchAll() as $s) {
                try {
                    $pdo->prepare(
                        "INSERT IGNORE INTO student_clearances
                         (student_id,academic_year_id,grade_id,status)
                         VALUES (?,?,?,'pending')"
                    )->execute([$s['id'], $ayId, $gid]);
                    $inserted++;
                } catch (Throwable $e) {}
            }
        }
        flash('success', "Generated $inserted new clearance records.");

    } elseif ($action === 'approve' && $canApprove) {
        $stdId = (int)($_POST['student_id'] ?? 0);
        if ($stdId) {
            $pdo->prepare(
                "INSERT INTO student_clearances (student_id,academic_year_id,grade_id,status,cleared_by,cleared_at)
                 SELECT s.id,?,s.current_grade_id,'approved',?,NOW()
                 FROM students s WHERE s.id=?
                 ON DUPLICATE KEY UPDATE status='approved',cleared_by=?,cleared_at=NOW()"
            )->execute([$ayId, currentUserId(), $stdId, currentUserId()]);
            flash('success', 'Clearance approved.');
        }

    } elseif ($action === 'bulk_approve' && $canApprove) {
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        $updated = $pdo->prepare(
            "UPDATE student_clearances SET status='approved',cleared_by=?,cleared_at=NOW()
             WHERE academic_year_id=? AND status='pending'"
            .($gradeId ? " AND grade_id=$gradeId" : "")
        );
        $updated->execute([$ayId === 0 ? [currentUserId(), $ayId] : [currentUserId(), $ayId]][0]);
        $pdo->prepare(
            "UPDATE student_clearances SET status='approved',cleared_by=?,cleared_at=NOW()
             WHERE academic_year_id=? AND status='pending'"
        )->execute([currentUserId(), $ayId]);
        flash('success', 'All pending clearances approved.');

    } elseif ($action === 'issue' && $canApprove) {
        $stdId = (int)($_POST['student_id'] ?? 0);
        if ($stdId) {
            $pdo->prepare(
                "UPDATE student_clearances SET status='issued',issued_at=NOW()
                 WHERE student_id=? AND academic_year_id=?"
            )->execute([$stdId, $ayId]);
            flash('success', 'Clearance marked as issued.');
        }

    } elseif ($action === 'withhold' && $canWithhold) {
        $stdId  = (int)($_POST['student_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($stdId) {
            $pdo->prepare(
                "INSERT INTO student_clearances (student_id,academic_year_id,grade_id,status,withheld_reason)
                 SELECT s.id,?,s.current_grade_id,'withheld',?
                 FROM students s WHERE s.id=?
                 ON DUPLICATE KEY UPDATE status='withheld',withheld_reason=?"
            )->execute([$ayId, $reason, $stdId, $reason]);
            flash('warning', 'Clearance withheld.');
        }

    } elseif ($action === 'reset' && $canWithhold) {
        $stdId = (int)($_POST['student_id'] ?? 0);
        if ($stdId) {
            $pdo->prepare(
                "UPDATE student_clearances SET status='pending',withheld_reason=NULL
                 WHERE student_id=? AND academic_year_id=?"
            )->execute([$stdId, $ayId]);
            flash('info', 'Clearance reset to pending.');
        }
    }
    redirect(BASE_URL.'/admin/wassce_clearance.php?grade_id='.($_POST['grade_id']??'').'&tab='.($_POST['tab']??'list'));
}

// ── Normal page render ────────────────────────────────────────
requireRole(['principal','vice_principal','registrar','super_admin','sys_admin','school_admin']);
$pageTitle   = 'WASSCE / WACE Clearance';
$activeAdmin = 'wassce_clearance';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo    = db();
$ayId   = currentAcademicYearId();
$ay     = currentAcademicYearName();
$tab    = $_GET['tab']      ?? 'list';
$gFilter= (int)($_GET['grade_id'] ?? 0);
$q      = trim($_GET['q']   ?? '');

$canApprove  = canAny(['admissions.approve','admissions.recommend']) ||
               hasRole(['principal','vice_principal','registrar','super_admin','sys_admin','school_admin']);
$canWithhold = hasRole(['principal','vice_principal','super_admin','sys_admin','school_admin']);

// Eligible grade IDs + names
$eligibleGrades = [4=>'Grade 3', 7=>'Grade 6', 10=>'Grade 9', 13=>'Grade 12'];

// ── Summary counts per grade ──────────────────────────────────
$summary = [];
foreach ($eligibleGrades as $gid => $gname) {
    try {
        $row = $pdo->prepare(
            "SELECT
               COUNT(s.id) total_students,
               COUNT(sc.id) in_system,
               SUM(sc.status='pending')  pending,
               SUM(sc.status='approved') approved,
               SUM(sc.status='issued')   issued,
               SUM(sc.status='withheld') withheld
             FROM students s
             LEFT JOIN student_clearances sc
               ON sc.student_id=s.id AND sc.academic_year_id=$ayId
             WHERE s.current_grade_id=$gid AND s.status='Active' AND s.academic_year_id=$ayId"
        );
        $row->execute();
        $summary[$gid] = $row->fetch() + ['name' => $gname, 'grade_id' => $gid];
    } catch (Throwable $e) {
        $summary[$gid] = ['name'=>$gname,'grade_id'=>$gid,'total_students'=>0,'in_system'=>0,
                          'pending'=>0,'approved'=>0,'issued'=>0,'withheld'=>0];
    }
}

// ── Student list ──────────────────────────────────────────────
$students = [];
if ($tab === 'list') {
    $where  = ['s.status=\'Active\'', 's.academic_year_id='.$ayId,
               's.current_grade_id IN (4,7,10,13)'];
    $params = [];
    if ($gFilter) { $where[] = 's.current_grade_id=?'; $params[] = $gFilter; }
    if ($q)       { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)';
                    $like="%$q%"; array_push($params,$like,$like,$like); }
    $wsql = implode(' AND ', $where);
    try {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.student_id, s.first_name, s.last_name, s.gender,
                    g.name grade_name, g.id grade_id, c.name class_name,
                    COALESCE(sc.status,'not_generated') clr_status,
                    sc.cleared_at, sc.issued_at, sc.withheld_reason,
                    sc.id clr_id,
                    u.name cleared_by_name
             FROM students s
             LEFT JOIN grades g ON g.id=s.current_grade_id
             LEFT JOIN classes c ON c.id=s.current_class_id
             LEFT JOIN student_clearances sc
               ON sc.student_id=s.id AND sc.academic_year_id=$ayId
             LEFT JOIN users u ON u.id=sc.cleared_by
             WHERE $wsql
             ORDER BY g.sequence, s.last_name, s.first_name"
        );
        $stmt->execute($params);
        $students = $stmt->fetchAll();
    } catch (Throwable $e) { $students = []; }
}

// ── Status helpers ────────────────────────────────────────────
$statusColors = [
    'not_generated' => ['#6b7280','#f3f4f6'],
    'pending'       => ['#d97706','#fffbeb'],
    'approved'      => ['#059669','#ecfdf5'],
    'issued'        => ['#2563eb','#eff6ff'],
    'withheld'      => ['#dc2626','#fef2f2'],
];
$statusLabels = [
    'not_generated' => '— Not Generated',
    'pending'       => '⏳ Pending Approval',
    'approved'      => '✓ Approved',
    'issued'        => '📜 Issued',
    'withheld'      => '✗ Withheld',
];
?>

<!-- Page heading -->
<div class="page-heading">
  <div>
    <div class="eyebrow">Registrar <span></span></div>
    <h1>WASSCE / WACE School Clearance</h1>
    <p>Manage and issue school clearance certificates for Grades 3, 6, 9 &amp; 12 — <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="post" onsubmit="return confirm('Generate clearance records for all eligible students not yet in the system?')">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="generate_all"/>
      <button type="submit" class="button button-secondary">🔄 Generate All Records</button>
    </form>
    <?php if ($canApprove): ?>
    <form method="post" onsubmit="return confirm('Approve ALL pending clearances for this year?')">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="bulk_approve"/>
      <input type="hidden" name="grade_id" value="<?= $gFilter ?>"/>
      <button type="submit" class="button button-primary">✓ Approve All Pending</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- ── Summary cards per grade ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px">
  <?php foreach ($summary as $gid => $s):
    $total   = max(1, (int)$s['total_students']);
    $issued  = (int)$s['issued'];
    $pct     = $total ? round($issued/$total*100) : 0;
  ?>
  <div style="
      background:var(--surface);border:1.5px solid var(--line);border-radius:10px;
      padding:16px 18px;cursor:pointer;transition:box-shadow .15s;
      <?= $gFilter==$gid ? 'border-color:var(--primary);box-shadow:0 0 0 2px var(--primary-soft)' : '' ?>
  " onclick="window.location='?grade_id=<?= $gid ?>&tab=list'">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em"><?= $s['name'] ?></div>
        <div style="font-size:22px;font-weight:900;color:var(--ink);margin-top:2px"><?= $s['total_students'] ?></div>
        <div style="font-size:11.5px;color:var(--ink-soft)">students</div>
      </div>
      <div style="text-align:right;font-size:11.5px">
        <?php foreach (['pending'=>'⏳','approved'=>'✓','issued'=>'📜','withheld'=>'✗'] as $st=>$ic): ?>
        <div style="color:<?= $statusColors[$st][0] ?>;font-weight:700">
          <?= $ic ?> <?= (int)$s[$st] ?> <?= $st ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Progress bar -->
    <div style="background:var(--bg);border-radius:4px;height:5px;overflow:hidden">
      <div style="height:100%;border-radius:4px;background:var(--green);width:<?= $pct ?>%;transition:width .3s"></div>
    </div>
    <div style="font-size:10.5px;color:var(--ink-faint);margin-top:3px"><?= $pct ?>% issued</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Filter bar ── -->
<div class="list-content">
  <form method="get" class="filter-row" style="margin-bottom:16px">
    <input type="hidden" name="tab" value="list"/>
    <div class="table-search">🔍
      <input type="search" name="q" placeholder="Search name, student ID…" value="<?= e($q) ?>"/>
    </div>
    <select name="grade_id" class="filter-button" onchange="this.form.submit()">
      <option value="">All Eligible Grades</option>
      <?php foreach ($eligibleGrades as $gid => $gname): ?>
      <option value="<?= $gid ?>" <?= $gFilter==$gid?'selected':'' ?>><?= $gname ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="button button-primary button-sm">Filter</button>
    <?php if ($q || $gFilter): ?>
    <a href="?tab=list" class="filter-button">Clear</a>
    <?php endif; ?>
    <!-- Export -->
    <a href="<?= BASE_URL ?>/api/export.php?type=students&format=excel<?= $gFilter?"&grade_id=$gFilter":'' ?>"
       class="button button-secondary button-sm" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
    <a href="<?= BASE_URL ?>/api/export.php?type=students&format=pdf<?= $gFilter?"&grade_id=$gFilter":'' ?>"
       class="button button-secondary button-sm" style="background:#c00200;color:#fff" target="_blank">🖨 PDF List</a>
  </form>

  <!-- ── Student clearance table ── -->
  <?php if (empty($students)): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:44px;margin-bottom:14px">📋</div>
    <h3 style="font-weight:700;margin-bottom:6px">No students found</h3>
    <p style="color:var(--ink-soft)">
      <?= $q ? 'No matches for "'.$q.'".' : 'Click "Generate All Records" to create clearance entries for eligible students.' ?>
    </p>
  </div>
  <?php else: ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Student ID</th>
          <th>Grade / Class</th>
          <th>Clearance Status</th>
          <th>Approved By</th>
          <th>Date</th>
          <th style="min-width:250px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $st):
          $ini    = strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));
          $clrSt  = $st['clr_status'];
          $col    = $statusColors[$clrSt] ?? $statusColors['not_generated'];
          $lbl    = $statusLabels[$clrSt] ?? $clrSt;
        ?>
        <tr>
          <!-- Student -->
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar-sm" style="background:var(--primary-soft);color:var(--primary);font-size:11px"><?= e($ini) ?></div>
              <div>
                <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>
                <div style="font-size:11px;color:var(--ink-faint)"><?= e($st['gender']) ?></div>
              </div>
            </div>
          </td>

          <!-- ID -->
          <td class="muted" style="font-size:12px"><?= e($st['student_id']) ?></td>

          <!-- Grade/Class -->
          <td style="font-size:13px">
            <?= e($st['grade_name'] ?? '—') ?>
            <?= $st['class_name'] ? '<div style="font-size:11px;color:var(--ink-faint)">'.e($st['class_name']).'</div>' : '' ?>
          </td>

          <!-- Status badge -->
          <td>
            <span style="display:inline-block;padding:4px 10px;border-radius:16px;font-size:11.5px;font-weight:700;
                         color:<?= $col[0] ?>;background:<?= $col[1] ?>">
              <?= $lbl ?>
            </span>
            <?php if ($clrSt === 'withheld' && $st['withheld_reason']): ?>
            <div style="font-size:11px;color:var(--error);margin-top:2px"><?= e(mb_substr($st['withheld_reason'],0,50)) ?></div>
            <?php endif; ?>
          </td>

          <!-- Approved by -->
          <td class="muted" style="font-size:12px"><?= e($st['cleared_by_name'] ?? '—') ?></td>

          <!-- Date -->
          <td class="muted" style="font-size:12px">
            <?php
            $dateStr = match($clrSt) {
              'issued'   => $st['issued_at']  ? date('d M Y', strtotime($st['issued_at'])) : '—',
              'approved' => $st['cleared_at'] ? date('d M Y', strtotime($st['cleared_at'])): '—',
              default    => '—',
            };
            echo e($dateStr);
            ?>
          </td>

          <!-- Actions -->
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">

              <!-- Print clearance (always available for eligible) -->
              <a href="<?= BASE_URL ?>/letters/wassce_clearance.php?student_id=<?= $st['id'] ?>"
                 target="_blank"
                 class="filter-button button-sm"
                 style="color:#6366f1;border-color:#6366f1;font-weight:700">
                📜 Print
              </a>

              <?php if ($canApprove): ?>

              <?php if ($clrSt === 'not_generated' || $clrSt === 'pending'): ?>
              <!-- Approve -->
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="approve"/>
                <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
                <input type="hidden" name="grade_id"   value="<?= $gFilter ?>"/>
                <button type="submit" class="filter-button button-sm"
                        style="color:var(--green);border-color:var(--green);font-weight:700">
                  ✓ Approve
                </button>
              </form>
              <?php endif; ?>

              <?php if ($clrSt === 'approved'): ?>
              <!-- Mark as Issued -->
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="issue"/>
                <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
                <input type="hidden" name="grade_id"   value="<?= $gFilter ?>"/>
                <button type="submit" class="filter-button button-sm"
                        style="color:#2563eb;border-color:#2563eb;font-weight:700">
                  📤 Mark Issued
                </button>
              </form>
              <?php endif; ?>

              <?php if ($clrSt !== 'withheld' && $canWithhold): ?>
              <!-- Withhold -->
              <button class="filter-button button-sm"
                      style="color:var(--error);border-color:var(--error)"
                      onclick="document.getElementById('wh_<?= $st['id'] ?>').style.display='flex'">
                ✗ Withhold
              </button>
              <?php endif; ?>

              <?php if ($clrSt === 'withheld'): ?>
              <!-- Reset -->
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="reset"/>
                <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
                <input type="hidden" name="grade_id"   value="<?= $gFilter ?>"/>
                <button type="submit" class="filter-button button-sm">↺ Reset</button>
              </form>
              <?php endif; ?>

              <?php endif; // canApprove ?>
            </div>

            <!-- Withhold modal -->
            <?php if ($canWithhold): ?>
            <div id="wh_<?= $st['id'] ?>"
                 style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
                        z-index:300;align-items:center;justify-content:center;padding:20px">
              <div style="background:var(--surface);border-radius:12px;max-width:420px;width:100%;
                          padding:24px;box-shadow:0 12px 40px rgba(0,0,0,.3)">
                <h3 style="margin-bottom:8px;font-size:15px">✗ Withhold Clearance</h3>
                <p style="font-size:13px;color:var(--ink-soft);margin-bottom:14px">
                  Provide a reason for withholding clearance for
                  <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>.
                </p>
                <form method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="withhold"/>
                  <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
                  <input type="hidden" name="grade_id"   value="<?= $gFilter ?>"/>
                  <textarea name="reason" rows="3" required placeholder="Reason (required)"
                            style="width:100%;padding:10px;border:1.5px solid var(--line);
                                   border-radius:8px;font-family:inherit;font-size:13px;
                                   margin-bottom:12px;resize:vertical"></textarea>
                  <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button"
                            onclick="document.getElementById('wh_<?= $st['id'] ?>').style.display='none'"
                            class="button button-secondary button-sm">Cancel</button>
                    <button type="submit"
                            class="button button-sm"
                            style="background:var(--error);color:#fff;border:none">
                      ✗ Withhold
                    </button>
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

  <!-- Summary footer -->
  <div style="display:flex;justify-content:space-between;align-items:center;
              padding:10px 4px;font-size:12.5px;color:var(--ink-soft);margin-top:6px">
    <span><?= count($students) ?> student<?= count($students)!==1?'s':'' ?> shown</span>
    <span>
      <?php
      $issued   = count(array_filter($students, fn($s)=>$s['clr_status']==='issued'));
      $approved = count(array_filter($students, fn($s)=>$s['clr_status']==='approved'));
      $pending  = count(array_filter($students, fn($s)=>in_array($s['clr_status'],['pending','not_generated'])));
      ?>
      <span style="color:#059669;font-weight:700">📜 <?= $issued ?> Issued</span>
      &nbsp;·&nbsp;
      <span style="color:#2563eb;font-weight:700">✓ <?= $approved ?> Approved</span>
      &nbsp;·&nbsp;
      <span style="color:#d97706;font-weight:700">⏳ <?= $pending ?> Pending</span>
    </span>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
