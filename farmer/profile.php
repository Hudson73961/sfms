<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$photo = db()->prepare('SELECT 1 FROM profile_photos WHERE user_id=?');
$photo->execute([$u['id']]);
$hasPhoto = (bool)$photo->fetchColumn();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $n = trim((string)($_POST['full_name'] ?? '')); $p = trim((string)($_POST['phone'] ?? '')); $l = trim((string)($_POST['location'] ?? ''));
    if ($n === '' || slen($n) > 80) $err = 'Please enter your full name.';
    elseif (!preg_match('/^[0-9+\s()\-]{7,20}$/', $p)) $err = 'Please enter a valid phone number.';
    elseif ($l === '' || slen($l) > 80) $err = 'Please enter your village / town.';
    else {
        $upload = $_FILES['profile_photo'] ?? null;
        $photoData = null; $photoMime = null;
        if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) $err = 'The profile photo could not be uploaded.';
            elseif ((int)$upload['size'] > 2097152) $err = 'The profile photo must be 2 MB or smaller.';
            else {
                $photoData = file_get_contents($upload['tmp_name']);
                if ($photoData === false) {
                    $err = 'The profile photo could not be read.';
                } else {
                    $photoMime = match (true) {
                        str_starts_with($photoData, "\xFF\xD8\xFF") => 'image/jpeg',
                        str_starts_with($photoData, "\x89PNG\r\n\x1A\n") => 'image/png',
                        substr($photoData, 0, 4) === 'RIFF' && substr($photoData, 8, 4) === 'WEBP' => 'image/webp',
                        default => null,
                    };
                    if ($photoMime === null) { $photoData = null; $err = 'Choose a JPEG, PNG, or WebP image.'; }
                    elseif (function_exists('getimagesize')) {
                        $image = getimagesize($upload['tmp_name']);
                        if (!$image || $image[0] > 4000 || $image[1] > 4000 || $image['mime'] !== $photoMime) {
                            $photoData = null; $photoMime = null; $err = 'The image is invalid or larger than 4,000 × 4,000 pixels.';
                        }
                    }
                }
            }
        }
        if ($err === '') {
            $pdo = db();
            if ($photoData !== null) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE users SET full_name=?,phone=?,location=? WHERE id=?')->execute([$n, $p, $l, $u['id']]);
                    $pdo->prepare('INSERT INTO profile_photos(user_id,image_data,mime_type,updated_at) VALUES(?,?,?,datetime(\'now\')) ON CONFLICT(user_id) DO UPDATE SET image_data=excluded.image_data,mime_type=excluded.mime_type,updated_at=datetime(\'now\')')
                        ->execute([$u['id'], $photoData, $photoMime]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
            } elseif (!empty($_POST['remove_photo'])) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE users SET full_name=?,phone=?,location=? WHERE id=?')->execute([$n, $p, $l, $u['id']]);
                    $pdo->prepare('DELETE FROM profile_photos WHERE user_id=?')->execute([$u['id']]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
            } else {
                $pdo->prepare('UPDATE users SET full_name=?,phone=?,location=? WHERE id=?')->execute([$n, $p, $l, $u['id']]);
            }
            audit((int)$u['id'], 'profile_update');
            flash('success', 'Profile saved. ✅');
            redirect('farmer/profile.php');
        }
    }
    $u['full_name'] = $n; $u['phone'] = $p; $u['location'] = $l;
}
render_head('Profile', 'profile');
?>
<div class="page-head"><div><h1>👤 My Profile</h1></div></div>
<div class="cols">
  <section class="card">
    <h2>My details</h2>
    <div class="profile-photo-wrap">
      <?php if ($hasPhoto): ?><img class="profile-photo" src="<?= e(url('api/avatar.php?id=' . (int)$u['id'])) ?>" alt="Profile photo of <?= e($u['full_name']) ?>" width="96" height="96">
      <?php else: ?><div class="avatar avatar-lg profile-avatar" aria-hidden="true"><?= e(initials($u['full_name'])) ?></div><?php endif; ?>
      <span class="muted"><?= $hasPhoto ? 'Your profile photo' : 'Add a profile photo (optional)' ?></span>
    </div>
    <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="profile_photo">Profile photo <small>(JPEG, PNG, or WebP · up to 2 MB)</small></label><input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp"></div>
      <?php if ($hasPhoto): ?><label class="photo-remove"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?>
      <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="80" value="<?= e($u['full_name']) ?>"></div>
      <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" required maxlength="20" value="<?= e($u['phone']) ?>"></div>
      <div class="field"><label for="location">Village / Town</label><input id="location" name="location" required maxlength="80" value="<?= e($u['location']) ?>"></div>
      <div class="field"><label>Email <small>(cannot be changed)</small></label><input value="<?= e($u['email']) ?>" disabled></div>
      <button class="btn btn-lg" type="submit">💾 Save changes</button>
    </form>
  </section>
  <section class="card">
    <h2>Account &amp; device</h2>
    <p class="muted">Member since <?= e(utc_to_local($u['created_at'], 'd M Y')) ?></p>
    <div class="row-item"><div class="row-ico">🔒</div><div class="row-main"><strong>Password</strong><small>Change it regularly to stay safe</small></div><a class="btn btn-sm btn-outline" href="<?= e(url('change-password.php')) ?>">Change</a></div>
    <div class="row-item"><div class="row-ico">📡</div><div class="row-main"><strong>Offline status</strong><small id="sync-status">Checking…</small></div></div>
    <div class="row-item" id="install-row" hidden><div class="row-ico">📲</div><div class="row-main"><strong>Install the app</strong><small>Add Smart Farmer to your home screen</small></div><button class="btn btn-sm" id="install-btn" type="button">Install</button></div>
  </section>
</div>
<?php render_foot(); ?>
