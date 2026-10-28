<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='farmer'");
    $st->execute([$id]);
    $f = $st->fetch();
    if (!$f) { flash('error', 'Farmer not found.'); redirect('admin/farmers.php'); }
    if ($act === 'status' && in_array($_POST['status'] ?? '', ['active', 'suspended', 'pending'], true)) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$_POST['status'], $id]);
        audit((int)$u['id'], 'farmer_status', $f['email'] . ' → ' . $_POST['status']);
        flash('success', $f['full_name'] . ' is now ' . $_POST['status'] . '.');
    } elseif ($act === 'reset') {
        $tmp = 'Sf-' . strtoupper(bin2hex(random_bytes(3))) . '-' . random_int(10, 99);
        $pdo->prepare('UPDATE users SET password_hash=?, must_change=1 WHERE id=?')->execute([hash_pw($tmp), $id]);
        $pdo->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$f['email']]);
        audit((int)$u['id'], 'farmer_reset_password', $f['email']);
        flash('warn', 'Temporary password for ' . $f['full_name'] . ': ' . $tmp . ' — give it to the farmer now. They must change it at next sign-in. It will not be shown again.');
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        audit((int)$u['id'], 'farmer_delete', $f['email']);
        flash('success', 'Farmer deleted.');
    }
    redirect('admin/farmers.php' . (!empty($_POST['back']) && preg_match('/^[\w=&%.+\-]*$/', $_POST['back']) ? '?' . $_POST['back'] : ''));
}

$q = trim((string)($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['active', 'pending', 'suspended'], true) ? $_GET['status'] : '';
$sql = "SELECT u.*, (SELECT COUNT(*) FROM crops c WHERE c.user_id=u.id) crops FROM users u WHERE u.role='farmer'";
$args = [];
if ($q !== '') { $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.location LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $sql = str_replace('LIKE ?', "LIKE ? ESCAPE '\\'", $sql); array_push($args, $like, $like, $like, $like); }
if ($status) { $sql .= ' AND u.status=?'; $args[] = $status; }
$sql .= ' ORDER BY u.id DESC LIMIT 300';
$st = $pdo->prepare($sql); $st->execute($args);
$rows = $st->fetchAll();
$back = http_build_query(array_filter(['q' => $q, 'status' => $status]));

render_head('Farmers', 'farmers');
?>
<div class="page-head"><div><h1>👩‍🌾 Farmers</h1><p class="muted"><?= count($rows) ?> found</p></div>
  <a class="btn btn-outline" href="<?= e(url('admin/reports.php?export=farmers')) ?>">⬇ Export CSV</a></div>

<form class="search-bar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="🔍 Search name, phone, email or place…" aria-label="Search farmers">
  <select name="status" aria-label="Status"><option value="">All statuses</option><?php foreach (['active', 'pending', 'suspended'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<?php if (!$rows): ?><div class="card empty"><div class="big-emoji">🔎</div><p>No farmers match your search.</p></div><?php endif; ?>
<div class="farmer-list">
<?php foreach ($rows as $r): ?>
  <article class="card farmer-card">
    <div class="fc-top">
      <div class="avatar avatar-lg"><?= e(initials($r['full_name'])) ?></div>
      <div class="fc-main"><h3><a href="<?= e(url('admin/farmer.php?id=' . (int)$r['id'])) ?>"><?= e($r['full_name']) ?></a></h3>
        <small>📍 <?= e($r['location'] ?: '—') ?> · 📞 <?= e($r['phone'] ?: '—') ?></small><small>✉️ <?= e($r['email']) ?></small></div>
      <span class="chip st-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
    </div>
    <div class="fc-meta"><span>🌱 <?= (int)$r['crops'] ?> crops</span><span>Joined <?= e(utc_to_local($r['created_at'], 'd M Y')) ?></span><span>Last login <?= e($r['last_login'] ? time_ago($r['last_login']) : 'never') ?></span></div>
    <div class="fc-actions">
      <a class="btn btn-sm" href="<?= e(url('admin/messages.php?farmer=' . (int)$r['id'])) ?>">💬 Message</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="status">
        <?php if ($r['status'] !== 'active'): ?><button class="btn btn-sm btn-outline" name="status" value="active" type="submit">✅ <?= $r['status'] === 'pending' ? 'Approve' : 'Activate' ?></button><?php endif; ?>
        <?php if ($r['status'] !== 'suspended'): ?><button class="btn btn-sm btn-outline" name="status" value="suspended" type="submit" data-confirm="Suspend this farmer? They will not be able to sign in.">⏸ Suspend</button><?php endif; ?></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-outline" type="submit" data-confirm="Create a new temporary password for this farmer?">🔑 Reset password</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this farmer and ALL their crops and messages? This cannot be undone.">🗑 Delete</button></form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php render_foot(); ?>
