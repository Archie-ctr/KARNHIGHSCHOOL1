<?php
// ============================================================
// KHSMIS — Core Configuration & Helpers
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'karnhighschool');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL',   '/KARNHIGHSCHOOL');
define('BASE_PATH',  dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH.'/uploads');
define('APP_NAME',   'KHSMIS');

// Suppress browser errors in web context
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    error_reporting(E_ALL);
}

// ── Workflow status constants ──────────────────────────────────
const STATUS_DRAFT      = 'draft';
const STATUS_SUBMITTED  = 'submitted';
const STATUS_REVIEW     = 'under_review';
const STATUS_APPROVED   = 'approved';
const STATUS_RETURNED   = 'returned';
const STATUS_REJECTED   = 'rejected';
const STATUS_PUBLISHED  = 'published';
const STATUS_LOCKED     = 'locked';
const STATUS_RESUBMIT   = 'resubmitted';

// ── PDO Singleton ────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: '.$e->getMessage());
            die('Database connection error. Please try again later.');
        }
    }
    return $pdo;
}

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

// ── Output helpers ───────────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never {
    if (ob_get_level()) ob_end_clean();
    header('Location: '.$url);
    exit;
}
function json_out(mixed $data, int $code=200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'.e(csrfToken()).'">';
}
function verifyCsrf(): void {
    $t = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $t)) {
        http_response_code(403);
        die('Invalid security token.');
    }
}

// ── School settings ───────────────────────────────────────────
function setting(string $key, string $default=''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query('SELECT setting_key,setting_value FROM school_settings')->fetchAll();
            $cache = array_column($rows,'setting_value','setting_key');
        } catch (Throwable $e) { $cache=[]; }
    }
    return $cache[$key] ?? $default;
}

// ── Auth ──────────────────────────────────────────────────────
function currentUser(): ?array { return $_SESSION['user'] ?? null; }
function isLoggedIn(): bool    { return !empty($_SESSION['user']['id']); }
function currentRole(): string { return $_SESSION['user']['role'] ?? ''; }
function currentUserId(): int  { return (int)($_SESSION['user']['id'] ?? 0); }
function hasRole(string|array $roles): bool {
    $r = currentRole();
    return is_array($roles) ? in_array($r, $roles, true) : $r === $roles;
}

// Staff roles that access the /admin portal
const STAFF_ROLES = [
    'sys_admin','super_admin','school_admin','principal','vice_principal','vice_principal_alt',
    'registrar','accountant','finance_officer',
    'teacher','class_teacher',
    'discipline_officer','librarian','ict_officer'
];

function isStaff(): bool         { return hasRole(STAFF_ROLES); }
function isStudent(): bool       { return hasRole('student'); }
function isParent(): bool        { return hasRole('parent'); }
function isSysAdmin(): bool      { return hasRole(['sys_admin','super_admin']); }
function isSchoolAdmin(): bool   { return hasRole(['sys_admin','super_admin','school_admin']); }
function isPrincipal(): bool     { return hasRole(['sys_admin','super_admin','school_admin','principal']); }
function isVicePrincipal(): bool { return hasRole(['sys_admin','super_admin','school_admin','principal','vice_principal','vice_principal_alt']); }
function isRegistrar(): bool     { return hasRole(['sys_admin','super_admin','school_admin','principal','registrar']); }
function isAccountant(): bool    { return hasRole(['sys_admin','super_admin','school_admin','principal','accountant']); }
function isLibrarian(): bool     { return hasRole(['sys_admin','super_admin','school_admin','principal','librarian']); }
function isTeacher(): bool       { return hasRole(['teacher','class_teacher']); }
function isClassTeacher(): bool  { return hasRole('class_teacher'); }
// Legacy compat
function isAcademicDean(): bool  { return isVicePrincipal(); }

// ── Granular permission check (cached per request) ────────────
function can(string $permission): bool {
    static $cache = [];
    $role = currentRole();
    if (!$role) return false;
    $key = $role.':'.$permission;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.name=? AND p.name=?'
        );
        $stmt->execute([$role, $permission]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { $cache[$key]=false; }
    return $cache[$key];
}

