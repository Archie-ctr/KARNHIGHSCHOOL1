<?php
$pageTitle='Reports'; $activeAdmin='reports';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

// Export CSV if requested
$export=trim($_GET['export']??''); $type=trim($_GET['type']??'');
if($export==='csv'&&$type){
    $filename=$type.'_'.date('Y-m-d').'.csv'; header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$filename.'"');
    $fp=fopen('php://output','w');
    if($type==='students'){
        fputcsv($fp,['Student ID','Admission #','First Name','Last Name','Gender','Grade','Class','Status','Admission Date','Phone']);
        $rows=$pdo->prepare("SELECT s.student_id,s.admission_number,s.first_name,s.last_name,s.gender,g.name grade_name,c.name class_name,s.status,s.admission_date,s.phone FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.academic_year_id=? ORDER BY s.last_name");
        $rows->execute([$ayId]); $rows=$rows->fetchAll();
        foreach($rows as $r) fputcsv($fp,[$r['student_id'],$r['admission_number'],$r['first_name'],$r['last_name'],$r['gender'],$r['grade_name'],$r['class_name'],$r['status'],$r['admission_date'],$r['phone']]);
    } elseif($type==='payments'){
        fputcsv($fp,['Receipt','Student','Student ID','Amount','Currency','Method','Date','Academic Year']);
        $rows=$pdo->prepare("SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id,p.amount,p.currency,p.payment_method,p.payment_date,ay.name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN academic_years ay ON ay.id=p.academic_year_id WHERE p.academic_year_id=? ORDER BY p.payment_date DESC");
        $rows->execute([$ayId]); $rows=$rows->fetchAll();
        foreach($rows as $r) fputcsv($fp,[$r['receipt_number'],$r['sname'],$r['student_id'],$r['amount'],$r['currency'],$r['payment_method'],$r['payment_date'],$r['name']]);
    } elseif($type==='applications'){
        fputcsv($fp,['App #','First Name','Last Name','Grade','Phone','Guardian','Status','Academic Year','Submitted']);
        $rows=$pdo->prepare("SELECT a.application_number,a.first_name,a.last_name,a.grade_applying_for,a.phone,a.guardian_name,a.status,ay.name ay_name,a.created_at FROM applications a LEFT JOIN academic_years ay ON ay.id=a.academic_year_id WHERE a.academic_year_id=? ORDER BY a.created_at DESC");
        $rows->execute([$ayId]); $rows=$rows->fetchAll();
        foreach($rows as $r) fputcsv($fp,[$r['application_number'],$r['first_name'],$r['last_name'],$r['grade_applying_for'],$r['phone'],$r['guardian_name'],$r['status'],$r['ay_name'],$r['created_at']]);
    } elseif($type==='attendance'){
        fputcsv($fp,['Student','Student ID','Grade','Date','Status']);
        $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name) sname,s.student_id,g.name grade_name,a.date,a.status FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE a.academic_year_id=? ORDER BY a.date DESC,s.last_name"); $rows->execute([$ayId]); $rows=$rows->fetchAll();
        foreach($rows as $r) fputcsv($fp,array_values($r));
    }
    fclose($fp); exit;
}
?>
<div class="page-heading"><div><div class="eyebrow">Reports <span></span></div><h1>Reports &amp; Exports</h1><p><?=e($ay)?></p></div></div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
  <?php
  $reports=[
    ['📋','Applications Report','All applications and their statuses','applications'],
    ['🎓','Student Register','All active student records','students'],
    ['💰','Payments Report','Fee collection history','payments'],
    ['📆','Attendance Report','Daily attendance records','attendance'],
  ];
  foreach($reports as [$icon,$title,$desc,$key]):
  ?>
  <div class="panel" style="padding:24px">
    <div style="font-size:32px;margin-bottom:12px"><?=$icon?></div>
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px"><?=$title?></h3>
    <p style="font-size:13.5px;color:var(--ink-soft);margin-bottom:16px"><?=$desc?></p>
    <a href="?export=csv&type=<?=$key?>" class="button button-secondary button-sm">📥 Export CSV</a>
  </div>
  <?php endforeach;?>

  <!-- Finance summary -->
  <div class="panel" style="padding:24px">
    <div style="font-size:32px;margin-bottom:12px">📊</div>
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">Finance Summary</h3>
    <p style="font-size:13.5px;color:var(--ink-soft);margin-bottom:12px">Current year collection overview.</p>
    <?php
    $fLRD=$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $fUSD=$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
    ?>
    <div style="font-size:14px;margin-bottom:6px"><strong>LRD Collected:</strong> LRD <?=number_format($fLRD)?></div>
    <div style="font-size:14px"><strong>USD Collected:</strong> USD <?=number_format($fUSD,2)?></div>
  </div>

  <!-- Student summary -->
  <div class="panel" style="padding:24px">
    <div style="font-size:32px;margin-bottom:12px">🎓</div>
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">Enrolment Summary</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:10px"><?= e($ay) ?></p>
    <?php
    $enrolStats=$pdo->prepare("SELECT status,COUNT(*) cnt FROM students WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC");
    $enrolStats->execute([$ayId]); $enrolStats=$enrolStats->fetchAll();
    ?>
    <?php foreach($enrolStats as $es):?>
    <div style="display:flex;justify-content:space-between;font-size:13.5px;padding:4px 0;border-bottom:1px solid var(--line-soft)"><?=e($es['status'])?><strong><?=$es['cnt']?></strong></div>
    <?php endforeach;?>
    <?php if(empty($enrolStats)):?><p style="font-size:13px;color:var(--ink-faint)">No enrolment data for <?=e($ay)?>.</p><?php endif;?>
  </div>
</div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
