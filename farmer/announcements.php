<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$news = db()->query('SELECT * FROM announcements ORDER BY id DESC LIMIT 50')->fetchAll();
render_head('Farm News', 'announcements');
?>
<div class="page-head"><div><h1>📢 Farm News</h1><p class="muted">Updates from your farm office.</p></div></div>
<?php if (!$news): ?><div class="card empty"><div class="big-emoji">📭</div><p>No news yet. Check back soon.</p></div><?php endif; ?>
<div class="news-list">
<?php foreach ($news as $n): ?>
  <article class="card news-item cat-<?= e(strtolower($n['category'])) ?>">
    <div class="news-top"><span class="chip"><?= cat_emoji($n['category']) ?> <?= e($n['category']) ?></span><small><?= e(utc_to_local($n['created_at'], 'd M Y')) ?></small></div>
    <h2><?= e($n['title']) ?></h2>
    <p><?= nl2br(e($n['body'])) ?></p>
  </article>
<?php endforeach; ?>
</div>
<?php render_foot(); ?>
