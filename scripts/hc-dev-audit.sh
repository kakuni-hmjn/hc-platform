#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"

if [ -f "$APP_DIR/.env.audit.local" ]; then
    set -a

    # shellcheck disable=SC1091
    . "$APP_DIR/.env.audit.local"

    set +a
fi

env_value() {
    local key="$1"
    local value=""

    value="$(printenv "$key" 2>/dev/null || true)"

    if [ -z "$value" ] && [ -f "$ENV_FILE" ]; then
        value="$(
            grep -E "^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=" \
                "$ENV_FILE" 2>/dev/null |
            tail -n 1 |
            sed -E \
                "s/^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=[[:space:]]*//"
        )"
    fi

    value="${value%$'\r'}"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"

    printf '%s' "$value"
}

first_value() {
    local key
    local value

    for key in "$@"; do
        value="$(env_value "$key")"

        if [ -n "$value" ]; then
            printf '%s' "$value"
            return 0
        fi
    done

    return 1
}

DB_HOST="${HC_DB_HOST:-127.0.0.1}"

DB_NAME="${HC_DB_NAME:-$(
    first_value \
        DB_NAME \
        POSTGRES_DB \
        DATABASE_NAME \
        2>/dev/null || true
)}"

DB_USER="${HC_DB_USER:-$(
    first_value \
        DB_USER \
        POSTGRES_USER \
        DATABASE_USER \
        2>/dev/null || true
)}"

DB_PASSWORD="${HC_DB_PASSWORD:-$(
    first_value \
        DB_PASSWORD \
        DB_PASS \
        POSTGRES_PASSWORD \
        DATABASE_PASSWORD \
        2>/dev/null || true
)}"

DB_NAME="${DB_NAME:-hc_account}"
DB_USER="${DB_USER:-hc_user}"

detect_db_port() {
    local requested="${HC_DB_PORT:-}"
    local port

    if [ -n "$requested" ]; then
        printf '%s' "$requested"
        return
    fi

    for port in 5433 5432; do
        if command -v nc >/dev/null 2>&1 &&
           nc -z -w 2 "$DB_HOST" "$port" >/dev/null 2>&1; then
            printf '%s' "$port"
            return
        fi

        if command -v pg_isready >/dev/null 2>&1 &&
           pg_isready \
               -h "$DB_HOST" \
               -p "$port" \
               >/dev/null 2>&1; then
            printf '%s' "$port"
            return
        fi
    done

    printf '5433'
}

DB_PORT="$(detect_db_port)"

echo "=================================================="
echo "HC Platform 開発環境監査"
echo "Dockerコマンドは使用しません"
echo "=================================================="
echo "URL: ${BASE_URL}"
echo "DB: ${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo "DB USER: ${DB_USER}"

if [ -z "$DB_PASSWORD" ]; then
    echo
    echo "WARN: DBパスワードを.envから取得できません"
    echo "HC_DB_PASSWORDを指定する必要があります"
fi

echo
echo "[DB事前接続テスト]"

if PGPASSWORD="$DB_PASSWORD" \
   psql \
       -X \
       -h "$DB_HOST" \
       -p "$DB_PORT" \
       -U "$DB_USER" \
       -d "$DB_NAME" \
       -c "SELECT current_database(), current_user;" ; then
    echo "OK: DBへ直接接続できました"
else
    echo
    echo "ERROR: DBへ直接接続できません"
    echo
    echo "確認値:"
    echo "  HOST=${DB_HOST}"
    echo "  PORT=${DB_PORT}"
    echo "  NAME=${DB_NAME}"
    echo "  USER=${DB_USER}"
    echo
    echo ".envのPOSTGRES_PASSWORDまたはDB_PASSWORDを確認してください"
    exit 1
fi

HC_ENV_MODE="dev" \
APP_DIR="$APP_DIR" \
ENV_FILE="$ENV_FILE" \
BASE_URL="$BASE_URL" \
HC_DB_HOST="$DB_HOST" \
HC_DB_PORT="$DB_PORT" \
HC_DB_NAME="$DB_NAME" \
HC_DB_USER="$DB_USER" \
HC_DB_PASSWORD="$DB_PASSWORD" \
HC_SESSION_COOKIE="${HC_SESSION_COOKIE:-}" \
HC_ALLOW_READ_ALL_TEST="${HC_ALLOW_READ_ALL_TEST:-0}" \
"$APP_DIR/scripts/hc-audit.sh"
