#!/usr/bin/env bash
# Runs the API tests against PHP's built-in server with a throwaway data folder and password.
# Usage: bash tests/run.sh   (needs php-cli with GD). Safe to run in a dev checkout: an existing
# inc/config.php is set aside for the run and put back afterwards.
set -euo pipefail
cd "$(dirname "$0")/.."
APP=event-manager
TMP="$(mktemp -d)"
PORT="${PORT:-8049}"
CFG="$APP/inc/config.php"
[ -f "$CFG" ] && mv "$CFG" "$TMP/config.php.saved"
cleanup() {
  [ -n "${PID:-}" ] && kill "$PID" 2>/dev/null || true
  rm -f "$CFG"
  [ -f "$TMP/config.php.saved" ] && mv "$TMP/config.php.saved" "$CFG"
  rm -rf "$TMP"
}
trap cleanup EXIT
mkdir -p "$TMP/data"
HASH="$(php -r 'echo password_hash("test-password", PASSWORD_DEFAULT);')"
printf "<?php\nreturn ['data_dir' => '%s', 'admin_password_hash' => '%s'];\n" "$TMP/data" "$HASH" > "$CFG"
php -S "127.0.0.1:$PORT" -t "$APP" "$APP/bin/dev-router.php" > "$TMP/server.log" 2>&1 &
PID=$!
for _ in $(seq 50); do php -r "exit(@fsockopen('127.0.0.1', $PORT) ? 0 : 1);" && break; sleep 0.1; done
status=0
for t in tests/*-test.php; do
  echo "== $t"
  php "$t" "http://127.0.0.1:$PORT/" "$TMP/data" "test-password" || status=1
done
[ "$status" = 0 ] || { echo "-- server log"; cat "$TMP/server.log"; }
exit "$status"
