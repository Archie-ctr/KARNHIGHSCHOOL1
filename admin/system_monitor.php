<?php
$pageTitle   = 'System Monitor';
$activeAdmin = 'system_monitor';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin']);

$pdo = db();
$tab = $_GET['tab'] ?? 'overview';

// ── System stats ──────────────────────────────────────────────
// DB size
$dbSize = $pdo->query(
    "SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2)
     FROM information_schema.tables WHERE table_schema='karnhighschool'"
)->fetchColumn();

// Table stats
$tables = $pdo->query(
    "SELECT table_name, table_rows,
            ROUND((data_length)/1024,1) data_kb,
            ROUND((index_length)/1024,1) idx_kb,
            ROUND((data_length+index_length)/1024,1) total_kb,
            create_time, update_time
     FROM information_schema.tables
     WHERE table_schema='karnhighschool'
     ORDER BY (data_length+index_length) DESC"
)->fetchAll();

// Uploads folder size
$uploadDir = BASE_PATH . '/uploads';
$uploadSize = 0;
$uploadFiles = 0;
if (is_dir($uploadDir)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $f) { $uploadSize += $f->getSize(); $uploadFiles++; }
}

// PHP info
$phpVersion  = PHP_VERSION;
$phpMemLimit = ini_get('memory_limit');
$phpMaxUpload= ini_get('upload_max_filesize');
$phpMaxPost  = ini_get('post_max_size');
$phpTimeout  = ini_get('max_execution_time');
$memUsage    = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

// Recent activity (last 24h)
$auditRecent = $pdo->query(
    "SELECT action, COUNT(*) cnt FROM audit_logs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY action ORDER BY cnt DESC LIMIT 10"
)->fetchAll();

