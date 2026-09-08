# Strefa klienta — konfiguracja Windows / Linux

Portal znajduje się pod `/custom/puchatyzakatek/portal/`. Ma własne konta i ciasteczko sesji, oddzielone od kont pracowników Dolibarra. Podgląd anonimowej dostępności jest publiczny. Logowanie jest wymagane dopiero do rezerwacji i własnych danych. Odwiedzający widzą wyłącznie godziny zajętości, bez nazwisk, psów, notatek i identyfikatorów wizyt. Własne wizyty i psy są dostępne w „Moich wizytach”.

### Publiczny terminarz — nowy wygląd

Duży widok 7 dni jest dostępny od razu, a na telefonie przełącza się dni za pomocą przycisków nad kalendarzem. Wolne godziny są miętowe, zajęte różowe, niedostępne szare. Kliknięcie wolnego terminu przez gościa otwiera okno logowania Google/e-mail i zachowuje datę na czas logowania; nie blokuje jeszcze miejsca. Rezerwację potwierdza zalogowany klient po wybraniu psa. Serwer ponownie sprawdza dostępność przy zapisie.

Interfejs korzysta z CSS Grid, responsywnych stylów, natywnego okna dialogowego i JavaScript — bez zewnętrznych CDN, fontów ani dodatkowych zależności do instalowania. Obsługuje klawiaturę i ograniczenie animacji. Publiczny endpoint `GET portal/api.php?op=calendar&from=RRRR-MM-DD` zwraca wyłącznie anonimową projekcję. `data` i operacje zapisu nadal wymagają konta. Test granicy dostępu: `php tests/portal-public-api.php` (izolowane zależności); reguły i SQL: `php tests/portal.php`.

Po `git pull --ff-only` odśwież portal; pliki CSS/JS mają nową wersję w adresie. Wzór `deploy/nginx-https.conf` kieruje teraz `/` na `/custom/puchatyzakatek/portal/`. Sam `git pull` nie zmienia konfiguracji nginx zainstalowanej na hoście: przenieś tę zmianę do aktywnej konfiguracji domeny, następnie wykonaj `sudo nginx -t` i dopiero po poprawnej walidacji `sudo systemctl reload nginx`. Panel pracowników pozostaje pod `/custom/puchatyzakatek/index.php`. Sekrety i konfiguracja godzin nie wymagają zmian.

## Pliki do samodzielnego uzupełnienia

- `portal-config.ini` — adres strony HTTPS, dni/godziny pracy, dni wolne, horyzont rezerwacji, dane Google i sekret aplikacji. Wzór: `deploy/portal-config.example.ini`.
- `mail-config.ini` — istniejąca konfiguracja SMTP. Wzór: `deploy/mail-config.example.ini`.
- `.env` — dotychczasowe dane serwera i wskazanie konfiguracji portalu.

Te trzy rzeczywiste pliki są ignorowane przez Git i Docker build. Nie umieszczaj ich w `custom/`, nie używaj `git add -f`. GitHub zawiera tylko puste wzory. Skopiuj prywatne pliki osobno na drugi serwer i dostosuj domenę oraz dane OAuth. Kopia GitHub nie zawiera klientów, wizyt ani bazy danych.

Portal jest domyślnie wyłączony. Puste godziny oznaczają dzień nieczynny. Bez konfiguracji Google klient nadal może założyć konto przez e-mail, jeśli działa SMTP.

### Windows PowerShell, w katalogu projektu

```powershell
if (!(Test-Path portal-config.ini)) { Copy-Item deploy/portal-config.example.ini portal-config.ini }
if (!(Test-Path mail-config.ini)) { Copy-Item deploy/mail-config.example.ini mail-config.ini }
notepad portal-config.ini
```

### Linux, w katalogu projektu

```bash
test -e portal-config.ini || cp deploy/portal-config.example.ini portal-config.ini
test -e mail-config.ini || cp deploy/mail-config.example.ini mail-config.ini
nano portal-config.ini
```

Ustaw prawa prywatnych plików tak, aby konfigurację mógł czytać proces PHP w kontenerze (zwykle www-data), a nie inni użytkownicy serwera. Samo `chmod 600` na pliku należącym do innego użytkownika może zablokować odczyt PHP.

