<?php
// ── POST must run BEFORE admin_header outputs HTML ────────────
require_once dirname(__DIR__).'/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    verifyCsrf();
    $pdo    = db();
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

// ── Now output the page ───────────────────────────────────────
$pageTitle   = 'Applications';
$activeAdmin = 'applications';
require_once dirname(__DIR__).'/includes/admin_header.php';
requirePermission('admissions.view');

$pdo = db();
// ── Filters ───────────────────────────────────────────────────
$q      = trim($_GET['q']      ?? '');
$status = trim($_GET['status'] ?? '');
$grade  = trim($_GET['grade']  ?? '');
$ayAppF = (int)($_GET['ay_id'] ?? currentAcademicYearId()); // default = current year; 0 = all
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 15;

$where=[]; $params=[];
if ($q)      { $where[]='(a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.phone LIKE ?)'; $like="%$q%"; $params=array_merge($params,[$like,$like,$like,$like]); }
if ($status) { $where[]='a.status=?'; $params[]=$status; }
if ($grade)  { $where[]='a.grade_applying_for=?'; $params[]=$grade; }
if ($ayAppF) { $where[]='a.academic_year_id=?'; $params[]=$ayAppF; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM applications a $wsql");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $per, $page);
try {
    $rowStmt = $pdo->prepare("SELECT a.* FROM applications a $wsql ORDER BY a.created_at DESC LIMIT $per OFFSET {$pg['offset']}");
    $rowStmt->execute($params);
    $apps = $rowStmt->fetchAll();
} catch (Throwable $e) { $apps = []; }

try {
    $grades = $pdo->query("SELECT name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $grades = []; }
$statuses = ['Application Submitted','Under Review','Documents needed','Approved for entrance','Entrance scheduled','Entrance completed','Entrance passed','Admitted','Rejected','Waitlisted'];

// Summary counts — scoped to selected year
$summaryWhere = $ayAppF ? "WHERE academic_year_id=$ayAppF" : '';
try {
    $summary = $pdo->query("SELECT status, COUNT(*) AS cnt FROM applications $summaryWhere GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $summary = []; }
try {
    $allYearsApp = $pdo->query("SELECT id,name,is_current FROM academic_years ORDER BY start_date DESC")->fetchAll();
} catch (Throwable $e) { $allYearsApp = []; }
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
    <select name="ay_id" class="filter-button" onchange="this.form.submit()" title="Filter by academic year">
      <option value="0">All years</option>
      <?php foreach ($allYearsApp as $yr): ?>
      <option value="<?= $yr['id'] ?>" <?= $ayAppF==$yr['id']?'selected':'' ?>>
        <?= e($yr['name']) ?><?= $yr['is_current']?' (Current)':'' ?>
      </option>
      <?php endforeach; ?>
    </select>
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
      <thead>
        <tr>
          <th>Applicant</th>
          <th>App #</th>
          <th>Grade</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Docs</th>
          <th style="min-width:220px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($apps)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--ink-faint)">No applications found.</td></tr>
        <?php else: ?>
        <?php foreach ($apps as $app):
          $ini = strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1));
          // Button colours per action
          $wf = match($app['status']) {
            'Application Submitted' => [['review','🔍 Review','var(--primary)'],['docs','📄 Req. Docs','var(--warning)'],['reject','✗ Reject','var(--error)']],
            'Under Review'          => [['approve','✓ Approve','var(--green)'],['docs','📄 Req. Docs','var(--warning)'],['reject','✗ Reject','var(--error)']],
            'Documents needed'      => [['review','🔍 Review','var(--primary)'],['reject','✗ Reject','var(--error)']],
            'Approved for entrance' => [['schedule','📅 Schedule Exam','#6366f1']],
            'Entrance scheduled'    => [['admit','🎓 Admit','var(--green)'],['reject','✗ Reject','var(--error)']],
            default                 => [],
          };
        ?>
        <tr>
          <td>
            <div class="person">
              <div class="avatar-sm"><?= e($ini) ?></div>
              <div>
                <strong><?= e($app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name']) ?></strong>
                <div style="font-size:11px;color:var(--ink-faint)"><?= e($app['phone']) ?></div>
              </div>
            </div>
          </td>
          <td class="muted" style="font-size:12px"><?= e($app['application_number']) ?></td>
          <td style="font-size:13px"><?= e($app['grade_applying_for']) ?></td>
          <td class="muted" style="font-size:12px"><?= date('d M Y', strtotime($app['created_at'])) ?></td>
          <td><?= statusBadge($app['status']) ?></td>
          <td><?= statusBadge($app['document_status']??'Pending') ?></td>
          <td>
            <!-- Action buttons row -->
            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">

              <!-- View button → opens modal -->
              <button
                class="filter-button button-sm"
                style="background:#1a2744;color:#fff;border-color:#1a2744"
                onclick="openAppModal(<?= htmlspecialchars(json_encode([
                  'id'                  => $app['id'],
                  'name'                => $app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name'],
                  'ini'                 => $ini,
                  'app_number'          => $app['application_number'],
                  'grade'               => $app['grade_applying_for'],
                  'status'              => $app['status'],
                  'submitted'           => date('d M Y', strtotime($app['created_at'])),
                  'phone'               => $app['phone']     ?? '—',
                  'email'               => $app['email']     ?? '—',
                  'dob'                 => $app['date_of_birth'] ?? '—',
                  'gender'              => $app['gender']    ?? '—',
                  'county'              => $app['county']    ?? '—',
                  'district'            => $app['district']  ?? '—',
                  'community'           => $app['community'] ?? '—',
                  'prev_school'         => $app['previous_school']    ?? '—',
                  'last_grade'          => $app['last_grade_completed'] ?? '—',
                  'guardian_name'       => $app['guardian_name']         ?? '—',
                  'guardian_rel'        => $app['guardian_relationship'] ?? '—',
                  'guardian_phone'      => $app['guardian_phone']        ?? '—',
                  'guardian_email'      => $app['guardian_email']        ?? '—',
                  'doc_status'          => $app['document_status'] ?? 'Pending',
                  'exam_date'           => $app['entrance_exam_date'] ?? '—',
                  'exam_time'           => $app['entrance_exam_time'] ?? '—',
                  'letter_ref'          => $app['entrance_letter_ref'] ?? '—',
                  'internal_notes'      => $app['internal_notes'] ?? '',
                  'ay'                  => $app['academic_year'] ?? $ay,
                ]), ENT_QUOTES) ?>)">
                👁 View
              </button>

              <!-- Workflow action buttons -->
              <?php foreach ($wf as [$act,$lbl,$col]): ?>
              <form method="post" style="display:inline" <?= $act==='reject'?"onsubmit=\"return confirm('Reject this application?')\"":'' ?>>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= e($act) ?>"/>
                <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
                <button type="submit" class="filter-button button-sm"
                        style="color:<?= $col ?>;border-color:<?= $col ?>;font-weight:700">
                  <?= e($lbl) ?>
                </button>
              </form>
              <?php endforeach; ?>

              <!-- Entrance letter link -->
              <?php if (!empty($app['entrance_letter_ref'])): ?>
              <a href="<?= BASE_URL ?>/letters/entrance_letter.php?id=<?= $app['id'] ?>"
                 class="filter-button button-sm" target="_blank"
                 style="color:#6366f1;border-color:#6366f1">
                📄 Letter
              </a>
              <?php endif; ?>

              <!-- Schedule exam (if approved) -->
              <?php if ($app['status']==='Approved for entrance'): ?>
              <button class="filter-button button-sm" style="color:#6366f1"
                      onclick="document.getElementById('examForm<?= $app['id'] ?>').style.display=document.getElementById('examForm<?= $app['id'] ?>').style.display==='none'?'flex':'none'">
                🗓 Set Date
              </button>
              <?php endif; ?>
            </div>

            <!-- Exam date inline form (collapsed) -->
            <?php if ($app['status']==='Approved for entrance'): ?>
            <form id="examForm<?= $app['id'] ?>" method="post"
                  style="display:none;margin-top:8px;gap:8px;align-items:center;flex-wrap:wrap;
                         padding:10px;background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--line)">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="set_exam_date"/>
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
              <label style="font-size:12px;font-weight:600">Date
                <input type="date" name="exam_date" class="filter-button" style="padding:5px 10px;margin-top:2px" value="<?= e($app['entrance_exam_date'] ?? '') ?>"/>
              </label>
              <label style="font-size:12px;font-weight:600">Time
                <input type="time" name="exam_time" class="filter-button" style="padding:5px 10px;margin-top:2px" value="<?= e($app['entrance_exam_time'] ?? '') ?>"/>
              </label>
              <button type="submit" class="button button-primary button-sm" style="align-self:flex-end">Save</button>
            </form>
            <?php endif; ?>
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

