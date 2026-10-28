<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$primary = ['dashboard', 'crops', 'messages', 'profile', 'tools'];
$items = array_filter(nav_items('farmer'), fn($item, $key) => !in_array($key, $primary, true), ARRAY_FILTER_USE_BOTH);
render_head('More farm tools', 'tools');
?>
<div class="page-head"><div><h1>☰ Farm tools</h1><p class="muted">All your farm pages, together in one place.</p></div></div>
<div class="tool-grid">
  <?php foreach ($items as [$href, $icon, $label]): ?>
    <a class="tool-card" href="<?= e(url($href)) ?>"><span class="tool-icon"><?= $icon ?></span><strong><?= e($label) ?></strong><span>Open <?= e(strtolower($label)) ?> →</span></a>
  <?php endforeach; ?>
</div>
<?php render_foot(); ?>
