<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','school_admin','principal']);
$pdo = db();

// Toggle individual permission for a role
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action  = $_POST['action'] ?? '';
    $roleId  = (int)($_POST['role_id'] ?? 0);
    $permId  = (int)($_POST['perm_id'] ?? 0);

    if ($action==='toggle' && $roleId && $permId) {
        $exists = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id=? AND permission_id=?")->execute([$roleId,$permId])
                      ? $pdo->query("SELECT COUNT(*) FROM role_permissions WHERE role_id=$roleId AND permission_id=$permId")->fetchColumn()
                      : 0;
        if ($exists) {
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id=? AND permission_id=?")->execute([$roleId,$permId]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id,permission_id) VALUES (?,?)")->execute([$roleId,$permId]);
        }
        auditLog('toggle_permission','roles','role',$roleId,'','perm_id:'.$permId);
        json_out(['ok'=>true,'granted'=>!$exists]);
    }
    redirect(BASE_URL.'/admin/roles.php');
}

$roles   = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$perms   = $pdo->query("SELECT * FROM permissions ORDER BY module,name")->fetchAll();
$modules = array_unique(array_column($perms,'module'));

// Build role→permission lookup
$granted = [];
$rp=$pdo->query("SELECT role_id,permission_id FROM role_permissions");
foreach ($rp->fetchAll() as $row) {
    $granted[$row['role_id']][$row['permission_id']] = true;
}

$selRole = (int)($_GET['role_id'] ?? $roles[0]['id'] ?? 0);
$activeRole = null;
foreach ($roles as $r) if ($r['id']==$selRole) { $activeRole=$r; break; }

// User count per role
$userCounts = $pdo->query("SELECT role_id,COUNT(*) cnt FROM users GROUP BY role_id")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle   = 'Roles & Permissions';
$activeAdmin = 'users';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System <span></span></div>
    <h1>Roles &amp; Permissions</h1>
    <p>Manage what each role can access in the system.</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start">

  <!-- Role list -->
  <div class="panel" style="overflow:hidden">
    <div class="panel-heading"><div><h3>System Roles</h3><p>12 roles defined</p></div></div>
    <div style="padding:0 0 12px">
      <?php foreach ($roles as $r):
        $isActive = $r['id']==$selRole;
        $cnt = (int)($userCounts[$r['id']]??0);
      ?>
      <a href="?role_id=<?=$r['id']?>"
         style="display:flex;align-items:center;justify-content:space-between;padding:11px 20px;text-decoration:none;font-size:13.5px;font-weight:600;color:<?=$isActive?'#fff':'var(--ink)'?>;background:<?=$isActive?'var(--primary)':'transparent'?>;transition:background .15s"
         onmouseover="if(!this.classList.contains('active'))this.style.background='var(--bg)'"
         onmouseout="this.style.background='<?=$isActive?'var(--primary)':'transparent'?>'">
        <span><?=e($r['label'])?></span>
        <span style="background:<?=$isActive?'rgba(255,255,255,.2)':'var(--bg-soft)'?>;color:<?=$isActive?'#fff':'var(--ink-soft)'?>;padding:2px 9px;border-radius:12px;font-size:11px"><?=$cnt?> user<?=$cnt!=1?'s':''?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Permission matrix for selected role -->
  <?php if ($activeRole): ?>
  <div>
    <div class="form-section">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--line-soft);flex-wrap:wrap;gap:10px">
        <div>
          <h3 style="font-size:18px;font-weight:800"><?=e($activeRole['label'])?></h3>
          <p style="color:var(--ink-soft);font-size:13.5px;margin-top:4px"><?=e($activeRole['description'])?></p>
        </div>
        <div style="text-align:right">
          <div style="font-size:22px;font-weight:800;color:var(--primary)"><?=count(array_filter($perms,fn($p)=>!empty($granted[$selRole][$p['id']])))?></div>
          <div style="font-size:12px;color:var(--ink-faint)">of <?=count($perms)?> permissions</div>
        </div>
      </div>

      <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">Click a permission to toggle it on or off for this role.</p>

      <?php foreach ($modules as $module): ?>
      <div style="margin-bottom:20px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px"><?=e(ucfirst($module))?></div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach ($perms as $p): if ($p['module']!==$module) continue;
            $active = !empty($granted[$selRole][$p['id']]);
          ?>
          <button
            onclick="togglePerm(<?=$selRole?>,<?=$p['id']?>,this)"
            data-active="<?=$active?'1':'0'?>"
            style="padding:6px 13px;border-radius:20px;font-size:12.5px;font-weight:600;border:1.5px solid <?=$active?'var(--green)':'var(--line)'?>;background:<?=$active?'var(--green-soft)':'var(--bg)'?>;color:<?=$active?'var(--green)':'var(--ink-faint)'?>;cursor:pointer;transition:all .2s"
            title="<?=e($p['name'])?>">
            <?=$active?'✓ ':''?><?=e($p['label'])?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Users with this role -->
    <?php
    $roleUsers = $pdo->prepare("SELECT id,name,email,phone,is_active FROM users WHERE role_id=? ORDER BY name");
    $roleUsers->execute([$selRole]); $roleUsers=$roleUsers->fetchAll();
    ?>
    <?php if (!empty($roleUsers)): ?>
    <div class="form-section">
      <div class="form-section-title">Users with this role (<?=count($roleUsers)?>)</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email / Phone</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($roleUsers as $u):
              $ini=strtoupper(implode('',array_map(fn($w)=>$w[0],array_slice(explode(' ',$u['name']),0,2))));
            ?>
            <tr>
              <td><div class="person"><div class="avatar-sm"><?=e($ini)?></div><strong><?=e($u['name'])?></strong></div></td>
              <td class="muted"><?=e($u['email']??$u['phone']??'—')?></td>
              <td><?=$u['is_active']?statusBadge('Active'):statusBadge('Inactive')?></td>
              <td><a href="<?=BASE_URL?>/admin/users.php" class="filter-button button-sm">Manage</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
const csrfToken = '<?= csrfToken() ?>';
async function togglePerm(roleId, permId, btn) {
  btn.style.opacity = '0.5';
  try {
    const fd = new FormData();
    fd.append('action','toggle');
    fd.append('role_id', roleId);
    fd.append('perm_id', permId);
    fd.append('csrf_token', csrfToken);
    const res  = await fetch('<?=BASE_URL?>/admin/roles.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      const granted = data.granted;
      btn.style.border  = granted ? '1.5px solid var(--green)'  : '1.5px solid var(--line)';
      btn.style.background = granted ? 'var(--green-soft)' : 'var(--bg)';
      btn.style.color   = granted ? 'var(--green)' : 'var(--ink-faint)';
      btn.dataset.active = granted ? '1' : '0';
      btn.textContent   = (granted ? '✓ ' : '') + btn.textContent.replace('✓ ','');
      // Update counter
      const total = document.querySelectorAll('[data-active="1"]').length + (granted?0:-1) + (granted?1:0);
    }
  } catch(e) { alert('Failed to update permission.'); }
  btn.style.opacity = '1';
}
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