<!-- ══ APPLICATION MODAL ══════════════════════════════════ -->
<div id="appModal" style="
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(10,10,20,.8);
    align-items:center;justify-content:center;padding:16px">
  <div style="
      background:var(--surface);border-radius:12px;
      width:100%;max-width:720px;max-height:92vh;
      display:flex;flex-direction:column;
      box-shadow:0 24px 80px rgba(0,0,0,.5);overflow:hidden">

    <!-- Header -->
    <div style="background:#1a2744;padding:16px 20px;display:flex;align-items:center;gap:14px;flex-shrink:0">
      <div id="appModalAvatar"
           style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.15);
                  color:#fff;font-size:15px;font-weight:800;display:flex;align-items:center;
                  justify-content:center;flex-shrink:0">
      </div>
      <div style="flex:1;min-width:0">
        <div id="appModalName" style="font-size:16px;font-weight:800;color:#fff"></div>
        <div id="appModalSub"  style="font-size:11.5px;color:rgba(255,255,255,.55);margin-top:2px"></div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <span id="appModalStatus"></span>
        <button onclick="closeAppModal()"
                style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
                       border-radius:6px;color:#fff;font-size:18px;cursor:pointer;
                       width:32px;height:32px;display:flex;align-items:center;justify-content:center"
                title="Close (Esc)">✕</button>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;background:#f4f5f8;border-bottom:2px solid var(--line);flex-shrink:0">
      <button id="appTab1" onclick="switchAppTab(1)"
              style="padding:10px 20px;font-size:13px;font-weight:700;border:none;cursor:pointer;
                     border-bottom:3px solid var(--primary);color:var(--primary);background:none">
        👤 Personal
      </button>
      <button id="appTab2" onclick="switchAppTab(2)"
              style="padding:10px 20px;font-size:13px;font-weight:700;border:none;cursor:pointer;
                     border-bottom:3px solid transparent;color:var(--ink-soft);background:none">
        👨‍👩‍👧 Guardian
      </button>
      <button id="appTab3" onclick="switchAppTab(3)"
              style="padding:10px 20px;font-size:13px;font-weight:700;border:none;cursor:pointer;
                     border-bottom:3px solid transparent;color:var(--ink-soft);background:none">
        🎓 Academic
      </button>
      <button id="appTab4" onclick="switchAppTab(4)"
              style="padding:10px 20px;font-size:13px;font-weight:700;border:none;cursor:pointer;
                     border-bottom:3px solid transparent;color:var(--ink-soft);background:none">
        📋 Exam & Notes
      </button>
    </div>

    <!-- Body -->
    <div style="flex:1;overflow-y:auto;padding:20px 24px">

      <!-- Pane 1: Personal -->
      <div id="appPane1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <?php
          $appFields1 = [
            ['Full Name','name'],['Application #','app_number'],
            ['Date of Birth','dob'],['Gender','gender'],
            ['Phone','phone'],['Email','email'],
            ['County','county'],['District','district'],
            ['Community','community'],['Academic Year','ay'],
          ];
          foreach ($appFields1 as [$lbl,$key]): ?>
          <div style="background:var(--bg);border-radius:8px;padding:12px 14px;border:1px solid var(--line)">
            <div style="font-size:10.5px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><?= $lbl ?></div>
            <div style="font-size:13px;color:var(--ink);font-weight:600" data-field="<?= $key ?>">—</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Pane 2: Guardian -->
      <div id="appPane2" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <?php
          $appFields2 = [
            ['Guardian Name','guardian_name'],['Relationship','guardian_rel'],
            ['Guardian Phone','guardian_phone'],['Guardian Email','guardian_email'],
          ];
          foreach ($appFields2 as [$lbl,$key]): ?>
          <div style="background:var(--bg);border-radius:8px;padding:12px 14px;border:1px solid var(--line)">
            <div style="font-size:10.5px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><?= $lbl ?></div>
            <div style="font-size:13px;color:var(--ink);font-weight:600" data-field="<?= $key ?>">—</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Pane 3: Academic -->
      <div id="appPane3" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <?php
          $appFields3 = [
            ['Grade Applying For','grade'],['Previous School','prev_school'],
            ['Last Grade Completed','last_grade'],['Document Status','doc_status'],
            ['Date Submitted','submitted'],['Status','status'],
          ];
          foreach ($appFields3 as [$lbl,$key]): ?>
          <div style="background:var(--bg);border-radius:8px;padding:12px 14px;border:1px solid var(--line)">
            <div style="font-size:10.5px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><?= $lbl ?></div>
            <div style="font-size:13px;color:var(--ink);font-weight:600" data-field="<?= $key ?>">—</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Pane 4: Exam & Notes -->
      <div id="appPane4" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <?php
          $appFields4 = [
            ['Exam Date','exam_date'],['Exam Time','exam_time'],
            ['Entrance Letter Ref','letter_ref'],
          ];
          foreach ($appFields4 as [$lbl,$key]): ?>
          <div style="background:var(--bg);border-radius:8px;padding:12px 14px;border:1px solid var(--line)">
            <div style="font-size:10.5px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><?= $lbl ?></div>
            <div style="font-size:13px;color:var(--ink);font-weight:600" data-field="<?= $key ?>">—</div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="appModalNotes" style="display:none;margin-top:14px;padding:14px;background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;font-size:13px">
          <strong>Internal Notes:</strong>
          <div id="appModalNotesText" style="margin-top:4px;color:var(--ink)"></div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div style="padding:12px 20px;background:#f4f5f8;border-top:1px solid var(--line);
                display:flex;justify-content:space-between;align-items:center;flex-shrink:0;font-size:12px;color:var(--ink-soft)">
      <span>KARN HIGH SCHOOL — Admissions</span>
      <button onclick="closeAppModal()"
              class="button button-secondary button-sm">Close</button>
    </div>
  </div>
