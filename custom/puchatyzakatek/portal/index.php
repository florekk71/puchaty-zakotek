<?php require __DIR__.'/bootstrap.php'; ?>
<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Puchaty Zakątek — Strefa klienta</title><link rel="icon" href="../img/logo-transparent.png"><link rel="stylesheet" href="portal.css"></head><body>
<header><img src="../img/logo-transparent.png" alt="Logo Puchaty Zakątek"><div><p class="eyebrow">STREFA KLIENTA</p><h1>Puchaty Zakątek</h1><p>Czas na pielęgnację Twojego pupila</p></div></header>
<main>
<p id="message" role="status" aria-live="polite"><?php echo htmlspecialchars((string)($_SESSION['pz_notice']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); unset($_SESSION['pz_notice']); ?></p>
<section id="login" class="card" hidden><h2>Witaj w Puchatym Zakątku</h2><p>Zaloguj się, aby sprawdzić dostępność i umówić wizytę. Pierwsze logowanie utworzy Twoje konto.</p>
<form id="google" method="post" action="google.php" hidden><input type="hidden" name="csrf"><button class="outline">Kontynuuj z Google</button></form>
<form id="email" hidden><label>Adres e-mail<input name="email" type="email" maxlength="128" autocomplete="email" required></label><button>Wyślij kod / załóż konto</button><p class="muted">Bez hasła. Kod jest ważny 10 minut.</p></form>
<form id="verify" hidden><label>Kod z wiadomości e-mail<input name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" required></label><button>Potwierdź i wejdź</button></form>
<p class="muted">Dane wykorzystujemy do obsługi konta i wizyt. <a id="privacy" target="_blank" rel="noopener noreferrer">Informacja o prywatności</a>.</p></section>
<div id="customer" hidden>
<nav class="card"><strong id="who"></strong><button id="logout" class="outline">Wyloguj</button><form id="linkGoogle" method="post" action="google.php" hidden><input type="hidden" name="csrf"><button class="outline">Połącz Google</button></form></nav>
<section id="profileCard" class="card" hidden><h2>Poznajmy się</h2><form id="profile"><label>Imię i nazwisko<input name="name" maxlength="128" autocomplete="name" required></label><label>Telefon<input name="phone" maxlength="20" type="tel" autocomplete="tel" required></label><button>Zapisz moje dane</button></form><p class="muted">Jeśli masz już kartotekę w salonie, poproś obsługę o połączenie jej z kontem przed dodaniem psa.</p></section>
<section class="card"><div class="section-title"><h2>Twoje psy</h2><button id="showDog" class="outline">+ Dodaj psa</button></div><label>Pies na wizytę<select id="dogs"><option value="">Najpierw uzupełnij dane</option></select></label>
<form id="dog" hidden><label>Imię psa<input name="name" maxlength="128" required></label><label>Rasa (opcjonalnie)<input name="breed" maxlength="128"></label><button>Zapisz psa</button></form></section>
<section class="card"><div class="section-title"><h2>Wybierz termin</h2><button id="refresh" class="outline">Odśwież</button></div><p>3 godziny spokojnej pielęgnacji na psa · maksymalnie 3 psy dziennie.</p><p class="muted">Rezerwacja najwcześniej godzinę do przodu. Godziny według czasu polskiego. Dane innych klientów są ukryte.</p>
<div class="controls"><button id="previous" class="outline">← Wstecz</button><label>Od dnia<input id="from" type="date"></label><button id="next" class="outline">Dalej →</button></div>
<p class="legend"><span class="free">Wolne</span><span class="busy">Zajęte</span><span class="unavailable">Niedostępne</span></p><div id="calendar" class="calendar"></div>
<div id="booking" class="confirmation" hidden><h3>Potwierdź rezerwację</h3><p id="bookingText"></p><button id="confirmBooking">Rezerwuję wizytę</button><button id="cancelBooking" class="outline">Wróć do wyboru</button></div></section>
<section class="card"><h2>Moje wizyty</h2><div id="visits"></div></section>
</div></main><footer>Puchaty Zakątek · Z troską o Twojego psa</footer><script src="portal.js" defer></script></body></html>
