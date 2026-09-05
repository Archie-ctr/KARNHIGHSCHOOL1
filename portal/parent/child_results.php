<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

// Resolve child — same logic as parent/index.php
$guardian=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1"); $guardian->execute([$user['id']]); $guardian=$guardian->fetch();
$children=[];
if($guardian){$ch=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id JOIN student_guardians sg ON sg.student_id=s.id WHERE sg.guardian_id=? ORDER BY s.first_name");$ch->execute([$guardian['id']]);$children=$ch->fetchAll();}
if(empty($children)){$ch2=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.status='Active' AND (s.phone=? OR s.email=?) ORDER BY s.first_name");$ch2->execute([$user['phone']??'',$user['email']??'']);$children=$ch2->fetchAll();}

$selChild=(int)($_GET['child_id']??($children[0]['id']??0));
$child=null; foreach($children as $ch) if($ch['id']==$selChild){$child=$ch;break;}
$subjects=[]; $bySubject=[]; $configs=[];
if($child){
    $subs=$pdo->prepare("SELECT DISTINCT s.id,s.name FROM assessment_scores asc2 JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name");$subs->execute([$child['id'],$ayId]);$subjects=$subs->fetchAll();
    foreach($subjects as $sub){$sc=$pdo->prepare("SELECT ac.name cfg_name,asc2.marks_obtained,asc2.max_marks FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=? AND asc2.status IN ('submitted','approved') ORDER BY ac.sequence");$sc->execute([$child['id'],$sub['id'],$ayId]);$bySubject[$sub['id']]=$sc->fetchAll();}
    foreach($subjects as $sub) foreach($bySubject[$sub['id']] as $sc) $configs[$sc['cfg_name']]=$sc['cfg_name'];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Child Results — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Parent Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/parent/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/parent/child_results.php<?=$selChild?"?child_id=$selChild":''?>" class="active">📊 Results</a>
    <a href="<?=BASE_URL?>/portal/parent/fees.php<?=$selChild?"?child_id=$selChild":''?>">💰 Fees</a>
    <a href="<?=BASE_URL?>/portal/parent/report_card.php<?=$selChild?"?child_id=$selChild":''?>">📑 Report Card</a>
    <a href="<?=BASE_URL?>/portal/parent/announcements.php">📢 Announcements</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading"><div><h1><?=$child?e($child['first_name']."'s"):'Child'?> Results</h1><p><?=e($ay)?></p></div></div>
  <?php if(count($children)>1):?>
  <div class="filter-row" style="margin-bottom:20px">
    <?php foreach($children as $ch):?><a href="?child_id=<?=$ch['id']?>" class="filter-button" style="<?=$selChild==$ch['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>"><?=e($ch['first_name'])?></a><?php endforeach;?>
  </div>
  <?php endif;?>
  <?php if($child&&empty($subjects)):?>
  <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)"><div style="font-size:36px;margin-bottom:12px">📊</div><p style="color:var(--ink-soft)">No results available yet for <?=e($ay)?>.</p></div>
  <?php elseif($child):?>
  <div class="table-wrap"><table>
    <thead><tr><th>Subject</th><?php foreach($configs as $cfg):?><th><?=e($cfg)?></th><?php endforeach;?><th>Avg</th><th>Grade</th></tr></thead>
    <tbody>
      <?php foreach($subjects as $sub):
        $vals=array_filter(array_map(fn($d)=>$d['marks_obtained']!==null?($d['marks_obtained']/$d['max_marks']*100):null,$bySubject[$sub['id']]),fn($v)=>$v!==null);
        $avg=count($vals)?round(array_sum($vals)/count($vals),1):null; $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
        $cfgVals=[]; foreach($bySubject[$sub['id']] as $sc) $cfgVals[$sc['cfg_name']]=$sc;
      ?>
      <tr>
        <td><strong><?=e($sub['name'])?></strong></td>
        <?php foreach($configs as $cfg): $d=$cfgVals[$cfg]??null;?><td><?=$d&&$d['marks_obtained']!==null?fmtMark($d['marks_obtained']).'/'.$d['max_marks']:'—'?></td><?php endforeach;?>
        <td><strong><?=$avg!==null?$avg.'%':'—'?></strong></td>
        <td><span class="status <?=in_array($gl,['A','B','C','D'])?'approved':'warning'?>"><?=e($gl)?></span></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table></div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
