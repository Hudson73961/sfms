<?php
defined('SF') or exit;

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
    migrate($pdo);
    $pdo->beginTransaction();
    try {
        migrate_default_admin_login($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    seed($pdo);
    return $pdo;
}

function migrate(PDO $p): void {
    $p->exec("
    CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL CHECK(role IN ('admin','farmer')),
        full_name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        phone TEXT, location TEXT,
        password_hash TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('pending','active','suspended')),
        must_change INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        last_login TEXT
    );
    CREATE TABLE IF NOT EXISTS crops(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        client_uid TEXT UNIQUE,
        crop_name TEXT NOT NULL, field_name TEXT, area_acres REAL,
        planting_date TEXT, harvest_date TEXT, expected_yield_kg REAL,
        status TEXT NOT NULL DEFAULT 'planted' CHECK(status IN ('planted','growing','harvested')),
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_crops_user ON crops(user_id);
    CREATE TABLE IF NOT EXISTS messages(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        farmer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        sender_role TEXT NOT NULL CHECK(sender_role IN ('admin','farmer')),
        body TEXT NOT NULL,
        client_uid TEXT UNIQUE,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        read_at TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_msg_farmer ON messages(farmer_id, id);
    CREATE TABLE IF NOT EXISTS announcements(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        title TEXT NOT NULL, body TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'General',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS farm_profiles(
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        farm_name TEXT NOT NULL,
        location TEXT NOT NULL,
        size_acres REAL,
        soil_type TEXT,
        notes TEXT,
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS profile_photos(
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        image_data BLOB NOT NULL,
        mime_type TEXT NOT NULL CHECK(mime_type IN ('image/jpeg','image/png','image/webp')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS farm_records(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        record_type TEXT NOT NULL CHECK(record_type IN ('livestock','activity','harvest','finance','market','weather','task','inventory')),
        title TEXT NOT NULL,
        details TEXT,
        quantity REAL,
        unit TEXT,
        amount REAL,
        record_date TEXT,
        due_date TEXT,
        status TEXT NOT NULL DEFAULT 'recorded',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_farm_records_user_type ON farm_records(user_id, record_type, id DESC);
    CREATE INDEX IF NOT EXISTS idx_farm_records_dates ON farm_records(user_id, due_date, record_date);
    CREATE TABLE IF NOT EXISTS login_attempts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL, email TEXT NOT NULL, ok INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_la ON login_attempts(email, created_at);
    CREATE TABLE IF NOT EXISTS audit_log(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, action TEXT NOT NULL, detail TEXT, ip TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    ");
}

function migrate_default_admin_login(PDO $p): void {
    $previousEmails = ['admin@smartfarmer.local'];
    $find = $p->prepare("SELECT id FROM users WHERE role='admin' AND email=?");
    $conflict = $p->prepare('SELECT 1 FROM users WHERE email=? AND id<>?');
    $update = $p->prepare('UPDATE users SET email=?,password_hash=?,must_change=0 WHERE id=?');

    foreach ($previousEmails as $previousEmail) {
        if (strcasecmp(DEFAULT_ADMIN_EMAIL, $previousEmail) === 0) continue;
        $find->execute([$previousEmail]);
        $id = $find->fetchColumn();
        if ($id === false) continue;
        if (DEFAULT_ADMIN_PASS === '') {
            throw new RuntimeException('Set SMARTFARMER_ADMIN_PASSWORD before migrating the default administrator account.');
        }

        $conflict->execute([DEFAULT_ADMIN_EMAIL, $id]);
        if ($conflict->fetchColumn()) {
            throw new RuntimeException('Cannot update the default administrator email because it is already in use.');
        }

        $update->execute([DEFAULT_ADMIN_EMAIL, hash_pw(DEFAULT_ADMIN_PASS), $id]);
    }
}

function hash_pw(string $pw): string {
    return password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

function seed(PDO $p): void {
    if ((int)$p->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;
    if (!filter_var(DEFAULT_ADMIN_EMAIL, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Set SMARTFARMER_ADMIN_EMAIL to a valid email address before creating the first administrator.');
    }
    if (DEFAULT_ADMIN_PASS === '') {
        throw new RuntimeException('Set SMARTFARMER_ADMIN_PASSWORD before the first run to create the administrator account.');
    }
    if (SEED_DEMO && DEMO_FARMER_PASS === '') {
        throw new RuntimeException('Set SMARTFARMER_DEMO_FARMER_PASSWORD before enabling demo data.');
    }
    $u = $p->prepare('INSERT INTO users(role,full_name,email,phone,location,password_hash,status,must_change) VALUES(?,?,?,?,?,?,?,?)');
    $u->execute(['admin', 'System Administrator', DEFAULT_ADMIN_EMAIL, '+254700000000', 'Head Office', hash_pw(DEFAULT_ADMIN_PASS), 'active', 0]);
    $adminId = (int)$p->lastInsertId();
    if (!SEED_DEMO) return;

    $u->execute(['farmer', 'Jane Wanjiku', 'farmer@smartfarmer.local', '+254711223344', 'Thika, Kiambu', hash_pw(DEMO_FARMER_PASS), 'active', 0]);
    $fid = (int)$p->lastInsertId();
    $d = fn(int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
    $c = $p->prepare('INSERT INTO crops(user_id,crop_name,field_name,area_acres,planting_date,harvest_date,expected_yield_kg,status,notes) VALUES(?,?,?,?,?,?,?,?,?)');
    $c->execute([$fid, 'Maize', 'North field', 2.0, $d(-60), $d(60), 1800, 'growing', 'Top-dressed with CAN last week.']);
    $c->execute([$fid, 'Tomatoes', 'Greenhouse', 0.25, $d(-10), $d(70), 900, 'planted', 'Seedlings transplanted.']);
    $c->execute([$fid, 'Beans', 'Lower farm', 1.0, $d(-120), $d(-15), 450, 'harvested', 'Good harvest, sold at the market.']);
    $a = $p->prepare('INSERT INTO announcements(admin_id,title,body,category) VALUES(?,?,?,?)');
    $a->execute([$adminId, 'Welcome to Smart Farmer 🌱', 'You can now record your crops, read updates and chat directly with your extension officer — even when you have no internet.', 'General']);
    $a->execute([$adminId, 'Long rains advisory', 'Expect heavy rainfall in the coming weeks. Prepare drainage channels and avoid applying fertiliser right before heavy rain.', 'Weather']);
    $a->execute([$adminId, 'Free training on composting', 'Join our free composting training at the Thika agricultural office this Friday at 10am.', 'Training']);
    $m = $p->prepare('INSERT INTO messages(farmer_id,sender_id,sender_role,body) VALUES(?,?,?,?)');
    $m->execute([$fid, $adminId, 'admin', 'Karibu Jane! Feel free to message me here if you need advice on your crops.']);
}
