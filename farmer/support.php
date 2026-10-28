<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
render_head('Help and Support', 'support');
?>
<div class="page-head"><div><h1>🆘 Help &amp; support</h1><p class="muted">Quick answers and a direct, private way to contact the farm office.</p></div></div>
<section class="card"><h2>Frequently asked questions</h2>
  <details class="faq"><summary>How do I add or update a crop?</summary><p>Open <a href="<?= e(url('farmer/crops.php')) ?>">My Crops</a>, add a crop, and save its field, dates, and growth stage. Crop changes can be queued while offline and are confirmed when the server saves them.</p></details>
  <details class="faq"><summary>How do I save a task or vaccination reminder?</summary><p>Open the <a href="<?= e(url('farmer/records.php?type=calendar')) ?>">Farm Calendar</a> and add a task. Livestock health checks can be recorded under <a href="<?= e(url('farmer/records.php?type=livestock')) ?>">Livestock</a>.</p></details>
  <details class="faq"><summary>Are weather forecasts and market prices live?</summary><p>No. Weather observations and market prices are records you enter yourself. Live forecast and price services are not configured in this installation.</p></details>
  <details class="faq"><summary>Can I use the app offline?</summary><p>Previously opened pages may be available offline on HTTPS or localhost. Crop and message changes can queue for later sync; other farm forms need a live connection. Wait for the app to confirm a queued item is saved.</p></details>
  <details class="faq"><summary>How do I change my password?</summary><p>Open <a href="<?= e(url('farmer/profile.php')) ?>">My Profile</a> and select Change password.</p></details>
</section>
<section class="card support-card"><div class="f-ico">💬</div><div><h2>Contact the administrator</h2><p class="muted">Send a private message about your account or farm records. Your message is saved in your conversation history.</p><a class="btn btn-lg" href="<?= e(url('farmer/messages.php')) ?>">Message the farm office</a></div></section>
<?php render_foot(); ?>
