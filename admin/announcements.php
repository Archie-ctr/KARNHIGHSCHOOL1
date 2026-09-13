<?php
// ── POST must run BEFORE admin_header outputs HTML ────────────
require_once dirname(__DIR__).'/config/db.php';

// ── Auto-migrate: add priority column if missing ─────────────
try {
    $hasPriority = db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='announcements' AND COLUMN_NAME='priority'")->fetchColumn();
    if (!$hasPriority) {
        db()->exec("ALTER TABLE announcements ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER is_public");
    }
} catch (Throwable $e) { /* silently skip if can't alter */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    verifyCsrf();
    $pdo    = db();
    $action = $_POST['action'] ?? '';

    // ── Permission: who can create announcements ──────────────
    $canCreate = canAny([
        'comms.create_announcement',
        'comms.view_announcements',
    ]) || hasRole(['principal','super_admin','sys_admin','school_admin','vice_principal','registrar','teacher','class_teacher']);

    // ── Permission: who can delete announcements ──────────────
    $canDelete = hasRole(['principal','super_admin','sys_admin','school_admin','vice_principal']);

    if ($action === 'add' && $canCreate) {
        $title     = trim($_POST['title']    ?? '');
        $message   = trim($_POST['message']  ?? '');
        $target    = $_POST['target']        ?? 'all';
        $targetId  = (int)($_POST['target_id'] ?? 0) ?: null;
        $expires   = trim($_POST['expires_at'] ?? '') ?: null;
        $isPublic  = (int)($_POST['is_public']  ?? 0);
        $pubNow    = !empty($_POST['publish_now']);
        $priority  = $_POST['priority'] ?? 'normal';
        if ($title && $message) {
            $pdo->prepare(
                "INSERT INTO announcements
                 (title,message,target,target_id,published_at,expires_at,is_public,priority,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                $title, $message, $target, $targetId,
                $pubNow ? date('Y-m-d H:i:s') : null,
                $expires, $isPublic, $priority,
                currentUser()['id']
            ]);
            flash('success', 'Announcement "'.htmlspecialchars($title).'" '.($pubNow?'published':'saved as draft').' successfully.');
        } else {
            flash('error', 'Title and message are required.');
        }

    } elseif ($action === 'publish') {
        $id = (int)($_POST['ann_id'] ?? 0);
        // Only creator or admin can publish
        $ann = $pdo->query("SELECT created_by FROM announcements WHERE id=$id")->fetch();
        if ($ann && ($ann['created_by'] == currentUserId() || $canDelete)) {
            $pdo->prepare("UPDATE announcements SET published_at=NOW() WHERE id=?")->execute([$id]);
            flash('success', 'Announcement published.');
        } else {
            flash('error', 'You can only publish your own announcements.');
        }

    } elseif ($action === 'unpublish') {
        $id = (int)($_POST['ann_id'] ?? 0);
        $ann = $pdo->query("SELECT created_by FROM announcements WHERE id=$id")->fetch();
        if ($ann && ($ann['created_by'] == currentUserId() || $canDelete)) {
            $pdo->prepare("UPDATE announcements SET published_at=NULL WHERE id=?")->execute([$id]);
            flash('success', 'Announcement unpublished (moved to draft).');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['ann_id'] ?? 0);
        $ann = $pdo->query("SELECT created_by,title FROM announcements WHERE id=$id")->fetch();
        // Can delete: own announcements OR admin roles
        if ($ann && ($ann['created_by'] == currentUserId() || $canDelete)) {
            $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
            flash('success', 'Announcement deleted.');
        } else {
            flash('error', 'You can only delete your own announcements.');
        }
    }
    redirect(BASE_URL.'/admin/announcements.php?tab='.($_POST['tab']??'all'));
}

// ── Normal page render ────────────────────────────────────────
$pageTitle   = 'Announcements';
$activeAdmin = 'announcements';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo = db();
$tab = $_GET['tab'] ?? 'all';

// Permissions for current user
$canCreate = canAny(['comms.create_announcement','comms.view_announcements'])
    || hasRole(['principal','super_admin','sys_admin','school_admin','vice_principal',
                'registrar','teacher','class_teacher','discipline_officer','librarian','ict_officer']);
$canDeleteAny = hasRole(['principal','super_admin','sys_admin','school_admin','vice_principal']);
$myId = currentUserId();

