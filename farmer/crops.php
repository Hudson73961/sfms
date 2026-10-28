<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
render_head('My Crops', 'crops');
?>
<div class="page-head"><div><h1>🌱 My Crops</h1><p class="muted">Add what you plant. It saves even without internet.</p></div>
  <button class="btn btn-lg" id="toggle-form" type="button">➕ Add a crop</button></div>

<div class="record-banner"><img src="<?= e(url('assets/img/field-banner.jpg')) ?>" alt="Green crop fields growing on a farm" width="1400" height="900" loading="lazy"></div>

<section class="card form-card" id="crop-card" hidden>
  <h2>New crop</h2>
  <form id="crop-form" novalidate>
    <div class="grid-2">
      <div class="field"><label for="crop_select">Crop</label>
        <select id="crop_select"><?php foreach (CROP_LIST as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="field" id="other-wrap" hidden><label for="crop_other">Crop name</label><input id="crop_other" maxlength="60" placeholder="Type the crop name"></div>
      <div class="field"><label for="field_name">Field name <small>(optional)</small></label><input id="field_name" maxlength="60" placeholder="e.g. North field"></div>
      <div class="field"><label for="area_acres">Size in acres <small>(optional)</small></label><input id="area_acres" type="number" inputmode="decimal" min="0" max="100000" step="0.01" placeholder="e.g. 1.5"></div>
      <div class="field"><label for="planting_date">Planting date</label><input id="planting_date" type="date"></div>
      <div class="field"><label for="harvest_date">Expected harvest date</label><input id="harvest_date" type="date"></div>
      <div class="field"><label for="expected_yield_kg">Expected yield, kg <small>(optional)</small></label><input id="expected_yield_kg" type="number" inputmode="decimal" min="0" step="1" placeholder="e.g. 800"></div>
      <div class="field"><label for="status">Stage</label>
        <select id="status"><option value="planted">🌱 Planted</option><option value="growing">🌿 Growing</option><option value="harvested">🌾 Harvested</option></select></div>
    </div>
    <div class="field"><label for="notes">Notes <small>(optional)</small></label><textarea id="notes" rows="2" maxlength="500" placeholder="Seed type, fertiliser used, problems seen…"></textarea></div>
    <div class="form-actions"><button class="btn btn-lg" type="submit">💾 Save crop</button><button class="btn btn-ghost btn-lg" type="button" id="cancel-form">Cancel</button></div>
  </form>
</section>

<div class="filters" id="crop-filters" role="tablist" aria-label="Filter crops">
  <button class="pill-btn active" data-filter="all">All</button>
  <button class="pill-btn" data-filter="planted">🌱 Planted</button>
  <button class="pill-btn" data-filter="growing">🌿 Growing</button>
  <button class="pill-btn" data-filter="harvested">🌾 Harvested</button>
</div>
<div id="crop-list" class="crop-grid" aria-live="polite"><div class="skeleton"></div><div class="skeleton"></div></div>
<?php render_foot(['crops.js']); ?>
