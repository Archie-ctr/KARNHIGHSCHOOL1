<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
requireAuth(); requireRole(['school_admin','sys_admin','super_admin']);
require_once dirname(__DIR__, 2) . '/dashboards/school_admin.php';
