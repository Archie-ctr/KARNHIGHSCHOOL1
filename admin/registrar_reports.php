<?php
$pageTitle   = 'Registrar Reports';
$activeAdmin = 'registrar_reports';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'enrollment';

// ── CSV exports ───────────────────────────────────────────────
$export = trim($_GET['export'] ?? '');
$type   = trim($_GET['type']   ?? '');
if ($export === 'csv' && $type) {
    $filename = $type.'_'.date('Y-m-d').'.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $fp = fopen('php://output','w');
    switch ($type) {
        case 'enrollment':
            fputcsv($fp,['Student ID','Admission #','First Name','Last Name','Gender','DOB','County','Grade','Class','Status','Admission Date','Previous School']);
            $rows=$pdo->prepare("SELECT s.student_id,s.admission_number,s.first_name,s.last_name,s.gender,s.date_of_birth,s.county,g.name,c.name,s.status,s.admission_date,s.previous_school FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.academic_year_id=? ORDER BY s.last_name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'demographics':
            fputcsv($fp,['Student ID','First Name','Last Name','Gender','Date of Birth','County','District','Community','Nationality','Phone','Email']);
            $rows=$pdo->prepare("SELECT s.student_id,s.first_name,s.last_name,s.gender,s.date_of_birth,s.county,s.district,s.community,s.nationality,s.phone,s.email FROM students s WHERE s.academic_year_id=? AND s.status='Active' ORDER BY s.last_name");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'admissions':
            fputcsv($fp,['App #','First Name','Last Name','DOB','Gender','County','Grade','Phone','Guardian','G.Phone','Status','Academic Year','Submitted','Entrance Date']);
            $rows=$pdo->prepare("SELECT a.application_number,a.first_name,a.last_name,a.date_of_birth,a.gender,a.county,a.grade_applying_for,a.phone,a.guardian_name,a.guardian_phone,a.status,ay.name,a.created_at,a.entrance_exam_date FROM applications a LEFT JOIN academic_years ay ON ay.id=a.academic_year_id WHERE a.academic_year_id=? ORDER BY a.created_at DESC");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'transfers':
            fputcsv($fp,['Student','Student ID','Type','Effective Date','Destination','Reason','Status','Academic Year']);
            try {
                $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,sm.movement_type,sm.effective_date,sm.destination_school,sm.reason,sm.status,ay.name FROM student_movements sm JOIN students s ON s.id=sm.student_id JOIN academic_years ay ON ay.id=sm.academic_year_id WHERE sm.academic_year_id=? ORDER BY sm.effective_date DESC");
                $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r));
            } catch(Throwable $e){ fputcsv($fp,['No data']); } break;
        case 'graduation':
            fputcsv($fp,['Student','Student ID','Status','Overall Avg','Certificate #','Graduation Date','Academic Year']);
            try {
                $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,gr.status,gr.overall_average,gr.certificate_number,gr.graduation_date,ay.name FROM graduation_records gr JOIN students s ON s.id=gr.student_id JOIN academic_years ay ON ay.id=gr.academic_year_id WHERE gr.academic_year_id=? ORDER BY s.last_name");
                $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r));
            } catch(Throwable $e){ fputcsv($fp,['No data']); } break;
        case 'promotion':
            fputcsv($fp,['Student','Student ID','From Grade','To Grade','Status','Academic Year','Processed Date']);
            try {
                $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,gf.name,gt.name,pr.status,ay.name,pr.processed_at FROM promotion_records pr JOIN students s ON s.id=pr.student_id LEFT JOIN grades gf ON gf.id=pr.from_grade_id LEFT JOIN grades gt ON gt.id=pr.to_grade_id JOIN academic_years ay ON ay.id=pr.academic_year_id WHERE pr.academic_year_id=? ORDER BY s.last_name");
                $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r));
            } catch(Throwable $e){ fputcsv($fp,['No data']); } break;
    }
    fclose($fp); exit;
}

// ── Data ──────────────────────────────────────────────────────

// Enrollment summary
$totalActive    = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalEnrolled  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId")->fetchColumn();
$newAdmissions  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId AND YEAR(admission_date)=YEAR(CURDATE())")->fetchColumn();

// Enrollment by grade
$byGrade = $pdo->prepare(
    "SELECT g.name grade_name, g.sequence,
            COUNT(s.id) total,
            SUM(s.gender='Male') males,
            SUM(s.gender='Female') females,
            SUM(s.status='Active') active
     FROM students s JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=?
     GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence"
);
$byGrade->execute([$ayId]); $byGrade = $byGrade->fetchAll();

// Enrollment by status
$byStatus = $pdo->prepare(
    "SELECT status, COUNT(*) cnt FROM students WHERE academic_year_id=?
     GROUP BY status ORDER BY cnt DESC"
);
$byStatus->execute([$ayId]); $byStatus = $byStatus->fetchAll();

