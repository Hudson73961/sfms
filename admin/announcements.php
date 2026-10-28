<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$pdo = db();
$err = ''; $v = ['title' => '', 'body' => '', 'category' => 'General'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        audit((int)$u['id'], 'announcement_delete', (string)(int)($_POST['id'] ?? 0));
        flash('success', 'Announcement deleted.'); redirect('admin/announcements.php');
    }
    $v = ['title' => trim((string)($_POST['title'] ?? '')), 'body' => trim((string)($_POST['body'] ?? '')), 'category' => (string)($_POST['category'] ?? 'General')];
    if (!in_array($v['category'], ANN_CATEGORIES, true)) $v['category'] = 'General';
    if ($v['title'] === '' || slen($v['title']) > 120) $err = 'Please enter a title (up to 120 characters).';
    elseif ($v['body'] === '' || slen($v['body']) > 3000) $err = 'Please write the message (up to 3000 characters).';
    else {
        $pdo->prepare('INSERT INTO announcements(admin_id,title,body,category) VALUES(?,?,?,?)')->execute([$u['id'], $v['title'], $v['body'], $v['category']]);
        audit((int)$u['id'], 'announcement_create', $v['title']);
        flash('success', 'Announcement posted — farmers will see it on their Home page. 📢'); redirect('admin/announcements.php');
    }
}
$list = $pdo->query('SELECT * FROM announcements ORDER BY id DESC LIMIT 100')->fetchAll();
render_head('News', 'announcements');
?>
<div class="page-head"><div><h1>📢 Announcements</h1><p class="muted">Share weather warnings, prices and training with all farmers.</p></div></div>
<div class="cols">
  <section class="card">
    <h2>New announcement</h2>
    <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="category">Category</label><select id="category" name="category"><?php foreach (ANN_CATEGORIES as $c): ?><option<?= $v['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="120" required value="<?= e($v['title']) ?>"></div>
      <div class="field"><label for="body">Message</label><textarea id="body" name="body" rows="5" maxlength="3000" required><?= e($v['body']) ?></textarea></div>
      <button class="btn btn-lg" type="submit">📢 Post to all farmers</button>
    </form>
  </section>
  <section class="card">
    <h2>Posted (<?= count($list) ?>)</h2>
    <?php if (!$list): ?><div class="empty"><p>Nothing posted yet.</p></div><?php endif; ?>
    <?php foreach ($list as $n): ?>
      <article class="news-item cat-<?= e(strtolower($n['category'])) ?>">
        <div class="news-top"><span class="chip"><?= cat_emoji($n['category']) ?> <?= e($n['category']) ?></span><small><?= e(utc_to_local($n['created_at'], 'd M Y, H:i')) ?></small></div>
        <h3><?= e($n['title']) ?></h3><p><?= nl2br(e($n['body'])) ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this announcement?">🗑 Delete</button></form>
      </article>
    <?php endforeach; ?>
  </section>
</div>
<?php render_foot(); ?>
