#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"

HC_ENV_MODE="prod" \
APP_DIR="$APP_DIR" \
ENV_FILE="$APP_DIR/.env" \
BASE_URL="${BASE_URL:-https://www.hc-jp.net}" \
"$APP_DIR/scripts/hc-audit.sh"
