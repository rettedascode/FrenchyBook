# ============================================================================
#  FrenchyBook – häufige Befehle
#  „make“ oder „make help“ zeigt alle Befehle.
#  Unter Windows läuft alles über Git Bash (egal ob aus PowerShell oder Git Bash gestartet).
# ============================================================================

ifeq ($(OS),Windows_NT)
    SHELL := C:/PROGRA~1/Git/bin/bash.exe
else
    SHELL := /bin/bash
endif
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := help

CONSOLE := php bin/console
SERVER  := frenchybook
APP_DIR := /var/www/frenchybook.com
PROD    := cd $(APP_DIR) && sudo -u www-data APP_ENV=prod php bin/console

.PHONY: help install start stop cc migrate fixtures db-reset lint test deps-update reminders \
	    deploy rollback guides-upload ssh status logs backup invite-code reminders-live admins

help: ## Diese Übersicht
	@echo "FrenchyBook - make <Befehl>"
	@awk 'BEGIN {FS = ":.*## "} /^## / {printf "\n\033[1m%s\033[0m\n", substr($$0, 4)} /^[a-z-]+:.*## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## Lokal entwickeln

install: ## Abhängigkeiten installieren (Composer + JavaScript)
	composer install
	$(CONSOLE) importmap:install

start: ## Lokalen Server starten → http://127.0.0.1:8000 (Strg+C beendet)
	symfony server:start --no-tls --port=8000

stop: ## Lokalen Server stoppen
	symfony server:stop

cc: ## Cache leeren
	$(CONSOLE) cache:clear

migrate: ## Datenbank-Migrationen ausführen
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

fixtures: ## Beispieldaten laden (Jeremy, Lena, Max, Sophie, Tom – Passwort: frenchy123)
	$(CONSOLE) doctrine:fixtures:load --no-interaction
	$(CONSOLE) app:admin Jeremy

db-reset: ## Lokale Datenbank komplett neu aufbauen (Migrationen + Beispieldaten)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(MAKE) --no-print-directory migrate fixtures

lint: ## Templates, YAML, Container und Datenbank-Schema prüfen
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config translations
	$(CONSOLE) lint:container
	$(CONSOLE) doctrine:schema:validate

test: ## PHPUnit-Tests ausführen
	php bin/phpunit

deps-update: ## Pakete aktualisieren (wie Dependabot) – danach prüfen, committen, make deploy
	composer update --no-interaction
	$(CONSOLE) importmap:outdated || true
	$(MAKE) --no-print-directory lint
	@git status --short composer.json composer.lock

reminders: ## Erinnerungen lokal testen (verschickt nichts)
	$(CONSOLE) app:send-reminders --dry-run

## Live schalten (GitHub → frenchybook.com)

deploy: ## Committeten Stand zu GitHub hochladen und live schalten
	bin/deploy

rollback: ## Ältere Version live schalten: make rollback COMMIT=abc1234
	@test -n "$(COMMIT)" || { echo "Bitte angeben: make rollback COMMIT=<commit> (siehe git log --oneline)"; exit 1; }
	bin/deploy $(COMMIT)

guides-upload: ## PDF-Anleitungen aus docs/ auf den Server (Verwaltung) und nach Cloudflare R2 laden
	@ls docs/*.pdf >/dev/null 2>&1 || { echo "Keine PDFs in docs/ gefunden."; exit 1; }
	ssh $(SERVER) 'mkdir -p $(APP_DIR)/var/guides'
	scp docs/*.pdf $(SERVER):$(APP_DIR)/var/guides/
	ssh $(SERVER) 'chown -R www-data:www-data $(APP_DIR)/var/guides && chmod 640 $(APP_DIR)/var/guides/*.pdf && frenchybook-backup --guides'

## Server

ssh: ## Auf dem Server anmelden
	ssh $(SERVER)

status: ## Live-Version, letztes Backup, gesperrte IPs, Speicherplatz
	@ssh $(SERVER) 'cd $(APP_DIR); \
	    echo "Version:   $$(git log -1 --format="%h %s (%cr)" 2>/dev/null || echo "noch nicht per Git")"; \
	    echo "Backup:    $$(cat var/backup-status.json 2>/dev/null || echo "noch keins")"; \
	    echo "fail2ban:  $$(fail2ban-client status sshd | grep "Currently banned" | tr -s "\t " " ")"; \
	    echo "Speicher:  $$(df -h / | awk "NR==2 {print \$$4\" frei von \"\$$2}")"; \
	    echo "Website:   $$(curl -s -o /dev/null -w "%{http_code}" https://frenchybook.com/anmelden)"'

logs: ## Letzte Fehler der App und von Apache anzeigen
	@ssh $(SERVER) 'cd $(APP_DIR); echo "== App-Fehler (var/log/errors-*.log):"; tail -n 20 var/log/errors-*.log 2>/dev/null || echo "keine"; \
	    echo; echo "== Apache:"; tail -n 20 /var/log/apache2/frenchybook_error.log 2>/dev/null || true'

backup: ## Backup jetzt ausführen (Server + Cloudflare R2)
	ssh $(SERVER) frenchybook-backup

invite-code: ## Aktuellen Einladungscode anzeigen
	@ssh $(SERVER) '$(PROD) app:invite-code --show'

reminders-live: ## Zeigen, wer heute erinnert würde (verschickt nichts)
	@ssh $(SERVER) '$(PROD) app:send-reminders --dry-run'

admins: ## Admins der Live-Seite anzeigen
	@ssh $(SERVER) '$(PROD) app:admin --list'
