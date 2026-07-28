#!/usr/bin/env bash

set -u
set -o pipefail

MODE="${HC_ENV_MODE:-dev}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
OWNER_USERNAME="${OWNER_USERNAME:-sou}"
HC_SESSION_COOKIE="${HC_SESSION_COOKIE:-}"
HC_ALLOW_READ_ALL_TEST="${HC_ALLOW_READ_ALL_TEST:-0}"

case "$MODE" in
    dev)
        BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
        DB_HOST="${HC_DB_HOST:-127.0.0.1}"
        DB_PORT="${HC_DB_PORT:-5433}"
        DB_NAME="${HC_DB_NAME:-hc_account}"
        DB_USER="${HC_DB_USER:-hc_user}"
        DB_PASSWORD="${HC_DB_PASSWORD:-}"
        ;;

    prod)
        BASE_URL="${BASE_URL:-https://www.hc-jp.net}"
        DB_HOST="${HC_DB_HOST:-}"
        DB_PORT="${HC_DB_PORT:-}"
        DB_NAME="${HC_DB_NAME:-}"
        DB_USER="${HC_DB_USER:-}"
        DB_PASSWORD="${HC_DB_PASSWORD:-}"
        ;;

    *)
        echo "ERROR: HC_ENV_MODEはdevまたはprodを指定してください"
        exit 2
        ;;
esac

PASS=0
WARN=0
FAIL=0

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/hc-audit.XXXXXX")"
trap 'rm -rf "$TMP_DIR"' EXIT

section() {
    printf '\n==================================================\n'
    printf '%s\n' "$1"
    printf '==================================================\n'
}

ok() {
    printf '✅ %s\n' "$1"
    PASS=$((PASS + 1))
}

warn() {
    printf '⚠️  %s\n' "$1"
    WARN=$((WARN + 1))
}

fail() {
    printf '❌ %s\n' "$1"
    FAIL=$((FAIL + 1))
}

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

    case "$value" in
        \"*\")
            value="${value#\"}"
            value="${value%\"}"
            ;;
        \'*\')
            value="${value#\'}"
            value="${value%\'}"
            ;;
    esac

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

check_env_group() {
    local label="$1"
    shift

    local key

    for key in "$@"; do
        if [ -n "$(env_value "$key")" ]; then
            ok "${label}: ${key}"
            return
        fi
    done

    warn "${label}: 未設定 [$*]"
}

if [ "$MODE" = "prod" ]; then
    DB_HOST="${DB_HOST:-$(first_value DB_HOST POSTGRES_HOST DATABASE_HOST 2>/dev/null || true)}"
    DB_PORT="${DB_PORT:-$(first_value DB_PORT POSTGRES_PORT DATABASE_PORT 2>/dev/null || true)}"
    DB_NAME="${DB_NAME:-$(first_value DB_NAME POSTGRES_DB DATABASE_NAME 2>/dev/null || true)}"
    DB_USER="${DB_USER:-$(first_value DB_USER POSTGRES_USER DATABASE_USER 2>/dev/null || true)}"
    DB_PASSWORD="${DB_PASSWORD:-$(first_value DB_PASSWORD DB_PASS POSTGRES_PASSWORD DATABASE_PASSWORD 2>/dev/null || true)}"
fi

DB_PORT="${DB_PORT:-5432}"

db() {
    PGPASSWORD="$DB_PASSWORD" \
    psql \
        -X \
        -v ON_ERROR_STOP=1 \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        "$@"
}

table_exists() {
    db -Atc "
        SELECT to_regclass('public.$1') IS NOT NULL;
    " 2>/dev/null
}

column_exists() {
    db -Atc "
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = '$1'
              AND column_name = '$2'
        );
    " 2>/dev/null
}

json_valid() {
    php -r '
        json_decode(
            file_get_contents($argv[1]),
            true
        );

        exit(
            json_last_error() === JSON_ERROR_NONE
                ? 0
                : 1
        );
    ' "$1" >/dev/null 2>&1
}

http_status() {
    local method="$1"
    local url="$2"
    local output="$3"
    local cookie="${4:-}"

    local args=(
        -k
        -sS
        -X "$method"
        -o "$output"
        -w "%{http_code}"
        --connect-timeout 8
        --max-time 25
        -H "Accept: application/json,text/html;q=0.9"
    )

    if [ -n "$cookie" ]; then
        args+=(
            -H "Cookie: $cookie"
        )
    fi

    curl "${args[@]}" "$url" 2>/dev/null || true
}

