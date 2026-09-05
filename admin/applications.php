<?php
$pageTitle   = 'Applications';
$activeAdmin = 'applications';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['principal','registrar','academic_dean','super_admin','vice_principal']);

$pdo = db();

// ── Handle status actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['note'] ?? '');
    $statusMap = [
        'review'   => 'Under Review',
        'docs'     => 'Documents needed',
        'approve'  => 'Approved for entrance',
        'schedule' => 'Entrance scheduled',
        'admit'    => 'Admitted',
        'reject'   => 'Rejected',
        'waitlist' => 'Waitlisted',
    ];
    if ($id && isset($statusMap[$action])) {
        $old = $pdo->prepare("SELECT status FROM applications WHERE id=?")->execute([$id]) ? $pdo->query("SELECT status FROM applications WHERE id=$id")->fetchColumn() : '';
        $new = $statusMap[$action];
        $pdo->prepare("UPDATE applications SET status=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$new, currentUser()['id'], $id]);
        $pdo->prepare("INSERT INTO application_status_history (application_id,old_status,new_status,changed_by,notes) VALUES (?,?,?,?,?)")->execute([$id,$old,$new,currentUser()['id'],$note]);
        auditLog('update_status','applications','application',$id,$old,$new);

        // Auto-generate entrance letter ref
        if ($new === 'Approved for entrance') {
            $ref = 'KEL-'.date('Y').'-'.str_pad($id,5,'0',STR_PAD_LEFT);
            $pdo->prepare("UPDATE applications SET entrance_letter_ref=? WHERE id=? AND entrance_letter_ref IS NULL")->execute([$ref,$id]);
        }
        flash('success','Application status updated to: '.$new);
    }
    if ($action === 'set_exam_date' && $id) {
        $date = $_POST['exam_date'] ?? '';
        $time = $_POST['exam_time'] ?? '';
        $pdo->prepare("UPDATE applications SET entrance_exam_date=?,entrance_exam_time=?,status='Entrance scheduled',updated_at=NOW() WHERE id=?")->execute([$date ?: null, $time ?: null, $id]);
        flash('success','Entrance exam date scheduled.');
    }
    redirect(BASE_URL.'/admin/applications.php?'.http_build_query(array_filter(['q'=>$_GET['q']??'','status'=>$_GET['status']??'','grade'=>$_GET['grade']??'','page'=>$_GET['page']??''])));
}

