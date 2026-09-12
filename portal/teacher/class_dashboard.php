<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['class_teacher']);  // class_teacher ONLY

$activePage = 'class_dashboard';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

include __DIR__.'/includes/resolve_teacher.php';

// ── Resolve the class this teacher is responsible for ─────────
// Class teacher is primarily responsible for ONE class
$myClass = $pdo->prepare(
    "SELECT c.id,c.name,g.name grade_name,g.sequence
     FROM teacher_assignments ta
     JOIN classes c ON c.id=ta.class_id
     JOIN grades  g ON g.id=c.grade_id
     WHERE ta.teacher_id=? AND ta.academic_year_id=?
     ORDER BY g.sequence,c.name LIMIT 1"
);
$myClass->execute([$teacherId,$ayId]); $myClass = $myClass->fetch();

// Allow override via GET
$classId = (int)($_GET['class_id'] ?? ($myClass['id'] ?? 0));
if (!$classId) { redirect(BASE_URL.'/portal/teacher/'); }

// Get full class info
$classInfo = $pdo->query("SELECT c.*,g.name grade_name FROM classes c JOIN grades g ON g.id=c.grade_id WHERE c.id=$classId")->fetch();
if (!$classInfo) { redirect(BASE_URL.'/portal/teacher/'); }

// All classes this teacher can manage
$allMyClasses = $pdo->prepare(
    "SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name"
);
$allMyClasses->execute([$teacherId,$ayId]); $allMyClasses=$allMyClasses->fetchAll();

// ── Students ──────────────────────────────────────────────────
$students = $pdo->query(
    "SELECT s.*,g.name grade_name FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.current_class_id=$classId AND s.status='Active'
     ORDER BY s.last_name,s.first_name"
)->fetchAll();
$totalStudents = count($students);

// ── Attendance today ──────────────────────────────────────────
try {
    $attToday = $pdo->query(
        "SELECT SUM(status='Present') p, SUM(status='Absent') a,
                SUM(status='Late') l, COUNT(*) t
         FROM attendance WHERE class_id=$classId AND date=CURDATE()"
    )->fetch();
    $attRateToday = ($attToday['t']>0) ? round($attToday['p']/$attToday['t']*100,1) : null;
} catch (Throwable $e) { $attToday=['p'=>0,'a'=>0,'l'=>0,'t'=>0]; $attRateToday=null; }

// ── Attendance this year (for trend) ─────────────────────────
try {
    $attYear = $pdo->query(
        "SELECT ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1)
         FROM attendance WHERE class_id=$classId AND academic_year_id=$ayId"
    )->fetchColumn();
} catch (Throwable $e) { $attYear = null; }

// ── Chronic absentees (>3 absences) ──────────────────────────
try {
    $absentees = $pdo->query(
        "SELECT s.first_name,s.last_name,s.student_id,s.phone,
                COUNT(*) absent_days
         FROM attendance a JOIN students s ON s.id=a.student_id
         WHERE a.class_id=$classId AND a.academic_year_id=$ayId AND a.status='Absent'
         GROUP BY s.id HAVING absent_days>=3 ORDER BY absent_days DESC LIMIT 10"
    )->fetchAll();
} catch (Throwable $e) { $absentees=[]; }

// ── Academic performance ──────────────────────────────────────
try {
    $classAvg = (float)$pdo->query(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1)
         FROM assessment_scores
         WHERE class_id=$classId AND academic_year_id=$ayId
           AND max_marks>0 AND status IN ('approved','published')"
    )->fetchColumn();
} catch (Throwable $e) { $classAvg = 0; }

// At-risk students (avg < 50%)
try {
    $atRisk = $pdo->query(
        "SELECT s.first_name,s.last_name,s.student_id,
                ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
         FROM students s
         LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
           AND asc2.academic_year_id=$ayId AND asc2.max_marks>0
           AND asc2.status IN ('approved','published')
         WHERE s.current_class_id=$classId AND s.status='Active'
         GROUP BY s.id HAVING avg_pct IS NOT NULL AND avg_pct < 50
         ORDER BY avg_pct ASC LIMIT 10"
    )->fetchAll();
} catch (Throwable $e) { $atRisk=[]; }

