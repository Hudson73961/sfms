<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('admin');
$pdo = db();

function csv_cell(mixed $v): string {
    $s = (string)$v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) $s = "'" . $s;       // block spreadsheet formula injection
    return '"' . str_replace('"', '""', $s) . '"';
}
if (in_array($_GET['export'] ?? '', ['farmers', 'crops', 'records'], true)) {
    $type = $_GET['export'];
    audit((int)$u['id'], 'export', $type);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="smart-farmer-' . $type . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    if ($type === 'farmers') {
        echo implode(',', array_map('csv_cell', ['Name', 'Email', 'Phone', 'Location', 'Status', 'Crops', 'Joined', 'Last login'])), "\r\n";
        foreach ($pdo->query("SELECT u.*, (SELECT COUNT(*) FROM crops c WHERE c.user_id=u.id) n FROM users u WHERE role='farmer' ORDER BY full_name")->fetchAll() as $r)
            echo implode(',', array_map('csv_cell', [$r['full_name'], $r['email'], $r['phone'], $r['location'], $r['status'], $r['n'], utc_to_local($r['created_at'], 'Y-m-d'), utc_to_local($r['last_login'], 'Y-m-d H:i')])), "\r\n";
    } elseif ($type === 'crops') {
        echo implode(',', array_map('csv_cell', ['Farmer', 'Location', 'Crop', 'Field', 'Acres', 'Planted', 'Harvest', 'Expected yield (kg)', 'Stage', 'Notes'])), "\r\n";
        foreach ($pdo->query('SELECT c.*, u.full_name, u.location FROM crops c JOIN users u ON u.id=c.user_id ORDER BY u.full_name, c.crop_name')->fetchAll() as $r)
            echo implode(',', array_map('csv_cell', [$r['full_name'], $r['location'], $r['crop_name'], $r['field_name'], $r['area_acres'], $r['planting_date'], $r['harvest_date'], $r['expected_yield_kg'], $r['status'], $r['notes']])), "\r\n";
    } else {
        echo implode(',', array_map('csv_cell', ['Farmer', 'Location', 'Type', 'Name', 'Details', 'Quantity', 'Unit', 'Amount (KES)', 'Status', 'Date', 'Due date'])), "\r\n";
        foreach ($pdo->query("SELECT r.*,u.full_name,u.location FROM farm_records r JOIN users u ON u.id=r.user_id WHERE u.role='farmer' ORDER BY r.id DESC")->fetchAll() as $r)
            echo implode(',', array_map('csv_cell', [$r['full_name'], $r['location'], $r['record_type'], $r['title'], $r['details'], $r['quantity'], $r['unit'], $r['amount'], $r['status'], $r['record_date'], $r['due_date']])), "\r\n";
    }
    exit;
}
$byCrop = $pdo->query("SELECT crop_name, COUNT(*) n, COALESCE(SUM(area_acres),0) acres, COALESCE(SUM(expected_yield_kg),0) yield FROM crops GROUP BY crop_name ORDER BY n DESC, crop_name")->fetchAll();
$byPlace = $pdo->query("SELECT COALESCE(NULLIF(location,''),'Unknown') place, COUNT(*) n FROM users WHERE role='farmer' GROUP BY place ORDER BY n DESC LIMIT 10")->fetchAll();
$audit = $pdo->query('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 15')->fetchAll();
$farmRecords = $pdo->query("SELECT record_type,COUNT(*) n FROM farm_records GROUP BY record_type ORDER BY n DESC")->fetchAll();
$recordCount = array_sum(array_map(fn($r) => (int)$r['n'], $farmRecords));
render_head('Reports', 'reports');
?>
<div class="page-head"><div><h1>📄 Reports</h1><p class="muted">Summaries you can print or open in Excel.</p></div>
  <div class="btn-row"><a class="btn btn-outline" href="<?= e(url('admin/reports.php?export=farmers')) ?>">⬇ Farmers CSV</a><a class="btn btn-outline" href="<?= e(url('admin/reports.php?export=crops')) ?>">⬇ Crops CSV</a><a class="btn btn-outline" href="<?= e(url('admin/reports.php?export=records')) ?>">⬇ Farm records CSV</a></div></div>
<div class="cols">
  <section class="card"><h2>🌽 Crops overview</h2>
    <?php if (!$byCrop): ?><div class="empty"><p>No crops recorded yet.</p></div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Crop</th><th class="num">Records</th><th class="num">Acres</th><th class="num">Expected kg</th></tr></thead><tbody>
    <?php foreach ($byCrop as $r): ?><tr><td><?= crop_emoji($r['crop_name']) ?> <?= e($r['crop_name']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= e(rtrim(rtrim(number_format((float)$r['acres'], 2), '0'), '.') ?: '0') ?></td><td class="num"><?= e(number_format((float)$r['yield'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></section>
  <section class="card"><h2>📍 Farmers by area</h2>
    <?php if (!$byPlace): ?><div class="empty"><p>No farmers yet.</p></div><?php endif; ?>
    <?php foreach ($byPlace as $r): ?><div class="row-item"><div class="row-ico">📍</div><div class="row-main"><strong><?= e($r['place']) ?></strong></div><span class="chip"><?= (int)$r['n'] ?></span></div><?php endforeach; ?>
  </section>
</div>
<section class="card"><div class="card-head"><h2>🗂️ Farmer-submitted farm records</h2><a href="<?= e(url('admin/farm-records.php')) ?>">Review records →</a></div>
  <?php if (!$farmRecords): ?><div class="empty"><p>No farm activity records have been submitted yet.</p></div><?php else: ?><p class="muted"><?= $recordCount ?> records across <?= count($farmRecords) ?> categories</p>
    <?php foreach ($farmRecords as $r): ?><div class="row-item"><div class="row-ico">📒</div><div class="row-main"><strong><?= e(ucfirst($r['record_type'])) ?></strong></div><span class="chip"><?= (int)$r['n'] ?></span></div><?php endforeach; ?>
  <?php endif; ?>
</section>
<section class="card"><h2>🛡️ Recent security activity</h2>
  <div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead><tbody>
  <?php foreach ($audit as $a): ?><tr><td><?= e(utc_to_local($a['created_at'], 'd M H:i')) ?></td><td><?= e($a['full_name'] ?? '—') ?></td><td><?= e($a['action']) ?></td><td><?= e($a['detail']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<?php render_foot(); ?>
