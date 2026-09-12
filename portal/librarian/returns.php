<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['librarian','principal','sys_admin','school_admin']);

$activePage = 'returns';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$librarian = null;
try { $s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1"); $s->execute([$user['id']]); $librarian=$s->fetch()?:null; } catch (Throwable $e) {}
if (!$librarian) $librarian=['first_name'=>$user['username']??'Librarian','last_name'=>'','id'=>0];

$txId = (int)($_GET['tx_id'] ?? 0);

// POST: process return
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $paction = $_POST['action'] ?? '';
    if ($paction === 'return_book') {
        $tid      = (int)$_POST['tx_id'];
        $fineAmt  = max(0, (float)($_POST['fine_amount']??0));
        $finePaid = isset($_POST['fine_paid'])?1:0;
        $condition= $_POST['book_condition']??'Good';
        try {
            $tx = $pdo->query("SELECT * FROM library_transactions WHERE id=$tid AND status='borrowed'")->fetch();
            if (!$tx) throw new RuntimeException('Transaction not found or already returned.');
            $pdo->prepare(
                "UPDATE library_transactions SET status='returned',returned_date=NOW(),fine_amount=?,fine_paid=?,book_condition=?,returned_by=? WHERE id=?"
            )->execute([$fineAmt,$finePaid,$condition,$user['id'],$tid]);
            $pdo->prepare("UPDATE books SET available_copies=available_copies+1 WHERE id=?")->execute([$tx['book_id']]);
            flash('success','Book returned successfully'.(($fineAmt>0&&!$finePaid)?' — fine of GHS '.number_format($fineAmt,2).' outstanding':'').'.') ;
        } catch (Throwable $e) { flash('error','Failed: '.$e->getMessage()); }
        redirect(BASE_URL.'/portal/librarian/returns.php');
    }
}

// Pre-load specific transaction for return form
$transaction = null;
if ($txId) {
    try {
        $transaction = $pdo->query(
            "SELECT lt.*,b.title book_title,b.id book_id,
                    CONCAT(s.first_name,' ',s.last_name) student_name,s.student_id sid,c.name class_name
             FROM library_transactions lt
             JOIN books b ON b.id=lt.book_id
             JOIN students s ON s.id=lt.student_id
             LEFT JOIN classes c ON c.id=s.current_class_id
             WHERE lt.id=$txId AND lt.status='borrowed'"
        )->fetch();
    } catch(Throwable $e){}
}

// Active loans to return
try {
    $activeLoans = $pdo->query(
        "SELECT lt.*,b.title book_title,
                CONCAT(s.first_name,' ',s.last_name) student_name,s.student_id sid,c.name class_name,
                DATEDIFF(CURDATE(),lt.due_date) days_overdue
         FROM library_transactions lt
         JOIN books b ON b.id=lt.book_id
         JOIN students s ON s.id=lt.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE lt.status='borrowed'
         ORDER BY days_overdue DESC,lt.due_date ASC LIMIT 100"
    )->fetchAll();
} catch(Throwable $e){$activeLoans=[];}

// Today's returns
try {
    $todayReturns = $pdo->query(
        "SELECT lt.*,b.title book_title,CONCAT(s.first_name,' ',s.last_name) student_name
         FROM library_transactions lt
         JOIN books b ON b.id=lt.book_id
         JOIN students s ON s.id=lt.student_id
         WHERE lt.status='returned' AND DATE(lt.returned_date)=CURDATE()
         ORDER BY lt.returned_date DESC"
    )->fetchAll();
} catch(Throwable $e){$todayReturns=[];}

