#!/usr/bin/env bash
set -e

MYSQL_DATA="/home/runner/workspace/mysql-data"
MYSQL_RUN="/home/runner/workspace/mysql-run"
MYSQL_SOCK="$MYSQL_RUN/mysql.sock"
MYSQL_LOG="$MYSQL_DATA/mysqld.log"

mkdir -p "$MYSQL_RUN"

# Initialize MySQL data directory if not already done
if [ ! -d "$MYSQL_DATA/mysql" ]; then
  echo "Initializing MySQL data directory..."
  mysqld --initialize-insecure \
    --user=runner \
    --datadir="$MYSQL_DATA" \
    2>&1
fi

# Start MySQL if not running
if ! mysqladmin --socket="$MYSQL_SOCK" ping --silent 2>/dev/null; then
  echo "Starting MySQL..."
  nohup mysqld \
    --user=runner \
    --datadir="$MYSQL_DATA" \
    --socket="$MYSQL_SOCK" \
    --pid-file="$MYSQL_RUN/mysql.pid" \
    --port=3306 \
    --bind-address=127.0.0.1 \
    --mysqlx=OFF \
    > "$MYSQL_LOG" 2>&1 &

  # Wait up to 30 seconds for MySQL to become ready
  echo "Waiting for MySQL to start..."
  for i in $(seq 1 30); do
    if mysqladmin --socket="$MYSQL_SOCK" ping --silent 2>/dev/null; then
      echo "MySQL is ready."
      break
    fi
    sleep 1
  done
fi

# Create database if it doesn't exist
mysql -u root --socket="$MYSQL_SOCK" -e "
  CREATE DATABASE IF NOT EXISTS fuelmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'fuelmate'@'%' IDENTIFIED BY 'fuelmate_secret';
  GRANT ALL PRIVILEGES ON fuelmate.* TO 'fuelmate'@'%';
  FLUSH PRIVILEGES;
" 2>/dev/null || true

# Also allow root TCP connections for artisan
mysql -u root --socket="$MYSQL_SOCK" -e "
  ALTER USER 'root'@'localhost' IDENTIFIED BY '';
  FLUSH PRIVILEGES;
" 2>/dev/null || true

cd /home/runner/workspace/artifacts/fuelmate-laravel

# Run migrations and seeding
php artisan migrate --force --seed 2>&1 || true

# Start Laravel development server
exec php artisan serve --host=0.0.0.0 --port="${PORT:-5000}"
