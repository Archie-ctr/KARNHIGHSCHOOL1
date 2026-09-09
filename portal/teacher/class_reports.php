<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['class_teacher']);

$activePage = 'class_reports';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) { redirect(BASE_URL.'/portal/teacher/'); }
$teacherId = $teacher['id'];

// ── Resolve class ─────────────────────────────────────────────
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) {
    $myClass = $pdo->prepare("SELECT c.id FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name LIMIT 1");
    $myClass->execute([$teacherId,$ayId]); $classId=(int)($myClass->fetchColumn()??0);
}
if (!$classId) { redirect(BASE_URL.'/portal/teacher/'); }
$classInfo = $pdo->query("SELECT c.*,g.name grade_name FROM classes c JOIN grades g ON g.id=c.grade_id WHERE c.id=$classId")->fetch();

$allMyClasses = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$allMyClasses->execute([$teacherId,$ayId]); $allMyClasses=$allMyClasses->fetchAll();

$tab = $_GET['tab'] ?? 'attendance';

// ── POST: save report card comment ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_comment') {
        $sid     = (int)($_POST['student_id'] ?? 0);
        $comment = trim($_POST['teacher_comment'] ?? '');
        $conduct = trim($_POST['conduct'] ?? 'Good');
        if ($sid) {
            try {
                $pdo->prepare(
                    "INSERT INTO report_cards (student_id,academic_year_id,teacher_comment,conduct,status,generated_at)
                     VALUES (?,?,'','',  'draft',NOW())
                     ON DUPLICATE KEY UPDATE teacher_comment=?,conduct=?,updated_at=NOW()"
                )->execute([$sid,$ayId,$comment,$conduct]);
                flash('success','Comment saved for '.($pdo->query("SELECT CONCAT(first_name,' ',last_name) FROM students WHERE id=$sid")->fetchColumn()?:'student').'.');
            } catch (Throwable $e) { flash('error','Failed: '.$e->getMessage()); }
        }
    } elseif ($action === 'save_bulk_comments') {
        $comments = $_POST['comments'] ?? [];
        $conducts = $_POST['conducts'] ?? [];
        $saved = 0;
        foreach ($comments as $sid => $comment) {
            $sid     = (int)$sid;
            $comment = trim($comment);
            $conduct = trim($conducts[$sid] ?? 'Good');
            if ($sid && $comment) {
                try {
                    $pdo->prepare(
                        "INSERT INTO report_cards (student_id,academic_year_id,teacher_comment,conduct,status,generated_at)
                         VALUES (?,?,?,?,'draft',NOW())
                         ON DUPLICATE KEY UPDATE teacher_comment=VALUES(teacher_comment),conduct=VALUES(conduct),updated_at=NOW()"
                    )->execute([$sid,$ayId,$comment,$conduct]);
                    $saved++;
                } catch (Throwable $e) {}
            }
        }
        flash('success',"Saved comments for $saved student".($saved!==1?'s':'').'.');
    }
    redirect(BASE_URL.'/portal/teacher/class_reports.php?class_id='.$classId.'&tab='.$tab);
}

// ── Students ──────────────────────────────────────────────────
$students = $pdo->query(
    "SELECT s.*,g.name grade_name FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.current_class_id=$classId AND s.status='Active'
     ORDER BY s.last_name,s.first_name"
)->fetchAll();
$studentIds = array_column($students,'id');
$totalStudents = count($students);

// ── ATTENDANCE tab ────────────────────────────────────────────
$attByStudent = [];
$attTotals    = ['present'=>0,'absent'=>0,'late'=>0,'total'=>0];
if (!empty($studentIds)) {
    try {
        $in = implode(',',array_map('intval',$studentIds));
        $attRows = $pdo->query(
            "SELECT student_id,
                    SUM(status='Present') p,
                    SUM(status='Absent')  a,
                    SUM(status='Late')    l,
                    COUNT(*) t
             FROM attendance
             WHERE class_id=$classId AND academic_year_id=$ayId AND student_id IN ($in)
             GROUP BY student_id"
        )->fetchAll();
        foreach ($attRows as $r) {
            $attByStudent[$r['student_id']] = $r;
            $attTotals['present'] += $r['p'];
            $attTotals['absent']  += $r['a'];
            $attTotals['late']    += $r['l'];
            $attTotals['total']   += $r['t'];
        }
    } catch (Throwable $e) {}
}

