#!/bin/bash
# ============================================================================
#  FrenchyBook-Backup: Datenbank + Cover
#  → lokal nach /var/backups/frenchybook (7 Tage)
#  → Cloudflare R2 (30 Tage), sobald /root/.frenchybook-backup.env ausgefüllt ist
#
#  Läuft täglich per Cron (/etc/cron.d/frenchybook-backup), von Hand: frenchybook-backup
#  Das Ergebnis steht in der Verwaltung (/admin) unter „Läuft alles?“.
# ============================================================================
set -uo pipefail

APP=/var/www/frenchybook.com
LOCAL=/var/backups/frenchybook
CONF=/root/.frenchybook-backup.env
STATUS=$APP/var/backup-status.json
KEEP_LOCAL_DAYS=7
KEEP_REMOTE_DAYS=30
STAMP=$(date -u +%Y-%m-%d_%H%M)
DIR=$LOCAL/$STAMP
SIZE=0
REMOTE=false

# Status für die Verwaltung schreiben (ok | local | error)
status() {
    local msg
    msg=$(printf '%s' "$2" | php -r 'echo json_encode(stream_get_contents(STDIN), JSON_UNESCAPED_UNICODE);')
    printf '{"time":"%s","result":"%s","message":%s,"size":%s,"remote":%s}\n' \
        "$(date -u +%FT%TZ)" "$1" "$msg" "$SIZE" "$REMOTE" > "$STATUS.tmp" \
        && chown www-data:www-data "$STATUS.tmp" && mv "$STATUS.tmp" "$STATUS"
}
fail() {
    echo "$(date -u +%FT%TZ) FEHLER: $1"
    status error "$1"
    exit 1
}

echo "$(date -u +%FT%TZ) Backup startet → $DIR"
mkdir -p "$DIR" && chmod 700 "$LOCAL" || fail "Backup-Ordner $LOCAL konnte nicht angelegt werden."
cd "$APP" || fail "Projektordner $APP fehlt."

# 1) Datenbank (Zugangsdaten aus der Symfony-Konfiguration)
eval "$(php -r '
    $u = parse_url((require ".env.local.php")["DATABASE_URL"]);
    foreach (["DB_USER" => rawurldecode($u["user"] ?? ""), "DB_PASS" => rawurldecode($u["pass"] ?? ""),
              "DB_HOST" => $u["host"] ?? "localhost", "DB_PORT" => (string) ($u["port"] ?? 3306),
              "DB_NAME" => ltrim($u["path"] ?? "", "/")] as $k => $v) {
        echo $k, "=", escapeshellarg($v), "\n";
    }')" || fail "Datenbank-Zugangsdaten nicht lesbar."
MYSQL_PWD="$DB_PASS" mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" --no-tablespaces --single-transaction --routines "$DB_NAME" \
    | gzip -9 > "$DIR/db.sql.gz" || fail "Datenbank-Export fehlgeschlagen."

# 2) Cover (Originale; die Vorschaubilder erzeugt die App bei Bedarf neu)
tar -czf "$DIR/covers.tar.gz" -C "$APP/public" uploads || fail "Cover konnten nicht gepackt werden."

SIZE=$(du -sb "$DIR" | cut -f1)
echo "$(date -u +%FT%TZ) Lokal gesichert: $(du -sh "$DIR" | cut -f1)"

# Alte lokale Backups aufräumen
find "$LOCAL" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_LOCAL_DAYS" -exec rm -rf {} +

# 3) Cloudflare R2
[ -f "$CONF" ] && . "$CONF"
if [ -z "${R2_ACCESS_KEY_ID:-}" ] || [ -z "${R2_SECRET_ACCESS_KEY:-}" ] || [ -z "${R2_ACCOUNT_ID:-}" ] || [ -z "${R2_BUCKET:-}" ]; then
    echo "$(date -u +%FT%TZ) R2 ist noch nicht eingerichtet ($CONF) – nur lokal gesichert."
    status local "Nur auf dem Server gesichert – Cloudflare R2 ist noch nicht eingerichtet."
    exit 0
fi

export RCLONE_CONFIG_R2_TYPE=s3
export RCLONE_CONFIG_R2_PROVIDER=Cloudflare
export RCLONE_CONFIG_R2_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID"
export RCLONE_CONFIG_R2_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"
export RCLONE_CONFIG_R2_ENDPOINT="https://$R2_ACCOUNT_ID.r2.cloudflarestorage.com"
export RCLONE_CONFIG_R2_NO_CHECK_BUCKET=true

OUT=$(rclone copy "$DIR" "r2:$R2_BUCKET/frenchybook/$STAMP" --retries 3 2>&1) \
    || fail "Upload zu Cloudflare R2 fehlgeschlagen: $(echo "$OUT" | grep -m1 -iE 'error|denied|forbidden|failed' | cut -c1-200)"
REMOTE=true
rclone delete "r2:$R2_BUCKET/frenchybook" --min-age "${KEEP_REMOTE_DAYS}d" >/dev/null 2>&1 \
    || echo "$(date -u +%FT%TZ) Hinweis: alte Backups in R2 konnten nicht gelöscht werden."

echo "$(date -u +%FT%TZ) In Cloudflare R2 hochgeladen: $R2_BUCKET/frenchybook/$STAMP"
status ok "Gesichert auf dem Server und in Cloudflare R2 ($R2_BUCKET)."