// ── Filters ───────────────────────────────────────────────────
$q      = trim($_GET['q']      ?? '');
$status = trim($_GET['status'] ?? '');
$grade  = trim($_GET['grade']  ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 15;

$where=[]; $params=[];
if ($q)      { $where[]='(a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.phone LIKE ?)'; $like="%$q%"; $params=array_merge($params,[$like,$like,$like,$like]); }
if ($status) { $where[]='a.status=?'; $params[]=$status; }
if ($grade)  { $where[]='a.grade_applying_for=?'; $params[]=$grade; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM applications a $wsql")->execute($params) ? $pdo->query("SELECT COUNT(*) FROM (SELECT a.id FROM applications a $wsql) t")->fetchColumn() : 0;
// proper count
$cnt = $pdo->prepare("SELECT COUNT(*) FROM applications a $wsql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg  = paginate($total,$per,$page);
$rows = $pdo->prepare("SELECT a.* FROM applications a $wsql ORDER BY a.created_at DESC LIMIT $per OFFSET {$pg['offset']}");
$rows->execute($params); $apps = $rows->fetchAll();

$grades   = $pdo->query("SELECT name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN);
$statuses = ['Application Submitted','Under Review','Documents needed','Approved for entrance','Entrance scheduled','Entrance completed','Entrance passed','Admitted','Rejected','Waitlisted'];

// Summary counts
$summary = $pdo->query("SELECT status,COUNT(*) cnt FROM applications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Admissions <span></span></div>
    <h1>Applications</h1>
    <p>Manage and review admission applications for KARN HIGH SCHOOL.</p>
  </div>
  <a href="<?= BASE_URL ?>/apply.php" target="_blank" class="button button-secondary">🌐 Public Apply Form</a>
</div>

<!-- Summary -->
<div class="metric-grid" style="margin-bottom:20px">
  <?php
  $cards=[['Total',(int)array_sum($summary),'📋'],['Pending',($summary['Application Submitted']??0)+($summary['Under Review']??0),'⏳'],['Approved',($summary['Approved for entrance']??0)+($summary['Admitted']??0),'✅'],['Admitted',$summary['Admitted']??0,'🎓']];
  foreach($cards as [$l,$v,$ic]):
  ?>
  <div class="metric-card"><div class="metric-top"><span><?= $l ?></span><div class="metric-icon"><?= $ic ?></div></div><strong><?= number_format($v) ?></strong></div>
  <?php endforeach; ?>
</div>

<div class="list-content">
  <!-- Filters -->
  <form method="get" class="filter-row">
    <div class="table-search">🔍<input type="search" name="q" placeholder="Name, app number, phone…" value="<?= e($q) ?>"/></div>
    <select name="status" class="filter-button" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <select name="grade" class="filter-button" onchange="this.form.submit()">
      <option value="">All grades</option>
      <?php foreach ($grades as $g): ?><option value="<?= e($g) ?>" <?= $grade===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="button button-primary button-sm">Search</button>
    <?php if ($q||$status||$grade): ?><a href="<?= BASE_URL ?>/admin/applications.php" class="filter-button">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Applicant</th><th>App Number</th><th>Grade</th><th>Submitted</th><th>Status</th><th>Documents</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($apps)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--ink-faint)">No applications found.</td></tr>
        <?php else: ?>
        <?php foreach ($apps as $app):
          $ini = strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1));
        ?>
        <tr>
          <td><div class="person"><div class="avatar-sm"><?= e($ini) ?></div><div>
            <strong><?= e($app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name']) ?></strong>
            <div style="font-size:11.5px;color:var(--ink-faint)"><?= e($app['phone']) ?></div>
          </div></div></td>
          <td class="muted" style="font-size:12px"><?= e($app['application_number']) ?></td>
          <td><?= e($app['grade_applying_for']) ?></td>
          <td class="muted"><?= date('M d, Y',strtotime($app['created_at'])) ?></td>
          <td><?= statusBadge($app['status']) ?></td>
          <td><?= statusBadge($app['document_status']??'Pending') ?></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <!-- View detail toggle -->
              <button class="filter-button button-sm" onclick="toggleDetail('d<?= $app['id'] ?>')">👁 View</button>

              <!-- Workflow buttons -->
              <?php
              $wf = match($app['status']) {
                'Application Submitted' => [['review','Under Review'],['docs','Req. Docs']],
                'Under Review'          => [['approve','Approve for Entrance'],['docs','Req. Docs'],['reject','Reject']],
                'Documents needed'      => [['review','Review'],['reject','Reject']],
                'Approved for entrance' => [['schedule','Schedule Exam']],
                'Entrance scheduled'    => [['admit','Admit'],['reject','Reject']],
                default                 => [],
              };
              foreach ($wf as [$act,$lbl]):
              ?>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= e($act) ?>"/>
                <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
                <button type="submit" class="filter-button button-sm"><?= e($lbl) ?></button>
              </form>
              <?php endforeach; ?>

              <?php if (!empty($app['entrance_letter_ref'])): ?>
              <a href="<?= BASE_URL ?>/letters/entrance_letter.php?id=<?= $app['id'] ?>" class="filter-button button-sm" target="_blank">📄 Letter</a>
              <?php endif; ?>
            </div>

            <!-- Detail panel -->
            <div id="d<?= $app['id'] ?>" style="display:none;margin-top:10px;background:var(--bg);border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.9;min-width:380px">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px">
                <span><strong>DOB:</strong> <?= e($app['date_of_birth']) ?></span>
                <span><strong>Gender:</strong> <?= e($app['gender']) ?></span>
                <span><strong>Email:</strong> <?= e($app['email']??'—') ?></span>
                <span><strong>County:</strong> <?= e($app['county']) ?></span>
                <span><strong>Previous School:</strong> <?= e($app['previous_school']??'—') ?></span>
                <span><strong>Last Grade:</strong> <?= e($app['last_grade_completed']??'—') ?></span>
                <span><strong>Guardian:</strong> <?= e($app['guardian_name'].' ('.$app['guardian_relationship'].')') ?></span>
                <span><strong>G. Phone:</strong> <?= e($app['guardian_phone']) ?></span>
                <?php if (!empty($app['entrance_exam_date'])): ?>
                <span><strong>Exam Date:</strong> <?= e($app['entrance_exam_date']) ?></span>
                <span><strong>Exam Time:</strong> <?= e($app['entrance_exam_time'] ?? '—') ?></span>
                <?php endif; ?>
                <?php if (!empty($app['entrance_letter_ref'])): ?>
                <span><strong>Letter Ref:</strong> <?= e($app['entrance_letter_ref']) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($app['internal_notes'])): ?>
              <div style="margin-top:8px;padding:8px;background:var(--warning-soft);border-radius:4px;font-size:12px"><strong>Notes:</strong> <?= e($app['internal_notes']) ?></div>
              <?php endif; ?>

              <!-- Schedule exam date inline -->
              <?php if ($app['status']==='Approved for entrance'): ?>
              <form method="post" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_exam_date"/>
                <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
                <input type="date" name="exam_date" class="filter-button" style="padding:5px 10px" value="<?= e($app['entrance_exam_date'] ?? '') ?>"/>
                <input type="time" name="exam_time" class="filter-button" style="padding:5px 10px" value="<?= e($app['entrance_exam_time'] ?? '') ?>"/>
                <button type="submit" class="button button-primary button-sm">Save Date</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pg['pages']>1): ?>
  <div class="pagination">
    <?php if ($pg['page']>1): ?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>&status=<?=urlencode($status)?>&grade=<?=urlencode($grade)?>">&laquo;</a><?php endif; ?>
    <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++): ?>
      <?php if($p===$pg['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?page=<?=$p?>&q=<?=urlencode($q)?>&status=<?=urlencode($status)?>&grade=<?=urlencode($grade)?>"><?=$p?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pg['page']<$pg['pages']): ?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>&status=<?=urlencode($status)?>&grade=<?=urlencode($grade)?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
