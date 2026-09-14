<?php
// ============================================================
// Liberian Standard Report Card — KARN HIGH SCHOOL
// Front: Semester 1 (left) + Semester 2 (right) marks table
// Back:  Promotion Statement
// Format matches the official Liberian school report card.
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
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
// ── Fee block ─────────────────────────────────────────────────
if ((isStudent() || hasRole('parent')) && studentOwesFees($stdId,$ayId)) {
    $owed = number_format(studentOwedAmount($stdId,$ayId),2);
    $ay2  = $pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();
    http_response_code(402);
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Access Restricted</title>
    <style>body{font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#fff5f5;margin:0}
    .box{text-align:center;max-width:420px;padding:40px;background:#fff;border:2px solid #fecaca;border-radius:12px}
    h2{color:#991b1b;margin-bottom:10px}p{color:#7f1d1d;font-size:14px;line-height:1.6}
    .amt{background:#fef2f2;border:1px solid #fecaca;padding:10px 20px;border-radius:8px;margin-top:14px;display:inline-block;font-size:14px;color:#991b1b;font-weight:700}
    a{display:inline-block;margin-top:18px;padding:10px 22px;background:#1a2744;color:#fff;text-decoration:none;border-radius:6px;font-size:14px}
    </style></head><body><div class="box">
    <div style="font-size:52px">🚫</div>
    <h2>Document Access Restricted</h2>
    <p>Your school fees for <strong>'.htmlspecialchars($ay2,ENT_QUOTES).'</strong> have not been fully settled.
    All official documents are locked until your balance is cleared.</p>
    <div class="amt">Outstanding: LRD '.$owed.'</div>
    <br><a href="javascript:history.back()">← Go Back</a>
    </div></body></html>');
}

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

/* ── Print page shell ── */
.page{
  width:270mm;
  background:#fff;
  margin:10px auto;
  padding:0;
  position:relative;
  box-shadow:0 1px 8px rgba(0,0,0,.3);
}
/* Logo watermark */
.page-wm{
  position:absolute;top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:45%;opacity:.05;
  pointer-events:none;user-select:none;z-index:0;
}

/* ── Card wrapper ── */
.card{
  position:relative;
  border:2px solid #000;
  margin:5mm;
  display:flex;
  flex-direction:column;
}

/* ── School header ── */
.school-header{
  text-align:center;
  padding:3mm 4mm 2mm;
  border-bottom:2px solid #000;
}
.school-header .sname{font-size:13pt;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
.school-header .smotto{font-size:8pt;font-style:italic;margin-top:1mm;color:#333}
.school-header .sdoc{font-size:9.5pt;font-weight:700;text-transform:uppercase;margin-top:1.5mm;letter-spacing:.08em}

/* ── Student info row ── */
.student-info{
  display:flex;flex-wrap:wrap;gap:2mm 8mm;
  padding:2mm 4mm;
  border-bottom:1.5px solid #000;
  font-size:8.5pt;
}
.student-info span{white-space:nowrap}
.ul{border-bottom:1px solid #000;display:inline-block;min-width:90px;padding-bottom:0;font-weight:700}

/* ── Two-semester side-by-side layout ── */
.semesters{
  display:grid;
  grid-template-columns:1fr 1fr;
}
.semester{position:relative;overflow:hidden}
.semester+.semester{border-left:2px solid #000}
.sem-title{
  background:#1a2744;color:#fff;
  text-align:center;font-size:8.5pt;font-weight:800;
  padding:1.5mm;letter-spacing:.1em;
  text-transform:uppercase;
}

/* ── Marks table ── */
.rt{width:100%;border-collapse:collapse;font-size:7.8pt}
.rt th,.rt td{
  border:0.6px solid #aaa;
  padding:.8mm 1mm;
  text-align:center;
  line-height:1.25;
}
.rt th{background:#f5f5f5;font-weight:700;font-size:7.5pt}
.rt td.subject{text-align:left;padding-left:1.5mm;white-space:nowrap;font-size:7.6pt}
/* summary rows */
.rt tr.average td{background:#eef4ff;font-weight:700;font-size:7.5pt}
.rt tr.rank    td{background:#f9f9f9;font-weight:700;font-size:7.5pt}
.rt tr.conduct td{background:#f9f9f9}
.rt tr.present td{background:#f9f9f9}
.rt tr.absent  td{background:#fff3f3}
/* failing mark */
.fail{color:#cc0000;font-weight:700}

/* ── PASSED stamp ── */
.stamp{
  position:absolute;
  top:50%;left:50%;
  transform:translate(-50%,-50%) rotate(-10deg);
  z-index:20;pointer-events:none;
}
.stamp span{
  display:block;
  border:4px solid #1a5a9a;
  border-radius:6px;
  padding:5px 18px;
  color:#1a5a9a;
  font-size:22pt;font-weight:900;
  letter-spacing:.18em;
  opacity:.45;
  text-shadow:0 0 4px #1a5a9a33;
  white-space:nowrap;
}

/* ── Grading legend ── */
.grading{
  border-top:2px solid #000;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:0;
  padding:2mm 4mm;
  font-size:7.6pt;
}
.grading>div{padding:0 2mm}
.grading>div+div{border-left:1px solid #ccc}
.grading h3{font-size:7.8pt;font-weight:800;text-transform:uppercase;margin-bottom:1.5mm;letter-spacing:.06em}
.grading p{margin:0.6mm 0;line-height:1.4}

/* ── BACK PAGE ── */
.card-back{border:2px solid #000;margin:5mm;padding:4mm 5mm;min-height:110mm;display:flex;flex-direction:column;gap:3mm}
.back-title{font-size:13pt;font-weight:700;text-align:center;text-decoration:underline;margin-bottom:2mm}
.back-body{font-size:9.5pt;line-height:1.7}
.back-name{font-size:11pt;border-bottom:1px solid #000;display:inline-block;min-width:160px;margin:1mm 0}
.back-grade{font-size:11pt;border-bottom:1px solid #000;display:inline-block;min-width:80px;margin:1mm 0}
.option-row{display:flex;align-items:flex-start;gap:4px;margin:1.5mm 0;font-size:9.5pt}
.option-letter{width:17px;height:17px;border-radius:50%;border:1.5px solid #000;display:flex;align-items:center;justify-content:center;font-size:8pt;font-weight:700;flex-shrink:0;margin-top:1px}
.option-letter.chosen{background:#000;color:#fff}
.sig-area{display:flex;flex-direction:column;gap:6mm;margin-top:3mm}
.sig-line{border-top:1px solid #000;width:180px;padding-top:1mm;font-size:8.5pt}
.back-note{font-size:8pt;font-style:italic;margin-top:auto}
.back-motto{font-size:8.5pt;font-style:italic;margin-top:2mm}

/* ── Print ── */
@media print{
  html,body{background:#fff;padding:0}
  .page{box-shadow:none;width:270mm;margin:0 auto}
  .no-print{display:none}
  .page-break{page-break-before:always}
  @page{size:A4 landscape;margin:6mm}
}
</style>
</head>
<body>

<!-- Print toolbar -->
<div class="no-print" style="width:270mm;margin:10px auto;display:flex;gap:8px;flex-wrap:wrap">
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
<img class="page-wm" src="<?=BASE_URL?>/assets/images/logo.png" alt=""
     onerror="this.src='<?=BASE_URL?>/assets/images/logo.jpg'"/>

<div class="card">

  <!-- School name / header -->
  <div class="school-header">
    <div class="sname"><?=e($school)?></div>
    <div class="smotto"><?=e($motto)?></div>
    <div class="sdoc">Academic Progress Report Card &mdash; <?=e($ay)?></div>
  </div>

  <!-- Student info strip -->
  <div class="student-info">
    <span>Student: <span class="ul"><?=e($studentFullName)?></span></span>
    <span>Grade: <span class="ul"><?=e($student['grade_name']??'—')?><?=$student['class_name']?' &mdash; '.e($student['class_name']):''?></span></span>
    <span>Student ID: <span class="ul"><?=e($student['student_id']??'—')?></span></span>
    <span>Academic Year: <span class="ul"><?=e($ay)?></span></span>
  </div>

  <!-- Two-semester tables -->
  <div class="semesters">

    <!-- ── FIRST SEMESTER ── -->
    <div class="semester">
      <?php if(in_array(strtolower($promoStatus),['promoted','graduated'])):?>
      <div class="stamp"><span>PASSED</span></div>
      <?php endif;?>

      <div class="sem-title">First Semester</div>
      <table class="rt">
        <thead>
          <tr>
            <th class="subject" style="text-align:left;width:35%">SUBJECTS</th>
            <th>1<sup>ST</sup><br>PD</th>
            <th>2<sup>ND</sup><br>PD</th>
            <th>3<sup>RD</sup><br>PD</th>
            <th>EXAM</th>
            <th>SEM.<br>AVE.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($subjects as $sub):
            $d  = $subData[$sub['id']];
            [$p1,$p2,$p3,$se1] = $d['s1'];
          ?>
          <tr>
            <td class="subject"><?=e($sub['name'])?></td>
            <td><?=fv($p1)?></td>
            <td><?=fv($p2)?></td>
            <td><?=fv($p3)?></td>
            <td><?=fv($se1)?></td>
            <td><?=fv($d['sa1'])?></td>
          </tr>
          <?php endforeach;?>
          <tr class="average">
            <td class="subject">Average</td>
            <?php
              // per-period averages for sem1
              $s1cols = [0,1,2,3]; // indices of p1,p2,p3,se1
              foreach($s1cols as $ci):
                $vals=array_filter(array_map(fn($sub)=>$subData[$sub['id']]['s1'][$ci]??null,$subjects),fn($v)=>$v!==null);
                $avg=count($vals)?round(array_sum($vals)/count($vals),1):null;
            ?>
            <td><?=$avg!==null?$avg.'%':''?></td>
            <?php endforeach;?>
            <td><?=$classAvgSem1!==null?$classAvgSem1.'%':''?></td>
          </tr>
          <tr class="rank">
            <td class="subject">Rank</td>
            <?php
              // show rank once spanning all columns — use colspan via repeated cells
              $rankStr = $rank ?? '';
              for($i=0;$i<4;$i++) echo "<td>$rankStr</td>";
            ?>
            <td><?=$rankStr?></td>
          </tr>
          <tr class="conduct">
            <td class="subject">Conduct</td>
            <?php for($i=0;$i<4;$i++) echo '<td>'.e($rc['conduct']??'✓').'</td>';?>
            <td></td>
          </tr>
          <tr class="present">
            <td class="subject">Days Present</td>
            <?php
              $dp=$rc['days_present']??'';
              for($i=0;$i<4;$i++) echo "<td>$dp</td>";
            ?>
            <td></td>
          </tr>
          <tr class="absent">
            <td class="subject">Days Absent</td>
            <?php
              $da=$rc['days_absent']??'0';
              for($i=0;$i<4;$i++) echo "<td>$da</td>";
            ?>
            <td><?=$da?></td>
          </tr>
        </tbody>
      </table>
    </div><!-- /first semester -->

    <!-- ── SECOND SEMESTER ── -->
    <div class="semester">
      <?php if(in_array(strtolower($promoStatus),['promoted','graduated'])):?>
      <div class="stamp"><span>PASSED</span></div>
      <?php endif;?>

      <div class="sem-title">Second Semester</div>
      <table class="rt">
        <thead>
          <tr>
            <th>4<sup>TH</sup><br>PD</th>
            <th>5<sup>TH</sup><br>PD</th>
            <th>6<sup>TH</sup><br>PD</th>
            <th>EXAM</th>
            <th>SEM.<br>AVE.</th>
            <th>YRLY<br>AVE.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($subjects as $sub):
            $d  = $subData[$sub['id']];
            [$p4,$p5,$p6,$se2] = $d['s2'];
          ?>
          <tr>
            <td><?=fv($p4)?></td>
            <td><?=fv($p5)?></td>
            <td><?=fv($p6)?></td>
            <td><?=fv($se2)?></td>
            <td><?=fv($d['sa2'])?></td>
            <td style="font-weight:700"><?=fv($d['ya'])?></td>
          </tr>
          <?php endforeach;?>
          <tr class="average">
            <?php
              $s2cols=[0,1,2,3];
              foreach($s2cols as $ci):
                $vals=array_filter(array_map(fn($sub)=>$subData[$sub['id']]['s2'][$ci]??null,$subjects),fn($v)=>$v!==null);
                $avg=count($vals)?round(array_sum($vals)/count($vals),1):null;
            ?>
            <td><?=$avg!==null?$avg.'%':''?></td>
            <?php endforeach;?>
            <td><?=$classAvgSem2!==null?$classAvgSem2.'%':''?></td>
            <td style="font-weight:800"><?=$overallAvg!==null?$overallAvg.'%':''?></td>
          </tr>
          <tr class="rank">
            <?php for($i=0;$i<5;$i++) echo '<td>'.($rank??'').'</td>';?>
            <td><?=$rank??''?></td>
          </tr>
          <tr class="conduct">
            <?php for($i=0;$i<5;$i++) echo '<td>'.e($rc['conduct']??'✓').'</td>';?>
            <td>✓</td>
          </tr>
          <tr class="present">
            <?php
              $dp=$rc['days_present']??'';
              for($i=0;$i<4;$i++) echo "<td>$dp</td>";
            ?>
            <td>✓</td><td>✓</td>
          </tr>
          <tr class="absent">
            <?php
              $da=$rc['days_absent']??'0';
              for($i=0;$i<5;$i++) echo "<td>$da</td>";
            ?>
            <td><?=$da?></td>
          </tr>
        </tbody>
      </table>
    </div><!-- /second semester -->

  </div><!-- /semesters -->

  <!-- Grading system legend -->
  <div class="grading">
    <div>
      <h3>Grading System</h3>
      <p>95 – 100 &nbsp; Principal's List (Excellent)</p>
      <p>90 – 94 &nbsp;&nbsp; High Honor (Very Good)</p>
      <p>85 – 89 &nbsp;&nbsp; Honor (Good)</p>
    </div>
    <div>
      <h3>&nbsp;</h3>
      <p>80 – 84 &nbsp;&nbsp; Satisfactorily (Fairly Good)</p>
      <p>75 – 79 &nbsp;&nbsp; Needs Help (Fair-pass)</p>
      <p>73 – 74 &nbsp;&nbsp; Needs Help (Weak Pass)</p>
      <p>72 &amp; Below &nbsp; Fail</p>
    </div>
  </div>

</div><!-- /card -->
</div><!-- /page 1 -->


<!-- ══════════════════════════════════════════════
     PAGE 2 — BACK (PROMOTION STATEMENT)
══════════════════════════════════════════════ -->
<div class="page page-break">
<img class="page-wm" src="<?=BASE_URL?>/assets/images/logo.png" alt=""
     onerror="this.src='<?=BASE_URL?>/assets/images/logo.jpg'"/>
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

  <!-- QR verification — inside card-back -->
  <?php
  $_rcRef = 'RC-'.date('Y').'-'.str_pad($stdId,4,'0',STR_PAD_LEFT).'-'.($ayId??0);
  echo docVerifyStrip('report_card',$stdId,
      $studentFullName, $student['grade_name']??'', $ay,
      $_rcRef, isLoggedIn()?currentUserId():null, null, '+5 years');
  ?>

  <!-- School footer -->
  <div style="text-align:center;font-size:8pt;color:#555;margin-top:auto;border-top:1px solid #ccc;padding-top:2mm">
    <?=e($school)?> &nbsp;&bull;&nbsp; <?=e(setting('school_address','Karnplay, Nimba County, Liberia'))?>
  </div>

</div><!-- /card-back -->
</div><!-- /page 2 -->
</body>
</html>
