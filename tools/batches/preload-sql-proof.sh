#!/bin/sh
# Run only in a new unprivileged MariaDB directory; no existing service is used.
set -eu
workspace=$(realpath "$1")
archive=$(realpath "$2")
test "$(id -u)" -ne 0
proof=$(mktemp -d /tmp/noobrocket-preload-sql.XXXXXXXX)
mkdir "$proof/database" "$proof/run" "$proof/logs"
cleanup() {
  if [ -f "$proof/run/database.pid" ]; then
    pid=$(cat "$proof/run/database.pid")
    case "$pid" in ''|*[!0-9]*) return;; esac
    if [ -r "/proc/$pid/cmdline" ] && tr '\000' ' ' < "/proc/$pid/cmdline" | grep -F "$proof" >/dev/null; then
      kill "$pid" 2>/dev/null || true
      attempt=0
      while kill -0 "$pid" 2>/dev/null; do attempt=$((attempt+1)); test "$attempt" -lt 30 || break; sleep 1; done
    fi
  fi
  echo "SQL artifacts retained: $proof"
}
trap cleanup EXIT HUP INT TERM
mariadb-install-db --no-defaults --datadir="$proof/database" --auth-root-authentication-method=normal > "$proof/logs/install.log" 2>&1
mariadbd --no-defaults --datadir="$proof/database" --socket="$proof/run/database.sock" --pid-file="$proof/run/database.pid" --skip-networking --log-error="$proof/logs/database.log" &
attempt=0
until mariadb --no-defaults --socket="$proof/run/database.sock" -u root -e 'SELECT 1' >/dev/null 2>&1; do attempt=$((attempt+1)); test "$attempt" -lt 45; sleep 1; done
mariadb --no-defaults --socket="$proof/run/database.sock" -u root -e 'CREATE DATABASE noobrocket_sql;'
unzip -q "$archive" -d "$proof"
wordpress="$proof/wordpress"
touch "$wordpress/.noobrocket-preload-sql-site"
mkdir -p "$wordpress/wp-content/mu-plugins"
cat > "$wordpress/wp-content/mu-plugins/no-network.php" <<'EOF'
<?php
add_filter('pre_http_request', static function () { return new WP_Error('isolated_proof', 'No external HTTP in SQL proof.'); });
add_filter('pre_wp_mail', '__return_true');
EOF
cat > "$wordpress/wp-config.php" <<EOF
<?php
define('DB_NAME','noobrocket_sql'); define('DB_USER','root'); define('DB_PASSWORD',''); define('DB_HOST','localhost:$proof/run/database.sock'); define('DB_CHARSET','utf8mb4'); define('DB_COLLATE','');
define('WP_HOME','http://sql-proof.invalid'); define('WP_SITEURL','http://sql-proof.invalid'); define('DISABLE_WP_CRON',true); define('AUTOMATIC_UPDATER_DISABLED',true); define('WP_DEBUG',true); define('WP_DEBUG_DISPLAY',false); define('WP_DEBUG_LOG',true);
\$table_prefix='wp_'; if(!defined('ABSPATH')) define('ABSPATH',__DIR__.'/'); require_once ABSPATH.'wp-settings.php';
EOF
export NOOBROCKET_PRELOAD_SQL_SITE="$wordpress"
php "$workspace/tests/wordpress-preload-sql.php" "$wordpress" "$workspace" "$proof/proof.json"
cp "$proof/proof.json" "$workspace/.artifacts/cache-fixes-20261009/preload-sql-proof.json"
