<?php
$pageTitle='Library Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $fn=explode(' ',currentUser()['name']??'Librarian')[0]; $hour=(int)date('G');
$greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$totalBooks=(int)$pdo->query("SELECT COUNT(*) FROM library_books WHERE is_active=1")->fetchColumn();
$issued=(int)$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE status='Issued'")->fetchColumn();
$overdue=(int)$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE status='Issued' AND due_date<CURDATE()")->fetchColumn();
$returned=$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE status='Returned' AND DATE(returned_at)=CURDATE()")->fetchColumn();
$recent=$pdo->query("SELECT lt.*,lb.title,CONCAT(s.first_name,' ',s.last_name) sname FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id JOIN students s ON s.id=lt.student_id ORDER BY lt.issued_at DESC LIMIT 8")->fetchAll();
?>
<div class="page-heading">
  <div><div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div><h1><?=$greet?>, <?=e($fn)?>.</h1><p>Library Dashboard</p></div>
  <a href="<?=BASE_URL?>/admin/library.php?tab=issue" class="button button-primary">📤 Issue Book</a>
</div>
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Total Books</span><div class="metric-icon">📚</div></div><strong><?=$totalBooks?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Currently Issued</span><div class="metric-icon">📤</div></div><strong><?=$issued?></strong></div>
  <div class="metric-card <?=$overdue>0?'finance-metrics':''?>"><div class="metric-top"><span>Overdue</span><div class="metric-icon">⏰</div></div><strong><?=$overdue?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Returned Today</span><div class="metric-icon">↩</div></div><strong><?=$returned?></strong></div>
</div>
<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?=BASE_URL?>/admin/library.php?tab=books"       class="quick-item"><span class="qi-icon">📚</span><div><strong>Catalogue</strong><small>All books</small></div></a>
  <a href="<?=BASE_URL?>/admin/library.php?tab=issue"       class="quick-item"><span class="qi-icon">📤</span><div><strong>Issue Book</strong><small>To student</small></div></a>
  <a href="<?=BASE_URL?>/admin/library.php?tab=transactions"class="quick-item"><span class="qi-icon">📋</span><div><strong>Transactions</strong><small>Returns & history</small></div></a>
  <a href="<?=BASE_URL?>/admin/students.php"                class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>Find borrower</small></div></a>
</div>
<div class="panel">
  <div class="panel-heading"><div><h3>Recent Transactions</h3></div><a href="<?=BASE_URL?>/admin/library.php?tab=transactions" class="filter-button">All →</a></div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table><thead><tr><th>Book</th><th>Student</th><th>Issued</th><th>Due</th><th>Status</th></tr></thead>
    <tbody><?php if(empty($recent)):?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--ink-faint)">No transactions yet.</td></tr>
    <?php else: foreach($recent as $t):?>
    <tr><td><strong><?=e($t['title'])?></strong></td><td><?=e($t['sname'])?></td><td class="muted"><?=date('M d',strtotime($t['issued_at']))?></td><td class="muted" style="color:<?=$t['status']==='Issued'&&strtotime($t['due_date'])<time()?'var(--error)':'inherit'?>"><?=date('M d',strtotime($t['due_date']))?></td><td><?=statusBadge($t['status'])?></td></tr>
    <?php endforeach; endif;?>
    </tbody></table>
  </div>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