// ── Fetch announcements ───────────────────────────────────────
$where = ['1=1'];
if ($tab === 'published') $where[] = 'a.published_at IS NOT NULL AND (a.expires_at IS NULL OR a.expires_at > NOW())';
if ($tab === 'draft')     $where[] = 'a.published_at IS NULL';
if ($tab === 'mine')      $where[] = 'a.created_by='.intval($myId);
if ($tab === 'expired')   $where[] = 'a.expires_at IS NOT NULL AND a.expires_at <= NOW()';
$wsql = implode(' AND ', $where);

try {
    $anns = $pdo->query(
        "SELECT a.*, u.name creator, u.role
         FROM announcements a
         JOIN users u ON u.id=a.created_by
         WHERE $wsql ORDER BY
           CASE WHEN a.priority='urgent' THEN 0 WHEN a.priority='high' THEN 1 ELSE 2 END,
           a.created_at DESC"
    )->fetchAll();
} catch (Throwable $e) { $anns = []; }

// Counts for tabs
try {
    $counts = $pdo->query(
        "SELECT
           COUNT(*) total,
           SUM(published_at IS NOT NULL AND (expires_at IS NULL OR expires_at > NOW())) published,
           SUM(published_at IS NULL) draft,
           SUM(created_by=$myId) mine,
           SUM(expires_at IS NOT NULL AND expires_at <= NOW()) expired
         FROM announcements"
    )->fetch();
} catch (Throwable $e) {
    $counts = ['total'=>0,'published'=>0,'draft'=>0,'mine'=>0,'expired'=>0];
}

try {
    $grades  = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
} catch (Throwable $e) { $grades = []; }

// Priority labels/colours
$priColor = ['urgent'=>'#c00200','high'=>'#d97706','normal'=>'var(--primary)','low'=>'#6b7280'];
$priLabel = ['urgent'=>'🚨 Urgent','high'=>'⚠️ High','normal'=>'📢 Normal','low'=>'💬 Low'];
?>

<!-- ── Page heading ── -->
<div class="page-heading">
  <div>
    <div class="eyebrow">Communications <span></span></div>
    <h1>Announcements</h1>
    <p>Post and manage announcements for students, parents, teachers and staff.</p>
  </div>
  <?php if ($canCreate): ?>
  <button class="button button-primary"
          onclick="document.getElementById('addAnnModal').style.display='flex'">
    + New Announcement
  </button>
  <?php else: ?>
  <div style="font-size:12px;color:var(--ink-faint);text-align:right">
    View only — contact an admin to post
  </div>
  <?php endif; ?>
</div>

<!-- ── Tabs ── -->
<div style="display:flex;gap:4px;margin-bottom:18px;border-bottom:2px solid var(--line);flex-wrap:wrap">
  <?php
  $tabs = [
    'all'       => ['All',         $counts['total']     ?? 0, ''],
    'published' => ['Published',   $counts['published'] ?? 0, 'var(--green)'],
    'draft'     => ['Drafts',      $counts['draft']     ?? 0, 'var(--warning)'],
    'mine'      => ['My Posts',    $counts['mine']      ?? 0, 'var(--primary)'],
    'expired'   => ['Expired',     $counts['expired']   ?? 0, 'var(--ink-faint)'],
  ];
  foreach ($tabs as $key => [$label, $cnt, $badgeColor]):
    $active = $tab === $key;
  ?>
  <a href="?tab=<?= $key ?>"
     style="padding:9px 16px;font-size:13px;font-weight:700;text-decoration:none;
            border-bottom:3px solid <?= $active?'var(--primary)':'transparent' ?>;
            color:<?= $active?'var(--primary)':'var(--ink-soft)' ?>;
            margin-bottom:-2px;transition:color .15s;display:flex;align-items:center;gap:5px">
    <?= $label ?>
    <?php if ($cnt > 0): ?>
    <span style="background:<?= $active?'var(--primary)':($badgeColor?:'var(--ink-faint)') ?>;
                 color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;font-weight:700">
      <?= $cnt ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Announcements list ── -->
