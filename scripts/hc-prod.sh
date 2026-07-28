#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"

PUBLIC_URL="${PUBLIC_URL:-https://www.hc-jp.net}"

CORE_SCRIPT="${CORE_SCRIPT:-/usr/local/sbin/hc-audit-core.sh}"

BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "sudoで実行してください"
        exit 1
    fi
}

service_exists() {
    systemctl \
        list-unit-files \
        "${1}.service" \
        >/dev/null 2>&1
}

restart_services() {
    local found=0
    local service

    if command -v apache2ctl >/dev/null 2>&1; then
        apache2ctl configtest
    fi

    if command -v nginx >/dev/null 2>&1; then
        nginx -t
    fi

    for service in \
        php8.3-fpm \
        apache2 \
        nginx
    do
        if service_exists "$service"; then
            found=1

            systemctl enable \
                "$service" \
                >/dev/null 2>&1 || true

            systemctl restart "$service"

            if ! systemctl \
                is-active \
                --quiet \
                "$service"; then
                systemctl status \
                    "$service" \
                    --no-pager || true

                exit 1
            fi

            echo "RUNNING: ${service}"
        fi
    done

    if [ "$found" -ne 1 ]; then
        echo "ERROR: apache2/nginx/php8.3-fpmが見つかりません"
        exit 1
    fi
}

permissions() {
    mkdir -p \
        "$APP_DIR/storage/logs" \
        "$APP_DIR/storage/cache" \
        "$APP_DIR/uploads"

    find "$APP_DIR" \
        -type d \
        -exec chmod 755 {} \;

    find "$APP_DIR" \
        -type f \
        -exec chmod 644 {} \;

    chown -R \
        "$WEB_USER:$WEB_GROUP" \
        "$APP_DIR/storage" \
        "$APP_DIR/uploads"

    chmod -R \
        775 \
        "$APP_DIR/storage" \
        "$APP_DIR/uploads"

    if [ -f "$APP_DIR/.env" ]; then
        chown \
            root:"$WEB_GROUP" \
            "$APP_DIR/.env"

        chmod \
            640 \
            "$APP_DIR/.env"
    fi
}

status() {
    local service

    for service in \
        php8.3-fpm \
        apache2 \
        nginx
    do
        if service_exists "$service"; then
            printf '%s: ' "$service"

            systemctl \
                is-active \
                "$service" || true
        fi
    done

    curl \
        -k \
        -sS \
        -o /dev/null \
        -w "PUBLIC HTTP: %{http_code}\n" \
        --connect-timeout 8 \
        --max-time 20 \
        "$PUBLIC_URL/" \
        2>/dev/null || true
}

audit() {
    if [ ! -x "$CORE_SCRIPT" ]; then
        echo "ERROR: ${CORE_SCRIPT}がありません"
        exit 1
    fi

    HC_ENV_MODE="prod" \
    APP_DIR="$APP_DIR" \
    ENV_FILE="$APP_DIR/.env" \
    BASE_URL="$PUBLIC_URL" \
    "$CORE_SCRIPT"
}

deploy() {
    require_root

    if [ ! -d "$APP_DIR/.git" ]; then
        echo "ERROR: ${APP_DIR}はGitリポジトリではありません"
        exit 1
    fi

    cd "$APP_DIR"

    git fetch \
        origin \
        "$BRANCH"

    git reset \
        --hard \
        "origin/$BRANCH"

    git clean \
        -fd \
        -e .env \
        -e uploads/ \
        -e storage/

    if [ -f composer.json ] &&
       command -v composer >/dev/null 2>&1; then
        composer install \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --no-progress \
            --optimize-autoloader
    fi

    php -l "$APP_DIR/index.php"

    permissions
    restart_services
    status

    echo "DBマイグレーションは自動実行していません"
}

case "${1:-status}" in
    start|restart)
        require_root

        if [ ! -d "$APP_DIR" ]; then
            echo "ERROR: ${APP_DIR}がありません"
            exit 1
        fi

        permissions
        restart_services
        status
        ;;

    status)
        status
        ;;

    audit)
        audit
        ;;

    deploy)
        deploy
        ;;

    logs)
        if [ -f /var/log/apache2/error.log ]; then
            tail -f /var/log/apache2/error.log
        elif [ -f /var/log/nginx/error.log ]; then
            tail -f /var/log/nginx/error.log
        else
            journalctl \
                -f \
                -u apache2 \
                -u nginx \
                -u php8.3-fpm
        fi
        ;;

    *)
        echo "使い方:"
        echo "$0 {start|restart|status|audit|deploy|logs}"
        exit 2
        ;;
esac
