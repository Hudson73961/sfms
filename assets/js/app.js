/* Smart Farmer — shared front-end: offline outbox, sync, status bar, service worker */
(() => {
  'use strict';
  const meta = n => (document.querySelector(`meta[name="${n}"]`) || {}).content || '';
  const BASE = meta('sf-base'), UID = meta('sf-uid'), ROLE = meta('sf-role');
  const url = p => BASE + '/' + String(p).replace(/^\//, '');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const emit = (n, d) => document.dispatchEvent(new CustomEvent(n, { detail: d }));
  const on = (n, fn) => document.addEventListener(n, e => fn(e.detail));
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch { return false; } },
    del(k) { try { localStorage.removeItem(k); } catch { } }
  };
  const uid = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
    : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);

  /* ---------- toasts ---------- */
  function toast(msg, type = 'info', ms = 3800) {
    const box = document.getElementById('toasts'); if (!box) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type; t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 300); }, ms);
  }

  /* ---------- API ---------- */
  async function api(path, opts = {}) {
    const r = await fetch(url(path), { credentials: 'same-origin', headers: { Accept: 'application/json' }, ...opts });
    if (!r.ok) { const e = new Error('http ' + r.status); e.status = r.status; throw e; }
    return r.json();
  }

  /* ---------- online / offline status ---------- */
  let online = navigator.onLine;
  const bar = () => document.getElementById('netbar');
  function paintNet() {
    const b = bar(); if (!b) return;
    const n = outbox.mine().length;
    if (!online) {
      b.className = 'netbar show off';
      b.textContent = '📡 You are offline. Keep working — everything is saved on this phone and will sync automatically.';
    } else if (n > 0) {
      b.className = 'netbar show sync'; b.textContent = '🔄 Back online — syncing your saved changes…';
    } else { b.className = 'netbar'; b.textContent = ''; }
    const chip = document.getElementById('pending-chip');
    if (chip) { chip.hidden = n === 0; chip.textContent = '⏳ ' + n + ' waiting to sync'; }
    const st = document.getElementById('sync-status');
    if (st) st.textContent = (online ? 'Online' : 'Offline') + (n ? ` · ${n} change${n > 1 ? 's' : ''} waiting to sync` : ' · everything is saved');
  }
  function setOnline(v) {
    if (v === online) return;
    online = v; paintNet(); emit('sf:net', { online });
    if (v) outbox.flush();
  }

  /* ---------- offline outbox (saved in this browser until the server confirms) ---------- */
  const OKEY = 'sf_outbox';
  const outbox = {
    flushing: false,
    all() { return store.get(OKEY, []); },
    mine() { return this.all().filter(i => i.owner === UID); },
    add(type, payload) {
      const item = { uid: uid(), type, payload, owner: UID, ts: Date.now() };
      const a = this.all(); a.push(item);
      if (!store.set(OKEY, a)) {
        toast('Could not save this change on the device. Free storage space and try again.', 'error', 7000);
        return null;
      }
      emit('sf:outbox'); paintNet(); this.flush();
      return item;
    },
    remove(uids) { store.set(OKEY, this.all().filter(i => !uids.includes(i.uid))); emit('sf:outbox'); paintNet(); },
    async flush() {
      if (this.flushing || !UID || !navigator.onLine) return;
      const items = this.mine(); if (!items.length) return;
      this.flushing = true;
      try {
        const t = await api('api/csrf.php');
        const r = await fetch(url('api/sync.php'), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': t.csrf, Accept: 'application/json' },
          body: JSON.stringify({ items: items.map(({ uid, type, payload }) => ({ uid, type, payload })) })
        });
        if (!r.ok) throw Object.assign(new Error('http'), { status: r.status });
        const data = await r.json();
        const results = Array.isArray(data.results) ? data.results : [];
        const done = results.filter(x => x.ok).map(x => x.uid);
        this.remove(done);
        results.filter(x => !x.ok).forEach(x => toast('⚠️ ' + (x.error || 'Could not save one item. The item remains queued.'), 'error', 6000));
        setOnline(true);
        emit('sf:synced', { results });
        if (results.some(x => x.ok) && !this.mine().length) toast('✅ All changes saved', 'success');
      } catch (e) {
        if (e.status === 401 || e.status === 419) toast('Please sign in again to save your offline changes.', 'error', 7000);
        else if (!e.status) setOnline(false);
      } finally { this.flushing = false; paintNet(); }
    }
  };

  /* ---------- time helpers ---------- */
  const fmtTime = iso => {
    const d = new Date(iso), now = new Date();
    const t = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return d.toDateString() === now.toDateString() ? t : d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ', ' + t;
  };
  const dayLabel = iso => {
    const d = new Date(iso), now = new Date(), y = new Date(Date.now() - 864e5);
    if (d.toDateString() === now.toDateString()) return 'Today';
    if (d.toDateString() === y.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'long' });
  };

  window.SF = { BASE, UID, ROLE, url, esc, store, uid, toast, api, outbox, on, emit, fmtTime, dayLabel, setOnline, isOnline: () => online };

  /* ---------- unread badge polling ---------- */
  async function pollUnread() {
    if (!UID || document.hidden) return;
    try {
      const d = await api('api/messages.php?action=unread');
      setOnline(true);
      document.querySelectorAll('[data-unread]').forEach(b => { b.textContent = d.unread; b.hidden = !d.unread; });
      emit('sf:unread', d.unread);
    } catch (e) {
      if (e.status === 401) { location.href = url('login.php'); return; }
      if (!e.status) setOnline(false);
    }
  }

  /* ---------- service worker (offline pages) ---------- */
  function registerSW() {
    const ok = 'serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1', '[::1]'].includes(location.hostname));
    if (!ok) return;
    navigator.serviceWorker.register(url('sw.js')).then(() => navigator.serviceWorker.ready).then(reg => {
      const list = meta('sf-prefetch');
      const key = 'sf_prefetched_' + UID;
      if (UID && list && navigator.onLine && !sessionStorage.getItem(key) && reg.active) {
        reg.active.postMessage({ type: 'prefetch', urls: list.split(',').map(url) });
        try { sessionStorage.setItem(key, '1'); } catch { }
      }
    }).catch(() => { });
  }

  /* ---------- small UI helpers ---------- */
  document.addEventListener('click', e => {
    const tg = e.target.closest('[data-toggle-password]');
    if (tg) { const i = document.getElementById(tg.dataset.togglePassword); if (i) i.type = i.type === 'password' ? 'text' : 'password'; }
    const c = e.target.closest('button[data-confirm]');
    if (c && !confirm(c.dataset.confirm)) e.preventDefault();
  });
  document.addEventListener('submit', e => {
    const m = e.target.dataset && e.target.dataset.confirm;
    if (m && !confirm(m)) e.preventDefault();
  });
  let deferredInstall = null;
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault(); deferredInstall = e;
    const row = document.getElementById('install-row'); if (row) row.hidden = false;
  });
  document.addEventListener('click', e => {
    if (e.target.id === 'install-btn' && deferredInstall) { deferredInstall.prompt(); deferredInstall = null; }
  });

  window.addEventListener('online', () => { online = true; setOnline(false); setOnline(true); });
  window.addEventListener('offline', () => setOnline(false));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { pollUnread(); outbox.flush(); } });

  paintNet();
  registerSW();
  if (UID) {
    outbox.flush();
    setInterval(() => outbox.flush(), 20000);
    setInterval(pollUnread, 15000);
    setTimeout(pollUnread, 1500);
  }
})();
