<?php
$pageTitle='Events'; $activeAdmin='events';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $action=$_POST['action']??'';
    if($action==='add'){
        $pdo->prepare("INSERT INTO events (title,description,event_date,end_date,start_time,end_time,venue,category,is_public,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$_POST['title'],trim($_POST['description']??'')?:null,$_POST['event_date'],$_POST['end_date']?:null,$_POST['start_time']?:null,$_POST['end_time']?:null,trim($_POST['venue']??'')?:null,$_POST['category']??'general',$_POST['is_public']??0,currentUser()['id']]);
        flash('success','Event created.');
    } elseif($action==='delete'){$pdo->prepare("DELETE FROM events WHERE id=?")->execute([(int)$_POST['event_id']]);flash('success','Deleted.');}
    redirect(BASE_URL.'/admin/events_admin.php');
}
$events=$pdo->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 50")->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">Communications <span></span></div><h1>Events</h1></div><button class="button button-primary" onclick="document.getElementById('addEvtModal').style.display='flex'">+ New Event</button></div>
<div class="table-wrap"><table>
  <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Category</th><th>Public</th><th>Actions</th></tr></thead>
  <tbody><?php if(empty($events)):?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--ink-faint)">No events yet.</td></tr>
  <?php else: foreach($events as $ev):?>
  <tr>
    <td><strong><?=e($ev['title'])?></strong><?=$ev['description']?'<div style="font-size:11.5px;color:var(--ink-faint)">'.e(mb_substr($ev['description'],0,60)).'</div>':''?></td>
    <td class="muted"><?=date('M d, Y',strtotime($ev['event_date']))?><?=$ev['start_time']?'<br><span style="font-size:11px">'.date('g:ia',strtotime($ev['start_time'])).'</span>':''?></td>
    <td class="muted"><?=e($ev['venue']??'—')?></td>
    <td><span class="status new-s"><?=e(ucfirst($ev['category']))?></span></td>
    <td><?=$ev['is_public']?'<span class="status approved">Yes</span>':'<span class="status pending">No</span>'?></td>
    <td><form method="post" onsubmit="return confirm('Delete?')" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"/><input type="hidden" name="event_id" value="<?=$ev['id']?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">Delete</button></form></td>
  </tr>
  <?php endforeach; endif;?></tbody>
</table></div>
<div id="addEvtModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:520px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">New Event</h3>
    <form method="post"><?=csrfField()?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Title *<input name="title" required/></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Description<textarea name="description" rows="2"></textarea></label></div></div>
      <div class="form-row"><div class="form-group"><label>Event Date *<input type="date" name="event_date" required/></label></div><div class="form-group"><label>End Date<input type="date" name="end_date"/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Start Time<input type="time" name="start_time"/></label></div><div class="form-group"><label>End Time<input type="time" name="end_time"/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Venue<input name="venue"/></label></div><div class="form-group"><label>Category<select name="category"><option value="academic">Academic</option><option value="community">Community</option><option value="sports">Sports</option><option value="cultural">Cultural</option><option value="general" selected>General</option></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="is_public" value="1" checked/> Show on public website</label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px"><button type="button" class="button button-secondary" onclick="document.getElementById('addEvtModal').style.display='none'">Cancel</button><button type="submit" class="button button-primary">Create →</button></div>
    </form>
  </div>
</div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
