<?php
$pageTitle='Guardians'; $activeAdmin='guardians';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $action=$_POST['action']??'';
    if($action==='add'){
        $pdo->prepare("INSERT INTO guardians (first_name,last_name,relationship,phone,email,address) VALUES (?,?,?,?,?,?)")->execute([trim($_POST['first_name']),trim($_POST['last_name']),$_POST['relationship']??'Guardian',trim($_POST['phone']),trim($_POST['email']??'')?:null,trim($_POST['address']??'')?:null]);
        flash('success','Guardian added.');
    } elseif($action==='link'){
        $gid=(int)($_POST['guardian_id']??0); $sid=(int)($_POST['student_id']??0);
        if($gid&&$sid){ try{$pdo->prepare("INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary) VALUES (?,?,?)")->execute([$sid,$gid,$_POST['is_primary']??0]);flash('success','Linked.');}catch(PDOException $e){flash('error','Already linked.');} }
    }
    redirect(BASE_URL.'/admin/guardians.php');
}

$guardians=$pdo->query("SELECT g.*,(SELECT COUNT(*) FROM student_guardians sg WHERE sg.guardian_id=g.id) children FROM guardians g ORDER BY g.first_name")->fetchAll();
$students=$pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">Students <span></span></div><h1>Guardians</h1></div><button class="button button-primary" onclick="document.getElementById('addGModal').style.display='flex'">+ Add Guardian</button></div>
<div class="table-wrap"><table>
  <thead><tr><th>Guardian</th><th>Relationship</th><th>Phone</th><th>Children Linked</th><th>Actions</th></tr></thead>
  <tbody><?php if(empty($guardians)):?><tr><td colspan="5" style="text-align:center;padding:28px;color:var(--ink-faint)">No guardians yet.</td></tr>
  <?php else: foreach($guardians as $g):?><tr>
    <td><strong><?=e($g['first_name'].' '.$g['last_name'])?></strong><?=$g['email']?'<div style="font-size:11.5px;color:var(--ink-faint)">'.e($g['email']).'</div>':''?></td>
    <td><?=e($g['relationship'])?></td>
    <td class="muted"><?=e($g['phone'])?></td>
    <td><?=(int)$g['children']?></td>
    <td><button class="filter-button button-sm" onclick="document.getElementById('linkModal_<?=$g['id']?>').style.display='flex'">Link Student</button>
      <div id="linkModal_<?=$g['id']?>" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
        <div style="background:var(--surface);border-radius:var(--radius-lg);padding:24px;max-width:400px;width:100%;box-shadow:var(--shadow-lg)">
          <h3 style="margin-bottom:14px">Link <?=e($g['first_name'])?> to Student</h3>
          <form method="post"><?=csrfField()?><input type="hidden" name="action" value="link"/><input type="hidden" name="guardian_id" value="<?=$g['id']?>"/>
            <div class="form-group" style="margin-bottom:12px"><label>Student<select name="student_id" required><option value="">Select…</option><?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_id'].')')?></option><?php endforeach;?></select></label></div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px"><input type="checkbox" name="is_primary" value="1"/> Primary guardian</label>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="button button-secondary button-sm" onclick="document.getElementById('linkModal_<?=$g['id']?>').style.display='none'">Cancel</button><button type="submit" class="button button-primary button-sm">Link →</button></div>
          </form>
        </div>
      </div>
    </td>
  </tr><?php endforeach; endif;?></tbody>
</table></div>
<div id="addGModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:460px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Guardian</h3>
    <form method="post"><?=csrfField()?><input type="hidden" name="action" value="add"/>
      <div class="form-row"><div class="form-group"><label>First Name *<input name="first_name" required/></label></div><div class="form-group"><label>Last Name *<input name="last_name" required/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Relationship<select name="relationship"><option>Mother</option><option>Father</option><option>Aunt/Uncle</option><option>Guardian</option></select></label></div><div class="form-group"><label>Phone *<input name="phone" required/></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Email<input type="email" name="email"/></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px"><button type="button" class="button button-secondary" onclick="document.getElementById('addGModal').style.display='none'">Cancel</button><button type="submit" class="button button-primary">Add →</button></div>
    </form>
  </div>
</div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
