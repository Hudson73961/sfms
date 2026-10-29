<?php
declare(strict_types=1);
define('SF', true);
require __DIR__ . '/config.php';

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    exit('<h2>PHP SQLite driver is missing</h2><p>Open your <code>php.ini</code>, enable <code>extension=pdo_sqlite</code> and <code>extension=sqlite3</code>, then restart the server.</p>');
}
require __DIR__ . '/db.php';
date_default_timezone_set(TIMEZONE);

/* ---------------- Paths & small helpers ---------------- */
$__doc = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__app = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
define('BASE', ($__doc !== '' && str_starts_with($__app, $__doc)) ? rtrim(substr($__app, strlen($__doc)), '/') : '');

function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function slen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* mbstring may be disabled on some PHP installs (e.g. Windows default) — provide safe fallbacks */
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null, ?string $enc = null): string {
        return implode('', array_slice(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [], $start, $len));
    }
}
if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $s, int $start, int $width, string $trim = '', ?string $enc = null): string {
        $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count($c) <= $width ? $s : implode('', array_slice($c, $start, max(0, $width - count(preg_split('//u', $trim, -1, PREG_SPLIT_NO_EMPTY) ?: [])))) . $trim;
    }
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function utc_to_local(?string $utc, string $fmt = 'd M Y, H:i'): string {
    if (!$utc) return '—';
    try { return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(TIMEZONE))->format($fmt); }
    catch (Throwable) { return '—'; }
}
function iso_utc(?string $utc): ?string { return $utc ? str_replace(' ', 'T', $utc) . 'Z' : null; }
function fmt_date(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }

/* ---------------- Security headers ---------------- */
function send_security_headers(): void {
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}
send_security_headers();

/* ---------------- Sessions ---------------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SFSESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
    $now = time();
    $fp = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!empty($_SESSION['uid'])) {
        $bad = ($now - ($_SESSION['last'] ?? 0)) > SESSION_IDLE
            || ($now - ($_SESSION['start'] ?? 0)) > SESSION_MAX
            || !hash_equals($_SESSION['fp'] ?? '', $fp);
        if ($bad) { session_regenerate_id(true); $_SESSION = []; $_SESSION['expired'] = true; }
    }
    $_SESSION['last'] = $now;
}
function destroy_session(): void {
    start_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

/* ---------------- Responses ---------------- */
function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail_page(int $code, string $title, string $msg): never {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '">'
       . '<body class="public"><main class="auth-wrap"><div class="card auth-card center"><div class="big-emoji">⚠️</div><h1>' . e($title) . '</h1><p class="muted">' . e($msg) . '</p><a class="btn" href="' . e(url('')) . '">Go to home</a></div></main></body>';
    exit;
}
function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}
function flash(string $type, string $msg): void { start_session(); $_SESSION['flash'][] = [$type, $msg]; }
function take_flashes(): array { start_session(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------------- CSRF ---------------- */
function csrf_token(): string { start_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(bool $json = false): void {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $badOrigin = $origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if ($badOrigin || !is_string($t) || !hash_equals(csrf_token(), $t)) {
        if ($json) json_out(['error' => 'Security token expired. Please refresh the page.'], 419);
        fail_page(419, 'Page expired', 'Your security token expired. Go back, refresh the page and try again.');
    }
}

/* ---------------- Auth ---------------- */
function current_user(bool $refresh = false): ?array {
    static $cache = false;
    if ($refresh) $cache = false;
    if ($cache !== false) return $cache;
    start_session();
    if (empty($_SESSION['uid'])) return $cache = null;
    $st = db()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'active') { destroy_session(); return $cache = null; }
    return $cache = $u;
}
function home_for(array $u): string { return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'farmer/dashboard.php'; }

function require_login(?string $role = null, bool $json = false): array {
    $u = current_user();
    if (!$u) {
        if ($json) json_out(['error' => 'Please sign in again.'], 401);
        redirect($role && in_array($role, ['admin', 'farmer'], true) ? 'login.php?role=' . $role : 'login.php');
    }
    if ($role && $u['role'] !== $role) {
        if ($json) json_out(['error' => 'Not allowed.'], 403);
        redirect(home_for($u));
    }
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ((int)$u['must_change'] === 1 && !$json && $self !== 'change-password.php' && $self !== 'logout.php') {
        redirect('change-password.php');
    }
    return $u;
}
function audit(?int $uid, string $action, string $detail = ''): void {
    db()->prepare('INSERT INTO audit_log(user_id,action,detail,ip) VALUES(?,?,?,?)')->execute([$uid, $action, $detail, client_ip()]);
}
function strong_password(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    if (strlen($p) > 200) return 'Password is too long.';
    return null;
}
/** @return array{0:bool,1:string} */
function attempt_login(string $email, string $pass): array {
    $pdo = db();
    $ip = client_ip();
    $since = gmdate('Y-m-d H:i:s', time() - LOCKOUT_SECONDS);
    $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email=? AND ok=0 AND created_at>?');
    $q->execute([$email, $since]);
    $byEmail = (int)$q->fetchColumn();
    $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ok=0 AND created_at>?');
    $q->execute([$ip, $since]);
    $byIp = (int)$q->fetchColumn();
    if ($byEmail >= MAX_LOGIN_FAILS || $byIp >= MAX_LOGIN_FAILS * 5) {
        return [false, 'Too many failed attempts. Please wait ' . (int)(LOCKOUT_SECONDS / 60) . ' minutes and try again.'];
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $st->execute([$email]);
    $u = $st->fetch();
    // Always run a hash check so timing doesn't reveal whether the email exists.
    $ok = password_verify($pass, $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ3Yv1Zr7nq0fJf6o1p5eJ8dq3m0Ka');
    $log = $pdo->prepare('INSERT INTO login_attempts(ip,email,ok) VALUES(?,?,?)');
    if (!$u || !$ok) { $log->execute([$ip, $email, 0]); return [false, 'Incorrect email or password.']; }
    if ($u['status'] === 'pending')   return [false, 'Your account is waiting for approval by the administrator.'];
    if ($u['status'] === 'suspended') return [false, 'Your account has been suspended. Please contact the administrator.'];
    $log->execute([$ip, $email, 1]);
    $pdo->prepare('DELETE FROM login_attempts WHERE email=? AND ok=0')->execute([$email]);
    if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([hash_pw($pass), $u['id']]);
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$u['id'], 'start' => time(), 'last' => time(), 'fp' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 'csrf' => bin2hex(random_bytes(32))];
    $pdo->prepare("UPDATE users SET last_login=datetime('now') WHERE id=?")->execute([$u['id']]);
    audit((int)$u['id'], 'login');
    return [true, ''];
}
