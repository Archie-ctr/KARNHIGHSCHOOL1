<?php
// ============================================================
// KHSMIS Admin Entry Point
// Routes each role to its dedicated dashboard in admin/roles/{role}/
// ============================================================
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();
requireStaff();

$role = currentRole();

// Map every role to its dashboard file inside admin/roles/
$dashMap = [
    'sys_admin'          => 'roles/sys_admin/dashboard.php',
    'super_admin'        => 'roles/sys_admin/dashboard.php',
    'school_admin'       => 'roles/school_admin/dashboard.php',
    'principal'          => 'roles/principal/dashboard.php',
    'vice_principal'     => 'roles/vice_principal/dashboard.php',
    'vice_principal_alt' => 'roles/vice_principal/dashboard.php',
    'academic_dean'      => 'roles/vice_principal/dashboard.php', // legacy alias
    'registrar'          => 'roles/registrar/dashboard.php',
    'accountant'         => 'roles/accountant/dashboard.php',
    'finance_officer'    => 'roles/finance_officer/dashboard.php',
    'teacher'            => 'roles/teacher/dashboard.php',
    'class_teacher'      => 'roles/class_teacher/dashboard.php',
    'discipline_officer' => 'roles/discipline_officer/dashboard.php',
    'librarian'          => 'roles/librarian/dashboard.php',
    'ict_officer'        => 'roles/ict_officer/dashboard.php',
];

$file = __DIR__ . '/' . ($dashMap[$role] ?? 'dashboards/default_dash.php');

if (file_exists($file)) {
    require $file;
} else {
    require __DIR__ . '/dashboards/default_dash.php';
}
