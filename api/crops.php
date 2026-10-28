<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
$u = require_login('farmer', true);
json_out(['crops' => farmer_crops((int)$u['id'])]);