section "実行環境"

printf 'MODE=%s\n' "$MODE"
printf 'APP_DIR=%s\n' "$APP_DIR"
printf 'BASE_URL=%s\n' "$BASE_URL"
printf 'DB=%s:%s/%s (%s)\n' \
    "${DB_HOST:-未設定}" \
    "${DB_PORT:-未設定}" \
    "${DB_NAME:-未設定}" \
    "${DB_USER:-未設定}"

if [ -d "$APP_DIR" ]; then
    ok "アプリケーションディレクトリ"
else
    fail "APP_DIRがありません"
    exit 1
fi

if [ -f "$ENV_FILE" ]; then
    ok ".env"
else
    warn ".envがありません"
fi

section "ホストコマンド"

for command in php psql curl git; do
    if command -v "$command" >/dev/null 2>&1; then
        ok "${command}"
    else
        fail "${command}がありません"
    fi
done

section "PHP"

if command -v php >/dev/null 2>&1; then
    php -v | head -n 1

    PHP_MODULES="$(php -m 2>/dev/null | tr -d '\r')"

    for extension in \
        PDO \
        pdo_pgsql \
        curl \
        openssl \
        mbstring \
        json \
        session
    do
        if printf '%s\n' "$PHP_MODULES" |
            grep -Eiq "^[[:space:]]*${extension}[[:space:]]*$"; then
            ok "PHP拡張 ${extension}"
        else
            fail "PHP拡張 ${extension}がありません"
        fi
    done
fi

section "PHP構文"

SYNTAX_ERRORS=0
SYNTAX_FILES=0

while IFS= read -r -d '' file; do
    SYNTAX_FILES=$((SYNTAX_FILES + 1))

    result="$(php -l "$file" 2>&1)"

    if ! printf '%s\n' "$result" |
        grep -q "No syntax errors"; then
        printf '%s\n' "$result"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done < <(
    find "$APP_DIR" \
        -type f \
        -name "*.php" \
        -not -path "$APP_DIR/vendor/*" \
        -not -path "$APP_DIR/storage/*" \
        -not -path "$APP_DIR/uploads/*" \
        -print0
)

if [ "$SYNTAX_ERRORS" -eq 0 ]; then
    ok "PHP構文 ${SYNTAX_FILES}ファイル"
else
    fail "PHP構文エラー ${SYNTAX_ERRORS}件"
fi

section "Git"

if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" status --short --branch || true
    git -C "$APP_DIR" log -1 --oneline || true

    if [ -z "$(git -C "$APP_DIR" status --porcelain)" ]; then
        ok "Git作業ツリー"
    else
        warn "Git作業ツリーに変更があります"
    fi
else
    warn ".gitがありません"
fi

section "PostgreSQL"

DB_OK=0

if [ -z "${DB_HOST:-}" ] ||
   [ -z "${DB_NAME:-}" ] ||
   [ -z "${DB_USER:-}" ]; then
    fail "DB接続情報が不足しています"
elif db -Atc "SELECT 1;" >/dev/null 2>&1; then
    DB_OK=1
    ok "PostgreSQL接続"

    db -c "
        SELECT
            current_database() AS database,
            current_user AS db_user,
            inet_server_addr() AS host,
            inet_server_port() AS port;
    " || true
else
    fail "PostgreSQL接続失敗"
fi