</div>

<script>
var _appData = null;
function openAppModal(d) {
  _appData = d;
  // Header
  document.getElementById('appModalAvatar').textContent = d.ini;
  document.getElementById('appModalName').textContent   = d.name;
  document.getElementById('appModalSub').textContent    = d.app_number + '  ·  ' + d.grade + '  ·  Submitted ' + d.submitted;
  document.getElementById('appModalStatus').innerHTML   =
    '<span style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;background:rgba(255,255,255,.15);color:#fff">'+ d.status +'</span>';
  // Fill all data-field cells
  document.querySelectorAll('#appModal [data-field]').forEach(function(el) {
    el.textContent = d[el.dataset.field] || '—';
  });
  // Notes
  var notesWrap = document.getElementById('appModalNotes');
  var notesText = document.getElementById('appModalNotesText');
  if (d.internal_notes) {
    notesText.textContent = d.internal_notes;
    notesWrap.style.display = 'block';
  } else {
    notesWrap.style.display = 'none';
  }
  switchAppTab(1);
  document.getElementById('appModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeAppModal() {
  document.getElementById('appModal').style.display = 'none';
  document.body.style.overflow = '';
}
function switchAppTab(n) {
  for (var i=1; i<=4; i++) {
    var btn  = document.getElementById('appTab'+i);
    var pane = document.getElementById('appPane'+i);
    var active = i === n;
    btn.style.borderBottomColor = active ? 'var(--primary)' : 'transparent';
    btn.style.color = active ? 'var(--primary)' : 'var(--ink-soft)';
    pane.style.display = active ? 'block' : 'none';
  }
}
document.getElementById('appModal').addEventListener('click', function(e){ if(e.target===this) closeAppModal(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAppModal(); });
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
