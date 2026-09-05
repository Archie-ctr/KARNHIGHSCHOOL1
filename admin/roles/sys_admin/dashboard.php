<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
requireAuth(); requireRole(['sys_admin','super_admin']);
require_once dirname(__DIR__, 2) . '/dashboards/sys_admin.php';
