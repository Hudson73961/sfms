<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$pdo = db();
$q = fn(string $sql) => $pdo->query($sql)->fetchColumn();

$farmers   = (int)$q("SELECT COUNT(*) FROM users WHERE role='farmer'");
$active    = (int)$q("SELECT COUNT(*) FROM users WHERE role='farmer' AND status='active'");
$pending   = (int)$q("SELECT COUNT(*) FROM users WHERE role='farmer' AND status='pending'");
$cropsN    = (int)$q('SELECT COUNT(*) FROM crops');
$acres     = (float)$q("SELECT COALESCE(SUM(area_acres),0) FROM crops WHERE status!='harvested'");
$unread    = unread_count($u);
$byStatus  = ['planted' => 0, 'growing' => 0, 'harvested' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM crops GROUP BY status')->fetchAll() as $r) $byStatus[$r['status']] = (int)$r['c'];
$maxS = max(1, ...array_values($byStatus));
$topCrops = $pdo->query('SELECT crop_name, COUNT(*) c FROM crops GROUP BY crop_name ORDER BY c DESC, crop_name LIMIT 5')->fetchAll();
$maxC = max(1, (int)($topCrops[0]['c'] ?? 1));
$recent = $pdo->query("SELECT id, full_name, location, status, created_at FROM users WHERE role='farmer' ORDER BY id DESC LIMIT 5")->fetchAll();
$inbox = $pdo->query("SELECT u.id, u.full_name, COUNT(*) n, MAX(m.created_at) last_at FROM messages m JOIN users u ON u.id=m.farmer_id WHERE m.sender_role='farmer' AND m.read_at IS NULL GROUP BY u.id ORDER BY last_at DESC LIMIT 5")->fetchAll();

render_head('Overview', 'dashboard');
?>
<section class="hello">
  <div class="hello-copy"><h1>Hello, <?= e(explode(' ', $u['full_name'])[0]) ?> 👋</h1><p class="muted">Here is what is happening on the farms today.</p></div>
  <div class="btn-row"><a class="btn btn-outline" href="<?= e(url('admin/farm-records.php')) ?>">🗂️ Review farm records</a><a class="btn btn-lg" href="<?= e(url('admin/announcements.php')) ?>">📢 Post news</a></div>
</section>

<section class="stats">
  <a class="stat s-green" href="<?= e(url('admin/farmers.php')) ?>"><span class="s-ico">👩‍🌾</span><b><?= $farmers ?></b><span>Farmers (<?= $active ?> active)</span></a>
  <a class="stat s-gold" href="<?= e(url('admin/reports.php')) ?>"><span class="s-ico">🌱</span><b><?= $cropsN ?></b><span>Crops recorded</span></a>
  <a class="stat s-brown" href="<?= e(url('admin/reports.php')) ?>"><span class="s-ico">📐</span><b><?= rtrim(rtrim(number_format($acres, 2), '0'), '.') ?: '0' ?></b><span>Acres in use</span></a>
  <a class="stat s-blue" href="<?= e(url('admin/messages.php')) ?>"><span class="s-ico">💬</span><b><?= $unread ?></b><span>Unread messages</span></a>
</section>
<?php if ($pending): ?><div class="alert alert-warn"><?= $pending ?> farmer<?= $pending > 1 ? 's are' : ' is' ?> waiting for approval. <a href="<?= e(url('admin/farmers.php?status=pending')) ?>">Review now →</a></div><?php endif; ?>

<div class="cols">
  <section class="card">
    <div class="card-head"><h2>📈 Crops by stage</h2></div>
    <?php foreach (['planted' => ['🌱', 'Planted'], 'growing' => ['🌿', 'Growing'], 'harvested' => ['🌾', 'Harvested']] as $k => [$ico, $lbl]): ?>
      <div class="bar-row"><span class="bar-lbl"><?= $ico ?> <?= $lbl ?></span><div class="bar"><i class="bar-<?= $k ?>" style="width:<?= round($byStatus[$k] / $maxS * 100) ?>%"></i></div><b><?= $byStatus[$k] ?></b></div>
    <?php endforeach; ?>
    <h3 class="sub">Most grown crops</h3>
    <?php if (!$topCrops): ?><p class="muted">No crops recorded yet.</p><?php endif; ?>
    <?php foreach ($topCrops as $c): ?>
      <div class="bar-row"><span class="bar-lbl"><?= crop_emoji($c['crop_name']) ?> <?= e($c['crop_name']) ?></span><div class="bar"><i class="bar-top" style="width:<?= round($c['c'] / $maxC * 100) ?>%"></i></div><b><?= (int)$c['c'] ?></b></div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>💬 Waiting for a reply</h2><a href="<?= e(url('admin/messages.php')) ?>">Open inbox →</a></div>
    <?php if (!$inbox): ?><div class="empty"><div class="big-emoji">✅</div><p>You are all caught up.</p></div><?php endif; ?>
    <?php foreach ($inbox as $i): ?>
      <a class="row-item link" href="<?= e(url('admin/messages.php?farmer=' . (int)$i['id'])) ?>">
        <div class="avatar"><?= e(initials($i['full_name'])) ?></div>
        <div class="row-main"><strong><?= e($i['full_name']) ?></strong><small><?= e(time_ago($i['last_at'])) ?></small></div>
        <span class="nav-badge"><?= (int)$i['n'] ?></span>
      </a>
    <?php endforeach; ?>
    <h3 class="sub">New farmers</h3>
    <?php foreach ($recent as $r): ?>
      <a class="row-item link" href="<?= e(url('admin/farmer.php?id=' . (int)$r['id'])) ?>">
        <div class="avatar"><?= e(initials($r['full_name'])) ?></div>
        <div class="row-main"><strong><?= e($r['full_name']) ?></strong><small><?= e($r['location']) ?> · joined <?= e(time_ago($r['created_at'])) ?></small></div>
        <span class="chip st-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
      </a>
    <?php endforeach; ?>
  </section>
</div>
<?php render_foot(); ?>
