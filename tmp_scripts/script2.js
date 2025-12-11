
(function(){
  const form = document.getElementById('entitlementsForm');
  const panel = document.getElementById('hooksPanel');
  if (!form || !panel) return;
  // Upload UI wiring
  const drop = document.getElementById('uploadDropzone');
  const addBtn = document.getElementById('addFilesBtn');
  const inputMulti = document.getElementById('ticketMulti');
  const inputSingle = document.getElementById('ticketSingle');
  const list = document.getElementById('selectedFilesList');
  const clearBtn = document.getElementById('clearFilesBtn');
  let dt = new DataTransfer();

  function fileKey(f){ return [f.name, f.size, f.lastModified].join(':'); }
  function addFiles(files){
    const seen = new Set(Array.from(dt.files).map(fileKey));
    for (const f of files) { if (!seen.has(fileKey(f))) dt.items.add(f); }
    sync();
  }
  function removeIndex(idx){
    const ndt = new DataTransfer();
    Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) ndt.items.add(f); });
    dt = ndt; sync();
  }
  function sync(){
    inputMulti.files = dt.files;
    const sdt = new DataTransfer();
    if (dt.files.length > 0) sdt.items.add(dt.files[0]);
    inputSingle.files = sdt.files;
    renderList();
    // Auto-submit after a micro delay so UI updates first
    setTimeout(()=>form.submit(), 0);
  }
  function renderList(){
    list.innerHTML = '';
    if (dt.files.length === 0) {
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Der er ikke valgt nogen fil.';
      list.appendChild(li);
      return;
    }
    Array.from(dt.files).forEach((f, i)=>{
      const li = document.createElement('li');
      li.style.display = 'flex'; li.style.alignItems = 'center'; li.style.gap = '8px'; li.style.marginTop = '6px';
      const name = document.createElement('span');
      name.textContent = f.name + ' (' + Math.round(f.size/1024) + ' KB)';
      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'small'; rm.textContent = 'Fjern';
      rm.addEventListener('click', ()=> removeIndex(i));
      li.appendChild(name); li.appendChild(rm);
      list.appendChild(li);
    });
  }
  if (drop && addBtn && inputMulti && inputSingle && list) {
  drop.addEventListener('click', ()=> inputMulti.click());
  addBtn.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); inputMulti.click(); });
    if (clearBtn) clearBtn.addEventListener('click', (e)=>{
      e.preventDefault(); e.stopPropagation();
      // Reset client-side selection
      dt = new DataTransfer();
      // Mark server-side clear-all and submit
      const hid = document.createElement('input');
      hid.type = 'hidden'; hid.name = 'clear_all'; hid.value = '1';
      form.appendChild(hid);
      sync();
    });
    drop.addEventListener('dragover', (e)=>{ e.preventDefault(); drop.style.background='#eef7ff'; });
    drop.addEventListener('dragleave', ()=>{ drop.style.background='#f7fbff'; });
    drop.addEventListener('drop', (e)=>{ e.preventDefault(); drop.style.background='#f7fbff'; if (e.dataTransfer?.files?.length){ addFiles(e.dataTransfer.files); }});
    inputMulti.addEventListener('change', ()=>{ if (inputMulti.files?.length) addFiles(inputMulti.files); });
    renderList();
  }

  // Always use partial AJAX refresh on input/change (PGR flow)
  let t = null;
  const trigger = () => {
    if (t) clearTimeout(t);
    t = setTimeout(async () => {
      try {
        const fd = new FormData(form);
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_hooks','1');
        const tokenInput = form.querySelector('input[name="_csrfToken"]');
        const csrf = tokenInput ? tokenInput.value : '';
        const res = await fetch(url.toString(), {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
          credentials: 'same-origin',
          body: fd
        });
        if (!res.ok) return;
        const html = await res.text();
        panel.innerHTML = html;
      } catch (e) { /* ignore network errors for now */ }
    }, 300);
  };
  form.addEventListener('input', trigger, { passive: true });
  form.addEventListener('change', trigger, { passive: true });

  // Sync missed-connection radios to text field
  const mcField = document.getElementById('mcField');
  const mcRadios = Array.from(document.querySelectorAll('input[type="radio"][data-mc-single]'));
  const syncMcSingle = () => {
    if (!mcField || !mcRadios.length) return;
    const sel = mcRadios.find(r=>r.checked);
    mcField.value = sel ? sel.value : '';
  };
  if (mcRadios.length) {
    mcRadios.forEach(r=> r.addEventListener('change', syncMcSingle));
    syncMcSingle();
  }

  // Bike flow client-side visibility for smoother UX
  function val(name){
    const nodes = form.querySelectorAll('input[name="'+name+'"]');
    for (const n of nodes) { if ((n.type==='radio'||n.type==='checkbox') && n.checked) return n.value; }
    return '';
  }
  function show(el, on){ if (!el) return; el.style.display = on ? 'block' : 'none'; }
  function updateBike(){
    const q2 = document.getElementById('bikeQ2');
    const block = document.getElementById('bikeArticle6');
    const q3b = document.getElementById('bikeQ3B');
    const q5 = document.getElementById('bikeQ5');
    const q6 = document.getElementById('bikeQ6');
    const was = val('bike_was_present') || '';
    const cause = val('bike_caused_issue') || '';
    const resMade = val('bike_reservation_made') || '';
    const denied = val('bike_denied_boarding') || '';
    const reasonProv = val('bike_refusal_reason_provided') || '';
    show(q2, was==='yes');
    show(block, was==='yes' && cause==='yes');
    show(q3b, resMade==='no');
    show(q5, denied==='yes');
    show(q6, denied==='yes' && reasonProv==='yes');
  }
  form.addEventListener('change', (e)=>{
    if (!e.target) return;
    const nm = e.target.name||'';
    if (nm.startsWith('bike_')) { updateBike(); }
  }, { passive:true });
  // Initial
  updateBike();

  // Klasse & reservation: intelligent trinvis visning
  function updateClassUI(){
    const q4 = document.getElementById('classQ4');
    const selRes = form.querySelector('select[name="berth_seat_type"]');
    const resVal = (selRes && selRes.value) ? selRes.value : '';
    const needsDelivery = ['seat','couchette','sleeper'].includes(resVal||'');
    if (q4) q4.style.display = needsDelivery ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && (e.target.name||'')) || '';
    if (nm === 'fare_class_purchased' || nm === 'berth_seat_type') updateClassUI();
    if (nm === 'fare_flex_type') updateSeasonUI();
  }, { passive:true });
  updateClassUI();

  // Season/period pass: show details block when dropdown is "pass"
  function updateSeasonUI(){
    const sel = form.querySelector('select[name="fare_flex_type"]');
    const block = document.getElementById('seasonPassBlock');
    const has = sel && sel.value === 'pass';
    if (block) block.style.display = has ? 'block' : 'none';
    // Maintain a hidden flag so server can persist even on AJAX refreshes
    let hid = form.querySelector('input[name="season_pass_has"]');
    if (!hid) { hid = document.createElement('input'); hid.type = 'hidden'; hid.name = 'season_pass_has'; form.appendChild(hid); }
    hid.value = has ? '1' : '';
  }
  updateSeasonUI();

  // Pricing block: show Q2 only after user changes Q1 (fare_flex_type)
  (function(){
    const sel = form.querySelector('select[name="fare_flex_type"]');
    const q2 = document.getElementById('pricingQ2');
    if (!sel || !q2) return;
    const initial = sel.value || '';
    let shown = false;
    function maybeShowQ2(){
      if (!shown && (sel.value||'') !== initial) {
        q2.style.display = 'block';
        shown = true;
      }
    }
    sel.addEventListener('change', maybeShowQ2, { passive: true });
  })();

  // Debug checkbox toggles ?debug=1 in the URL for more hooks info
  const dbg = document.getElementById('toggleDebugChk');
  if (dbg) {
    dbg.addEventListener('change', ()=>{
      const url = new URL(window.location.href);
      if (dbg.checked) { url.searchParams.set('debug','1'); }
      else { url.searchParams.delete('debug'); }
      // Preserve PRG mode flag in URL
      try {
        const prgNow = new URLSearchParams(window.location.search).get('prg') || new URLSearchParams(window.location.search).get('pgr');
        if (prgNow) { url.searchParams.set('prg','1'); }
      } catch(e) {}
      window.location.assign(url.toString());
    });
  }
  // Handle ticket removal without nested forms (keeps main form valid)
  const rmBtns = Array.from(document.querySelectorAll('.remove-ticket-btn'));
  if (rmBtns.length) {
    rmBtns.forEach(btn => {
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        const v = btn.getAttribute('data-file') || '';
        if (!v) return;
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'remove_ticket';
        hid.value = v;
        form.appendChild(hid);
        form.submit();
      });
    });
  }

  // PMR client-side visibility
  function valRadio(name){
    const els = form.querySelectorAll('input[name="'+name+'"]');
    for (const el of els) { if (el.checked) return el.value; }
    return '';
  }
  function updatePMR(){
    const qBooked = document.getElementById('pmrQBooked');
    const qDelivered = document.getElementById('pmrQDelivered');
    const qProm = document.getElementById('pmrQPromised');
    const qDet = document.getElementById('pmrQDetails');
    const u = valRadio('pmr_user');
    const b = valRadio('pmr_booked');
    const p = valRadio('pmr_promised_missing');
    if (qBooked) qBooked.style.display = (u==='yes') ? 'block' : 'none';
    if (qDelivered) qDelivered.style.display = (u==='yes' && b!=='no') ? 'block' : 'none';
    if (qProm) qProm.style.display = (u==='yes') ? 'block' : 'none';
    if (qDet) qDet.style.display = (p==='yes') ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && e.target.name) || '';
    if (nm.startsWith('pmr_')) updatePMR();
    if (nm === 'preinformed_disruption' || nm === 'preinfo_channel' || nm === 'realtime_info_seen') updateDisruption();
  }, { passive:true });
  updatePMR();

  // Disruption client-side visibility + simple required checks for Continue
  const art10Applies = true;
  function valRadio2(name){ const els = form.querySelectorAll('input[name="'+name+'"]'); for (const el of els) { if (el.checked) return el.value; } return ''; }
  function updateDisruption(){
    const q2 = document.getElementById('disQ2');
    const q3 = document.getElementById('disQ3');
    const q1 = valRadio2('preinformed_disruption');
    if (q2) q2.style.display = (q1==='yes') ? 'block' : 'none';
    if (q3) q3.style.display = (q1==='yes' && art10Applies) ? 'block' : 'none';
  }
  updateDisruption();

  // Art. 12: show only necessary questions, allow edit to reveal radios
  const a12Init = {
    sellerInit: '',
    showSeller: false,
    showThrough: false,
    showSeparate: false
  };
  function updateA12(){
    const q1 = document.getElementById('a12Q1');
    const q2 = document.getElementById('a12Q2');
    const q3 = document.getElementById('a12Q3');
    const seller = valRadio2('seller_channel') || a12Init.sellerInit;
    // Reveal more questions only if initially required OR if user changes seller to a different channel (esp. retailer)
    const needExtra = (seller && seller !== a12Init.sellerInit) || seller === 'retailer' || seller === 'unknown';
    if (q2) q2.style.display = (a12Init.showThrough || needExtra) ? 'block' : 'none';
    if (q3) q3.style.display = (a12Init.showSeparate || needExtra) ? 'block' : 'none';
  }
  const a12EditBtn = document.getElementById('a12EditSellerBtn');
  if (a12EditBtn) {
    a12EditBtn.addEventListener('click', function(){
      const q1 = document.getElementById('a12Q1');
      if (q1) { q1.style.display = 'block'; q1.scrollIntoView({ behavior:'smooth', block:'center' }); }
      updateA12();
    });
  }
  // React when seller choice changes
  (function(){
    const els = document.querySelectorAll('input[name="seller_channel"]');
    els.forEach(function(el){ el.addEventListener('change', updateA12, { passive:true }); });
  })();

  // Only enforce disruption answers (Art. 9(1)) when that article is actually applicable/visible
  const art9_1_enforced = false;
  const disruptionBlock = document.getElementById('disruptionBlock');
  const contBtn = form.querySelector('button[name="continue"]');
  if (contBtn) {
    contBtn.addEventListener('click', (e)=>{
      // Skip gating entirely when Art. 9(1) is exempt/hidden
      if (!art9_1_enforced || !disruptionBlock) { return; }
      const err = document.getElementById('disruptionReqError');
      if (err) err.style.display = 'none';
      const q1 = valRadio2('preinformed_disruption');
      if (!q1) {
        e.preventDefault();
        if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
      }
      if (q1 === 'yes') {
        const sel = form.querySelector('select[name="preinfo_channel"]');
        if (sel && !sel.value) {
          e.preventDefault();
          if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
          return;
        }
        if (art10Applies) {
          const q3 = valRadio2('realtime_info_seen');
          if (!q3) {
            e.preventDefault();
            if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
          }
        }
      }
    });
  }
})();
