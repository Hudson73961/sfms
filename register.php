<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
if ($u = current_user()) redirect(home_for($u));
start_session();
$err = []; $v = ['full_name' => '', 'email' => '', 'phone' => '', 'location' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['email'] = strtolower($v['email']);
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if (!empty($_POST['website'])) { redirect('login.php'); }                       // honeypot: bots fill this in
    if ($v['full_name'] === '' || slen($v['full_name']) > 80) $err['full_name'] = 'Please enter your full name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) $err['email'] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\s()\-]{7,20}$/', $v['phone'])) $err['phone'] = 'Please enter a valid phone number.';
    if ($v['location'] === '' || slen($v['location']) > 80) $err['location'] = 'Please enter your village / town.';
    if ($m = strong_password($pw)) $err['password'] = $m;
    elseif ($pw !== $pw2) $err['password2'] = 'The two passwords do not match.';
    if (!$err) {
        $st = db()->prepare("SELECT COUNT(*) FROM audit_log WHERE action='register' AND ip=? AND created_at>?");
        $st->execute([client_ip(), gmdate('Y-m-d H:i:s', time() - 3600)]);
        if ((int)$st->fetchColumn() >= MAX_REGISTER_PER_HOUR) $err['email'] = 'Too many sign-ups from this network. Please try again later.';
    }
    if (!$err) {
        $st = db()->prepare('SELECT 1 FROM users WHERE email=?'); $st->execute([$v['email']]);
        if ($st->fetch()) $err['email'] = 'That email is already registered. Try signing in.';
    }
    if (!$err) {
        $status = REQUIRE_APPROVAL ? 'pending' : 'active';
        db()->prepare("INSERT INTO users(role,full_name,email,phone,location,password_hash,status) VALUES('farmer',?,?,?,?,?,?)")
            ->execute([$v['full_name'], $v['email'], $v['phone'], $v['location'], hash_pw($pw), $status]);
        audit((int)db()->lastInsertId(), 'register');
        flash('success', REQUIRE_APPROVAL ? 'Account created! Please wait for the administrator to approve it.' : 'Account created! You can now sign in. 🎉');
        redirect('login.php');
    }
}
function fe(array $err, string $k): string { return isset($err[$k]) ? '<small class="field-error">' . e($err[$k]) . '</small>' : ''; }
render_head('Create account', '', ['public' => true]);
?>
<main class="auth-wrap">
  <div class="auth-side">
    <a class="brand" href="<?= e(url('')) ?>"><?= logo_svg(44) ?><span><?= e(APP_NAME) ?></span></a>
    <h2>Join Smart Farmer 🌾</h2>
    <p>It takes one minute. Then you can record crops and message the farm office anytime.</p>
    <ul class="ticks"><li>Record every crop and field</li><li>Get advice and farm news</li><li>Chat with the administrator</li></ul>
  </div>
  <div class="card auth-card">
    <h1>Create farmer account</h1>
    <form method="post" novalidate autocomplete="on">
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="80" autocomplete="name" value="<?= e($v['full_name']) ?>"><?= fe($err, 'full_name') ?></div>
      <div class="grid-2">
        <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="20" autocomplete="tel" placeholder="07xx xxx xxx" value="<?= e($v['phone']) ?>"><?= fe($err, 'phone') ?></div>
        <div class="field"><label for="location">Village / Town</label><input id="location" name="location" required maxlength="80" placeholder="e.g. Thika" value="<?= e($v['location']) ?>"><?= fe($err, 'location') ?></div>
      </div>
      <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" inputmode="email" required maxlength="190" autocomplete="email" value="<?= e($v['email']) ?>"><?= fe($err, 'email') ?></div>
      <?= pw_field('password', 'Password', 'new-password', 'At least 8 characters with letters and numbers.') ?><?= fe($err, 'password') ?>
      <?= pw_field('password2', 'Confirm password', 'new-password') ?><?= fe($err, 'password2') ?>
      <button class="btn btn-lg btn-block" type="submit">Create my account</button>
    </form>
    <p class="muted center mt">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
  </div>
</main>
<?php render_foot(); ?>
