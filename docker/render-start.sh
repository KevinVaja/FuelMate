#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

if [[ -n "${RENDER_EXTERNAL_URL:-}" ]]; then
  export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"
  export ASSET_URL="${ASSET_URL:-$RENDER_EXTERNAL_URL}"
fi

if [[ -z "${APP_KEY:-}" ]]; then
  echo "APP_KEY is not set. Generate one with: php artisan key:generate --show"
  exit 1
fi

php artisan storage:link || true
php artisan optimize:clear

attempt=1
max_attempts=10

until php artisan migrate --force; do
  if [[ "$attempt" -ge "$max_attempts" ]]; then
    echo "Database migrations failed after ${max_attempts} attempts."
    exit 1
  fi

  echo "Database not ready yet. Retrying in 5 seconds..."
  attempt=$((attempt + 1))
  sleep 5
done

php artisan config:cache

if [[ "${RUN_SCHEDULER:-true}" == "true" ]]; then
  (
    while true; do
      php artisan schedule:run --no-interaction || true
      sleep 60
    done
  ) &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
