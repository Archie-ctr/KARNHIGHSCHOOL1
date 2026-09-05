<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','school_admin','principal']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action==='add') {
        $name   = trim($_POST['name']    ?? '');
        $email  = trim($_POST['email']   ?? '');
        $phone  = trim($_POST['phone']   ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $pass   = trim($_POST['password'] ?? '');
        if ($name && ($email||$phone) && $roleId && strlen($pass)>=4) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            try {
                $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role_id) VALUES (?,?,?,?,?)")
                   ->execute([$name,$email?:null,$phone?:null,$hash,$roleId]);
                auditLog('create','users','user',(int)$pdo->lastInsertId(),'','Created: '.$name);
                flash('success','User created successfully.');
            } catch (PDOException $e) {
                flash('error','Email or phone already in use.');
            }
        } else {
            flash('error','Please fill in all required fields. Password must be at least 4 characters.');
        }
    } elseif ($action==='toggle') {
        $id=(int)($_POST['user_id']??0);
        if ($id) {
            $pdo->prepare("UPDATE users SET is_active=NOT is_active WHERE id=?")->execute([$id]);
            auditLog('toggle_active','users','user',$id);
            flash('success','User status updated.');
        }
    } elseif ($action==='reset_pw') {
        $id=(int)($_POST['user_id']??0);
        $pw=trim($_POST['new_password']??'');
        if ($id && strlen($pw)>=4) {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pw,PASSWORD_BCRYPT),$id]);
            auditLog('reset_password','users','user',$id);
            flash('success','Password updated.');
        } else {
            flash('error','Password must be at least 4 characters.');
        }
    } elseif ($action==='change_role') {
        $id=(int)($_POST['user_id']??0);
        $rid=(int)($_POST['role_id']??0);
        if ($id && $rid) {
            $old=$pdo->query("SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=$id")->fetchColumn();
            $pdo->prepare("UPDATE users SET role_id=? WHERE id=?")->execute([$rid,$id]);
            auditLog('change_role','users','user',$id,$old,'role_id:'.$rid);
            flash('success','Role updated.');
        }
    } elseif ($action==='delete') {
        $id=(int)($_POST['user_id']??0);
        if ($id && $id !== (int)(currentUser()['id']??0)) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            auditLog('delete','users','user',$id);
            flash('success','User deleted.');
        }
    }
    redirect(BASE_URL.'/admin/users.php?'.http_build_query(array_filter(['q'=>$_GET['q']??'','role_id'=>$_GET['role_id']??''])));
}

// Filters
$q      = trim($_GET['q']      ?? '');
$roleF  = (int)($_GET['role_id'] ?? 0);
$page   = max(1,(int)($_GET['page']??1));
$per    = 25;

