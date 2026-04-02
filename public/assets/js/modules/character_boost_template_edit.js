(function(){
  const form = document.getElementById('boostTplEditForm');
  const flashBox = document.getElementById('boostTplEditFlash');

  const showFlash = (msg, ok) => {
    if(!flashBox) return;
    flashBox.textContent = msg || (ok ? 'OK' : 'Error');
    flashBox.classList.remove('panel-flash--success','panel-flash--danger');
    flashBox.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    flashBox.style.display = 'block';
  };

  if(!form) return;
  const endpoint = form.dataset.endpoint;
  if(!endpoint) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = new FormData(form);
    if(!data.get('_csrf') && window.__CSRF_TOKEN){
      data.set('_csrf', window.__CSRF_TOKEN);
    }

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        body: data,
        headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
      });
      const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
      showFlash(json.message || (json.success ? 'OK' : 'Failed'), !!json.success);

      if(json && json.success && json.payload && json.payload.id){
        // If created, keep user on the page and update URL.
        const id = String(json.payload.id);
        const url = new URL(window.location.href);
        url.searchParams.set('id', id);
        window.history.replaceState({}, '', url.toString());
      }
    } catch(err){
      showFlash((err && err.message) ? err.message : 'Network error', false);
    }
  });
})();
