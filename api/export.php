<?php
// ============================================================
// Unified Export Endpoint — KARN HIGH SCHOOL
// URL: /api/export.php?type=TYPE&format=pdf|excel|csv&ay_id=N
//
// Supported types:
//   students, enrollment, demographics, admissions, transfers,
//   graduation, promotion, payments, outstanding, expenses,
//   attendance, class_enrollment, marks_summary, broadsheet,
//   discipline, teachers, staff, staff_attendance,
//   cashbook, library_books, library_transactions, results
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/export_helper.php';
requireAuth();
requireStaff();

$pdo    = db();
$type   = trim($_GET['type']   ?? '');
$format = trim($_GET['format'] ?? 'excel'); // pdf | excel | csv
$ayId   = (int)($_GET['ay_id'] ?? currentAcademicYearId());
$classId= (int)($_GET['class_id'] ?? 0);
$month  = trim($_GET['month']  ?? '');

if (!in_array($format, ['pdf','excel','csv'])) $format = 'excel';

$ay = '';
try { $ay = $pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn() ?: currentAcademicYearName(); }
catch (Throwable $e) { $ay = currentAcademicYearName(); }

// ── Helper: safe fetch ────────────────────────────────────────
function safeQuery(PDO $pdo, string $sql, array $params = []): array {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_NUM) ?: [];
    } catch (Throwable $e) { return []; }
}

