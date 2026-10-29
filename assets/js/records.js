/* Farm record creation queue — entries are saved locally and synced with the server. */
(() => {
  'use strict';
  const { esc, outbox, on } = window.SF;
  const form = document.querySelector('form[data-offline-record]');
  const list = document.getElementById('pending-records');
  if (!form || !list) return;

  const recordType = form.dataset.offlineRecord;
  const fields = ['title', 'details', 'quantity', 'unit', 'amount', 'record_date', 'due_date', 'status'];

  function pending() {
    return outbox.mine().filter(item => item.type === 'farm_record_add' && item.payload.record_type === recordType);
  }

  function render() {
    const records = pending();
    if (!records.length) {
      list.replaceChildren();
      return;
    }
    list.innerHTML = `<section class="card"><div class="card-head"><h2>Saved on this device</h2><span class="chip chip-warn">${records.length} waiting to sync</span></div>
      ${records.slice().reverse().map(item => `<article class="record-row is-pending">
        <div class="row-main"><strong>${esc(item.payload.title)}</strong><small>${esc(item.payload.record_date || item.payload.due_date || 'Date not set')}</small>
          <p class="muted">This record is stored on this device until a connection is available.</p></div>
        <button class="btn btn-sm btn-danger" type="button" data-cancel="${esc(item.uid)}">Cancel</button>
      </article>`).join('')}</section>`;
  }

  form.addEventListener('submit', event => {
    event.preventDefault();
    const payload = { record_type: recordType };
    const formData = new FormData(form);
    for (const field of fields) payload[field] = String(formData.get(field) || '').trim();
    if (!outbox.add('farm_record_add', payload)) return;
    form.reset();
    const date = form.querySelector('[name="record_date"]');
    if (date) date.value = new Date().toISOString().slice(0, 10);
    render();
    window.SF.toast(window.SF.isOnline()
      ? 'Record saved — syncing now.'
      : 'Record saved on this device — it will sync when you are online.', 'success');
  });

  list.addEventListener('click', event => {
    const button = event.target.closest('[data-cancel]');
    if (!button) return;
    outbox.remove([button.dataset.cancel]);
    render();
  });

  on('sf:outbox', render);
  on('sf:synced', detail => {
    if ((detail.results || []).some(result => result.type === 'farm_record_add' && result.ok)) location.reload();
  });
  render();
})();
