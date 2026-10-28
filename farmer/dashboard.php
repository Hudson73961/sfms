<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$pdo = db();

$st = $pdo->prepare('SELECT status, COUNT(*) c, COALESCE(SUM(area_acres),0) a FROM crops WHERE user_id=? GROUP BY status');
$st->execute([$u['id']]);
$by = ['planted' => [0, 0], 'growing' => [0, 0], 'harvested' => [0, 0]];
foreach ($st->fetchAll() as $r) $by[$r['status']] = [(int)$r['c'], (float)$r['a']];
$active = $by['planted'][0] + $by['growing'][0];
$acres  = $by['planted'][1] + $by['growing'][1];

$st = $pdo->prepare("SELECT * FROM crops WHERE user_id=? AND status!='harvested' AND harvest_date IS NOT NULL ORDER BY harvest_date LIMIT 3");
$st->execute([$u['id']]);
$upcoming = $st->fetchAll();
$news = $pdo->query('SELECT * FROM announcements ORDER BY id DESC LIMIT 3')->fetchAll();
$unread = unread_count($u);
$st = $pdo->prepare('SELECT farm_name FROM farm_profiles WHERE user_id=?');
$st->execute([$u['id']]);
$farmName = $st->fetchColumn();
$st = $pdo->prepare("SELECT title,record_type,record_date,due_date FROM farm_records WHERE user_id=? AND record_type IN ('task','activity','harvest','livestock') ORDER BY COALESCE(record_date,due_date,created_at) DESC,id DESC LIMIT 5");
$st->execute([$u['id']]);
$recentFarmRecords = $st->fetchAll();
$st = $pdo->prepare("SELECT title,details,record_date FROM farm_records WHERE user_id=? AND record_type='weather' ORDER BY COALESCE(record_date,created_at) DESC,id DESC LIMIT 1");
$st->execute([$u['id']]);
$weatherNote = $st->fetch();
$st = $pdo->prepare("SELECT title,due_date,record_type FROM farm_records WHERE user_id=? AND record_type IN ('task','livestock') AND due_date>=date('now') AND status!='done' ORDER BY due_date LIMIT 1");
$st->execute([$u['id']]);
$nextReminder = $st->fetch();

$h = (int)date('G');
$greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
$tips = [
    'Test your soil before planting — the right fertiliser saves money and boosts yield.',
    'Rotate crops each season to keep pests and diseases from building up.',
    'Water early in the morning to reduce evaporation and fungal disease.',
    'Mulching keeps soil moist, controls weeds and adds nutrients as it rots.',
    'Keep records of every input and harvest — it shows you which crops pay best.',
    'Dry your harvested grain well (below 13.5% moisture) before storing it.',
    'Plant certified seed. Good seed is the cheapest way to improve your harvest.',
];
$tip = $tips[(int)date('z') % count($tips)];

render_head('Home', 'dashboard');
?>
<section class="hello">
  <div class="hello-copy">
    <h1><?= e($greet) ?>, <?= e(explode(' ', $u['full_name'])[0]) ?> 👋</h1>
    <p class="muted"><?= e($farmName ?: 'Your farm at a glance') ?> · <?= date('l, j F Y') ?></p>
    <a class="btn btn-sm" href="<?= e(url('farmer/farm.php')) ?>">Update farm profile</a>
  </div>
  <img class="hello-photo" src="<?= e(url('assets/img/field-banner.jpg')) ?>" alt="Green growing fields on a farm" width="1400" height="900" loading="lazy">
  <a class="btn btn-lg" href="<?= e(url('farmer/messages.php')) ?>">💬 Message the office<?= $unread ? ' <span class="pill">' . $unread . ' new</span>' : '' ?></a>
</section>

<section class="stats">
  <a class="stat s-green" href="<?= e(url('farmer/crops.php')) ?>"><span class="s-ico">🌿</span><b><?= $active ?></b><span>Crops growing</span></a>
  <a class="stat s-gold"  href="<?= e(url('farmer/crops.php')) ?>"><span class="s-ico">🌾</span><b><?= $by['harvested'][0] ?></b><span>Harvested</span></a>
  <a class="stat s-brown" href="<?= e(url('farmer/crops.php')) ?>"><span class="s-ico">📐</span><b><?= rtrim(rtrim(number_format($acres, 2), '0'), '.') ?: '0' ?></b><span>Acres in use</span></a>
  <a class="stat s-blue"  href="<?= e(url('farmer/messages.php')) ?>"><span class="s-ico">💬</span><b><?= $unread ?></b><span>New messages</span></a>
