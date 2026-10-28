<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$pdo = db();
$types = ['livestock', 'activity', 'harvest', 'finance', 'market', 'weather', 'task', 'inventory'];
$labels = ['livestock'=>'Livestock','activity'=>'Activity','harvest'=>'Harvest','finance'=>'Finance','market'=>'Market','weather'=>'Weather','task'=>'Task','inventory'=>'Inventory'];
$filter = (string)($_GET['type'] ?? '');
if ($filter !== '' && !in_array($filter, $types, true)) $filter = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT record_type,title FROM farm_records WHERE id=?');
    $st->execute([$id]);
    $record = $st->fetch();
    if (!$record) {
        $err = 'That farm record could not be found.';
    } else {
        $pdo->prepare('DELETE FROM farm_records WHERE id=?')->execute([$id]);
        audit((int)$u['id'], 'farm_record_admin_delete', $record['record_type'] . ':' . $record['title']);
        flash('success', 'Farmer record removed.');
        redirect('admin/farm-records.php' . ($filter ? '?type=' . rawurlencode($filter) : ''));
    }
}
$sql = 'SELECT r.*,u.full_name,u.location FROM farm_records r JOIN users u ON u.id=r.user_id WHERE u.role=\'farmer\'';
$args = [];
if ($filter !== '') { $sql .= ' AND r.record_type=?'; $args[] = $filter; }
$sql .= ' ORDER BY r.id DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($args);
$records = $st->fetchAll();
render_head('Farmer records', 'farmer_records');
?>
<div class="page-head"><div><h1>🗂️ Farmer farm records</h1><p class="muted">Review records submitted by farmers. Deletions are permanent and recorded in the audit log.</p></div></div>
<?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
<section class="card">
  <form method="get" class="search-bar">
    <label for="type">Filter by record type</label>
    <select name="type" id="type"><option value="">All farm records</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"<?= $filter === $type ? ' selected' : '' ?>><?= e($labels[$type]) ?></option><?php endforeach; ?></select>
    <button class="btn" type="submit">Apply filter</button>
  </form>
  <div class="card-head"><h2>Recent submissions</h2><span class="chip">Up to 200</span></div>
  <?php if (!$records): ?><div class="empty"><p>No farmer records match this filter.</p></div><?php endif; ?>
  <?php foreach ($records as $record): ?>
    <article class="record-row">
      <div class="row-ico"><?= ['livestock'=>'🐄','activity'=>'🛠️','harvest'=>'🌾','finance'=>'💰','market'=>'🛒','weather'=>'🌦️','task'=>'🗓️','inventory'=>'📦'][$record['record_type']] ?></div>
      <div class="row-main"><strong><?= e($record['title']) ?></strong><small><?= e($record['full_name']) ?> · <?= e($record['location']) ?> · <?= e($labels[$record['record_type']]) ?> · <?= e(fmt_date($record['record_date'] ?: $record['created_at'])) ?></small>
        <?php if ($record['quantity'] !== null): ?><small><?= e(rtrim(rtrim(number_format((float)$record['quantity'], 2), '0'), '.')) ?> <?= e($record['unit']) ?></small><?php endif; ?>
        <?php if ($record['amount'] !== null): ?><small>KES <?= e(number_format((float)$record['amount'], 2)) ?><?= $record['record_type'] === 'finance' ? ' · ' . e(ucfirst($record['status'])) : '' ?></small><?php endif; ?>
        <?php if ($record['due_date']): ?><small>Due <?= e(fmt_date($record['due_date'])) ?></small><?php endif; ?>
        <?php if ($record['details']): ?><p class="record-details"><?= nl2br(e($record['details'])) ?></p><?php endif; ?>
      </div>
      <form method="post" data-confirm="Permanently remove this farmer's record?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Remove</button></form>
    </article>
  <?php endforeach; ?>
</section>
<?php render_foot(); ?>