// ── canAny: true if user has ANY of the listed permissions ─────
function canAny(array $permissions): bool {
    foreach ($permissions as $p) if (can($p)) return true;
    return false;
}

// ── canAll: true only if user has ALL of the permissions ───────
function canAll(array $permissions): bool {
    foreach ($permissions as $p) if (!can($p)) return false;
    return true;
}

// ── Record-scope helpers ──────────────────────────────────────
// Returns class IDs the current teacher is assigned to
function myClassIds(): array {
    static $ids = null;
    if ($ids !== null) return $ids;
    if (!isTeacher()) return ($ids = []);
    try {
        $t = db()->query("SELECT id FROM teachers WHERE user_id=".currentUserId()." LIMIT 1")->fetchColumn();
        if (!$t) return ($ids=[]);
        $rows = db()->prepare("SELECT DISTINCT class_id FROM teacher_assignments WHERE teacher_id=? AND academic_year_id=?");
        $rows->execute([$t, currentAcademicYearId()]);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $ids=[]; }
    return $ids;
}

// Returns student IDs in teacher's assigned classes
function myStudentIds(): array {
    $cls = myClassIds();
    if (empty($cls)) return [];
    try {
        $in  = implode(',', array_map('intval', $cls));
        $rows= db()->query("SELECT id FROM students WHERE current_class_id IN ($in) AND status='Active'")->fetchAll(PDO::FETCH_COLUMN);
        return $rows;
    } catch (Throwable $e) { return []; }
}

// Returns teacher record for current user
function currentTeacher(): ?array {
    static $t = null;
    if ($t !== null) return $t;
    try { $t = db()->query("SELECT * FROM teachers WHERE user_id=".currentUserId()." LIMIT 1")->fetch() ?: null; }
    catch (Throwable $e) { $t=null; }
    return $t;
}

// ── Academic year helpers ─────────────────────────────────────
function currentAcademicYear(): ?array {
    static $ay = null;
    if ($ay === null) {
        try { $ay = db()->query('SELECT * FROM academic_years WHERE is_current=1 LIMIT 1')->fetch() ?: null; }
        catch (Throwable $e) { $ay=null; }
    }
    return $ay;
}
function currentAcademicYearId(): int  { return (int)(currentAcademicYear()['id'] ?? 0); }
function currentAcademicYearName(): string { return currentAcademicYear()['name'] ?? setting('current_academic_year','2026/2027'); }

// ── Auth guards ───────────────────────────────────────────────
function requireAuth(): void {
    if (!isLoggedIn()) redirect(BASE_URL.'/login.php');
}
function requireStaff(): void {
    requireAuth();
    if (!isStaff()) redirect(BASE_URL.'/login.php?error=access');
}
function requireRole(string|array $roles): void {
    requireAuth();
    if (!hasRole($roles)) {
        if (isStaff()) { http_response_code(403); include BASE_PATH.'/includes/403.php'; exit; }
        redirect(BASE_URL.'/login.php?error=access');
    }
}
function requirePermission(string $perm): void {
    requireAuth();
    if (!can($perm)) {
        if (isStaff()) { http_response_code(403); include BASE_PATH.'/includes/403.php'; exit; }
        redirect(BASE_URL.'/login.php?error=access');
    }
}

// Legacy compat
function isAdminLoggedIn(): bool { return isLoggedIn() && isStaff(); }
function requireAdmin(): void    { requireStaff(); }

// Portal redirect after login
function portalRedirect(): never {
    $role = currentRole();
    $map  = [
        'student'           => BASE_URL.'/portal/student/',
        'parent'            => BASE_URL.'/portal/parent/',
        'teacher'           => BASE_URL.'/portal/teacher/',
        'class_teacher'     => BASE_URL.'/portal/teacher/',
    ];
    redirect($map[$role] ?? BASE_URL.'/admin/index.php');
}

