'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const node = (tag, text, cls) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; };
  let status, customer, pending = null, pendingKey = '', busy = false;
  const message = text => { $('message').textContent = text; };
  const dateLabel = day => new Date(day + 'T12:00:00Z').toLocaleDateString('pl-PL', { weekday: 'short', day: 'numeric', month: 'long', timeZone: 'Europe/Warsaw' });
  const addDays = (day, n) => { const d = new Date(day + 'T12:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
  async function api(op, data, query = '') {
    const response = await fetch('api.php?op=' + op + query, data === undefined ? { cache: 'no-store' } : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-PZ-CSRF': status.csrf }, body: JSON.stringify(data) });
    let result; try { result = await response.json(); } catch { throw new Error('Nie potwierdzono odpowiedzi serwera. Odśwież dane przed ponowieniem zapisu.'); }
    if (!response.ok) { const error = new Error(result.error || 'Nie udało się wykonać operacji.'); error.status = response.status; throw error; }
    return result;
  }
  async function run(fn) {
    if (busy) return; busy = true;
    const buttons = Array.from(document.querySelectorAll('button')).filter(b => !b.disabled); buttons.forEach(b => b.disabled = true);
    try { await fn(); } catch (e) { message(e.message); } finally { busy = false; buttons.filter(b => b.isConnected).forEach(b => b.disabled = false); if (status?.authenticated) { $('previous').disabled = $('from').value <= status.today; $('next').disabled = addDays($('from').value, 7) > $('from').max; } }
  }
  function remember() { if (pending) sessionStorage.setItem(pendingKey, JSON.stringify(pending)); else sessionStorage.removeItem(pendingKey); }
  function pendingView() {
    $('booking').hidden = !pending;
    if (!pending) return;
    $('bookingText').textContent = `${pending.dogName} · ${dateLabel(pending.date.slice(0, 10))}, ${pending.date.slice(11, 16)} · 3 godziny`;
    $('confirmBooking').textContent = pending.attempted ? 'Sprawdź / ponów tę samą rezerwację' : 'Rezerwuję wizytę';
  }
  async function calendar() {
    const response = await api('calendar', undefined, '&from=' + encodeURIComponent($('from').value));
    $('calendar').replaceChildren();
    for (const day of response.days) {
      const card = node('article', undefined, 'day'); card.append(node('h3', dateLabel(day.date)));
      if (day.closed) card.append(node('p', 'Salon nieczynny', 'muted'));
      else if (day.full) card.append(node('p', 'Komplet — 3 psy', 'badge busy'));
      for (const occupied of day.busy) card.append(node('p', `${occupied.start}–${occupied.end} · Zajęte`, 'busy-block busy'));
      for (const slot of day.slots) {
        const b = node('button', `${slot.start}–${slot.end} · ${slot.available ? 'Wolne' : 'Niedostępne'}`, 'slot ' + (slot.available ? 'free' : 'unavailable'));
        b.disabled = !slot.available;
        b.addEventListener('click', () => {
          if (busy) return;
          if (pending?.attempted) return message('Najpierw sprawdź wynik poprzedniej rezerwacji, używając przycisku pod kalendarzem.');
          const dog = customer.dogs.find(d => String(d.id) === $('dogs').value);
          if (!dog) return message('Uzupełnij dane klienta i wybierz swojego psa.');
          pending = { requestKey: crypto.randomUUID(), dogId: Number(dog.id), dogName: dog.name, date: slot.date, attempted: false };
          remember(); pendingView(); $('booking').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        card.append(b);
      }
      $('calendar').append(card);
    }
    $('previous').disabled = $('from').value <= status.today;
    $('next').disabled = addDays($('from').value, 7) > $('from').max;
  }
  async function refresh() {
    customer = await api('data'); $('who').textContent = customer.profile?.name || customer.email;
    $('profileCard').hidden = !!customer.profile; $('showDog').hidden = !customer.profile;
    const selected = $('dogs').value; $('dogs').replaceChildren(new Option('Wybierz psa', ''));
    for (const d of customer.dogs) $('dogs').add(new Option(d.name, d.id));
    if (customer.dogs.some(d => String(d.id) === selected)) $('dogs').value = selected;
    else if (customer.dogs.length) $('dogs').value = customer.dogs[0].id;
    $('visits').replaceChildren();
    if (!customer.visits.length) $('visits').append(node('p', 'Nie masz jeszcze umówionych wizyt.', 'muted'));
    const labels = { planned: 'Zarezerwowana', completed: 'Rozliczona', cancelled: 'Odwołana' };
    for (const v of customer.visits) {
      const row = node('article', undefined, 'visit'), details = node('div');
      details.append(node('strong', v.dog), node('p', `${dateLabel(v.date.slice(0, 10))} · ${v.date.slice(11, 16)}`), node('span', labels[v.status] || v.status, 'badge ' + (v.status === 'planned' ? 'busy' : v.status === 'completed' ? 'free' : 'unavailable')));
      row.append(details);
      if (v.status === 'planned') {
        const cancel = node('button', 'Odwołaj wizytę', 'outline');
        cancel.addEventListener('click', () => { if (confirm(`Odwołać wizytę ${v.dog}, ${v.date.slice(0, 16)}?`)) run(async () => { await api('cancel', { id: v.id }); await refresh(); message('Wizyta została odwołana.'); }); });
        row.append(cancel);
      }
      $('visits').append(row);
    }
    await calendar();
  }
  for (const id of ['email', 'verify', 'profile', 'dog']) $(id).addEventListener('submit', e => {
    e.preventDefault(); run(async () => {
      const result = await api(id, Object.fromEntries(new FormData(e.target)));
      if (id === 'email') { $('verify').hidden = false; message(result.message); $('verify').elements.code.focus(); }
      else if (id === 'verify') location.reload();
      else { if (id === 'dog') { $('dog').reset(); $('dog').hidden = true; } await refresh(); message('Dane zapisane.'); }
    });
  });
  $('logout').addEventListener('click', () => run(async () => { await api('logout', {}); location.reload(); }));
  $('showDog').addEventListener('click', () => { $('dog').hidden = !$('dog').hidden; });
  $('refresh').addEventListener('click', () => run(refresh));
  $('from').addEventListener('change', () => run(calendar));
  $('previous').addEventListener('click', () => run(async () => { $('from').value = [$('from').min, addDays($('from').value, -7)].sort().pop(); await calendar(); }));
  $('next').addEventListener('click', () => run(async () => { $('from').value = [addDays($('from').value, 7), $('from').max].sort()[0]; await calendar(); }));
  $('cancelBooking').addEventListener('click', () => {
    if (pending?.attempted) return message('Wynik zapisu nie jest jeszcze potwierdzony. Użyj „Sprawdź / ponów tę samą rezerwację”.');
    pending = null; remember(); pendingView();
  });
  $('confirmBooking').addEventListener('click', () => run(async () => {
    if (!pending) return; pending.attempted = true; remember(); pendingView();
    let result;
    try { result = await api('book', pending); }
    catch (e) { if (e.status === 400) { pending.attempted = false; remember(); pendingView(); } throw e; }
    pending = null; remember(); pendingView(); await refresh(); message(`Wizyta nr ${result.id} została zapisana. Znajdziesz ją w „Moich wizytach”.`);
  }));
  async function init() {
    status = await api('status'); $('privacy').href = status.privacyUrl;
    $('login').hidden = status.authenticated; $('customer').hidden = !status.authenticated;
    $('google').hidden = !status.google; $('linkGoogle').hidden = !status.google;
    $('email').hidden = !status.email;
    document.querySelectorAll('input[name="csrf"]').forEach(n => n.value = status.csrf);
    if (status.authenticated) {
      $('from').value = status.today; $('from').min = status.today; $('from').max = addDays(status.today, status.daysAhead);
      await refresh(); pendingKey = 'pz-customer-booking:' + customer.email;
      try { pending = JSON.parse(sessionStorage.getItem(pendingKey)); } catch { pending = null; }
      pendingView();
    }
  }
  init().catch(e => message(e.message));
})();
