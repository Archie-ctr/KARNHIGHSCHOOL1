<?php
$pageTitle='Search'; $activeAdmin='';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db(); $q=trim($_GET['q']??''); $ayId=currentAcademicYearId();
$students=[]; $applications=[]; $teachers=[]; $payments=[];
if(strlen($q)>=2){
    $like='%'.$q.'%';
    $st=$pdo->prepare("SELECT id,student_id,first_name,last_name,current_grade_id,status FROM students WHERE first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ? OR phone LIKE ? OR admission_number LIKE ? ORDER BY first_name LIMIT 10"); $st->execute([$like,$like,$like,$like,$like]); $students=$st->fetchAll();
    $ap=$pdo->prepare("SELECT id,application_number,first_name,last_name,grade_applying_for,status FROM applications WHERE application_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 10"); $ap->execute([$like,$like,$like,$like]); $applications=$ap->fetchAll();
    $tc=$pdo->prepare("SELECT id,teacher_id,first_name,last_name,specialization FROM teachers WHERE first_name LIKE ? OR last_name LIKE ? OR teacher_id LIKE ? ORDER BY first_name LIMIT 10"); $tc->execute([$like,$like,$like]); $teachers=$tc->fetchAll();
    $py=$pdo->prepare("SELECT p.*,CONCAT(s.first_name,' ',s.last_name) sname FROM payments p JOIN students s ON s.id=p.student_id WHERE p.receipt_number LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ? ORDER BY p.created_at DESC LIMIT 10"); $py->execute([$like,$like]); $payments=$py->fetchAll();
}
$total=count($students)+count($applications)+count($teachers)+count($payments);
?>
<div class="page-heading"><div><div class="eyebrow">Global Search <span></span></div><h1>Search Results</h1></div></div>
<form method="get" class="filter-row" style="margin-bottom:24px"><div class="table-search" style="max-width:500px;flex:1">🔍<input type="search" name="q" placeholder="Search students, applications, teachers, receipts…" value="<?=e($q)?>" autofocus style="flex:1"/></div><button class="button button-primary">Search</button></form>
<?php if($q&&$total===0):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)"><div style="font-size:36px;margin-bottom:12px">🔍</div><p style="color:var(--ink-soft)">No results found for <strong>"<?=e($q)?>"</strong>.</p></div>
<?php elseif($q):?>
<p style="color:var(--ink-soft);margin-bottom:20px"><?=$total?> result<?=$total!=1?'s':''?> for <strong>"<?=e($q)?>"</strong></p>
<?php if(!empty($students)):?>
<h3 style="font-size:15px;font-weight:700;margin-bottom:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">Students (<?=count($students)?>)</h3>
<div class="table-wrap" style="margin-bottom:20px"><table><thead><tr><th>Name</th><th>Student ID</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($students as $s):?><tr><td><strong><?=e($s['first_name'].' '.$s['last_name'])?></strong></td><td class="muted"><?=e($s['student_id'])?></td><td><?=statusBadge($s['status'])?></td><td><a href="<?=BASE_URL?>/admin/students.php?q=<?=urlencode($s['student_id'])?>" class="filter-button button-sm">View →</a></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
<?php if(!empty($applications)):?>
<h3 style="font-size:15px;font-weight:700;margin-bottom:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">Applications (<?=count($applications)?>)</h3>
<div class="table-wrap" style="margin-bottom:20px"><table><thead><tr><th>Applicant</th><th>App #</th><th>Grade</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($applications as $a):?><tr><td><strong><?=e($a['first_name'].' '.$a['last_name'])?></strong></td><td class="muted"><?=e($a['application_number'])?></td><td><?=e($a['grade_applying_for'])?></td><td><?=statusBadge($a['status'])?></td><td><a href="<?=BASE_URL?>/admin/applications.php?q=<?=urlencode($a['application_number'])?>" class="filter-button button-sm">View →</a></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
<?php if(!empty($teachers)):?>
<h3 style="font-size:15px;font-weight:700;margin-bottom:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">Teachers (<?=count($teachers)?>)</h3>
<div class="table-wrap" style="margin-bottom:20px"><table><thead><tr><th>Name</th><th>Teacher ID</th><th>Specialization</th></tr></thead><tbody>
  <?php foreach($teachers as $t):?><tr><td><strong><?=e($t['first_name'].' '.$t['last_name'])?></strong></td><td class="muted"><?=e($t['teacher_id'])?></td><td><?=e($t['specialization']??'—')?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
<?php if(!empty($payments)):?>
<h3 style="font-size:15px;font-weight:700;margin-bottom:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">Payments (<?=count($payments)?>)</h3>
<div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Date</th><th></th></tr></thead><tbody>
  <?php foreach($payments as $p):?><tr><td class="muted"><?=e($p['receipt_number'])?></td><td><strong><?=e($p['sname'])?></strong></td><td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td><td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td><td><a href="<?=BASE_URL?>/letters/receipt_pdf.php?payment_id=<?=$p['id']?>" class="filter-button button-sm" target="_blank">📄 Receipt</a></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
<?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
