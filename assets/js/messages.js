/* Chat between farmer and administrator — works offline (messages queue and sync later) */
(() => {
  'use strict';
  const SF = window.SF, { esc, api, store, outbox, ROLE, UID } = SF;
  const root = document.getElementById('chat'); if (!root) return;
  const $ = id => document.getElementById(id);
  const isAdmin = ROLE === 'admin';
  const scroller = $('chat-scroll'), form = $('chat-form'), input = $('chat-input');
  let farmerId = isAdmin ? Number(new URLSearchParams(location.search).get('farmer') || 0) : Number(UID);
  let msgs = [], lastId = 0, lastRead = 0, peer = null, convos = [];
  const cacheKey = id => `sf_msgs_${UID}_${id}`;

  const pending = () => outbox.mine().filter(i => i.type === 'message' && Number(i.payload.farmer_id) === farmerId);
  const body = t => esc(t).replace(/\n/g, '<br>');

  function render(stick) {
    if (!scroller) return;
    const near = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 140;
    const all = msgs.concat(pending().map(i => ({ id: null, sender_role: ROLE, body: i.payload.body, created_at: new Date(i.ts).toISOString(), pending: true })));
    if (!farmerId) { scroller.innerHTML = '<div class="chat-empty"><div class="big-emoji">💬</div><p>Choose a farmer on the left to start chatting.</p></div>'; return; }
    if (!all.length) {
      scroller.innerHTML = `<div class="chat-empty"><div class="big-emoji">👋</div><p>${isAdmin ? 'No messages yet. Say hello!' : 'Say hello to the farm office. Ask anything about your crops.'}</p></div>`;
      return;
    }
    let html = '', day = '';
    for (const m of all) {
      const d = SF.dayLabel(m.created_at);
      if (d !== day) { day = d; html += `<div class="day-sep"><span>${esc(d)}</span></div>`; }
      const mine = m.sender_role === ROLE;
      const tick = !mine ? '' : (m.pending ? '<span class="tick" title="Waiting to send">🕒</span>' : `<span class="tick${m.id <= lastRead ? ' seen' : ''}" title="${m.id <= lastRead ? 'Seen' : 'Sent'}">${m.id <= lastRead ? '✓✓' : '✓'}</span>`);
      html += `<div class="bubble ${mine ? 'mine' : 'theirs'}${m.pending ? ' pending' : ''}"><div class="b-text">${body(m.body)}</div><div class="b-meta">${esc(SF.fmtTime(m.created_at))} ${tick}</div></div>`;
    }
    scroller.innerHTML = html;
    if (stick || near) scroller.scrollTop = scroller.scrollHeight;
  }

  function merge(list) {
    let added = false;
    for (const m of list) if (!msgs.some(x => x.id === m.id)) { msgs.push(m); added = true; }
    if (added) msgs.sort((a, b) => a.id - b.id);
    return added;
  }
  const saveCache = () => store.set(cacheKey(farmerId), { msgs: msgs.slice(-100), lastRead, peer });

  async function load(initial) {
    if (!farmerId) return;
    const myFarmer = farmerId;
    try {
      const d = await api(`api/messages.php?action=list&farmer_id=${farmerId}&after=${initial ? 0 : lastId}&view=${document.hidden ? 0 : 1}`);
      if (myFarmer !== farmerId) return;                      // user switched conversation meanwhile
      SF.setOnline(true);
      let changed = false;
      if (initial) { msgs = d.messages; changed = true; } else changed = merge(d.messages);
      if (d.messages.length) lastId = Math.max(lastId, ...d.messages.map(m => m.id));
      if (d.last_read_id !== lastRead) { lastRead = d.last_read_id; changed = true; }
      if (d.peer) { peer = d.peer; paintPeer(); }
      saveCache();
      if (changed) render(initial || d.messages.length > 0);
      if (d.messages.some(m => m.sender_role !== ROLE) && isAdmin) loadConvos();
    } catch (e) {
      if (e.status === 401) location.href = SF.url('login.php');
      else if (!e.status) SF.setOnline(false);
    }
  }

  /* ---------- administrator: conversation list ---------- */
  const convList = $('conv-list'), search = $('conv-search');
  function paintConvos() {
    if (!convList) return;
    const q = (search && search.value || '').trim().toLowerCase();
    const items = convos.filter(c => !q || c.name.toLowerCase().includes(q) || (c.location || '').toLowerCase().includes(q));
    if (!items.length) { convList.innerHTML = '<p class="muted pad">No farmers found.</p>'; return; }
    convList.innerHTML = items.map(c => `
      <button class="conv${c.id === farmerId ? ' active' : ''}" data-id="${c.id}" type="button">
        <span class="avatar">${esc(c.name.split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase())}</span>
        <span class="conv-main"><strong>${esc(c.name)}</strong><small>${esc(c.last || c.location || 'No messages yet')}</small></span>
        <span class="conv-side">${c.last_at ? `<small>${esc(SF.fmtTime(c.last_at))}</small>` : ''}${c.unread ? `<b class="nav-badge">${c.unread}</b>` : ''}</span>
      </button>`).join('');
  }
  async function loadConvos() {
    if (!isAdmin) return;
    try { const d = await api('api/messages.php?action=conversations'); convos = d.conversations; store.set('sf_convos_' + UID, convos); paintConvos(); }
    catch { /* offline: keep what we have */ }
  }
  function paintPeer() {
    const n = $('chat-peer-name'), s = $('chat-peer-sub'), av = $('chat-peer-av');
    if (!isAdmin || !peer) return;
    n.textContent = peer.name;
    s.innerHTML = [peer.location ? esc(peer.location) : '', peer.phone ? `<a href="tel:${esc(peer.phone)}">📞 ${esc(peer.phone)}</a>` : ''].filter(Boolean).join(' · ');
    av.textContent = peer.name.split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
  }
  function select(id) {
    farmerId = id; lastId = 0; lastRead = 0; peer = null;
    const c = store.get(cacheKey(id), null);
    msgs = c ? c.msgs : []; lastRead = c ? c.lastRead : 0; peer = c ? c.peer : null;
    const cv = convos.find(x => x.id === id);
    if (!peer && cv) peer = { id, name: cv.name, phone: cv.phone, location: cv.location };
    root.classList.add('is-chat'); $('chat-main').classList.add('is-open');
    history.replaceState(null, '', `?farmer=${id}`);
    paintPeer(); paintConvos(); render(true); load(true);
    if (cv) { cv.unread = 0; paintConvos(); }
    input && input.focus();
  }
  if (isAdmin) {
    convos = store.get('sf_convos_' + UID, []); paintConvos();
    convList.addEventListener('click', e => { const b = e.target.closest('.conv'); if (b) select(Number(b.dataset.id)); });
    search && search.addEventListener('input', paintConvos);
    $('back-btn') && $('back-btn').addEventListener('click', () => { root.classList.remove('is-chat'); history.replaceState(null, '', location.pathname); farmerId = 0; paintConvos(); render(); });
    loadConvos().then(() => { if (farmerId) select(farmerId); });
    setInterval(() => { if (!document.hidden) loadConvos(); }, 10000);
  } else {
    const c = store.get(cacheKey(farmerId), null);
    if (c) { msgs = c.msgs; lastRead = c.lastRead; lastId = msgs.reduce((a, m) => Math.max(a, m.id), 0); }
    render(true); load(true);
  }

  /* ---------- sending ---------- */
  const autosize = () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; };
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && matchMedia('(pointer:fine)').matches) { e.preventDefault(); form.requestSubmit(); }
  });
  form.addEventListener('submit', e => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text || !farmerId) return;
    if (!outbox.add('message', { farmer_id: farmerId, body: text })) return;
    input.value = ''; autosize(); render(true); input.focus();
  });

  SF.on('sf:outbox', () => render());
  SF.on('sf:synced', d => {
    let changed = false;
    for (const r of d.results) if (r.ok && r.type === 'message' && r.data && r.data.farmer_id === farmerId) changed = merge([r.data]) || changed;
    if (changed) { saveCache(); }
    render(true); load(false);
    if (isAdmin) loadConvos();
  });
  setInterval(() => { if (!document.hidden && SF.isOnline()) load(false); }, 4000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) load(false); });
  window.addEventListener('online', () => load(false));
})();