// Gender breakdown
$genderStats = $pdo->prepare(
    "SELECT gender, COUNT(*) cnt FROM students WHERE academic_year_id=? AND status='Active'
     GROUP BY gender ORDER BY cnt DESC"
);
$genderStats->execute([$ayId]); $genderStats = $genderStats->fetchAll();

// County breakdown (top 10)
$countyStats = $pdo->prepare(
    "SELECT COALESCE(NULLIF(county,''),'Unknown') county, COUNT(*) cnt
     FROM students WHERE academic_year_id=? AND status='Active'
     GROUP BY county ORDER BY cnt DESC LIMIT 10"
);
$countyStats->execute([$ayId]); $countyStats = $countyStats->fetchAll();

// Admissions pipeline
$appPipeline = $pdo->prepare(
    "SELECT status, COUNT(*) cnt FROM applications WHERE academic_year_id=?
     GROUP BY status ORDER BY cnt DESC"
);
$appPipeline->execute([$ayId]); $appPipeline = $appPipeline->fetchAll();

// Admissions history (all years)
$admHistory = $pdo->query(
    "SELECT ay.name ay_name,
            COUNT(a.id) total,
            SUM(a.status='Admitted') admitted,
            SUM(a.status='Rejected') rejected
     FROM academic_years ay
     LEFT JOIN applications a ON a.academic_year_id=ay.id
     GROUP BY ay.id,ay.name ORDER BY ay.start_date DESC LIMIT 5"
)->fetchAll();

// Transfers & withdrawals
try {
    $movStats = $pdo->prepare(
        "SELECT movement_type, status, COUNT(*) cnt
         FROM student_movements WHERE academic_year_id=?
         GROUP BY movement_type,status ORDER BY movement_type,cnt DESC"
    );
    $movStats->execute([$ayId]); $movStats = $movStats->fetchAll();
    $movByType = [];
    foreach ($movStats as $m) $movByType[$m['movement_type']][$m['status']] = $m['cnt'];
} catch (Throwable $e) { $movByType = []; }

// Graduation stats
try {
    $gradStats = $pdo->prepare(
        "SELECT status, COUNT(*) cnt FROM graduation_records WHERE academic_year_id=?
         GROUP BY status ORDER BY cnt DESC"
    );
    $gradStats->execute([$ayId]); $gradStats = $gradStats->fetchAll(PDO::FETCH_KEY_PAIR);
    $gradTotal = array_sum($gradStats);
} catch (Throwable $e) { $gradStats = []; $gradTotal = 0; }

// Promotion stats
try {
    $promoStats = $pdo->prepare(
        "SELECT status, COUNT(*) cnt FROM promotion_records WHERE academic_year_id=?
         GROUP BY status ORDER BY cnt DESC"
    );
    $promoStats->execute([$ayId]); $promoStats = $promoStats->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $promoStats = []; }

$maxGrade = max(array_column($byGrade,'total') ?: [1]);
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Registrar <span></span></div>
    <h1>Registrar Reports</h1>
    <p><?= e($ay) ?></p>
  </div>
</div>

<!-- Quick metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div><strong><?= number_format($totalActive) ?></strong><small><i></i><?= e($ay) ?></small></div>
  <div class="metric-card"><div class="metric-top"><span>New Admissions</span><div class="metric-icon">🆕</div></div><strong><?= $newAdmissions ?></strong><small><i></i>This year</small></div>
  <div class="metric-card"><div class="metric-top"><span>Graduated</span><div class="metric-icon">🎓</div></div><strong><?= $gradStats['graduated'] ?? 0 ?></strong><small><i></i><?= $gradTotal ?> on list</small></div>
  <div class="metric-card"><div class="metric-top"><span>Promoted</span><div class="metric-icon">⬆️</div></div><strong><?= $promoStats['Promoted'] ?? 0 ?></strong><small><i></i><?= $promoStats['Repeating']??0 ?> repeating</small></div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:20px">
  <a href="?tab=enrollment"   class="tab-btn <?= $tab==='enrollment'  ?'active':'' ?>">🎓 Enrollment</a>
  <a href="?tab=demographics" class="tab-btn <?= $tab==='demographics'?'active':'' ?>">👫 Demographics</a>
  <a href="?tab=admissions"   class="tab-btn <?= $tab==='admissions'  ?'active':'' ?>">📋 Admissions</a>
  <a href="?tab=movements"    class="tab-btn <?= $tab==='movements'   ?'active':'' ?>">🔄 Transfers</a>
  <a href="?tab=graduation"   class="tab-btn <?= $tab==='graduation'  ?'active':'' ?>">🎓 Graduation</a>
  <a href="?tab=promotion"    class="tab-btn <?= $tab==='promotion'   ?'active':'' ?>">⬆️ Promotion</a>
