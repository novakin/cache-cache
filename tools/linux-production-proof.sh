#!/bin/sh
# Existing prerequisites only. Every writable path is a new disposable directory.
set -eu
if [ "$#" -lt 4 ] || [ "$#" -gt 5 ]; then
  echo 'Usage: tools/linux-production-proof.sh /absolute/workspace /absolute/wordpress.zip /absolute/node /absolute/chromium [/absolute/local-css/package.json]' >&2
  exit 2
fi
workspace=$(realpath "$1")
archive=$(realpath "$2")
node=$(realpath "$3")
browser=$(realpath "$4")
test "$(id -u)" -ne 0 || { echo 'Run as an unprivileged account; Chromium sandbox stays enabled.' >&2; exit 2; }
test -f "$workspace/noobrocket/noobrocket.php"
dependencies=${5:-$workspace/noobrocket/local-css/package.json}
test -f "$dependencies"
php_bin=$(command -v "${PHP_CLI:-php}") || { echo "Missing PHP CLI: ${PHP_CLI:-php}" >&2; exit 2; }
php_bin=$(realpath "$php_bin")
php_version=$("$php_bin" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')
fpm_bin=$(command -v "${PHP_FPM:-php-fpm$php_version}") || { echo "Missing matching PHP-FPM: ${PHP_FPM:-php-fpm$php_version}" >&2; exit 2; }
apache_modules=${APACHE_MODULE_DIR:-/usr/lib/apache2/modules}
test -d "$apache_modules" || { echo "Missing Apache module directory: $apache_modules" >&2; exit 2; }
test -d "$(dirname "$dependencies")/node_modules" || { echo 'Install worker dependencies with npm ci before server proof.' >&2; exit 2; }
for command in unzip mariadb mariadbd mariadb-install-db apache2 nginx; do
  command -v "$command" >/dev/null || { echo "Missing server prerequisite: $command" >&2; exit 2; }
done
echo "PHP CLI: $php_bin ($php_version); PHP-FPM: $fpm_bin"
proof_parent=${NOOBROCKET_PROOF_DIR:-${TMPDIR:-/tmp}}
mkdir -p "$proof_parent"
proof=$(mktemp -d "$proof_parent/noobrocket-production.XXXXXXXX")
echo "Disposable proof: $proof"
mkdir "$proof/database" "$proof/run" "$proof/logs"
sampler_pid=''
cleanup() {
  for file in "$proof/run/apache.pid" "$proof/run/nginx.pid" "$proof/run/fpm.pid" "$proof/run/database.pid"; do
    if [ -f "$file" ]; then
      pid=$(cat "$file")
      case "$pid" in ''|*[!0-9]*) continue;; esac
      if [ -r "/proc/$pid/cmdline" ] && tr '\000' ' ' < "/proc/$pid/cmdline" | grep -F "$proof" >/dev/null; then kill "$pid" 2>/dev/null || true; fi
    fi
  done
  if [ -n "$sampler_pid" ] && [ -r "/proc/$sampler_pid/cmdline" ] && tr '\000' ' ' < "/proc/$sampler_pid/cmdline" | grep -F "$proof" >/dev/null; then kill "$sampler_pid" 2>/dev/null || true; fi
  echo "Proof artifacts retained: $proof"
}
trap cleanup EXIT HUP INT TERM
mariadb-install-db --no-defaults --datadir="$proof/database" --auth-root-authentication-method=normal > "$proof/logs/database-install.log" 2>&1
mariadbd --no-defaults --datadir="$proof/database" --socket="$proof/run/database.sock" --pid-file="$proof/run/database.pid" --skip-networking --log-error="$proof/logs/database.log" &
attempt=0
until mariadb --no-defaults --socket="$proof/run/database.sock" -u root -e 'SELECT 1' >/dev/null 2>&1; do attempt=$((attempt+1)); test "$attempt" -lt 60; sleep 1; done
mariadb --no-defaults --socket="$proof/run/database.sock" -u root -e 'CREATE DATABASE noobrocket_proof;'
unzip -q "$archive" -d "$proof"
wordpress="$proof/wordpress"
touch "$wordpress/.noobrocket-acceptance-site"
mkdir "$wordpress/wp-content/plugins/noobrocket"
tar -C "$workspace/noobrocket" --exclude='./local-css/node_modules' -cf - . | tar -C "$wordpress/wp-content/plugins/noobrocket" -xf -
ln -s "$(dirname "$dependencies")/node_modules" "$wordpress/wp-content/plugins/noobrocket/local-css/node_modules"
mkdir -p "$wordpress/wp-content/mu-plugins"
cp "$workspace/tests/wordpress-http-audit.php" "$wordpress/wp-content/mu-plugins/noobrocket-http-audit.php"
cat > "$wordpress/wp-content/mu-plugins/noobrocket-no-core-check.php" <<'EOF'
<?php
foreach ( [ 'core', 'plugins', 'themes' ] as $type ) {
  add_filter( 'pre_site_transient_update_' . $type, static function () { return (object) [ 'last_checked' => time(), 'version_checked' => $GLOBALS['wp_version'], 'updates' => [], 'checked' => [], 'response' => [], 'no_update' => [] ]; } );
}
EOF
cat > "$wordpress/wp-content/mu-plugins/noobrocket-worker-fixture.php" <<'EOF'
<?php
add_action( 'wp_enqueue_scripts', static function () {
  if ( is_singular() && get_post_meta( get_queried_object_id(), '_noobrocket_linux_worker', true ) ) {
    wp_enqueue_style( 'linux-worker-proof', content_url( '/linux-worker-proof.css' ) );
  }
} );
EOF
printf '.linux-worker-proof{color:green}.unused-linux-rule{color:red}\n' > "$wordpress/wp-content/linux-worker-proof.css"
cat > "$wordpress/wp-config.php" <<EOF
<?php
define('DB_NAME','noobrocket_proof'); define('DB_USER','root'); define('DB_PASSWORD',''); define('DB_HOST','localhost:$proof/run/database.sock'); define('DB_CHARSET','utf8mb4'); define('DB_COLLATE','');
define('WP_HOME','http://127.0.0.1:8875'); define('WP_SITEURL','http://127.0.0.1:8875'); define('WP_DEBUG',true); define('WP_DEBUG_LOG',true); define('WP_DEBUG_DISPLAY',false); define('DISABLE_WP_CRON',true); define('AUTOMATIC_UPDATER_DISABLED',true); define('FS_METHOD','direct');
define('AUTH_KEY','disposable-noobrocket-auth'); define('SECURE_AUTH_KEY','disposable-noobrocket-secure'); define('LOGGED_IN_KEY','disposable-noobrocket-login'); define('NONCE_KEY','disposable-noobrocket-nonce'); define('AUTH_SALT','disposable-noobrocket-auth-salt'); define('SECURE_AUTH_SALT','disposable-noobrocket-secure-salt'); define('LOGGED_IN_SALT','disposable-noobrocket-login-salt'); define('NONCE_SALT','disposable-noobrocket-nonce-salt');
\$table_prefix='wp_'; if(!defined('ABSPATH')) define('ABSPATH',__DIR__.'/'); require_once ABSPATH.'wp-settings.php';
EOF
export NOOBROCKET_LINUX_PROOF="$wordpress"
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" install
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" activate
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" boot
cat > "$proof/fpm.conf" <<EOF
[global]
pid = $proof/run/fpm.pid
error_log = $proof/logs/fpm.log
[proof]
listen = $proof/run/fpm.sock
pm = static
pm.max_children = 2
catch_workers_output = yes
EOF
"$fpm_bin" -y "$proof/fpm.conf" -D
cat > "$proof/apache.conf" <<EOF
ServerRoot /etc/apache2
PidFile "$proof/run/apache.pid"
Listen 127.0.0.1:8875
ServerName 127.0.0.1
LoadModule mpm_event_module $apache_modules/mod_mpm_event.so
LoadModule authz_core_module $apache_modules/mod_authz_core.so
LoadModule authz_host_module $apache_modules/mod_authz_host.so
LoadModule mime_module $apache_modules/mod_mime.so
LoadModule dir_module $apache_modules/mod_dir.so
LoadModule alias_module $apache_modules/mod_alias.so
LoadModule rewrite_module $apache_modules/mod_rewrite.so
LoadModule headers_module $apache_modules/mod_headers.so
LoadModule env_module $apache_modules/mod_env.so
LoadModule expires_module $apache_modules/mod_expires.so
LoadModule filter_module $apache_modules/mod_filter.so
LoadModule deflate_module $apache_modules/mod_deflate.so
LoadModule setenvif_module $apache_modules/mod_setenvif.so
LoadModule proxy_module $apache_modules/mod_proxy.so
LoadModule proxy_fcgi_module $apache_modules/mod_proxy_fcgi.so
User $(id -un)
Group $(id -gn)
ErrorLog "$proof/logs/apache.log"
DocumentRoot "$wordpress"
DirectoryIndex index.php
TypesConfig /etc/mime.types
<Directory "$wordpress">
Options FollowSymLinks
AllowOverride All
Require all granted
</Directory>
<FilesMatch "\\.php$">
SetHandler "proxy:unix:$proof/run/fpm.sock|fcgi://localhost/"
</FilesMatch>
<FilesMatch "\\.html(?:_gzip)?$">
Header always set X-NoobRocket-Static hit
</FilesMatch>
EOF
apache2 -f "$proof/apache.conf" -k start
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" http
"$node" "$workspace/tools/production-check.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --dependencies "$dependencies" --require-linux yes --require-mysql yes --require-multisite no --out "$proof/apache-preflight.json"
apache2 -f "$proof/apache.conf" -k stop
attempt=0
while [ -e "$proof/run/apache.pid" ]; do attempt=$((attempt+1)); test "$attempt" -lt 30; sleep 1; done
cat > "$proof/nginx.conf" <<EOF
pid $proof/run/nginx.pid;
error_log $proof/logs/nginx.log;
events { worker_connections 128; }
http {
include /etc/nginx/mime.types;
access_log off;
client_body_temp_path $proof/run/client-body;
fastcgi_temp_path $proof/run/fastcgi;
proxy_temp_path $proof/run/proxy;
scgi_temp_path $proof/run/scgi;
uwsgi_temp_path $proof/run/uwsgi;
map \$request_method \$cache_method { default 0; GET 1; }
map \$query_string \$cache_query { default 0; "" 1; }
map \$http_cookie \$cache_cookie { default 1; ~*wordpress_logged_in_|wp-postpass_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_ 0; }
map "\$cache_method\$cache_query\$cache_cookie" \$cache_file { default /__no_cache__; 111 /wp-content/cache/noobrocket/pages/\$host\$uri/index.html; }
server {
listen 127.0.0.1:8875;
server_name 127.0.0.1;
root $wordpress;
index index.php;
location ~ ^/wp-content/cache/noobrocket/pages/.*\\.html$ { add_header X-NoobRocket-Static hit always; }
location / { if (-f \$document_root\$cache_file) { rewrite ^ \$cache_file last; } try_files \$uri \$uri/ /index.php?\$args; }
location ~ \\.php$ { include /etc/nginx/fastcgi_params; fastcgi_param HTTP_HOST \$http_host; fastcgi_param SERVER_PORT \$server_port; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_pass unix:$proof/run/fpm.sock; }
}
}
EOF
nginx -c "$proof/nginx.conf" -p "$proof"
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" http
"$node" "$workspace/tools/server-load.mjs" --duration 2 --interval 1000 --out "$proof/host-load-idle.json"
"$php_bin" "$workspace/tests/wordpress-local-css-settings.php" "$wordpress"
"$php_bin" "$workspace/tests/wordpress-local-css.php" "$wordpress"
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" worker-prepare
"$node" "$workspace/tools/server-load.mjs" --duration 10 --interval 1000 --out "$proof/host-load-worker.json" &
sampler_pid=$!
"$node" "$wordpress/wp-content/plugins/noobrocket/local-css/worker.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --site-url http://127.0.0.1:8875/ --check
"$node" "$wordpress/wp-content/plugins/noobrocket/local-css/worker.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --site-url http://127.0.0.1:8875/ --once --limit 2 --max-seconds 30 --delay-ms 1000
wait "$sampler_pid"
sampler_pid=''
"$php_bin" "$workspace/tests/linux-production.php" "$wordpress" worker-check
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" network-install
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" network-activate
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" prepare
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" prepare subsite
"$node" "$wordpress/wp-content/plugins/noobrocket/local-css/worker.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --site-url http://127.0.0.1:8875/ --check
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" hold-main
"$node" "$wordpress/wp-content/plugins/noobrocket/local-css/worker.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --site-url http://127.0.0.1:8875/subsite/ --check
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" check
"$php_bin" "$workspace/tests/linux-multisite.php" "$wordpress" invalid-site
"$node" "$workspace/tools/production-check.mjs" --wordpress "$wordpress" --php "$php_bin" --browser "$browser" --site-url http://127.0.0.1:8875/subsite/ --dependencies "$dependencies" --require-linux yes --require-mysql yes --require-multisite yes --out "$proof/multisite-preflight.json"
if [ -s "$wordpress/wp-content/debug.log" ]; then
  echo 'WordPress debug log is not empty after the production proof:' >&2
  tail -n 20 "$wordpress/wp-content/debug.log" >&2
  exit 1
fi
echo 'WordPress debug log empty after single-site and network proof.'
echo 'Linux/MariaDB/Apache/Nginx/sandbox proof complete. This fixture does not prove every production server configuration or installed WooCommerce.'
