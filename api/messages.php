<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
$u = require_login(null, true);
$pdo = db();
$action = (string)($_GET['action'] ?? 'list');

if ($action === 'unread') json_out(['unread' => unread_count($u)]);

if ($action === 'conversations') {
    if ($u['role'] !== 'admin') json_out(['error' => 'Not allowed.'], 403);
    $rows = $pdo->query("
        SELECT u.id, u.full_name, u.phone, u.location,
          (SELECT body FROM messages m WHERE m.farmer_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_body,
          (SELECT created_at FROM messages m WHERE m.farmer_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_at,
          (SELECT COUNT(*) FROM messages m WHERE m.farmer_id=u.id AND m.sender_role='farmer' AND m.read_at IS NULL) AS unread
        FROM users u WHERE u.role='farmer' AND u.status='active'
        ORDER BY last_at DESC, u.full_name COLLATE NOCASE")->fetchAll();
    json_out(['conversations' => array_map(fn($r) => [
        'id' => (int)$r['id'], 'name' => $r['full_name'], 'phone' => $r['phone'], 'location' => $r['location'],
        'last' => $r['last_body'] !== null ? mb_substr($r['last_body'], 0, 70) : null,
        'last_at' => iso_utc($r['last_at']), 'unread' => (int)$r['unread'],
    ], $rows)]);
}

if ($action === 'list') {
    $peer = null;
    if ($u['role'] === 'farmer') {
        $fid = (int)$u['id'];
    } else {
        $fid = (int)($_GET['farmer_id'] ?? 0);
        $st = $pdo->prepare("SELECT id, full_name, phone, location FROM users WHERE id=? AND role='farmer'");
        $st->execute([$fid]);
        $peer = $st->fetch();
        if (!$peer) json_out(['error' => 'Farmer not found.'], 404);
    }
    $after = max(0, (int)($_GET['after'] ?? 0));
    if ($after > 0) {
        $st = $pdo->prepare('SELECT * FROM messages WHERE farmer_id=? AND id>? ORDER BY id LIMIT 200');
        $st->execute([$fid, $after]);
    } else {
        $st = $pdo->prepare('SELECT * FROM (SELECT * FROM messages WHERE farmer_id=? ORDER BY id DESC LIMIT 100) ORDER BY id');
        $st->execute([$fid]);
    }
    $msgs = array_map('msg_out', $st->fetchAll());
    $other = $u['role'] === 'admin' ? 'farmer' : 'admin';
    if (($_GET['view'] ?? '') === '1') {
        $pdo->prepare("UPDATE messages SET read_at=datetime('now') WHERE farmer_id=? AND sender_role=? AND read_at IS NULL")->execute([$fid, $other]);
    }
    $st = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM messages WHERE farmer_id=? AND sender_role=? AND read_at IS NOT NULL');
    $st->execute([$fid, $u['role']]);
    json_out(['messages' => $msgs, 'last_read_id' => (int)$st->fetchColumn(),
              'peer' => $peer ? ['id' => (int)$peer['id'], 'name' => $peer['full_name'], 'phone' => $peer['phone'], 'location' => $peer['location']] : null]);
}
json_out(['error' => 'Unknown action.'], 400);
