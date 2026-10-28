<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$st = db()->prepare("SELECT * FROM users WHERE id=? AND role='farmer'");
$st->execute([(int)($_GET['id'] ?? 0)]);
$f = $st->fetch();
if (!$f) { flash('error', 'Farmer not found.'); redirect('admin/farmers.php'); }
$crops = farmer_crops((int)$f['id']);
$stm = db()->prepare('SELECT COUNT(*) FROM messages WHERE farmer_id=?'); $stm->execute([$f['id']]);
$msgCount = (int)$stm->fetchColumn();
$stm = db()->prepare('SELECT * FROM farm_profiles WHERE user_id=?');
$stm->execute([$f['id']]);
$farm = $stm->fetch();
$stm = db()->prepare('SELECT record_type,title,quantity,unit,amount,status,record_date,due_date FROM farm_records WHERE user_id=? ORDER BY id DESC LIMIT 8');
$stm->execute([$f['id']]);
$farmRecords = $stm->fetchAll();
render_head($f['full_name'], 'farmers');
?>
<div class="page-head"><div><a class="muted" href="<?= e(url('admin/farmers.php')) ?>">← All farmers</a><h1><?= e($f['full_name']) ?></h1></div>
  <a class="btn btn-lg" href="<?= e(url('admin/messages.php?farmer=' . (int)$f['id'])) ?>">💬 Message</a></div>
<div class="cols">
  <section class="card">
    <h2>Details</h2>
    <div class="row-item"><div class="row-ico">📞</div><div class="row-main"><strong><?= e($f['phone'] ?: '—') ?></strong><small>Phone</small></div><?php if ($f['phone']): ?><a class="btn btn-sm btn-outline" href="tel:<?= e($f['phone']) ?>">Call</a><?php endif; ?></div>
    <div class="row-item"><div class="row-ico">✉️</div><div class="row-main"><strong><?= e($f['email']) ?></strong><small>Email</small></div></div>
    <div class="row-item"><div class="row-ico">📍</div><div class="row-main"><strong><?= e($f['location'] ?: '—') ?></strong><small>Location</small></div></div>
    <div class="row-item"><div class="row-ico">📅</div><div class="row-main"><strong><?= e(utc_to_local($f['created_at'], 'd M Y')) ?></strong><small>Joined · last login <?= e($f['last_login'] ? time_ago($f['last_login']) : 'never') ?></small></div></div>
    <div class="row-item"><div class="row-ico">💬</div><div class="row-main"><strong><?= $msgCount ?> messages</strong><small>In conversation</small></div><span class="chip st-<?= e($f['status']) ?>"><?= e(ucfirst($f['status'])) ?></span></div>
  </section>
  <section class="card">
    <h2>🌱 Crops (<?= count($crops) ?>)</h2>
    <?php if (!$crops): ?><div class="empty"><p>No crops recorded yet.</p></div><?php endif; ?>
    <?php foreach ($crops as $c): ?>
      <div class="row-item">
        <div class="row-ico"><?= crop_emoji($c['crop_name']) ?></div>
        <div class="row-main"><strong><?= e($c['crop_name']) ?></strong><small><?= e($c['field_name'] ?: '—') ?><?= $c['area_acres'] !== null ? ' · ' . e($c['area_acres']) . ' acres' : '' ?> · harvest <?= e(fmt_date($c['harvest_date'])) ?></small></div>
        <span class="chip st-<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<div class="cols dashboard-extras">
  <section class="card"><h2>🌳 Farm profile</h2>
    <?php if (!$farm): ?><div class="empty"><p>This farmer has not added a farm profile yet.</p></div><?php else: ?>
      <div class="row-item"><div class="row-ico">🏡</div><div class="row-main"><strong><?= e($farm['farm_name']) ?></strong><small><?= e($farm['location']) ?></small></div></div>
      <div class="row-item"><div class="row-ico">📐</div><div class="row-main"><strong><?= $farm['size_acres'] !== null ? e(number_format((float)$farm['size_acres'], 2)) . ' acres' : 'Size not provided' ?></strong><small>Farm size<?= $farm['soil_type'] ? ' · ' . e($farm['soil_type']) . ' soil' : '' ?></small></div></div>
      <?php if ($farm['notes']): ?><p class="record-details"><?= nl2br(e($farm['notes'])) ?></p><?php endif; ?>
    <?php endif; ?>
  </section>
  <section class="card"><div class="card-head"><h2>🗂️ Recent farm records</h2><a href="<?= e(url('admin/farm-records.php')) ?>">Manage all →</a></div>
    <?php if (!$farmRecords): ?><div class="empty"><p>No livestock, activity, harvest, or finance records submitted yet.</p></div><?php endif; ?>
    <?php foreach ($farmRecords as $r): ?><div class="row-item"><div class="row-ico">📒</div><div class="row-main"><strong><?= e($r['title']) ?></strong><small><?= e(ucfirst($r['record_type'])) ?><?php if ($r['quantity'] !== null): ?> · <?= e(rtrim(rtrim(number_format((float)$r['quantity'], 2), '0'), '.')) ?> <?= e($r['unit']) ?><?php endif; ?><?php if ($r['amount'] !== null): ?> · KES <?= e(number_format((float)$r['amount'], 2)) ?><?php endif; ?> · <?= e(fmt_date($r['record_date'] ?: $r['due_date'])) ?></small></div></div><?php endforeach; ?>
  </section>
</div>
<?php render_foot(); ?>
