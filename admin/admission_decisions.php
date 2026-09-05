<?php
$pageTitle='Admission Decisions'; $activeAdmin='admissions_mgr';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['principal','registrar','super_admin']);
$pdo=db(); $ayId=currentAcademicYearId();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $appId=(int)($_POST['app_id']??0); $action=$_POST['action']??'';
    if($appId&&$action==='admit'){
        $app=$pdo->query("SELECT * FROM applications WHERE id=$appId")->fetch();
        if($app&&$app['status']==='Entrance passed'){
            $sid=generateStudentId(); $admn=generateAdmissionNumber();
            $gradeId=(int)($pdo->query("SELECT id FROM grades WHERE name='".$app['grade_applying_for']."' LIMIT 1")->fetchColumn()??0);
            $pdo->prepare("INSERT INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,email,current_address,county,current_grade_id,academic_year_id,status,admission_date,application_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$sid,$admn,$app['first_name'],$app['middle_name'],$app['last_name'],$app['gender'],$app['date_of_birth'],$app['phone'],$app['email'],$app['current_address'],$app['county'],$gradeId?:null,$ayId,'Active',date('Y-m-d'),$appId]);
            $pdo->prepare("UPDATE applications SET status='Admitted',final_decision='Admitted',decision_by=?,decision_at=NOW() WHERE id=?")->execute([currentUser()['id'],$appId]);
            auditLog('admit','admissions','application',$appId,'','Student created: '.$sid);
            flash('success','Student admitted. Student ID: '.$sid.', Admission #: '.$admn);
        }
    } elseif($appId&&$action==='reject'){
        $pdo->prepare("UPDATE applications SET status='Rejected',final_decision='Rejected',decision_by=?,decision_at=NOW() WHERE id=?")->execute([currentUser()['id'],$appId]);
        flash('success','Application rejected.');
    }
    redirect(BASE_URL.'/admin/admission_decisions.php');
}

$apps=$pdo->query("SELECT * FROM applications WHERE status IN ('Entrance passed','Entrance completed','Admitted','Rejected') ORDER BY updated_at DESC LIMIT 50")->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">Admissions <span></span></div><h1>Admission Decisions</h1><p>Review entrance results and make final admission decisions.</p></div></div>
<div class="table-wrap"><table>
  <thead><tr><th>Applicant</th><th>App #</th><th>Grade</th><th>Exam Score</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if(empty($apps)):?><tr><td colspan="6" style="text-align:center;padding:32px;color:var(--ink-faint)">No applications ready for final decision.</td></tr>
    <?php else: foreach($apps as $app): $ini=strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1));?>
    <tr>
      <td><div class="person"><div class="avatar-sm"><?=e($ini)?></div><strong><?=e($app['first_name'].' '.$app['last_name'])?></strong></div></td>
      <td class="muted"><?=e($app['application_number'])?></td>
      <td><?=e($app['grade_applying_for'])?></td>
      <td><?=$app['entrance_score']!==null?fmtPct((float)$app['entrance_score']):'—'?></td>
      <td><?=statusBadge($app['status'])?></td>
      <td>
        <?php if($app['status']==='Entrance passed'):?>
        <div style="display:flex;gap:6px">
          <form method="post" style="display:inline" onsubmit="return confirm('Admit this student? A student record will be created.')">
            <?=csrfField()?><input type="hidden" name="app_id" value="<?=$app['id']?>"/><input type="hidden" name="action" value="admit"/>
            <button type="submit" class="button button-success button-sm">✓ Admit Student</button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Reject this application?')">
            <?=csrfField()?><input type="hidden" name="app_id" value="<?=$app['id']?>"/><input type="hidden" name="action" value="reject"/>
            <button type="submit" class="button button-danger button-sm">✗ Reject</button>
          </form>
          <a href="<?=BASE_URL?>/letters/entrance_letter.php?id=<?=$app['id']?>" class="filter-button button-sm" target="_blank">📄 Letter</a>
        </div>
        <?php elseif($app['status']==='Admitted'):?>
        <span class="status approved">Admitted ✓</span>
        <?php elseif($app['status']==='Rejected'):?>
        <span class="status warning">Rejected</span>
        <?php endif;?>
      </td>
    </tr>
    <?php endforeach; endif;?>
  </tbody>
</table></div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
