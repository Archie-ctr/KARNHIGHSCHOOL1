<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
$pdo=db(); $id=(int)($_GET['payment_id']??0);
if(!$id) die('Invalid receipt.');
$pay=$pdo->query("SELECT p.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,g.name grade_name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id WHERE p.id=$id")->fetch();
if(!$pay) die('Receipt not found.');
// Students can only see their own receipt
if(isStudent()){
    $myStd=$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id'])->fetchColumn();
    if($myStd!=$pay['student_id']) die('Access denied.');
}
$school=setting('school_name','KARN HIGH SCHOOL');
$feeType=$pdo->query("SELECT fee_type FROM fee_structures WHERE id=".($pay['fee_structure_id']??0))->fetchColumn();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Receipt <?=e($pay['receipt_number'])?></title>
<style>*{box-sizing:border-box;margin:0;padding:0} body{font-family:'Arial',sans-serif;background:#f5f5f5;padding:20px} .receipt{background:#fff;max-width:600px;margin:0 auto;padding:32px 40px;box-shadow:0 2px 8px rgba(0,0,0,.1)} .rh{display:flex;align-items:center;gap:16px;border-bottom:2px solid #ac2443;padding-bottom:14px;margin-bottom:16px} .rh img{width:56px;height:56px;border-radius:10px} .sname{font-size:17px;font-weight:700;color:#ac2443} .ssub{font-size:12px;color:#666} .rtitle{text-align:center;font-size:16px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#ac2443;margin:12px 0 20px;padding:8px;border:2px solid #ac2443;border-radius:6px} .rno{display:flex;justify-content:space-between;font-size:13px;margin-bottom:18px;background:#f7e8ec;padding:10px 14px;border-radius:6px} table{width:100%;border-collapse:collapse;margin-bottom:20px} td{padding:10px 14px;font-size:13.5px;border-bottom:1px solid #f0f0f0} td:first-child{color:#666;font-weight:600;width:160px} td:last-child{font-weight:700} .total-row td{background:#1a1a1f;color:#fff;font-size:16px;padding:12px 14px} .stamp{text-align:center;margin-top:20px;padding:14px;border:2px dashed #ac2443;border-radius:8px;color:#ac2443;font-size:12px;font-weight:700;letter-spacing:.08em} .footer{text-align:center;font-size:11px;color:#999;margin-top:16px} @media print{body{background:#fff;padding:0} .receipt{box-shadow:none} .no-print{display:none}}</style>
</head><body>
<div class="no-print" style="max-width:600px;margin:0 auto 12px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print Receipt</button>
  <a href="javascript:history.back()" style="padding:8px 18px;border:1px solid #ddd;border-radius:6px;font-size:14px;color:#555;text-decoration:none">← Back</a>
</div>
<div class="receipt">
  <div class="rh"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><div><div class="sname"><?=e($school)?></div><div class="ssub">Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?=e(setting('school_phone','+231 886 417 711'))?></div></div></div>
  <div class="rtitle">Official Payment Receipt</div>
  <div class="rno"><span><strong>Receipt #:</strong> <?=e($pay['receipt_number'])?></span><span><strong>Date:</strong> <?=date('F d, Y',strtotime($pay['payment_date']))?></span></div>
  <table>
    <tr><td>Student Name</td><td><?=e($pay['sname'])?></td></tr>
    <tr><td>Student ID</td><td><?=e($pay['sid'])?></td></tr>
    <tr><td>Grade</td><td><?=e($pay['grade_name']??'—')?></td></tr>
    <tr><td>Academic Year</td><td><?=e($pay['academic_year_id']?currentAcademicYearName():'—')?></td></tr>
    <tr><td>Fee Type</td><td><?=e($feeType?:'School Fees')?></td></tr>
    <tr><td>Payment Method</td><td><?=e($pay['payment_method'])?></td></tr>
    <?php if($pay['notes']):?><tr><td>Notes</td><td><?=e($pay['notes'])?></td></tr><?php endif;?>
    <tr class="total-row"><td>Amount Paid</td><td><?=e($pay['currency'])?> <?=number_format($pay['amount'],2)?></td></tr>
  </table>
  <div class="stamp">✓ PAYMENT RECEIVED — <?=e($school)?></div>
  <div class="footer"><?=e($school)?> &nbsp;|&nbsp; This receipt is valid proof of payment &nbsp;|&nbsp; <?=date('Y')?></div>
</div>
</body></html>
