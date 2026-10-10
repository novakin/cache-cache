#!/bin/sh
# Fresh CI site only. The database is owned by this job's MariaDB service.
set -eu
test "$#" -eq 3 || { echo 'Usage: setup-wordpress.sh WORKSPACE VERSION NEW_DIRECTORY' >&2; exit 2; }
workspace=$(realpath "$1")
version=$2
destination=$3
sh "$workspace/.github/scripts/download-wordpress.sh" "$version" "$destination"
wordpress="$destination/wordpress"
test -f "$workspace/noobrocket/noobrocket.php"
touch "$wordpress/.noobrocket-acceptance-site"
mkdir -p "$wordpress/wp-content/plugins/noobrocket" "$wordpress/wp-content/mu-plugins"
tar -C "$workspace/noobrocket" --exclude='./local-css/node_modules' -cf - . | tar -C "$wordpress/wp-content/plugins/noobrocket" -xf -
cp "$workspace/tests/wordpress-http-audit.php" "$wordpress/wp-content/mu-plugins/noobrocket-http-audit.php"
cat > "$wordpress/wp-content/mu-plugins/noobrocket-no-core-check.php" <<'EOF'
<?php
foreach ( [ 'core', 'plugins', 'themes' ] as $type ) {
  add_filter( 'pre_site_transient_update_' . $type, static function () { return (object) [ 'last_checked' => time(), 'version_checked' => $GLOBALS['wp_version'], 'updates' => [], 'checked' => [], 'response' => [], 'no_update' => [] ]; } );
}
EOF
cat > "$wordpress/wp-config.php" <<'EOF'
<?php
define('DB_NAME', 'cachecache'); define('DB_USER', 'cachecache'); define('DB_PASSWORD', 'disposable-ci'); define('DB_HOST', '127.0.0.1:3306'); define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('WP_HOME', 'http://127.0.0.1:8765'); define('WP_SITEURL', 'http://127.0.0.1:8765'); define('WP_DEBUG', true); define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false); define('DISABLE_WP_CRON', true); define('AUTOMATIC_UPDATER_DISABLED', true); define('FS_METHOD', 'direct');
define('AUTH_KEY', 'disposable-ci-auth'); define('SECURE_AUTH_KEY', 'disposable-ci-secure'); define('LOGGED_IN_KEY', 'disposable-ci-login'); define('NONCE_KEY', 'disposable-ci-nonce');
define('AUTH_SALT', 'disposable-ci-auth-salt'); define('SECURE_AUTH_SALT', 'disposable-ci-secure-salt'); define('LOGGED_IN_SALT', 'disposable-ci-login-salt'); define('NONCE_SALT', 'disposable-ci-nonce-salt');
$table_prefix = 'wp_'; if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/'); require_once ABSPATH . 'wp-settings.php';
EOF
php "$workspace/.github/scripts/install-wordpress.php" "$wordpress"
if [ -s "$wordpress/wp-content/http-audit-blocked.txt" ]; then
  echo 'Unexpected HTTP blocked during disposable WordPress setup.' >&2
  exit 1
fi
echo "Prepared marked copied-plugin site: $wordpress"
