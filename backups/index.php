<?php
// Direct access protection
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
requireRole(['sys_admin','super_admin']);
$file = basename($_GET['file'] ?? '');
$path = __DIR__.'/'.$file;
if ($file && file_exists($path) && str_ends_with($file, '.sql')) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.addslashes($file).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}
redirect(BASE_URL.'/admin/backup.php');