</section>

<div class="tip card"><span class="tip-ico">💡</span><div><strong>Farming tip of the day</strong><p><?= e($tip) ?></p></div></div>

<div class="cols">
  <section class="card">
    <div class="card-head"><h2>🗓️ Coming up</h2><a href="<?= e(url('farmer/records.php?type=calendar')) ?>">Farm calendar →</a></div>
    <?php if (!$upcoming): ?>
      <div class="empty"><div class="big-emoji">🌱</div><p>No harvests scheduled yet.</p><a class="btn" href="<?= e(url('farmer/crops.php')) ?>">Add a crop</a></div>
    <?php else: foreach ($upcoming as $c):
        $days = (int)ceil((strtotime($c['harvest_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
      <div class="row-item">
        <div class="row-ico"><?= crop_emoji($c['crop_name']) ?></div>
        <div class="row-main"><strong><?= e($c['crop_name']) ?></strong><small><?= e($c['field_name'] ?: 'Field not named') ?> · harvest <?= e(fmt_date($c['harvest_date'])) ?></small></div>
        <span class="chip <?= $days < 0 ? 'chip-warn' : '' ?>"><?= $days < 0 ? abs($days) . ' d overdue' : ($days === 0 ? 'Today' : 'in ' . $days . ' d') ?></span>
      </div>
    <?php endforeach; endif; ?>
    <?php if ($nextReminder): ?><div class="row-item"><div class="row-ico"><?= $nextReminder['record_type'] === 'livestock' ? '💉' : '✅' ?></div><div class="row-main"><strong><?= e($nextReminder['title']) ?></strong><small><?= e($nextReminder['record_type'] === 'livestock' ? 'Animal health reminder' : 'Farm task') ?> · <?= e(fmt_date($nextReminder['due_date'])) ?></small></div></div><?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>📢 Latest news</h2><a href="<?= e(url('farmer/announcements.php')) ?>">See all →</a></div>
    <?php if (!$news): ?><div class="empty"><p>No news yet.</p></div><?php endif; ?>
    <?php foreach ($news as $n): ?>
      <article class="news-item cat-<?= e(strtolower($n['category'])) ?>">
        <div class="news-top"><span class="chip"><?= cat_emoji($n['category']) ?> <?= e($n['category']) ?></span><small><?= e(time_ago($n['created_at'])) ?></small></div>
        <h3><?= e($n['title']) ?></h3>
        <p><?= nl2br(e(mb_strimwidth($n['body'], 0, 140, '…'))) ?></p>
      </article>
    <?php endforeach; ?>
  </section>
</div>
<div class="cols dashboard-extras">
  <section class="card"><div class="card-head"><h2>🛠️ Recent farm activity</h2><a href="<?= e(url('farmer/records.php?type=activity')) ?>">Add activity →</a></div>
    <?php if (!$recentFarmRecords): ?><div class="empty"><p>Planting, harvest, livestock and task records you add will appear here.</p><a class="btn btn-sm btn-outline" href="<?= e(url('farmer/tools.php')) ?>">Explore farm tools</a></div><?php endif; ?>
    <?php foreach ($recentFarmRecords as $r): ?><div class="row-item"><div class="row-ico"><?= ['task'=>'✅','activity'=>'🛠️','harvest'=>'🌾','livestock'=>'🐄'][$r['record_type']] ?></div><div class="row-main"><strong><?= e($r['title']) ?></strong><small><?= e(ucfirst($r['record_type'])) ?> · <?= e(fmt_date($r['record_date'] ?: $r['due_date'])) ?></small></div></div><?php endforeach; ?>
  </section>
  <section class="card"><div class="card-head"><h2>🌦️ Weather notes</h2><a href="<?= e(url('farmer/records.php?type=weather')) ?>">Log observation →</a></div>
    <?php if ($weatherNote): ?><strong><?= e($weatherNote['title']) ?></strong><p class="muted"><?= e($weatherNote['details'] ?: 'No additional notes.') ?></p><small><?= e(fmt_date($weatherNote['record_date'])) ?></small>
    <?php else: ?><div class="empty"><p>No weather observations recorded yet.</p><p class="muted">Live forecasts are not connected in this installation.</p></div><?php endif; ?>
  </section>
</div>
<?php render_foot(); ?>
