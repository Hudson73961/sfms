<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$guides = [
    ['Soil and planting', '🌱', 'Use a soil test where available. Select a crop and seed variety suited to your local rainfall and soil, prepare drainage, and follow the seed supplier or local extension officer planting guidance.'],
    ['Water and irrigation', '💧', 'Check soil moisture before watering. Water near the roots when possible, and inspect irrigation equipment for leaks. Adjust watering to crop stage and local conditions.'],
    ['Pest monitoring', '🐛', 'Inspect a few plants across the field regularly and record what you see. Confirm the pest or disease before treatment, and follow label and local extension guidance for safe handling.'],
    ['Harvest and storage', '🌾', 'Harvest at the appropriate maturity for the crop. Keep produce clean and dry, use sound storage, and record quantity, quality, and sales to improve future planning.'],
    ['Livestock care', '🐄', 'Provide clean water, suitable feed, shade, and a clean resting area. Keep health and vaccination records, and contact a qualified animal-health professional when animals are ill.'],
    ['Farm records', '📒', 'Record dates, quantities, inputs, and costs as they happen. Clear records help you plan, notice changes, and understand which activities are worthwhile.'],
];
render_head('Tips and Guides', 'guides');
?>
<div class="page-head"><div><h1>📚 Farming tips &amp; guides</h1><p class="muted">Short, practical reminders to support your farm decisions.</p></div></div>
<div class="guide-hero"><img src="<?= e(url('assets/img/market-produce.jpg')) ?>" alt="Fresh farm produce prepared for market" width="900" height="600" loading="lazy"><div><span class="chip">Learn and grow</span><h2>Good records. Better decisions.</h2><p>Use these general reminders alongside advice from your local agricultural extension officer.</p></div></div>
<div class="guide-grid">
  <?php foreach ($guides as [$title, $icon, $text]): ?><article class="card guide-card"><div class="f-ico"><?= $icon ?></div><h2><?= e($title) ?></h2><p><?= e($text) ?></p></article><?php endforeach; ?>
</div>
<?php render_foot(); ?>
