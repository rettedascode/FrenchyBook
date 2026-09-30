#!/bin/bash
# ============================================================================
#  FrenchyBook: neuesten Stand von GitHub holen und live schalten.
#  Liegt auf dem Server als /usr/local/bin/frenchybook-deploy.
#
#    frenchybook-deploy            neueste Version (Branch main)
#    frenchybook-deploy <commit>   bestimmte Version, z. B. zurück auf eine ältere
#    frenchybook-deploy --force    auch ausführen, wenn sich nichts geändert hat
#
#  Vorher wird automatisch gesichert (Datenbank + Stand) nach /root/backup-frenchybook-*.
# ============================================================================
set -euo pipefail

APP=/var/www/frenchybook.com
BRANCH=main
TARGET=""
FORCE=false
for arg in "$@"; do
    case "$arg" in
        --force) FORCE=true ;;
        *) TARGET="$arg" ;;
    esac
done

cd "$APP"
git fetch --quiet origin "$BRANCH"
OLD=$(git rev-parse --short HEAD)
NEW=$(git rev-parse --short "${TARGET:-origin/$BRANCH}")
if [ "$OLD" = "$NEW" ] && [ "$FORCE" = false ]; then
    echo "Schon aktuell ($OLD) – nichts zu tun. (--force erzwingt einen Durchlauf)"
    exit 0
fi

echo "==> Backup"
B=/root/backup-frenchybook-$(date +%Y%m%d-%H%M%S)
mkdir -p "$B"
echo "$OLD" > "$B/commit.txt"
eval "$(php -r '
    $u = parse_url((require ".env.local.php")["DATABASE_URL"]);
    echo "DB_USER=", escapeshellarg(rawurldecode($u["user"] ?? "")), "\n",
         "DB_PASS=", escapeshellarg(rawurldecode($u["pass"] ?? "")), "\n",
         "DB_NAME=", escapeshellarg(ltrim($u["path"] ?? "", "/")), "\n";')"
MYSQL_PWD="$DB_PASS" mysqldump -u"$DB_USER" --no-tablespaces --single-transaction "$DB_NAME" | gzip > "$B/db.sql.gz"
echo "    $B (Stand $OLD)"

echo "==> Code: $OLD → $NEW"
git log --oneline --no-decorate "$OLD..$NEW" 2>/dev/null | head -20 | sed 's/^/    /' || true
git reset --hard --quiet "$NEW"

echo "==> Abhängigkeiten, Datenbank, Assets"
export COMPOSER_ALLOW_SUPERUSER=1 APP_ENV=prod
rm -rf var/cache/prod
composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --quiet
chown -R www-data:www-data var public/uploads public/media
C="sudo -u www-data APP_ENV=prod php bin/console"
$C cache:clear --quiet
$C doctrine:migrations:migrate --no-interaction --allow-no-migration --quiet
php bin/console importmap:install --quiet
rm -rf public/assets
php bin/console asset-map:compile --quiet
chown -R www-data:www-data public/assets var
$C cache:clear --quiet

# Server-Skripte aus dem Repository aktuell halten
install -m 750 deploy/frenchybook-backup.sh /usr/local/bin/frenchybook-backup
install -m 750 deploy/deploy.sh /usr/local/bin/frenchybook-deploy

STATUS=$(curl -s -o /dev/null -w '%{http_code}' https://frenchybook.com/anmelden || true)
if [ "$STATUS" = "200" ]; then
    echo "==> Fertig: $NEW ist live (Seite antwortet mit 200)."
else
    echo "==> ACHTUNG: Die Seite antwortet mit $STATUS."
    echo "    Zurück zur vorherigen Version: frenchybook-deploy $OLD"
    exit 1
fi
