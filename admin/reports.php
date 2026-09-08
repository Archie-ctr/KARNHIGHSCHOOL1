<?php
$pageTitle='Reports'; $activeAdmin='reports';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

// ── CSV EXPORTS ────────────────────────────────────────────────
$export = trim($_GET['export'] ?? '');
$type   = trim($_GET['type']   ?? '');
$month  = trim($_GET['month']  ?? '');

if ($export === 'csv' && $type) {
    $filename = $type.'_'.date('Y-m-d').'.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $fp = fopen('php://output','w');

    switch ($type) {

        case 'students':
            fputcsv($fp,['Student ID','Admission #','First Name','Last Name','Gender','Grade','Class','Status','Admission Date','Phone','County','Email']);
            $rows=$pdo->prepare("SELECT s.student_id,s.admission_number,s.first_name,s.last_name,s.gender,g.name,c.name,s.status,s.admission_date,s.phone,s.county,s.email FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.academic_year_id=? ORDER BY s.last_name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'payments':
            fputcsv($fp,['Receipt','Student','Student ID','Grade','Amount','Currency','Method','Date','Academic Year']);
            $rows=$pdo->prepare("SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name,p.amount,p.currency,p.payment_method,p.payment_date,ay.name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN academic_years ay ON ay.id=p.academic_year_id WHERE p.academic_year_id=? ORDER BY p.payment_date DESC");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'applications':
            fputcsv($fp,['App #','First Name','Last Name','DOB','Gender','County','Grade','Phone','Guardian','G.Phone','Status','Academic Year','Submitted']);
            $rows=$pdo->prepare("SELECT a.application_number,a.first_name,a.last_name,a.date_of_birth,a.gender,a.county,a.grade_applying_for,a.phone,a.guardian_name,a.guardian_phone,a.status,ay.name,a.created_at FROM applications a LEFT JOIN academic_years ay ON ay.id=a.academic_year_id WHERE a.academic_year_id=? ORDER BY a.created_at DESC");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'attendance':
            fputcsv($fp,['Student','Student ID','Grade','Class','Date','Status','Remarks']);
            $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name,c.name,a.date,a.status,a.remarks FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE a.academic_year_id=? ORDER BY a.date DESC,s.last_name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'teachers':
            fputcsv($fp,['Name','Email','Phone','Qualification','Specialization','Department','Status','Join Date']);
            $rows=$pdo->query("SELECT CONCAT(u.first_name,' ',u.last_name),u.email,u.phone,t.qualification,t.specialization,COALESCE(d.name,'—') dept,t.status,t.join_date FROM teachers t JOIN users u ON u.id=t.user_id LEFT JOIN departments d ON d.id=t.department_id ORDER BY u.last_name");
            foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'staff':
            fputcsv($fp,['Name','Email','Phone','Role','Status','Last Login']);
            $rows=$pdo->query("SELECT u.name,u.email,u.phone,r.label,IF(u.is_active,'Active','Inactive'),u.last_login FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name NOT IN ('student','parent','applicant') ORDER BY r.id,u.name");
            foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'discipline':
            fputcsv($fp,['Student','Student ID','Grade','Date','Category','Description','Action','Resolved','Academic Year']);
            $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name,d.incident_date,d.category,d.description,d.action_taken,IF(d.resolved,'Yes','No'),ay.name FROM discipline_records d JOIN students s ON s.id=d.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN academic_years ay ON ay.id=d.academic_year_id WHERE d.academic_year_id=? ORDER BY d.incident_date DESC");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'class_enrollment':
            fputcsv($fp,['Class','Grade','Total Students','Male','Female','Active']);
            $rows=$pdo->prepare("SELECT c.name,g.name,COUNT(s.id),SUM(s.gender='Male'),SUM(s.gender='Female'),SUM(s.status='Active') FROM students s JOIN classes c ON c.id=s.current_class_id JOIN grades g ON g.id=s.current_grade_id WHERE s.academic_year_id=? GROUP BY c.id,c.name,g.name ORDER BY g.sequence,c.name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;

        case 'staff_attendance':
            $mWhere = $month ? "WHERE DATE_FORMAT(sa.date,'%Y-%m')='".addslashes($month)."'" : '';
            fputcsv($fp,['Staff','Role','Date','Status','Remarks']);
            try {
                $rows=$pdo->query("SELECT u.name,r.label,sa.date,sa.status,sa.remarks FROM staff_attendance sa JOIN users u ON u.id=sa.user_id JOIN roles r ON r.id=u.role_id $mWhere ORDER BY sa.date DESC,u.name");
                foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r));
            } catch (Throwable $e) { fputcsv($fp,['No staff attendance data yet']); }
            break;

        case 'marks_summary':
            fputcsv($fp,['Student','Student ID','Grade','Subject','Average %','Grade Letter']);
            $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name grade,sub.name subject,ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,'—' FROM assessment_scores asc2 JOIN students s ON s.id=asc2.student_id JOIN subjects sub ON sub.id=asc2.subject_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE asc2.academic_year_id=? AND asc2.max_marks>0 GROUP BY s.id,sub.id ORDER BY s.last_name,sub.name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
    }
    fclose($fp); exit;
}