<?php if (empty($anns)): ?>
<div style="text-align:center;padding:56px 20px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:44px;margin-bottom:12px">📢</div>
  <h3 style="font-weight:700;margin-bottom:6px">No announcements <?= $tab !== 'all' ? 'in this view' : 'yet' ?></h3>
  <p style="color:var(--ink-soft);font-size:13px">
    <?php if ($canCreate): ?>
    Click <strong>+ New Announcement</strong> to post the first one.
    <?php else: ?>
    Check back later for updates.
    <?php endif; ?>
  </p>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($anns as $ann):
    $isPub     = !empty($ann['published_at']) && (empty($ann['expires_at']) || strtotime($ann['expires_at']) > time());
    $isExpired = !empty($ann['expires_at'])   && strtotime($ann['expires_at']) <= time();
    $isDraft   = empty($ann['published_at']);
    $isMine    = $ann['created_by'] == $myId;
    $canEdit   = $isMine || $canDeleteAny;
    $pri       = $ann['priority'] ?? 'normal';
    $borderColor = $priColor[$pri] ?? 'var(--line)';
  ?>
  <div style="
      background:var(--surface);
      border:1.5px solid var(--line);
      border-left:4px solid <?= $borderColor ?>;
      border-radius:var(--radius);
      padding:16px 18px;
      <?= $isExpired ? 'opacity:.65' : '' ?>
  ">
    <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">

      <!-- ── Content ── -->
      <div style="flex:1;min-width:240px">
        <!-- Title + priority badge -->
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px">
          <strong style="font-size:14px"><?= e($ann['title']) ?></strong>
          <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;
                       background:<?= $borderColor ?>;color:#fff">
            <?= $priLabel[$pri] ?? $pri ?>
          </span>
          <!-- Status badge -->
          <?php if ($isDraft): ?>
          <span class="status pending" style="font-size:10px">Draft</span>
          <?php elseif ($isExpired): ?>
          <span class="status warning" style="font-size:10px">Expired</span>
          <?php else: ?>
          <span class="status approved" style="font-size:10px">Published</span>
          <?php endif; ?>
          <!-- Public badge -->
          <?php if ($ann['is_public']): ?>
          <span style="font-size:10px;background:#e0f2fe;color:#0369a1;padding:2px 7px;border-radius:10px;font-weight:700">🌐 Public</span>
          <?php endif; ?>
        </div>

        <!-- Message preview -->
        <div style="font-size:13px;color:var(--ink-soft);line-height:1.6;margin-bottom:8px">
          <?= nl2br(e(mb_substr($ann['message'], 0, 200))) ?>
          <?= mb_strlen($ann['message']) > 200 ? '<span style="color:var(--ink-faint)">…</span>' : '' ?>
        </div>

        <!-- Meta -->
        <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:11.5px;color:var(--ink-faint)">
          <span>👤 <?= e($ann['creator']) ?></span>
          <span>🎯 <?= e(ucfirst($ann['target'] ?? 'all')) ?></span>
          <?php if ($ann['published_at']): ?>
          <span>📅 Published <?= date('d M Y H:i', strtotime($ann['published_at'])) ?></span>
          <?php else: ?>
          <span>📝 Created <?= date('d M Y', strtotime($ann['created_at'])) ?></span>
          <?php endif; ?>
          <?php if ($ann['expires_at']): ?>
          <span style="color:<?= $isExpired?'var(--error)':'var(--ink-faint)' ?>">
            ⏰ Expires <?= date('d M Y', strtotime($ann['expires_at'])) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Actions ── -->
      <?php if ($canEdit): ?>
      <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
        <?php if ($isDraft): ?>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="publish"/>
          <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>"/>
          <input type="hidden" name="tab"    value="<?= e($tab) ?>"/>
          <button type="submit" class="button button-success button-sm" style="width:100%">
            ▶ Publish
          </button>
        </form>
        <?php elseif (!$isExpired): ?>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="unpublish"/>
          <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>"/>
          <input type="hidden" name="tab"    value="<?= e($tab) ?>"/>
          <button type="submit" class="button button-secondary button-sm" style="width:100%">
            ⏸ Unpublish
          </button>
        </form>
        <?php endif; ?>
        <!-- Expand to read full -->
        <button class="button button-secondary button-sm"
                onclick="toggleMsg('msg<?= $ann['id'] ?>')" style="width:100%">
          👁 Read
        </button>
        <form method="post" onsubmit="return confirm('Delete this announcement?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete"/>
          <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>"/>
          <input type="hidden" name="tab"    value="<?= e($tab) ?>"/>
          <button type="submit" class="button button-secondary button-sm"
                  style="width:100%;color:var(--error)">
            🗑 Delete
          </button>
        </form>
      </div>
      <?php else: ?>
      <!-- View-only users can still read full -->
      <button class="button button-secondary button-sm"
              onclick="toggleMsg('msg<?= $ann['id'] ?>')"
              style="flex-shrink:0;align-self:flex-start">
        👁 Read
      </button>
      <?php endif; ?>
    </div>

    <!-- Full message (hidden by default) -->
    <div id="msg<?= $ann['id'] ?>" style="display:none;margin-top:12px;padding:12px;background:var(--bg);border-radius:6px;font-size:13px;line-height:1.7;white-space:pre-wrap;color:var(--ink)">
      <?= e($ann['message']) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ── Add Announcement Modal ── -->
