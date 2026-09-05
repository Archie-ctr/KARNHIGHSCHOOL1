<?php
$pageTitle='Student Documents'; $activeAdmin='documents';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['registrar','principal','super_admin']);
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    if($_POST['action']==='upload'){
        $stdId=(int)($_POST['student_id']??0);
        if($stdId&&!empty($_FILES['doc']['name'])){
            $path=uploadFile($_FILES['doc'],'student_docs/'.$stdId);
            if($path){
                $pdo->prepare("INSERT INTO student_documents (student_id,doc_type,file_name,file_path,file_size,mime_type,uploaded_by) VALUES (?,?,?,?,?,?,?)")->execute([$stdId,$_POST['doc_type']??'other',$_FILES['doc']['name'],$path,$_FILES['doc']['size'],$_FILES['doc']['type'],currentUser()['id']]);
                flash('success','Document uploaded.');
            } else { flash('error','Upload failed. Check file type and size.'); }
        }
    } elseif($_POST['action']==='delete'){
        $id=(int)($_POST['doc_id']??0);
        if($id){
            $doc=$pdo->query("SELECT file_path FROM student_documents WHERE id=$id")->fetch();
            $pdo->prepare("DELETE FROM student_documents WHERE id=?")->execute([$id]);
            if($doc) @unlink(UPLOAD_DIR.'/'.$doc['file_path']);
            flash('success','Document deleted.');
        }
    }
    redirect(BASE_URL.'/admin/documents.php?student_id='.($_POST['student_id']??''));
}

$stdId=(int)($_GET['student_id']??0);
$students=$pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
$student=null; $docs=[];
if($stdId){
    $student=$pdo->query("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.id=$stdId")->fetch();
    $docs=$pdo->query("SELECT * FROM student_documents WHERE student_id=$stdId ORDER BY created_at DESC")->fetchAll();
}
?>
<div class="page-heading"><div><div class="eyebrow">Students <span></span></div><h1>Student Documents</h1></div></div>
<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="student_id" class="filter-button" onchange="this.form.submit()" style="min-width:220px">
    <option value="">Select student…</option>
    <?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=$stdId==$s['id']?'selected':''?>><?=e($s['name'].' ('.$s['student_id'].')')?></option><?php endforeach;?>
  </select>
</form>
<?php if($student):?>
<div class="form-section" style="margin-bottom:20px">
  <div class="form-section-title">Upload Document for <?=e($student['first_name'].' '.$student['last_name'])?></div>
  <form method="post" enctype="multipart/form-data">
    <?=csrfField()?><input type="hidden" name="action" value="upload"/><input type="hidden" name="student_id" value="<?=$stdId?>"/>
    <div class="form-row">
      <div class="form-group"><label>Document Type<select name="doc_type"><option value="birth_certificate">Birth Certificate</option><option value="report_card">Report Card</option><option value="passport_photo">Passport Photo</option><option value="other">Other</option></select></label></div>
      <div class="form-group"><label>File (PDF/JPG/PNG, max 5MB)<input type="file" name="doc" required accept=".pdf,.jpg,.jpeg,.png"/></label></div>
    </div>
    <button type="submit" class="button button-primary button-sm">Upload →</button>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead>
  <tbody><?php if(empty($docs)):?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--ink-faint)">No documents uploaded.</td></tr>
  <?php else: foreach($docs as $d):?>
  <tr>
    <td><strong><?=e($d['file_name'])?></strong></td>
    <td><span class="status new-s"><?=e(str_replace('_',' ',ucfirst($d['doc_type'])))?></span></td>
    <td class="muted"><?=$d['file_size']?round($d['file_size']/1024,1).' KB':'—'?></td>
    <td class="muted"><?=date('M d, Y',strtotime($d['created_at']))?></td>
    <td><div style="display:flex;gap:6px">
      <a href="<?=BASE_URL?>/uploads/<?=e($d['file_path'])?>" class="filter-button button-sm" target="_blank">View</a>
      <form method="post" onsubmit="return confirm('Delete this document?')" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"/><input type="hidden" name="doc_id" value="<?=$d['id']?>"/><input type="hidden" name="student_id" value="<?=$stdId?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">Delete</button></form>
    </div></td>
  </tr>
  <?php endforeach; endif;?></tbody>
</table></div>
<?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