// ── Page data ──────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'academic';

// Shared summaries
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalStaff    = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')")->fetchColumn();
$fLRD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$fUSD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();

// Attendance rate
try {
    $attRate = $pdo->prepare("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance WHERE academic_year_id=?");
    $attRate->execute([$ayId]); $attRate = (float)$attRate->fetchColumn();
} catch (Throwable $e) { $attRate = 0; }
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Reports <span></span></div>
    <h1>Reports &amp; Exports</h1>
    <p><?= e($ay) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/admin/student_statistics.php" class="button button-secondary">📊 Statistics</a>
</div>

<!-- Quick stats -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div><strong><?= number_format($totalStudents) ?></strong><small><i></i><?= e($ay) ?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Active Staff</span><div class="metric-icon">👥</div></div><strong><?= number_format($totalStaff) ?></strong><small><i></i>All roles</small></div>
  <div class="metric-card"><div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div><strong><?= $attRate ?>%</strong><small><i></i>This year</small></div>
  <div class="metric-card"><div class="metric-top"><span>LRD Collected</span><div class="metric-icon">💰</div></div><strong>LRD <?= number_format($fLRD) ?></strong><small><i></i><?= e($ay) ?></small></div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:20px">
  <a href="?tab=academic"       class="tab-btn <?= $tab==='academic'      ?'active':'' ?>">🎓 Academic</a>
  <a href="?tab=student"        class="tab-btn <?= $tab==='student'       ?'active':'' ?>">👩‍🎓 Students</a>
  <a href="?tab=staff"          class="tab-btn <?= $tab==='staff'         ?'active':'' ?>">👥 Staff</a>
  <a href="?tab=attendance_rep" class="tab-btn <?= $tab==='attendance_rep'?'active':'' ?>">📆 Attendance</a>
  <a href="?tab=finance_rep"    class="tab-btn <?= $tab==='finance_rep'   ?'active':'' ?>">💰 Finance</a>
  <a href="?tab=discipline_rep" class="tab-btn <?= $tab==='discipline_rep'?'active':'' ?>">⚖️ Discipline</a>
  <a href="?tab=admin_rep"      class="tab-btn <?= $tab==='admin_rep'     ?'active':'' ?>">📋 Administrative</a>
</div>

