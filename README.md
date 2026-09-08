# Puchaty Zakątek — moduł Dolibarra

Wersja 0.5.5. Obsługa salonu pielęgnacji psów: klienci, kartoteki psów, terminarz, rozliczenia, faktury konsumenckie przy zwolnieniu z VAT, potwierdzenia 80 mm, magazyn i raporty z wykresami.

Repozytorium zawiera kod projektu. Nie zawiera kont użytkowników, haseł, klientów, wizyt, wystawionych dokumentów ani kopii bazy. Logo i kolorystyka salonu pozostają w kodzie. Dane sprzedawcy należy skonfigurować po instalacji lub przenieść prywatnie z bazą.

## Logo na ekranie logowania

Dockerfile instaluje logo Puchatego Zakątka zamiast logo Dolibarra i usuwa widoczny nagłówek z wersją Dolibarra. Nie zmienia mechanizmu uwierzytelniania. Po pobraniu aktualizacji uruchom `docker compose up -d --build dolibarr`, a następnie odśwież stronę Ctrl+F5.

Na dotychczasowej instalacji Windows w `C:\PuchatyZakatek` można zamiast przebudowy obrazu uruchomić z pobranego repozytorium:

```powershell
& .\deploy\install-login.ps1 -ProjectRoot C:\PuchatyZakatek
```

Skrypt robi kopię Compose i szablonu, sprawdza składnię zmienionego PHP, dodaje trwałe montowanie szablonu i odtwarza kontener Dolibarra. W razie błędu przywraca Compose i poprzedni montowany szablon. Przerywa działanie przy nieznanym układzie szablonu lub innym istniejącym montowaniu. Zmiana nie wymaga edycji bazy. Po późniejszej aktualizacji wersji Dolibarra trzeba ponownie sprawdzić zgodność szablonu; montowanie zachowuje wersję z chwili instalacji.

## Nowa instalacja na serwerze z Docker Compose

1. Pobierz lub sklonuj repozytorium i wejdź do jego katalogu.
2. Skopiuj `.env.example` do `.env`. Na Linuksie:

   ```sh
   cp .env.example .env
   chmod 600 .env
   mkdir -p data/mariadb data/documents
   ```

3. W `.env` ustaw różne, losowe hasła `MARIADB_ROOT_PASSWORD`, `MARIADB_PASSWORD`, `DOLI_ADMIN_PASSWORD` oraz losowy `DOLI_INSTANCE_UNIQUE_ID`. Możesz wygenerować każdą wartość osobno przez `openssl rand -hex 24`. Wpisz właściwy `DOLI_URL_ROOT`, bez końcowego ukośnika. Nie używaj rzeczywistych haseł w plikach śledzonych przez Git.
4. Uruchom:

   ```sh
   docker compose config --quiet
   docker compose up -d --build
   docker compose logs --tail=80 dolibarr
   ```

5. Po zakończeniu inicjalizacji zaloguj się na konto administratora podane w `.env`. Włącz moduł **Kontrahenci**, a następnie **Puchaty Zakątek** w konfiguracji modułów Dolibarra.
6. Otwórz `/custom/puchatyzakatek/index.php`. Uruchom inicjalizację tabel w module, jeśli wyświetli taki komunikat. Administrator może też wykonać:

   ```sh
   docker compose exec dolibarr php /var/www/html/custom/puchatyzakatek/scripts/install_db.php
   ```

7. W **NDG i raporty → Dane sprzedawcy** ustaw właściwe dane i profil podatkowy. Uzupełnij klientów oraz psy lub przenieś prywatną kopię poprzedniej instalacji.

Domyślnie port 8180 jest dostępny tylko lokalnie na serwerze. Do zdalnego testu można użyć tunelu SSH, np. `ssh -L 8180:127.0.0.1:8180 użytkownik@serwer`, a następnie otworzyć `http://localhost:8180`. Do stałego udostępnienia przez domenę skonfiguruj reverse proxy HTTPS i ustaw `DOLI_URL_ROOT` na tę domenę. Nie podłączaj dwóch aplikacji zapisujących równolegle do tej samej przenoszonej bazy.

