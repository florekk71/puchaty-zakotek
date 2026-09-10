'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const node = (tag, text, cls) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; };
  const storage = { get(key) { try { return sessionStorage.getItem(key); } catch { return null; } }, set(key, value) { try { if (value === null) sessionStorage.removeItem(key); else sessionStorage.setItem(key, value); } catch { /* Browsers may disable storage; the live page still works. */ } } };
  let status, customer, pending = null, pendingKey = '', selectedDate = null, busy = false, activeDay = null, renderedFrom = null;
  const message = text => { $('message').textContent = text; $('loginMessage').textContent = $('loginModal').open ? text : ''; };
  const dateObject = day => new Date(day + 'T12:00:00Z');
  const format = (day, options) => dateObject(day).toLocaleDateString('pl-PL', { ...options, timeZone: 'Europe/Warsaw' });
  const dateLabel = day => format(day, { weekday: 'long', day: 'numeric', month: 'long' });
  const addDays = (day, n) => { const d = dateObject(day); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
  const validDate = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:00$/.test(value);
  const dog = () => customer?.dogs.find(d => String(d.id) === $('dogs').value);
  function controls() {
    if (!status) return;
    $('previous').disabled = busy || $('from').value <= status.today;
    $('next').disabled = busy || addDays($('from').value, 7) > $('from').max;
    $('confirmBooking').disabled = busy || (!pending?.attempted && (!selectedDate || !dog()));
  }
  async function api(op, data, query = '') {
    const response = await fetch('api.php?op=' + op + query, data === undefined ? { cache: 'no-store' } : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-PZ-CSRF': status.csrf }, body: JSON.stringify(data) });
    let result; try { result = await response.json(); } catch { throw new Error('Nie potwierdzono odpowiedzi serwera. Odśwież dane przed ponowieniem zapisu.'); }
    if (!response.ok) { const error = new Error(result.error || 'Nie udało się wykonać operacji.'); error.status = response.status; throw error; }
    return result;
  }
  async function run(fn) {
    if (busy) return; busy = true;
    const buttons = Array.from(document.querySelectorAll('button')).filter(b => !b.disabled); buttons.forEach(b => b.disabled = true);
    try { await fn(); } catch (e) { message(e.message); } finally { busy = false; buttons.filter(b => b.isConnected).forEach(b => b.disabled = false); controls(); }
  }
  function remember() { if (pendingKey) storage.set(pendingKey, pending ? JSON.stringify(pending) : null); }
  function saveSelection(value) { selectedDate = value; storage.set('pz-slot-intent', value); }
  function pendingView() {
    const date = pending?.date || selectedDate;
    $('booking').hidden = !date || !status?.authenticated;
    if (!date) return;
    const name = pending?.attempted ? pending.dogName : dog()?.name;
    $('bookingText').textContent = `${dateLabel(date.slice(0, 10))}, ${date.slice(11, 16)} · 3 godziny${name ? ' · ' + name : ' · Wybierz lub dodaj psa'}`;
    $('confirmBooking').textContent = pending?.attempted ? 'Sprawdź / ponów tę samą rezerwację' : 'Potwierdzam rezerwację ↗';
    controls();
  }
  function openLogin() {
    if (!status) return;
    $('loginSelection').hidden = !selectedDate;
    $('loginSelection').textContent = selectedDate ? `Wybrany termin: ${dateLabel(selectedDate.slice(0, 10))}, ${selectedDate.slice(11, 16)}. Zaloguj się, aby kontynuować.` : '';
    $('loginMessage').textContent = '';
    if (!$('loginModal').open) $('loginModal').showModal();
  }
  function choose(date) {
    if (busy) return;
    if (pending?.attempted) { message('Najpierw sprawdź wynik poprzedniej rezerwacji przyciskiem w podsumowaniu.'); $('booking').scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    pending = null; remember(); saveSelection(date);
    if (!status.authenticated) return openLogin();
    pendingView();
    (customer.profile ? $('booking') : $('profileCard')).scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function selectDay(day) {
    activeDay = day;
    document.querySelectorAll('.day').forEach(n => n.classList.toggle('mobile-active', n.dataset.day === day));
    document.querySelectorAll('.mobile-day').forEach(n => n.setAttribute('aria-pressed', String(n.dataset.day === day)));
  }
  function overlaps(slot, occupied) { return occupied.start < slot.end && (occupied.end <= occupied.start || occupied.end > slot.start); }
  async function calendar() {
    const from = $('from').value;
    if (!from || from < status.today || from > $('from').max) { $('from').value = renderedFrom || status.today; throw new Error('Wybierz dzień w dostępnym okresie rezerwacji.'); }
    let response; try { response = await api('calendar', undefined, '&from=' + encodeURIComponent(from)); } catch (e) { $('from').value = renderedFrom || status.today; throw e; }
    renderedFrom = from;
    const total = response.days.reduce((sum, day) => sum + (day.closed ? 0 : day.slots.filter(s => s.available).length), 0);
    const plural = new Intl.PluralRules('pl').select(total);
    $('availabilityCount').textContent = total ? `${total} ${plural === 'one' ? 'wolny termin' : plural === 'few' ? 'wolne terminy' : 'wolnych terminów'} łącznie` : 'Brak wolnych terminów w tym tygodniu';
    $('rangeTitle').textContent = format(from, { day: 'numeric', month: 'short' }) + ' — ' + format(addDays(from, 6), { day: 'numeric', month: 'short', year: 'numeric' });
    $('calendar').replaceChildren(); $('mobileDays').replaceChildren();
    if (!response.days.some(d => d.date === activeDay)) activeDay = response.days.find(d => !d.closed && d.slots.some(s => s.available))?.date || response.days[0]?.date;
    for (const day of response.days) {
      const free = day.closed ? 0 : day.slots.filter(s => s.available).length;
      const tab = node('button', undefined, 'mobile-day' + (free ? ' has-free' : '')); tab.dataset.day = day.date;
      tab.setAttribute('aria-label', `${dateLabel(day.date)}: ${day.closed ? 'Dzień odpoczynku' : free + ' wolnych terminów'}`);
      tab.append(node('span', format(day.date, { weekday: 'short' })), node('strong', format(day.date, { day: 'numeric' })));
      tab.addEventListener('click', () => selectDay(day.date)); $('mobileDays').append(tab);
      const card = node('article', undefined, 'day'); card.dataset.day = day.date; card.setAttribute('aria-label', dateLabel(day.date));
      const heading = node('div', undefined, 'day-heading'), left = node('div');
      left.append(node('span', format(day.date, { weekday: 'short' }), 'weekday'), node('span', format(day.date, { day: 'numeric' }), 'day-number' + (day.date === status.today ? ' today-mark' : '')), node('span', format(day.date, { month: 'short' }), 'day-month'));
      heading.append(left, node('span', free ? `${free} ${free === 1 ? 'wolny termin' : 'wolne terminy'}` : !day.closed && day.full ? 'Komplet' : '', 'day-count')); card.append(heading);
      if (day.closed || !day.slots.length) { const closed = node('div', undefined, 'closed-day'); closed.append(node('span', '—'), node('span', day.closed ? 'Dzień odpoczynku' : 'Brak terminów')); card.append(closed); }
      // Closed days take precedence over older bookings in the public calendar.
      if (day.closed) { $('calendar').append(card); continue; }
      for (const slot of day.slots) {
        const occupied = day.busy.some(b => overlaps(slot, b));
        const label = slot.available ? 'Rezerwuj wizytę' : occupied ? 'Zajęte' : day.full ? 'Limit dnia' : 'Niedostępne';
        const b = node('button', undefined, 'slot ' + (slot.available ? 'free' : occupied ? 'busy' : 'unavailable')); b.disabled = !slot.available;
        b.setAttribute('aria-label', `${dateLabel(day.date)}, ${slot.start}–${slot.end}: ${label}`);
        b.append(node('span', slot.start, 'slot-time'), node('span', 'do ' + slot.end, 'slot-end'));
        const action = node('span', undefined, 'slot-action'); action.append(node('span', label), node('b', slot.available ? '↗' : '—')); b.append(action);
        b.addEventListener('click', () => choose(slot.date)); card.append(b);
      }
      $('calendar').append(card);
    }
    selectDay(activeDay); controls();
  }
  async function loadCustomer() {
    customer = await api('data'); $('who').textContent = customer.profile?.name || customer.email;
    $('profileCard').hidden = !!customer.profile; $('showDog').hidden = !customer.profile;
    const selected = $('dogs').value; $('dogs').replaceChildren(new Option('Wybierz psa', ''));
    for (const d of customer.dogs) $('dogs').add(new Option(d.name, d.id));
    if (customer.dogs.some(d => String(d.id) === selected)) $('dogs').value = selected;
    else if (customer.dogs.length) $('dogs').value = customer.dogs[0].id;
    $('visits').replaceChildren();
    if (!customer.visits.length) $('visits').append(node('p', 'Tutaj pojawią się Twoje wizyty. Wybierz wolny termin w kalendarzu powyżej.', 'muted'));
    const labels = { planned: 'Zarezerwowana', completed: 'Rozliczona', cancelled: 'Odwołana' };
    for (const v of customer.visits) {
      const row = node('article', undefined, 'visit'), details = node('div');
      details.append(node('strong', v.dog), node('p', `${dateLabel(v.date.slice(0, 10))} · ${v.date.slice(11, 16)}`), node('span', labels[v.status] || v.status, 'badge ' + (v.status === 'planned' ? 'busy' : v.status === 'completed' ? 'free' : 'unavailable'))); row.append(details);
      if (v.status === 'planned') {
        const cancel = node('button', 'Odwołaj wizytę', 'button outline small');
        cancel.addEventListener('click', () => { if (confirm(`Odwołać wizytę ${v.dog}, ${v.date.slice(0, 16)}?`)) run(async () => { await api('cancel', { id: v.id }); await refresh(); message('Wizyta została odwołana.'); }); }); row.append(cancel);
      }
      $('visits').append(row);
    }
    pendingView();
  }
  async function refresh() { if (status.authenticated) await loadCustomer(); await calendar(); }
  for (const id of ['email', 'verify', 'passwordLogin', 'passwordSet', 'profile', 'dog']) $(id).addEventListener('submit', e => {
    e.preventDefault(); run(async () => {
      const result = await api(id, Object.fromEntries(new FormData(e.target)));
      if (id === 'email') { $('verify').hidden = false; message(result.message); $('verify').elements.code.focus(); }
      else if (id === 'verify' || id === 'passwordLogin') { if (id === 'verify') storage.set('pz-password-setup', '1'); location.reload(); }
      else if (id === 'passwordSet') { $('passwordSet').reset(); $('passwordMessage').textContent = 'Hasło zapisane. Następnym razem zalogujesz się swoim e-mailem i hasłem.'; }
      else { if (id === 'dog') { $('dog').reset(); $('dog').hidden = true; } await refresh(); message('Dane zapisane. Możesz potwierdzić wybrany termin.'); }
    });
  });
  $('useCode').addEventListener('click', () => { message(''); $('email').elements.email.value = $('passwordLogin').elements.email.value; $('passwordLogin').hidden = true; $('email').hidden = false; $('email').elements.email.focus(); });
  $('usePassword').addEventListener('click', () => { message(''); $('passwordLogin').elements.email.value = $('email').elements.email.value; $('email').hidden = true; $('verify').hidden = true; $('passwordLogin').hidden = false; $('passwordLogin').elements.password.focus(); });
  ['openLogin', 'guestLogin'].forEach(id => $(id).addEventListener('click', openLogin));
  $('closeLogin').addEventListener('click', () => $('loginModal').close());
  $('loginModal').addEventListener('click', e => { if (e.target === $('loginModal')) { const r = e.target.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close(); } });
  $('logout').addEventListener('click', () => run(async () => { await api('logout', {}); location.reload(); }));
  $('showDog').addEventListener('click', () => { $('dog').hidden = !$('dog').hidden; });
  $('dogs').addEventListener('change', () => { if (!pending?.attempted) { pending = null; remember(); } pendingView(); });
  $('refresh').addEventListener('click', () => run(refresh));
  $('from').addEventListener('change', () => run(calendar));
  $('today').addEventListener('click', () => run(async () => { $('from').value = status.today; activeDay = status.today; await calendar(); }));
  $('previous').addEventListener('click', () => run(async () => { $('from').value = [status.today, addDays($('from').value, -7)].sort().pop(); await calendar(); }));
  $('next').addEventListener('click', () => run(async () => { $('from').value = [addDays($('from').value, 7), $('from').max].sort()[0]; await calendar(); }));
  $('cancelBooking').addEventListener('click', () => { if (pending?.attempted) return message('Użyj „Sprawdź / ponów tę samą rezerwację”, zanim wybierzesz nowy termin.'); pending = null; remember(); saveSelection(null); pendingView(); $('availability').scrollIntoView({ behavior: 'smooth' }); });
  $('confirmBooking').addEventListener('click', () => run(async () => {
    if (!pending?.attempted) {
      if (!selectedDate || !dog()) throw new Error('Wybierz swojego psa i termin.');
      pending = { requestKey: crypto.randomUUID(), dogId: Number(dog().id), dogName: dog().name, date: selectedDate, attempted: false };
    }
    pending.attempted = true; remember(); pendingView();
    let result;
    try { result = await api('book', pending); }
    catch (e) { if (e.status === 400) { pending.attempted = false; remember(); pendingView(); } if (e.status === 401) { status.authenticated = false; openLogin(); } throw e; }
    pending = null; remember(); saveSelection(null); pendingView(); await refresh(); message(`Wizyta nr ${result.id} została zapisana. Do zobaczenia w salonie!`); $('visitsSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }));
  async function init() {
    status = await api('status'); $('privacy').href = status.privacyUrl; $('privacyFooter').href = status.privacyUrl;
    $('customer').hidden = !status.authenticated; $('guestNote').hidden = status.authenticated; $('openLogin').hidden = status.authenticated; $('logout').hidden = !status.authenticated; $('myVisitsLink').hidden = !status.authenticated;
    $('google').hidden = !status.google; $('linkGoogle').hidden = !status.google; $('email').hidden = true; $('passwordLogin').hidden = !status.email; $('passwordCard').hidden = !status.email; $('emailDivider').hidden = !status.google || !status.email;
    document.querySelectorAll('input[name="csrf"]').forEach(n => n.value = status.csrf);
    const intent = storage.get('pz-slot-intent'); if (validDate(intent) && intent.slice(0, 10) >= status.today && intent.slice(0, 10) <= addDays(status.today, status.daysAhead)) selectedDate = intent;
    $('from').min = status.today; $('from').max = addDays(status.today, status.daysAhead); $('from').value = selectedDate?.slice(0, 10) || status.today;
    if (status.authenticated) {
      if (storage.get('pz-password-setup')) { $('passwordSettings').open = true; storage.set('pz-password-setup', null); }
      await loadCustomer(); pendingKey = 'pz-customer-booking:' + customer.email;
      try { const stored = JSON.parse(storage.get(pendingKey)); if (stored && validDate(stored.date) && typeof stored.requestKey === 'string') { pending = stored; selectedDate = stored.date; } } catch { pending = null; }
      pendingView();
    }
    await calendar();
    if (status.authenticated && selectedDate) $('booking').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  init().catch(e => message(e.message));
})();