## Co wpisać w portal-config.ini

1. `base_url`: pełny adres, np. `https://salon.example/custom/puchatyzakatek/portal`, bez ukośnika na końcu. Na publicznym serwerze używaj HTTPS. Reverse proxy musi kierować ten adres do kontenera Dolibarra. `DOLI_URL_ROOT` w `.env` również musi odpowiadać publicznej instalacji. Nie publikuj portu MariaDB.
2. `privacy_url`: adres własnej informacji o przetwarzaniu danych klientów (HTTPS).
3. `app_secret`: losowy sekret. Wygeneruj lokalnie poniższym poleceniem i wklej wynik do prywatnego INI. Nie zmieniaj go przy zwykłej aktualizacji.
4. `service_user_login`: istniejący aktywny pracownik z prawem zapisu modułu PZ. Używany jako autor nowej kartoteki klienta; konto klienta NIE otrzymuje praw tego pracownika.
5. Godziny `monday`…`sunday` w formacie `09:00-18:00` albo pusty tekst. Uzupełnij własne godziny. Trzy wizyty po 3 godziny wymagają 9 godzin pracy. Przy krótszym dniu będzie mniej dostępnych terminów. `closed_dates` pozwala zamknąć konkretne daty.
6. `days_ahead`: od 1 do 365. Po wpisaniu godzin ustaw `enforce_hours = true` — wymagane do uruchomienia portalu. W wyłączonym wzorze jest `false`, aby puste godziny nie blokowały dotychczasowego terminarza pracowników.