Obrazy przypięto do Dolibarr 24.0.0 i MariaDB 11.8. Nie zmieniaj wersji Dolibarra bez sprawdzenia integracji ograniczenia tras. Dockerfile dodaje do jego `main.inc.php` wywołanie ochrony kont ograniczonych do modułu, w tym samym miejscu co istniejąca instalacja. Budowa przerwie się, jeśli oczekiwany punkt integracji nie zostanie znaleziony.

## Przeniesienie dotychczasowych danych

Samo pobranie projektu tworzy nową instalację. Aby zachować klientów, psy, wizyty, konta, ustawienia i faktury, przenieś osobno, prywatnym kanałem:

- pełny logiczny zrzut bazy Dolibarra z procedurami i triggerami;
- katalog dokumentów Dolibarra;
- dodatkowe moduły/indywidualne rozszerzenia, jeżeli istnieją poza tym repozytorium.

Na starej instalacji administrator ma generator kopii w **NDG i raporty → Kopie bezpieczeństwa** (PowerShell/Docker). Zatrzymaj zapisy podczas wykonania końcowej kopii i migracji. Przy odtwarzaniu zatrzymaj kontener aplikacji, pozostaw bazę uruchomioną, zaimportuj logiczny zrzut do nowej bazy i odtwórz dokumenty. Następnie uruchom aplikację. Nie kopiuj działającego katalogu plików MariaDB jako zamiennika logicznego zrzutu.

Zaimportowana baza przywraca stare konta i ich hasła — hasło `DOLI_ADMIN_PASSWORD` z `.env` nie służy do resetowania istniejącego konta. Przed przełączeniem serwera sprawdź: logowanie administratora i konta ograniczonego, dostęp do klientów/psów, terminarz, ostatnie numery dokumentów, generowanie PDF oraz potwierdzenie 80 mm. Nie wystawiaj fikcyjnej sprzedaży w docelowej bazie.

## Uprawnienia

Moduł ma prawa odczytu, zapisu i pełnej obsługi. Nie nadają one praw globalnego administratora. W dotychczasowej instalacji konta przeznaczone tylko do salonu mają wpis `PZ_ONLY_MODULE=1` w `user_param`; migracja pełnej bazy zachowuje ten wpis. Samo skopiowanie modułu do innej instalacji Dolibarra nie instaluje ochrony wszystkich tras — zapewnia ją Dockerfile w tym projekcie. Zakładanie nowych kont ograniczonych należy wykonać z poprawnymi prawami i znacznikiem; repozytorium nie tworzy automatycznie konta konkretnej osoby.

## Ograniczenia i sprawdzenie

Faktury w tym profilu dotyczą konsumentów i sprzedaży zwolnionej z VAT na podstawie art. 113. Potwierdzenie 80 mm jest niefiskalne. Przepisy i status sprzedawcy wymagają osobnej oceny; program nie jest integracją z KSeF ani kasą fiskalną. Statutowy limit NDG w implementacji jest zweryfikowany dla 2026 r.; późniejsze lata wymagają aktualizacji reguł.

