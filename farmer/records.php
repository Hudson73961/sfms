<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
require __DIR__ . '/../app/layout.php';
$u = require_login('farmer');
$pdo = db();

$pages = [
    'livestock' => ['Livestock', 'livestock', '🐄', 'Record animals, herd size, health notes, and vaccination reminders.', 'Animal or flock', 'Animal type, breed, or group', true, true, false],
    'activity' => ['Farm activities', 'activities', '🛠️', 'Keep a dated record of planting, watering, spraying, weeding, and feeding.', 'Activity', 'e.g. Irrigated the east field', false, false, false],
    'harvest' => ['Harvest management', 'harvest', '🌾', 'Record harvested produce, quality notes, storage, and sale amounts.', 'Crop or produce', 'e.g. Maize harvest', true, false, true],
    'finance' => ['Farm finance', 'finance', '💰', 'Record farm income and expenses to see your running balance.', 'Income or expense', 'e.g. Fertiliser, seed, produce sale', false, false, true],
    'market' => ['Market prices', 'markets', '🛒', 'Keep dated local price notes and selling opportunities, with their source.', 'Crop or market opportunity', 'e.g. Maize price at Eldoret market', false, false, true],
    'weather' => ['Weather log', 'weather', '🌦️', 'Save weather observations and the farming actions you took.', 'Weather observation', 'e.g. Light rain, cloudy morning', false, false, false],
    'task' => ['Farm calendar', 'calendar', '🗓️', 'Plan farm work, expected harvests, and animal-health reminders.', 'Task or reminder', 'e.g. Vaccinate goats', false, true, false],
    'inventory' => ['Inventory', 'inventory', '📦', 'Track feed, seed, fertiliser, tools, and other farm supplies.', 'Supply or tool', 'e.g. Maize seed', true, false, false],
];
$type = (string)($_GET['type'] ?? '');
if (!isset($pages[$type]) && !in_array($type, ['calendar', 'notifications', 'reports'], true)) fail_page(404, 'Page not found', 'Choose a page from the farmer menu.');
if ($type === 'support') redirect('farmer/support.php');

function record_csv_cell(mixed $value): string {
    $value = (string)$value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) $value = "'" . $value;
    return '"' . str_replace('"', '""', $value) . '"';
}

if ($type === 'reports' && ($_GET['export'] ?? '') === 'records') {
    $st = $pdo->prepare('SELECT record_type,title,details,quantity,unit,amount,status,record_date,due_date FROM farm_records WHERE user_id=? ORDER BY id DESC');
    $st->execute([$u['id']]);
    audit((int)$u['id'], 'export', 'farm_records');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my-farm-records-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF", implode(',', array_map('record_csv_cell', ['Type', 'Name', 'Details', 'Quantity', 'Unit', 'Amount', 'Status', 'Date', 'Due date'])), "\r\n";
    foreach ($st->fetchAll() as $row) echo implode(',', array_map('record_csv_cell', array_values($row))), "\r\n";
    exit;
}

$config = $pages[$type] ?? null;
$edit = null;
$err = '';
if ($config && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM farm_records WHERE id=? AND user_id=? AND record_type=?');
    $st->execute([(int)$_GET['edit'], $u['id'], $type]);
    $edit = $st->fetch();
    if (!$edit) fail_page(404, 'Record not found', 'That record does not exist in your account.');
}

