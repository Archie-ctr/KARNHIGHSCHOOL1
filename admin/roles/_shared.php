<?php
/**
 * Shared bootstrap for ALL role feature pages.
 * Set these before requiring this file:
 *   $requiredPerm  (string)   — permission required, e.g. 'marks.create'
 *   $pageTitle     (string)   — browser title
 *   $activeAdmin   (string)   — sidebar key
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
requireAuth();
requireStaff();
if (!empty($requiredPerm)) requirePermission($requiredPerm);
require_once dirname(__DIR__, 1) . '/includes/admin_header.php';
