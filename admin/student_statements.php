<?php
$pageTitle='Student Statements'; $activeAdmin='finance';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['accountant','principal','super_admin']);
$pdo=db(); $ayId=currentAcademicYearId();

$q=trim($_GET['q']??''); $stdId=(int)($_GET['student_id']??0);
$students=$pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

$student=null; $payments=[]; $feeRows=[]; $totalDue=0; $totalPaid=0;
if($stdId){
    $student=$pdo->query("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.id=$stdId")->fetch();
    if($student){
        $feeRows=$pdo->prepare("SELECT * FROM fee_structures WHERE academic_year_id=? AND (grade_id IS NULL OR grade_id=?) AND is_active=1 ORDER BY fee_type")->execute([$ayId,$student['current_grade_id']])?$pdo->query("SELECT * FROM fee_structures WHERE academic_year_id=$ayId AND (grade_id IS NULL OR grade_id={$student['current_grade_id']}) AND is_active=1")->fetchAll():[];
        $totalDue=array_sum(array_column(array_filter($feeRows,fn($f)=>$f['currency']==='LRD'),'amount'));
        $payments=$pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY payment_date DESC")->execute([$stdId])?$pdo->query("SELECT * FROM payments WHERE student_id=$stdId ORDER BY payment_date DESC")->fetchAll():[];
        $totalPaid=array_sum(array_column(array_filter($payments,fn($p)=>$p['currency']==='LRD'),'amount'));
    }
}
?>
<div class="page-heading"><div><a href="<?=BASE_URL?>/admin/finance.php" class="text-link" style="margin-bottom:8px;display:flex">← Finance</a><div class="eyebrow">Finance <span></span></div><h1>Student Statements</h1></div></div>
<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="student_id" class="filter-button" onchange="this.form.submit()" style="min-width:220px">
    <option value="">Select student…</option>
    <?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=$stdId==$s['id']?'selected':''?>><?=e($s['name'].' ('.$s['student_id'].')')?></option><?php endforeach;?>
  </select>
  <?php if($student):?>
  <a href="<?=BASE_URL?>/letters/receipt_pdf.php?student_id=<?=$stdId?>" class="button button-secondary button-sm" target="_blank">📄 Print Statement</a>
  <?php endif;?>
</form>
<?php if($student):?>
<div class="form-section">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div><h3><?=e($student['first_name'].' '.$student['last_name'])?></h3><p style="font-size:13px;color:var(--ink-soft)"><?=e($student['student_id'])?> &middot; <?=e($student['grade_name']??'')?></p></div>
    <div style="text-align:right"><div style="font-size:13px;color:var(--ink-soft)">Balance</div><div style="font-size:22px;font-weight:800;color:<?=$totalDue-$totalPaid>0?'var(--error)':'var(--green)'?>">LRD <?=number_format(abs($totalDue-$totalPaid))?></div><div style="font-size:11px;color:var(--ink-faint)"><?=$totalDue-$totalPaid>0?'Outstanding':'Paid in full'?></div></div>
  </div>
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
    <div class="metric-card"><div class="metric-top"><span>Total Due (LRD)</span></div><strong>LRD <?=number_format($totalDue)?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Total Paid (LRD)</span></div><strong style="color:var(--green)">LRD <?=number_format($totalPaid)?></strong></div>
    <div class="metric-card finance-metrics"><div class="metric-top"><span>Balance</span></div><strong style="color:<?=$totalDue-$totalPaid>0?'var(--error)':'var(--green)'?>">LRD <?=number_format(abs($totalDue-$totalPaid))?></strong></div>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($payments)):?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--ink-faint)">No payments recorded.</td></tr>
      <?php else: foreach($payments as $p):?>
      <tr><td class="muted"><?=e($p['receipt_number'])?></td><td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td><td><?=e($p['payment_method'])?></td><td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td><td><a href="<?=BASE_URL?>/letters/receipt_pdf.php?payment_id=<?=$p['id']?>" class="filter-button button-sm" target="_blank">Receipt</a></td></tr>
      <?php endforeach; endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
