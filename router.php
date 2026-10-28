<?php
/* Router for PHP's built-in server (php -S).
   Blocks direct access to private folders/files so nobody can download the database. */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (
    str_contains($path, "\0") ||
    preg_match('#(^|/)(app|data|\.vscode|\.git)(/|$)#i', $path) ||
    preg_match('#\.(sqlite|sqlite-wal|sqlite-shm|db|log|bat|sh)$#i', $path) ||
    preg_match('#(^|/)router\.php$#i', $path)
) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    return true;
}
return false;   // let PHP serve the file normally
