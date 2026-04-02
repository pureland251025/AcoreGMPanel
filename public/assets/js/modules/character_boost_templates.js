(function(){
  const table = document.getElementById('boostTplTable');
  const flashBox = document.getElementById('boostTplFlash');

  const showFlash = (msg, ok) => {
    if(!flashBox) return;
    flashBox.textContent = msg || (ok ? 'OK' : 'Error');
    flashBox.classList.remove('panel-flash--success','panel-flash--danger');
    flashBox.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    flashBox.style.display = 'block';
  };

  if(!table) return;
  const deleteEndpoint = table.dataset.deleteEndpoint;
  if(!deleteEndpoint) return;

  table.querySelectorAll('.js-boost-tpl-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if(!id) return;
      if(!confirm('Delete template #' + id + '?')) return;

      const data = new FormData();
      data.set('id', String(id));
      if(window.__CSRF_TOKEN){
        data.set('_csrf', window.__CSRF_TOKEN);
      }

      try {
        const res = await fetch(deleteEndpoint, {
          method: 'POST',
          body: data,
          headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
        });
        const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));

        if(json && json.success){
          const row = table.querySelector('tr[data-id="' + id + '"]');
          if(row) row.remove();
        }

        showFlash(json.message || (json.success ? 'OK' : 'Failed'), !!json.success);
      } catch(err){
        showFlash((err && err.message) ? err.message : 'Network error', false);
      }
    });
  });
})();
