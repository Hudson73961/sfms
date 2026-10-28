<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('login.php');
csrf_verify();
if ($u = current_user()) audit((int)$u['id'], 'logout');
destroy_session();
header('Clear-Site-Data: "cache"');
render_head('Signing out', '', ['public' => true, 'bodyClass' => 'logout']);
?>
<main class="auth-wrap"><div class="card auth-card center">
  <div class="big-emoji">👋</div><h1>You are signed out</h1>
  <p class="muted">Clearing saved data from this device…</p>
  <a class="btn" href="<?= e(url('login.php')) ?>">Sign in again</a>
</div></main>
<?php render_foot(['logout.js']); ?>
