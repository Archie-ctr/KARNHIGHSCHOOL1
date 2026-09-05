<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
requireAuth(); requireRole(['vice_principal','vice_principal_alt','principal','sys_admin','super_admin','school_admin']);
require_once dirname(__DIR__, 2) . '/dashboards/vice_principal.php';
