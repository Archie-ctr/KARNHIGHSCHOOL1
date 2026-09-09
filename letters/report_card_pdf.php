<?php
// ============================================================
// Liberian Standard Report Card — KARN HIGH SCHOOL
// Front: Semester 1 (left) + Semester 2 (right) marks table
// Back:  Promotion Statement
// Format matches the official Liberian school report card.
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
$pdo=db();
$stdId=(int)($_GET['student_id']??0);
$ayId =(int)($_GET['ay_id']??currentAcademicYearId());
if(!$stdId) die('Invalid request.');

// Access control
if(isStudent()){
    $myStd=(int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if($myStd!==$stdId) die('Access denied.');
}
if(hasRole('parent')){
    $g=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1");
    $g->execute([currentUser()['id']]);
    $gid=(int)($g->fetchColumn()?:0);
    if($gid){$linked=(int)$pdo->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_id=$gid AND student_id=$stdId")->fetchColumn();if(!$linked)die('Access denied.');}
}

$student=$pdo->query(
    "SELECT s.*,g.name grade_name,g.id grade_id,c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     WHERE s.id=$stdId"
)->fetch();
if(!$student) die('Student not found.');

$rc=$pdo->query("SELECT * FROM report_cards WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1")->fetch();
if(!$rc||$rc['status']!=='published') die('Report card not yet published.');

$ay=$pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();
$school = setting('school_name','KARN HIGH SCHOOL');
$motto  = setting('school_motto','Show the light, the people will find the way');

// ── Load subject scores ───────────────────────────────────────
$subjects=$pdo->prepare(
    "SELECT DISTINCT s.id,s.name,s.category
     FROM assessment_scores asc2
     JOIN subjects s ON s.id=asc2.subject_id
     WHERE asc2.student_id=? AND asc2.academic_year_id=?
     ORDER BY s.category,s.name"
);
$subjects->execute([$stdId,$ayId]);
$subjects=$subjects->fetchAll();

// Map score columns
// Keys we look for in assessment_configs.name (case-insensitive partial match)
$sem1Keys=['1st Period','2nd Period','3rd Period','Semester 1 Examination'];
$sem2Keys=['4th Period','5th Period','6th Period','Semester 2 Examination'];
$allKeys =array_merge($sem1Keys,$sem2Keys);

$scoreMap=[]; // [subject_id][config_key] = marks_obtained%
foreach($subjects as $sub){
    $sc=$pdo->prepare(
        "SELECT ac.name cfg,asc2.marks_obtained,asc2.max_marks
         FROM assessment_scores asc2
         JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
         WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
         ORDER BY ac.sequence"
    );
    $sc->execute([$stdId,$sub['id'],$ayId]);
    $rows=$sc->fetchAll();
    $scoreMap[$sub['id']]=[];
    foreach($rows as $r){
        foreach($allKeys as $k){
            if(stripos($r['cfg'],$k)!==false&&!isset($scoreMap[$sub['id']][$k])){
                $pct=$r['max_marks']>0?round($r['marks_obtained']/$r['max_marks']*100,1):null;
                $scoreMap[$sub['id']][$k]=$pct;
            }
        }
    }
}

// ── Compute averages ─────────────────────────────────────────
function semAvg(array $vals):?float{
    $v=array_filter($vals,fn($x)=>$x!==null);
    return count($v)?round(array_sum($v)/count($v),1):null;
}
function yearAvg(float $s1=null,float $s2=null):?float{
    $v=array_filter([$s1,$s2],fn($x)=>$x!==null);
    return count($v)?round(array_sum($v)/count($v),1):null;
}

// Per subject computed values
$subData=[];
foreach($subjects as $sub){
    $sid=$sub['id']; $sm=$scoreMap[$sid]??[];
    $s1=[($sm['1st Period']??null),($sm['2nd Period']??null),($sm['3rd Period']??null),($sm['Semester 1 Examination']??null)];
    $s2=[($sm['4th Period']??null),($sm['5th Period']??null),($sm['6th Period']??null),($sm['Semester 2 Examination']??null)];
    $sa1=semAvg(array_slice($s1,0,3)); // Sem1 Ave = avg of 1st,2nd,3rd (or include exam)
    $sa2=semAvg(array_slice($s2,0,3));
    $ya =yearAvg($sa1,$sa2);
    $subData[$sid]=['s1'=>$s1,'s2'=>$s2,'sa1'=>$sa1,'sa2'=>$sa2,'ya'=>$ya];
}

// Class aggregate/average/rank
$yearAvgs=array_filter(array_map(fn($d)=>$d['ya'],$subData),fn($v)=>$v!==null);
$classAvgSem1=count($subData)?round(array_sum(array_map(fn($d)=>$d['sa1']??0,$subData))/count($subData),1):null;
$classAvgSem2=count($subData)?round(array_sum(array_map(fn($d)=>$d['sa2']??0,$subData))/count($subData),1):null;
$overallAvg=count($yearAvgs)?round(array_sum($yearAvgs)/count($yearAvgs),1):null;

// Aggregate (sum of raw percentages)
$agg1=array_sum(array_filter(array_map(fn($d)=>$d['sa1'],$subData),fn($v)=>$v!==null));
$agg2=array_sum(array_filter(array_map(fn($d)=>$d['sa2'],$subData),fn($v)=>$v!==null));

// Class rank (if annual_results table has it)
$rank=null;
try{$rank=$pdo->query("SELECT class_position FROM annual_results WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1")->fetchColumn()?:null;}catch(Throwable $e){}

// Promotion statement details
$isGr12=($student['grade_id']==13);
$promoStatus=$rc['promotion_status']??'Pending';
$promoOption=match(strtolower($promoStatus)){
    'promoted'             =>'A',
    'promoted with support'=>'B',
    'repeating'            =>'C',
    'not promoted'         =>'D',
    default                =>'A',
};
$nextGrade=$student['grade_name']??'Next Grade';
if(preg_match('/(\d+)/',$nextGrade,$m)) $nextGradeNum=(int)$m[1]+1;
else $nextGradeNum='';

$closingDate=$rc['updated_at']??date('Y-m-d');
$studentFullName=trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name']);

// ── Format helper ────────────────────────────────────────────
function fv($v,bool $showRed=true):string{
    if($v===null||$v==='') return '';
    $num=(float)$v;
    if($showRed&&$num<70) return '<span style="color:#d00">'.$v.'</span>';
    return (string)$v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Report Card — <?=e($studentFullName)?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:Arial,sans-serif;font-size:9.5pt;color:#000;background:#e0e0e0}
/* ── Print pages ── */
.page{width:190mm;min-height:120mm;background:#fff;margin:8px auto;padding:0;position:relative;box-shadow:0 1px 6px rgba(0,0,0,.3)}
/* FRONT page — two-panel card layout */
.card-front{display:flex;flex-direction:column;border:2px solid #000;margin:4mm}
.card-header{padding:2.5mm 3mm 1.5mm;border-bottom:1.5px solid #000}
.card-header-row{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.card-title{font-size:10.5pt;font-weight:700;text-align:center;margin-bottom:2mm;letter-spacing:.02em}
.hn{font-size:9.5pt} .hl{border-bottom:1px solid #000;min-width:100px;display:inline-block;padding-bottom:0}
/* Dual panel grid */
.panels{display:grid;grid-template-columns:1fr 1fr;border-top:1.5px solid #000}
.panel{position:relative}
.panel+.panel{border-left:2px solid #000}
/* Table inside panels */
.rt{width:100%;border-collapse:collapse;font-size:8.5pt}
.rt th,.rt td{border:0.75px solid #999;padding:1mm 1.5mm;text-align:center;line-height:1.2}
.rt th{background:#fff;font-weight:700;font-size:8pt}
.rt td.subj{text-align:left;font-size:8pt;padding-left:1.5mm;white-space:nowrap}
.rt tr.section-row td,.rt tr.section-row th{background:#f0f0f0;font-weight:700}
.rt tr.total-row td{font-weight:700;border-top:1px solid #000}
/* Grading footer */
.card-footer{border-top:2px solid #000;padding:2mm 3mm;text-align:center;font-size:8.5pt;font-weight:700;letter-spacing:.02em}
/* Promotion stamp */
.promo-stamp{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-8deg);
    border:3px solid #1a5a9a;border-radius:4px;padding:4px 10px;
    color:#1a5a9a;font-size:13pt;font-weight:900;letter-spacing:.12em;
    opacity:.6;white-space:nowrap;pointer-events:none;z-index:10;
    text-shadow:0 0 2px #1a5a9a44}

/* ── BACK PAGE — Promotion Statement ── */
.card-back{border:2px solid #000;margin:4mm;padding:4mm 5mm;min-height:100mm;display:flex;flex-direction:column;gap:3mm}
.back-title{font-size:13pt;font-weight:700;text-align:center;text-decoration:underline;margin-bottom:2mm}
.back-body{font-size:9.5pt;line-height:1.7}
.back-name{font-size:11pt;border-bottom:1px solid #000;display:inline-block;min-width:160px;margin:1mm 0}
.back-grade{font-size:11pt;border-bottom:1px solid #000;display:inline-block;min-width:80px;margin:1mm 0}
.option-row{display:flex;align-items:flex-start;gap:4px;margin:1mm 0;font-size:9.5pt}
.option-letter{width:16px;height:16px;border-radius:50%;border:1.5px solid #000;display:flex;align-items:center;justify-content:center;font-size:8pt;font-weight:700;flex-shrink:0;margin-top:1px}
.option-letter.chosen{background:#000;color:#fff}
.sig-area{display:flex;flex-direction:column;gap:6mm;margin-top:3mm}
.sig-line{border-top:1px solid #000;width:180px;padding-top:1mm;font-size:8.5pt}
.back-note{font-size:8pt;font-style:italic;margin-top:auto}
.back-motto{font-size:8.5pt;font-style:italic;margin-top:2mm}

/* ── Print ── */
@media print{
    html,body{background:#fff;padding:0}
    .page{box-shadow:none;width:190mm;margin:0 auto}
    .no-print{display:none}
    .page-break{page-break-before:always}
    @page{size:A5 landscape;margin:6mm}
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════
     PRINT BUTTON
══════════════════════════════════════════════ -->
<div class="no-print" style="width:190mm;margin:10px auto;display:flex;gap:8px;flex-wrap:wrap">
  <button onclick="window.print()"
    style="padding:8px 18px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:700">
    🖨️ Print Both Pages (Front &amp; Back)
  </button>
  <a href="gradesheet_pdf.php?student_id=<?=$stdId?>&ay_id=<?=$ayId?>" target="_blank"
     style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#333;text-decoration:none">
     📋 View Gradesheet
  </a>
  <a href="javascript:history.back()"
     style="padding:8px 14px;border:1px solid #ccc;border-radius:6px;font-size:13px;color:#555;text-decoration:none">
     ← Back
  </a>
</div>

<!-- ══════════════════════════════════════════════
     PAGE 1 — FRONT (MARKS)
══════════════════════════════════════════════ -->
<div class="page">
<div class="card-front">

  <!-- Header: student info + grade + year -->
  <div class="card-header">
    <div style="text-align:center;font-size:10pt;font-weight:700;margin-bottom:2mm"><?=e($school)?></div>
    <div style="display:flex;flex-wrap:wrap;gap:2mm 8mm;font-size:9pt">
      <span><strong>Student:</strong> <span class="hl"><?=e($studentFullName)?></span></span>
      <span><strong>Grade:</strong> <span class="hl"><?=e($student['grade_name']??'—').($student['class_name']?' '.e($student['class_name']):'')?></span></span>
      <span><strong>School Year:</strong> <span class="hl"><?=e($ay)?></span></span>
    </div>
  </div>

  <!-- Dual panel: Semester 1 (left) | Semester 2 (right) -->
  <div class="panels">

    <!-- LEFT — Semester 1 -->
    <div class="panel">
      <?php if(strtolower($promoStatus)==='promoted'||strtolower($promoStatus)==='graduated'):?>
      <div class="promo-stamp">PROMOTED</div>
      <?php endif;?>
      <table class="rt">
        <thead>
          <tr>
            <th colspan="6" style="font-size:9pt;padding:1.5mm">1<sup>st</sup> SEMESTER</th>
          </tr>
          <tr>
            <th class="subj" style="text-align:left;width:32%">Subjects</th>
            <th>1<sup>st</sup><br>Pd.</th>
            <th>2<sup>nd</sup><br>Pd.</th>
            <th>3<sup>rd</sup><br>Pd.</th>
            <th>Sem.<br>Ex.</th>
            <th>Sem.<br>Ave.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($subjects as $sub):
            $d=$subData[$sub['id']];
            [$p1,$p2,$p3,$se1]=$d['s1'];
          ?>
          <tr>
            <td class="subj"><?=e($sub['name'])?></td>
            <td><?=fv($p1)?></td>
            <td><?=fv($p2)?></td>
            <td><?=fv($p3)?></td>
            <td><?=fv($se1)?></td>
            <td><?=fv($d['sa1'])?></td>
          </tr>
          <?php endforeach;?>
          <!-- Aggregate -->
          <tr class="total-row">
            <td class="subj">Aggregate</td>
            <td colspan="4"></td>
            <td><?=$agg1>0?round($agg1,0):''?></td>
          </tr>
          <!-- Average -->
          <tr class="total-row">
            <td class="subj">Average</td>
            <td colspan="4"></td>
            <td><?=$classAvgSem1!==null?$classAvgSem1:''?></td>
          </tr>
          <!-- Rank -->
          <tr><td class="subj">Rank</td><td colspan="5"><?=$rank??''?></td></tr>
          <!-- Conduct/Behavior -->
          <tr>
            <td class="subj">Conduct/Behavior</td>
            <td colspan="5"><?=e($rc['conduct']??'')?></td>
          </tr>
          <!-- Days Absent -->
          <tr>
            <td class="subj">Day Absent</td>
            <td colspan="5"><?=$rc['days_absent']??''?></td>
          </tr>
          <!-- Days Present -->
          <tr>
            <td class="subj">Day Present</td>
            <td colspan="5"><?=$rc['days_present']??''?></td>
          </tr>
          <!-- Times Tardy -->
          <tr>
            <td class="subj">Times/Tardy</td>
            <td colspan="5"><?=$rc['days_tardy']??''?></td>
          </tr>
        </tbody>
      </table>
    </div><!-- /LEFT panel -->

    <!-- RIGHT — Semester 2 + Yearly -->
    <div class="panel">
      <?php if(strtolower($promoStatus)==='promoted'||strtolower($promoStatus)==='graduated'):?>
      <div class="promo-stamp">PROMOTED</div>
      <?php endif;?>
      <table class="rt">
        <thead>
          <tr>
            <th colspan="7" style="font-size:9pt;padding:1.5mm">2<sup>nd</sup> SEMESTER</th>
          </tr>
          <tr>
            <th>4<sup>th</sup><br>Pd.</th>
            <th>5<sup>th</sup><br>Pd.</th>
            <th>6<sup>th</sup><br>Pd.</th>
            <th>Sem<br>Ex.</th>
            <th>Sem.<br>Ave.</th>
            <th>Yrly.<br>Ave.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($subjects as $sub):
            $d=$subData[$sub['id']];
            [$p4,$p5,$p6,$se2]=$d['s2'];
          ?>
          <tr>
            <td><?=fv($p4)?></td>
            <td><?=fv($p5)?></td>
            <td><?=fv($p6)?></td>
            <td><?=fv($se2)?></td>
            <td><?=fv($d['sa2'])?></td>
            <td><?=fv($d['ya'])?></td>
          </tr>
          <?php endforeach;?>
          <!-- Aggregate -->
          <tr class="total-row">
            <td colspan="4"></td>
            <td><?=$agg2>0?round($agg2,0):''?></td>
            <td></td>
          </tr>
          <!-- Average -->
          <tr class="total-row">
            <td colspan="4"></td>
            <td><?=$classAvgSem2!==null?$classAvgSem2:''?></td>
            <td style="font-weight:800;font-size:9.5pt"><?=$overallAvg!==null?$overallAvg:''?></td>
          </tr>
          <!-- Rank -->
          <tr><td colspan="6"><?=$rank??''?></td></tr>
          <!-- Conduct -->
          <tr><td colspan="6"><?=e($rc['conduct']??'')?></td></tr>
          <!-- Days Absent -->
          <tr><td colspan="6"><?=$rc['days_absent']??''?></td></tr>
          <!-- Days Present -->
          <tr><td colspan="6"><?=$rc['days_present']??''?></td></tr>
          <!-- Times Tardy -->
          <tr><td colspan="6"><?=$rc['days_tardy']??''?></td></tr>
        </tbody>
      </table>
    </div><!-- /RIGHT panel -->
  </div><!-- /panels -->

  <!-- Footer -->
  <div class="card-footer">ANY GRADE BELOW 70% IS A FAILING GRADE</div>

</div><!-- /card-front -->
</div><!-- /page 1 -->


<!-- ══════════════════════════════════════════════
     PAGE 2 — BACK (PROMOTION STATEMENT)
══════════════════════════════════════════════ -->
<div class="page page-break">
<div class="card-back">

  <div class="back-title">PROMOTION STATEMENT</div>

  <div class="back-body">
    <div style="margin-bottom:3mm">This certifies that:</div>

    <div style="margin-bottom:2mm">
      <span class="back-name"><?=e($studentFullName)?></span>
    </div>

    <div style="margin-bottom:3mm">
      Has/Has not satisfactorily completed the work of grade
      <span class="back-grade"><?=e($student['grade_name']??'').' '.e($student['class_name']??'')?></span>
      and is:
    </div>

    <!-- Option A -->
    <div class="option-row">
      <span class="option-letter <?=$promoOption==='A'?'chosen':''?>">A</span>
      <span>Eligible for promotion to Grade
        <span style="border-bottom:1px solid #000;min-width:40px;display:inline-block;padding-bottom:0"><?=e($nextGradeNum?:'___')?></span>
      </span>
    </div>
    <!-- Option B -->
    <div class="option-row">
      <span class="option-letter <?=$promoOption==='B'?'chosen':''?>">B</span>
      <span>Eligible for promotion in grade but require to attend vacation enrichment program</span>
    </div>
    <!-- Option C -->
    <div class="option-row">
      <span class="option-letter <?=$promoOption==='C'?'chosen':''?>">C</span>
      <span>Requires to repeat Grade
        <span style="border-bottom:1px solid #000;min-width:40px;display:inline-block;padding-bottom:0"><?=e($student['grade_name']??'___')?></span>
      </span>
    </div>
    <!-- Option D -->
    <div class="option-row">
      <span class="option-letter <?=$promoOption==='D'?'chosen':''?>">D</span>
      <span>Asked <strong>NOT TO ENROLL</strong> next Academic year</span>
    </div>
  </div>

  <!-- Signatures -->
  <div class="sig-area">
    <div>
      <div style="height:28px"></div>
      <div class="sig-line">Registrar</div>
    </div>
    <div>
      <div style="height:28px"></div>
      <div class="sig-line">Principal</div>
    </div>
    <div style="font-size:9pt;margin-top:2mm">
      <strong>Closing Date:</strong>
      <span style="border-bottom:1px solid #000;min-width:120px;display:inline-block">
        <?=date('F j, Y',strtotime($closingDate))?>
      </span>
    </div>
  </div>

  <div class="back-note">NOTE: Any erasure on this card makes it invalid</div>
  <div class="back-motto">Motto: "<?=e($motto)?>"</div>

  <!-- School footer -->
  <div style="text-align:center;font-size:8pt;color:#555;margin-top:auto;border-top:1px solid #ccc;padding-top:2mm">
    <?=e($school)?> &nbsp;&bull;&nbsp; <?=e(setting('school_address','Karnplay, Nimba County, Liberia'))?>
  </div>

</div><!-- /card-back -->
</div><!-- /page 2 -->

</body>
</html>
