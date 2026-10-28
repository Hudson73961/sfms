<?php
defined('SF') or exit;

/* ============ Smart Farmer — settings you may change ============ */
const APP_NAME         = 'Smart Farmer';
const APP_TAGLINE      = 'Grow smarter. Stay connected.';
const TIMEZONE         = 'Africa/Nairobi';

const DB_FILE          = __DIR__ . '/../data/smartfarmer.sqlite';

const REQUIRE_APPROVAL = false;   // true = new farmers must be approved by an admin before they can log in
const SEED_DEMO        = false;   // opt in to sample data for local demonstrations

define('DEFAULT_ADMIN_EMAIL', getenv('SMARTFARMER_ADMIN_EMAIL') ?: 'admin@smartfarmer.local');
define('DEFAULT_ADMIN_PASS', getenv('SMARTFARMER_ADMIN_PASSWORD') ?: '');
define('DEMO_FARMER_PASS', getenv('SMARTFARMER_DEMO_FARMER_PASSWORD') ?: '');

/* ---- Security ---- */
const SESSION_IDLE     = 1800;    // seconds of inactivity before auto logout (30 min)
const SESSION_MAX      = 28800;   // absolute session length (8 hours)
const MAX_LOGIN_FAILS  = 5;       // wrong passwords per account before lock
const LOCKOUT_SECONDS  = 900;     // lock time (15 min)
const MAX_MSG_PER_MIN  = 30;      // anti-spam: messages per user per minute
const MAX_REGISTER_PER_HOUR = 5;  // registrations per IP per hour