if [ "$DB_OK" -eq 1 ]; then
    section "HC Account・認証"

    for table in \
        users \
        pending_registrations \
        account_roles \
        permissions \
        user_roles \
        role_permissions
    do
        if [ "$(table_exists "$table")" = "t" ]; then
            ok "テーブル ${table}"
        else
            fail "テーブル ${table}がありません"
        fi
    done

    OWNER_ACCESS="$(
        db -Atc "
            SELECT EXISTS (
                SELECT 1
                FROM users u
                JOIN user_roles ur
                  ON ur.user_id = u.id
                JOIN account_roles r
                  ON r.id = ur.role_id
                JOIN role_permissions rp
                  ON rp.role_id = r.id
                JOIN permissions p
                  ON p.id = rp.permission_id
                WHERE u.username = '${OWNER_USERNAME}'
                  AND r.slug = 'owner'
                  AND p.permission_key = 'staff.access'
            );
        " 2>/dev/null || true
    )"

    if [ "$OWNER_ACCESS" = "t" ]; then
        ok "${OWNER_USERNAME} → owner → staff.access"
    else
        fail "${OWNER_USERNAME}のowner権限連携不良"
    fi

    db -c "
        SELECT
            r.slug,
            COUNT(DISTINCT rp.permission_id) AS granted,
            (
                SELECT COUNT(*)
                FROM permissions
            ) AS total
        FROM account_roles r
        LEFT JOIN role_permissions rp
          ON rp.role_id = r.id
        WHERE r.slug = 'owner'
        GROUP BY r.id, r.slug;
    " 2>/dev/null || true

    section "Staff Console"

    for table in \
        staff_users \
        staff_roles \
        staff_permissions \
        staff_user_roles \
        staff_role_permissions \
        staff_tasks \
        staff_notifications \
        staff_announcements \
        staff_audit_logs
    do
        if [ "$(table_exists "$table")" = "t" ]; then
            ok "テーブル ${table}"
        else
            warn "テーブル ${table}がありません"
        fi
    done

    section "Notification Center DB"

    if [ "$(table_exists staff_notifications)" = "t" ]; then
        ok "staff_notifications"

        db -c "
            SELECT
                ordinal_position,
                column_name,
                data_type,
                is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'staff_notifications'
            ORDER BY ordinal_position;
        " || true

        for column in \
            id \
            user_id \
            category \
            level \
            created_at
        do
            if [ "$(column_exists staff_notifications "$column")" = "t" ]; then
                ok "staff_notifications.${column}"
            else
                fail "staff_notifications.${column}がありません"
            fi
        done

        if [ "$(column_exists staff_notifications title)" = "t" ] ||
           [ "$(column_exists staff_notifications subject)" = "t" ]; then
            ok "通知タイトル列"
        else
            fail "通知タイトル列がありません"
        fi

        if [ "$(column_exists staff_notifications message)" = "t" ] ||
           [ "$(column_exists staff_notifications body)" = "t" ]; then
            ok "通知本文列"
        else
            fail "通知本文列がありません"
        fi

        if [ "$(column_exists staff_notifications is_read)" = "t" ] ||
           [ "$(column_exists staff_notifications read_at)" = "t" ]; then
            ok "通知既読列"
        else
            fail "通知既読列がありません"
        fi

        db -c "
            SELECT
                category,
                COUNT(*) AS total
            FROM staff_notifications
            GROUP BY category
            ORDER BY category;
        " || true
    else
        fail "staff_notificationsがありません"
    fi

    section "注文・決済・Provisioning"

    for table in \
        game_server_plans \
        game_server_orders \
        server_order_events \
        invoices \
        payment_methods \
        provisioning_jobs
    do
        if [ "$(table_exists "$table")" = "t" ]; then
            ok "テーブル ${table}"
        else
            warn "テーブル ${table}がありません"
        fi
    done

    printf '\n[連携関連テーブル]\n'

    db -c "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND (
              table_name LIKE '%notification%'
              OR table_name LIKE '%order%'
              OR table_name LIKE '%invoice%'
              OR table_name LIKE '%payment%'
              OR table_name LIKE '%billing%'
              OR table_name LIKE '%provision%'
              OR table_name LIKE '%ptero%'
              OR table_name LIKE '%stripe%'
              OR table_name LIKE '%audit%'
          )
        ORDER BY table_name;
    " || true
fi

section "Notification Center API"

for category in \
    all \
    system \
    order \
    discord \
    github \
    development
do
    BODY_FILE="$TMP_DIR/notifications-${category}.body"

    STATUS="$(
        http_status \
            GET \
            "${BASE_URL}/staff/api/notifications/?category=${category}&limit=30" \
            "$BODY_FILE" \
            "$HC_SESSION_COOKIE"
    )"

    if [ -n "$HC_SESSION_COOKIE" ]; then
        if [ "$STATUS" = "200" ] &&
           json_valid "$BODY_FILE"; then
            ok "通知API ${category}"
        else
            fail "通知API ${category} HTTP ${STATUS}"
            cat "$BODY_FILE"
        fi
    else
        case "$STATUS" in
            200)
                if json_valid "$BODY_FILE"; then
                    ok "通知API HTTP 200 JSON"
                else
                    fail "通知API JSON不正"
                fi
                ;;

            301|302|303|307|308|401|403)
                ok "通知API認証保護 HTTP ${STATUS}"
                ;;

            500|502|503|504)
                fail "通知API HTTP ${STATUS}"
                cat "$BODY_FILE"
                ;;

            *)
                warn "通知API HTTP ${STATUS}"
                ;;
        esac

        break
    fi