Kod eksportowano z wersji zgodnej z bieżącą instalacją 0.5.5. Usunięto domyślny adres sprzedawcy. Nowy szablon wdrożenia przygotowano na podstawie [oficjalnej dokumentacji obrazu Dolibarra](https://github.com/Dolibarr/dolibarr-docker). Składnia kodu i mechanizm wstawiania ochrony tras zostały sprawdzone lokalnie; pełna budowa i pierwsze uruchomienie tej konfiguracji Docker na nowym serwerze wymagają weryfikacji.

Kod Dolibarra i MariaDB jest pobierany z obrazów upstream i podlega ich własnym licencjom. Logo jest oznaczeniem salonu Puchaty Zakątek.

## Wysyłka e-mail

Moduł kolejkuje potwierdzenie rezerwacji, zmianę terminu, odwołanie oraz fakturę PDF po potwierdzeniu wpłaty. Przypomnienia dotyczą przyszłych planowanych wizyt w przedziale `hours_before` (domyślnie 24 godziny); nie tworzą rezerwacji. Wymagany jest e-mail klienta. Faktura korzysta z adresu nabywcy utrwalonego podczas jej wystawienia. Brak adresu jest oznaczany w kolejce jako pominięcie, bez blokowania sprzedaży.

1. Zaktualizuj moduł. Skopiuj `deploy/mail-config.example.ini` jako `mail-config.ini` do głównego katalogu instalacji, poza `custom`. Uzupełnij SMTP, nadawcę i `test_recipient`; pozostaw `delivery.enabled = false`. Plik zawiera hasło i jest wykluczony z Gita oraz obrazu Docker.
2. W Windows uruchom z folderu repozytorium:

   ```powershell
   .\deploy\install-mail.ps1 -ProjectRoot "C:\PuchatyZakatek"
   docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/mail-worker.php check
   docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/mail-worker.php test
   ```

   `test` wysyła jedną wiadomość wyłącznie do `test_recipient`, także gdy automatyczna wysyłka jest wyłączona. Installer nie wysyła testu samodzielnie.
3. Po otrzymaniu testu ustaw `delivery.enabled = true` i wybrane przełączniki w `[messages]` na `true`. Uruchom ponownie instalator, aby Docker odczytał zmieniony plik. Istniejący plik z hasłem nie jest nadpisywany. Zmiany nie są zapisywane do globalnych ustawień SMTP Dolibarra.
4. Zajrzyj do **NDG i raporty → Poczta i kolejka**. Kolejka pokazuje ostatnie 100 pozycji. Nie wysyła historycznych faktur i potwierdzeń po samym uruchomieniu; nowe operacje przy włączonej konfiguracji tworzą wpisy. Przypomnienia obejmują również istniejące, nadchodzące wizyty.

Harmonogram Windows działa co minutę jako bieżący użytkownik, gdy jest zalogowany, komputer jest włączony i Docker działa. Ostatni wynik jest w `pz-mail-worker.log` w katalogu instalacji. Wyłączenie komputera lub wylogowanie zatrzymuje wysyłkę. Instalator tworzy zadanie `Puchaty-poczta-puchaty-dolibarr`.

Na serwerze Linux dodaj do wolumenów usługi `dolibarr` montowanie `./mail-config.ini:/run/secrets/pz-mail.ini:ro`, odtwórz kontener i ustaw w crontab użytkownika z dostępem do Dockera:

```cron
* * * * * /usr/bin/docker exec puchaty-dolibarr php /var/www/html/custom/puchatyzakatek/scripts/mail-worker.php run
```

Weryfikacja TLS jest wymagana. Hasło aplikacji wpisz, jeśli wymaga go dostawca. Implementacja obsługuje SMTP LOGIN, STARTTLS i TLS, nie OAuth2.

Po niepotwierdzonym wyniku SMTP pozycja ma stan **Do sprawdzenia**. SMTP nie zapewnia dokładnie jednokrotnego doręczenia: przed ręcznym ponowieniem sprawdź, czy wiadomość dotarła. System nie ponawia niepewnej wysyłki samoczynnie. Blokada bazy uniemożliwia równoległą pracę dwóch procesów w tej samej jednostce; przerwany proces pozostawia pozycję do sprawdzenia. Sukces oznacza przyjęcie przez serwer SMTP, nie gwarantuje umieszczenia w skrzynce zamiast spamu.

Dane SMTP są czytane z `/run/secrets/pz-mail.ini` (alternatywnie ścieżka `PZ_MAIL_CONFIG`). Obsługiwana jest bieżąca jednostka Dolibarra; standardowa instalacja używa jednostki 1. Kolejka znajduje się w istniejącej tabeli modułu `pz_store`, więc nie wymaga migracji tabel. Kod PDF jest wspólny dla podglądu i załącznika. Wydruk 80 mm pozostaje odrębny.

Testy lokalne obejmują kolejkę i transport zastępczy. Test z prawdziwą skrzynką wymaga uzupełnienia prywatnej konfiguracji na docelowym serwerze.