// ── Report definitions ────────────────────────────────────────
// Each returns ['title', 'subtitle', 'headers', 'rows', 'numCols']
function getReportData(string $type, PDO $pdo, int $ayId, string $ay, int $classId, string $month): array
{
    $numCols = [];

    switch ($type) {

        // ── STUDENTS ────────────────────────────────────────
        case 'students':
            // Support grade_id, status, ay_id filters passed via GET
            $sGradeId  = (int)($_GET['grade_id'] ?? 0);
            $sStatus   = trim($_GET['status'] ?? '');
            $sAyId     = (int)($_GET['ay_id'] ?? $ayId);
            $sWhere    = ['1=1'];
            $sParams   = [];
            if ($sAyId)   { $sWhere[] = 's.academic_year_id=?';  $sParams[] = $sAyId;  }
            if ($sGradeId){ $sWhere[] = 's.current_grade_id=?';  $sParams[] = $sGradeId; }
            if ($sStatus) { $sWhere[] = 's.status=?';             $sParams[] = $sStatus;  }
            $sWsql = implode(' AND ', $sWhere);

            // Grade / AY name for subtitle
            $sGradeName = $sGradeId
                ? ($pdo->query("SELECT name FROM grades WHERE id=$sGradeId")->fetchColumn() ?: '')
                : '';
            $sAyName = $sAyId
                ? ($pdo->query("SELECT name FROM academic_years WHERE id=$sAyId")->fetchColumn() ?: $ay)
                : 'All Years';
            $sSub = trim(($sGradeName ? $sGradeName.' — ' : '').($sStatus ? $sStatus.' — ' : '').$sAyName);

            $headers = ['Student ID','Admission #','First Name','Last Name','Gender','Grade','Class','Status','Admission Date','Phone','County','Email'];
            $rows = safeQuery($pdo,
                "SELECT s.student_id,s.admission_number,s.first_name,s.last_name,
                        s.gender,COALESCE(g.name,'—'),COALESCE(c.name,'—'),s.status,
                        COALESCE(DATE_FORMAT(s.admission_date,'%d %b %Y'),'—'),
                        COALESCE(s.phone,'—'),COALESCE(s.county,'—'),COALESCE(s.email,'—')
                 FROM students s
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 LEFT JOIN classes c ON c.id=s.current_class_id
                 WHERE $sWsql ORDER BY g.sequence,s.last_name,s.first_name",
                $sParams);
            return ['title'=>'Student Directory','subtitle'=>$sSub,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── ENROLLMENT ──────────────────────────────────────
        case 'enrollment':
            $headers = ['Grade','Total','Active','Male','Female','New This Year'];
            $rows = safeQuery($pdo,
                "SELECT g.name,COUNT(s.id),SUM(s.status='Active'),
                        SUM(s.gender='Male'),SUM(s.gender='Female'),
                        SUM(YEAR(s.admission_date)=YEAR(CURDATE()))
                 FROM students s JOIN grades g ON g.id=s.current_grade_id
                 WHERE s.academic_year_id=?
                 GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence",
                [$ayId]);
            return ['title'=>'Enrollment by Grade','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[1,2,3,4,5]];

        // ── DEMOGRAPHICS ────────────────────────────────────
        case 'demographics':
            $headers = ['Student ID','First Name','Last Name','Gender','Date of Birth','County','District','Community','Nationality','Phone','Email'];
            $rows = safeQuery($pdo,
                "SELECT s.student_id,s.first_name,s.last_name,s.gender,
                        COALESCE(s.date_of_birth,'—'),COALESCE(s.county,'—'),
                        COALESCE(s.district,'—'),COALESCE(s.community,'—'),
                        COALESCE(s.nationality,'—'),COALESCE(s.phone,'—'),COALESCE(s.email,'—')
                 FROM students s WHERE s.academic_year_id=? AND s.status='Active'
                 ORDER BY s.last_name",
                [$ayId]);
            return ['title'=>'Student Demographics','subtitle'=>'Active Students — '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── ADMISSIONS ──────────────────────────────────────
        case 'admissions':
            $headers = ['App #','First Name','Last Name','DOB','Gender','County','Grade Applying','Phone','Guardian','G.Phone','Status','Academic Year','Submitted','Exam Date'];
            $rows = safeQuery($pdo,
                "SELECT a.application_number,a.first_name,a.last_name,
                        COALESCE(a.date_of_birth,'—'),a.gender,COALESCE(a.county,'—'),
                        a.grade_applying_for,COALESCE(a.phone,'—'),
                        COALESCE(a.guardian_name,'—'),COALESCE(a.guardian_phone,'—'),
                        a.status,COALESCE(ay.name,'—'),
                        DATE_FORMAT(a.created_at,'%d %b %Y'),
                        COALESCE(DATE_FORMAT(a.entrance_exam_date,'%d %b %Y'),'—')
                 FROM applications a LEFT JOIN academic_years ay ON ay.id=a.academic_year_id
                 WHERE a.academic_year_id=? ORDER BY a.created_at DESC",
                [$ayId]);
            return ['title'=>'Admissions Applications','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── TRANSFERS ───────────────────────────────────────
        case 'transfers':
            $headers = ['Student','Student ID','Type','Effective Date','Destination','Reason','Status','Academic Year'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        sm.movement_type,
                        COALESCE(DATE_FORMAT(sm.effective_date,'%d %b %Y'),'—'),
                        COALESCE(sm.destination_school,'—'),
                        COALESCE(sm.reason,'—'),sm.status,ay.name
                 FROM student_movements sm
                 JOIN students s ON s.id=sm.student_id
                 JOIN academic_years ay ON ay.id=sm.academic_year_id
                 WHERE sm.academic_year_id=? ORDER BY sm.effective_date DESC",
                [$ayId]);
            return ['title'=>'Student Transfers & Movements','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── GRADUATION ──────────────────────────────────────
        case 'graduation':
            $headers = ['Student','Student ID','Status','Overall Avg %','Certificate #','Graduation Date','Academic Year'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        gr.status,COALESCE(gr.overall_average,'—'),
                        COALESCE(gr.certificate_number,'—'),
                        COALESCE(DATE_FORMAT(gr.graduation_date,'%d %b %Y'),'—'),
                        ay.name
                 FROM graduation_records gr
                 JOIN students s ON s.id=gr.student_id
                 JOIN academic_years ay ON ay.id=gr.academic_year_id
                 WHERE gr.academic_year_id=? ORDER BY s.last_name",
                [$ayId]);
            return ['title'=>'Graduation Records','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[3]];

        // ── PROMOTION ───────────────────────────────────────
        case 'promotion':
            $headers = ['Student','Student ID','From Grade','To Grade','Status','Academic Year','Processed Date'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        COALESCE(gf.name,'—'),COALESCE(gt.name,'—'),
                        pr.status,ay.name,
                        COALESCE(DATE_FORMAT(pr.processed_at,'%d %b %Y'),'—')
                 FROM promotion_records pr
                 JOIN students s ON s.id=pr.student_id
                 LEFT JOIN grades gf ON gf.id=pr.from_grade_id
                 LEFT JOIN grades gt ON gt.id=pr.to_grade_id
                 JOIN academic_years ay ON ay.id=pr.academic_year_id
                 WHERE pr.academic_year_id=? ORDER BY s.last_name",
                [$ayId]);
            return ['title'=>'Promotion Records','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── PAYMENTS ────────────────────────────────────────
        case 'payments':
            $headers = ['Receipt #','Student','Student ID','Grade','Amount','Currency','Method','Date','Academic Year'];
            $rows = safeQuery($pdo,
                "SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name),
                        s.student_id,COALESCE(g.name,'—'),
                        p.amount,p.currency,p.payment_method,
                        DATE_FORMAT(p.payment_date,'%d %b %Y'),ay.name
                 FROM payments p
                 JOIN students s ON s.id=p.student_id
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 LEFT JOIN academic_years ay ON ay.id=p.academic_year_id
                 WHERE p.academic_year_id=? ORDER BY p.payment_date DESC",
                [$ayId]);
            return ['title'=>'Payment Records','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[4]];

        // ── OUTSTANDING FEES ─────────────────────────────────
        case 'outstanding':
            $headers = ['Student ID','Name','Grade','Total Due (LRD)','Total Paid (LRD)','Balance (LRD)','% Paid'];
            $rows = safeQuery($pdo,
                "SELECT s.student_id,CONCAT(s.first_name,' ',s.last_name),g.name,
                        COALESCE(f.due,0),COALESCE(p.paid,0),
                        COALESCE(f.due,0)-COALESCE(p.paid,0),
                        CASE WHEN COALESCE(f.due,0)>0 THEN
                          CONCAT(ROUND(COALESCE(p.paid,0)/COALESCE(f.due,1)*100,1),'%')
                        ELSE '—' END
                 FROM students s
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 LEFT JOIN (SELECT grade_id,SUM(amount) due FROM fee_structures
                             WHERE academic_year_id=? AND currency='LRD' AND is_active=1
                             GROUP BY grade_id) f ON f.grade_id=s.current_grade_id
                 LEFT JOIN (SELECT student_id,SUM(amount) paid FROM payments
                             WHERE academic_year_id=? AND currency='LRD'
                             GROUP BY student_id) p ON p.student_id=s.id
                 WHERE s.academic_year_id=? AND s.status='Active'
                   AND COALESCE(f.due,0)-COALESCE(p.paid,0) > 0
                 ORDER BY (COALESCE(f.due,0)-COALESCE(p.paid,0)) DESC",
                [$ayId,$ayId,$ayId]);
            return ['title'=>'Outstanding Fee Balances','subtitle'=>'Active Students — '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[3,4,5]];

        // ── EXPENSES ─────────────────────────────────────────
        case 'expenses':
            $headers = ['Title','Category','Amount','Currency','Requested By','Status','Date'];
            $rows = safeQuery($pdo,
                "SELECT er.title,COALESCE(er.category,'—'),er.amount,er.currency,
                        COALESCE(u.name,'—'),er.status,
                        DATE_FORMAT(er.created_at,'%d %b %Y')
                 FROM expense_requests er
                 LEFT JOIN users u ON u.id=er.requested_by
                 WHERE er.academic_year_id=? ORDER BY er.created_at DESC",
                [$ayId]);
            return ['title'=>'Expense Requests','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[2]];

        // ── ATTENDANCE ───────────────────────────────────────
        case 'attendance':
            $where  = ['a.academic_year_id=?'];
            $params = [$ayId];
            if ($classId) { $where[] = 'a.class_id=?'; $params[] = $classId; }
            $wsql = implode(' AND ', $where);
            $headers = ['Student','Student ID','Grade','Class','Date','Status','Remarks'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        g.name,c.name,DATE_FORMAT(a.date,'%d %b %Y'),
                        a.status,COALESCE(a.remarks,'—')
                 FROM attendance a
                 JOIN students s ON s.id=a.student_id
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 LEFT JOIN classes c ON c.id=a.class_id
                 WHERE $wsql ORDER BY a.date DESC,s.last_name",
                $params);
            return ['title'=>'Attendance Records','subtitle'=>'Academic Year: '.$ay.($classId?' — Class ID '.$classId:''),'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── CLASS ENROLLMENT ─────────────────────────────────
        case 'class_enrollment':
            $headers = ['Class','Grade','Total Students','Male','Female','Active'];
            $rows = safeQuery($pdo,
                "SELECT c.name,g.name,COUNT(s.id),
                        SUM(s.gender='Male'),SUM(s.gender='Female'),SUM(s.status='Active')
                 FROM students s
                 JOIN classes c ON c.id=s.current_class_id
                 JOIN grades g ON g.id=s.current_grade_id
                 WHERE s.academic_year_id=?
                 GROUP BY c.id,c.name,g.name,g.sequence ORDER BY g.sequence,c.name",
                [$ayId]);
            return ['title'=>'Class Enrollment Summary','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[2,3,4,5]];

        // ── MARKS SUMMARY ────────────────────────────────────
        case 'marks_summary':
            $where  = ['asc2.academic_year_id=?','asc2.max_marks>0'];
            $params = [$ayId];
            if ($classId) { $where[] = 'asc2.class_id=?'; $params[] = $classId; }
            $wsql = implode(' AND ', $where);
            $headers = ['Student','Student ID','Grade','Subject','Avg %','Grade Letter'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        COALESCE(g.name,'—'),sub.name,
                        CONCAT(ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1),'%'),
                        COALESCE(MAX(asc2.grade_letter),'—')
                 FROM assessment_scores asc2
                 JOIN students s ON s.id=asc2.student_id
                 JOIN subjects sub ON sub.id=asc2.subject_id
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 WHERE $wsql
                 GROUP BY s.id,sub.id ORDER BY s.last_name,sub.name",
                $params);
            return ['title'=>'Marks Summary','subtitle'=>'Academic Year: '.$ay.($classId?' — Class ID '.$classId:''),'headers'=>$headers,'rows'=>$rows,'numCols'=>[4]];

        // ── BROADSHEET ───────────────────────────────────────
        case 'broadsheet':
            if (!$classId) return ['title'=>'Class Broadsheet','subtitle'=>'No class selected','headers'=>['Note'],'rows'=>[['Please select a class']],'numCols'=>[]];
            $className = $pdo->query("SELECT name FROM classes WHERE id=$classId")->fetchColumn() ?: 'Class';
            // Get subjects
            $subs = safeQuery($pdo,
                "SELECT DISTINCT s.id,s.name FROM assessment_scores asc2
                 JOIN subjects s ON s.id=asc2.subject_id
                 WHERE asc2.class_id=? AND asc2.academic_year_id=? ORDER BY s.name",
                [$classId,$ayId]);
            if (empty($subs)) return ['title'=>'Broadsheet','subtitle'=>'No marks data','headers'=>['Note'],'rows'=>[['No marks entered']],'numCols'=>[]];
            $subNames = array_column($subs, 1);
            $subIds   = array_column($subs, 0);
            $headers  = array_merge(['#','Student','Student ID'], $subNames, ['Overall Avg','Grade','Rank']);
            $numCols  = array_merge([0], range(3, 3+count($subs)-1), [3+count($subs), 3+count($subs)+1, 3+count($subs)+2]);
            // Get all scores
            $scoreData = [];
            $scoreRows = safeQuery($pdo,
                "SELECT asc2.student_id,asc2.subject_id,
                        ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
                 FROM assessment_scores asc2
                 WHERE asc2.class_id=? AND asc2.academic_year_id=? AND asc2.max_marks>0
                 GROUP BY asc2.student_id,asc2.subject_id",
                [$classId,$ayId]);
            foreach ($scoreRows as $sr) $scoreData[$sr[0]][$sr[1]] = $sr[2];
            // Get students
            $students = safeQuery($pdo,
                "SELECT id,student_id,CONCAT(first_name,' ',last_name) name
                 FROM students WHERE current_class_id=? AND status='Active'
                 ORDER BY last_name,first_name",
                [$classId]);
            // Build rows + compute overall
            $withAvg = [];
            foreach ($students as $st) {
                $subScores = [];
                $total = 0; $cnt = 0;
                foreach ($subIds as $sid) {
                    $v = $scoreData[$st[0]][$sid] ?? '—';
                    $subScores[] = $v;
                    if ($v !== '—') { $total += (float)$v; $cnt++; }
                }
                $overall = $cnt ? round($total/$cnt,1) : 0;
                // Grade letter
                $gl = $overall >= 90?'A+':($overall >= 80?'A':($overall >= 70?'B':($overall >= 60?'C':($overall >= 50?'D':'F'))));
                $withAvg[] = ['id'=>$st[0],'sid'=>$st[1],'name'=>$st[2],'scores'=>$subScores,'overall'=>$overall,'gl'=>$gl];
            }
            usort($withAvg, fn($a,$b) => $b['overall'] <=> $a['overall']);
            $rows = [];
            foreach ($withAvg as $rank => $w) {
                $rows[] = array_merge([$rank+1, $w['name'], $w['sid']], $w['scores'], [$w['overall'].'%', $w['gl'], $rank+1]);
            }
            return ['title'=>'Class Broadsheet','subtitle'=>$className.' — '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>$numCols];

        // ── RESULTS ──────────────────────────────────────────
        case 'results':
            $where  = ['asc2.academic_year_id=?','asc2.status IN (\'approved\',\'published\',\'submitted\')'];
            $params = [$ayId];
            if ($classId) { $where[] = 'asc2.class_id=?'; $params[] = $classId; }
            $wsql = implode(' AND ', $where);
            $headers = ['Student','Student ID','Grade','Subject','Assessment','Marks Obtained','Max Marks','%','Grade','Status'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        COALESCE(g.name,'—'),sub.name,ac.name,
                        asc2.marks_obtained,asc2.max_marks,
                        CONCAT(ROUND(asc2.marks_obtained/asc2.max_marks*100,1),'%'),
                        COALESCE(asc2.grade_letter,'—'),asc2.status
                 FROM assessment_scores asc2
                 JOIN students s ON s.id=asc2.student_id
                 JOIN subjects sub ON sub.id=asc2.subject_id
                 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 WHERE $wsql ORDER BY s.last_name,sub.name,ac.sequence",
                $params);
            return ['title'=>'Assessment Results','subtitle'=>'Academic Year: '.$ay.($classId?' — Class ID '.$classId:''),'headers'=>$headers,'rows'=>$rows,'numCols'=>[5,6,7]];

        // ── DISCIPLINE ───────────────────────────────────────
        case 'discipline':
            $headers = ['Student','Student ID','Grade','Date','Category','Description','Action Taken','Severity','Resolved','Academic Year'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(s.first_name,' ',s.last_name),s.student_id,
                        COALESCE(g.name,'—'),DATE_FORMAT(d.incident_date,'%d %b %Y'),
                        COALESCE(d.category,'—'),COALESCE(d.description,'—'),
                        COALESCE(d.action_taken,'—'),COALESCE(d.severity,'—'),
                        IF(d.resolved,'Yes','No'),ay.name
                 FROM discipline_records d
                 JOIN students s ON s.id=d.student_id
                 LEFT JOIN grades g ON g.id=s.current_grade_id
                 LEFT JOIN academic_years ay ON ay.id=d.academic_year_id
                 WHERE d.academic_year_id=? ORDER BY d.incident_date DESC",
                [$ayId]);
            return ['title'=>'Discipline Records','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── TEACHERS ─────────────────────────────────────────
        case 'teachers':
            $headers = ['Name','Email','Phone','Qualification','Specialization','Department','Status','Join Date'];
            $rows = safeQuery($pdo,
                "SELECT CONCAT(t.first_name,' ',t.last_name),
                        COALESCE(t.email,'—'),COALESCE(t.phone,'—'),
                        COALESCE(t.qualification,'—'),COALESCE(t.specialization,'—'),
                        COALESCE(d.name,'—'),t.status,
                        COALESCE(DATE_FORMAT(t.employment_date,'%d %b %Y'),'—')
                 FROM teachers t LEFT JOIN departments d ON d.id=t.department_id
                 ORDER BY t.last_name");
            return ['title'=>'Teacher Directory','subtitle'=>'All Staff','headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── STAFF ────────────────────────────────────────────
        case 'staff':
            $headers = ['Name','Email','Phone','Role','Status','Last Login'];
            $rows = safeQuery($pdo,
                "SELECT u.name,COALESCE(u.email,'—'),COALESCE(u.phone,'—'),
                        r.label,IF(u.is_active,'Active','Inactive'),
                        COALESCE(DATE_FORMAT(u.last_login,'%d %b %Y'),'Never')
                 FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.name NOT IN ('student','parent','applicant')
                 ORDER BY r.id,u.name");
            return ['title'=>'Staff Directory','subtitle'=>'All Active Staff Accounts','headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── STAFF ATTENDANCE ─────────────────────────────────
        case 'staff_attendance':
            $mWhere = $month ? "AND DATE_FORMAT(sa.date,'%Y-%m')='".addslashes($month)."'" : '';
            $headers = ['Staff','Role','Date','Status','Remarks'];
            $rows = safeQuery($pdo,
                "SELECT u.name,r.label,DATE_FORMAT(sa.date,'%d %b %Y'),
                        sa.status,COALESCE(sa.remarks,'—')
                 FROM staff_attendance sa
                 JOIN users u ON u.id=sa.user_id
                 JOIN roles r ON r.id=u.role_id
                 WHERE 1=1 $mWhere ORDER BY sa.date DESC,u.name");
            return ['title'=>'Staff Attendance','subtitle'=>$month?'Month: '.$month:'All Months','headers'=>$headers,'rows'=>$rows,'numCols'=>[]];

        // ── CASHBOOK ─────────────────────────────────────────
        case 'cashbook':
            $headers = ['Date','Type','Category','Description','Amount','Currency','Method','Reference','Recorded By'];
            $rows = safeQuery($pdo,
                "SELECT DATE_FORMAT(ce.entry_date,'%d %b %Y'),ce.entry_type,
                        COALESCE(ce.category,'—'),COALESCE(ce.description,'—'),
                        ce.amount,ce.currency,COALESCE(ce.payment_method,'—'),
                        COALESCE(ce.reference_number,'—'),COALESCE(u.name,'—')
                 FROM cashbook_entries ce
                 LEFT JOIN users u ON u.id=ce.recorded_by
                 WHERE ce.academic_year_id=? ORDER BY ce.entry_date DESC",
                [$ayId]);
            return ['title'=>'Cashbook Entries','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[4]];

        // ── LIBRARY BOOKS ────────────────────────────────────
        case 'library_books':
            $headers = ['ISBN','Title','Author','Category','Publisher','Year','Total Copies','Available','Condition','Status'];
            $rows = safeQuery($pdo,
                "SELECT COALESCE(lb.isbn,'—'),lb.title,COALESCE(lb.author,'—'),
                        COALESCE(lb.category,'—'),COALESCE(lb.publisher,'—'),
                        COALESCE(lb.publication_year,'—'),
                        COALESCE(lb.total_copies,0),COALESCE(lb.available_copies,0),
                        COALESCE(lb.condition_status,'—'),lb.status
                 FROM library_books lb ORDER BY lb.title");
            return ['title'=>'Library Book Catalog','subtitle'=>'All Books','headers'=>$headers,'rows'=>$rows,'numCols'=>[6,7]];

        // ── LIBRARY TRANSACTIONS ─────────────────────────────
        case 'library_transactions':
            $headers = ['Book Title','Borrower','Student ID','Issue Date','Due Date','Return Date','Status','Fine (LRD)'];
            $rows = safeQuery($pdo,
                "SELECT lb.title,CONCAT(s.first_name,' ',s.last_name),
                        s.student_id,
                        DATE_FORMAT(lt.issued_at,'%d %b %Y'),
                        DATE_FORMAT(lt.due_date,'%d %b %Y'),
                        COALESCE(DATE_FORMAT(lt.returned_at,'%d %b %Y'),'—'),
                        lt.status,COALESCE(lt.fine_amount,0)
                 FROM library_transactions lt
                 JOIN library_books lb ON lb.id=lt.book_id
                 JOIN students s ON s.id=lt.student_id
                 WHERE lt.academic_year_id=? ORDER BY lt.issued_at DESC",
                [$ayId]);
            return ['title'=>'Library Transactions','subtitle'=>'Academic Year: '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[7]];

        // ── GUARDIANS ────────────────────────────────────────
        case 'guardians':
            $headers = ['First Name','Last Name','Relationship','Phone','Email','Children Linked','Address'];
            $rows = safeQuery($pdo,
                "SELECT g.first_name,g.last_name,g.relationship,
                        COALESCE(g.phone,'—'),COALESCE(g.email,'—'),
                        (SELECT COUNT(*) FROM student_guardians sg WHERE sg.guardian_id=g.id),
                        COALESCE(g.address,'—')
                 FROM guardians g
                 ORDER BY g.first_name,g.last_name");
            return ['title'=>'Guardian Directory','subtitle'=>'All Registered Guardians','headers'=>$headers,'rows'=>$rows,'numCols'=>[5]];

        // ── PROMOTION LIST ───────────────────────────────────
        case 'promotion_list':
            $gradeIdParam = (int)($_GET['grade_id'] ?? 0);
            $gradeNameP   = $gradeIdParam
                ? ($pdo->query("SELECT name FROM grades WHERE id=$gradeIdParam")->fetchColumn() ?: 'Grade')
                : 'All Grades';
            $where  = ["s.status='Active'"];
            $params = [];
            if ($gradeIdParam) { $where[] = 's.current_grade_id=?'; $params[] = $gradeIdParam; }
            $wsql = implode(' AND ', $where);

            $headers = ['Student ID','First Name','Last Name','Gender','Grade','Class','Avg Mark %','Promotion Status'];
            $rows = safeQuery($pdo,
                "SELECT s.student_id,s.first_name,s.last_name,s.gender,
                        COALESCE(g.name,'—'),COALESCE(c.name,'—'),
                        COALESCE(CONCAT(ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1),'%'),'—'),
                        COALESCE(pr.status,'Pending')
                 FROM students s
                 LEFT JOIN grades g  ON g.id=s.current_grade_id
                 LEFT JOIN classes c ON c.id=s.current_class_id
                 LEFT JOIN assessment_scores asc2
                   ON asc2.student_id=s.id
                   AND asc2.academic_year_id=".intval($ayId)."
                   AND asc2.max_marks>0
                 LEFT JOIN promotion_records pr
                   ON pr.student_id=s.id
                   AND pr.academic_year_id=".intval($ayId)."
                 WHERE $wsql
                 GROUP BY s.id,s.student_id,s.first_name,s.last_name,s.gender,g.name,g.sequence,c.name,pr.status
                 ORDER BY g.sequence,s.last_name,s.first_name",
                $params);
            return ['title'=>'Promotion List','subtitle'=>$gradeNameP.' — '.$ay,'headers'=>$headers,'rows'=>$rows,'numCols'=>[6]];

        default:
            return ['title'=>'Unknown Report','subtitle'=>'','headers'=>['Error'],'rows'=>[['Unknown report type: '.$type]],'numCols'=>[]];
    }
}

// ── Run the export ────────────────────────────────────────────
$data = getReportData($type, $pdo, $ayId, $ay, $classId, $month);

$title    = $data['title'];
$subtitle = $data['subtitle'];
$headers  = $data['headers'];
$rows     = $data['rows'];
$numCols  = $data['numCols'];
$filename = preg_replace('/\s+/','_', strtolower($type)).'_'.str_replace('/','_',$ay).'_'.date('Y-m-d');

switch ($format) {

    case 'pdf':
        $body = buildReportTable('', $headers, $rows, $numCols);
        $count = count($rows);
        $body  = '<p style="font-size:8.5pt;color:#555;margin-bottom:8px">
                    <strong>Total records:</strong> '.$count.'
                    &nbsp;&bull;&nbsp; <strong>Report:</strong> '.htmlspecialchars($title).'
                    &nbsp;&bull;&nbsp; <strong>'.htmlspecialchars($subtitle).'</strong>
                  </p>'.$body;
        pdfReportPage($title, $subtitle, $body, $filename);
        break;

    case 'excel':
        excelExport($filename, $headers, $rows, $title);
        break;

    case 'csv':
    default:
        csvExport($filename, $headers, $rows);
        break;
}
