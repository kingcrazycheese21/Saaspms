// public/app.js - full frontend for Hotel PMS
(function(){
  function $(sel,ctx=document){return ctx.querySelector(sel)}
  function $all(sel,ctx=document){return Array.from(ctx.querySelectorAll(sel))}
  function uid(prefix='id'){return prefix+'_'+Math.random().toString(36).slice(2,9)}
  async function api(action, payload){
    const res = await fetch('../api/api.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(Object.assign({action}, payload||{}))
    });
    return res.json();
  }

  // UI elements
  const hotelSelector = $('#hotelSelector')
  const content = $('#content')
  const templates = {}
  $all('template').forEach(t => templates[t.id.replace('Tpl','')] = t)

  function renderSidebarActive(name){
    $all('.sidebar button').forEach(b=>b.classList.toggle('active', b.dataset.view===name))
  }

  function setView(view){
    renderSidebarActive(view)
    const tpl = templates[view]
    if(!tpl) return
    content.innerHTML = ''
    content.appendChild(tpl.content.cloneNode(true))
    bindView(view)
  }

  // authentication UI
  async function showLogin(){
    content.innerHTML = ''
    content.appendChild(templates['login'].content.cloneNode(true))
    const form = $('#loginForm')
    form.addEventListener('submit', async e=>{
      e.preventDefault(); const f = new FormData(form)
      const res = await api('login',{username:f.get('username'), password:f.get('password')})
      if(res.error) return alert(res.error)
      document.getElementById('userName').textContent = res.user.username
      await initApp()
    })
  }

  async function logout(){
    await api('logout',{})
    location.reload()
  }
  document.getElementById('logoutBtn').addEventListener('click', logout)

  async function refreshHotelOptions(){
    const data = await api('list')
    hotelSelector.innerHTML = ''
    (data.hotels||[]).forEach(h=>{
      const o = document.createElement('option'); o.value=h.id; o.textContent = h.name; hotelSelector.appendChild(o)
    })
  }

  document.getElementById('addHotelBtn').addEventListener('click', async ()=>{
    const name = prompt('Hotel name?'); if(!name) return
    await api('createHotel',{name}); await refreshHotelOptions(); alert('Hotel created: '+name)
  })

  $all('.sidebar button').forEach(b=> b.addEventListener('click', ()=> setView(b.dataset.view)) )

  hotelSelector.addEventListener('change', ()=> setView(document.querySelector('.sidebar button.active').dataset.view))

  async function bindView(view){
    const list = await api('list')
    const hotelId = hotelSelector.value || (list.hotels && list.hotels[0] && list.hotels[0].id)
    if(!hotelId && view!=='settings' && view!=='payments'){ content.innerHTML = '<p>No hotel available. Create one in Settings.</p>'; return; }
    if(view==='dashboard') bindDashboard(hotelId)
    else if(view==='reservations') bindReservations(hotelId)
    else if(view==='housekeeping') bindHousekeeping(hotelId)
    else if(view==='billing') bindBilling(hotelId)
    else if(view==='shifts') bindShifts(hotelId)
    else if(view==='reports') bindReports(hotelId)
    else if(view==='payments') bindPayments(hotelId)
    else if(view==='settings') bindSettings(hotelId)
  }

  // Dashboard
  async function bindDashboard(hotelId){
    const data = await api('list')
    const hotel = data.hotels.find(h=>h.id===hotelId)
    const canvas = $('#occupancyChart'); const ctx = canvas.getContext('2d')
    const occupied = hotel.rooms.filter(r=>r.status==='occupied').length; const vacant = hotel.rooms.length - occupied
    new Chart(ctx,{type:'doughnut',data:{labels:['Occupied','Vacant'],datasets:[{data:[occupied,vacant]}]}})
    const todayList = $('#todayResList'); todayList.innerHTML=''
    const today = new Date().toISOString().slice(0,10)
    (data.reservations||[]).filter(r=>r.hotel_id===hotelId && r.checkin===today).forEach(r=>{ const li=document.createElement('li'); li.textContent=`${r.guest} - ${r.room_number||'TBD'}`; todayList.appendChild(li) })
  }

  // Reservations
  async function bindReservations(hotelId){
    const data = await api('list')
    const hotel = data.hotels.find(h=>h.id===hotelId)
    const form = $('#resForm'); const roomSel = form.querySelector('select[name=room]'); roomSel.innerHTML='<option value="">--room--</option>'
    hotel.rooms.forEach(r=>{ const opt=document.createElement('option'); opt.value=r.id; opt.textContent=r.number+' ('+r.room_type+')'; roomSel.appendChild(opt) })
    const tableBody = $('#resTable tbody')
    async function refresh(){
      const list = (await api('list')).reservations.filter(r=>r.hotel_id===hotelId)
      tableBody.innerHTML=''
      list.forEach(r=>{
        const tr=document.createElement('tr')
        tr.innerHTML = `<td>${r.guest}</td><td>${r.room_number||'-'}</td><td>${r.checkin}</td><td>${r.checkout}</td><td>${r.amount}</td>
          <td><button class="edit" data-id="${r.id}">Edit</button> <button class="delete" data-id="${r.id}">Delete</button></td>`
        tableBody.appendChild(tr)
      })
      $all('.delete', tableBody).forEach(btn=> btn.addEventListener('click', async ()=>{ if(confirm('Delete reservation?')){ await api('deleteReservation',{id:btn.dataset.id}); refresh() } }))
      $all('.edit', tableBody).forEach(btn=> btn.addEventListener('click', async ()=>{
        const id = btn.dataset.id; const res = await api('getReservation',{id}); const r=res.reservation
        if(!r) return alert('Not found')
        // simple inline edit prompt (for demo)
        const guest = prompt('Guest name', r.guest); if(!guest) return
        const amount = prompt('Amount', r.amount)
        await api('updateReservation',{id, guest, checkin: r.checkin, checkout: r.checkout, roomId: r.room_id, amount: amount})
        refresh()
      }))
    }
    form.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(form); await api('createReservation',{hotelId, guest:f.get('guest'), checkin:f.get('checkin'), checkout:f.get('checkout'), roomId:f.get('room'), amount:Number(f.get('amount'))}); form.reset(); refresh() })
    refresh()
  }

  // Housekeeping
  async function bindHousekeeping(hotelId){
    const data = await api('list'); const hotel = data.hotels.find(h=>h.id===hotelId)
    const form = $('#hkForm'); const roomSel = form.querySelector('select[name=room]'); roomSel.innerHTML=''
    hotel.rooms.forEach(r=>{ const o=document.createElement('option'); o.value=r.id; o.textContent=r.number+' ('+r.status+')'; roomSel.appendChild(o) })
    const tableBody = $('#hkTable tbody')
    async function refresh(){
      const res = await api('listHK',{hotelId}); const hk = res.housekeeping || []
      tableBody.innerHTML=''
      hk.forEach(t=>{
        const tr=document.createElement('tr')
        tr.innerHTML = `<td>${t.room_number}</td><td>${t.assigned||'-'}</td><td>${t.status}</td><td>${t.status!=='done'?'<button class="done" data-id="'+t.id+'">Mark Done</button>':''}</td>`
        tableBody.appendChild(tr)
      })
      $all('.done', tableBody).forEach(btn=> btn.addEventListener('click', async ()=>{ if(confirm('Mark done?')){ await api('completeHousekeeping',{id:btn.dataset.id}); refresh() } }))
    }
    form.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(form); await api('createHK',{hotelId, roomId:f.get('room'), assigned:f.get('assigned')}); form.reset(); refresh() })
    refresh()
  }

  // Billing
  async function bindBilling(hotelId){
    const form = $('#billingForm'); const tableBody = $('#billingTable tbody')
    async function refresh(){
      const res = await api('listFolios',{hotelId}); const folios = res.folios || []
      tableBody.innerHTML=''
      folios.forEach(f=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${f.guest||f.id}</td><td>${f.description}</td><td>${f.amount}</td><td>${f.shift_code||'-'}</td><td>${f.time_created}</td>`; tableBody.appendChild(tr) })
    }
    form.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(form); const shiftCode = f.get('shiftCode')||''; await api('postFolio',{hotelId, guest:f.get('folioGuest'), desc:f.get('desc'), amount:Number(f.get('amount')), shiftCode}); form.reset(); refresh() })
    refresh()
  }

  // Shifts
  async function bindShifts(hotelId){
    const shiftCodeInput = $('#shiftCodeInput'); const openBtn = $('#openShiftBtn'), closeBtn = $('#closeShiftBtn'), startNewBtn = $('#startNewShiftBtn')
    const info = $('#currentShiftInfo'), txBody = $('#shiftTxTable tbody')
    async function refresh(){
      const sres = await api('listShifts',{hotelId}); const shifts = sres.shifts || []
      const open = shifts.find(s=>s.hotel_id===hotelId && s.status==='open') || null
      if(open) info.textContent = `Open Shift: ${open.code} started at ${open.started_at}`; else info.textContent = 'No open shift'
      const txs = (await api('list')).transactions.filter(t=>t.hotel_id===hotelId)
      txBody.innerHTML=''
      txs.forEach(t=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${t.time_created}</td><td>${t.type}</td><td>${t.amount}</td><td>${t.ref||'-'}</td>`; txBody.appendChild(tr) })
    }
    openBtn.addEventListener('click', async ()=>{ const code = shiftCodeInput.value || ''; await api('openShift',{hotelId, code}); shiftCodeInput.value=''; refresh() })
    closeBtn.addEventListener('click', async ()=>{ await api('closeShift',{hotelId}); refresh() })
    startNewBtn.addEventListener('click', async ()=>{ await api('startNewShift',{hotelId}); refresh() })
    refresh()
  }

  // Reports & Night Audit
  async function bindReports(hotelId){
    const out = $('#reportOutput')
    $('#generateReportBtn').addEventListener('click', async ()=>{ const r = await api('generateShiftReport',{hotelId}); out.textContent = JSON.stringify(r, null, 2); window.print() })
    $('#nightAuditBtn').addEventListener('click', async ()=>{ const res = await api('nightAudit',{hotelId}); out.textContent = JSON.stringify(res, null, 2) })
  }

  // Payments
  async function bindPayments(hotelId){
    const form = $('#paymentForm'); const gatewaySelect = $('#gatewaySelect'); const result = $('#paymentResult')
    async function refresh(){ const g = await api('listGateways'); gatewaySelect.innerHTML=''; (g.gateways||[]).forEach(x=>{ const o=document.createElement('option'); o.value=x.id; o.textContent = x.name+' ('+x.url+')'; gatewaySelect.appendChild(o) }) }
    form.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(form); const gw = f.get('gateway'); const payload = {hotelId, amount:Number(f.get('amount')), card:f.get('card')}; const res = await api('chargePayment',{gatewayId:gw, payload}); result.textContent = JSON.stringify(res, null, 2) })
    refresh()
  }

  // Settings (hotels, rooms, gateways)
  async function bindSettings(hotelId){
    const hotelForm = $('#hotelForm'); const roomForm = $('#roomForm'); const gatewayForm = $('#gatewayForm'); const gatewaysList = $('#gatewaysList');
    const hotelSel = roomForm.querySelector('select[name=hotel]');
    async function refresh(){
      const data = await api('list'); hotelSel.innerHTML=''; (data.hotels||[]).forEach(h=>{ const o=document.createElement('option'); o.value=h.id; o.textContent=h.name; hotelSel.appendChild(o) })
      const g = await api('listGateways'); gatewaysList.innerHTML=''; (g.gateways||[]).forEach(x=>{ const li=document.createElement('li'); li.textContent = x.name + ' - ' + x.url; gatewaysList.appendChild(li) })
    }
    hotelForm.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(hotelForm); await api('createHotel',{name:f.get('name')}); hotelForm.reset(); refresh() })
    roomForm.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(roomForm); await api('createRoom',{hotelId:f.get('hotel'), number:f.get('number'), type:f.get('type')}); roomForm.reset(); refresh() })
    gatewayForm.addEventListener('submit', async e=>{ e.preventDefault(); const f=new FormData(gatewayForm); await api('registerGateway',{name:f.get('name'), url:f.get('url')}); gatewayForm.reset(); refresh() })
    refresh()
  }

  // Init app: check if logged in, else show login
  async function initApp(){
    // try to call list; if 401 -> show login
    const res = await fetch('../api/api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'list'})});
    if(res.status===401){ showLogin(); return; }
    const data = await res.json(); if(data.error){ showLogin(); return; }
    document.getElementById('userName').textContent = (data.user && data.user.username) || 'user';
    await refreshHotelOptions(); setView('dashboard'); bindView('dashboard')
  }

  // boot
  (async function boot(){ await initApp(); })();

})();