if ($config && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $st = $pdo->prepare('DELETE FROM farm_records WHERE id=? AND user_id=? AND record_type=?');
        $st->execute([$id, $u['id'], $type]);
        if (!$st->rowCount()) fail_page(404, 'Record not found', 'That record could not be found in your account.');
        audit((int)$u['id'], 'farm_record_delete', $type . ':' . $id);
        flash('success', 'Farm record deleted.');
        redirect('farmer/records.php?type=' . $type);
    }

    $entry = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'details' => trim((string)($_POST['details'] ?? '')),
        'quantity' => $_POST['quantity'] ?? '',
        'unit' => trim((string)($_POST['unit'] ?? '')),
        'amount' => $_POST['amount'] ?? '',
        'record_date' => $_POST['record_date'] ?? '',
        'due_date' => $_POST['due_date'] ?? '',
        'status' => (string)($_POST['status'] ?? 'recorded'),
    ];
    try {
        if ($entry['title'] === '' || slen($entry['title']) > 100) throw new InvalidArgumentException('Enter a name of up to 100 characters.');
        if (slen($entry['details']) > 2000) throw new InvalidArgumentException('Notes are too long (maximum 2,000 characters).');
        if (slen($entry['unit']) > 32) throw new InvalidArgumentException('Unit is too long.');
        $quantity = $config[6] ? num_or_null($entry['quantity'], 'Quantity', 100000000) : null;
        if ($config[6] && $entry['quantity'] !== '' && $quantity === null) throw new InvalidArgumentException('Enter a valid quantity.');
        $amount = $config[8] ? num_or_null($entry['amount'], 'Amount', 100000000000) : null;
        if ($config[8] && $entry['amount'] !== '' && $amount === null) throw new InvalidArgumentException('Enter a valid amount.');
        if ($type === 'finance' && $amount === null) throw new InvalidArgumentException('Enter an amount for this income or expense.');
        if ($type === 'market' && $amount === null) throw new InvalidArgumentException('Enter the observed market price.');
        if (in_array($type, ['livestock', 'harvest', 'inventory'], true) && $quantity === null) throw new InvalidArgumentException('Enter a quantity.');
        $recordDate = date_or_null($entry['record_date']);
        $dueDate = date_or_null($entry['due_date']);
        if (in_array($type, ['livestock', 'activity', 'harvest', 'finance', 'market', 'weather'], true) && $recordDate === null) throw new InvalidArgumentException('Choose the date for this record.');
        if ($type === 'market' && $entry['details'] === '') throw new InvalidArgumentException('Record the market or person who provided this price.');
        if ($type === 'task' && $dueDate === null) throw new InvalidArgumentException('Choose a due date for this task.');
        if ($type === 'finance' && !in_array($entry['status'], ['income', 'expense'], true)) throw new InvalidArgumentException('Choose income or expense.');
        if ($type === 'task' && !in_array($entry['status'], ['planned', 'done'], true)) throw new InvalidArgumentException('Choose a valid task status.');
        if ($type === 'livestock' && !in_array($entry['status'], ['healthy', 'monitor'], true)) throw new InvalidArgumentException('Choose a valid animal health status.');
        if ($type === 'inventory' && !in_array($entry['status'], ['in_stock', 'low', 'out'], true)) throw new InvalidArgumentException('Choose a valid stock status.');
        $status = in_array($type, ['finance', 'task', 'livestock', 'inventory'], true) ? $entry['status'] : 'recorded';

        if ($id > 0) {
            $st = $pdo->prepare('UPDATE farm_records SET title=?,details=?,quantity=?,unit=?,amount=?,record_date=?,due_date=?,status=?,updated_at=datetime(\'now\') WHERE id=? AND user_id=? AND record_type=?');
            $st->execute([$entry['title'], $entry['details'] ?: null, $quantity, $entry['unit'] ?: null, $amount, $recordDate, $dueDate, $status, $id, $u['id'], $type]);
            if (!$st->rowCount()) {
                $check = $pdo->prepare('SELECT 1 FROM farm_records WHERE id=? AND user_id=? AND record_type=?');
                $check->execute([$id, $u['id'], $type]);
                if (!$check->fetchColumn()) fail_page(404, 'Record not found', 'That record could not be found in your account.');
            }
            audit((int)$u['id'], 'farm_record_update', $type . ':' . $id);
        } else {
            $count = $pdo->prepare('SELECT COUNT(*) FROM farm_records WHERE user_id=?');
            $count->execute([$u['id']]);
            if ((int)$count->fetchColumn() >= 2000) throw new RuntimeException('You have reached the farm record limit.');
            $pdo->prepare('INSERT INTO farm_records(user_id,record_type,title,details,quantity,unit,amount,record_date,due_date,status) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$u['id'], $type, $entry['title'], $entry['details'] ?: null, $quantity, $entry['unit'] ?: null, $amount, $recordDate, $dueDate, $status]);
            audit((int)$u['id'], 'farm_record_create', $type . ':' . $entry['title']);
        }
        flash('success', 'Farm record saved.');
        redirect('farmer/records.php?type=' . $type);
    } catch (InvalidArgumentException | RuntimeException $e) {
        $err = $e->getMessage();
        $edit = $entry + ['id' => $id, 'record_type' => $type];
    }
}