done

for endpoint in \
    /staff/api/notifications/read.php \
    /staff/api/notifications/read-all.php \
    /staff/api/status/update.php
do
    BODY_FILE="$TMP_DIR/endpoint.body"

    STATUS="$(
        http_status \
            GET \
            "${BASE_URL}${endpoint}" \
            "$BODY_FILE" \
            "$HC_SESSION_COOKIE"
    )"

    case "$STATUS" in
        200|301|302|303|307|308|400|401|403|405)
            ok "${endpoint} HTTP ${STATUS}"
            ;;

        500|502|503|504)
            fail "${endpoint} HTTP ${STATUS}"
            cat "$BODY_FILE"
            ;;

        *)
            warn "${endpoint} HTTP ${STATUS}"
            ;;
    esac
done

if [ -n "$HC_SESSION_COOKIE" ]; then
    BODY_FILE="$TMP_DIR/read-invalid.json"

    STATUS="$(
        curl \
            -k \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 8 \
            --max-time 25 \
            -X POST \
            -H "Cookie: ${HC_SESSION_COOKIE}" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            --data '{"id":0}' \
            "${BASE_URL}/staff/api/notifications/read.php" \
            2>/dev/null || true
    )"

    case "$STATUS" in
        400|404)
            if json_valid "$BODY_FILE"; then
                ok "通知個別既読API入力検証"
            else
                fail "通知個別既読API JSON不正"
            fi
            ;;

        500|502|503|504)
            fail "通知個別既読API HTTP ${STATUS}"
            cat "$BODY_FILE"
            ;;

        *)
            warn "通知個別既読API HTTP ${STATUS}"
            ;;
    esac

    if [ "$HC_ALLOW_READ_ALL_TEST" = "1" ]; then
        BODY_FILE="$TMP_DIR/read-all.json"

        STATUS="$(
            curl \
                -k \
                -sS \
                -o "$BODY_FILE" \
                -w "%{http_code}" \
                --connect-timeout 8 \
                --max-time 25 \
                -X POST \
                -H "Cookie: ${HC_SESSION_COOKIE}" \
                -H "Accept: application/json" \
                "${BASE_URL}/staff/api/notifications/read-all.php" \
                2>/dev/null || true
        )"

        if [ "$STATUS" = "200" ] &&
           json_valid "$BODY_FILE"; then
            ok "通知一括既読API"
        else
            fail "通知一括既読API HTTP ${STATUS}"
            cat "$BODY_FILE"
        fi
    else
        warn "一括既読APIはデータ変更を伴うため未実行"
    fi
else
    warn "HC_SESSION_COOKIE未指定"
fi

section "環境変数"

check_env_group "APP URL" APP_URL
check_env_group "SMTP" SMTP_HOST MAIL_HOST
check_env_group "SMTP認証" SMTP_USER SMTP_PASSWORD MAIL_USERNAME MAIL_PASSWORD
check_env_group "Mail From" MAIL_FROM_ADDRESS
check_env_group "Turnstile" TURNSTILE_SITE_KEY TURNSTILE_SECRET_KEY
check_env_group "Stripe" STRIPE_SECRET_KEY STRIPE_WEBHOOK_SECRET
check_env_group "Pterodactyl" PTERO_PANEL_URL PTERO_API_KEY
check_env_group "Discord" DISCORD_BOT_TOKEN DISCORD_WEBHOOK_URL DISCORD_GUILD_ID
check_env_group "GitHub" GITHUB_TOKEN GITHUB_REPOSITORY GITHUB_WEBHOOK_SECRET
check_env_group "Cloudflare" CLOUDFLARE_API_TOKEN CLOUDFLARE_TUNNEL_ID

section "Stripe"

STRIPE_KEY="$(first_value STRIPE_SECRET_KEY STRIPE_API_KEY 2>/dev/null || true)"

if [ -n "$STRIPE_KEY" ]; then
    BODY_FILE="$TMP_DIR/stripe.json"

    STATUS="$(
        curl \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            -u "${STRIPE_KEY}:" \
            https://api.stripe.com/v1/account \
            2>/dev/null || true
    )"

    if [ "$STATUS" = "200" ] &&
       json_valid "$BODY_FILE"; then
        ok "Stripe API"

        if grep -q '"charges_enabled": true' "$BODY_FILE"; then
            ok "Stripe charges_enabled=true"
        else
            warn "Stripe charges_enabled=false"
        fi

        if grep -q '"livemode": true' "$BODY_FILE"; then
            ok "Stripe Liveキー"
        else
            warn "Stripe Testキー"
        fi
    else
        fail "Stripe API HTTP ${STATUS}"
    fi
