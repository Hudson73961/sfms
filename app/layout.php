<?php
defined('SF') or exit;
require_once __DIR__ . '/services.php';

function logo_svg(int $size = 38): string {
    return '<svg viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" aria-hidden="true"><rect width="64" height="64" rx="16" fill="#1f6b3a"/><path d="M32 51V30" stroke="#fff" stroke-width="4" stroke-linecap="round"/><path d="M32 33c0-10 6-15 16-15 0 10-6 15-16 15z" fill="#a6d65b"/><path d="M32 40c0-8-5-12-14-12 0 8 5 12 14 12z" fill="#d9f0a8"/></svg>';
}
function nav_items(string $role): array {
    return $role === 'admin' ? [
        'dashboard'     => ['admin/dashboard.php', '📊', 'Overview'],
        'farmers'       => ['admin/farmers.php', '👩‍🌾', 'Farmers'],
        'farmer_records'=> ['admin/farm-records.php', '🗂️', 'Farm records'],
        'messages'      => ['admin/messages.php', '💬', 'Messages'],
        'announcements' => ['admin/announcements.php', '📢', 'News'],
        'reports'       => ['admin/reports.php', '📄', 'Reports'],
        'tools'         => ['admin/tools.php', '☰', 'More'],
    ] : [
        'dashboard'     => ['farmer/dashboard.php', '🏡', 'Home'],
        'farm'          => ['farmer/farm.php', '🌳', 'My Farm'],
        'crops'         => ['farmer/crops.php', '🌱', 'My Crops'],
        'livestock'     => ['farmer/records.php?type=livestock', '🐄', 'Livestock'],
        'activities'    => ['farmer/records.php?type=activity', '🛠️', 'Activities'],
        'harvest'       => ['farmer/records.php?type=harvest', '🌾', 'Harvest'],
        'finance'       => ['farmer/records.php?type=finance', '💰', 'Farm finance'],
        'markets'       => ['farmer/records.php?type=market', '🛒', 'Market prices'],
        'weather'       => ['farmer/records.php?type=weather', '🌦️', 'Weather log'],
        'guides'        => ['farmer/guides.php', '📚', 'Tips & guides'],
        'calendar'      => ['farmer/records.php?type=calendar', '🗓️', 'Farm calendar'],
        'inventory'     => ['farmer/records.php?type=inventory', '📦', 'Inventory'],
        'notifications' => ['farmer/records.php?type=notifications', '🔔', 'Notifications'],
        'messages'      => ['farmer/messages.php', '💬', 'Messages'],
        'announcements' => ['farmer/announcements.php', '📢', 'News'],
        'reports'       => ['farmer/records.php?type=reports', '📈', 'Reports'],
        'support'       => ['farmer/support.php', '🆘', 'Help & support'],
        'profile'       => ['farmer/profile.php', '👤', 'Profile'],
        'tools'         => ['farmer/tools.php', '☰', 'More'],
    ];
}