// ── Approval workflow helpers ────────────────────────────────
function createApprovalRequest(
    string $module, string $recordType, int $recordId,
    string $title, string $description='',
    ?string $oldValue=null, ?string $newValue=null,
    string $priority='normal'
): int {
    try {
        db()->prepare("INSERT INTO approval_requests
            (module,record_type,record_id,requested_by,status,priority,title,description,old_value,new_value)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$module,$recordType,$recordId,currentUserId(),'pending',
                      $priority,$title,$description,$oldValue,$newValue]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
}

function countPendingApprovals(): array {
    static $counts = null;
    if ($counts !== null) return $counts;
    $counts = [];
    if (!can('approvals.view')) return $counts;
    try {
        $rows = db()->query("SELECT module, COUNT(*) cnt FROM approval_requests WHERE status='pending' GROUP BY module")->fetchAll();
        foreach ($rows as $r) $counts[$r['module']] = (int)$r['cnt'];
        $counts['_total'] = array_sum($counts);
    } catch (Throwable $e) { $counts=['_total'=>0]; }
    return $counts;
}

// Marks version history recorder
function recordMarksHistory(
    int $scoreId, int $studentId, int $subjectId, int $configId, int $ayId,
    ?float $oldMarks, ?float $newMarks, string $oldStatus, string $newStatus,
    string $reason=''
): void {
    try {
        db()->prepare("INSERT INTO marks_history
            (assessment_score_id,student_id,subject_id,assessment_config_id,academic_year_id,
             old_marks,new_marks,old_status,new_status,changed_by,change_reason)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$scoreId,$studentId,$subjectId,$configId,$ayId,
                      $oldMarks,$newMarks,$oldStatus,$newStatus,currentUserId(),$reason?:null]);
    } catch (Throwable $e) { /* never break on history failure */ }
}

// ── Audit logging ─────────────────────────────────────────────
function auditLog(string $action, string $module, string $recordType='', int $recordId=0, string $old='', string $new=''): void {
    try {
        $u = currentUser();
        db()->prepare("INSERT INTO audit_logs (user_id,user_name,action,module,record_type,record_id,old_value,new_value,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$u['id']??null,$u['name']??'System',$action,$module,$recordType,$recordId?:null,$old?:null,$new?:null,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
    } catch (Throwable $e) {}
}

// ── Notifications ─────────────────────────────────────────────
function notify(int $userId, string $type, string $title, string $message, string $link=''): void {
    try {
        db()->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)")
           ->execute([$userId,$type,$title,$message,$link?:null]);
    } catch (Throwable $e) {}
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type'=>$type,'message'=>$message];
}
function renderFlash(): string {
    if (empty($_SESSION['flash'])) return '';
    $html='';
    foreach ($_SESSION['flash'] as $f) {
        $cls = match($f['type']){'success'=>'alert-success','error'=>'alert-error','warning'=>'alert-warning',default=>'alert-info'};
        $html.='<div class="alert '.$cls.'">'.e($f['message']).'</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ── File upload ───────────────────────────────────────────────
function uploadFile(array $file, string $subDir, array $allowed=['jpg','jpeg','png','pdf'], int $maxMB=5): string|false {
    $maxBytes=$maxMB*1024*1024;
    if ($file['error']!==UPLOAD_ERR_OK) return false;
    if ($file['size']>$maxBytes) return false;
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,$allowed,true)) return false;
    $dir=UPLOAD_DIR.'/'.$subDir;
    if (!is_dir($dir)) mkdir($dir,0755,true);
    $name=bin2hex(random_bytes(16)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) return false;
    return $subDir.'/'.$name;
}

// ── Pagination ────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $page): array {
    $pages=max(1,(int)ceil($total/$perPage));
    $page=max(1,min($page,$pages));
    return ['total'=>$total,'per_page'=>$perPage,'page'=>$page,'pages'=>$pages,'offset'=>($page-1)*$perPage];
}

