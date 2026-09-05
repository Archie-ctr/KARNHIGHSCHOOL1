<?php
$pageTitle='Timetable'; $activeAdmin='timetable';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $action=$_POST['action']??'';
    if($action==='save'){
        $clsId=(int)($_POST['class_id']??0);
        if($clsId){
            $pdo->prepare("DELETE FROM timetable WHERE class_id=? AND academic_year_id=?")->execute([$clsId,$ayId]);
            foreach(($_POST['slot']??[]) as $day=>$periods){
                foreach($periods as $period=>$subId){
                    $subId=(int)$subId; $tId=(int)($_POST['teacher'][$day][$period]??0);
                    if($subId){
                        $pdo->prepare("INSERT INTO timetable (class_id,subject_id,teacher_id,day_of_week,period_slot,academic_year_id) VALUES (?,?,?,?,?,?)")->execute([$clsId,$subId,$tId?:null,$day,$period,$ayId]);
                    }
                }
            }
            flash('success','Timetable saved.');
        }
    }
    redirect(BASE_URL.'/admin/timetable.php?class_id='.($_POST['class_id']??''));
}

$classes=$pdo->prepare("SELECT c.id,c.name FROM classes c WHERE c.academic_year_id=? ORDER BY c.name")->execute([$ayId])?$pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll():[];
$subjects=$pdo->query("SELECT id,name FROM subjects WHERE is_active=1 ORDER BY name")->fetchAll();
$teachers=$pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();
$selClass=(int)($_GET['class_id']??0);
$existing=[];
if($selClass){ $tt=$pdo->query("SELECT * FROM timetable WHERE class_id=$selClass AND academic_year_id=$ayId")->fetchAll(); foreach($tt as $r) $existing[$r['day_of_week']][$r['period_slot']]=$r; }
$days=[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];
?>
<div class="page-heading"><div><div class="eyebrow">Academics <span></span></div><h1>Timetable</h1><p><?=e($ay)?></p></div></div>
<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px"><option value="">Select Class…</option><?php foreach($classes as $c):?><option value="<?=$c['id']?>" <?=$selClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select>
</form>
<?php if($selClass):?>
<form method="post">
  <?=csrfField()?><input type="hidden" name="action" value="save"/><input type="hidden" name="class_id" value="<?=$selClass?>"/>
  <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:12px">
    <thead><tr><th style="padding:10px;background:var(--primary);color:#fff;min-width:60px">Period</th><?php foreach($days as $dn):?><th style="padding:10px;background:var(--primary);color:#fff;min-width:200px"><?=$dn?></th><?php endforeach;?></tr></thead>
    <tbody><?php for($p=1;$p<=8;$p++):?><tr style="border-bottom:1px solid var(--line)">
      <td style="padding:8px 12px;font-weight:700;background:var(--bg);text-align:center"><?=$p?></td>
      <?php foreach(array_keys($days) as $d): $cell=$existing[$d][$p]??null;?>
      <td style="padding:6px;border-right:1px solid var(--line-soft)">
        <select name="slot[<?=$d?>][<?=$p?>]" style="width:100%;padding:4px 6px;border:1px solid var(--line);border-radius:4px;font-size:12px;margin-bottom:4px">
          <option value="0">—</option><?php foreach($subjects as $s):?><option value="<?=$s['id']?>" <?=($cell&&$cell['subject_id']==$s['id'])?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?>
        </select>
        <select name="teacher[<?=$d?>][<?=$p?>]" style="width:100%;padding:4px 6px;border:1px solid var(--line);border-radius:4px;font-size:11px;color:var(--ink-soft)">
          <option value="">Teacher…</option><?php foreach($teachers as $t):?><option value="<?=$t['id']?>" <?=($cell&&$cell['teacher_id']==$t['id'])?'selected':''?>><?=e($t['name'])?></option><?php endforeach;?>
        </select>
      </td>
      <?php endforeach;?></tr><?php endfor;?></tbody>
  </table></div>
  <div style="display:flex;justify-content:flex-end;margin-top:14px"><button type="submit" class="button button-primary">💾 Save Timetable</button></div>
</form>
<?php else:?><div style="text-align:center;padding:40px;color:var(--ink-faint)">Select a class above to manage its timetable.</div><?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
