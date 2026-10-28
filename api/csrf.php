<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
$u = require_login(null, true);
json_out(['csrf' => csrf_token(), 'uid' => (int)$u['id'], 'role' => $u['role']]);