// Missing marks (students with no approved marks)
try {
    $missingMarks = $pdo->query(
        "SELECT s.first_name,s.last_name,s.student_id
         FROM students s
         WHERE s.current_class_id=$classId AND s.status='Active'
           AND s.id NOT IN (
               SELECT DISTINCT student_id FROM assessment_scores
               WHERE class_id=$classId AND academic_year_id=$ayId
                 AND status IN ('submitted','approved','published')
           )
         ORDER BY s.last_name LIMIT 15"
    )->fetchAll();
} catch (Throwable $e) { $missingMarks=[]; }

// ── Discipline ────────────────────────────────────────────────
try {
    $openCases = $pdo->query(
        "SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname
         FROM discipline_records d JOIN students s ON s.id=d.student_id
         WHERE s.current_class_id=$classId AND d.resolved=0
           AND d.academic_year_id=$ayId
         ORDER BY d.incident_date DESC LIMIT 10"
    )->fetchAll();
    $totalOpenCases = count($openCases);
} catch (Throwable $e) { $openCases=[]; $totalOpenCases=0; }

// ── POST: add discipline referral / send message ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_discipline') {
        $sid    = (int)($_POST['student_id'] ?? 0);
        $cat    = $_POST['category'] ?? 'Misconduct';
        $desc   = trim($_POST['description'] ?? '');
        $atn    = $_POST['action_taken'] ?? 'Verbal Warning';
        $date   = $_POST['incident_date'] ?? date('Y-m-d');
        if ($sid && $desc) {
            try {
                $pdo->prepare(
                    "INSERT INTO discipline_records (student_id,incident_date,category,description,action_taken,recorded_by,academic_year_id)
                     VALUES (?,?,?,?,?,?,?)"
                )->execute([$sid,$date,$cat,$desc,$atn,currentUserId(),$ayId]);
                auditLog('create','discipline','discipline_record',(int)$pdo->lastInsertId(),'','Class teacher referral');
                flash('success','Discipline referral recorded.');
            } catch (Throwable $e) { flash('error','Failed to save.'); }
        }
    }
    redirect(BASE_URL.'/portal/teacher/class_dashboard.php?class_id='.$classId);
}

