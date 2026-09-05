<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','super_admin','school_admin']);
require_once dirname(__DIR__, 2) . '/dashboards/discipline_dash.php';
