function pzOptionalLimit(value){const n=Number(value);return Number.isFinite(n)&&n>0?n:null;}
'use strict';
const boot = JSON.parse(document.getElementById('pzBoot').textContent);
let token = boot.token, data = null, activeClient = null, activeDog = null;
let cart = [], payment = 'Gotówka', plannedVisit = 0, lastReceipt = null, saving = false;
let requestKey = crypto.randomUUID(), apiQueue = Promise.resolve();
let pendingSale = null;
try { const saved=JSON.parse(sessionStorage.getItem(boot.storageKey)||'null');if(saved&&typeof saved.requestKey==='string'&&Array.isArray(saved.items))pendingSale=saved; } catch { /* Corrupt browser state must never stop the application. */ }
const $ = id => document.getElementById(id);
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = cents => (Number(cents) / 100).toLocaleString('pl-PL', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' zł';
const today = () => { const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; };
function message(text, bad=false) { $('pzMessage').textContent=text; $('pzMessage').className=bad?'notice error':'notice'; $('pzMessage').hidden=!text; }
function api(op, payload, args={}) {
  const run = async (retry=false) => {
    const query=new URLSearchParams({op,...args});
    const options={credentials:'same-origin',headers:{Accept:'application/json','X-PZ-User':String(boot.userId)}};
    if(payload!==undefined){options.method='POST';options.body=new URLSearchParams({op,token,data:JSON.stringify(payload)});}
    let response;
    try { response=await fetch('api.php?'+query,options); } catch {throw new Error('Brak połączenia. Zachowano koszyk. Ponów zapis po odzyskaniu połączenia.');}
    if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('Sesja wygasła lub serwer zwrócił błąd. Zaloguj się ponownie i odśwież stronę.');
    const result=await response.json();if(result._token)token=result._token;
    if(!retry&&payload!==undefined&&response.status===403&&result.code==='csrf'&&result._token)return run(true);
    if(!response.ok||result.error){const e=new Error(result.error||'Operacja nie powiodła się.');e.definitive=response.status>=400&&response.status<500;throw e;}
    return result;
  };
  const promise=apiQueue.then(()=>run(),()=>run());apiQueue=promise.catch(()=>{});return promise;
}
async function refresh(){data=await api('data');renderAll();if(typeof refreshCommerce==='function')await refreshCommerce();}
function canWrite(){return !!boot.write;}
function writeButton(label, action, cls='primary'){return canWrite()?`<button class="btn ${cls}" data-action="${action}">${label}</button>`:'';}
function sizeLabel(weight){
  if(weight===null||weight===''||!Number.isFinite(Number(weight))||Number(weight)<=0)return 'Brak prawidłowej wagi';
  if(weight<=10)return 'Mały pies do 10 kg';if(weight<=20)return 'Średni pies 10–20 kg';if(weight<=35)return 'Duży pies 20–35 kg';return 'Bardzo duży pies 35+ kg';
}
function updateStepper(stage){const order=['client','dog','service','payment'];const n=order.indexOf(stage);order.forEach((s,i)=>{$('stepPill'+(i+1)).classList.toggle('active',i===n);$('stepPill'+(i+1)).classList.toggle('done',i<n);});}
function goToStage(stage){
  if(saving||pendingSale)return;
  if(stage!=='client'&&!activeClient){message('Najpierw wybierz klienta.',true);return;}
  if(['service','payment'].includes(stage)&&!activeDog){message('Najpierw wybierz psa.',true);return;}
  if(stage==='payment'&&!cart.length){message('Dodaj usługę do koszyka.',true);return;}
  message('');
  document.querySelectorAll('#pos .pos-stage').forEach(e=>e.classList.remove('active'));
  $('stage'+stage[0].toUpperCase()+stage.slice(1)).classList.add('active');updateStepper(stage);
  if(stage==='dog')renderPosDogs();if(['service','payment'].includes(stage))refreshSelectionUI();
}
function renderPosClients(filter=''){
  const found=data.clients.filter(c=>(c.name+' '+(c.phone||'')+' '+(c.email||'')).toLowerCase().includes(filter.trim().toLowerCase()));
  $('posClientResults').innerHTML=found.map(c=>`<button class="client-card" data-client="${Number(c.id)}"><b>${esc(c.name)}</b><small>${esc(c.phone)}<br>${esc(c.email)}</small></button>`).join('')||'<p class="muted">Brak klientów pasujących do wyszukiwania.</p>';
}
function renderPosDogs(){
  if(!activeClient)return;$('clientSummaryChip').textContent='Klient: '+activeClient.name;
  const found=data.dogs.filter(d=>Number(d.clientId)===Number(activeClient.id));
  $('posDogResults').innerHTML=found.map(d=>`<button class="dog-card" data-dog="${Number(d.id)}"><div style="font-size:28px">🐶</div><b>${esc(d.name)}</b><small>${esc(d.breed)}<br>${d.weight===null?'Brak wagi':esc(d.weight)+' kg'}<br>${esc(sizeLabel(d.weight))}</small></button>`).join('')||'<p class="muted">Ten klient nie ma jeszcze psa. Dodaj kartotekę, aby kontynuować.</p>';
}
function refreshSelectionUI(){
  if(!activeClient||!activeDog)return;
  $('selectedClientChip').textContent='👤 '+activeClient.name;
  $('selectedDogChip').textContent='🐶 '+activeDog.name+' • '+(activeDog.breed||'');
  $('paymentClientChip').textContent='👤 '+activeClient.name;$('paymentDogChip').textContent='🐶 '+activeDog.name;
  $('dogSizeHint').textContent=activeDog.name+' — '+sizeLabel(activeDog.weight)+'. Wybierz odpowiednią usługę z cennika.';
}
function renderServices(filter=''){
  const match=s=>s.name.toLowerCase().includes(filter.trim().toLowerCase());
  const tile=(s,addon)=>`<button class="${addon?'addon':'service'}" data-service="${esc(s.id)}"><div class="dog">${addon?'✂️':'🐾'}</div><b>${esc(s.name)}</b><strong>${money(s.price)}</strong></button>`;
  $('services').innerHTML=data.config.services.filter(s=>s.active!==false&&!s.addon&&match(s)).map(s=>tile(s,false)).join('')||'<p>Brak usług.</p>';
  $('addons').innerHTML=data.config.services.filter(s=>s.active!==false&&s.addon).map(s=>tile(s,true)).join('');
}
function clearCart(){if(saving||pendingSale)return;cart=[];requestKey=crypto.randomUUID();renderCart();$('lastSelectedBox').style.display='none';}
function addToCart(id){
  if(saving||pendingSale||!activeClient||!activeDog)return;
  if(cart.length>=100){message('Koszyk może zawierać najwyżej 100 pozycji.',true);return;}
  const s=data.config.services.find(s=>s.id===id);if(!s)return;
  message('');
  cart.push({...s});requestKey=crypto.randomUUID();renderCart();
  $('lastSelectedName').textContent=s.name;$('lastSelectedPrice').textContent=money(s.price);$('lastSelectedBox').style.display='block';
}
function renderCart(){
  $('cartBody').innerHTML=cart.map((s,i)=>`<tr><td>${esc(s.name)}<br><button class="btn danger" data-remove="${i}">usuń</button></td><td>${money(s.price)}</td></tr>`).join('');
  $('paymentCartBody').innerHTML=cart.map(s=>`<tr><td>${esc(s.name)}</td><td>${money(s.price)}</td></tr>`).join('');
  $('cartTotal').textContent=$('paymentCartTotal').textContent=money(cart.reduce((sum,s)=>sum+s.price,0));
}
function showView(id){
  document.querySelectorAll('.view').forEach(e=>e.classList.toggle('active',e.id===id));
  document.querySelectorAll('.nav button').forEach(e=>{e.classList.toggle('active',e.dataset.view===id);if(e.dataset.view===id)$('pageTitle').textContent=e.textContent.trim();});
}
function table(headers,rows){return `<div class="table-scroll"><table class="table"><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')||`<tr><td colspan="${headers.length}">Brak danych.</td></tr>`}</tbody></table></div>`;}
function row(values){return '<tr>'+values.map(x=>'<td>'+x+'</td>').join('')+'</tr>';}
function renderClients(){
  const filter=($('clientListSearch')?.value||'').toLowerCase();
  $('clientList').innerHTML=table(['Klient','Telefon','E-mail','Psy','Kartoteka'],data.clients.filter(c=>(c.name+' '+c.phone+' '+c.email).toLowerCase().includes(filter)).map(c=>row([esc(c.name),esc(c.phone),esc(c.email),esc(data.dogs.filter(d=>Number(d.clientId)===Number(c.id)).map(d=>d.name).join(', ')),`<button class="btn ghost" data-edit-client="${Number(c.id)}">${canWrite()?'Otwórz / edytuj klienta':'Otwórz'}</button> ${archiveButton('client',c.id)} <button type="button" class="btn ghost" data-commerce="clientreport" data-id="${Number(c.id)}">Obrót / raport</button>`])));
}
function renderDogs(){
  $('dogList').innerHTML=table(['Pies','Rasa','Waga','Właściciel','Kartoteka'],data.dogs.map(d=>row([esc(d.name),esc(d.breed),d.weight===null?'—':esc(d.weight)+' kg',esc(data.clients.find(c=>Number(c.id)===Number(d.clientId))?.name),`<button class="btn ghost" data-edit-dog="${Number(d.id)}">${canWrite()?'Otwórz / edytuj':'Otwórz'}</button> ${archiveButton('dog',d.id)}`])));
}
function visitStatusClass(status){return ['planned','completed','cancelled'].includes(status)?'status-'+status:'status-unknown';}
function visitStatusBadge(status){const label={planned:'Zarezerwowana',completed:'Rozliczona',cancelled:'Odwołana'};return '<span class="visit-status '+visitStatusClass(status)+'">'+esc(label[status]||status)+'</span>';}
function renderVisits(){
  renderCalendar();
  const start=$('visitFrom').value,end=$('visitTo').value;
  const status={planned:'Planowana',completed:'Rozliczona',cancelled:'Anulowana'};
  $('visitList').innerHTML=table(['Termin','Klient','Pies','Status','Kwota','Płatność','Akcje'],data.visits.filter(v=>(!start||v.visit_date.slice(0,10)>=start)&&(!end||v.visit_date.slice(0,10)<=end)).map(v=>row([
    esc(v.visit_date.slice(0,16)),esc(v.client),esc(v.dog),visitStatusBadge(v.status),money(Math.round(Number(v.amount_total)*100)),v.status==='completed'?(v.paymentState.received?'Otrzymana':'Oczekuje'):'—',
    v.status==='planned'&&canWrite()?`<button class="btn ghost" data-edit-visit="${Number(v.rowid)}">Edytuj termin</button> <button class="btn soft" data-checkout="${Number(v.rowid)}">Rozlicz</button> <button class="btn ghost" data-cancel="${Number(v.rowid)}">Anuluj</button>`:v.status==='completed'?`<button class="btn ghost" data-receipt="${Number(v.rowid)}">Potwierdzenie 80 mm</button> <button type="button" class="btn ghost" data-commerce="visitpreview" data-id="${Number(v.rowid)}">Dokument sprzedaży</button>${!v.paymentState.received&&canWrite()?` <button class="btn mint" data-paid="${Number(v.rowid)}">Otrzymano wpłatę</button>`:''}`:''
  ])));
}
function renderExpenses(){
  $('expenseList').innerHTML=table(['Data','Dokument','Dostawca','Kategoria','Opis','Kwota'],data.expenses.map(e=>row([esc(e.expense_date),esc(e.document_no),esc(e.supplier),esc(e.category),esc(e.description),money(Math.round(Number(e.amount_gross)*100))])));
}
function period(){return {from:$('reportFrom').value,to:$('reportTo').value};}
function reportData(){const p=period(),inside=d=>(!p.from||d>=p.from)&&(!p.to||d<=p.to);return {visits:data.visits.filter(v=>v.status==='completed'&&inside(v.paymentState.completedDate||v.visit_date.slice(0,10))),received:data.visits.filter(v=>v.status==='completed'&&v.paymentState.received&&v.paymentState.date&&inside(v.paymentState.date)),expenses:data.expenses.filter(e=>inside(e.expense_date))};}
function renderReports(){
  const r=reportData(),sum=(a,k)=>a.reduce((n,v)=>n+Math.round(Number(v[k])*100),0);
  const revenue=sum(r.visits,'amount_total'),received=sum(r.received,'amount_total'),cost=sum(r.expenses,'amount_gross');
  $('accountingTotals').innerHTML=[['Wartość rozliczonych wizyt',revenue],['Otrzymane wpłaty',received],['Zapisane koszty',cost],['Wpłaty minus koszty',received-cost]].map(([name,v])=>`<div class="kpi"><span>${name}</span><strong>${money(v)}</strong></div>`).join('');
  $('reportSummary').textContent=`W wybranym okresie rozliczono ${r.visits.length} wizyt. Średnia wartość: ${money(r.visits.length?Math.round(revenue/r.visits.length):0)}.`;
  const months={};r.visits.forEach(v=>{const k=(v.paymentState.completedDate||v.visit_date).slice(0,7);months[k]=(months[k]||0)+Math.round(Number(v.amount_total)*100);});
  renderVisitDashboard(r);
  $('reportTable').innerHTML=table(['Miesiąc','Wartość wizyt'],Object.entries(months).sort().map(([m,v])=>row([esc(m),money(v)])));
  const d=new Date(),q=Math.floor(d.getMonth()/3)*3,from=`${d.getFullYear()}-${String(q+1).padStart(2,'0')}-01`,end=new Date(d.getFullYear(),q+3,0),to=`${end.getFullYear()}-${String(end.getMonth()+1).padStart(2,'0')}-${String(end.getDate()).padStart(2,'0')}`;
  const qr=sum(data.visits.filter(v=>v.status==='completed'&&(v.paymentState.completedDate||v.visit_date.slice(0,10))>=from&&(v.paymentState.completedDate||v.visit_date.slice(0,10))<=to),'amount_total');
  $('limitInfo').textContent=pzOptionalLimit(data.config.quarterLimit)===null?'Własny próg kwartalny: nie ustawiono. Ustawowy limit sprawdzisz w NDG i raporty → Ewidencja NDG.':`Własny próg — bieżący kwartał: ${money(qr)} / ${money(data.config.quarterLimit)}. Pozostało: ${money(Math.max(0,data.config.quarterLimit-qr))}.`;
}
function renderSettings(){
  const c=data.config;
  $('settingsFields').innerHTML=field('salon','Nazwa salonu',c.salon)+field('address','Adres',c.address)+field('phone','Telefon',c.phone)+field('quarterLimit','Własny limit kwartalny (zł; opcjonalnie)',pzOptionalLimit(c.quarterLimit)===null?'':c.quarterLimit/100,'number','step="0.01" min="0"')+
    '<h3 style="grid-column:1/-1">Cennik</h3>'+c.services.map(s=>field('price_'+s.id,s.name,s.price/100,'number','step="0.01" min="0.01" required')).join('');
  $('settingsForm').querySelectorAll('input,button').forEach(e=>e.disabled=!boot.manage);
}
function renderAll(){renderPosClients($('posClientSearch').value);renderServices($('serviceSearch').value);renderClients();renderDogs();renderVisits();renderExpenses();renderReports();renderSettings();renderArchives();}
function field(name,label,value='',type='text',attrs=''){return `<div class="field"><label for="f_${esc(name)}">${esc(label)}</label><input class="input" id="f_${esc(name)}" name="${esc(name)}" type="${type}" value="${esc(value)}" ${attrs}></div>`;}
function selectField(name,label,options,value=''){return `<div class="field"><label for="f_${esc(name)}">${esc(label)}</label><select class="select" id="f_${esc(name)}" name="${esc(name)}" required>${options.map(([id,text])=>`<option value="${esc(id)}" ${String(id)===String(value)?'selected':''}>${esc(text)}</option>`).join('')}</select></div>`;}
function noteField(name,label,value=''){return `<div class="field"><label for="f_${name}">${esc(label)}</label><textarea class="textarea" id="f_${name}" name="${name}" maxlength="5000">${esc(value)}</textarea></div>`;}
let modalSubmit=null,modalBack=null;
function modal(title,html,submit){
  modalBack=null;$('modalSave').textContent='Zapisz';$('modalTitle').textContent=title;$('modalFields').innerHTML=html;$('modalError').textContent='';$('modalSave').hidden=!submit;modalSubmit=submit;$('pzModal').showModal();
}
function dogForm(id,context={}){
  if(!data.clients.length){message('Najpierw dodaj klienta w zakładce Klienci.',true);return;}
  const d=data.dogs.find(x=>Number(x.id)===Number(id))||{};
  const html=selectField('clientId','Właściciel',data.clients.map(c=>[c.id,c.name]),d.clientId||context.clientId||activeClient?.id)+field('name','Imię psa',d.name,'text','required maxlength="128"')+field('breed','Rasa',d.breed,'text','maxlength="128"')+field('weight','Waga (kg)',d.weight,'number','min="0.01" max="150" step="0.01"')+field('birthdate','Data urodzenia',d.birthdate,'date',`max="${today()}"`)+noteField('allergies','Alergie',d.allergies)+noteField('health_notes','Uwagi zdrowotne',d.health_notes)+noteField('grooming_notes','Pielęgnacja',d.grooming_notes)+noteField('behavior_notes','Zachowanie',d.behavior_notes);
  modal(d.id?'Kartoteka psa':'Dodaj psa',html,canWrite()?async values=>{const saved=await api('dog',{...values,id:d.id||0});d.id=Number(saved.id);await refresh();if(activeClient)renderPosDogs();message('Kartoteka psa zapisana w bazie.');if(context.onSaved){context.onSaved(d.id,Number(values.clientId));return false;}}:null);
  if(!canWrite())$('modalFields').querySelectorAll('input,textarea,select').forEach(e=>e.disabled=true);
}
function earliestReservation(){
 const d=new Date(Math.ceil((Date.now()+3600000)/60000)*60000);
 const parts=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Warsaw',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(d).map(p=>[p.type,p.value]));
 return parts.year+'-'+parts.month+'-'+parts.day+'T'+parts.hour+':'+parts.minute;
}
function reservationField(value){return field('date','Termin (czas polski)',value,'datetime-local','required min="'+earliestReservation()+'"')+'<p class="muted">W panelu możesz wpisać godzinę poza okienkami portalu. Wizyta trwa 3 godziny; minimum godzinę do przodu.</p>';}
async function checkoutVisit(id){
 await refresh();const v=data.visits.find(v=>Number(v.rowid)===Number(id));if(!v||v.status!=='planned')throw new Error('Wizyta nie jest już planowana. Sprawdź jej status w historii.');const c=data.clients.find(c=>Number(c.id)===Number(v.fk_soc)),d=data.dogs.find(d=>Number(d.id)===Number(v.fk_dog));if(!c||!d)throw new Error('Brak dostępu do klienta lub aktywnej kartoteki psa tej wizyty.');activeClient=c;activeDog=d;clearCart();plannedVisit=Number(v.rowid);showView('pos');goToStage('service');
}
function blockCheckoutForm(id,draft={clientId:0,dogId:0}){
 if(!canWrite())return;
 const block=(data.blocks||[]).find(b=>b.id===id);
 if(!block)throw new Error('Odśwież terminarz — blokada nie jest już dostępna.');
 const clients=data.clients;
 const dogs=data.dogs.filter(d=>Number(d.clientId)===Number(draft.clientId));
 if(!dogs.some(d=>Number(d.id)===Number(draft.dogId)))draft.dogId=0;
 modal('Rozlicz zablokowany termin','<p style="grid-column:1/-1">Termin: '+esc(block.date.slice(0,16))+'. Wybierz klienta i psa. Blokada zmieni się w wizytę, potem wybierzesz usługi i płatność.</p>'+selectField('blockClient','Klient',[[0,'Wybierz klienta'],...clients.map(c=>[c.id,c.name]),['__new_client','+ Dodaj klienta']],draft.clientId)+selectField('blockDog','Pies',[[0,draft.clientId?'Wybierz psa':'Najpierw wybierz klienta'],...dogs.map(d=>[d.id,d.name]),['__new_dog','+ Dodaj psa']],draft.dogId),async values=>{
  const dog=data.dogs.find(d=>Number(d.id)===Number(values.blockDog)&&Number(d.clientId)===Number(values.blockClient));
  if(!dog)throw new Error('Wybierz klienta i jego psa.');
  const result=await api('blockvisit',{id,dogId:dog.id});await checkoutVisit(result.id);
  message('Termin przypisany do wizyty. Wybierz usługi i zakończ sprzedaż.');
 });
 $('modalSave').textContent='Przypisz i przejdź do usług';
 $('f_blockClient').addEventListener('change',()=>{
  const value=$('f_blockClient').value;
  if(value==='__new_client'){
   clientForm(0,{onSaved:clientId=>{draft.clientId=clientId;draft.dogId=0;blockCheckoutForm(id,draft);}});
   modalBack=()=>blockCheckoutForm(id,draft);
  }else{draft.clientId=Number(value);draft.dogId=0;blockCheckoutForm(id,draft);}
 });
 $('f_blockDog').addEventListener('change',()=>{
  const value=$('f_blockDog').value;
  if(value==='__new_dog'){
   if(!draft.clientId){$('modalError').textContent='Najpierw wybierz lub dodaj klienta.';$('f_blockDog').value='0';return;}
   dogForm(0,{clientId:draft.clientId,onSaved:(dogId,clientId)=>{draft.clientId=clientId;draft.dogId=dogId;blockCheckoutForm(id,draft);}});
   modalBack=()=>blockCheckoutForm(id,draft);
  }else draft.dogId=Number(value);
 });
}
async function blockForm(from=today()){
 if(!canWrite())return;
 const result=await api('blockslots',undefined,{from}),slots=result.day.slots.filter(s=>s.available),key=crypto.randomUUID();
 const summary=result.day.visitCount===0?'Brak wizyt w tym dniu.':'Liczba wizyt w tym dniu: '+result.day.visitCount+'.';
 const blocks=result.day.blockCount?' Zablokowane okienka: '+result.day.blockCount+'.':'';
 const guidance=result.day.closed?'Dzień poza grafikiem portalu — możesz wpisać własną godzinę.':slots.length?'Wybierz standardowe okienka lub wpisz własną godzinę.':'Brak dostępnych standardowych okienek — możesz wpisać własną godzinę. Sprawdzimy zajętość i godzinę wyprzedzenia przy zapisie.';

 modal('Zablokuj terminy',field('blockDay','Dzień',from,'date','required min="'+today()+'"')+field('manualBlock','Albo wpisz własną godzinę (blokada 3 godziny)','','time')+'<p class="muted">Zaznacz okienka albo wpisz własną godzinę. Własna godzina zastępuje zaznaczone okienka. Każda blokada trwa 3 godziny.</p>'+slots.map((s,i)=>'<label class="payment-check"><input type="checkbox" name="block_'+i+'"> '+esc(s.start)+'–'+esc(s.end)+'</label>').join('')+'<p class="muted" style="grid-column:1/-1">'+esc(summary+blocks)+'<br>'+esc(guidance)+'</p>',async()=>{
  const manual=$('f_manualBlock').value;
  const dates=manual?[from+' '+manual+':00']: slots.filter((s,i)=>$('modalFields').querySelector('[name="block_'+i+'"]').checked).map(s=>s.date);
  if(!dates.length)throw new Error('Zaznacz okienko lub wpisz własną godzinę.');
  await api('block',{dates,manual:!!manual,requestKey:key});await refresh();message('Wybrane terminy zostały zablokowane.');
 });
 $('f_blockDay').addEventListener('change',async e=>{const day=e.target.value;if(!day)return;try{await blockForm(day);}catch(err){$('modalError').textContent=err.message;}});
}
let findingAppointment=false;
async function planForm(selectedDate){
  if(!canWrite()){message('Brak uprawnień do dodawania wizyt.',true);return;}
  if(findingAppointment)return;
  findingAppointment=true;
  try{
    message('Szukam najbliższego wolnego terminu…');
    const next=await api('nextappointment',undefined,{from:selectedDate||''});
    const draft={clientId:Number(activeClient?.id||data.clients[0]?.id||0),dogId:0,date:next.date?next.date.replace(' ','T').slice(0,16):'',notes:'',availabilityNotice:next.message};
    planDraftForm(draft);
    message(next.message,!next.date);
  }finally{findingAppointment=false;}
}
function planDraftForm(draft){
  const dogs=data.dogs.filter(d=>Number(d.clientId)===Number(draft.clientId));
  if(!dogs.some(d=>Number(d.id)===Number(draft.dogId)))draft.dogId=Number(dogs[0]?.id||0);
  const clients=[[0,'Wybierz klienta'],...data.clients.map(c=>[c.id,c.name]),['__new_client','+ Dodaj klienta']];
  const choices=[[0,'Wybierz psa'],...dogs.map(d=>[d.id,d.name]),['__new_dog','+ Dodaj psa']];
  modal('Nowa wizyta',selectField('clientId','Klient',clients,draft.clientId)+selectField('dogId','Pies tego klienta',choices,draft.dogId)+reservationField(draft.date)+(draft.availabilityNotice?'<p class="muted">'+esc(draft.availabilityNotice)+'</p>':'')+noteField('notes','Uwagi',draft.notes),async values=>{
    const dog=data.dogs.find(d=>Number(d.id)===Number(values.dogId)&&Number(d.clientId)===Number(values.clientId));
    if(!dog)throw new Error('Wybierz klienta i jego psa albo dodaj ich z listy.');
    await api('plan',{dogId:dog.id,date:values.date,notes:values.notes});await refresh();message('Termin zapisany.');
  });
  const remember=()=>{draft.date=$('f_date').value;draft.notes=$('f_notes').value;};
  $('f_clientId').addEventListener('change',()=>{
    remember();const value=$('f_clientId').value;
    if(value==='__new_client'){
      clientForm(0,{onSaved:id=>{draft.clientId=id;draft.dogId=0;planDraftForm(draft);}});
      modalBack=()=>planDraftForm(draft);
    }else{draft.clientId=Number(value);draft.dogId=0;planDraftForm(draft);}
  });
  $('f_dogId').addEventListener('change',()=>{
    remember();const value=$('f_dogId').value;
    if(value==='__new_dog'){
      if(!draft.clientId){$('modalError').textContent='Najpierw wybierz lub dodaj klienta.';$('f_dogId').value='0';return;}
      dogForm(0,{clientId:draft.clientId,onSaved:(id,owner)=>{draft.clientId=owner;draft.dogId=id;planDraftForm(draft);}});
      modalBack=()=>planDraftForm(draft);
    }else draft.dogId=Number(value);
  });
}
function expenseForm(){modal('Dodaj koszt',field('date','Data',today(),'date','required')+field('document','Numer dokumentu','','text','required maxlength="128"')+selectField('clientId','Kontrahent z kartoteki',[[0,'Wpisany ręcznie'],...data.clients.map(c=>[c.id,c.name])],0)+field('supplier','Dostawca','','text','required maxlength="255"')+field('category','Kategoria','','text','required maxlength="128"')+field('amount','Kwota brutto (zł)','','number','required min="0.01" step="0.01"')+noteField('description','Opis')+noteField('note','Opis własny'),async values=>{await api('expense',values);await refresh();message('Koszt zapisany.');});}
function acceptSale(result){
  if(!Number.isInteger(Number(result.id))||Number(result.id)<=0)throw new Error('Serwer nie potwierdził numeru wizyty. Dane pozostają do sprawdzenia.');
  // Remove the saved recovery record first; a storage failure leaves the request recoverable.
  sessionStorage.removeItem(boot.storageKey);pendingSale=null;$('retrySale').hidden=true;$('retryConfirmedSale').hidden=true;$('discardPendingSale').hidden=true;
  lastReceipt=Number(result.id);cart=[];plannedVisit=0;activeDog=null;activeClient=null;requestKey=crypto.randomUUID();renderCart();
  saving=false;goToStage('client');$('lastReceiptButton').hidden=false;
  $('issueInvoice').checked=false;$('issueInvoice').disabled=false;$('lastDocumentButton').hidden=!result.documentId;message('Zapisano wizytę nr '+lastReceipt+(result.documentNumber?' i dokument '+result.documentNumber:'')+'. Kwota: '+money(result.total)+'. Potwierdzenie 80 mm jest gotowe do wydruku.');
}
async function checkPendingSale(){
  if(saving)return;if(!pendingSale){$('retrySale').hidden=true;return;}
  saving=true;$('retrySale').disabled=true;$('retryConfirmedSale').hidden=true;
  try{
    const r=await api('salestatus',undefined,{requestKey:pendingSale.requestKey});
    if(r.state==='saved'){acceptSale(r);await refresh();}
    else if(r.state==='aborted'){clearAbortedSale();await refresh();}
    else if(r.state==='absent'){$('discardPendingSale').hidden=!canWrite();$('retryConfirmedSale').hidden=!canWrite();message('Brak potwierdzonego zapisu tej operacji w bazie. Dane zachowano. Możesz ponowić zapis tej samej wizyty. Jeśli pojawi się błąd, pozostanie widoczny poniżej.',true);}
    else message('Nie można jeszcze potwierdzić wyniku. Dane zachowano; sprawdź ponownie za chwilę.',true);
  }catch(e){message('Nie udało się sprawdzić zapisu: '+e.message+' Dane wizyty pozostają zachowane.',true);}
  finally{saving=false;$('retrySale').disabled=false;}
}
async function finishSale(){
  if(saving)return;if(!canWrite()){message('Brak uprawnień do zapisu. Oczekująca operacja pozostaje zachowana.',true);return;}
  if(!pendingSale&&(!activeClient||!activeDog||!cart.length)){message('Wybierz klienta, psa i co najmniej jedną usługę.',true);return;}
  saving=true;$('finishSale').disabled=true;$('retryConfirmedSale').disabled=true;
  try{
    if(!pendingSale)pendingSale={clientId:activeClient.id,dogId:activeDog.id,visitId:plannedVisit,items:cart.map(s=>({id:s.id,price:s.price})),payment,received:$('paymentReceived').checked,issueInvoice:$('issueInvoice').checked,requestKey};
    $('issueInvoice').checked=pendingSale.issueInvoice===true;$('issueInvoice').disabled=true;
    sessionStorage.setItem(boot.storageKey,JSON.stringify(pendingSale));
    const result=await api('sale',pendingSale);acceptSale(result);await refresh();
  }catch(e){$('retrySale').hidden=!pendingSale;$('retryConfirmedSale').hidden=true;message('Nie potwierdzono zapisu: '+e.message+(pendingSale?' Dane zachowano. Użyj „Sprawdź wynik zapisu” — sprawdzenie nie tworzy nowej wizyty.':''),true);}
  finally{saving=false;$('finishSale').disabled=!canWrite();$('retryConfirmedSale').disabled=false;}
}
async function printReceipt(id=lastReceipt){
  if(!id){message('Najpierw zapisz wizytę.',true);return;}
  const r=await api('receipt',undefined,{id}),d=r.document,seller=d?.seller||{name:r.config.salon,address:r.config.address},paid=r.payment?.received;
  const lines=d?d.lines.map(l=>({name:l.name,qty:l.qty/1000,unit:l.unit,total:l.total})):r.lines.map(l=>({name:l.service_name,qty:Number(l.qty),unit:'usł.',total:Math.round(Number(l.total_price)*100)}));
  $('receipt').innerHTML='<h2>'+esc(seller.name)+'</h2><p>'+esc(seller.address)+(seller.nip?'<br>NIP: '+esc(seller.nip):'')+'</p><h3>Potwierdzenie wizyty #'+Number(r.visit.rowid)+'</h3>'+(d?'<p><b>Dokument sprzedaży: '+esc(d.number)+'</b><br>Data sprzedaży: '+esc(d.saleDate)+'<br>Klient: '+esc(d.buyer?.nom)+'</p>':'<p>'+esc(r.visit.visit_date)+'</p>')+'<table>'+lines.map(l=>row([esc(l.name)+'<br>'+esc(l.qty)+' '+esc(l.unit),money(l.total)])).join('')+'</table><p><b>Razem: '+money(d?d.total:Math.round(Number(r.visit.amount_total)*100))+'</b><br>Metoda płatności: '+esc(r.visit.payment_type)+'<br>'+(paid?'Opłacono: '+esc(r.payment.date):'Do zapłaty: '+money(d?d.total:Math.round(Number(r.visit.amount_total)*100)))+'</p><p>Potwierdzenie niefiskalne'+(d?' — wydruk do dokumentu '+esc(d.number):'')+'</p><p>Dziękujemy 🐾</p>';
  window.print();
}
function exportCsv(kind){
  const r=reportData();let rows;
  if(kind==='expenses')rows=[['Data','Dokument','Dostawca','Kategoria','Opis','Kwota brutto'],...r.expenses.map(e=>[e.expense_date,e.document_no,e.supplier,e.category,e.description,e.amount_gross])];
  else rows=[['ID wizyty','Data wizyty','Klient','Pies','Kwota','Metoda płatności','Otrzymana','Data wpłaty'],...r.visits.map(v=>[v.rowid,v.visit_date,v.client,v.dog,v.amount_total,v.payment_type,v.paymentState.received?'Tak':'Nie',v.paymentState.date||''])];
  const cell=v=>{let s=String(v??'');if(/^[\s]*[=+\-@]/.test(s))s="'"+s;return '"'+s.replace(/"/g,'""')+'"';};
  const blob=new Blob(['\uFEFF'+rows.map(r=>r.map(cell).join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=`puchaty-${kind}-${today()}.csv`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
document.addEventListener('click',async event=>{
  const b=event.target.closest('button');if(!b||b.disabled||saving)return;
  try{
    if(pendingSale&&(b.dataset.client||b.dataset.dog||b.dataset.service||b.dataset.remove!==undefined||b.dataset.checkout||b.dataset.blockCheckout||b.dataset.pay)){await checkPendingSale();if(pendingSale)return;}
    if(b.dataset.view)showView(b.dataset.view);
    if(b.dataset.archiveKind){archiveForm(b.dataset.archiveKind,Number(b.dataset.archiveId));return;}
    if(b.dataset.restoreKind){await api('restore',{kind:b.dataset.restoreKind,id:Number(b.dataset.restoreId)});await refresh();message('Kartoteka przywrócona.');return;}
    if(b.dataset.client){activeClient=data.clients.find(c=>Number(c.id)===Number(b.dataset.client));activeDog=null;plannedVisit=0;clearCart();goToStage('dog');}
    if(b.dataset.dog){activeDog=data.dogs.find(d=>Number(d.id)===Number(b.dataset.dog));plannedVisit=0;clearCart();goToStage('service');}
    if(b.dataset.service)addToCart(b.dataset.service);
    if(b.dataset.remove!==undefined){cart.splice(Number(b.dataset.remove),1);requestKey=crypto.randomUUID();renderCart();}
    if(b.dataset.editVisit)editVisit(Number(b.dataset.editVisit));
    if(b.dataset.blockDate){await blockForm(b.dataset.blockDate);return;}
    if(b.dataset.blockCheckout){blockCheckoutForm(b.dataset.blockCheckout);return;}
    if(b.dataset.unblock){const id=b.dataset.unblock;modal('Zwolnij termin','<p>Okienko ponownie będzie dostępne do rezerwacji.</p>',async()=>{await api('unblock',{id});await refresh();message('Termin zwolniony.');});return;}
    if(b.dataset.planDate)await planForm(b.dataset.planDate);
    if(b.dataset.calendarShift)shiftCalendar(Number(b.dataset.calendarShift));
    if(b.dataset.editDog)dogForm(b.dataset.editDog);
    if(b.dataset.pay){payment=b.dataset.pay;document.querySelectorAll('.pay').forEach(x=>x.classList.toggle('active',x===b));}
    if(b.dataset.receipt)await printReceipt(Number(b.dataset.receipt));
    if(b.dataset.checkout)await checkoutVisit(Number(b.dataset.checkout));
    if(b.dataset.cancel){const id=Number(b.dataset.cancel);modal('Odwołaj wizytę','<p>Wizyta pozostanie w historii ze statusem „Anulowana”.</p>',async()=>{await api('cancel',{id});await refresh();message('Wizyta odwołana. Pozostaje w historii.');});}
    if(b.dataset.paid){const id=Number(b.dataset.paid);modal('Zarejestruj otrzymaną wpłatę',field('date','Data otrzymania',today(),'date','required'),async values=>{await api('paid',{id,...values});await refresh();message('Wpłata zarejestrowana.');});}
    const action=b.dataset.action;
    if(action==='calendar-today'){$('calendarDate').value=today();salonCalendarDay=today();renderCalendar();}
    if(action==='block'){await blockForm();return;}
    if(action==='dog')dogForm();if(action==='plan')await planForm();if(action==='expense')expenseForm();
    if(action==='client')clientForm();
    if(b.dataset.editClient)clientForm(Number(b.dataset.editClient));
    if(action==='close-modal'){if($('modalSave').disabled)return;if(modalBack)modalBack();else $('pzModal').close();}
    if(action==='refresh'){await refresh();message('Dane odświeżone.');}
    if(action==='last-receipt')await printReceipt();
    if(action==='export-sales')exportCsv('sales');if(action==='export-expenses')exportCsv('expenses');
    if(action==='install'){b.disabled=true;try{await api('install',{});location.reload();}catch(e){b.disabled=false;throw e;}}
  }catch(e){message(e.message,true);}
});
$('modalForm').addEventListener('submit',async e=>{e.preventDefault();if(!modalSubmit||$('modalSave').disabled)return;$('modalSave').disabled=true;try{const result=await modalSubmit(Object.fromEntries(new FormData(e.target)));if(result!==false)$('pzModal').close();}catch(err){$('modalError').textContent=err.message;}finally{$('modalSave').disabled=false;}});
$('settingsForm').addEventListener('submit',async e=>{e.preventDefault();const b=e.target.querySelector('button');b.disabled=true;try{const values=Object.fromEntries(new FormData(e.target)),prices={};data.config.services.forEach(s=>prices[s.id]=values['price_'+s.id]);await api('settings',{...values,prices});await refresh();message('Ustawienia zapisane.');}catch(err){message(err.message,true);}finally{b.disabled=!boot.manage;}});
$('posClientSearch').addEventListener('input',e=>data&&renderPosClients(e.target.value));
$('clientListSearch').addEventListener('input',()=>{if(data){renderClients();renderArchives();}});
$('serviceSearch').addEventListener('input',e=>data&&renderServices(e.target.value));
['visitFrom','visitTo'].forEach(id=>$(id).addEventListener('change',()=>data&&renderVisits()));
['reportFrom','reportTo'].forEach(id=>$(id).addEventListener('change',()=>data&&renderReports()));
function tick(){$('clock').textContent=new Date().toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'});}tick();setInterval(tick,1000);
(async()=>{
  $('calendarDate').value=today();
  $('reportFrom').value=today().slice(0,4)+'-01-01';$('reportTo').value=today();
  document.querySelectorAll('[data-write]').forEach(e=>e.hidden=!canWrite());
  if(!boot.admin)$('installDb').hidden=true;
  try{const status=await api('status');if(!status.ready){$('setupNotice').hidden=false;$('appPanels').hidden=true;return;}await refresh();$('appPanels').hidden=false;if(pendingSale){$('retrySale').hidden=false;message('Poprzedni zapis wymaga sprawdzenia. Ponów tę samą operację, aby uniknąć duplikatu.',true);}else message('');}catch(e){message(e.message,true);}
})();

function calendarIso(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
function calendarStart(){const d=new Date(($('calendarDate').value||today())+'T12:00:00');if($('calendarMode').value==='week')d.setDate(d.getDate()-((d.getDay()+6)%7));return d;}
function shiftCalendar(direction){const d=calendarStart();d.setDate(d.getDate()+direction*($('calendarMode').value==='week'?7:1));$('calendarDate').value=calendarIso(d);renderCalendar();}
function editVisit(id){const v=data.visits.find(x=>Number(x.rowid)===id);if(!v||v.status!=='planned'||!canWrite())return;modal('Edytuj termin wizyty',reservationField(v.visit_date.slice(0,16).replace(' ','T'))+noteField('notes','Uwagi',v.notes),async values=>{await api('reschedule',{id,...values});await refresh();message('Termin wizyty zaktualizowany.');});}
let salonCalendarDay=null;
function salonSelectDay(iso){
 salonCalendarDay=iso;
 document.querySelectorAll('#calendarBoard .calendar-day').forEach(n=>n.classList.toggle('salon-active-day',n.dataset.salonDay===iso));
 document.querySelectorAll('[data-salon-select-day]').forEach(n=>n.setAttribute('aria-pressed',String(n.dataset.salonSelectDay===iso)));
}
function renderCalendar(){
 const mode=$('calendarMode').value,board=$('calendarBoard'),tabs=$('calendarMobileDays');board.hidden=mode==='list';tabs.hidden=mode!=='week';$('visitList').parentElement.open=mode==='list';if(mode==='list'){$('calendarRange').textContent='Lista według filtrów Od / Do';return;}
 const start=calendarStart(),days=mode==='week'?7:1,html=[],tabHtml=[],dates=[];board.className='calendar-board'+(days===1?' single':'');
 for(let i=0;i<days;i++){
  const d=new Date(start);d.setDate(d.getDate()+i);const iso=calendarIso(d);dates.push(iso);
  const visits=data.visits.filter(v=>v.visit_date.slice(0,10)===iso&&v.status!=='cancelled').sort((a,b)=>a.visit_date.localeCompare(b.visit_date));
  const blocks=(data.blocks||[]).filter(b=>b.date.slice(0,10)===iso).sort((a,b)=>a.date.localeCompare(b.date));
  const blockHtml=blocks.map(b=>'<article class="calendar-event salon-block"><strong>'+esc(b.date.slice(11,16))+' · Zablokowane</strong><p>'+esc(b.by)+'</p>'+(canWrite()?'<button class="btn soft" data-block-checkout="'+esc(b.id)+'">Rozlicz ↗</button> <button class="btn ghost" data-unblock="'+esc(b.id)+'">Zwolnij termin</button>':'')+'</article>').join('');
  const weekday=esc(d.toLocaleDateString('pl-PL',{weekday:'short'}));
  tabHtml.push('<button type="button" class="salon-mobile-day" data-salon-select-day="'+iso+'" aria-label="'+esc(d.toLocaleDateString('pl-PL',{weekday:'long',day:'numeric',month:'long'}))+', wizyty: '+visits.length+'"><span>'+weekday+'</span><strong>'+d.getDate()+'</strong><small>'+visits.length+'</small></button>');
  const events=visits.map(v=>'<article class="calendar-event '+visitStatusClass(v.status)+'"><div class="salon-event-top"><time class="salon-event-time">'+esc(v.visit_date.slice(11,16))+'</time>'+visitStatusBadge(v.status)+'</div><strong class="salon-event-dog">'+esc(v.dog)+'</strong><div class="salon-event-client">'+esc(v.client)+'</div>'+(v.notes?'<p class="salon-event-notes">'+esc(v.notes)+'</p>':'')+(canWrite()&&v.status==='planned'?'<div class="salon-event-actions"><button class="btn soft" data-checkout="'+Number(v.rowid)+'">Rozlicz ↗</button><button class="btn ghost" data-edit-visit="'+Number(v.rowid)+'">Edytuj termin</button><button class="btn ghost" data-cancel="'+Number(v.rowid)+'">Odwołaj wizytę</button></div>':'')+'</article>').join('');
  html.push('<section data-salon-day="'+iso+'" class="calendar-day'+(visits.length?'':' calendar-day-empty')+'"><div class="salon-day-heading"><h4><span>'+weekday+'</span><b class="'+(iso===today()?'salon-today':'')+'">'+d.getDate()+'</b><small>'+esc(d.toLocaleDateString('pl-PL',{month:'short'}))+'</small></h4><span class="salon-day-count">Wizyty: '+visits.length+'</span></div>'+events+blockHtml+(visits.length||blocks.length?'':'<p class="calendar-empty"><span aria-hidden="true">—</span>Brak wizyt</p>')+(canWrite()?'<button class="btn ghost salon-add-visit" data-plan-date="'+iso+'">+ Dodaj wizytę</button><button class="btn ghost salon-add-visit" data-block-date="'+iso+'">Zablokuj termin</button>':'')+'</section>');
 }
 board.innerHTML=html.join('');tabs.innerHTML=tabHtml.join('');
 if(!dates.includes(salonCalendarDay))salonCalendarDay=dates.includes($('calendarDate').value)?$('calendarDate').value:dates.includes(today())?today():dates[0];
 salonSelectDay(salonCalendarDay);
 const end=new Date(start);end.setDate(end.getDate()+days-1);$('calendarRange').textContent=start.toLocaleDateString('pl-PL',{day:'numeric',month:'long'})+' — '+end.toLocaleDateString('pl-PL',{day:'numeric',month:'long',year:'numeric'});
}
$('calendarMobileDays').addEventListener('click',e=>{const button=e.target.closest('[data-salon-select-day]');if(button)salonSelectDay(button.dataset.salonSelectDay);});
['calendarMode','calendarDate'].forEach(id=>$(id).addEventListener('change',()=>{if(id==='calendarDate')salonCalendarDay=$('calendarDate').value;if(data)renderCalendar();}));

function clearAbortedSale(){$('issueInvoice').checked=false;$('issueInvoice').disabled=false;sessionStorage.removeItem(boot.storageKey);pendingSale=null;saving=false;cart=[];activeClient=null;activeDog=null;plannedVisit=0;requestKey=crypto.randomUUID();renderCart();$('retrySale').hidden=true;$('retryConfirmedSale').hidden=true;$('discardPendingSale').hidden=true;goToStage('client');message('Niezapisany koszyk został zamknięty. Możesz rozliczyć wizytę od nowa.');}
function discardPendingSale(){if(!pendingSale||saving)return;modal('Zamknij niezapisany koszyk','<p>Sprawdzimy bazę i zamkniemy tę próbę zapisu. Zapisana sprzedaż nie zostanie usunięta. Planowana wizyta pozostanie w terminarzu.</p>',async()=>{const r=await api('abortsale',{requestKey:pendingSale.requestKey});if(r.state==='saved')acceptSale(r);else if(r.state==='aborted')clearAbortedSale();else throw new Error('Nie potwierdzono zamknięcia koszyka.');await refresh();});}

function clientForm(id=0,context={}){
 const c=data.clients.find(c=>Number(c.id)===Number(id))||{};
 const html='<p class="muted" style="grid-column:1/-1">Pola z * są obowiązkowe. Pozostałe dane możesz uzupełnić później.</p>'+selectField('clientKind','Rodzaj klienta *',[['person','Osoba fizyczna bez NIP'],['company','Firma / osoba z NIP']],c.clientKind||(c.nip?'company':'person'))+field('name','Imię i nazwisko / nazwa *',c.name,'text','required maxlength="128"')+field('phone','Telefon *',c.phone,'tel','required maxlength="20"')+field('email','E-mail *',c.email,'email','required maxlength="128"')+field('town','Miejscowość',c.town,'text','maxlength="128"')+field('address','Adres',c.address)+field('zip','Kod pocztowy',c.zip)+field('nip','NIP (puste dla osoby prywatnej)',c.nip)+field('alias','Skrócona nazwa',c.alias)+field('www','Strona www',c.www)+field('contact','Osoba kontaktowa',c.contact)+noteField('notes','Notatki',c.notes);
 modal(id?'Kartoteka klienta':'Nowy klient',html,canWrite()?async values=>{const saved=await api('client',{...values,id});id=Number(saved.id);await refresh();if(activeClient){activeClient=data.clients.find(c=>Number(c.id)===Number(activeClient.id));if(activeDog)refreshSelectionUI();}message('Klient zapisany.');if(context.onSaved){context.onSaved(id);return false;}}:null);
 if($('f_clientKind')){const toggle=()=>{$('f_nip').closest('.field').hidden=$('f_clientKind').value==='person';};$('f_clientKind').addEventListener('change',toggle);toggle();}
 if(!canWrite())$('modalFields').querySelectorAll('input,select,textarea').forEach(e=>e.disabled=true);
}
$('switchUserForm').addEventListener('submit',e=>{
 if(saving){e.preventDefault();message('Poczekaj na wynik zapisu przed zmianą użytkownika.',true);return;}
 if((cart.length||pendingSale)&&!window.confirm(pendingSale?'Niepotwierdzony zapis pozostanie przypisany do tego konta. Zmienić użytkownika?':'Koszyk nie został zapisany i zostanie zamknięty. Zmienić użytkownika?')){e.preventDefault();return;}
 $('logoutToken').value=token;
});

function archiveButton(kind,id){return canWrite()?'<button class="btn danger" data-archive-kind="'+kind+'" data-archive-id="'+Number(id)+'">Usuń z listy</button>':'';}
function renderArchives(){
 for(const [kind,container,items] of [['client','clientList',data.archivedClients||[]],['dog','dogList',data.archivedDogs||[]]]){
  const host=$(container);host.querySelector('.archive-list')?.remove();
  const details=document.createElement('details');details.className='archive-list';
  details.innerHTML='<summary>Archiwum ('+items.length+')</summary>'+table(['Kartoteka','Akcja'],items.map(c=>row([esc(c.name)+(kind==='dog'?' — '+esc(c.clientName):''),canWrite()?'<button class="btn ghost" data-restore-kind="'+kind+'" data-restore-id="'+Number(c.id)+'">Przywróć</button>':''])));
  host.appendChild(details);
 }
}
function archiveForm(kind,id){
 if(saving||pendingSale){message('Najpierw sprawdź wynik zapisu lub zamknij niezapisany koszyk.',true);return;}
 const item=(kind==='client'?data.clients:data.dogs).find(x=>Number(x.id)===id);if(!item)return;
 const text=kind==='client'?'Klient i jego psy znikną z wyboru przy nowych wizytach.':'Pies zniknie z wyboru przy nowych wizytach.';
 modal('Usunąć z listy: '+item.name+'?', '<p>'+text+' Historia wizyt i rozliczeń pozostanie. Kartotekę można przywrócić z archiwum.</p>',async()=>{
  await api('archive',{kind,id});
  if((kind==='client'&&Number(activeClient?.id)===id)||(kind==='dog'&&Number(activeDog?.id)===id)){cart=[];plannedVisit=0;activeClient=null;activeDog=null;requestKey=crypto.randomUUID();renderCart();goToStage('client');}
  await refresh();message('Przeniesiono do archiwum.');
 });
 $('modalSave').textContent='Tak, usuń z listy';
}

$('pzModal').addEventListener('cancel',e=>{if($('modalSave').disabled){e.preventDefault();return;}if(modalBack){e.preventDefault();modalBack();}});