<?php if ($canCreate): ?>
<div id="addAnnModal" style="
    display:none;position:fixed;inset:0;
    background:rgba(26,26,31,.55);
    z-index:200;align-items:center;
    justify-content:center;padding:20px
">
  <div style="
      background:var(--surface);
      border-radius:var(--radius-lg);
      width:100%;max-width:560px;
      box-shadow:var(--shadow-lg);
      padding:28px;max-height:92vh;
      overflow-y:auto;
  ">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <h3 style="font-size:16px;font-weight:800">📢 New Announcement</h3>
      <button onclick="document.getElementById('addAnnModal').style.display='none'"
              style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--ink-soft)">✕</button>
    </div>

    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add"/>
      <input type="hidden" name="tab"    value="<?= e($tab) ?>"/>

      <!-- Title -->
      <div class="form-group" style="margin-bottom:12px">
        <label style="font-weight:700;font-size:13px">Title *
          <input name="title" required maxlength="200"
                 placeholder="Announcement title…" style="margin-top:4px"/>
        </label>
      </div>

      <!-- Message -->
      <div class="form-group" style="margin-bottom:12px">
        <label style="font-weight:700;font-size:13px">Message *
          <textarea name="message" rows="5" required
                    placeholder="Type your announcement here…"
                    style="margin-top:4px;font-size:13px;line-height:1.6"></textarea>
        </label>
      </div>

      <!-- Target + Priority -->
      <div class="form-row" style="margin-bottom:12px">
        <div class="form-group">
          <label style="font-weight:700;font-size:13px">Target Audience
            <select name="target" style="margin-top:4px">
              <option value="all">🌍 Everyone</option>
              <option value="students">🎓 Students</option>
              <option value="parents">👨‍👩‍👧 Parents</option>
              <option value="teachers">👩‍🏫 Teachers</option>
              <option value="staff">👥 Staff only</option>
            </select>
          </label>
        </div>
        <div class="form-group">
          <label style="font-weight:700;font-size:13px">Priority
            <select name="priority" style="margin-top:4px">
              <option value="normal">📢 Normal</option>
              <option value="high">⚠️ High</option>
              <option value="urgent">🚨 Urgent</option>
              <option value="low">💬 Low</option>
            </select>
          </label>
        </div>
      </div>

      <!-- Expiry + Public -->
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group">
          <label style="font-weight:700;font-size:13px">Expiry Date
            <span style="font-weight:400;color:var(--ink-faint)">(optional)</span>
            <input type="date" name="expires_at" style="margin-top:4px"
                   min="<?= date('Y-m-d') ?>"/>
          </label>
        </div>
        <div class="form-group">
          <div style="font-weight:700;font-size:13px;margin-bottom:8px">Visibility</div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;
                        padding:8px 10px;border:1.5px solid var(--line);border-radius:7px;background:var(--bg)">
            <input type="checkbox" name="is_public" value="1" style="width:16px;height:16px"/>
            🌐 Show on public website
          </label>
        </div>
      </div>

      <!-- Publication -->
      <div style="background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:8px;
                  padding:14px 16px;margin-bottom:18px">
        <div style="font-size:11.5px;font-weight:800;color:#0369a1;
                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
          📅 Publication Setting
        </div>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
          <input type="checkbox" name="publish_now" value="1" checked
                 style="width:16px;height:16px;margin-top:2px;flex-shrink:0"/>
          <span style="font-size:13px;color:#0c4a6e;line-height:1.5">
            <strong>Publish immediately</strong><br>
            <span style="font-size:12px;color:#0369a1;font-weight:400">
              Uncheck to save as draft and publish later
            </span>
          </span>
        </label>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="button button-secondary"
                onclick="document.getElementById('addAnnModal').style.display='none'">
          Cancel
        </button>
        <button type="submit" class="button button-primary">
          📢 Post Announcement
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// Toggle full message display
function toggleMsg(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
// Close modal on backdrop click
document.getElementById('addAnnModal')?.addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const m = document.getElementById('addAnnModal');
    if (m) m.style.display = 'none';
  }
});
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
