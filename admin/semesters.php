<?php
$pageTitle='Semesters'; $activeAdmin='academic_years';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db(); $ayId=(int)($_GET['ay_id']??currentAcademicYearId());
$ay=$pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();

if($_SERVER['REQUEST_METHOD']==='POST'){ verifyCsrf(); $action=$_POST['action']??'';
    if($action==='set_current_sem'){ $id=(int)($_POST['sem_id']??0); $pdo->prepare("UPDATE semesters SET is_current=0 WHERE academic_year_id=?")->execute([$ayId]); $pdo->prepare("UPDATE semesters SET is_current=1 WHERE id=?")->execute([$id]); flash('success','Current semester updated.'); }
    elseif($action==='set_current_per'){ $id=(int)($_POST['per_id']??0); $pdo->prepare("UPDATE periods SET is_current=0")->execute([]); $pdo->prepare("UPDATE periods SET is_current=1 WHERE id=?")->execute([$id]); flash('success','Current period updated.'); }
    redirect(BASE_URL.'/admin/semesters.php?ay_id='.$ayId);
}

$sems=$pdo->query("SELECT s.*,(SELECT COUNT(*) FROM periods p WHERE p.semester_id=s.id) pc FROM semesters s WHERE s.academic_year_id=$ayId ORDER BY s.sequence")->fetchAll();
foreach($sems as &$sem){ $sem['periods']=$pdo->query("SELECT * FROM periods WHERE semester_id={$sem['id']} ORDER BY sequence")->fetchAll(); } unset($sem);
?>
<div class="page-heading"><div><a href="<?=BASE_URL?>/admin/academic_years.php" class="text-link" style="margin-bottom:8px;display:flex">← Academic Years</a><div class="eyebrow">Academic Year <span></span></div><h1>Semesters &amp; Periods</h1><p><?=e($ay)?></p></div></div>
<?php foreach($sems as $sem):?>
<div class="form-section" style="margin-bottom:20px">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
    <span><?=e($sem['name'])?></span>
    <div style="display:flex;gap:8px;align-items:center">
      <?=$sem['is_current']?'<span class="status approved">Current</span>':''?>
      <?php if(!$sem['is_current']):?><form method="post" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="set_current_sem"/><input type="hidden" name="sem_id" value="<?=$sem['id']?>"/><button type="submit" class="filter-button button-sm">Set Current</button></form><?php endif;?>
    </div>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Period</th><th>Type</th><th>Current</th><th>Action</th></tr></thead>
    <tbody><?php foreach($sem['periods'] as $p):?>
    <tr>
      <td><strong><?=e($p['name'])?></strong></td>
      <td><span class="status <?=$p['type']==='exam'?'warning':'new-s'?>"><?=e(ucfirst($p['type']))?></span></td>
      <td><?=$p['is_current']?'<span class="status approved">Current</span>':'—'?></td>
      <td><?php if(!$p['is_current']):?><form method="post" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="set_current_per"/><input type="hidden" name="per_id" value="<?=$p['id']?>"/><button type="submit" class="filter-button button-sm">Set Current</button></form><?php endif;?></td>
    </tr>
    <?php endforeach;?></tbody>
  </table></div>
</div>
<?php endforeach;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
