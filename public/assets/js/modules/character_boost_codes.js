(function(){
  const form = document.getElementById('boostCodesForm');
  const flashBox = document.getElementById('boostCodesFlash');
  const output = document.getElementById('boostCodesOutput');

  const manageForm = document.getElementById('boostCodesManageForm');
  const manageFlash = document.getElementById('boostCodesManageFlash');
  const manageTbody = document.getElementById('boostCodesManageTbody');
  const statTotal = document.getElementById('boostCodesStatTotal');
  const statUnused = document.getElementById('boostCodesStatUnused');
  const statUsed = document.getElementById('boostCodesStatUsed');
  const btnRefresh = document.getElementById('boostCodesManageRefresh');
  const btnPurgeUnused = document.getElementById('boostCodesManagePurgeUnused');
  const selectTemplate = document.getElementById('boostCodesManageTemplate');
  const chkUnusedOnly = document.getElementById('boostCodesManageUnusedOnly');
  const btnPrev = document.getElementById('boostCodesPagePrev');
  const btnNext = document.getElementById('boostCodesPageNext');
  const pageInfo = document.getElementById('boostCodesPageInfo');

  const sortIdLink = document.getElementById('boostCodesSortId');
  const sortIdIcon = document.getElementById('boostCodesSortIdIcon');

  let managePage = 1;
  const perPage = 50;

  const manageSort = 'id';
  let manageDir = 'desc';

  const updateSortUi = () => {
    if(!sortIdIcon) return;
    sortIdIcon.textContent = (manageDir === 'asc') ? '▲' : '▼';
  };

  const showFlash = (msg, ok) => {
    if(!flashBox) return;
    flashBox.textContent = msg || (ok ? 'OK' : 'Error');
    flashBox.classList.remove('panel-flash--success','panel-flash--danger');
    flashBox.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    flashBox.style.display = 'block';
  };

  const showManageFlash = (msg, ok) => {
    if(!manageFlash) return;
    manageFlash.textContent = msg || (ok ? 'OK' : 'Error');
    manageFlash.classList.remove('panel-flash--success','panel-flash--danger');
    manageFlash.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    manageFlash.style.display = 'block';
  };

  const csrfFrom = (fallbackForm) => {
    try {
      const el = (fallbackForm || document).querySelector('input[name="_csrf"]');
      if(el && el.value) return el.value;
    } catch(e){}
    return window.__CSRF_TOKEN || '';
  };

  const postJson = async (url, data, csrfToken) => {
    const res = await fetch(url, {
      method: 'POST',
      body: data,
      headers: {
        'X-CSRF-TOKEN': csrfToken || '',
        'Accept': 'application/json'
      }
    });
    return await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
  };

  const currentTemplateId = () => {
    if(!selectTemplate) return 'all';
    return String(selectTemplate.value || 'all');
  };

  const isUnusedOnly = () => {
    return !!(chkUnusedOnly && chkUnusedOnly.checked);
  };

  const setStats = (stats) => {
    if(statTotal) statTotal.textContent = stats ? String(stats.total ?? '-') : '-';
    if(statUnused) statUnused.textContent = stats ? String(stats.unused ?? '-') : '-';
    if(statUsed) statUsed.textContent = stats ? String(stats.used ?? '-') : '-';
  };

  const renderRows = (items) => {
    if(!manageTbody) return;

    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c));

    if(!Array.isArray(items) || items.length === 0){
      manageTbody.innerHTML = '<tr><td colspan="7" style="text-align:center;opacity:.75">(empty)</td></tr>';
      return;
    }

    manageTbody.innerHTML = items.map(row => {
      const used = !!row.used_at;
      const status = used ? 'USED' : 'UNUSED';
      const usedBy = used
        ? (esc(row.used_character_name || '') + (row.used_realm_id ? (' (realm ' + esc(row.used_realm_id) + ')') : '') + (row.used_ip ? (' / ' + esc(row.used_ip)) : ''))
        : '-';
      const act = used
        ? '<span style="opacity:.6">-</span>'
        : '<button class="btn btn-sm btn-danger js-del-unused" data-id="' + esc(row.id) + '">Delete</button>';

      return (
        '<tr>'
        + '<td>' + esc(row.id) + '</td>'
        + '<td>' + esc(row.template_name || ('#' + row.template_id)) + '</td>'
        + '<td style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \'Liberation Mono\', \'Courier New\', monospace">' + esc(row.code) + '</td>'
        + '<td>' + status + '</td>'
        + '<td>' + usedBy + '</td>'
        + '<td>' + esc(row.created_at || '-') + '</td>'
        + '<td>' + act + '</td>'
        + '</tr>'
      );
    }).join('');
  };

  const updatePager = (list) => {
    if(!pageInfo) return;
    const page = list ? (parseInt(list.page, 10) || 1) : 1;
    const pages = list ? (parseInt(list.pages, 10) || 1) : 1;
    const total = list ? (parseInt(list.total, 10) || 0) : 0;
    pageInfo.textContent = 'Page ' + page + ' / ' + pages + ' · ' + total;
    if(btnPrev) btnPrev.disabled = page <= 1;
    if(btnNext) btnNext.disabled = page >= pages;
  };

  const refreshManage = async () => {
    if(!manageForm) return;
    const csrfToken = csrfFrom(manageForm);
    const tpl = currentTemplateId();
    const unusedOnly = isUnusedOnly();

    updateSortUi();

    // Stats
    try {
      const d1 = new FormData();
      d1.set('_csrf', csrfToken);
      d1.set('template_id', tpl);
      const statsJson = await postJson(manageForm.dataset.endpointStats, d1, csrfToken);
      if(statsJson && statsJson.success && statsJson.payload && statsJson.payload.stats){
        setStats(statsJson.payload.stats);
      } else {
        setStats(null);
      }
    } catch(e){
      setStats(null);
    }

    // List
    manageTbody.innerHTML = '<tr><td colspan="7" style="text-align:center;opacity:.75">Loading…</td></tr>';
    const d2 = new FormData();
    d2.set('_csrf', csrfToken);
    d2.set('template_id', tpl);
    d2.set('unused_only', unusedOnly ? '1' : '0');
    d2.set('page', String(managePage));
    d2.set('per_page', String(perPage));
    d2.set('sort', manageSort);
    d2.set('dir', manageDir);

    const listJson = await postJson(manageForm.dataset.endpointList, d2, csrfToken);
    if(!(listJson && listJson.success && listJson.payload && listJson.payload.list)){
      renderRows([]);
      updatePager(null);
      showManageFlash((listJson && listJson.message) ? listJson.message : 'Failed', false);
      return;
    }

    const list = listJson.payload.list;
    renderRows(list.items || []);
    updatePager(list);
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

      const ct = (res.headers.get('Content-Type') || '').toLowerCase();
      const isText = ct.includes('text/plain');

      if(isText){
        const blob = await res.blob();
        const text = await blob.text();

        if(output){
          output.value = text || '';
        }

        // Trigger download
        const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
        const a = document.createElement('a');
        a.href = url;

        const disp = res.headers.get('Content-Disposition') || '';
        const m = disp.match(/filename="?([^";]+)"?/i);
        a.download = m ? m[1] : 'boost-redeem-codes.txt';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        const cnt = res.headers.get('X-Generated-Count');
        showFlash(cnt ? ('OK (' + cnt + ')') : 'OK', true);
        return;
      }

      const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));

      if(output){
        output.value = '';
        if(json && json.success && json.payload && Array.isArray(json.payload.generated)){
          const blocks = [];
          json.payload.generated.forEach(g => {
            const header = (g.template_name ? (g.template_name + ' (#' + g.template_id + ')') : ('Template #' + g.template_id));
            blocks.push('[' + header + ']');
            if(Array.isArray(g.codes)){
              g.codes.forEach(c => blocks.push(String(c)));
            }
            blocks.push('');
          });
          output.value = blocks.join('\n');
        }
      }

      showFlash((json && json.message) ? json.message : (json && json.success ? 'OK' : 'Failed'), !!(json && json.success));

      if(json && json.success){
        // After generating new codes, refresh management table & stats.
        if(manageForm){
          managePage = 1;
          refreshManage().catch(()=>{});
        }
      }
    } catch(err){
      showFlash((err && err.message) ? err.message : 'Network error', false);
    }
  });

  if(manageForm){
    const onChange = () => { managePage = 1; refreshManage().catch(()=>{}); };
    if(selectTemplate) selectTemplate.addEventListener('change', onChange);
    if(chkUnusedOnly) chkUnusedOnly.addEventListener('change', onChange);
    if(btnRefresh) btnRefresh.addEventListener('click', () => { refreshManage().catch(()=>{}); });

    if(sortIdLink){
      sortIdLink.addEventListener('click', (e) => {
        e.preventDefault();
        manageDir = (manageDir === 'asc') ? 'desc' : 'asc';
        managePage = 1;
        refreshManage().catch(()=>{});
      });
    }

    if(btnPrev) btnPrev.addEventListener('click', () => {
      if(managePage > 1){ managePage--; refreshManage().catch(()=>{}); }
    });
    if(btnNext) btnNext.addEventListener('click', () => {
      managePage++; refreshManage().catch(()=>{});
    });

    if(btnPurgeUnused){
      btnPurgeUnused.addEventListener('click', async () => {
        if(!confirm('Delete ALL unused redeem codes?')) return;
        const csrfToken = csrfFrom(manageForm);
        const d = new FormData();
        d.set('_csrf', csrfToken);
        d.set('template_id', currentTemplateId());
        const json = await postJson(manageForm.dataset.endpointPurgeUnused, d, csrfToken);
        showManageFlash((json && json.message) ? json.message : (json && json.success ? 'OK' : 'Failed'), !!(json && json.success));
        managePage = 1;
        refreshManage().catch(()=>{});
      });
    }

    manageTbody && manageTbody.addEventListener('click', async (e) => {
      const btn = e.target && e.target.closest ? e.target.closest('.js-del-unused') : null;
      if(!btn) return;
      const id = btn.getAttribute('data-id');
      if(!id) return;
      if(!confirm('Delete this unused redeem code?')) return;

      const csrfToken = csrfFrom(manageForm);
      const d = new FormData();
      d.set('_csrf', csrfToken);
      d.set('id', String(id));
      const json = await postJson(manageForm.dataset.endpointDeleteUnused, d, csrfToken);
      showManageFlash((json && json.message) ? json.message : (json && json.success ? 'OK' : 'Failed'), !!(json && json.success));
      refreshManage().catch(()=>{});
    });

    // Initial load
    refreshManage().catch(()=>{});
  }
})();