if ($config) {
    $st = $pdo->prepare('SELECT * FROM farm_records WHERE user_id=? AND record_type=? ORDER BY COALESCE(record_date,due_date,created_at) DESC,id DESC LIMIT 100');
    $st->execute([$u['id'], $type]);
    $records = $st->fetchAll();
    [$title, $active, $icon, $description, $nameLabel, $placeholder, $showQuantity, $showDue, $showAmount] = $config;
    $image = match ($type) { 'livestock' => 'livestock.jpg', 'harvest', 'market' => 'market-produce.jpg', default => 'field-banner.jpg' };
    render_head($title, $active);
    ?>
    <div class="page-head"><div><h1><?= $icon ?> <?= e($title) ?></h1><p class="muted"><?= e($description) ?></p></div></div>
    <?php if (in_array($type, ['livestock', 'harvest', 'market'], true)): ?>
      <div class="record-banner"><img src="<?= e(url('assets/img/' . $image)) ?>" alt="<?= e($type === 'livestock' ? 'Livestock on a working farm' : 'Fresh produce ready for the market') ?>" width="900" height="600" loading="lazy"></div>
    <?php endif; ?>
    <section class="card form-card">
      <h2><?= $edit ? 'Update record' : 'Add a record' ?></h2>
      <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="grid-2">
          <div class="field"><label for="title"><?= e($nameLabel) ?></label><input id="title" name="title" required maxlength="100" value="<?= e($edit['title'] ?? '') ?>" placeholder="<?= e($placeholder) ?>"></div>
          <?php if ($showQuantity): ?><div class="field"><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" inputmode="decimal" min="0" max="100000000" step="0.01" value="<?= e($edit['quantity'] ?? '') ?>"<?= in_array($type, ['livestock', 'harvest', 'inventory'], true) ? ' required' : '' ?>></div>
          <div class="field"><label for="unit">Unit <small>(e.g. kg, bags, heads)</small></label><input id="unit" name="unit" maxlength="32" value="<?= e($edit['unit'] ?? '') ?>"></div><?php endif; ?>
          <?php if ($showAmount): ?><div class="field"><label for="amount"><?= $type === 'market' ? 'Observed price (KES)' : 'Amount (KES)' ?><?= $type === 'finance' ? '' : ($type === 'market' ? '' : ' (optional)') ?></label><input id="amount" name="amount" type="number" inputmode="decimal" min="0" max="100000000000" step="0.01" value="<?= e($edit['amount'] ?? '') ?>"<?= in_array($type, ['finance', 'market'], true) ? ' required' : '' ?>></div><?php endif; ?>
          <?php if ($type === 'finance'): ?><div class="field"><label for="status">Entry type</label><select id="status" name="status"><option value="expense"<?= ($edit['status'] ?? 'expense') === 'expense' ? ' selected' : '' ?>>Expense</option><option value="income"<?= ($edit['status'] ?? '') === 'income' ? ' selected' : '' ?>>Income</option></select></div><?php endif; ?>
          <?php if ($type === 'task'): ?><div class="field"><label for="status">Task status</label><select id="status" name="status"><option value="planned"<?= ($edit['status'] ?? 'planned') === 'planned' ? ' selected' : '' ?>>Planned</option><option value="done"<?= ($edit['status'] ?? '') === 'done' ? ' selected' : '' ?>>Done</option></select></div><?php endif; ?>
          <?php if ($type === 'livestock'): ?><div class="field"><label for="status">Health status</label><select id="status" name="status"><option value="healthy"<?= ($edit['status'] ?? 'healthy') === 'healthy' ? ' selected' : '' ?>>Healthy</option><option value="monitor"<?= ($edit['status'] ?? '') === 'monitor' ? ' selected' : '' ?>>Needs monitoring</option></select></div><?php endif; ?>
          <?php if ($type === 'inventory'): ?><div class="field"><label for="status">Stock status</label><select id="status" name="status"><option value="in_stock"<?= ($edit['status'] ?? 'in_stock') === 'in_stock' ? ' selected' : '' ?>>In stock</option><option value="low"<?= ($edit['status'] ?? '') === 'low' ? ' selected' : '' ?>>Low</option><option value="out"<?= ($edit['status'] ?? '') === 'out' ? ' selected' : '' ?>>Out of stock</option></select></div><?php endif; ?>
          <?php if (in_array($type, ['activity', 'harvest', 'finance', 'market', 'weather', 'livestock'], true)): ?><div class="field"><label for="record_date"><?= $type === 'weather' ? 'Observation date' : 'Record date' ?></label><input id="record_date" name="record_date" type="date" value="<?= e($edit['record_date'] ?? date('Y-m-d')) ?>" required></div><?php endif; ?>
          <?php if ($showDue): ?><div class="field"><label for="due_date"><?= $type === 'livestock' ? 'Next vaccination / health check' : 'Due date / reminder date' ?></label><input id="due_date" name="due_date" type="date" value="<?= e($edit['due_date'] ?? '') ?>"<?= $type === 'task' ? ' required' : '' ?>></div><?php endif; ?>
        </div>
        <div class="field"><label for="details"><?= $type === 'market' ? 'Market, source, and notes' : ($type === 'weather' ? 'Location, source, and farming notes' : 'Notes / details') ?><?= $type === 'market' ? '' : ' (optional)' ?></label><textarea id="details" name="details" rows="3" maxlength="2000" placeholder="<?= $type === 'market' ? 'Record the market or person who quoted this price and the date checked.' : '' ?>"<?= $type === 'market' ? ' required' : '' ?>><?= e($edit['details'] ?? '') ?></textarea></div>
        <div class="form-actions"><button class="btn btn-lg" type="submit">💾 Save record</button><?php if ($edit): ?><a class="btn btn-ghost btn-lg" href="<?= e(url('farmer/records.php?type=' . $type)) ?>">Cancel</a><?php endif; ?></div>
      </form>
    </section>
    <section class="card"><div class="card-head"><h2>Recent records</h2><span class="chip"><?= count($records) ?> shown</span></div>
      <?php if (!$records): ?><div class="empty"><div class="big-emoji"><?= $icon ?></div><p>No records yet. Add your first entry above.</p></div><?php endif; ?>
      <?php foreach ($records as $record): ?>
        <article class="record-row">
          <div class="row-ico"><?= $icon ?></div>
          <div class="row-main"><strong><?= e($record['title']) ?><?php if ($type === 'finance'): ?> · KES <?= e(number_format((float)$record['amount'], 2)) ?><?php endif; ?></strong>
            <small><?php if ($record['quantity'] !== null): ?><?= e(rtrim(rtrim(number_format((float)$record['quantity'], 2), '0'), '.')) ?> <?= e($record['unit']) ?> · <?php endif; ?><?= e($record['record_date'] ? fmt_date($record['record_date']) : 'No date') ?><?php if ($record['due_date']): ?> · due <?= e(fmt_date($record['due_date'])) ?><?php endif; ?> · <?= e(str_replace('_', ' ', ucfirst($record['status']))) ?></small>
            <?php if ($record['amount'] !== null && $type !== 'finance'): ?><small>Price / amount: KES <?= e(number_format((float)$record['amount'], 2)) ?></small><?php endif; ?>
            <?php if ($record['details']): ?><p class="record-details"><?= nl2br(e($record['details'])) ?></p><?php endif; ?>
          </div>
          <div class="record-actions"><a class="btn btn-sm btn-outline" href="<?= e(url('farmer/records.php?type=' . $type . '&edit=' . (int)$record['id'])) ?>">Edit</a>
            <form method="post" data-confirm="Delete this record?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form></div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php render_foot(); exit;
}