else
    warn "Stripe秘密鍵未設定"
fi

section "Pterodactyl"

PTERO_URL="$(first_value PTERO_PANEL_URL PTERODACTYL_PANEL_URL PTERO_URL 2>/dev/null || true)"
PTERO_KEY="$(first_value PTERO_API_KEY PTERODACTYL_API_KEY PTERODACTYL_APPLICATION_API_KEY 2>/dev/null || true)"
PTERO_URL="${PTERO_URL%/}"

if [ -n "$PTERO_URL" ] &&
   [ -n "$PTERO_KEY" ]; then
    BODY_FILE="$TMP_DIR/ptero.json"

    STATUS="$(
        curl \
            -k \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            -H "Authorization: Bearer ${PTERO_KEY}" \
            -H "Accept: Application/vnd.pterodactyl.v1+json" \
            "${PTERO_URL}/api/application/users?per_page=1" \
            2>/dev/null || true
    )"

    case "$STATUS" in
        200)
            ok "Pterodactyl API"
            ;;
        401)
            fail "Pterodactyl APIキー不正"
            ;;
        403)
            fail "Pterodactyl権限不足"
            ;;
        *)
            fail "Pterodactyl HTTP ${STATUS}"
            ;;
    esac
else
    warn "Pterodactyl設定不足"
fi

section "Discord"

DISCORD_TOKEN="$(first_value DISCORD_BOT_TOKEN DISCORD_TOKEN 2>/dev/null || true)"
DISCORD_WEBHOOK="$(first_value DISCORD_WEBHOOK_URL 2>/dev/null || true)"

if [ -n "$DISCORD_TOKEN" ]; then
    BODY_FILE="$TMP_DIR/discord.json"

    STATUS="$(
        curl \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            -H "Authorization: Bot ${DISCORD_TOKEN}" \
            https://discord.com/api/v10/users/@me \
            2>/dev/null || true
    )"

    if [ "$STATUS" = "200" ] &&
       json_valid "$BODY_FILE"; then
        ok "Discord Bot API"
    else
        fail "Discord Bot HTTP ${STATUS}"
    fi
else
    warn "Discord Bot Token未設定"
fi

if [ -n "$DISCORD_WEBHOOK" ]; then
    STATUS="$(
        curl \
            -sS \
            -o "$TMP_DIR/discord-webhook.json" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            "$DISCORD_WEBHOOK" \
            2>/dev/null || true
    )"

    if [ "$STATUS" = "200" ]; then
        ok "Discord Webhook"
    else
        fail "Discord Webhook HTTP ${STATUS}"
    fi
else
    warn "Discord Webhook未設定"
fi

section "GitHub"

GITHUB_TOKEN_VALUE="$(first_value GITHUB_TOKEN GH_TOKEN 2>/dev/null || true)"
GITHUB_REPOSITORY_VALUE="$(first_value GITHUB_REPOSITORY 2>/dev/null || true)"
GITHUB_REPOSITORY_VALUE="${GITHUB_REPOSITORY_VALUE:-kakuni-hmjn/hc-platform}"

GITHUB_ARGS=(
    -sS
    -o "$TMP_DIR/github.json"
    -w "%{http_code}"
    --connect-timeout 10
    --max-time 25
    -H "Accept: application/vnd.github+json"
    -H "X-GitHub-Api-Version: 2022-11-28"
)

if [ -n "$GITHUB_TOKEN_VALUE" ]; then
    GITHUB_ARGS+=(
        -H "Authorization: Bearer ${GITHUB_TOKEN_VALUE}"
    )
fi

STATUS="$(
    curl \
        "${GITHUB_ARGS[@]}" \
        "https://api.github.com/repos/${GITHUB_REPOSITORY_VALUE}" \
        2>/dev/null || true
)"

if [ "$STATUS" = "200" ] &&
   json_valid "$TMP_DIR/github.json"; then
    ok "GitHub Repository API"
else
    fail "GitHub API HTTP ${STATUS}"
fi

section "SMTP・Turnstile・Cloudflare"

