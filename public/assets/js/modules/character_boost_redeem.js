(function(){
  const form = document.getElementById('boostRedeemForm');
  const flashBox = document.getElementById('boostRedeemFlash');
  const realmSelect = document.getElementById('boostRedeemRealm');
  const templateSelect = document.getElementById('boostRedeemTemplate');

  const showFlash = (msg, ok) => {
    if(!flashBox) return;
    flashBox.textContent = msg || (ok ? 'OK' : 'Error');
    flashBox.classList.remove('panel-flash--success','panel-flash--danger');
    flashBox.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    flashBox.style.display = 'block';
  };

  if(!form || !realmSelect || !templateSelect) return;

  const optionsEndpoint = form.dataset.optionsEndpoint;
  const redeemEndpoint = form.dataset.redeemEndpoint;
  if(!optionsEndpoint || !redeemEndpoint) return;

  let templates = [];

  const renderTemplatesForRealm = (realmId) => {
    const rid = String(realmId || '');
    const list = templates.filter(t => String(t.realm_id) === rid);

    templateSelect.innerHTML = '';
    if(!list.length){
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'No templates';
      templateSelect.appendChild(opt);
      templateSelect.disabled = true;
      return;
    }

    list.forEach(t => {
      const opt = document.createElement('option');
      opt.value = String(t.id);
      opt.textContent = t.name + (t.target_level ? (' (Lv.' + t.target_level + ')') : '');
      templateSelect.appendChild(opt);
    });

    templateSelect.disabled = false;
  };

  const loadOptions = async () => {
    try {
      const res = await fetch(optionsEndpoint, { method: 'GET' });
      const json = await res.json();
      if(!json || !json.success){
        showFlash((json && json.message) ? json.message : 'Failed to load options', false);
        return;
      }

      const realms = Array.isArray(json.realms) ? json.realms : [];
      templates = Array.isArray(json.templates) ? json.templates : [];

      realmSelect.innerHTML = '';
      realms.forEach(r => {
        const opt = document.createElement('option');
        opt.value = String(r.realm_id);
        opt.textContent = r.label || ('Realm ' + r.realm_id);
        realmSelect.appendChild(opt);
      });

      const firstRealm = realmSelect.value || (realms[0] ? String(realms[0].realm_id) : '');
      if(firstRealm){
        realmSelect.value = firstRealm;
        renderTemplatesForRealm(firstRealm);
      }
    } catch(err){
      showFlash((err && err.message) ? err.message : 'Network error', false);
    }
  };

  realmSelect.addEventListener('change', () => {
    renderTemplatesForRealm(realmSelect.value);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = new FormData(form);
    if(!data.get('_csrf') && window.__CSRF_TOKEN){
      data.set('_csrf', window.__CSRF_TOKEN);
    }

    // Server decides template by redeem code; template_id is for display only.
    data.delete('template_id');

    try {
      const res = await fetch(redeemEndpoint, {
        method: 'POST',
        body: data,
        headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
      });
      const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
      showFlash(json.message || (json.success ? 'OK' : 'Failed'), !!json.success);
    } catch(err){
      showFlash((err && err.message) ? err.message : 'Network error', false);
    }
  });

  loadOptions();
})();