// Last 10 days attendance
try {
    $recentDays = $pdo->query(
        "SELECT date,
                SUM(status='Present') p,
                SUM(status='Absent')  a,
                COUNT(*) t
         FROM attendance
         WHERE class_id=$classId AND academic_year_id=$ayId
         GROUP BY date ORDER BY date DESC LIMIT 10"
    )->fetchAll();
    $recentDays = array_reverse($recentDays);
} catch (Throwable $e) { $recentDays=[]; }

// ── PERFORMANCE tab ───────────────────────────────────────────
$subjectPerf = [];
try {
    $subjectPerf = $pdo->query(
        "SELECT sub.name subject_name,
                ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
                SUM(asc2.marks_obtained/asc2.max_marks*100 >= 50) passing,
                COUNT(DISTINCT asc2.student_id) students
         FROM assessment_scores asc2
         JOIN subjects sub ON sub.id=asc2.subject_id
         WHERE asc2.class_id=$classId AND asc2.academic_year_id=$ayId
           AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
         GROUP BY sub.id,sub.name ORDER BY avg_pct DESC"
    )->fetchAll();
} catch (Throwable $e) {}

$studentPerf = [];
try {
    $studentPerf = $pdo->query(
        "SELECT asc2.student_id,
                ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
                COUNT(DISTINCT asc2.subject_id) subjects
         FROM assessment_scores asc2
         WHERE asc2.class_id=$classId AND asc2.academic_year_id=$ayId
           AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
         GROUP BY asc2.student_id"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}

// ── COMMENTS tab ─────────────────────────────────────────────
$existingComments = [];
try {
    $rc = $pdo->query(
        "SELECT student_id,teacher_comment,conduct FROM report_cards
         WHERE academic_year_id=$ayId AND student_id IN (".implode(',',array_map('intval',$studentIds) ?: [0]).")"
    )->fetchAll();
    foreach ($rc as $r) $existingComments[$r['student_id']] = $r;
} catch (Throwable $e) {}

$conductOptions = ['Excellent','Very Good','Good','Fair','Needs Improvement'];
$maxSubjAvg = max(array_column($subjectPerf,'avg_pct') ?: [1]);

function pColor(float $v): string {
    return $v >= 70 ? 'var(--green)' : ($v >= 50 ? 'var(--warning)' : 'var(--error)');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Class Reports — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>Class Reports</h1>
      <p><?= e($classInfo['name']) ?> &mdash; <?= e($classInfo['grade_name']) ?> &mdash; <?= e($ay) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/portal/teacher/class_dashboard.php?class_id=<?= $classId ?>" class="button button-secondary">← Class Dashboard</a>
    </div>
  </div>

  <!-- Class switcher -->
  <?php if (count($allMyClasses)>1): ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
    <?php foreach($allMyClasses as $c): ?>
    <a href="?class_id=<?=$c['id']?>&tab=<?=$tab?>" style="padding:5px 12px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:600;border:1.5px solid <?=$classId==$c['id']?'var(--primary)':'var(--line)'?>;background:<?=$classId==$c['id']?'var(--primary)':'#fff'?>;color:<?=$classId==$c['id']?'#fff':'var(--ink2)'?>;text-decoration:none"><?=e($c['name'])?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Summary metrics -->
  <div class="metric-grid" style="margin-bottom:20px">
    <div class="metric-card"><div class="metric-top"><span>Students</span><div class="metric-icon">🎓</div></div><strong><?=$totalStudents?></strong></div>
    <div class="metric-card <?=$attTotals['total']>0&&($attTotals['present']/$attTotals['total'])<0.8?'finance-metrics':''?>">
      <div class="metric-top"><span>Year Attendance</span><div class="metric-icon">📆</div></div>
      <strong><?=$attTotals['total']>0?round($attTotals['present']/$attTotals['total']*100,1).'%':'—'?></strong>
      <small><i></i><?=$attTotals['present']?> / <?=$attTotals['total']?> days</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Class Average</span><div class="metric-icon">📊</div></div>
      <?php $ca=count($studentPerf)>0?round(array_sum($studentPerf)/count($studentPerf),1):null; ?>
      <strong style="color:<?=$ca?pColor($ca):'inherit'?>"><?=$ca?$ca.'%':'—'?></strong>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Comments</span><div class="metric-icon">💬</div></div>
      <strong><?=count($existingComments)?> / <?=$totalStudents?></strong>
      <small><i></i>Report card comments</small>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar" style="margin-bottom:16px">
    <a href="?class_id=<?=$classId?>&tab=attendance"  class="tab-btn <?=$tab==='attendance' ?'active':''?>">📆 Attendance</a>
    <a href="?class_id=<?=$classId?>&tab=performance" class="tab-btn <?=$tab==='performance'?'active':''?>">📊 Performance</a>
    <a href="?class_id=<?=$classId?>&tab=progress"    class="tab-btn <?=$tab==='progress'   ?'active':''?>">📈 Student Progress</a>
    <a href="?class_id=<?=$classId?>&tab=comments"    class="tab-btn <?=$tab==='comments'   ?'active':''?>">💬 Report Comments</a>
  </div>

  <?php if ($tab === 'attendance'): ?>
  <!-- ── ATTENDANCE REPORT ─────────────────────────────────── -->

  <!-- Recent 10 days trend -->
  <?php if (!empty($recentDays)): ?>
  <div class="panel" style="padding:20px;margin-bottom:16px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📅 Attendance Trend — Last <?=count($recentDays)?> Days</h3>
    <div style="display:flex;align-items:flex-end;gap:6px;height:60px;margin-bottom:8px">
      <?php foreach ($recentDays as $d):
        $rate = $d['t']>0 ? round($d['p']/$d['t']*100) : 0;
        $h = max(6, round($rate/100*54));
        $col = $rate>=90?'var(--green)':($rate>=75?'var(--warning)':'var(--error)');
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
        <span style="font-size:8px;color:var(--ink-soft)"><?=$rate?>%</span>
        <div style="width:100%;height:<?=$h?>px;background:<?=$col?>;border-radius:3px 3px 0 0;opacity:.85"></div>
        <span style="font-size:8px;color:var(--ink-soft)"><?=date('M d',strtotime($d['date']))?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Per-student attendance -->
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th style="color:var(--green)">Present</th><th style="color:var(--error)">Absent</th><th style="color:var(--warning)">Late</th><th>Total</th><th>Rate</th></tr></thead>
      <tbody>
        <?php foreach ($students as $s):
          $att = $attByStudent[$s['id']] ?? ['p'=>0,'a'=>0,'l'=>0,'t'=>0];
          $rate = $att['t']>0 ? round($att['p']/$att['t']*100,1) : null;
          $rateColor = $rate!==null?($rate>=80?'var(--green)':($rate>=70?'var(--warning)':'var(--error)')):'inherit';
        ?>
        <tr>
          <td><strong><?=e($s['first_name'].' '.$s['last_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($s['student_id'])?></div></td>
          <td style="color:var(--green);font-weight:700"><?=$att['p']?></td>
          <td style="color:<?=$att['a']>0?'var(--error)':'var(--ink-faint)'?>;font-weight:700"><?=$att['a']?$att['a']:'—'?></td>
          <td style="color:var(--warning)"><?=$att['l']?$att['l']:'—'?></td>
          <td class="muted"><?=$att['t']?></td>
          <td><span style="font-weight:700;color:<?=$rateColor?>"><?=$rate!==null?$rate.'%':'—'?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:flex-end;margin-top:10px">
    <a href="<?=BASE_URL?>/admin/reports.php?export=csv&type=attendance&class_id=<?=$classId?>" class="button button-secondary button-sm">📥 Export CSV</a>
  </div>

  <?php elseif ($tab === 'performance'): ?>
  <!-- ── SUBJECT PERFORMANCE ──────────────────────────────── -->
  <div class="panel" style="padding:20px;margin-bottom:16px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📚 Performance by Subject</h3>
    <?php if (empty($subjectPerf)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No approved marks yet for this class.</p>
    <?php else: foreach ($subjectPerf as $s):
      $w = $maxSubjAvg>0?round($s['avg_pct']/$maxSubjAvg*100):0;
      $col = pColor($s['avg_pct']);
      $passPct = $s['students']>0?round($s['passing']/$s['students']*100):0;
    ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?=e($s['subject_name'])?> <span style="color:var(--ink-soft)">(<?=$s['students']?> students)</span></span>
        <div style="display:flex;gap:12px">
          <span>Pass: <strong style="color:<?=$passPct>=50?'var(--green)':'var(--error)' ?>"><?=$passPct?>%</strong></span>
          <strong style="color:<?=$col?>"><?=$s['avg_pct']?>%</strong>
        </div>
      </div>
      <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?=$w?>%;height:100%;background:<?=$col?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <?php elseif ($tab === 'progress'): ?>
  <!-- ── STUDENT PROGRESS ─────────────────────────────────── -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Average</th>
          <th>Grade</th>
          <th>Attendance</th>
          <th>Discipline</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $discByStudent = [];
        try {
            $disc = $pdo->query("SELECT student_id,COUNT(*) cnt FROM discipline_records WHERE academic_year_id=$ayId AND student_id IN (".implode(',',array_map('intval',$studentIds)?:[0]).") GROUP BY student_id");
            foreach ($disc->fetchAll() as $d) $discByStudent[$d['student_id']]=$d['cnt'];
        } catch (Throwable $e) {}

        foreach ($students as $s):
            $avg = $studentPerf[$s['id']] ?? null;
            $gl  = $avg ? gradeLetter($avg,$ayId) : '—';
            $att = $attByStudent[$s['id']] ?? ['p'=>0,'t'=>0];
            $attRate = $att['t']>0?round($att['p']/$att['t']*100,1):null;
            $disc = $discByStudent[$s['id']] ?? 0;
            $status = 'On Track';
            $statusClass = 'approved';
            if ($avg!==null&&$avg<50) { $status='At Risk'; $statusClass='warning'; }
            if ($attRate!==null&&$attRate<75) { $status='Attendance Alert'; $statusClass='warning'; }
            if (($avg!==null&&$avg<50)&&($attRate!==null&&$attRate<75)) { $status='High Risk'; $statusClass='warning'; }
        ?>
        <tr>
          <td><strong><?=e($s['first_name'].' '.$s['last_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($s['student_id'])?></div></td>
          <td><strong style="color:<?=$avg?pColor($avg):'var(--ink-faint)'?>"><?=$avg?$avg.'%':'—'?></strong></td>
          <td><strong style="color:<?=in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)'?>"><?=$gl?></strong></td>
          <td><span style="color:<?=$attRate!==null?($attRate>=80?'var(--green)':'var(--error)'):'var(--ink-faint)'?>"><?=$attRate!==null?$attRate.'%':'—'?></span></td>
          <td><?=$disc>0?"<span style='color:var(--warning);font-weight:600'>$disc incident".($disc!=1?'s':'')."</span>":'<span class="muted">—</span>'?></td>
          <td><span class="status <?=$statusClass?>" style="font-size:11px"><?=$status?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'comments'): ?>
  <!-- ── REPORT CARD COMMENTS ─────────────────────────────── -->
  <div class="alert alert-info" style="margin-bottom:16px">
    💬 These comments will appear on each student's report card as the Class Teacher's remark.
    <?php if(count($existingComments)>0):?> <strong><?=count($existingComments)?></strong> of <strong><?=$totalStudents?></strong> saved.<?php endif;?>
  </div>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="save_bulk_comments"/>
    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach ($students as $s):
        $existing = $existingComments[$s['id']] ?? null;
        $savedComment = $existing['teacher_comment'] ?? '';
        $savedConduct = $existing['conduct'] ?? 'Good';
        $ini2 = strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1));
        $avg2 = $studentPerf[$s['id']] ?? null;
        $gl2  = $avg2 ? gradeLetter($avg2,$ayId) : '—';
      ?>
      <div class="panel" style="padding:16px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap">
          <div class="avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0"><?=e($ini2)?></div>
          <div style="flex:1">
            <strong><?=e($s['first_name'].' '.$s['last_name'])?></strong>
            <span style="font-size:12px;color:var(--ink-soft);margin-left:8px"><?=e($s['student_id'])?></span>
          </div>
          <?php if($avg2):?>
          <span style="font-size:12px;font-weight:700;color:<?=pColor($avg2)?>"><?=$avg2?>% · <?=$gl2?></span>
          <?php endif;?>
          <select name="conducts[<?=$s['id']?>]" style="padding:6px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:12.5px;font-family:inherit">
            <?php foreach($conductOptions as $opt):?><option value="<?=$opt?>" <?=$savedConduct===$opt?'selected':''?>><?=$opt?></option><?php endforeach;?>
          </select>
        </div>
        <textarea name="comments[<?=$s['id']?>]" rows="2"
                  placeholder="Write a comment for <?=e($s['first_name'])?>…"
                  style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"><?=e($savedComment)?></textarea>
        <?php if($savedComment):?><div style="font-size:11px;color:var(--green);margin-top:4px">✓ Comment saved</div><?php endif;?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button type="submit" class="button button-primary">💾 Save All Comments</button>
    </div>
  </form>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