// ── ID generators ─────────────────────────────────────────────
function generateApplicationNumber(): string {
    $year=date('Y');
    for ($i=0;$i<10;$i++) {
        $n='KHS-'.$year.'-'.str_pad(random_int(100000,999999),6,'0',STR_PAD_LEFT);
        if (!db()->prepare('SELECT 1 FROM applications WHERE application_number=?')->execute([$n]) || !db()->query("SELECT 1 FROM applications WHERE application_number='$n'")->fetchColumn()) return $n;
    }
    return 'KHS-'.$year.'-'.uniqid();
}
function generateStudentId(): string {
    $max=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM students")->fetchColumn();
    return 'KHS-'.date('Y').'-'.str_pad($max+1,4,'0',STR_PAD_LEFT);
}
function generateAdmissionNumber(): string {
    $max=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM students")->fetchColumn();
    return 'ADM-'.date('Y').'-'.str_pad($max+1,4,'0',STR_PAD_LEFT);
}
function generateReceiptNumber(): string {
    $max=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM payments")->fetchColumn();
    return 'REC-'.date('Y').'-'.str_pad($max+1,5,'0',STR_PAD_LEFT);
}
function generateTeacherId(): string {
    $max=(int)db()->query("SELECT COALESCE(MAX(id),0) FROM teachers")->fetchColumn();
    return 'TCH-'.str_pad($max+1,3,'0',STR_PAD_LEFT);
}

// ── Grading helpers ───────────────────────────────────────────
function gradeLetter(float $pct, int $ayId): string {
    $stmt=db()->prepare('SELECT grade_letter FROM grading_scales WHERE academic_year_id=? AND ? BETWEEN min_percent AND max_percent LIMIT 1');
    $stmt->execute([$ayId,$pct]);
    return $stmt->fetchColumn() ?: 'N/A';
}
function isPassing(float $pct, int $ayId): bool {
    $stmt=db()->prepare('SELECT is_pass FROM grading_scales WHERE academic_year_id=? AND ? BETWEEN min_percent AND max_percent LIMIT 1');
    $stmt->execute([$ayId,$pct]);
    return (bool)($stmt->fetchColumn() ?? false);
}

// ── Display helpers ───────────────────────────────────────────
function fmtMark(?float $v): string  { return $v===null?'—':number_format($v,1); }
function fmtPct(?float $v): string   { return $v===null?'—':number_format($v,1).'%'; }

function statusBadge(string $status): string {
    $map=[
        'draft'           =>'pending',  'submitted'       =>'new-s',
        'under_review'    =>'pending',  'approved'        =>'approved',
        'returned'        =>'warning',  'rejected'        =>'warning',
        'published'       =>'approved', 'locked'          =>'approved',
        'resubmitted'     =>'new-s',    'pending'         =>'pending',
        'Application Submitted'=>'pending','Under Review' =>'pending',
        'Approved for entrance'=>'approved','Entrance scheduled'=>'new-s',
        'Documents needed'=>'warning',  'Admitted'        =>'approved',
        'Rejected'        =>'warning',  'Active'          =>'approved',
        'Inactive'        =>'warning',  'Graduated'       =>'new-s',
        'Transferred'     =>'pending',  'Promoted'        =>'approved',
        'Repeating'       =>'warning',  'Present'         =>'approved',
        'Absent'          =>'warning',  'Late'            =>'pending',
        'Excused'         =>'new-s',    'Issued'          =>'new-s',
        'Returned'        =>'approved', 'Overdue'         =>'warning',
    ];
    $cls=$map[$status]??'pending';
    return '<span class="status '.$cls.'">'.e($status).'</span>';
}

function workflowBadge(string $status): string {
    $icons=[
        STATUS_DRAFT     => ['⏳','pending',   'Draft'],
        STATUS_SUBMITTED => ['📤','new-s',     'Submitted'],
        STATUS_REVIEW    => ['🔍','pending',   'Under Review'],
        STATUS_APPROVED  => ['✅','approved',  'Approved'],
        STATUS_RETURNED  => ['↩','warning',   'Returned'],
        STATUS_REJECTED  => ['❌','warning',   'Rejected'],
        STATUS_PUBLISHED => ['📢','approved',  'Published'],
        STATUS_LOCKED    => ['🔒','approved',  'Locked'],
        STATUS_RESUBMIT  => ['🔄','new-s',     'Resubmitted'],
    ];
    [$icon,$cls,$label]=$icons[$status]??['?','pending',$status];
    return '<span class="status '.$cls.'">'.$icon.' '.e($label).'</span>';
}
