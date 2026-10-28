(async () => {
  const base = (document.querySelector('meta[name="sf-base"]') || {}).content || '';
  try {
    Object.keys(localStorage).filter(k => k.startsWith('sf_') && k !== 'sf_outbox').forEach(k => localStorage.removeItem(k));
    sessionStorage.clear();
  } catch { }
  try {
    if ('caches' in window) for (const k of await caches.keys()) if (k.startsWith('sf-pages')) await caches.delete(k);
  } catch { }
  setTimeout(() => location.replace(base + '/login.php'), 900);
})();
