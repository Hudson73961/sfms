<?php
require __DIR__ . '/../app/core.php';
$u = require_login('farmer');
$id = (int)($_GET['id'] ?? $u['id']);
if ($id !== (int)$u['id']) fail_page(403, 'Not allowed', 'You can only view your own profile photo.');
$st = db()->prepare('SELECT image_data,mime_type FROM profile_photos WHERE user_id=?');
$st->execute([$u['id']]);
$photo = $st->fetch();
if (!$photo || !is_string($photo['image_data']) || $photo['image_data'] === '') fail_page(404, 'Photo not found', 'No profile photo has been uploaded.');
if (!in_array($photo['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) fail_page(404, 'Photo not found', 'The saved profile photo type is invalid.');
header('Content-Type: ' . $photo['mime_type']);
header('Content-Length: ' . strlen($photo['image_data']));
header('Content-Disposition: inline; filename="profile-photo"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $photo['image_data'];