// Activity by hour today
$actByHour = $pdo->query(
    "SELECT HOUR(created_at) hr, COUNT(*) cnt FROM audit_logs
     WHERE DATE(created_at)=CURDATE()
     GROUP BY HOUR(created_at) ORDER BY hr"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Key record counts
$counts = [];
$countTables = ['students','users','applications','payments','attendance','assessment_scores','discipline_records','library_transactions','announcements','report_cards'];
foreach ($countTables as $t) {
    try { $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
    catch (Throwable $e) { $counts[$t] = 0; }
}

// Error log entries (last 50 lines of PHP error log if accessible)
$errorLog = [];
$logFile  = ini_get('error_log');
if ($logFile && file_exists($logFile) && is_readable($logFile)) {
    $lines = file($logFile);
    $errorLog = array_slice(array_reverse($lines), 0, 50);
}

// Disk space (server level)
$diskFree  = function_exists('disk_free_space')  ? disk_free_space(BASE_PATH)  : null;
$diskTotal = function_exists('disk_total_space') ? disk_total_space(BASE_PATH) : null;
$diskUsedPct = ($diskFree !== null && $diskTotal > 0)
    ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : null;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System <span></span></div>
    <h1>System Monitor</h1>
    <p>Database health, storage, PHP environment and activity.</p>
  </div>
  <a href="<?= BASE_URL ?>/admin/backup.php" class="button button-secondary">💾 Backup</a>
</div>

<!-- Health banner -->
<?php $healthy = $dbSize < 500 && $diskUsedPct < 90; ?>
<div style="padding:14px 20px;border-radius:var(--radius);border:1.5px solid <?= $healthy?'#b5dfc5':'var(--error)' ?>;background:<?= $healthy?'#edf7f0':'#fff1f2' ?>;display:flex;align-items:center;gap:14px;margin-bottom:20px">
  <span style="font-size:1.4rem"><?= $healthy ? '✅' : '⚠️' ?></span>
  <div>
    <strong style="color:<?= $healthy?'var(--green)':'var(--error)' ?>"><?= $healthy ? 'System Healthy' : 'Attention Required' ?></strong>
    <p style="font-size:13px;color:var(--ink-soft);margin:0">
      DB: <?= $dbSize ?> MB &nbsp;·&nbsp;
      Uploads: <?= round($uploadSize/1024/1024,2) ?> MB (<?= $uploadFiles ?> files) &nbsp;·&nbsp;
      <?= $diskUsedPct !== null ? "Disk: {$diskUsedPct}% used" : 'Disk info unavailable' ?>
    </p>
  </div>
</div>

<!-- Metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Database Size</span><div class="metric-icon">🗄️</div></div><strong><?= $dbSize ?> MB</strong><small><i></i><?= count($tables) ?> tables</small></div>
  <div class="metric-card"><div class="metric-top"><span>Uploads Storage</span><div class="metric-icon">📁</div></div><strong><?= round($uploadSize/1024/1024,2) ?> MB</strong><small><i></i><?= number_format($uploadFiles) ?> files</small></div>
  <div class="metric-card"><div class="metric-top"><span>PHP Memory Peak</span><div class="metric-icon">💻</div></div><strong><?= $memUsage ?> MB</strong><small><i></i>Limit: <?= $phpMemLimit ?></small></div>
  <div class="metric-card <?= $diskUsedPct > 85 ? 'finance-metrics' : '' ?>"><div class="metric-top"><span>Disk Used</span><div class="metric-icon">💿</div></div><strong><?= $diskUsedPct !== null ? $diskUsedPct.'%' : '—' ?></strong><small><i></i><?= $diskFree !== null ? round($diskFree/1024/1024/1024,2).' GB free' : 'N/A' ?></small></div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=overview"   class="tab-btn <?= $tab==='overview'  ?'active':'' ?>">📊 Overview</a>
  <a href="?tab=database"   class="tab-btn <?= $tab==='database'  ?'active':'' ?>">🗄️ Database</a>
  <a href="?tab=records"    class="tab-btn <?= $tab==='records'   ?'active':'' ?>">📋 Record Counts</a>
  <a href="?tab=php"        class="tab-btn <?= $tab==='php'       ?'active':'' ?>">⚙️ PHP Environment</a>
  <a href="?tab=error_log"  class="tab-btn <?= $tab==='error_log' ?'active':'' ?>">🔴 Error Log</a>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ── OVERVIEW ──────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="monitor-grid">

  <!-- Activity last 24h -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📊 Activity Last 24 Hours</h3>
    <?php if (empty($auditRecent)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No activity recorded.</p>
    <?php else: foreach ($auditRecent as $a): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= e($a['action']) ?></span>
      <strong><?= $a['cnt'] ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Hourly activity today -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📈 Hourly Activity Today</h3>
    <?php if (empty($actByHour)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No activity today yet.</p>
    <?php else:
      $maxCnt = max(array_values($actByHour)) ?: 1;
      for ($h = 0; $h < 24; $h += 2): $cnt = $actByHour[$h] ?? 0; $pct = round($cnt/$maxCnt*100); ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:12px">
      <span style="width:28px;color:var(--ink-soft)"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?>h</span>
      <div style="flex:1;height:8px;background:var(--bg);border-radius:4px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:4px"></div>
      </div>
      <span style="width:24px;text-align:right"><?= $cnt ?></span>
    </div>
    <?php endfor; endif; ?>
  </div>
</div>

<?php elseif ($tab === 'database'): ?>
<!-- ── DATABASE TABLES ───────────────────────────────────── -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Table</th><th>Est. Rows</th><th>Data</th><th>Index</th><th>Total</th><th>Last Update</th></tr></thead>
    <tbody>
      <?php foreach ($tables as $t): ?>
      <tr>
        <td><strong><?= e($t['table_name']) ?></strong></td>
        <td class="muted"><?= number_format((int)$t['table_rows']) ?></td>
        <td class="muted"><?= $t['data_kb'] ?> KB</td>
        <td class="muted"><?= $t['idx_kb'] ?> KB</td>
        <td><strong><?= $t['total_kb'] ?> KB</strong></td>
        <td class="muted"><?= $t['update_time'] ? date('M d, Y', strtotime($t['update_time'])) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'records'): ?>
<!-- ── RECORD COUNTS ─────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
  <?php
  $icons = ['students'=>'🎓','users'=>'👥','applications'=>'📋','payments'=>'💰','attendance'=>'📆','assessment_scores'=>'📊','discipline_records'=>'⚖️','library_transactions'=>'📖','announcements'=>'📢','report_cards'=>'📑'];
  foreach ($counts as $t => $c): ?>
  <div class="panel" style="padding:20px;text-align:center">
    <div style="font-size:1.8rem;margin-bottom:8px"><?= $icons[$t] ?? '📋' ?></div>
    <strong style="display:block;font-size:1.6rem;color:var(--primary)"><?= number_format($c) ?></strong>
    <span style="font-size:12px;color:var(--ink-soft)"><?= e(str_replace('_',' ', $t)) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($tab === 'php'): ?>
<!-- ── PHP ENVIRONMENT ───────────────────────────────────── -->
<div class="panel" style="padding:24px;max-width:640px">
  <h3 style="font-weight:700;margin-bottom:16px">⚙️ PHP Environment</h3>
  <?php
  $phpInfo = [
    'PHP Version'          => PHP_VERSION,
    'OS'                   => PHP_OS,
    'Server Software'      => $_SERVER['SERVER_SOFTWARE'] ?? '—',
    'Memory Limit'         => $phpMemLimit,
    'Upload Max Filesize'  => $phpMaxUpload,
    'Post Max Size'        => $phpMaxPost,
    'Max Execution Time'   => $phpTimeout.'s',
    'Peak Memory Usage'    => $memUsage.' MB',
    'Document Root'        => $_SERVER['DOCUMENT_ROOT'] ?? '—',
    'PHP Extensions'       => implode(', ', array_slice(get_loaded_extensions(), 0, 12)).'…',
    'Date / Timezone'      => date('Y-m-d H:i:s T'),
    'Error Reporting Level'=> error_reporting(),
    'Display Errors'       => ini_get('display_errors') ? 'On' : 'Off',
    'Error Log'            => ini_get('error_log') ?: 'Not configured',
    'Session Save Path'    => session_save_path() ?: sys_get_temp_dir(),
  ];
  foreach ($phpInfo as $k => $v): ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px;gap:16px">
    <span style="color:var(--ink-soft);font-weight:600;min-width:160px;flex-shrink:0"><?= $k ?></span>
    <span style="text-align:right;word-break:break-all;color:var(--ink)"><?= e((string)$v) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($tab === 'error_log'): ?>
<!-- ── ERROR LOG ─────────────────────────────────────────── -->
<?php if (empty($errorLog)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">✅</div>
  <p style="color:var(--ink-soft)">No error log entries found or log file not accessible.<br>
    Log path: <code><?= e(ini_get('error_log') ?: 'Not configured') ?></code></p>
</div>
<?php else: ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:14px 18px;background:var(--bg);border-bottom:1px solid var(--line);display:flex;justify-content:space-between">
    <strong style="font-size:13px">PHP Error Log (last 50 entries)</strong>
    <span style="font-size:12px;color:var(--ink-soft)"><?= e(ini_get('error_log')) ?></span>
  </div>
  <div style="overflow-x:auto;max-height:500px;overflow-y:auto">
    <pre style="padding:16px;font-size:11.5px;line-height:1.6;margin:0;white-space:pre-wrap;word-break:break-word"><?php foreach ($errorLog as $line): ?><?= htmlspecialchars(trim($line))."\n" ?><?php endforeach; ?></pre>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>@media(max-width:640px){.monitor-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
