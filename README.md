# 🌾 Smart Farmer Management System

A complete farm management web app built with **HTML, CSS, PHP and JavaScript** (SQLite database — no MySQL needed).

* **Farmers** register, record crops and farm details, manage livestock, harvests, activities, finance, and inventory, update their profile photo, read farm news, get help, and chat with the administrator.
* **Administrator** manages farmers, reviews submitted farm records, answers messages, posts announcements, and exports reports.
* **Works offline** — crop and message changes can queue on the device and sync when connected. Other farm-record forms require a live connection.
* **Works on any network** — same Wi-Fi, mobile data, or anywhere in the world (see step 3).

---

## 1. Run it in VS Code (2 minutes)

1. Install **PHP 8.1 or newer** (https://windows.php.net/download, or install XAMPP) and make sure `php` works in a terminal:
   `php -v`
   > Windows tip: if you see *"PDO SQLite driver is missing"*, open `php.ini` and remove the `;` in front of
   > `extension=pdo_sqlite`, `extension=sqlite3` (and optionally `extension=mbstring`), then restart.
2. Open this folder in VS Code (**File → Open Folder → smart-farmer**).
3. Start the server – pick one:
   * Press **Ctrl+Shift+B** (runs the included task), **or**
   * Open the terminal and run `php -S 0.0.0.0:8000 router.php`, **or**
   * Double-click **start.bat** (Windows) / run `./start.sh` (Mac/Linux).
4. Open **http://localhost:8000**

> ⚠️ Always start with `router.php` — it blocks direct downloads of the database and private code.
> (On Apache/XAMPP the included `.htaccess` files do the same: copy the folder into `htdocs`.)

### Administrator setup and first logins

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@smartfarmer.local` by default, or your `SMARTFARMER_ADMIN_EMAIL` value | Set privately as `SMARTFARMER_ADMIN_PASSWORD` before the first run |
| Farmer | Register using the farmer sign-in portal | Choose your own password |

For a new database, set `SMARTFARMER_ADMIN_EMAIL` (optional) and `SMARTFARMER_ADMIN_PASSWORD` in the server process environment before first start. On Windows PowerShell, you can enter the password without echoing it and run the server in the same terminal:

```powershell
$env:SMARTFARMER_ADMIN_EMAIL = 'admin@smartfarmer.local'
$securePassword = Read-Host 'Choose a new administrator password' -AsSecureString
$env:SMARTFARMER_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $securePassword).Password
php -S 0.0.0.0:8000 router.php
```

Never commit credentials or your `.sqlite` database. For an existing installation, the database's accounts and passwords are preserved. Demo data is disabled by default. To enable it for local testing, set `SEED_DEMO = true` in `app/config.php` and set `SMARTFARMER_DEMO_FARMER_PASSWORD` to a private password in the server environment before first run. This creates a sample farmer, crops, and announcements. To start clean, remove the database only if you intend to delete its records.

## 2. Use it on the same network (Wi-Fi / hotspot)

1. Run the server as above (it listens on all network cards: `0.0.0.0`).
2. Find your computer's IP: `ipconfig` (Windows) or `ifconfig` / `hostname -I`. Example: `192.168.1.20`.
3. On the farmer's phone (same Wi-Fi) open `http://192.168.1.20:8000`.
4. If it does not open, allow PHP through **Windows Defender Firewall** (Private networks) when Windows asks.

## 3. Use it from other networks (different Wi-Fi, mobile data)

Pick **one**:

**A. Quick & free – Cloudflare Tunnel (gives you a secure https link in seconds)**
```
cloudflared tunnel --url http://localhost:8000
```
It prints a link like `https://something.trycloudflare.com` – share that with farmers. (ngrok works the same way: `ngrok http 8000`.) The link works while your computer and the tunnel are running.

