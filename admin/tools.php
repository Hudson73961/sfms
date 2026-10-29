<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$primary = ['dashboard', 'farmers', 'messages', 'reports', 'tools'];
$items = array_filter(nav_items('admin'), fn($item, $key) => !in_array($key, $primary, true), ARRAY_FILTER_USE_BOTH);
render_head('More administrator tools', 'tools');
?>
<div class="page-head"><div><h1>☰ Administrator tools</h1><p class="muted">Open the administrator workspaces for records, communications, and reports.</p></div></div>
<div class="tool-grid">
  <?php foreach ($items as [$href, $icon, $label]): ?>
    <a class="tool-card" href="<?= e(url($href)) ?>"><span class="tool-icon"><?= $icon ?></span><strong><?= e($label) ?></strong><span>Open <?= e(strtolower($label)) ?> →</span></a>
  <?php endforeach; ?>
</div>
<?php render_foot(); ?>
