#!/usr/bin/env sh
# =============================================================================
# backup-db.sh
# -----------------------------------------------------------------------------
# Daily Postgres backup. Streams pg_dump | gzip and either:
#   * uploads to s3://${BACKUP_BUCKET}/db/$(date +%F).sql.gz   (when BACKUP_BUCKET set)
#   * writes to storage/backups/$(date +%F).sql.gz             (fallback)
#
# Env vars (all sourced from Laravel .env via the container):
#   DB_HOST           Postgres host                 (default: db)
#   DB_PORT           Postgres port                 (default: 5432)
#   DB_DATABASE       Database name                 (required)
#   DB_USERNAME       Database user                 (required)
#   DB_PASSWORD       Database password             (required)
#   BACKUP_BUCKET     S3 bucket name (optional — falls back to local storage)
#   BACKUP_S3_PREFIX  Key prefix inside bucket      (default: db)
#   AWS_*             Standard AWS SDK env vars (region, key, secret, ...)
#
# Exits non-zero if pg_dump OR upload fails. Logs every line with a UTC timestamp.
# =============================================================================

set -eu

log() {
    printf '%s [backup-db] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*"
}

die() {
    log "ERROR: $*"
    exit 1
}

# --- Resolve config ----------------------------------------------------------

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKUP_S3_PREFIX="${BACKUP_S3_PREFIX:-db}"

[ -n "$DB_DATABASE" ] || die "DB_DATABASE is not set"
[ -n "$DB_USERNAME" ] || die "DB_USERNAME is not set"

STAMP="$(date -u +%F)"
FILENAME="${STAMP}.sql.gz"

# --- Dependency check --------------------------------------------------------

command -v pg_dump >/dev/null 2>&1 || die "pg_dump not found in PATH (install postgresql-client in the image)"
command -v gzip    >/dev/null 2>&1 || die "gzip not found in PATH"

# --- Dispatch: S3 or local ---------------------------------------------------

export PGPASSWORD="$DB_PASSWORD"

if [ -n "${BACKUP_BUCKET:-}" ]; then
    command -v aws >/dev/null 2>&1 || die "BACKUP_BUCKET is set but aws CLI not found in PATH"

    DEST="s3://${BACKUP_BUCKET}/${BACKUP_S3_PREFIX}/${FILENAME}"
    log "Streaming pg_dump of '${DB_DATABASE}' to ${DEST}"

    # Pipe pg_dump | gzip | aws s3 cp - <dest>. `set -o pipefail` would be
    # ideal but POSIX sh lacks it; instead we tee both exit codes via temp file.
    PIPESTATUS_FILE="$(mktemp)"
    {
        pg_dump --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
                --no-owner --no-privileges --format=plain "$DB_DATABASE" \
            || echo "pg_dump=$?" >> "$PIPESTATUS_FILE"
    } | gzip -c | aws s3 cp - "$DEST" \
        || die "aws s3 cp failed for ${DEST}"

    if [ -s "$PIPESTATUS_FILE" ]; then
        ERR="$(cat "$PIPESTATUS_FILE")"
        rm -f "$PIPESTATUS_FILE"
        die "pg_dump failed: ${ERR}"
    fi
    rm -f "$PIPESTATUS_FILE"

    log "Uploaded ${DEST}"
else
    # Prefer the standard Laravel storage path; otherwise resolve relative to
    # the script location so the script also works when run from the host.
    if [ -d "/var/www/html/storage" ]; then
        BACKUP_DIR="/var/www/html/storage/backups"
    else
        SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
        BACKUP_DIR="${SCRIPT_DIR}/../../storage/backups"
    fi
    mkdir -p "$BACKUP_DIR" || die "Could not create ${BACKUP_DIR}"
    # Normalise (collapses the `../..` for cleaner log output)
    BACKUP_DIR="$(cd "$BACKUP_DIR" && pwd)"

    DEST="${BACKUP_DIR}/${FILENAME}"
    log "Streaming pg_dump of '${DB_DATABASE}' to ${DEST} (BACKUP_BUCKET unset)"

    pg_dump --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
            --no-owner --no-privileges --format=plain "$DB_DATABASE" \
        | gzip -c > "$DEST" \
        || die "pg_dump | gzip failed for ${DEST}"

    if [ ! -s "$DEST" ]; then
        die "Backup file ${DEST} is empty"
    fi

    log "Wrote ${DEST} ($(wc -c < "$DEST") bytes)"
fi

unset PGPASSWORD
log "Backup complete: ${FILENAME}"
exit 0