if ($type === 'calendar') {
    $events = [];
    $st = $pdo->prepare("SELECT crop_name title, harvest_date event_date, 'Expected crop harvest' event_type, status FROM crops WHERE user_id=? AND status!='harvested' AND harvest_date IS NOT NULL");
    $st->execute([$u['id']]);
    foreach ($st->fetchAll() as $event) $events[] = $event;
    $st = $pdo->prepare("SELECT title, due_date event_date, CASE WHEN record_type='livestock' THEN 'Animal health reminder' ELSE 'Farm task' END event_type, status FROM farm_records WHERE user_id=? AND due_date IS NOT NULL AND status!='done'");
    $st->execute([$u['id']]);
    foreach ($st->fetchAll() as $event) $events[] = $event;
    usort($events, fn($a, $b) => strcmp($a['event_date'], $b['event_date']));
    render_head('Farm Calendar', 'calendar');
    ?>
    <div class="page-head"><div><h1>🗓️ Farm Calendar</h1><p class="muted">Upcoming harvests, tasks, and animal-health reminders from your saved records.</p></div><a class="btn" href="<?= e(url('farmer/records.php?type=task')) ?>">➕ Add task</a></div>
    <section class="card"><div class="card-head"><h2>Scheduled dates</h2><span class="chip"><?= count($events) ?> events</span></div>
      <?php if (!$events): ?><div class="empty"><div class="big-emoji">🗓️</div><p>No dates scheduled yet. Add a task or record a crop with its expected harvest date.</p></div><?php endif; ?>
      <?php foreach ($events as $event): $days = (int)floor((strtotime($event['event_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
        <div class="row-item"><div class="row-ico"><?= $event['event_type'] === 'Expected crop harvest' ? '🌾' : ($event['event_type'] === 'Animal health reminder' ? '💉' : '✅') ?></div><div class="row-main"><strong><?= e($event['title']) ?></strong><small><?= e($event['event_type']) ?> · <?= e(fmt_date($event['event_date'])) ?></small></div><span class="chip<?= $days < 0 ? ' chip-warn' : '' ?>"><?= $days < 0 ? abs($days) . ' days overdue' : ($days === 0 ? 'Today' : 'In ' . $days . ' days') ?></span></div>
      <?php endforeach; ?>
    </section>
    <?php render_foot(); exit;
}

if ($type === 'notifications') {
    $st = $pdo->prepare("SELECT title,due_date,record_type,status FROM farm_records WHERE user_id=? AND due_date IS NOT NULL AND due_date<=date('now','+30 days') AND status NOT IN ('done','out') ORDER BY due_date LIMIT 30");
    $st->execute([$u['id']]);
    $reminders = $st->fetchAll();
    $unread = unread_count($u);
    $news = $pdo->query('SELECT id,title,category,created_at FROM announcements ORDER BY id DESC LIMIT 5')->fetchAll();
    render_head('Notifications', 'notifications');
    ?>
    <div class="page-head"><div><h1>🔔 Notifications</h1><p class="muted">Messages, new farm news, and reminders saved to your farm calendar.</p></div></div>
    <section class="card"><div class="card-head"><h2>Messages</h2><?php if ($unread): ?><span class="nav-badge"><?= $unread ?> new</span><?php endif; ?></div><div class="row-item"><div class="row-ico">💬</div><div class="row-main"><strong><?= $unread ? $unread . ' unread message' . ($unread === 1 ? '' : 's') : 'No unread messages' ?></strong><small>Messages from your administrator</small></div><a class="btn btn-sm btn-outline" href="<?= e(url('farmer/messages.php')) ?>">Open messages</a></div></section>
    <section class="card"><h2>Upcoming reminders</h2><?php if (!$reminders): ?><div class="empty"><p>No reminders due in the next 30 days.</p><a href="<?= e(url('farmer/records.php?type=calendar')) ?>">Open calendar</a></div><?php endif; ?>
      <?php foreach ($reminders as $r): ?><div class="row-item"><div class="row-ico"><?= $r['record_type'] === 'livestock' ? '💉' : '🗓️' ?></div><div class="row-main"><strong><?= e($r['title']) ?></strong><small><?= e(fmt_date($r['due_date'])) ?> · <?= e($r['record_type'] === 'livestock' ? 'Animal health' : 'Farm task') ?></small></div><a href="<?= e(url('farmer/records.php?type=' . ($r['record_type'] === 'livestock' ? 'livestock' : 'calendar'))) ?>">View</a></div><?php endforeach; ?>
    </section>
    <section class="card"><h2>Latest news</h2><?php if (!$news): ?><p class="muted">No announcements yet.</p><?php endif; ?><?php foreach ($news as $n): ?><div class="row-item"><div class="row-ico">📢</div><div class="row-main"><strong><?= e($n['title']) ?></strong><small><?= e($n['category']) ?> · <?= e(time_ago($n['created_at'])) ?></small></div><a href="<?= e(url('farmer/announcements.php')) ?>">Read</a></div><?php endforeach; ?></section>
    <?php render_foot(); exit;
}

$st = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN status='income' THEN amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN status='expense' THEN amount ELSE 0 END),0) expenses FROM farm_records WHERE user_id=? AND record_type='finance'");
$st->execute([$u['id']]);
$finance = $st->fetch();
$st = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) quantity, COUNT(*) records FROM farm_records WHERE user_id=? AND record_type='harvest'");
$st->execute([$u['id']]);
$harvest = $st->fetch();
$st = $pdo->prepare("SELECT record_type,COUNT(*) n FROM farm_records WHERE user_id=? GROUP BY record_type");
$st->execute([$u['id']]);
$counts = $st->fetchAll(PDO::FETCH_KEY_PAIR);
render_head('Farm Reports', 'reports');
?>
<div class="page-head"><div><h1>📈 Farm reports</h1><p class="muted">Summaries calculated from records you have entered. Export your records for your own files.</p></div><a class="btn btn-outline" href="<?= e(url('farmer/records.php?type=reports&export=records')) ?>">⬇ Export CSV</a></div>
<section class="stats">
  <div class="stat s-green"><span class="s-ico">🌾</span><b><?= e(number_format((float)$harvest['quantity'], 1)) ?></b><span>Harvest quantity (<?= (int)$harvest['records'] ?> records)</span></div>
  <div class="stat s-gold"><span class="s-ico">💵</span><b>KES <?= e(number_format((float)$finance['income'], 0)) ?></b><span>Recorded income</span></div>
  <div class="stat s-brown"><span class="s-ico">🧾</span><b>KES <?= e(number_format((float)$finance['expenses'], 0)) ?></b><span>Recorded expenses</span></div>
  <div class="stat s-blue"><span class="s-ico">📊</span><b>KES <?= e(number_format((float)$finance['income'] - (float)$finance['expenses'], 0)) ?></b><span>Recorded balance (income − expenses)</span></div>
</section>
<section class="card"><h2>Records by category</h2>
  <?php if (!$counts): ?><div class="empty"><p>Add farm records to see your summaries here.</p></div><?php endif; ?>
  <?php $labels = ['livestock' => ['🐄', 'Livestock'], 'activity' => ['🛠️', 'Activities'], 'harvest' => ['🌾', 'Harvests'], 'finance' => ['💰', 'Finance entries'], 'market' => ['🛒', 'Market notes'], 'weather' => ['🌦️', 'Weather observations'], 'task' => ['🗓️', 'Tasks'], 'inventory' => ['📦', 'Inventory items']]; foreach ($labels as $key => [$emoji, $label]): if (!isset($counts[$key])) continue; ?>
    <div class="row-item"><div class="row-ico"><?= $emoji ?></div><div class="row-main"><strong><?= e($label) ?></strong></div><span class="chip"><?= (int)$counts[$key] ?> records</span></div>
  <?php endforeach; ?>
</section>
<section class="card"><h2>Keep your records up to date</h2><p class="muted">This report reflects entries saved in Smart Farmer; it does not estimate unsaved costs, forecast yields, or fetch live prices.</p><div class="btn-row"><a class="btn btn-outline" href="<?= e(url('farmer/records.php?type=finance')) ?>">Farm finance</a><a class="btn btn-outline" href="<?= e(url('farmer/records.php?type=harvest')) ?>">Harvest records</a></div></section>
<?php render_foot(); ?>
