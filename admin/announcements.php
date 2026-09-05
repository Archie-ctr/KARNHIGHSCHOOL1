<?php
$pageTitle='Announcements'; $activeAdmin='announcements';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $action=$_POST['action']??'';
    if($action==='add'){
        $pdo->prepare("INSERT INTO announcements (title,message,target,target_id,published_at,expires_at,is_public,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$_POST['title'],$_POST['message'],$_POST['target']??'all',$_POST['target_id']?:null,$_POST['publish_now']?date('Y-m-d H:i:s'):null,$_POST['expires_at']?:null,$_POST['is_public']??0,currentUser()['id']]);
        flash('success','Announcement created.');
    } elseif($action==='delete'){$pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([(int)($_POST['ann_id']??0)]);flash('success','Deleted.');}
    elseif($action==='publish'){$pdo->prepare("UPDATE announcements SET published_at=NOW() WHERE id=?")->execute([(int)($_POST['ann_id']??0)]);flash('success','Published.');}
    redirect(BASE_URL.'/admin/announcements.php');
}

$anns=$pdo->query("SELECT a.*,u.name creator FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC")->fetchAll();
$grades=$pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$classes=$pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([currentAcademicYearId()])?$pdo->query("SELECT id,name FROM classes WHERE academic_year_id=".currentAcademicYearId()." ORDER BY name")->fetchAll():[];
?>
<div class="page-heading"><div><div class="eyebrow">Communications <span></span></div><h1>Announcements</h1></div><button class="button button-primary" onclick="document.getElementById('addAnnModal').style.display='flex'">+ New Announcement</button></div>
<div class="table-wrap"><table>
  <thead><tr><th>Title</th><th>Target</th><th>Published</th><th>Expires</th><th>Public</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if(empty($anns)):?><tr><td colspan="6" style="text-align:center;padding:32px;color:var(--ink-faint)">No announcements yet.</td></tr>
    <?php else: foreach($anns as $a):?>
    <tr>
      <td><strong><?=e($a['title'])?></strong><div style="font-size:11.5px;color:var(--ink-faint)"><?=e(mb_substr($a['message'],0,80)).'…'?></div></td>
      <td><span class="status new-s"><?=e(ucfirst($a['target']))?></span></td>
      <td class="muted"><?=$a['published_at']?date('M d, Y',strtotime($a['published_at'])):'<em>Draft</em>'?></td>
      <td class="muted"><?=$a['expires_at']?date('M d, Y',strtotime($a['expires_at'])):'—'?></td>
      <td><?=$a['is_public']?'<span class="status approved">Yes</span>':'<span class="status pending">No</span>'?></td>
      <td><div style="display:flex;gap:5px">
        <?php if(!$a['published_at']):?><form method="post" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="publish"/><input type="hidden" name="ann_id" value="<?=$a['id']?>"/><button type="submit" class="filter-button button-sm" style="color:var(--green)">Publish</button></form><?php endif;?>
        <form method="post" onsubmit="return confirm('Delete?')" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"/><input type="hidden" name="ann_id" value="<?=$a['id']?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">Delete</button></form>
      </div></td>
    </tr>
    <?php endforeach; endif;?>
  </tbody>
</table></div>

<div id="addAnnModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:540px;box-shadow:var(--shadow-lg);padding:28px;max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:18px">New Announcement</h3>
    <form method="post">
      <?=csrfField()?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Title *<input name="title" required/></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Message *<textarea name="message" rows="4" required></textarea></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Target<select name="target"><option value="all">Everyone</option><option value="students">Students</option><option value="parents">Parents</option><option value="teachers">Teachers</option></select></label></div>
        <div class="form-group"><label>Expiry Date<input type="date" name="expires_at"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="is_public" value="1"/> Show on public website</label></div>
        <div class="form-group"><label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="publish_now" value="1" checked/> Publish immediately</label></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px"><button type="button" class="button button-secondary" onclick="document.getElementById('addAnnModal').style.display='none'">Cancel</button><button type="submit" class="button button-primary">Create →</button></div>
    </form>
  </div>
</div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