SMTP_HOST_VALUE="$(first_value SMTP_HOST MAIL_HOST 2>/dev/null || true)"
SMTP_PORT_VALUE="$(first_value SMTP_PORT MAIL_PORT 2>/dev/null || true)"
SMTP_PORT_VALUE="${SMTP_PORT_VALUE:-587}"

if [ -n "$SMTP_HOST_VALUE" ] &&
   command -v nc >/dev/null 2>&1; then
    if nc \
        -z \
        -w 8 \
        "$SMTP_HOST_VALUE" \
        "$SMTP_PORT_VALUE" \
        >/dev/null 2>&1; then
        ok "SMTP ${SMTP_HOST_VALUE}:${SMTP_PORT_VALUE}"
    else
        fail "SMTP接続失敗"
    fi
else
    warn "SMTPホストまたはncがありません"
fi

TURNSTILE_SECRET="$(first_value TURNSTILE_SECRET_KEY 2>/dev/null || true)"

if [ -n "$TURNSTILE_SECRET" ]; then
    BODY_FILE="$TMP_DIR/turnstile.json"

    STATUS="$(
        curl \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            -X POST \
            --data-urlencode "secret=${TURNSTILE_SECRET}" \
            --data-urlencode "response=hc-audit-invalid-token" \
            https://challenges.cloudflare.com/turnstile/v0/siteverify \
            2>/dev/null || true
    )"

    if [ "$STATUS" = "200" ] &&
       json_valid "$BODY_FILE"; then
        if grep -q "invalid-input-secret" "$BODY_FILE"; then
            fail "Turnstile Secret不正"
        else
            ok "Turnstile API"
        fi
    else
        fail "Turnstile HTTP ${STATUS}"
    fi
else
    warn "Turnstile Secret未設定"
fi

CLOUDFLARE_TOKEN="$(first_value CLOUDFLARE_API_TOKEN 2>/dev/null || true)"

if [ -n "$CLOUDFLARE_TOKEN" ]; then
    BODY_FILE="$TMP_DIR/cloudflare.json"

    STATUS="$(
        curl \
            -sS \
            -o "$BODY_FILE" \
            -w "%{http_code}" \
            --connect-timeout 10 \
            --max-time 25 \
            -H "Authorization: Bearer ${CLOUDFLARE_TOKEN}" \
            https://api.cloudflare.com/client/v4/user/tokens/verify \
            2>/dev/null || true
    )"

    if [ "$STATUS" = "200" ] &&
       grep -Eq '"success"[[:space:]]*:[[:space:]]*true' "$BODY_FILE"; then
        ok "Cloudflare API Token"
    else
        fail "Cloudflare HTTP ${STATUS}"
    fi
else
    warn "Cloudflare API Token未設定"
fi

section "主要画面"

for path in \
    / \
    /login/ \
    /register/ \
    /verify-code/ \
    /dashboard/ \
    /services/ \
    /services/rental/game-server/ \
    /order/game-server/ \
    /billing/ \
    /admin/ \
    /staff/ \
    /staff/tasks/ \
    /staff/notifications/ \
    /staff/rental-server/ \
    /staff/rental-server/game-server/ \
    /staff/audit/
do
    BODY_FILE="$TMP_DIR/page.body"

    STATUS="$(
        http_status \
            GET \
            "${BASE_URL}${path}" \
            "$BODY_FILE"
    )"

    case "$STATUS" in
        200|301|302|303|307|308|401|403)
            ok "${path} HTTP ${STATUS}"
            ;;
        500|502|503|504)
            fail "${path} HTTP ${STATUS}"
            ;;
        000|"")
            fail "${path} 接続不可"
            ;;
        *)
            warn "${path} HTTP ${STATUS}"
            ;;
    esac
done

section "ログ"

for log in \
    "$APP_DIR/storage/logs/app.log" \
    "$APP_DIR/storage/logs/error.log" \
    "$APP_DIR/storage/logs/php-error.log" \
    /var/log/apache2/error.log \
    /var/log/nginx/error.log
do
    if [ -f "$log" ]; then
        printf '\n--- %s ---\n' "$log"

        tail -n 300 "$log" |
        grep -Ei \
            'fatal|exception|uncaught|sqlstate|undefined|warning|error|failed|notification' |
        tail -n 120 || true
    fi
done

section "監査結果"

printf '✅ PASS: %s\n' "$PASS"
printf '⚠️  WARN: %s\n' "$WARN"
printf '❌ FAIL: %s\n' "$FAIL"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