<?php if ($tab === 'academic'): ?>
<!-- ═══════════════ ACADEMIC REPORTS ═══════════════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

  <?php
  // Class enrollment summary
  $classEnrol = $pdo->prepare("SELECT c.name class_name,g.name grade_name,COUNT(s.id) total,SUM(s.status='Active') active FROM students s JOIN classes c ON c.id=s.current_class_id JOIN grades g ON g.id=s.current_grade_id WHERE s.academic_year_id=? GROUP BY c.id ORDER BY g.sequence,c.name");
  $classEnrol->execute([$ayId]); $classEnrol = $classEnrol->fetchAll();

  // Grade distribution
  $gradeDist = $pdo->prepare("SELECT g.name,COUNT(s.id) cnt FROM students s JOIN grades g ON g.id=s.current_grade_id WHERE s.academic_year_id=? AND s.status='Active' GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence");
  $gradeDist->execute([$ayId]); $gradeDist = $gradeDist->fetchAll();
  ?>

  <!-- Class enrollment export -->
  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">🏫</div>
    <h3 style="font-weight:700;margin-bottom:6px">Class Enrollment Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Students per class with gender and active breakdowns.</p>
    <a href="?export=csv&type=class_enrollment" class="button button-secondary button-sm">📥 Export CSV</a>
    <?php if (!empty($classEnrol)): ?>
    <div style="margin-top:14px">
      <?php foreach ($classEnrol as $c): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($c['class_name']) ?> <span style="color:var(--ink-soft)">(<?= e($c['grade_name']) ?>)</span></span>
        <strong><?= $c['active'] ?> active</strong>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Marks / results summary -->
  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📊</div>
    <h3 style="font-weight:700;margin-bottom:6px">Marks Summary Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Average marks per student per subject for <?= e($ay) ?>.</p>
    <a href="?export=csv&type=marks_summary" class="button button-secondary button-sm">📥 Export CSV</a>
    <?php
    try {
        $marksStats = $pdo->prepare("SELECT COUNT(DISTINCT student_id) students_with_marks, COUNT(DISTINCT subject_id) subjects, ROUND(AVG(marks_obtained/max_marks*100),1) overall_avg FROM assessment_scores WHERE academic_year_id=? AND max_marks>0 AND status IN ('submitted','approved')");
        $marksStats->execute([$ayId]); $ms = $marksStats->fetch();
    } catch (Throwable $e) { $ms = null; }
    if ($ms): ?>
    <div style="margin-top:14px;display:flex;gap:16px">
      <div style="text-align:center"><strong style="font-size:1.2rem;color:var(--primary)"><?= $ms['students_with_marks'] ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Students</span></div>
      <div style="text-align:center"><strong style="font-size:1.2rem;color:var(--primary)"><?= $ms['subjects'] ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Subjects</span></div>
      <div style="text-align:center"><strong style="font-size:1.2rem;color:var(--primary)"><?= $ms['overall_avg'] ?>%</strong><br><span style="font-size:11px;color:var(--ink-soft)">Avg Score</span></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Grade distribution -->
  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📈</div>
    <h3 style="font-weight:700;margin-bottom:6px">Grade Distribution</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Active students per grade level this year.</p>
    <a href="<?= BASE_URL ?>/admin/student_statistics.php" class="button button-secondary button-sm">View Statistics</a>
    <?php $maxGD = max(array_column($gradeDist,'cnt') ?: [1]); ?>
    <div style="margin-top:14px">
      <?php foreach ($gradeDist as $g):
        $w = round($g['cnt']/$maxGD*100); ?>
      <div style="margin-bottom:6px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
          <span><?= e($g['name']) ?></span><strong><?= $g['cnt'] ?></strong>
        </div>
        <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
          <div style="width:<?= $w ?>%;height:100%;background:var(--primary);border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php elseif ($tab === 'student'): ?>