function render_head(string $title, string $active = '', array $o = []): void {
    $u = current_user();
    $public = !$u || !empty($o['public']);
    $GLOBALS['__shell'] = !$public;
    $GLOBALS['__active'] = $active;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    $nav = $u ? nav_items($u['role']) : [];
    $prefetch = $u ? implode(',', array_map(fn($n) => $n[0], $nav)) : '';
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<meta name="theme-color" content="#1f6b3a">
<meta name="description" content="<?= e(APP_TAGLINE) ?>">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/icon.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<meta name="sf-base" content="<?= e(BASE) ?>">
<meta name="sf-csrf" content="<?= e(csrf_token()) ?>">
<meta name="sf-uid" content="<?= $u ? (int)$u['id'] : '' ?>">
<meta name="sf-role" content="<?= $u ? e($u['role']) : '' ?>">
<meta name="sf-prefetch" content="<?= e($prefetch) ?>">
</head>
<body class="<?= $public ? 'public' : 'role-' . e($u['role']) ?> <?= e($o['bodyClass'] ?? '') ?>">
<?php if ($public) { echo '<div id="netbar" class="netbar" role="status" aria-live="polite"></div>'; return; }
    $unread = unread_count($u);
?>
<div class="app">
  <aside class="sidebar">
    <a class="brand" href="<?= e(url(home_for($u))) ?>"><?= logo_svg() ?><span><?= e(APP_NAME) ?><small><?= $u['role'] === 'admin' ? 'Administrator' : 'Farmer' ?></small></span></a>
    <nav aria-label="Main navigation">
      <?php foreach ($nav as $key => [$href, $ico, $label]): ?>
        <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
          <span class="ico"><?= $ico ?></span><span><?= e($label) ?></span>
          <?php if ($key === 'messages'): ?><span class="nav-badge" data-unread<?= $unread ? '' : ' hidden' ?>><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-user">
      <div class="avatar"><?= e(initials($u['full_name'])) ?></div>
      <div class="side-user-info"><strong><?= e($u['full_name']) ?></strong><small><?= e($u['email']) ?></small></div>
    </div>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-block" type="submit">🚪 Log out</button></form>
  </aside>
  <div class="main">
    <header class="topbar">
      <a class="brand brand-sm" href="<?= e(url(home_for($u))) ?>"><?= logo_svg(32) ?><span><?= e(APP_NAME) ?></span></a>
      <span id="pending-chip" class="chip chip-warn" hidden></span>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="topbar-logout"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" aria-label="Log out">🚪</button></form>
    </header>
    <div id="netbar" class="netbar" role="status" aria-live="polite"></div>
    <main class="content" id="content">
<?php foreach (take_flashes() as [$type, $msg]): ?>
      <div class="alert alert-<?= e($type) ?>" role="alert"><?= e($msg) ?></div>
<?php endforeach;
}

function render_foot(array $js = []): void {
    $u = current_user();
    if ($u && !empty($GLOBALS['__shell'])) {
        $active = $GLOBALS['__active'] ?? '';
        $unread = unread_count($u);
        echo "    </main>\n  </div>\n</div>\n";
        echo '<nav class="bottom-nav" aria-label="Main navigation">';
        $mobileKeys = $u['role'] === 'farmer'
            ? ['dashboard', 'crops', 'messages', 'tools', 'profile']
            : ['dashboard', 'farmers', 'messages', 'reports', 'tools'];
        $mobileNav = nav_items($u['role']);
        foreach ($mobileKeys as $key) {
            if (!isset($mobileNav[$key])) continue;
            [$href, $ico, $label] = $mobileNav[$key];
            echo '<a class="bn-link' . ($key === $active ? ' active' : '') . '" href="' . e(url($href)) . '"><span class="ico">' . $ico . '</span><span>' . e($label) . '</span>';
            if ($key === 'messages') echo '<span class="nav-badge" data-unread' . ($unread ? '' : ' hidden') . '>' . $unread . '</span>';
            echo '</a>';
        }
        echo "</nav>\n";
    }
    echo '<div id="toasts" class="toasts" aria-live="polite"></div>' . "\n";
    echo '<script src="' . e(url('assets/js/app.js')) . '" defer></script>' . "\n";
    foreach ($js as $f) echo '<script src="' . e(url('assets/js/' . $f)) . '" defer></script>' . "\n";
    echo "</body>\n</html>";
}

function show_flashes(): void {
    foreach (take_flashes() as [$type, $msg]) echo '<div class="alert alert-' . e($type) . '" role="alert">' . e($msg) . '</div>';
}
function pw_field(string $name, string $label, string $auto = 'current-password', string $hint = ''): string {
    return '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label><div class="pw-wrap">'
        . '<input id="' . e($name) . '" name="' . e($name) . '" type="password" autocomplete="' . e($auto) . '" required minlength="8" maxlength="200">'
        . '<button type="button" class="pw-toggle" data-toggle-password="' . e($name) . '" aria-label="Show or hide password">👁</button></div>'
        . ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
}
