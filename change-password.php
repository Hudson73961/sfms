<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
$u = require_login();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['password'] ?? ''); $new2 = (string)($_POST['password2'] ?? '');
    if (!password_verify($cur, $u['password_hash'])) $err = 'Your current password is incorrect.';
    elseif ($m = strong_password($new)) $err = $m;
    elseif ($new !== $new2) $err = 'The two new passwords do not match.';
    elseif (hash_equals($cur, $new)) $err = 'Choose a password different from the old one.';
    else {
        db()->prepare('UPDATE users SET password_hash=?, must_change=0 WHERE id=?')->execute([hash_pw($new), $u['id']]);
        audit((int)$u['id'], 'password_change');
        session_regenerate_id(true);
        flash('success', 'Password updated successfully. 🔒');
        redirect(home_for($u));
    }
}
render_head('Change password', 'profile');
?>
<div class="page-head"><h1>🔒 Change password</h1></div>
<?php if ((int)$u['must_change']): ?><div class="alert alert-warn">For your safety, please choose a new password before continuing.</div><?php endif; ?>
<div class="card narrow">
  <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <?= pw_field('current', 'Current password') ?>
    <?= pw_field('password', 'New password', 'new-password', 'At least 8 characters with letters and numbers.') ?>
    <?= pw_field('password2', 'Confirm new password', 'new-password') ?>
    <button class="btn btn-lg" type="submit">Save new password</button>
  </form>
</div>
<?php render_foot(); ?>