$where=[]; $params=[];
if ($q)     { $where[]='(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like); }
if ($roleF) { $where[]='u.role_id=?'; $params[]=$roleF; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt=$pdo->prepare("SELECT COUNT(*) FROM users u $wsql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg=paginate($total,$per,$page);
$rows=$pdo->prepare("SELECT u.*,r.label role_label,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id $wsql ORDER BY r.id,u.name LIMIT $per OFFSET {$pg['offset']}");
$rows->execute($params); $users=$rows->fetchAll();

$roles = $pdo->query("SELECT id,name,label,(SELECT COUNT(*) FROM users WHERE role_id=roles.id) cnt FROM roles ORDER BY id")->fetchAll();

$pageTitle   = 'Users & Roles';
$activeAdmin = 'users';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System <span></span></div>
    <h1>Users &amp; Roles</h1>
    <p>Manage accounts for all <?= $total ?> system users.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/admin/roles.php" class="button button-secondary">🔑 Manage Permissions</a>
    <button class="button button-primary" onclick="document.getElementById('addUserModal').style.display='flex'">+ Add User</button>
  </div>
</div>

<!-- Role summary cards -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <a href="?role_id=0&q=<?=urlencode($q)?>" class="filter-button" style="<?=!$roleF?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">
    All (<?= array_sum(array_column($roles,'cnt')) ?>)
  </a>
  <?php foreach ($roles as $r): ?>
  <a href="?role_id=<?=$r['id']?>&q=<?=urlencode($q)?>" class="filter-button"
     style="<?=$roleF==$r['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">
    <?=e($r['label'])?> (<?=$r['cnt']?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="list-content">
  <!-- Search -->
  <form method="get" class="filter-row">
    <?php if ($roleF): ?><input type="hidden" name="role_id" value="<?=$roleF?>"/><?php endif; ?>
    <div class="table-search">🔍<input type="search" name="q" placeholder="Name, email, phone…" value="<?=e($q)?>"/></div>
    <button type="submit" class="button button-primary button-sm">Search</button>
    <?php if ($q||$roleF): ?><a href="<?=BASE_URL?>/admin/users.php" class="filter-button">Clear</a><?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Email / Phone</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="6" style="text-align:center;padding:36px;color:var(--ink-faint)">No users found.</td></tr>
        <?php else: ?>
        <?php foreach ($users as $u):
          $ini=strtoupper(implode('',array_map(fn($w)=>strtoupper($w[0]),array_slice(explode(' ',$u['name']),0,2))));
          $isMe= (int)$u['id'] === (int)(currentUser()['id']??0);
          $roleCls = match($u['role_name']) {
            'sys_admin','school_admin' => 'warning',
            'principal','vice_principal' => 'approved',
            'student' => 'new-s',
            'parent'  => 'pending',
            default   => 'new-s',
          };
        ?>
        <tr>
          <td>
            <div class="person">
              <div class="avatar-sm"><?=e($ini)?></div>
              <div>
                <strong><?=e($u['name'])?></strong>
                <?php if ($isMe): ?><span style="font-size:10px;color:var(--primary);font-weight:700"> (you)</span><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="muted" style="font-size:13px"><?=e($u['email']??$u['phone']??'—')?></td>
          <td><span class="status <?=$roleCls?>"><?=e($u['role_label']??$u['role_name']??'—')?></span></td>
          <td><?=$u['is_active']?statusBadge('Active'):statusBadge('Inactive')?></td>
          <td class="muted" style="font-size:12px"><?=$u['last_login']?date('M d, Y H:i',strtotime($u['last_login'])):'Never'?></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <!-- Change role -->
              <?php if (!$isMe): ?>
              <form method="post" style="display:inline">
                <?=csrfField()?>
                <input type="hidden" name="action"  value="change_role"/>
                <input type="hidden" name="user_id" value="<?=$u['id']?>"/>
                <select name="role_id" class="filter-button" style="padding:5px 8px;font-size:12px" onchange="this.form.submit()">
                  <?php foreach ($roles as $r): ?>
                  <option value="<?=$r['id']?>" <?=$u['role_id']==$r['id']?'selected':''?>><?=e($r['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php endif; ?>

              <!-- Reset password inline -->
              <button class="filter-button button-sm"
                      onclick="document.getElementById('pwForm_<?=$u['id']?>').style.display='block'">
                🔑 Reset PW
              </button>
              <div id="pwForm_<?=$u['id']?>" style="display:none;margin-top:6px">
                <form method="post" style="display:flex;gap:6px;align-items:center">
                  <?=csrfField()?>
                  <input type="hidden" name="action"  value="reset_pw"/>
                  <input type="hidden" name="user_id" value="<?=$u['id']?>"/>
                  <input type="password" name="new_password" placeholder="New password (min 4)" minlength="4"
                         style="padding:5px 8px;border:1px solid var(--line);border-radius:6px;font-size:13px;width:160px"/>
                  <button type="submit" class="button button-primary button-sm">Set</button>
                  <button type="button" class="button button-secondary button-sm"
                          onclick="document.getElementById('pwForm_<?=$u['id']?>').style.display='none'">✕</button>
                </form>
              </div>

              <!-- Toggle active -->
              <?php if (!$isMe): ?>
              <form method="post" style="display:inline">
                <?=csrfField()?>
                <input type="hidden" name="action"  value="toggle"/>
                <input type="hidden" name="user_id" value="<?=$u['id']?>"/>
                <button type="submit" class="filter-button button-sm" style="color:<?=$u['is_active']?'var(--warning)':'var(--green)'?>">
                  <?=$u['is_active']?'Deactivate':'Activate'?>
                </button>
              </form>

              <!-- Delete -->
              <form method="post" onsubmit="return confirm('Delete user <?=e(addslashes($u['name']))?> permanently?')" style="display:inline">
                <?=csrfField()?>
                <input type="hidden" name="action"  value="delete"/>
                <input type="hidden" name="user_id" value="<?=$u['id']?>"/>
                <button type="submit" class="filter-button button-sm" style="color:var(--error)">✕</button>
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
    <?php if ($pg['page']>1): ?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>&role_id=<?=$roleF?>">&laquo;</a><?php endif; ?>
    <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++): ?>
      <?php if($p===$pg['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?page=<?=$p?>&q=<?=urlencode($q)?>&role_id=<?=$roleF?>"><?=$p?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pg['page']<$pg['pages']): ?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>&role_id=<?=$roleF?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:500px;box-shadow:var(--shadow-lg);padding:28px">
    <div class="modal-head" style="padding:0 0 16px;border-bottom:1px solid var(--line-soft)">
      <div><div class="eyebrow">New User <span></span></div><h2 style="font-size:20px;margin-top:6px">Add User</h2></div>
      <button class="modal-close" onclick="document.getElementById('addUserModal').style.display='none'">&times;</button>
    </div>
    <form method="post" style="margin-top:18px">
      <?=csrfField()?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Full Name *<input name="name" required placeholder="e.g. John Doe"/></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Email<input type="email" name="email" placeholder="you@karnhighschool.edu.lr"/></label></div>
        <div class="form-group"><label>Phone<input name="phone" placeholder="+231 …"/></label></div>
      </div>
      <div class="form-row full">
        <div class="form-group">
          <label>Role *
            <select name="role_id" required>
              <option value="">Select role…</option>
              <?php foreach ($roles as $r): ?>
              <option value="<?=$r['id']?>"><?=e($r['label'])?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </div>
      <div class="form-row full">
        <div class="form-group"><label>Password * (min 4 characters)<input type="password" name="password" required minlength="4" placeholder="Enter password"/></label></div>
      </div>
      <div style="background:var(--blue-soft);border-radius:var(--radius-sm);padding:10px 14px;font-size:12.5px;color:var(--blue);margin-bottom:16px">
        💡 After creating the user, go to <strong>Roles &amp; Permissions</strong> to review what this role can access.
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Create User →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
