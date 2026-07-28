#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
ADMINER_URL="${ADMINER_URL:-http://127.0.0.1:8081}"
WEB_SERVICE="${WEB_SERVICE:-web}"
DB_SERVICE="${DB_SERVICE:-db}"
DB_USER="${DB_USER:-hc_user}"
DB_NAME="${DB_NAME:-hc_account}"
WAIT_SECONDS="${WAIT_SECONDS:-60}"

cd "$APP_DIR"

section() {
    printf '\n==================================================\n'
    printf '%s\n' "$1"
    printf '==================================================\n'
}

require_docker() {
    command -v docker >/dev/null 2>&1 || {
        echo "ERROR: Dockerがインストールされていません"
        exit 1
    }

    docker info >/dev/null 2>&1 || {
        echo "ERROR: Docker Desktopが起動していません"
        exit 1
    }

    docker compose version >/dev/null 2>&1 || {
        echo "ERROR: docker composeが使用できません"
        exit 1
    }

    docker compose config >/dev/null 2>&1 || {
        echo "ERROR: Docker Compose設定にエラーがあります"
        exit 1
    }
}

service_running() {
    docker compose ps \
        --status running \
        --services 2>/dev/null |
    grep -qx "$1"
}

wait_for_db() {
    local i

    for i in $(seq 1 "$WAIT_SECONDS"); do
        if docker compose exec -T "$DB_SERVICE" \
            pg_isready \
                -U "$DB_USER" \
                -d "$DB_NAME" \
                >/dev/null 2>&1; then
            echo "DB: READY"
            return 0
        fi

        sleep 1
    done

    echo "ERROR: PostgreSQLが起動しませんでした"
    docker compose logs --tail=100 "$DB_SERVICE" || true
    return 1
}

wait_for_web() {
    local i
    local status

    for i in $(seq 1 "$WAIT_SECONDS"); do
        status="$(
            curl \
                -k \
                -sS \
                -o /dev/null \
                -w '%{http_code}' \
                --connect-timeout 3 \
                --max-time 5 \
                "$BASE_URL/" 2>/dev/null || true
        )"

        case "$status" in
            200|301|302|303|307|308|401|403)
                echo "Web: READY HTTP ${status}"
                return 0
                ;;
        esac

        sleep 1
    done

    echo "ERROR: Webが起動しませんでした"
    docker compose logs --tail=100 "$WEB_SERVICE" || true
    return 1
}

start() {
    require_docker

    section "HC Platform 開発環境起動"

    mkdir -p \
        storage/logs \
        storage/cache \
        uploads

    docker compose up -d --build

    wait_for_db
    wait_for_web

    status

    echo
    echo "開発環境起動完了"
    echo "HC Platform: ${BASE_URL}"
    echo "Adminer: ${ADMINER_URL}"
}

stop() {
    require_docker

    section "HC Platform 開発環境停止"

    docker compose down
}

restart() {
    require_docker

    section "HC Platform 開発環境再起動"

    docker compose down
    docker compose up -d --build

    wait_for_db
    wait_for_web

    status
}

status() {
    require_docker

    section "HC Platform 開発環境状態"

    docker compose ps

    if service_running "$WEB_SERVICE"; then
        echo "Webコンテナ: RUNNING"
    else
        echo "Webコンテナ: STOPPED"
    fi

    if service_running "$DB_SERVICE"; then
        echo "DBコンテナ: RUNNING"
    else
        echo "DBコンテナ: STOPPED"
    fi

    curl \
        -k \
        -sS \
        -o /dev/null \
        -w "HTTP: %{http_code}\n" \
        --connect-timeout 5 \
        --max-time 10 \
        "$BASE_URL/" 2>/dev/null || true
}

logs() {
    require_docker

    docker compose logs -f
}

web_logs() {
    require_docker

    docker compose logs -f "$WEB_SERVICE"
}

db_logs() {
    require_docker

    docker compose logs -f "$DB_SERVICE"
}

shell_web() {
    require_docker

    docker compose exec "$WEB_SERVICE" bash
}

shell_db() {
    require_docker

    docker compose exec "$DB_SERVICE" \
        psql \
            -U "$DB_USER" \
            -d "$DB_NAME"
}

audit() {
    "$APP_DIR/scripts/hc-dev-audit.sh"
}

case "${1:-start}" in
    start)
        start
        ;;

    stop)
        stop
        ;;

    restart)
        restart
        ;;

    status)
        status
        ;;

    logs)
        logs
        ;;

    web-logs)
        web_logs
        ;;

    db-logs)
        db_logs
        ;;

    shell-web)
        shell_web
        ;;

    shell-db)
        shell_db
        ;;

    audit)
        audit
        ;;

    *)
        echo "使い方:"
        echo "$0 start"
        echo "$0 stop"
        echo "$0 restart"
        echo "$0 status"
        echo "$0 logs"
        echo "$0 web-logs"
        echo "$0 db-logs"
        echo "$0 shell-web"
        echo "$0 shell-db"
        echo "$0 audit"
        exit 2
        ;;
esac