**B. Permanent – put it on a web host / VPS** (any host with PHP 8.1+ and SQLite, e.g. cPanel hosting)
1. Upload the application files (keep `.htaccess` files), provide administrator environment variables, and make sure `data/` is writable.
2. Turn on **HTTPS** (free Let's Encrypt in most hosts).
3. Change the admin password immediately and set `SEED_DEMO = false` before going live.

## 4. How offline mode works

* Pages you have opened once are saved on the phone, so they open without internet.
* Crops and messages created offline are stored on the phone and show a ⏳ "waiting to sync" label. When the connection returns they are sent automatically (duplicates are impossible – each item has a unique ID). Farm-profile and other farm-record forms require a live connection.
* **Important:** browsers only allow offline page caching on **https** or **localhost**. On a plain `http://192.168…` address the app still queues new crops/messages while the page is open, but reloading the page offline will not work. Use option 3A/3B (https) for full offline use, then tap *Install* on the Profile page to add it to the home screen.

## 5. Security features

* Passwords hashed with **Argon2id/bcrypt**; strength rules; forced change of the default admin password
* **CSRF tokens** on every form and API call + Origin check
* **SQL-injection safe** (prepared statements everywhere) and **XSS safe** (output escaping + strict Content-Security-Policy, no external scripts)
* **Brute-force protection**: account lock after 5 wrong passwords (15 min), per-IP limit, constant-time checks
* Hardened sessions: HttpOnly, SameSite, Secure on https, ID regenerated at login, 30-minute idle timeout, 8-hour maximum, browser-fingerprint binding
* **Role-based access**: farmers can only ever see their own crops and messages; admin pages are admin-only
* Anti-spam: message rate limit, sign-up limit per IP, hidden honeypot field on the registration form
* Security headers (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on https)
* Private files (database, `app/`) blocked from the web; CSV exports protected against formula injection
* Audit log of logins, password resets, exports and admin actions (visible in *Reports*)
* Saved offline data is cleared from the phone at logout

## 6. Folder guide

```
smart-farmer/
├─ index.php  login.php  register.php  logout.php  change-password.php
├─ farmer/    dashboard · farm profile · crops · livestock · activities · harvest · finance
│             markets · weather log · guides · calendar · inventory · notifications
│             reports · messages · announcements · support · profile
├─ admin/     dashboard · farmers · farm records · farmer · messages · announcements · reports
├─ api/       csrf · messages · sync · crops · avatar   (authenticated JSON/media endpoints)
├─ app/       config.php (settings) · core.php (security) · db.php (database) · services.php · layout.php
├─ assets/    css/style.css · js/app.js, messages.js, crops.js · img/ (icons and farm photos)
├─ data/      smartfarmer.sqlite (created automatically — back this file up!)
├─ sw.js  manifest.json  offline.html   (offline / installable app)
└─ router.php  .htaccess  start.bat  start.sh  .vscode/
```

Farm records are stored in the existing SQLite database and scoped to their farmer account. Weather notes and market prices are manual records: this app does not fetch live forecasts or prices. The agricultural photos in `assets/img/` are local copies from [Unsplash](https://unsplash.com/license): [field photo](https://images.unsplash.com/photo-1500382017468-9049fed747ef), [produce photo](https://images.unsplash.com/photo-1542838132-92c53300491e), and [livestock photo](https://images.unsplash.com/photo-1516467508483-a7212febe31a).

## 7. Settings

`SMARTFARMER_ADMIN_EMAIL` and `SMARTFARMER_ADMIN_PASSWORD` configure the initial administrator in a new database. `SMARTFARMER_DEMO_FARMER_PASSWORD` is required only when demo seeding is enabled. `REQUIRE_APPROVAL`, `SEED_DEMO`, session timeouts, login-lock limits, and timezone are configured in `app/config.php`.

## 8. Before you go live – checklist

- [ ] Change the admin password and use a strong one
- [ ] Set `SEED_DEMO = false` (or delete the demo farmer in *Farmers*)
- [ ] Serve over **HTTPS**
- [ ] Back up `data/smartfarmer.sqlite` regularly (just copy the file)
- [ ] Keep PHP updated
