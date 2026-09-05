<?php
/**
 * Role page header helper
 * Include at the top of every role feature file.
 * Sets $pageTitle and $activeAdmin, then requires the main admin_header.
 *
 * Usage:
 *   $pageTitle   = 'My Classes';
 *   $activeAdmin = 'marks_entry';       // sidebar key
 *   $requiredPerm = 'marks.create';     // permission required (or '' to skip)
 *   require_once dirname(__DIR__).'/_role_header.php';
 */

$_basePath = dirname(dirname(__DIR__)); // /KARNHIGHSCHOOL
require_once $_basePath.'/config/db.php';
requireAuth();
requireStaff();

if (!empty($requiredPerm)) {
    requirePermission($requiredPerm);
}

if (!isset($pageTitle))   $pageTitle   = 'Dashboard';
if (!isset($activeAdmin)) $activeAdmin = 'dashboard';

require_once dirname(__DIR__).'/includes/admin_header.php';