$ini = strtoupper(substr($teacher['first_name'],0,1).substr($teacher['last_name'],0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Class — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <div style="background:var(--primary);color:#fff;padding:6px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:700">Class Teacher</div>
        <h1 style="font-size:22px;font-weight:800"><?= e($classInfo['name']) ?></h1>
        <span style="font-size:13px;color:var(--ink-soft)"><?= e($classInfo['grade_name']) ?></span>
      </div>
      <p style="color:var(--ink-soft);font-size:13px"><?= e($ay) ?> &mdash; <?= $totalStudents ?> students</p>
    </div>
    <!-- Class switcher if assigned to multiple -->
    <?php if (count($allMyClasses) > 1): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach ($allMyClasses as $c): ?>
      <a href="?class_id=<?= $c['id'] ?>"
         style="padding:6px 14px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:600;border:1.5px solid <?= $classId==$c['id']?'var(--primary)':'var(--line)' ?>;background:<?= $classId==$c['id']?'var(--primary)':'#fff' ?>;color:<?= $classId==$c['id']?'#fff':'var(--ink2)' ?>;text-decoration:none">
        <?= e($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- KPI Metrics -->
  <div class="metric-grid" style="margin-bottom:24px">
    <div class="metric-card">
      <div class="metric-top"><span>Students</span><div class="metric-icon">🎓</div></div>
      <strong><?= $totalStudents ?></strong><small><i></i>Active</small>
    </div>
    <div class="metric-card <?= $attRateToday!==null&&$attRateToday<80?'finance-metrics':'' ?>">
      <div class="metric-top"><span>Attendance Today</span><div class="metric-icon">📆</div></div>
      <strong style="color:<?= $attRateToday!==null?($attRateToday>=80?'var(--green)':'var(--error)'):'inherit' ?>"><?= $attRateToday!==null?$attRateToday.'%':'Not taken' ?></strong>
      <small><i></i><?= $attToday['p'] ?> present / <?= $attToday['t'] ?> total</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Year Attendance</span><div class="metric-icon">📊</div></div>
      <strong style="color:<?= $attYear?($attYear>=80?'var(--green)':'var(--warning)'):'inherit' ?>"><?= $attYear!==null?$attYear.'%':'—' ?></strong>
      <small><i></i>This year</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Class Average</span><div class="metric-icon">📚</div></div>
      <strong style="color:<?= $classAvg>=70?'var(--green)':($classAvg>=50?'var(--warning)':'var(--error)') ?>"><?= $classAvg?$classAvg.'%':'—' ?></strong>
      <small><i></i>Approved marks</small>
    </div>
    <div class="metric-card <?= count($atRisk)>0?'finance-metrics':'' ?>">
      <div class="metric-top"><span>At Risk</span><div class="metric-icon">⚠️</div></div>
      <strong style="color:<?= count($atRisk)>0?'var(--error)':'inherit' ?>"><?= count($atRisk) ?></strong>
      <small><i></i>Below 50%</small>
    </div>
    <div class="metric-card <?= $totalOpenCases>0?'finance-metrics':'' ?>">
      <div class="metric-top"><span>Open Discipline</span><div class="metric-icon">⚖️</div></div>
      <strong style="color:<?= $totalOpenCases>0?'var(--warning)':'inherit' ?>"><?= $totalOpenCases ?></strong>
      <small><i></i>Unresolved</small>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="quick-grid" style="margin-bottom:24px">
    <a href="<?= BASE_URL ?>/portal/teacher/take_attendance.php?class_id=<?= $classId ?>" class="quick-item"><span class="qi-icon">📆</span><div><strong>Take Attendance</strong><small><?= $attRateToday!==null?'Today: '.$attRateToday.'%':'Not taken today' ?></small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php?class_id=<?= $classId ?>"    class="quick-item"><span class="qi-icon">✏️</span><div><strong>Enter Marks</strong><small>Assessment scores</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/class_reports.php?class_id=<?= $classId ?>"  class="quick-item"><span class="qi-icon">📑</span><div><strong>Class Reports</strong><small>Attendance & performance</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/report_cards.php?class_id=<?= $classId ?>"   class="quick-item"><span class="qi-icon">📋</span><div><strong>Report Cards</strong><small>Add comments</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/results.php?class_id=<?= $classId ?>"        class="quick-item"><span class="qi-icon">📊</span><div><strong>Results</strong><small>Class performance</small></div></a>
    <button onclick="document.getElementById('discModal').style.display='flex'" class="quick-item" style="background:#fff;border:1px solid var(--line);border-radius:var(--radius-sm);cursor:pointer;text-align:left"><span class="qi-icon">⚖️</span><div><strong>Discipline Referral</strong><small>Log incident</small></div></button>
  </div>

  <!-- 3-col panels: At-risk + Absentees + Discipline -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px" class="class-panels">

    <!-- At-risk students -->
    <div class="panel" style="padding:18px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="font-weight:700;font-size:13px">⚠️ Students At Risk</h3>
        <a href="<?= BASE_URL ?>/portal/teacher/class_reports.php?class_id=<?= $classId ?>&tab=performance" class="filter-button">All →</a>
      </div>
      <?php if (empty($atRisk)): ?>
      <p style="color:var(--ink-faint);font-size:12px;text-align:center;padding:12px">✅ No at-risk students</p>
      <?php else: foreach ($atRisk as $s): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12px">
        <div>
          <strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong>
          <div style="font-size:10px;color:var(--ink-faint)"><?= e($s['student_id']) ?></div>
        </div>
        <span style="color:var(--error);font-weight:700"><?= $s['avg_pct'] ?>%</span>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Chronic absentees -->
    <div class="panel" style="padding:18px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="font-weight:700;font-size:13px">📆 Absent 3+ Days</h3>
        <a href="<?= BASE_URL ?>/portal/teacher/class_reports.php?class_id=<?= $classId ?>&tab=attendance" class="filter-button">Details →</a>
      </div>
      <?php if (empty($absentees)): ?>
      <p style="color:var(--ink-faint);font-size:12px;text-align:center;padding:12px">✅ No chronic absentees</p>
      <?php else: foreach ($absentees as $s): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12px">
        <div>
          <strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong>
          <?php if($s['phone']):?><div style="font-size:10px;color:var(--ink-faint)"><?= e($s['phone']) ?></div><?php endif; ?>
        </div>
        <span class="status warning" style="font-size:10px"><?= $s['absent_days'] ?> days</span>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Open discipline -->
    <div class="panel" style="padding:18px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="font-weight:700;font-size:13px">⚖️ Open Discipline Cases</h3>
        <button onclick="document.getElementById('discModal').style.display='flex'" style="font-size:11px;color:var(--primary);font-weight:600;background:none;border:none;cursor:pointer">+ Refer</button>
      </div>
      <?php if (empty($openCases)): ?>
      <p style="color:var(--ink-faint);font-size:12px;text-align:center;padding:12px">✅ No open cases</p>
      <?php else: foreach ($openCases as $c): ?>
      <div style="padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12px">
        <strong><?= e($c['sname']) ?></strong>
        <div style="color:var(--ink-soft);font-size:11px"><?= e($c['category']) ?> · <?= date('M d',strtotime($c['incident_date'])) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>

  <!-- Students missing marks -->
  <?php if (!empty($missingMarks)): ?>
  <div class="panel" style="padding:18px;margin-bottom:20px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:12px">📝 Students with No Submitted Marks (<?= count($missingMarks) ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($missingMarks as $s): ?>
      <span style="background:var(--warning-soft,#fef9ec);color:var(--warning,#b45309);font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px">
        <?= e($s['first_name'].' '.$s['last_name']) ?> (<?= e($s['student_id']) ?>)
      </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Full student list -->
  <div class="panel" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">👩‍🎓 Class Register — <?= e($classInfo['name']) ?></h3>
      <a href="<?= BASE_URL ?>/portal/teacher/students.php?class_id=<?= $classId ?>" class="filter-button">Full View →</a>
    </div>
    <div class="table-wrap" style="border:none">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Phone</th><th>Guardian</th></tr></thead>
        <tbody>
          <?php foreach ($students as $i => $s):
            $ini2 = strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1));
          ?>
          <tr>
            <td class="muted"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0"><?= $ini2 ?></div>
                <strong style="font-size:13px"><?= e($s['first_name'].' '.$s['last_name']) ?></strong>
              </div>
            </td>
            <td class="muted"><?= e($s['student_id']) ?></td>
            <td class="muted"><?= e($s['phone']??'—') ?></td>
            <td class="muted"><?= e($s['guardian_name']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<!-- Discipline Referral Modal -->
<div id="discModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);max-width:480px;width:100%;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:16px">⚖️ Discipline Referral</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_discipline"/>
      <div class="form-group">
        <label>Student *
          <select name="student_id" required>
            <option value="">Select student…</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>"><?= e($s['first_name'].' '.$s['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Category
            <select name="category">
              <?php foreach (['Misconduct','Late to Class','Truancy','Bullying','Cheating','Vandalism','Disrespect','Other'] as $c): ?>
              <option><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label>Date<input type="date" name="incident_date" value="<?= date('Y-m-d') ?>"/></label>
        </div>
      </div>
      <div class="form-group"><label>Action Taken
        <select name="action_taken">
          <?php foreach (['Verbal Warning','Written Warning','Parent Notified','Sent to VP','Sent to Principal','Counseling','Suspension Recommended','Other'] as $a): ?>
          <option><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </label></div>
      <div class="form-group"><label>Description *<textarea name="description" rows="3" required placeholder="Describe the incident…"></textarea></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" onclick="document.getElementById('discModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Submit Referral</button>
      </div>
    </form>
  </div>
</div>
<script>document.getElementById('discModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});</script>
<style>@media(max-width:768px){.class-panels{grid-template-columns:1fr !important}}</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
