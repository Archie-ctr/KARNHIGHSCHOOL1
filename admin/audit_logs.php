<?php
$pageTitle='Audit Logs'; $activeAdmin='audit_logs';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['principal','super_admin']);
$pdo=db();
$q=trim($_GET['q']??''); $page=max(1,(int)($_GET['page']??1)); $per=30;
$wsql=$q?"WHERE (al.action LIKE '%".addslashes($q)."%' OR al.user_name LIKE '%".addslashes($q)."%' OR al.module LIKE '%".addslashes($q)."%')":'';
$cnt=$pdo->query("SELECT COUNT(*) FROM audit_logs al $wsql")->fetchColumn(); $pg=paginate((int)$cnt,$per,$page);
$logs=$pdo->query("SELECT * FROM audit_logs al $wsql ORDER BY al.created_at DESC LIMIT $per OFFSET {$pg['offset']}")->fetchAll();
?>
<div class="page-heading"><div><div class="eyebrow">System <span></span></div><h1>Audit Logs</h1><p>Track all system activity and changes.</p></div></div>
<form method="get" class="filter-row" style="margin-bottom:14px"><div class="table-search">🔍<input type="search" name="q" placeholder="Action, user, module…" value="<?=e($q)?>"/></div><button class="button button-primary button-sm">Search</button><?php if($q):?><a href="<?=BASE_URL?>/admin/audit_logs.php" class="filter-button">Clear</a><?php endif;?></form>
<div class="table-wrap"><table>
  <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Changes</th><th>IP</th></tr></thead>
  <tbody><?php if(empty($logs)):?><tr><td colspan="7" style="text-align:center;padding:28px;color:var(--ink-faint)">No logs found.</td></tr>
  <?php else: foreach($logs as $l):?>
  <tr>
    <td style="font-size:12px" class="muted"><?=date('M d, Y H:i',strtotime($l['created_at']))?></td>
    <td><strong><?=e($l['user_name']??'System')?></strong></td>
    <td><span class="status <?=$l['action']==='login'?'approved':($l['action']==='delete'?'warning':'new-s')?>"><?=e($l['action'])?></span></td>
    <td class="muted"><?=e($l['module'])?></td>
    <td class="muted"><?=e($l['record_type']?$l['record_type'].'#'.$l['record_id']:'')?></td>
    <td style="font-size:12px;max-width:200px">
      <?php if($l['old_value']||$l['new_value']):?>
      <?php if($l['old_value']):?><span style="color:var(--error)">− <?=e(mb_substr($l['old_value'],0,40))?></span><br><?php endif;?>
      <?php if($l['new_value']):?><span style="color:var(--green)">+ <?=e(mb_substr($l['new_value'],0,40))?></span><?php endif;?>
      <?php endif;?>
    </td>
    <td class="muted" style="font-size:11px"><?=e($l['ip_address']??'')?></td>
  </tr>
  <?php endforeach; endif;?></tbody>
</table></div>
<?php if($pg['pages']>1):?>
<div class="pagination">
  <?php if($pg['page']>1):?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>">&laquo;</a><?php endif;?>
  <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++):?><?php if($p===$pg['page']):?><span class="current"><?=$p?></span><?php else:?><a href="?page=<?=$p?>&q=<?=urlencode($q)?>"><?=$p?></a><?php endif;?><?php endfor;?>
  <?php if($pg['page']<$pg['pages']):?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>">&raquo;</a><?php endif;?>
</div>
<?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
