/* Farmer crop records — add/update/delete work offline and sync later */
(() => {
  'use strict';
  const SF = window.SF, { esc, api, store, outbox, UID } = SF;
  const $ = id => document.getElementById(id);
  const listEl = $('crop-list'); if (!listEl) return;
  const card = $('crop-card'), form = $('crop-form');
  const KEY = 'sf_crops_' + UID;
  let base = store.get(KEY, null), filter = 'all';

  const EMOJI = { maize: '🌽', beans: '🫘', tomatoes: '🍅', potatoes: '🥔', kale: '🥬', cabbage: '🥬', onions: '🧅', carrots: '🥕', wheat: '🌾', rice: '🌾', sorghum: '🌾', bananas: '🍌', avocado: '🥑', mango: '🥭', coffee: '☕', tea: '🍃', sugarcane: '🎋' };
  const STAGE = { planted: ['🌱', 'Planted'], growing: ['🌿', 'Growing'], harvested: ['🌾', 'Harvested'] };
  const emoji = n => EMOJI[String(n || '').toLowerCase().split(' ')[0]] || '🌱';
  const fmtD = d => d ? new Date(d + 'T00:00:00').toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
  const num = v => v === null || v === undefined ? null : Number(v).toLocaleString([], { maximumFractionDigits: 2 });

  function view() {
    let list = (base || []).map(c => ({ ...c }));
    const mine = outbox.mine();
    for (const it of mine) {
      if (it.type === 'crop_status') { const c = list.find(x => x.id === it.payload.id); if (c) c.status = it.payload.status; }
      if (it.type === 'crop_delete') list = list.filter(x => x.id !== it.payload.id);
    }
    const added = mine.filter(i => i.type === 'crop_add').map(i => ({ ...i.payload, id: null, uid: i.uid, pending: true }));
    return added.reverse().concat(list);
  }
  function progress(c) {
    if (c.status === 'harvested') return 100;
    if (!c.planting_date || !c.harvest_date) return null;
    const s = Date.parse(c.planting_date), e = Date.parse(c.harvest_date);
    if (!(e > s)) return null;
    return Math.max(0, Math.min(100, Math.round((Date.now() - s) / (e - s) * 100)));
  }
  function cardHtml(c) {
    const st = STAGE[c.status] || STAGE.planted, p = progress(c);
    const sub = [c.field_name, c.area_acres != null && c.area_acres !== '' ? num(c.area_acres) + ' acres' : ''].filter(Boolean).map(esc).join(' · ');
    const next = c.status === 'planted' ? ['growing', '🌿 Mark growing'] : c.status === 'growing' ? ['harvested', '🌾 Mark harvested'] : ['growing', '↩ Reopen'];
    const actions = c.pending
      ? `<button class="btn btn-sm btn-ghost" data-act="cancel" data-uid="${esc(c.uid)}" type="button">✖ Cancel</button>`
      : `<button class="btn btn-sm btn-outline" data-act="status" data-id="${c.id}" data-status="${next[0]}" type="button">${next[1]}</button>
         <button class="btn btn-sm btn-danger" data-act="delete" data-id="${c.id}" data-name="${esc(c.crop_name)}" type="button" aria-label="Delete ${esc(c.crop_name)}">🗑</button>`;
    return `<article class="crop-card st-${esc(c.status)}${c.pending ? ' is-pending' : ''}">
      <div class="crop-top"><div class="crop-ico">${emoji(c.crop_name)}</div>
        <div class="crop-title"><h3>${esc(c.crop_name)}</h3><small>${sub || 'No field details'}</small></div>
        <span class="chip stage">${st[0]} ${st[1]}</span></div>
      ${c.pending ? '<div class="chip chip-warn">⏳ Waiting to sync</div>' : ''}
      ${p !== null ? `<div class="progress" role="progressbar" aria-valuenow="${p}" aria-valuemin="0" aria-valuemax="100"><i style="width:${p}%"></i></div>
        <div class="prog-lbl"><span>Planted ${esc(fmtD(c.planting_date))}</span><span>Harvest ${esc(fmtD(c.harvest_date))}</span></div>`
        : `<div class="prog-lbl"><span>Planted ${esc(fmtD(c.planting_date))}</span><span>Harvest ${esc(fmtD(c.harvest_date))}</span></div>`}
      ${c.expected_yield_kg != null && c.expected_yield_kg !== '' ? `<div class="fact">⚖️ Expected yield: <b>${esc(num(c.expected_yield_kg))} kg</b></div>` : ''}
      ${c.notes ? `<p class="crop-notes">📝 ${esc(c.notes)}</p>` : ''}
      <div class="crop-actions">${actions}</div></article>`;
  }
  function render() {
    if (base === null && !outbox.mine().length) return;
    const all = view();
    const list = filter === 'all' ? all : all.filter(c => c.status === filter);
    if (!list.length) {
      listEl.innerHTML = `<div class="card empty span-all"><div class="big-emoji">🌱</div><p>${all.length ? 'No crops in this stage.' : 'You have not added any crops yet.'}</p>${all.length ? '' : '<button class="btn btn-lg" data-act="open-form" type="button">➕ Add my first crop</button>'}</div>`;
      return;
    }
    listEl.innerHTML = list.map(cardHtml).join('');
  }
  async function refresh() {
    try { const d = await api('api/crops.php'); base = d.crops; store.set(KEY, base); SF.setOnline(true); }
    catch (e) { if (e.status === 401) { location.href = SF.url('login.php'); return; } if (!e.status) SF.setOnline(false); if (base === null) base = []; }
    render();
  }

  /* ---------- form ---------- */
  const openForm = () => { card.hidden = false; $('planting_date').value = $('planting_date').value || new Date().toISOString().slice(0, 10); card.scrollIntoView({ behavior: 'smooth', block: 'start' }); $('crop_select').focus(); };
  const closeForm = () => { card.hidden = true; };
  $('toggle-form').addEventListener('click', () => card.hidden ? openForm() : closeForm());
  $('cancel-form').addEventListener('click', closeForm);
  $('crop_select').addEventListener('change', e => { $('other-wrap').hidden = e.target.value !== 'Other'; if (e.target.value === 'Other') $('crop_other').focus(); });
  form.addEventListener('submit', e => {
    e.preventDefault();
    const sel = $('crop_select').value;
    const name = (sel === 'Other' ? $('crop_other').value : sel).trim();
    if (!name) { SF.toast('Please type the crop name.', 'error'); return; }
    const plant = $('planting_date').value, harv = $('harvest_date').value;
    if (plant && harv && harv < plant) { SF.toast('Harvest date must be after the planting date.', 'error'); return; }
    outbox.add('crop_add', {
      crop_name: name, field_name: $('field_name').value.trim(), area_acres: $('area_acres').value,
      planting_date: plant, harvest_date: harv, expected_yield_kg: $('expected_yield_kg').value,
      status: $('status').value, notes: $('notes').value.trim()
    });
    form.reset(); $('other-wrap').hidden = true; closeForm();
    SF.toast(SF.isOnline() ? '🌱 Crop saved' : '🌱 Saved on this phone — will sync when online', 'success');
    render();
  });

  /* ---------- list actions ---------- */
  listEl.addEventListener('click', e => {
    const b = e.target.closest('[data-act]'); if (!b) return;
    const act = b.dataset.act;
    if (act === 'open-form') return openForm();
    if (act === 'status') outbox.add('crop_status', { id: Number(b.dataset.id), status: b.dataset.status });
    if (act === 'delete') { if (!confirm(`Delete ${b.dataset.name}? This cannot be undone.`)) return; outbox.add('crop_delete', { id: Number(b.dataset.id) }); }
    if (act === 'cancel') outbox.remove([b.dataset.uid]);
    render();
  });
  $('crop-filters').addEventListener('click', e => {
    const b = e.target.closest('[data-filter]'); if (!b) return;
    filter = b.dataset.filter;
    document.querySelectorAll('#crop-filters .pill-btn').forEach(x => x.classList.toggle('active', x === b));
    render();
  });

  SF.on('sf:outbox', render);
  SF.on('sf:synced', refresh);
  window.addEventListener('online', refresh);
  render();
  refresh();
})();