</div>

<?php if ($tab === 'enrollment'): ?>
<!-- ── ENROLLMENT ─────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=enrollment" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="reg-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📚 Enrollment by Grade</h3>
    <?php foreach ($byGrade as $g):
      $w = round($g['total'] / $maxGrade * 100);
    ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?= e($g['grade_name']) ?></span>
        <span><strong><?= $g['active'] ?></strong> active / <?= $g['total'] ?> total
          <span style="color:var(--ink-soft);margin-left:6px"><?= $g['males']??0 ?>M · <?= $g['females']??0 ?>F</span>
        </span>
      </div>
      <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--primary);border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($byGrade)):?><p style="color:var(--ink-faint);font-size:13px">No data.</p><?php endif; ?>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📊 Enrollment by Status</h3>
    <?php foreach ($byStatus as $s): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= e($s['status']) ?></span><strong><?= $s['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($byStatus)):?><p style="color:var(--ink-faint);font-size:13px">No data.</p><?php endif; ?>
    <div class="divider" style="margin:var(--sp5) 0"></div>
    <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700">
      <span>Total Enrolled</span><span><?= $totalEnrolled ?></span>
    </div>
  </div>
</div>

<?php elseif ($tab === 'demographics'): ?>
<!-- ── DEMOGRAPHICS ────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=demographics" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="reg-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">👫 Gender Breakdown (Active)</h3>
    <?php $maxG = max(array_column($genderStats,'cnt') ?: [1]); foreach ($genderStats as $g): $w=round($g['cnt']/$maxG*100); ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?= e($g['gender'] ?: 'Not specified') ?></span>
        <strong><?= $g['cnt'] ?> (<?= $totalActive>0?round($g['cnt']/$totalActive*100,1):0 ?>%)</strong>
      </div>
      <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:<?= $g['gender']==='Male'?'#3b5bdb':'#e64980' ?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📍 Students by County (Top 10)</h3>
    <?php $maxC = max(array_column($countyStats,'cnt') ?: [1]); foreach ($countyStats as $c): $w=round($c['cnt']/$maxC*100); ?>
    <div style="margin-bottom:7px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
        <span><?= e($c['county']) ?></span><strong><?= $c['cnt'] ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--primary);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'admissions'): ?>
