<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
$role = $_GET['role'] ?? '';
$role = in_array($role, ['farmer', 'admin'], true) ? $role : '';
if ($u = current_user()) redirect(home_for($u));
start_session();
$error = ''; $email = '';
if (!empty($_SESSION['expired'])) { unset($_SESSION['expired']); $error = 'You were signed out for your safety. Please sign in again.'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    [$ok, $msg] = attempt_login($email, (string)($_POST['password'] ?? ''));
    if ($ok) {
        $u = current_user(true);
        if ($role !== '' && $u && $u['role'] !== $role) {
            destroy_session();
            flash('error', $role === 'admin'
                ? 'This is the administrator sign-in. Please use an administrator account or choose Farmer sign-in.'
                : 'This is the farmer sign-in. Please use a farmer account or choose Administrator sign-in.');
            redirect('login.php?role=' . $role);
        } else {
            redirect($u && (int)$u['must_change'] ? 'change-password.php' : home_for($u));
        }
    }
    else $error = $msg;
}
render_head('Sign in', '', ['public' => true]);
?>
<main class="auth-wrap">
  <div class="auth-side">
    <a class="brand" href="<?= e(url('')) ?>"><?= logo_svg(44) ?><span><?= e(APP_NAME) ?></span></a>
    <h2><?= $role === 'admin' ? 'Administrator sign-in' : ($role === 'farmer' ? 'Farmer sign-in' : 'Welcome back 👋') ?></h2>
    <p><?= $role === 'admin' ? 'Sign in to manage farmer accounts, crop activity, messages and farm news.' : 'Sign in to see your crops, read farm news and chat with the farm office.' ?></p>
    <ul class="ticks"><li>Separate, role-specific workspace</li><li>Your account and data stay protected</li><li>Use the portal that matches your account</li></ul>
  </div>
  <div class="card auth-card">
    <h1><?= $role === 'admin' ? 'Administrator sign in' : ($role === 'farmer' ? 'Farmer sign in' : 'Sign in') ?></h1>
    <?php show_flashes(); ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="email">Email address</label>
        <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required maxlength="190" value="<?= e($email) ?>" autofocus></div>
      <?= pw_field('password', 'Password') ?>
      <button class="btn btn-lg btn-block" type="submit">Sign in</button>
    </form>
    <?php if ($role !== 'admin'): ?><p class="muted center mt">New here? <a href="<?= e(url('register.php')) ?>">Create a farmer account</a></p><?php endif; ?>
    <p class="muted center mt">Wrong portal? <a href="<?= e(url('login.php?role=' . ($role === 'admin' ? 'farmer' : 'admin'))) ?>">Switch to <?= $role === 'admin' ? 'farmer' : 'administrator' ?> sign in</a></p>
  </div>
</main>
<?php render_foot(); ?>
