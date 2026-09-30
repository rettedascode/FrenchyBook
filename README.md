# FrenchyBook 📚

Unsere gemeinsame Bibliothek: Jeder trägt seine Bücher ein, man sieht auf einen Blick, **wem welches Buch gehört**, **wer es gerade hat** und **seit wann** – und kann sich Bücher mit einem Klick gegenseitig ausleihen.

- Bücher per **Barcode-Scan** (Handykamera) oder ISBN hinzufügen – Titel, Autor, Cover usw. kommen automatisch von [Open Library](https://openlibrary.org)
- Kachel-Ansicht mit großen Covern, Suche, Filter-Chips und farbigen Status-Badges
- Ausleihen eintragen, Anfragen stellen/annehmen/ablehnen, Verlauf pro Buch
- „Meine Übersicht“ mit offenen Anfragen, verliehenen und geliehenen Büchern
- E-Mail-Erinnerung bei überfälligen Büchern (Cronjob)
- Mobile first, Dark Mode automatisch, Seitenwechsel ohne Neuladen (Turbo)
- **Zweisprachig: Deutsch und Französisch** – jede Person wählt ihre Sprache, E-Mails kommen in der Sprache der Empfängerin/des Empfängers

**Technik:** Symfony 7.4 LTS · PHP 8.3+ · Doctrine ORM + Migrations · SQLite (umstellbar auf MySQL/PostgreSQL) · Twig · Symfony UX Turbo/Stimulus · AssetMapper (kein Node-Build) · LiipImagineBundle · ZXing

---

### Bibliothèque Frenchie de Cologne

- **Mitglieder** registrieren sich mit Vorname, Nachname und **Kölner Stadtbezirk** (ungefährer Abholort). Ältere Konten ohne diese Angaben sehen auf der Übersicht einen Hinweis.
- Jedes Mitglied kann **bis zu 20 Bücher** einstellen (`Book::MAX_PER_OWNER`), mit Autor, Titel, Erscheinungsjahr, Genre, **Auszeichnungen**, **Kurzrezension** und **1–5 Sternen**.
- **Status** jedes Buchs: „Abholung bei Eigentümer“, „Aktuell ausgeliehen“ oder „Zur Rückgabe fällig“ (Termin in ≤ 3 Tagen oder überschritten). Die Buchseite zeigt Eigentümer, aktuellen Besitzer und den Bezirk, in dem das Buch gerade ist.
- **Verlängern:** Wer ein Buch hat, verlängert selbst um 14 Tage, höchstens 2-mal und nur, solange niemand auf der Warteliste steht. Der Eigentümer bekommt einen Push.
- **Warteliste:** Bei ausgeliehenen Büchern kann man sich eintragen. Nach der Rückgabe wird die erste Person per E-Mail und Push benachrichtigt. Fragt sie an oder bekommt sie das Buch, verschwindet ihr Eintrag.
- **Benachrichtigungen:** neue Ausleihe (E-Mail + Push), 2 Tage vor dem Rückgabetermin, bei Überfälligkeit (wöchentlich) und wenn ein Buch von der Warteliste frei wird. Den Versand übernimmt der tägliche Cron `app:send-reminders` (`--before=2`, `--interval=7`).

## 1. Voraussetzungen

- **PHP 8.3 oder neuer** mit den Erweiterungen `pdo_sqlite` (bzw. `pdo_mysql`/`pdo_pgsql`), `gd` (mit WebP-Unterstützung), `intl`, `fileinfo`, `mbstring`, `exif` (empfohlen, damit Handyfotos richtig gedreht werden)
- [Composer](https://getcomposer.org)
- [Symfony CLI](https://symfony.com/download) für den lokalen Server

Prüfen:

```bash
symfony check:requirements
php -r "var_dump(gd_info()['WebP Support']);"   # sollte true sein
```

## 2. Installation

```bash
git clone <repo-url> frenchybooks
cd frenchybooks
composer install
```

Eigene Einstellungen gehören in eine Datei **`.env.local`** (wird nicht eingecheckt):

```dotenv
# Geheimer Schlüssel – einmal zufällig erzeugen, z. B. mit: php -r "echo bin2hex(random_bytes(16));"
APP_SECRET=hier-ein-langer-zufallswert

# Optional: Nur wer diesen Code kennt, kann sich registrieren
INVITE_CODE=Bücherwurm

# E-Mail-Versand (für Anfragen & Erinnerungen), siehe Abschnitt 6
MAILER_DSN=null://null
MAILER_FROM="FrenchyBook <noreply@example.com>"
```

## 3. Datenbank einrichten

Standardmäßig wird **SQLite** verwendet – es ist nichts weiter zu installieren, die Datenbank liegt in `var/data_dev.db`.

```bash
php bin/console doctrine:migrations:migrate
```

Die Migrationen legen alle Tabellen und eine Auswahl an Standard-Genres an.

### Auf MySQL/MariaDB oder PostgreSQL umstellen

Nur die `DATABASE_URL` in `.env.local` ändern – die Migrationen sind datenbankunabhängig geschrieben:

```dotenv
DATABASE_URL="mysql://user:passwort@127.0.0.1:3306/frenchybooks?serverVersion=8.0.32&charset=utf8mb4"
# oder
DATABASE_URL="postgresql://user:passwort@127.0.0.1:5432/frenchybooks?serverVersion=16&charset=utf8"
```

Dann:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## 4. Beispieldaten laden (Fixtures)

Zum Ausprobieren gibt es 5 Nutzer, 15 Bücher mit Covern, Ausleihen (eine davon überfällig), Verlauf und offene Anfragen:

```bash
php bin/console doctrine:fixtures:load
```

> ⚠️ Das **löscht alle vorhandenen Daten** und Cover! Nur lokal verwenden.

Angemeldet wird mit dem **Benutzernamen** (Groß-/Kleinschreibung egal), nicht mit der E-Mail-Adresse:

| Benutzername | Passwort | Hinweis |
|--------------|----------|---------|
| Jeremy | `frenchy123` | |
| Lena   | `frenchy123` | |
| Max    | `frenchy123` | |
| Sophie | `frenchy123` | nutzt die App auf Französisch |
| Tom    | `frenchy123` | |

Die Cover werden von Open Library geladen. Ohne Internet (oder mit `FIXTURES_OFFLINE=1`) werden schlichte Cover lokal erzeugt:

```bash
FIXTURES_OFFLINE=1 php bin/console doctrine:fixtures:load
```

### Einladungscode

Ist ein Einladungscode gesetzt, kann sich nur registrieren, wer den Code kennt. Alle Mitglieder sehen ihn im Profil.
Am einfachsten verwaltest du ihn in der **Verwaltung** unter `/admin` (neuer Code, eigener Code, abschalten). Alternativ per Befehl:

```bash
php bin/console app:invite-code            # neuen Code erzeugen – der alte ist sofort ungültig
php bin/console app:invite-code --show     # aktuellen Code anzeigen
php bin/console app:invite-code --set=lesekreis2026   # eigenen Code festlegen
php bin/console app:invite-code --off      # Code-Pflicht abschalten (jeder kann sich registrieren)
```

Der Code steht in der Datenbank (Tabelle `app_setting`). Solange dort noch keiner gespeichert ist, gilt `INVITE_CODE` aus `.env.local`.
Bereits registrierte Nutzer sind von einem neuen Code nicht betroffen.

## 5. Lokal starten

```bash
symfony serve
```

Dann <http://127.0.0.1:8000> öffnen.

- Der Symfony-Server liest automatisch die `php.ini` im Projektordner. Sie erhöht das Upload-Limit auf 8 MB, weil Handyfotos größer als die PHP-Standardgrenze von 2 MB sein können.
- **Am Handy testen:** Handy und Rechner ins selbe WLAN, dann `symfony serve --allow-all-ip` und die IP des Rechners aufrufen. Der **Kamera-Scan funktioniert nur über HTTPS** (oder `localhost`), siehe [Abschnitt 8](#8-deployment-mit-https). Zum schnellen Testen hilft ein Tunnel wie `cloudflared tunnel --url http://127.0.0.1:8000` oder `ngrok http 8000`, der eine HTTPS-Adresse liefert.

## 6. E-Mails

FrenchyBook verschickt E-Mails bei neuen Anfragen (an den Besitzer), bei Zu- oder Absage (an den Anfragenden), als Erinnerung bei überfälligen Büchern und für **„Passwort vergessen“** (Link zum Zurücksetzen, 1 Stunde gültig, nur einmal nutzbar). Alle Mails gehen in der Sprache der Empfängerin/des Empfängers raus.

Solange kein Mailversand eingerichtet ist (`MAILER_DSN=null://null`), wird der Link „Passwort vergessen?“ auf der Login-Seite automatisch ausgeblendet.

> **Hetzner-Hinweis:** Hetzner sperrt bei neuen Servern ausgehende Verbindungen auf Port 25 und 465. Nutze deshalb einen SMTP-Anbieter über **Port 587** (z. B. Brevo, Mailjet, SMTP2GO oder das Postfach deines Mail-Anbieters) statt eines eigenen Mailservers.

Das Ziel wird über `MAILER_DSN` eingestellt:

```dotenv
# Nichts verschicken (Standard)
MAILER_DSN=null://null
# SMTP-Anbieter / Postfach (Port 587). Sonderzeichen im Passwort URL-kodieren (z. B. @ → %40)
MAILER_DSN=smtp://benutzer:passwort@smtp.example.com:587
MAILER_FROM="FrenchyBook <noreply@deine-domain.de>"
```

Lokal zum Mitlesen eignet sich [Mailpit](https://mailpit.axllent.org): `MAILER_DSN=smtp://127.0.0.1:1025`.
Kann eine Mail nicht verschickt werden, wird das nur protokolliert. Die eigentliche Aktion klappt trotzdem.

### Logo als Absenderbild

- **BIMI (Yahoo, AOL, laposte.net, Fastmail u. a.):** DNS-Eintrag `default._bimi` (TXT) mit `v=BIMI1; l=https://frenchybook.com/bimi.svg;`. Voraussetzung: SPF und DKIM passen, und DMARC steht auf `p=quarantine` oder `p=reject`. Gmail und Apple zeigen BIMI-Logos nur mit einem kostenpflichtigen Marken-Zertifikat (VMC/CMC).

## 7. Cronjob für Erinnerungen

Der Befehl `app:send-reminders` schickt allen, deren Ausleihe überfällig ist, eine freundliche E-Mail. Pro Ausleihe wird **höchstens alle 7 Tage** erinnert, damit niemand täglich eine Mail bekommt.

```bash
php bin/console app:send-reminders --dry-run        # nur anzeigen, nichts verschicken
php bin/console app:send-reminders                   # verschicken
php bin/console app:send-reminders --interval=3      # alle 3 Tage erinnern
```

Täglich um 9 Uhr per Cron (`crontab -e` auf dem Server):

```cron
0 9 * * * cd /var/www/frenchybooks && php bin/console app:send-reminders --env=prod >> var/log/reminders.log 2>&1
```

Damit Links in den Mails stimmen, in `.env.local` die öffentliche Adresse setzen:

```dotenv
DEFAULT_URI=https://buecher.example.com
```

## 8. Deployment mit HTTPS

HTTPS ist Pflicht: Browser erlauben den Kamerazugriff (Barcode-Scan) nur auf sicheren Seiten.

### Einfachster Weg: kleiner Server mit Caddy

[Caddy](https://caddyserver.com) holt die HTTPS-Zertifikate (Let's Encrypt) automatisch.

1. **Code auf den Server bringen** (z. B. nach `/var/www/frenchybooks`) und installieren:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **`.env.local` anlegen** mit mindestens:

   ```dotenv
   APP_ENV=prod
   APP_SECRET=<langer-zufallswert>
   DEFAULT_URI=https://buecher.example.com
   INVITE_CODE=<euer-code>
   MAILER_DSN=smtp://...
   MAILER_FROM="FrenchyBook <noreply@buecher.example.com>"
   ```

   Optional für etwas mehr Tempo: `composer dump-env prod`.

3. **Datenbank, Assets und Cache vorbereiten:**

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   php bin/console asset-map:compile
   php bin/console cache:clear
   ```

4. **Schreibrechte** für den Webserver-Nutzer (meist `www-data`):

   ```bash
   mkdir -p public/uploads/covers public/media/cache
   chown -R www-data:www-data var public/uploads public/media
   ```

5. **PHP-Uploadgrenzen** in der `php.ini` von PHP-FPM setzen (oder die mitgelieferte `public/.user.ini` nutzen):

   ```ini
   upload_max_filesize = 8M
   post_max_size = 10M
   ```

6. **Caddyfile** (`/etc/caddy/Caddyfile`):

   ```caddyfile
   buecher.example.com {
       root * /var/www/frenchybooks/public
       encode zstd gzip

       # Cover & Thumbnails lange cachen
       @images path /uploads/* /media/cache/*
       header @images Cache-Control "public, max-age=2592000"

       php_fastcgi unix//run/php/php8.3-fpm.sock
       file_server
   }
   ```

   Danach `systemctl reload caddy`. Der DNS-Eintrag der Domain muss auf den Server zeigen.

7. **Cronjob** für die Erinnerungen einrichten (siehe Abschnitt 7).

### Updates einspielen

Der Code liegt auf GitHub (`rettedascode/FrenchyBook`), der Server holt ihn von dort. Ein Update ist ein einziger Befehl:

```bash
git add -A && git commit -m "Was sich geändert hat"
bin/deploy            # unter Windows in Git Bash
```

`bin/deploy` lädt die Änderungen zu GitHub hoch und startet auf dem Server `frenchybook-deploy` ([deploy/deploy.sh](deploy/deploy.sh)). Das Skript
1. sichert Datenbank und aktuellen Stand nach `/root/backup-frenchybook-<Datum>`,
2. holt den neuen Stand von GitHub (hochgeladene Cover und `.env.local` bleiben unberührt),
3. führt `composer install`, die Migrationen, `importmap:install`, `asset-map:compile` und `cache:clear` aus,
4. prüft, ob die Seite antwortet, und nennt sonst den Befehl zum Zurückgehen.

**Zurück auf eine ältere Version:** `bin/deploy <commit>` oder auf dem Server `frenchybook-deploy <commit>`. Die Commits zeigt `git log --oneline`.

Der Server ist nur per SSH-Schlüssel erreichbar (Kürzel `frenchybook` in `~/.ssh/config`), fail2ban sperrt Passwort-Rater.

### Backup

Jede Nacht um 2:15 UTC sichert `/usr/local/bin/frenchybook-backup` (Quelle: [deploy/frenchybook-backup.sh](deploy/frenchybook-backup.sh)) die Datenbank und alle Cover:

- **auf dem Server** unter `/var/backups/frenchybook/<Datum>/` (7 Tage),
- **in Cloudflare R2** unter `<bucket>/frenchybook/<Datum>/` (30 Tage), sobald die Zugangsdaten eingetragen sind.

Das Ergebnis steht in der Verwaltung (`/admin` → „Läuft alles?“), das Protokoll in `/var/log/frenchybook-backup.log`.

**R2 einrichten:**
1. In Cloudflare unter R2 einen Bucket `frenchybook-backups` anlegen.
2. „Manage R2 API Tokens“ öffnen und ein Token mit **Object Read & Write** nur für diesen Bucket anlegen.
3. Access Key ID und Secret Access Key auf dem Server in `/root/.frenchybook-backup.env` eintragen (nur für root lesbar).
4. Zum Testen `frenchybook-backup` einmal von Hand ausführen.

Die `.env.local` mit den Passwörtern wird bewusst nicht mitgesichert. Die Zugangsdaten lassen sich bei Bedarf neu anlegen (DB-Passwort, Brevo-Schlüssel, `app:push-keys`).

**Wiederherstellen:**

```bash
cd /var/www/frenchybook.com
gunzip -c /var/backups/frenchybook/<Datum>/db.sql.gz | mysql -u frenchybook -p frenchybook
tar -xzf /var/backups/frenchybook/<Datum>/covers.tar.gz -C public/
chown -R www-data:www-data public/uploads && rm -rf public/media/cache/*
```

Aus R2 holst du ein Backup vorher mit `rclone copy r2:frenchybook-backups/frenchybook/<Datum> /tmp/restore`. Die Zugangsdaten stehen wie im Skript als Umgebungsvariablen in `/root/.frenchybook-backup.env`.

## 9. Sprachen (Deutsch / Français)

FrenchyBook gibt es auf **Deutsch** und **Französisch**:

- **Angemeldete Nutzer** stellen ihre Sprache im **Profil** ein. Sie gilt auf allen Geräten.
- **Login und Registrierung** erscheinen automatisch in der Browsersprache; unten gibt es einen Umschalter „Deutsch · Français“. Wer sich auf Französisch registriert, bekommt direkt Französisch als Profilsprache.
- **E-Mails** (Anfragen, Zusagen, Erinnerungen) werden in der Sprache der Person verschickt, die sie bekommt.
- Mitübersetzt werden Formate (Taschenbuch → Poche), Zustände, Sprachnamen, die Standard-Genres (Krimi → Policier) und Datumsformate (26.10.2026 / 26/10/2026). Buchtitel, Autoren und selbst angelegte Genres bleiben, wie sie eingegeben wurden.
- Die URLs bleiben deutsch (`/buecher`, `/profil` …) – das stört im Alltag nicht.

### Wo liegen die Texte?

| Datei | Inhalt |
|-------|--------|
| `translations/messages+intl-icu.de.yaml` / `.fr.yaml` | alle Texte der Oberfläche und der E-Mails (ICU-Format, z. B. `{name}`, Plural mit `{count, plural, …}`) |
| `translations/validators.de.yaml` / `.fr.yaml` | Fehlermeldungen von Formularen |
| `translations/genres.fr.yaml` | französische Namen der Standard-Genres |

In Templates stehen nur Schlüssel, z. B. `{{ 'nav.books'|trans }}`. Einen Text ändern = den Wert in beiden YAML-Dateien anpassen.
Fehlende Übersetzungen findet `php bin/console debug:translation fr --only-missing`.

### Eine weitere Sprache hinzufügen (z. B. Englisch)

1. In `config/packages/translation.yaml` den Code ergänzen: `enabled_locales: ['de', 'fr', 'en']`
2. In `src/Entity/User.php` die Konstante `LOCALES` ergänzen
3. Die Dateien `messages+intl-icu.fr.yaml` und `validators.fr.yaml` nach `….en.yaml` kopieren und übersetzen (optional `genres.en.yaml`)
4. Die Sprache im Profil-Formular (`src/Form/ProfileType.php`), im Umschalter (`templates/base.html.twig`, Makro `language_switch`), in der Route (`src/Controller/LanguageController.php`) und bei den Datumsformaten (`src/Util/LocalizedDate.php`) eintragen


## 10. Als App auf dem Handy (PWA) & Push-Benachrichtigungen

FrenchyBook lässt sich wie eine App installieren – mit eigenem Symbol auf dem Startbildschirm, ohne Browserleiste:

- **Android (Chrome, Edge, Samsung):** Auf der Übersicht und im Profil erscheint „App installieren“.
- **iPhone/iPad (Safari):** Im Profil steht eine Kurzanleitung („Teilen → Zum Home-Bildschirm“). Einen Installations-Button erlaubt Apple nicht.
- **Schnellzugriffe:** Langes Drücken aufs App-Symbol → „Barcode scannen“, „Bücher“, „Meine Übersicht“.
- **Offline:** Ohne Verbindung erscheint eine freundliche Offline-Seite. Persönliche Seiten werden bewusst nicht zwischengespeichert.

**Push-Benachrichtigungen** (neue Anfragen, Zu-/Absagen, direkt eingetragene Ausleihen, Erinnerungen) aktiviert jede Person im Profil unter „App & Benachrichtigungen“ – pro Gerät, mit Test-Button. Auf dem iPhone funktionieren sie ab iOS 16.4, sobald FrenchyBook als App installiert ist.

Einrichten (einmalig, auf dem Server als root):

```bash
php bin/console app:push-keys          # erzeugt die Schlüssel (VAPID) in .env.local
php bin/console app:push-keys --show   # öffentlichen Schlüssel anzeigen
```

Ohne Schlüssel ist Push einfach ausgeblendet. Neue Schlüssel (`--force`) machen alle bestehenden Push-Abos ungültig – dann muss jede Person Push im Profil neu aktivieren.

| Datei | Zweck |
|-------|-------|
| `public/sw.js` | Service Worker: Offline-Seite, Cache für Assets und Cover, Anzeige der Push-Nachrichten. Bei Änderungen `VERSION` erhöhen. |
| `public/offline.html` | Offline-Seite (Deutsch/Französisch) |
| `src/Controller/PwaController.php` | Manifest in der Sprache des Nutzers, Push an-/abmelden, Test-Push |
| `src/Service/PushNotifier.php` | Versand an alle Geräte einer Person, räumt abgemeldete Geräte auf |
| `public/icon-*.png`, `badge-96.png`, `shortcut-*.png`, `screenshots/` | App-Symbole, Benachrichtigungs-Symbol, Schnellzugriffe, Vorschaubilder für den Installationsdialog |

## 11. Anleitung für Mitglieder

Unter **`/hilfe`** („So funktioniert FrenchyBook“) steht eine bebilderte Schritt-für-Schritt-Anleitung auf Deutsch und Französisch. Die Seite ist auch ohne Anmeldung erreichbar, man kann den Link also vorab verschicken. Die Themen: App aufs Handy, Buch eintragen, Bücher finden, ausleihen, Anfragen beantworten, direkt verleihen, Benachrichtigungen, Konto und „Wenn etwas nicht klappt“.

- Jedes Thema hat einen eigenen Link, der das Thema direkt aufklappt, z. B. `https://frenchybook.com/hilfe#ausleihen`. Weitere Anker: `app`, `buch-hinzufuegen`, `finden`, `anfragen`, `verleihen`, `benachrichtigungen`, `konto`, `probleme`.
- Die gezeigten Buttons sind Nachbauten der echten Buttons und nutzen dieselben Übersetzungen. Wird ein Button umbenannt, stimmt die Anleitung also automatisch.
- Erreichbar über das **?** oben rechts, über „Neu hier?“ auf Login und Registrierung, über die Karte „Anleitung“ im Profil und über „Wie funktioniert das?“ beim Buch hinzufügen.
- Auf der Übersicht zeigt neuen Mitgliedern die Checkliste **„Deine ersten Schritte“** (Konto, App, erstes Buch, Benachrichtigungen, erste Anfrage), was noch fehlt. Erledigtes hakt sich von selbst ab. Die Checkliste verschwindet, sobald alles erledigt ist oder man auf „Ausblenden“ tippt.
- Die Texte stehen unter `help.*` und `onboarding.*` in `translations/`, der Aufbau der Seite in `templates/help/index.html.twig`.

## 12. Verwaltung (Admin)

Unter **`/admin`** verwalten Admins die ganze Gruppe. Am Computer steht „Verwaltung“ in der Menüleiste, am Handy gibt es eine Karte im Profil.

Den ersten Admin legst du per Befehl fest, weitere kannst du danach in der Verwaltung ernennen:

```bash
php bin/console app:admin Jeremy            # zum Admin machen
php bin/console app:admin Jeremy --remove   # Rechte entziehen
php bin/console app:admin --list            # alle Admins
```

| Bereich | Was geht |
|---------|----------|
| Übersicht | Zahlen, Status von E-Mail, Push und Fehlern, Test-Mail an dich selbst, Einladungscode |
| Mitglieder | Name, E-Mail und Sprache korrigieren, Einmal-Passwort erzeugen (für alle, die mit „Passwort vergessen“ nicht klarkommen), Admin-Rechte vergeben, Mitglied samt Büchern löschen, „zuletzt angemeldet“ |
| Bücher | Alle Bücher durchsuchen, bearbeiten, löschen, Besitzer ändern (falls beim falschen Mitglied eingetragen) |
| Ausleihen | Hängende Ausleihen zurückbuchen oder löschen, offene Anfragen entfernen – ohne Benachrichtigungen |
| Fehler | Fehler der letzten 14 Tage, gleiche zusammengefasst (aus `var/log/errors-*.log`, 30 Tage aufbewahrt) |

Admins sehen auf fremden Buchseiten zusätzlich „Bearbeiten“ und „Löschen“. Die private Notiz des Besitzers sehen sie nicht. Sich selbst können Admins weder die Rechte entziehen noch löschen.

## 13. Überblick über den Code

| Ordner | Inhalt |
|--------|--------|
| `src/Entity` | `User`, `Book`, `Author`, `Genre`, `Loan`, `LoanRequest` |
| `src/Enum` | Format, Zustand, Anfrage-Status, Sprachen (mit deutschen Bezeichnungen) |
| `src/Controller` | Bücher, Ausleihen, Anfragen, Übersicht, Profil, Login/Registrierung |
| `src/Security/Voter/BookVoter.php` | Rechte: Nur der Besitzer darf bearbeiten, löschen, verleihen, Rückgaben bestätigen |
| `src/Service/OpenLibraryClient.php` | ISBN-Suche bei Open Library (Timeouts, kein Treffer, Teilausfälle) |
| `src/Service/CoverManager.php` | Cover speichern, drehen, verkleinern, aufräumen |
| `src/Service/LoanManager.php` | Regeln rund ums Verleihen (max. eine aktive Ausleihe pro Buch usw.) |
| `src/Service/LoanNotifier.php` | E-Mails zu Anfragen und Erinnerungen |
| `src/Command/SendRemindersCommand.php` | Cron-Befehl `app:send-reminders` |
| `assets/styles/app.css` | Design-System (Farben, Buttons, Karten, Badges, Chips, Dark Mode) |
| `assets/controllers` | Stimulus: Barcode-Scanner, Cover-Vorschau, Live-Suche, Flash-Meldungen |
| `templates` | Twig-Templates inkl. freundlicher Fehlerseiten und E-Mails (nur Übersetzungsschlüssel) |
| `translations` | Texte auf Deutsch und Französisch |
| `src/EventSubscriber/LocaleSubscriber.php` | wählt pro Anfrage die Sprache (Profil → Umschalter → Browser) |

### Barcode-Scan

Der Scanner nutzt den eingebauten `BarcodeDetector` des Browsers (Chrome auf Android) und fällt sonst auf die Bibliothek ZXing zurück (z. B. Safari auf dem iPhone). ZXing wird über AssetMapper lokal ausgeliefert und erst beim Scannen geladen. Akzeptiert werden nur echte Buch-Barcodes (EAN-13 mit 978/979 und gültiger Prüfziffer). Nach dem Speichern führt „Speichern & nächstes scannen“ direkt zurück zur Kamera, damit ganze Regale schnell erfasst sind.
