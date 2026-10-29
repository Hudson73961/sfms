<?php
require __DIR__ . '/../app/core.php';
if ($user = current_user()) redirect(home_for($user));
redirect('login.php?role=admin');
