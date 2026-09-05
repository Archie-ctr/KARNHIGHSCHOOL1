<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
requireAuth(); requireRole(['finance_officer','accountant','principal','sys_admin','super_admin','school_admin']);
require_once dirname(__DIR__, 2) . '/dashboards/accountant.php';