```text
docker exec puchaty-dolibarr php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

W `.env` dodaj lub zmień:

```dotenv
PZ_PORTAL_CONFIG_FILE=./portal-config.ini
```

Standardowy `compose.yml` montuje ten plik poza katalogiem WWW, pod `/run/secrets/pz-portal.ini`. Bez tej zmiennej montuje tylko wyłączony wzór. Po wymianie pliku INI odtwórz kontener, ponieważ edytor może zmienić plik przez zastąpienie jego inode.

## Logowanie e-mail i Google

**E-mail:** `email_enabled = true`. Klient wpisuje adres i dostaje 8-cyfrowy kod ważny 10 minut. Potwierdzenie tworzy konto lub loguje na istniejące konto. Kod jest jednorazowy, ma limit 5 prób; przechowujemy tylko jego HMAC. Nie ma haseł klientów ani potrzeby ich resetowania. W `mail-config.ini` wymagane są poprawne SMTP i `delivery.enabled = true`. Kody wysyłane są bezpośrednio, bez czekania na minutowy worker. Nie wysyłamy nic podczas instalacji/testu konfiguracji.

**Google:** w Google Cloud utwórz klienta OAuth typu **Web application**, skonfiguruj ekran zgody, domenę oraz odbiorców. Wpisz do prywatnego INI `google_client_id`, `google_client_secret`, ustaw `google_enabled = true`. Do Authorized redirect URIs wpisz dokładnie `base_url` z dopiskiem `/google.php`. W trybie testowym Google trzeba dodać użytkowników testowych; dostęp publiczny wymaga opublikowania aplikacji zgodnie z ustawieniami Google.

Używamy wymiany kodu OAuth po stronie serwera, stanu sesji, PKCE i tożsamości z endpointu Google przez weryfikowane HTTPS. Konta Google są wiązane po `sub`. Gmail i Workspace z potwierdzonym adresem mogą logować się bez kodu e-mail. Konta Google z adresem innego dostawcy kierujemy do weryfikacji e-mail. Istniejącego konta nie łączymy automatycznie po samym adresie: klient loguje się kodem i wybiera „Połącz Google” (ten sam adres). Nie pobieramy kalendarzy ani kontaktów Google.

Każda sesja klienta wygasa po 12 godzinach; wylogowanie dotyczy tylko strefy klienta. Portal nie honoruje sesji administratora Dolibarra. Ograniczenia prób używają REMOTE_ADDR. Przy reverse proxy skonfiguruj po stronie serwera zaufane przekazywanie adresu klienta; bez tego limit może być wspólny dla wszystkich klientów za proxy. Aplikacja nie ufa dowolnemu nagłówkowi X-Forwarded-For.

## Uruchomienie — oba systemy

Uzupełnij konfigurację, ustaw `portal.enabled = true`. Zachowaj prywatne pliki przy `git pull`; jeżeli Git zgłosi konflikt z lokalnie zmienionym Compose, scal montowania zamiast nadpisywać plik.

```text
docker compose -f compose.yml -f compose.portal.yml config --quiet
docker compose -f compose.yml -f compose.portal.yml up -d --no-deps --force-recreate dolibarr
docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/check-portal.php
```

Nakładka `compose.portal.yml` montuje rzeczywiste konfiguracje portalu i poczty, nie nadpisuje ich. Pliki muszą istnieć wcześniej. Przy kolejnych odtworzeniach kontenera używaj tych samych dwóch `-f`; jeśli poczta jest już trwale zamontowana w lokalnym Compose i ustawiono `PZ_PORTAL_CONFIG_FILE` w `.env`, wystarczy zwykłe `docker compose up -d`.

`check-portal.php` sprawdza konfigurację, bazę i pracownika, bez wysyłania maili oraz zapisywania kont. Faktyczne logowanie Google i dostarczanie kodów wymagają osobnego testu na docelowej domenie i własnej skrzynce. Błąd konfiguracji jest zapisywany do dziennika; publiczna odpowiedź nie ujawnia sekretów.

Powiadomienia o rezerwacji/odwołaniu korzystają z dotychczasowej kolejki i ustawień poczty. Worker pocztowy nadal musi działać co minutę. Kody logowania działają niezależnie od przełącznika wiadomości o rezerwacji.

## Rezerwacje i istniejące kartoteki

- 180 minut na psa, maksymalnie 3 wizyty dziennie w całym salonie/firmie, co najmniej 60 minut wyprzedzenia. Statusy planowana i rozliczona zajmują miejsce, odwołana zwalnia je.
- Portal proponuje trzygodzinne bloki od godziny otwarcia. Personel może wybrać inny początek, jeśli całe 3 godziny mieszczą się w godzinach i nie nakładają się na inne wizyty. Dane z wcześniejszych wersji nie są przesuwane ani usuwane.
- Personel i portal używają tej samej blokady transakcyjnej `config/main`, więc sprawdzenie dostępności i zapis są wykonywane razem. Ponowienie rezerwacji z tym samym identyfikatorem zwraca poprzedni wynik zamiast tworzyć duplikat.
- Limity 3 h / 3 psy obowiązują także personel bez uruchomienia portalu. Godziny pracy obowiązują, jeśli zamontowano konfigurację z `enforce_hours=true`. Rozliczenie istniejącej wizyty nie tworzy nowego miejsca; sprzedaż bez wybranej wizyty sprawdza dostępność bieżącego terminu.
- Klient może odwołać własną planowaną wizytę do jej rozpoczęcia. Zmiany kartoteki i terminu może zgłosić salonowi; portal nie udostępnia notatek personelu.
- Nowe konto nie otrzymuje cudzej kartoteki na podstawie nazwiska lub e-maila. Po pierwszym logowaniu, przed wypełnieniem nowego profilu, obsługa może po sprawdzeniu tożsamości połączyć je z istniejącym klientem:

```text
docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/portal-account.php link klient@example.com 123
```

`123` to rzeczywiste ID klienta. Narzędzie działa wyłącznie z konsoli; odmawia przepięcia konta już powiązanego z inną kartoteką i przypisania kartoteki zajętej przez inne konto. Nie scala historii automatycznie.

```text
docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/portal-account.php disable klient@example.com
docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/portal-account.php enable klient@example.com
```

## Testy deweloperskie

`php tests/portal.php` (rozszerzenia pdo_sqlite i mbstring) testuje na pustej bazie w pamięci: kolizje, limit dzienny, granice czasu, dni wolne, ponowienia, dostęp tylko do własnego psa/wizyty, anonimowość, kody jednorazowe, tożsamości Google i ograniczenia prób. Test nie używa produkcyjnej bazy ani SMTP. SQLite nie zastępuje testu współbieżności MariaDB ani pełnego testu logowania na docelowej instalacji.
