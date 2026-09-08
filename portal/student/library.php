<?php
// ============================================================
// Student Portal — Library Records
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo    = db();
$user   = currentUser();
$activePage = 'library';

$student = $pdo->prepare(
    "SELECT id, first_name, last_name, student_id FROM students WHERE user_id=? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

// Current borrowings (not returned)
try {
    $current = $pdo->prepare(
        "SELECT lb.*, b.title, b.author, b.isbn, b.category
         FROM library_borrowings lb
         JOIN books b ON b.id = lb.book_id
         WHERE lb.student_id = ? AND lb.returned_at IS NULL
         ORDER BY lb.due_date ASC"
    );
    $current->execute([$student['id']]);
    $current = $current->fetchAll();
} catch (Throwable $e) { $current = []; }

// Borrowing history (returned)
try {
    $history = $pdo->prepare(
        "SELECT lb.*, b.title, b.author, b.isbn
         FROM library_borrowings lb
         JOIN books b ON b.id = lb.book_id
         WHERE lb.student_id = ? AND lb.returned_at IS NOT NULL
         ORDER BY lb.returned_at DESC LIMIT 30"
    );
    $history->execute([$student['id']]);
    $history = $history->fetchAll();
} catch (Throwable $e) { $history = []; }

// Outstanding fines
try {
    $fines = $pdo->prepare(
        "SELECT COALESCE(SUM(fine_amount),0) total,
                SUM(CASE WHEN fine_paid=0 THEN fine_amount ELSE 0 END) unpaid
         FROM library_borrowings
         WHERE student_id = ? AND fine_amount > 0"
    );
    $fines->execute([$student['id']]);
    $fines = $fines->fetch();
} catch (Throwable $e) { $fines = ['total'=>0,'unpaid'=>0]; }

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Library — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>Library</h1>
      <p>Your borrowing records and book history</p>
    </div>
  </div>

  <!-- Summary metrics -->
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="metric-card">
      <div class="metric-top"><span>Currently Borrowed</span><div class="metric-icon">📖</div></div>
      <strong><?= count($current) ?></strong>
      <small><i></i>Books in your possession</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Total Borrowed</span><div class="metric-icon">📚</div></div>
      <strong><?= count($current) + count($history) ?></strong>
      <small><i></i>All time</small>
    </div>
    <div class="metric-card <?= $fines['unpaid'] > 0 ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Outstanding Fines</span><div class="metric-icon">💸</div></div>
      <strong style="<?= $fines['unpaid'] > 0 ? 'color:var(--error)' : 'color:var(--green)' ?>">
        LRD <?= number_format((float)$fines['unpaid'], 2) ?>
      </strong>
      <small><i></i><?= $fines['unpaid'] > 0 ? 'Pay at library desk' : 'No outstanding fines' ?></small>
    </div>
  </div>

  <!-- Currently borrowed -->
  <div class="panel" style="padding:20px 22px;margin-bottom:20px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">📖 Currently Borrowed</h3>
    <?php if (empty($current)): ?>
    <p style="color:var(--ink-faint);font-size:13px;text-align:center;padding:20px">You have no books currently borrowed.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Book Title</th>
            <th>Author</th>
            <th>Borrowed</th>
            <th>Due Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($current as $b):
            $overdue  = $b['due_date'] && $b['due_date'] < $today;
            $dueSoon  = !$overdue && $b['due_date'] && $b['due_date'] <= date('Y-m-d', strtotime('+3 days'));
          ?>
          <tr>
            <td><strong><?= e($b['title']) ?></strong><?php if (!empty($b['isbn'])): ?><br><span class="muted" style="font-size:11px">ISBN: <?= e($b['isbn']) ?></span><?php endif; ?></td>
            <td class="muted"><?= e($b['author'] ?? '—') ?></td>
            <td class="muted"><?= $b['borrowed_at'] ? date('M d, Y', strtotime($b['borrowed_at'])) : '—' ?></td>
            <td>
              <?php if ($b['due_date']): ?>
              <span style="font-weight:600;color:<?= $overdue?'var(--error)':($dueSoon?'var(--warning)':'var(--ink)') ?>">
                <?= date('M d, Y', strtotime($b['due_date'])) ?>
              </span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php if ($overdue): ?>
              <span class="status warning">Overdue</span>
              <?php elseif ($dueSoon): ?>
              <span class="status pending">Due Soon</span>
              <?php else: ?>
              <span class="status approved">Active</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Borrowing history -->
  <?php if (!empty($history)): ?>
  <div class="panel" style="padding:20px 22px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">📋 Borrowing History</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Book Title</th>
            <th>Author</th>
            <th>Borrowed</th>
            <th>Returned</th>
            <th>Fine</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $b): ?>
          <tr>
            <td><?= e($b['title']) ?></td>
            <td class="muted"><?= e($b['author'] ?? '—') ?></td>
            <td class="muted"><?= $b['borrowed_at'] ? date('M d, Y', strtotime($b['borrowed_at'])) : '—' ?></td>
            <td class="muted"><?= $b['returned_at'] ? date('M d, Y', strtotime($b['returned_at'])) : '—' ?></td>
            <td>
              <?php if ((float)$b['fine_amount'] > 0): ?>
              <span style="color:<?= $b['fine_paid']?'var(--green)':'var(--error)' ?>;font-weight:600;font-size:12px">
                LRD <?= number_format((float)$b['fine_amount'],2) ?>
                <?= $b['fine_paid'] ? '✓' : '(unpaid)' ?>
              </span>
              <?php else: ?>
              <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <p style="margin-top:16px;font-size:12px;color:var(--ink-faint);text-align:center">
    To borrow or return books, visit the school library. For fines, pay at the library desk.
  </p>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