<!-- ═══════════════ STUDENT REPORTS ═══════════════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

  <?php
  $genderStats = $pdo->prepare("SELECT gender, COUNT(*) cnt FROM students WHERE academic_year_id=? AND status='Active' GROUP BY gender ORDER BY cnt DESC"); $genderStats->execute([$ayId]); $genderStats = $genderStats->fetchAll();
  $statusStats = $pdo->prepare("SELECT status, COUNT(*) cnt FROM students WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"); $statusStats->execute([$ayId]); $statusStats = $statusStats->fetchAll();
  $countyStats = $pdo->prepare("SELECT COALESCE(NULLIF(county,''),'Unknown') county,COUNT(*) cnt FROM students WHERE academic_year_id=? AND status='Active' GROUP BY county ORDER BY cnt DESC LIMIT 10"); $countyStats->execute([$ayId]); $countyStats = $countyStats->fetchAll();
  ?>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">🎓</div>
    <h3 style="font-weight:700;margin-bottom:6px">Student Register</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Full student directory with grade, class and contact details.</p>
    <a href="?export=csv&type=students" class="button button-secondary button-sm">📥 Export CSV</a>
    <div style="margin-top:14px">
      <?php foreach ($statusStats as $st): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($st['status']) ?></span><strong><?= $st['cnt'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">👫</div>
    <h3 style="font-weight:700;margin-bottom:6px">Gender Breakdown</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Active students by gender — <?= e($ay) ?>.</p>
    <a href="<?= BASE_URL ?>/admin/student_statistics.php" class="button button-secondary button-sm">View Statistics</a>
    <div style="margin-top:14px">
      <?php $maxG = max(array_column($genderStats,'cnt') ?: [1]); foreach ($genderStats as $g): $w = round($g['cnt']/$maxG*100); ?>
      <div style="margin-bottom:8px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
          <span><?= e($g['gender']?:'Not specified') ?></span>
          <strong><?= $g['cnt'] ?> (<?= $totalStudents>0?round($g['cnt']/$totalStudents*100,1):0 ?>%)</strong>
        </div>
        <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
          <div style="width:<?= $w ?>%;height:100%;background:<?= $g['gender']==='Male'?'#3b5bdb':'#e64980' ?>;border-radius:4px"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📍</div>
    <h3 style="font-weight:700;margin-bottom:6px">Students by County</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Top 10 counties of origin.</p>
    <div style="margin-top:4px">
      <?php foreach ($countyStats as $c): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($c['county']) ?></span><strong><?= $c['cnt'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📋</div>
    <h3 style="font-weight:700;margin-bottom:6px">Applications Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">All admission applications for <?= e($ay) ?>.</p>
    <a href="?export=csv&type=applications" class="button button-secondary button-sm">📥 Export CSV</a>
    <?php
    $appStats = $pdo->prepare("SELECT status,COUNT(*) cnt FROM applications WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"); $appStats->execute([$ayId]); $appStats=$appStats->fetchAll();
    ?>
    <div style="margin-top:14px">
      <?php foreach ($appStats as $a): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($a['status']) ?></span><strong><?= $a['cnt'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'staff'): ?>
<!-- ═══════════════ STAFF REPORTS ═══════════════ -->
<?php
$roleStats = $pdo->query("SELECT r.label,COUNT(u.id) cnt FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant') GROUP BY r.id,r.label ORDER BY r.id")->fetchAll();
$deptStats = [];
try { $deptStats=$pdo->query("SELECT d.name,COUNT(t.id) cnt FROM teachers t JOIN departments d ON d.id=t.department_id GROUP BY d.id,d.name ORDER BY cnt DESC")->fetchAll(); } catch(Throwable $e){}
?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">👩‍🏫</div>
    <h3 style="font-weight:700;margin-bottom:6px">Teacher Records</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">All active teachers with qualifications and assignments.</p>
    <a href="?export=csv&type=teachers" class="button button-secondary button-sm">📥 Export CSV</a>
    <?php
    try {
        $tStats = $pdo->query("SELECT COUNT(*) total, SUM(status='Active') active, SUM(status='Inactive') inactive FROM teachers")->fetch();
    } catch(Throwable $e) { $tStats = null; }
    if ($tStats): ?>
    <div style="margin-top:14px;display:flex;gap:20px">
      <div><strong style="font-size:1.3rem;color:var(--primary)"><?= $tStats['active'] ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Active</span></div>
      <div><strong style="font-size:1.3rem"><?= $tStats['inactive'] ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Inactive</span></div>
      <div><strong style="font-size:1.3rem"><?= $tStats['total'] ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Total</span></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">👥</div>
    <h3 style="font-weight:700;margin-bottom:6px">All Staff Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">All staff across all roles with login status.</p>
    <a href="?export=csv&type=staff" class="button button-secondary button-sm">📥 Export CSV</a>
    <div style="margin-top:14px">
      <?php foreach ($roleStats as $r): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($r['label']) ?></span><strong><?= $r['cnt'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">🏢</div>
    <h3 style="font-weight:700;margin-bottom:6px">Staff Attendance Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Export monthly staff attendance records.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <a href="?export=csv&type=staff_attendance&month=<?= date('Y-m') ?>" class="button button-secondary button-sm">📥 This Month CSV</a>
      <a href="<?= BASE_URL ?>/admin/staff_attendance.php" class="button button-secondary button-sm">📆 Staff Attendance</a>
    </div>
    <?php if (!empty($deptStats)): ?>
    <h4 style="font-size:12px;font-weight:700;color:var(--ink-soft);margin:14px 0 8px;text-transform:uppercase;letter-spacing:.06em">By Department</h4>
    <?php foreach ($deptStats as $d): ?>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
      <span><?= e($d['name']) ?></span><strong><?= $d['cnt'] ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<?php elseif ($tab === 'attendance_rep'): ?>
<!-- ═══════════════ ATTENDANCE REPORTS ═══════════════ -->
<?php
try {
    $attByGrade = $pdo->prepare("SELECT g.name grade_name,COUNT(*) total,SUM(a.status='Present') present,ROUND(SUM(a.status='Present')/COUNT(*)*100,1) rate FROM attendance a JOIN students s ON s.id=a.student_id JOIN grades g ON g.id=s.current_grade_id WHERE a.academic_year_id=? GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence");
    $attByGrade->execute([$ayId]); $attByGrade=$attByGrade->fetchAll();
} catch (Throwable $e) { $attByGrade = []; }
try {
    $absentees = $pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name) name,s.student_id,g.name grade_name,COUNT(*) absent_count FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE a.academic_year_id=? AND a.status='Absent' GROUP BY s.id,s.first_name,s.last_name,s.student_id,g.name HAVING absent_count>=5 ORDER BY absent_count DESC LIMIT 20");
    $absentees->execute([$ayId]); $absentees=$absentees->fetchAll();
} catch (Throwable $e) { $absentees = []; }
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px" class="rep-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📆 Attendance by Grade — <?= e($ay) ?></h3>
    <a href="?export=csv&type=attendance" class="button button-secondary button-sm" style="margin-bottom:14px">📥 Export Full CSV</a>
    <?php if (empty($attByGrade)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No attendance data for this year.</p>
    <?php else: foreach ($attByGrade as $a): $rateColor = $a['rate']>=90?'var(--green)':($a['rate']>=75?'var(--warning)':'var(--error)'); ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= e($a['grade_name']) ?></span>
      <div style="display:flex;gap:12px;align-items:center">
        <span style="color:var(--ink-soft)"><?= number_format($a['present']) ?>/<?= number_format($a['total']) ?></span>
        <strong style="color:<?= $rateColor ?>"><?= $a['rate'] ?>%</strong>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">⚠️ Frequent Absentees (5+ absences)</h3>
    <?php if (empty($absentees)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No students with 5+ absences this year.</p>
    <?php else: foreach ($absentees as $ab): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <div><strong><?= e($ab['name']) ?></strong><br><span style="font-size:11px;color:var(--ink-soft)"><?= e($ab['student_id']) ?> · <?= e($ab['grade_name']??'—') ?></span></div>
      <span class="status warning"><?= $ab['absent_count'] ?> days</span>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif ($tab === 'finance_rep'): ?>
<!-- ═══════════════ FINANCE REPORTS ═══════════════ -->
<?php
$payByGrade = $pdo->prepare("SELECT g.name grade_name,COUNT(DISTINCT p.student_id) paid_students,SUM(p.amount) total_lrd FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE p.academic_year_id=? AND p.currency='LRD' GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence"); $payByGrade->execute([$ayId]); $payByGrade=$payByGrade->fetchAll();
$payByMethod = $pdo->prepare("SELECT payment_method,COUNT(*) cnt,SUM(amount) total FROM payments WHERE academic_year_id=? GROUP BY payment_method ORDER BY total DESC"); $payByMethod->execute([$ayId]); $payByMethod=$payByMethod->fetchAll();
$payByMonth = $pdo->prepare("SELECT DATE_FORMAT(payment_date,'%Y-%m') mon,SUM(amount) total FROM payments WHERE academic_year_id=? AND currency='LRD' GROUP BY mon ORDER BY mon"); $payByMonth->execute([$ayId]); $payByMonth=$payByMonth->fetchAll();
?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">💵</div>
    <h3 style="font-weight:700;margin-bottom:8px">Collection Summary (LRD)</h3>
    <div style="font-size:1.5rem;font-weight:800;color:var(--primary);margin-bottom:4px">LRD <?= number_format($fLRD) ?></div>
    <div style="font-size:14px;color:var(--ink-soft);margin-bottom:12px">USD <?= number_format($fUSD,2) ?></div>
    <a href="?export=csv&type=payments" class="button button-secondary button-sm">📥 Export CSV</a>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:12px">💰 Collections by Grade</h3>
    <?php foreach ($payByGrade as $g): ?>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
      <span><?= e($g['grade_name']) ?> (<?= $g['paid_students'] ?> students)</span>
      <strong>LRD <?= number_format($g['total_lrd']) ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:12px">🔄 By Payment Method</h3>
    <?php foreach ($payByMethod as $m): ?>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
      <span><?= e($m['payment_method']) ?> (<?= $m['cnt'] ?>)</span>
      <strong><?= number_format($m['total']) ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:12px">📅 Monthly Collections (LRD)</h3>
    <?php $maxPay = max(array_column($payByMonth,'total') ?: [1]);
    foreach ($payByMonth as $m): $w = round($m['total']/$maxPay*100); ?>
    <div style="margin-bottom:7px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
        <span><?= e($m['mon']) ?></span><strong>LRD <?= number_format($m['total']) ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--green);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'discipline_rep'): ?>
<!-- ═══════════════ DISCIPLINE REPORTS ═══════════════ -->
<?php
$discStats = $pdo->prepare("SELECT category,COUNT(*) cnt,SUM(resolved) resolved FROM discipline_records WHERE academic_year_id=? GROUP BY category ORDER BY cnt DESC"); $discStats->execute([$ayId]); $discStats=$discStats->fetchAll();
$discByGrade = $pdo->prepare("SELECT g.name grade_name,COUNT(d.id) cnt FROM discipline_records d JOIN students s ON s.id=d.student_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE d.academic_year_id=? GROUP BY g.id,g.name,g.sequence ORDER BY cnt DESC"); $discByGrade->execute([$ayId]); $discByGrade=$discByGrade->fetchAll();
$discTotal = (int)$pdo->prepare("SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=?")->execute([$ayId]) ? $pdo->query("SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId")->fetchColumn() : 0;
$discOpen  = (int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND resolved=0")->fetchColumn();
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px" class="rep-grid">
  <div class="panel" style="padding:22px">
    <div style="display:flex;gap:20px;margin-bottom:16px">
      <div><strong style="font-size:1.5rem;color:var(--error)"><?= $discTotal ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Total incidents</span></div>
      <div><strong style="font-size:1.5rem;color:var(--warning)"><?= $discOpen ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Open / unresolved</span></div>
      <div><strong style="font-size:1.5rem;color:var(--green)"><?= $discTotal - $discOpen ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Resolved</span></div>
    </div>
    <h4 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:10px">By Category</h4>
    <?php foreach ($discStats as $d): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= e($d['category']) ?></span>
      <span><strong><?= $d['cnt'] ?></strong> <span style="color:var(--green);font-size:11px">(<?= $d['resolved'] ?> resolved)</span></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:12px">
      <a href="?export=csv&type=discipline" class="button button-secondary button-sm">📥 Export CSV</a>
    </div>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:12px">⚖️ Incidents by Grade</h3>
    <?php foreach ($discByGrade as $g): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= e($g['grade_name']) ?></span><strong><?= $g['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if (empty($discByGrade)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No incidents recorded for <?= e($ay) ?>.</p>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'admin_rep'): ?>
<!-- ═══════════════ ADMINISTRATIVE REPORTS ═══════════════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📊</div>
    <h3 style="font-weight:700;margin-bottom:6px">Enrollment Summary</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Student enrollment status summary for <?= e($ay) ?>.</p>
    <?php
    $enrolStats=$pdo->prepare("SELECT status,COUNT(*) cnt FROM students WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"); $enrolStats->execute([$ayId]); $enrolStats=$enrolStats->fetchAll();
    ?>
    <div style="margin-bottom:12px">
      <?php foreach ($enrolStats as $es): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--line-soft)"><?=e($es['status'])?><strong><?=$es['cnt']?></strong></div>
      <?php endforeach; if(empty($enrolStats)):?><p style="font-size:13px;color:var(--ink-faint)">No data for <?=e($ay)?>.</p><?php endif;?>
    </div>
    <a href="?export=csv&type=students" class="button button-secondary button-sm">📥 Full Register CSV</a>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">🏢</div>
    <h3 style="font-weight:700;margin-bottom:6px">Departments Report</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">School departments and teacher allocations.</p>
    <?php
    try {
        $depts = $pdo->query("SELECT d.name,(SELECT COUNT(*) FROM teachers t WHERE t.department_id=d.id) teachers FROM departments d ORDER BY d.name")->fetchAll();
    } catch(Throwable $e) { $depts=[]; }
    ?>
    <?php if (!empty($depts)): ?>
    <div style="margin-bottom:12px">
      <?php foreach ($depts as $d): ?>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
        <span><?= e($d['name']) ?></span><strong><?= $d['teachers'] ?> teachers</strong>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="font-size:13px;color:var(--ink-faint);margin-bottom:12px">No departments configured.</p>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/departments.php" class="button button-secondary button-sm">Manage Departments</a>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📢</div>
    <h3 style="font-weight:700;margin-bottom:6px">Announcements Summary</h3>
    <?php
    $annStats = $pdo->query("SELECT target,COUNT(*) cnt FROM announcements WHERE published_at IS NOT NULL GROUP BY target ORDER BY cnt DESC")->fetchAll();
    $annTotal = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
    $annActive= (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE (expires_at IS NULL OR expires_at>NOW())")->fetchColumn();
    ?>
    <div style="display:flex;gap:16px;margin-bottom:12px">
      <div><strong style="font-size:1.3rem;color:var(--primary)"><?= $annTotal ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Total</span></div>
      <div><strong style="font-size:1.3rem;color:var(--green)"><?= $annActive ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Active</span></div>
    </div>
    <?php foreach ($annStats as $a): ?>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0;border-bottom:1px solid var(--line-soft)">
      <span>Target: <?= e(ucfirst($a['target'])) ?></span><strong><?= $a['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="panel" style="padding:22px">
    <div style="font-size:2rem;margin-bottom:10px">📅</div>
    <h3 style="font-weight:700;margin-bottom:6px">Academic Year Overview</h3>
    <?php
    $ayList = $pdo->query("SELECT ay.*,(SELECT COUNT(*) FROM students s WHERE s.academic_year_id=ay.id) students FROM academic_years ay ORDER BY ay.start_date DESC LIMIT 5")->fetchAll();
    ?>
    <?php foreach ($ayList as $year): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <div>
        <strong><?= e($year['name']) ?></strong>
        <?= $year['is_current'] ? '<span class="status approved" style="font-size:10px;margin-left:4px">Current</span>' : '' ?>
        <br><span style="font-size:11px;color:var(--ink-soft)"><?= e(ucfirst($year['status'])) ?></span>
      </div>
      <strong><?= number_format($year['students']) ?> students</strong>
    </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/admin/academic_years.php" class="button button-secondary button-sm" style="margin-top:12px">Manage Years</a>
  </div>

</div>
<?php endif; ?>

<style>@media(max-width:640px){.rep-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
