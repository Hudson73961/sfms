<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
if ($u = current_user()) redirect(home_for($u));
render_head('Welcome', '', ['public' => true]);
?>
<header class="land-nav">
  <a class="brand" href="<?= e(url('')) ?>"><?= logo_svg(40) ?><span><?= e(APP_NAME) ?></span></a>
  <div class="land-actions"><a class="btn btn-ghost" href="<?= e(url('login.php?role=farmer')) ?>">Farmer sign in</a><a class="btn" href="<?= e(url('login.php?role=admin')) ?>">Admin sign in</a></div>
</header>

<section class="hero">
  <div class="hero-text">
    <span class="chip">🌍 Works with or without internet</span>
    <h1>Farm smarter.<br>Stay connected.</h1>
    <p>Record your crops, get advice and news from your extension officer, and chat directly with the farm office — from your phone, even when the network is gone.</p>
    <div class="hero-cta">
      <a class="btn btn-lg" href="#portals">Choose your dashboard</a>
      <a class="btn btn-lg btn-outline" href="<?= e(url('register.php')) ?>">🌱 Create farmer account</a>
    </div>
  </div>
  <div class="hero-art">
    <img src="<?= e(url('assets/img/field-banner.jpg')) ?>" alt="Green fields stretching across a productive rural farm" width="1400" height="900" fetchpriority="high">
  </div>
</section>

<section class="portals" id="portals" aria-labelledby="portals-title">
  <h2 id="portals-title">Choose your dashboard</h2>
  <p class="portals-intro">Sign in through the portal for your role. Farmer and administrator accounts open separate workspaces.</p>
  <div class="portal-grid">
    <article class="portal-card">
      <div class="f-ico">🌱</div>
      <h3>Farmer dashboard</h3>
      <p>Manage your crops, check harvest dates, read farm news and message the office.</p>
      <a class="btn btn-lg btn-block" href="<?= e(url('login.php?role=farmer')) ?>">Sign in as a farmer</a>
      <a class="portal-register" href="<?= e(url('register.php')) ?>">New farmer? Create an account</a>
    </article>
    <article class="portal-card portal-admin">
      <div class="f-ico">📊</div>
      <h3>Administrator dashboard</h3>
      <p>Manage farmer accounts, review crop activity, respond to messages and post news.</p>
      <a class="btn btn-lg btn-block" href="<?= e(url('login.php?role=admin')) ?>">Sign in as administrator</a>
      <span class="portal-register">For authorized administrator accounts</span>
    </article>
  </div>
</section>

<section class="features">
  <article class="feature"><div class="f-ico">🌱</div><h3>My crop records</h3><p>Keep track of what you planted, when to harvest and how much you expect.</p></article>
  <article class="feature"><div class="f-ico">💬</div><h3>Chat with the office</h3><p>Ask questions and get advice directly from the administrator.</p></article>
  <article class="feature"><div class="f-ico">📢</div><h3>Farm news</h3><p>Weather warnings, market prices and training alerts in one place.</p></article>
  <article class="feature"><div class="f-ico">📡</div><h3>Works offline</h3><p>No network? Keep working. Everything syncs automatically when you reconnect.</p></article>
  <article class="feature"><div class="f-ico">🔒</div><h3>Safe &amp; private</h3><p>Encrypted passwords, protected sessions and strict access control.</p></article>
  <article class="feature"><div class="f-ico">📱</div><h3>Use on any phone</h3><p>Install it on your home screen like an app. No downloads from a store needed.</p></article>
</section>
<footer class="land-foot">© <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></footer>
<?php render_foot(); ?>