<!-- ── ADMISSIONS ──────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=admissions" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="reg-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Current Year Pipeline — <?= e($ay) ?></h3>
    <?php foreach ($appPipeline as $a): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= e($a['status']) ?></span><strong><?= $a['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($appPipeline)):?><p style="color:var(--ink-faint);font-size:13px">No applications this year.</p><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/applications.php" class="lnk" style="font-size:12px;margin-top:12px">Manage applications →</a>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📅 Admission History (Last 5 Years)</h3>
    <div class="table-wrap" style="border:none">
      <table>
        <thead><tr><th>Year</th><th>Total</th><th>Admitted</th><th>Rejected</th></tr></thead>
        <tbody>
          <?php foreach ($admHistory as $h): ?>
          <tr>
            <td><strong><?= e($h['ay_name']) ?></strong></td>
            <td><?= $h['total'] ?></td>
            <td style="color:var(--green)"><?= $h['admitted'] ?></td>
            <td style="color:var(--error)"><?= $h['rejected'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php elseif ($tab === 'movements'): ?>
<!-- ── TRANSFERS & WITHDRAWALS ─────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=transfers" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<?php if (empty($movByType)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🔄</div>
  <p style="color:var(--ink-soft)">No transfer or withdrawal records for <?= e($ay) ?>.</p>
  <a href="<?= BASE_URL ?>/admin/student_transfers.php" class="button button-secondary" style="margin-top:14px">Manage Movements</a>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
  <?php
  $typeLabels = ['transfer_out'=>['➡️','Transfer Out','var(--primary)'],'transfer_in'=>['⬅️','Transfer In','var(--green)'],'withdrawal'=>['❌','Withdrawal','var(--error)'],'re_enrollment'=>['🔄','Re-enrollment','var(--warning)']];
  foreach ($typeLabels as $type => [$ico,$label,$color]):
    if (!isset($movByType[$type])) continue;
  ?>
  <div class="panel" style="padding:20px;border-top:3px solid <?= $color ?>">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <span style="font-size:1.5rem"><?= $ico ?></span>
      <h3 style="font-weight:700;font-size:14px"><?= $label ?></h3>
    </div>
    <?php foreach ($movByType[$type] as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)">
      <span><?= ucfirst($status) ?></span><strong><?= $cnt ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
<div style="margin-top:14px">
  <a href="<?= BASE_URL ?>/admin/student_transfers.php" class="button button-secondary button-sm">Manage Movements →</a>
</div>
<?php endif; ?>

<?php elseif ($tab === 'graduation'): ?>
<!-- ── GRADUATION ──────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=graduation" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<?php if (empty($gradStats)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🎓</div>
  <p style="color:var(--ink-soft)">No graduation records for <?= e($ay) ?>.</p>
  <a href="<?= BASE_URL ?>/admin/graduation.php" class="button button-secondary" style="margin-top:14px">Manage Graduation</a>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="reg-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">🎓 Graduation Status — <?= e($ay) ?></h3>
    <?php
    $statusColors = ['eligible'=>'new-s','approved'=>'approved','graduated'=>'approved','withheld'=>'warning'];
    foreach ($gradStats as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span class="status <?= $statusColors[$status]??'new-s' ?>" style="font-size:11px"><?= ucfirst($status) ?></span>
      <strong style="font-size:1.2rem"><?= $cnt ?></strong>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:14px;padding-top:10px;border-top:1.5px solid var(--line);display:flex;justify-content:space-between;font-size:13px;font-weight:700">
      <span>Total</span><span><?= $gradTotal ?></span>
    </div>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">🏅 Certified Graduates</h3>
    <?php
    try {
        $certified = $pdo->prepare(
            "SELECT CONCAT(s.first_name,' ',s.last_name) name, s.student_id, gr.certificate_number, gr.overall_average
             FROM graduation_records gr JOIN students s ON s.id=gr.student_id
             WHERE gr.academic_year_id=? AND gr.status IN ('approved','graduated')
             ORDER BY s.last_name LIMIT 15"
        );
        $certified->execute([$ayId]); $certified = $certified->fetchAll();
    } catch (Throwable $e) { $certified = []; }
    ?>
    <?php if (empty($certified)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No approved graduates yet.</p>
    <?php else: foreach ($certified as $c): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <div>
        <strong><?= e($c['name']) ?></strong>
        <div style="font-size:11px;color:var(--ink-faint)"><?= e($c['student_id']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--ink-soft)"><?= e($c['certificate_number']) ?></div>
        <?php if($c['overall_average']):?><div style="font-size:11px;color:var(--green)"><?= $c['overall_average'] ?>%</div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
    <a href="<?= BASE_URL ?>/admin/graduation.php" class="lnk" style="font-size:12px;margin-top:10px">Manage Graduation →</a>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'promotion'): ?>
<!-- ── PROMOTION ───────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=promotion" class="button button-secondary button-sm">📥 Export CSV</a>
</div>
<?php if (empty($promoStats)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">⬆️</div>
  <p style="color:var(--ink-soft)">No promotion records for <?= e($ay) ?>.</p>
  <a href="<?= BASE_URL ?>/admin/promotion.php" class="button button-secondary" style="margin-top:14px">Manage Promotion</a>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="reg-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">⬆️ Promotion Summary — <?= e($ay) ?></h3>
    <?php
    $promoColors=['Promoted'=>'approved','Repeating'=>'pending','Graduated'=>'approved','Transferred'=>'new-s','Withdrawn'=>'warning','Not Promoted'=>'warning'];
    foreach ($promoStats as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span class="status <?= $promoColors[$status]??'new-s' ?>" style="font-size:11px"><?= e($status) ?></span>
      <strong style="font-size:1.2rem"><?= $cnt ?></strong>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:14px;padding-top:10px;border-top:1.5px solid var(--line);display:flex;justify-content:space-between;font-size:13px;font-weight:700">
      <span>Total</span><span><?= array_sum($promoStats) ?></span>
    </div>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Promotion by Grade</h3>
    <?php
    try {
        $promoByGrade = $pdo->prepare(
            "SELECT g.name grade_name, pr.status, COUNT(*) cnt
             FROM promotion_records pr
             JOIN grades g ON g.id=pr.from_grade_id
             WHERE pr.academic_year_id=?
             GROUP BY g.id,g.name,pr.status ORDER BY g.sequence,pr.status"
        );
        $promoByGrade->execute([$ayId]); $promoByGrade = $promoByGrade->fetchAll();
        $promoGradeMap = [];
        foreach ($promoByGrade as $r) $promoGradeMap[$r['grade_name']][$r['status']] = $r['cnt'];
    } catch (Throwable $e) { $promoGradeMap = []; }
    ?>
    <?php foreach ($promoGradeMap as $grade => $statuses): ?>
    <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid var(--line-soft)">
      <strong style="font-size:12.5px"><?= e($grade) ?></strong>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
        <?php foreach ($statuses as $s => $c): ?>
        <span style="font-size:11px;background:var(--bg2);padding:2px 8px;border-radius:10px"><?= e($s) ?>: <strong><?= $c ?></strong></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($promoGradeMap)):?><p style="color:var(--ink-faint);font-size:13px">No grade breakdown available.</p><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/promotion.php" class="lnk" style="font-size:12px;margin-top:8px">Manage Promotion →</a>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>@media(max-width:640px){.reg-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