$conditions=['Good','Fair','Damaged','Lost'];
// Default fine rate per day
$finePerDay = 0.50;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Returns — Library Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Returns</h1><p>Process book returns &mdash; <?= count($activeLoans) ?> active loan<?= count($activeLoans)!=1?'s':'' ?></p></div>
  </div>

  <?php foreach (getFlash() as $f): ?><div class="alert alert-<?= $f['type'] ?>"><?= e($f['message']) ?></div><?php endforeach; ?>

  <!-- Return form for a specific transaction -->
  <?php if ($transaction): ?>
  <div class="panel" style="padding:22px;margin-bottom:16px;border-left:4px solid var(--primary)">
    <h3 style="font-weight:700;margin-bottom:14px">Process Return</h3>
    <div style="background:var(--bg2);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px;font-size:13px">
      <strong><?= e($transaction['student_name']) ?></strong> <span class="muted"><?= e($transaction['sid']) ?> &middot; <?= e($transaction['class_name']??'—') ?></span><br/>
      📖 <?= e($transaction['book_title']) ?><br/>
      Borrowed: <?= date('d M Y',strtotime($transaction['borrow_date'])) ?> &middot;
      Due: <strong style="color:<?= $transaction['due_date']<date('Y-m-d')?'var(--error)':'var(--ink2)' ?>"><?= date('d M Y',strtotime($transaction['due_date'])) ?></strong>
      <?php $daysOver=max(0,ceil((time()-strtotime($transaction['due_date']))/86400)); if($daysOver>0):?>
      <span style="color:var(--error);font-weight:700"> — <?=$daysOver?> day<?=$daysOver!=1?'s':''?> overdue</span>
      <?php endif;?>
    </div>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="return_book"/><input type="hidden" name="tx_id" value="<?= $txId ?>"/>
      <div class="form-grid">
        <div class="form-group">
          <label>Book Condition</label>
          <select name="book_condition">
            <?php foreach ($conditions as $c): ?><option value="<?=$c?>"><?=$c?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fine Amount (GHS)</label>
          <input type="number" name="fine_amount" min="0" step="0.01" value="<?= round($daysOver*$finePerDay,2) ?>"/>
          <?php if($daysOver>0):?><small style="color:var(--error)">Suggested: GHS <?=number_format($daysOver*$finePerDay,2)?> (<?=$daysOver?> days × GHS <?=$finePerDay?>)</small><?php endif;?>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:20px">
            <input type="checkbox" name="fine_paid" style="width:auto"/> Fine collected now
          </label>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="button button-primary">📥 Process Return</button>
        <a href="returns.php" class="button button-secondary">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
    <!-- Active loans -->
    <div class="panel" style="padding:18px">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📤 Active Loans</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Overdue</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($activeLoans)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--ink-faint);padding:24px">No active loans.</td></tr>
            <?php else: foreach ($activeLoans as $l): $ov=max(0,(int)$l['days_overdue']); ?>
            <tr <?=$ov>0?'style="background:rgba(239,68,68,.04)"':''?>>
              <td><strong><?=e($l['student_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($l['sid'])?></div></td>
              <td style="max-width:150px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($l['book_title'])?></div></td>
              <td class="muted" style="<?=$ov>0?'color:var(--error);font-weight:700':''?>"><?=date('d M',strtotime($l['due_date']))?></td>
              <td><?=$ov>0?"<span style='color:var(--error);font-weight:700'>$ov day".($ov!=1?'s':'')."</span>":'<span class="muted">—</span>'?></td>
              <td><a href="?tx_id=<?=$l['id']?>" class="button button-primary button-sm">📥 Return</a></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Today's returns -->
    <div class="panel" style="padding:18px">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">✅ Today's Returns (<?=count($todayReturns)?>)</h3>
      <?php if (empty($todayReturns)): ?>
      <p style="color:var(--ink-faint);font-size:13px">None yet today.</p>
      <?php else: foreach ($todayReturns as $r): ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--line)">
        <div style="font-size:13px;font-weight:600"><?=e($r['student_name'])?></div>
        <div style="font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($r['book_title'],0,35))?></div>
        <?php if($r['fine_amount']>0):?>
        <div style="font-size:11px;color:<?=$r['fine_paid']?'var(--green)':'var(--error)'?>">
          Fine: GHS <?=number_format($r['fine_amount'],2)?> <?=$r['fine_paid']?'(Paid)':'(Outstanding)'?>
        </div>
        <?php endif;?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
