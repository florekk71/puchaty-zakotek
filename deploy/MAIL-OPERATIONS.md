# Kolejka pocztowa serwera

Wiadomości są zapisywane w bazie aplikacji podczas operacji na wizycie lub dokumencie. Timer systemd `puchaty-mail.timer` uruchamia worker co minutę, również po restarcie serwera. Nie instalować równolegle zadania cron ani harmonogramu Windows.

Stan: `systemctl status puchaty-mail.timer puchaty-mail.service`

Logi: `journalctl -u puchaty-mail.service --since today`

Panel: **NDG i raporty → Poczta i kolejka**. `sent` oznacza przyjęcie przez lokalnego Exima; doręczenie zewnętrzne sprawdza się w `/var/log/exim4/mainlog`. Exim ponawia tymczasowe błędy doręczenia. Trwałe odrzucenie odbiorcy wymaga diagnozy, np. SPF/DKIM.

Blokada bazy zapobiega równoległej obsłudze, a klucze zdarzeń zapobiegają ich ponownemu kolejkowaniu. Worker wysyła maksymalnie 20 wiadomości w jednym uruchomieniu. Wiadomości przestarzałe lub bez adresu są pomijane. Stany `failed` i `unknown` wymagają sprawdzenia przed ręcznym ponowieniem; wynik niepewny nie jest automatycznie ponawiany, żeby uniknąć duplikatów. Po przekroczeniu 15 minut systemd kończy uruchomienie; pozostawiona pozycja `sending` przechodzi przy następnym przebiegu do `unknown`.

Wstrzymanie przetwarzania: `systemctl stop puchaty-mail.timer`. Dodatkowo ustawienie `[delivery] enabled = false` w `mail-config.ini` zatrzymuje tworzenie nowych powiadomień. Po podmianie pliku INI odtworzyć kontener: `docker compose up -d --no-deps --force-recreate dolibarr`.

Konfiguracja lokalnego przekaźnika: `172.24.0.1:25`, bez uwierzytelniania, dostępny z sieci aplikacji. Plik `mail-config.ini` jest montowany tylko do odczytu poza katalogiem WWW i wykluczony z Gita. Nadawca: `noreply@zakatek.topkomp.pl`.

Adres kontaktowy salonu: `biuro@puchaty-zakatek.pl`. Szablony i nagłówek Reply-To używają konfiguracji `[sender]`. Stara wartość `biuro@topkomp.pl` w polach nadawcy/odpowiedzi, odbiorcy testowego i powiadomień właściciela jest odczytywana jako nowy adres, również dla oczekujących powiadomień właściciela. Adresy klientów nie są migrowane. Login i hasło SMTP pozostają bez zmian; zmiana skrzynki używanej do uwierzytelniania wymaga uzupełnienia prywatnego `mail-config.ini` danymi nowej skrzynki. Test: `php tests/salon-email.php`.
