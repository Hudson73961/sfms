<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$pdo = db();
$st = $pdo->prepare('SELECT * FROM farm_profiles WHERE user_id=?');
$st->execute([$u['id']]);
$profile = $st->fetch() ?: ['farm_name' => '', 'location' => $u['location'], 'size_acres' => null, 'soil_type' => '', 'notes' => ''];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $profile = [
        'farm_name' => trim((string)($_POST['farm_name'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'size_acres' => $_POST['size_acres'] ?? '',
        'soil_type' => trim((string)($_POST['soil_type'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];
    try {
        if ($profile['farm_name'] === '' || slen($profile['farm_name']) > 100) throw new InvalidArgumentException('Enter a farm name of up to 100 characters.');
        if ($profile['location'] === '' || slen($profile['location']) > 80) throw new InvalidArgumentException('Enter your village, town, or county.');
        if (!in_array($profile['soil_type'], ['', 'Loam', 'Clay', 'Sandy', 'Silt', 'Volcanic', 'Other'], true)) throw new InvalidArgumentException('Choose a valid soil type.');
        if (slen($profile['notes']) > 2000) throw new InvalidArgumentException('Farm notes are too long (maximum 2,000 characters).');
        $size = num_or_null($profile['size_acres'], 'Farm size', 100000);
        $pdo->prepare('INSERT INTO farm_profiles(user_id,farm_name,location,size_acres,soil_type,notes,updated_at) VALUES(?,?,?,?,?,?,datetime(\'now\')) ON CONFLICT(user_id) DO UPDATE SET farm_name=excluded.farm_name,location=excluded.location,size_acres=excluded.size_acres,soil_type=excluded.soil_type,notes=excluded.notes,updated_at=datetime(\'now\')')
            ->execute([$u['id'], $profile['farm_name'], $profile['location'], $size, $profile['soil_type'] ?: null, $profile['notes'] ?: null]);
        $pdo->prepare('UPDATE users SET location=? WHERE id=?')->execute([$profile['location'], $u['id']]);
        audit((int)$u['id'], 'farm_profile_update', $profile['farm_name']);
        flash('success', 'Farm profile saved.');
        redirect('farmer/farm.php');
    } catch (InvalidArgumentException $e) {
        $err = $e->getMessage();
    }
}

render_head('My Farm', 'farm');
?>
<div class="page-head"><div><h1>🌳 My Farm</h1><p class="muted">Keep your farm details in one place. Only you and authorized administrators can see them.</p></div></div>
<section class="farm-banner">
  <img src="<?= e(url('assets/img/field-banner.jpg')) ?>" alt="Green farmland and crop fields" width="1400" height="900" loading="lazy">
  <div><span class="chip">Your farm profile</span><h2><?= e($profile['farm_name'] ?: 'Tell us about your farm') ?></h2><p><?= e($profile['location'] ?: 'Add your village, town, or county') ?></p></div>
</section>
<section class="card">
  <h2>Farm information</h2>
  <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="field"><label for="farm_name">Farm name</label><input id="farm_name" name="farm_name" maxlength="100" required value="<?= e($profile['farm_name']) ?>" placeholder="e.g. Green Valley Farm"></div>
      <div class="field"><label for="location">Village / town / county</label><input id="location" name="location" maxlength="80" required value="<?= e($profile['location']) ?>" placeholder="e.g. Eldoret, Uasin Gishu"></div>
      <div class="field"><label for="size_acres">Farm size in acres</label><input id="size_acres" name="size_acres" type="number" inputmode="decimal" min="0" max="100000" step="0.01" value="<?= e($profile['size_acres']) ?>"></div>
      <div class="field"><label for="soil_type">Main soil type</label><select id="soil_type" name="soil_type">
        <option value="">Choose if known</option><?php foreach (['Loam','Clay','Sandy','Silt','Volcanic','Other'] as $soil): ?><option value="<?= e($soil) ?>"<?= $profile['soil_type'] === $soil ? ' selected' : '' ?>><?= e($soil) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="field"><label for="notes">Farm notes <small>(optional)</small></label><textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Water sources, field names, access notes…"><?= e($profile['notes']) ?></textarea></div>
    <button class="btn btn-lg" type="submit">💾 Save farm details</button>
  </form>
</section>
<?php render_foot(); ?>
