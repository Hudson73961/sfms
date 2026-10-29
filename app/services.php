<?php
defined('SF') or exit;

const CROP_LIST   = ['Maize','Beans','Tomatoes','Potatoes','Kale (Sukuma wiki)','Cabbage','Onions','Carrots','Wheat','Rice','Sorghum','Bananas','Avocado','Mango','Coffee','Tea','Sugarcane','Other'];
const CROP_STATUS = ['planted','growing','harvested'];
const ANN_CATEGORIES = ['General','Weather','Market','Training','Alert'];

function valid_uid(mixed $u): bool { return is_string($u) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $u) === 1; }

/* ---------------- Messages ---------------- */
function msg_out(array $m): array {
    return ['id' => (int)$m['id'], 'farmer_id' => (int)$m['farmer_id'], 'sender_role' => $m['sender_role'],
            'body' => $m['body'], 'created_at' => iso_utc($m['created_at']), 'read' => $m['read_at'] !== null];
}
function create_message(array $user, int $farmerId, string $body, ?string $uid): array {
    $pdo = db();
    $body = trim($body);
    if ($body === '') throw new InvalidArgumentException('Message is empty.');
    if (slen($body) > 2000) throw new InvalidArgumentException('Message is too long (max 2000 characters).');
    if ($user['role'] === 'farmer') {
        $farmerId = (int)$user['id'];
    } else {
        $st = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='farmer'");
        $st->execute([$farmerId]);
        if (!$st->fetch()) throw new InvalidArgumentException('Farmer not found.');
    }
    if ($uid) {
        $st = $pdo->prepare('SELECT * FROM messages WHERE client_uid=? AND sender_id=?');
        $st->execute([$uid, $user['id']]);
        if ($row = $st->fetch()) return $row;           // already saved earlier (retry) — don't duplicate
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender_id=? AND created_at>?');
    $st->execute([$user['id'], gmdate('Y-m-d H:i:s', time() - 60)]);
    if ((int)$st->fetchColumn() >= MAX_MSG_PER_MIN) throw new RuntimeException('You are sending too fast. Please wait a moment.');
    $pdo->prepare('INSERT INTO messages(farmer_id,sender_id,sender_role,body,client_uid) VALUES(?,?,?,?,?)')
        ->execute([$farmerId, $user['id'], $user['role'], $body, $uid]);
    $st = $pdo->prepare('SELECT * FROM messages WHERE id=?');
    $st->execute([$pdo->lastInsertId()]);
    return $st->fetch();
}

/* ---------------- Crops ---------------- */
function date_or_null(mixed $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $dt = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$dt || $dt->format('Y-m-d') !== $v) throw new InvalidArgumentException('Please enter a valid date.');
    return $v;
}
function num_or_null(mixed $v, string $label, float $max): ?float {
    if ($v === null || trim((string)$v) === '') return null;
    if (!is_numeric($v) || (float)$v < 0 || (float)$v > $max) throw new InvalidArgumentException("$label must be a number between 0 and $max.");
    return (float)$v;
}
function clean_crop(array $d): array {
    $name = trim((string)($d['crop_name'] ?? ''));
    if ($name === '' || slen($name) > 60) throw new InvalidArgumentException('Please choose or type a crop name.');
    $field = trim((string)($d['field_name'] ?? ''));
    $notes = trim((string)($d['notes'] ?? ''));
    if (slen($field) > 60) throw new InvalidArgumentException('Field name is too long.');
    if (slen($notes) > 500) throw new InvalidArgumentException('Notes are too long (max 500 characters).');
    $status = (string)($d['status'] ?? 'planted');
    if (!in_array($status, CROP_STATUS, true)) $status = 'planted';
    $plant = date_or_null($d['planting_date'] ?? '');
    $harv  = date_or_null($d['harvest_date'] ?? '');
    if ($plant && $harv && $harv < $plant) throw new InvalidArgumentException('Harvest date must be after the planting date.');
    return ['crop_name' => $name, 'field_name' => $field ?: null, 'area_acres' => num_or_null($d['area_acres'] ?? null, 'Area', 100000),
            'planting_date' => $plant, 'harvest_date' => $harv, 'expected_yield_kg' => num_or_null($d['expected_yield_kg'] ?? null, 'Expected yield', 100000000),
            'status' => $status, 'notes' => $notes ?: null];
}
function crop_out(array $c): array {
    return ['id' => (int)$c['id'], 'crop_name' => $c['crop_name'], 'field_name' => $c['field_name'],
            'area_acres' => $c['area_acres'] !== null ? (float)$c['area_acres'] : null, 'planting_date' => $c['planting_date'],
            'harvest_date' => $c['harvest_date'], 'expected_yield_kg' => $c['expected_yield_kg'] !== null ? (float)$c['expected_yield_kg'] : null,
            'status' => $c['status'], 'notes' => $c['notes']];
}
function farmer_crops(int $userId): array {
    $st = db()->prepare('SELECT * FROM crops WHERE user_id=? ORDER BY (status="harvested"), COALESCE(planting_date, created_at) DESC, id DESC');
    $st->execute([$userId]);
    return array_map('crop_out', $st->fetchAll());
}
function create_crop(int $userId, array $d, ?string $uid): array {
    $pdo = db();
    if ($uid) {
        $st = $pdo->prepare('SELECT * FROM crops WHERE client_uid=? AND user_id=?');
        $st->execute([$uid, $userId]);
        if ($row = $st->fetch()) return $row;
    }
    $c = clean_crop($d);
    $st = $pdo->prepare('SELECT COUNT(*) FROM crops WHERE user_id=?');
    $st->execute([$userId]);
    if ((int)$st->fetchColumn() >= 500) throw new RuntimeException('Crop record limit reached.');
    $pdo->prepare('INSERT INTO crops(user_id,client_uid,crop_name,field_name,area_acres,planting_date,harvest_date,expected_yield_kg,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute([$userId, $uid, $c['crop_name'], $c['field_name'], $c['area_acres'], $c['planting_date'], $c['harvest_date'], $c['expected_yield_kg'], $c['status'], $c['notes']]);
    $st = $pdo->prepare('SELECT * FROM crops WHERE id=?');
    $st->execute([$pdo->lastInsertId()]);
    return $st->fetch();
}

/* ---------------- Farm records ---------------- */
function clean_farm_record(string $type, array $d): array {
    $types = ['livestock', 'activity', 'harvest', 'finance', 'market', 'weather', 'task', 'inventory'];
    if (!in_array($type, $types, true)) throw new InvalidArgumentException('Choose a valid farm record type.');

    $title = trim((string)($d['title'] ?? ''));
    $details = trim((string)($d['details'] ?? ''));
    $unit = trim((string)($d['unit'] ?? ''));
    $status = (string)($d['status'] ?? 'recorded');
    if ($title === '' || slen($title) > 100) throw new InvalidArgumentException('Enter a name of up to 100 characters.');
    if (slen($details) > 2000) throw new InvalidArgumentException('Notes are too long (maximum 2,000 characters).');
    if (slen($unit) > 32) throw new InvalidArgumentException('Unit is too long.');

    $showQuantity = in_array($type, ['livestock', 'harvest', 'inventory'], true);
    $showAmount = in_array($type, ['finance', 'market'], true);
    $quantity = $showQuantity ? num_or_null($d['quantity'] ?? '', 'Quantity', 100000000) : null;
    $amount = $showAmount ? num_or_null($d['amount'] ?? '', 'Amount', 100000000000) : null;
    if ($showQuantity && $quantity === null) throw new InvalidArgumentException('Enter a valid quantity.');
    if ($type === 'finance' && $amount === null) throw new InvalidArgumentException('Enter an amount for this income or expense.');
    if ($type === 'market' && $amount === null) throw new InvalidArgumentException('Enter the observed market price.');

    $recordDate = date_or_null($d['record_date'] ?? '');
    $dueDate = date_or_null($d['due_date'] ?? '');
    if (in_array($type, ['livestock', 'activity', 'harvest', 'finance', 'market', 'weather'], true) && $recordDate === null) {
        throw new InvalidArgumentException('Choose the date for this record.');
    }
    if ($type === 'market' && $details === '') throw new InvalidArgumentException('Record the market or person who provided this price.');
    if ($type === 'task' && $dueDate === null) throw new InvalidArgumentException('Choose a due date for this task.');
    if ($type === 'finance' && !in_array($status, ['income', 'expense'], true)) throw new InvalidArgumentException('Choose income or expense.');
    if ($type === 'task' && !in_array($status, ['planned', 'done'], true)) throw new InvalidArgumentException('Choose a valid task status.');
    if ($type === 'livestock' && !in_array($status, ['healthy', 'monitor'], true)) throw new InvalidArgumentException('Choose a valid animal health status.');
    if ($type === 'inventory' && !in_array($status, ['in_stock', 'low', 'out'], true)) throw new InvalidArgumentException('Choose a valid stock status.');

    return [
        'title' => $title,
        'details' => $details ?: null,
        'quantity' => $quantity,
        'unit' => $unit ?: null,
        'amount' => $amount,
        'record_date' => $recordDate,
        'due_date' => $dueDate,
        'status' => in_array($type, ['finance', 'task', 'livestock', 'inventory'], true) ? $status : 'recorded',
    ];
}

function create_farm_record(int $userId, string $type, array $data, ?string $uid): array {
    $pdo = db();
    if ($uid !== null) {
        $st = $pdo->prepare('SELECT * FROM farm_records WHERE client_uid=? AND user_id=?');
        $st->execute([$uid, $userId]);
        if ($row = $st->fetch()) return $row;
    }
    $record = clean_farm_record($type, $data);
    $st = $pdo->prepare('SELECT COUNT(*) FROM farm_records WHERE user_id=?');
    $st->execute([$userId]);
    if ((int)$st->fetchColumn() >= 2000) throw new RuntimeException('You have reached the farm record limit.');
    $pdo->prepare('INSERT INTO farm_records(user_id,client_uid,record_type,title,details,quantity,unit,amount,record_date,due_date,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$userId, $uid, $type, $record['title'], $record['details'], $record['quantity'], $record['unit'], $record['amount'], $record['record_date'], $record['due_date'], $record['status']]);
    $st = $pdo->prepare('SELECT * FROM farm_records WHERE id=?');
    $st->execute([$pdo->lastInsertId()]);
    return $st->fetch();
}

/* ---------------- Offline sync: one queued action ---------------- */
function process_sync_item(array $user, array $it): array {
    $uid  = $it['uid'] ?? null;
    $type = (string)($it['type'] ?? '');
    $p    = is_array($it['payload'] ?? null) ? $it['payload'] : [];
    if (!valid_uid($uid)) return ['uid' => (string)$uid, 'type' => $type, 'ok' => false, 'error' => 'Invalid item.'];
    try {
        $isFarmer = $user['role'] === 'farmer';
        switch ($type) {
            case 'message':
                $m = create_message($user, (int)($p['farmer_id'] ?? 0), (string)($p['body'] ?? ''), $uid);
                return ['uid' => $uid, 'type' => $type, 'ok' => true, 'data' => msg_out($m)];
            case 'crop_add':
                if (!$isFarmer) throw new RuntimeException('Only farmers can add crops.');
                return ['uid' => $uid, 'type' => $type, 'ok' => true, 'data' => crop_out(create_crop((int)$user['id'], $p, $uid))];
            case 'farm_record_add':
                if (!$isFarmer) throw new RuntimeException('Only farmers can add farm records.');
                $recordType = (string)($p['record_type'] ?? '');
                $record = create_farm_record((int)$user['id'], $recordType, $p, $uid);
                return ['uid' => $uid, 'type' => $type, 'ok' => true, 'data' => ['id' => (int)$record['id'], 'record_type' => $record['record_type']]];
            case 'crop_status':
                if (!$isFarmer) throw new RuntimeException('Only farmers can edit crops.');
                if (!in_array($p['status'] ?? '', CROP_STATUS, true)) throw new InvalidArgumentException('Invalid status.');
                db()->prepare('UPDATE crops SET status=? WHERE id=? AND user_id=?')->execute([$p['status'], (int)($p['id'] ?? 0), $user['id']]);
                return ['uid' => $uid, 'type' => $type, 'ok' => true];
            case 'crop_delete':
                if (!$isFarmer) throw new RuntimeException('Only farmers can delete crops.');
                db()->prepare('DELETE FROM crops WHERE id=? AND user_id=?')->execute([(int)($p['id'] ?? 0), $user['id']]);
                return ['uid' => $uid, 'type' => $type, 'ok' => true];
            default:
                throw new InvalidArgumentException('Unknown action.');
        }
    } catch (InvalidArgumentException | RuntimeException $ex) {
        return ['uid' => $uid, 'type' => $type, 'ok' => false, 'error' => $ex->getMessage()];
    }
}

/* ---------------- Misc ---------------- */
function unread_count(array $u): int {
    $pdo = db();
    if ($u['role'] === 'farmer') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE farmer_id=? AND sender_role='admin' AND read_at IS NULL");
        $st->execute([$u['id']]);
    } else {
        $st = $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_role='farmer' AND read_at IS NULL");
    }
    return (int)$st->fetchColumn();
}
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) $s .= strtoupper(mb_substr($p, 0, 1));
    return $s ?: '?';
}
function crop_emoji(string $name): string {
    static $m = ['maize'=>'🌽','beans'=>'🫘','tomatoes'=>'🍅','potatoes'=>'🥔','kale'=>'🥬','cabbage'=>'🥬','onions'=>'🧅','carrots'=>'🥕','wheat'=>'🌾','rice'=>'🌾','sorghum'=>'🌾','bananas'=>'🍌','avocado'=>'🥑','mango'=>'🥭','coffee'=>'☕','tea'=>'🍃','sugarcane'=>'🎋'];
    $k = strtolower(trim(explode(' ', $name)[0]));
    return $m[$k] ?? '🌱';
}

function cat_emoji(string $c): string {
    return ['General' => '📣', 'Weather' => '⛈️', 'Market' => '💰', 'Training' => '🎓', 'Alert' => '🚨'][$c] ?? '📣';
}
function time_ago(?string $utc): string {
    if (!$utc) return '';
    $d = time() - strtotime($utc . ' UTC');
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 604800) return floor($d / 86400) . ' d ago';
    return utc_to_local($utc, 'd M Y');
}
