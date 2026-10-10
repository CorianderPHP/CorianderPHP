#!/usr/bin/env bash
set -euo pipefail
project="$(cd "$(dirname "$0")/../../.." && pwd)"
work="$(mktemp -d /tmp/coriander-production-XXXXXX)"
fpm_pid=""
nginx_pid=""
cleanup() {
    if [[ $? -ne 0 ]]; then
        for log in "$work/fpm.log" "$work/nginx.log" "$work/application.log"; do
            if [[ -f "$log" ]]; then cat "$log" >&2; fi
        done
    fi
    if [[ -n "$nginx_pid" ]]; then kill "$nginx_pid" 2>/dev/null || true; wait "$nginx_pid" 2>/dev/null || true; fi
    if [[ -n "$fpm_pid" ]]; then kill "$fpm_pid" 2>/dev/null || true; wait "$fpm_pid" 2>/dev/null || true; fi
    case "$work" in /tmp/coriander-production-*) rm -rf -- "$work" ;; esac
}
trap cleanup EXIT
mkdir "$work/app" "$work/sessions"
php "$project/CorianderCore/Tests/benchmarks/production-fixture.php" "$work/app"
cat > "$work/fpm.conf" <<EOF
[global]
error_log = $work/fpm.log
daemonize = no
[benchmark]
user = $(id -un)
group = $(id -gn)
listen = $work/fpm.sock
pm = static
pm.max_children = 8
clear_env = no
php_admin_value[session.save_path] = $work/sessions
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.file_update_protection] = 0
php_admin_value[display_errors] = 0
EOF
cat > "$work/nginx.conf" <<EOF
pid $work/nginx.pid;
error_log $work/nginx.log;
events { worker_connections 1024; }
http {
    access_log off;
    client_body_temp_path $work/client-body;
    proxy_temp_path $work/proxy;
    fastcgi_temp_path $work/fastcgi;
    uwsgi_temp_path $work/uwsgi;
    scgi_temp_path $work/scgi;
    server {
        listen 127.0.0.1:8087;
        location / {
            include /etc/nginx/fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $work/app/public/index.php;
            fastcgi_param SCRIPT_NAME /index.php;
            fastcgi_pass unix:$work/fpm.sock;
        }
    }
}
EOF
APP_ENV=production APP_DEBUG=0 LOG_CHANNEL="$work/application.log" "${PHP_FPM_BINARY:-php-fpm8.3}" -F -y "$work/fpm.conf" &
fpm_pid=$!
nginx -p "$work/" -c "$work/nginx.conf" -g 'daemon off;' &
nginx_pid=$!
for attempt in {1..50}; do
    if curl -fsS http://127.0.0.1:8087/ > /dev/null; then break; fi
    sleep .1
done
BENCHMARK_COMMIT="$(git -C "$project" rev-parse HEAD)" python3 "$project/CorianderCore/Tests/benchmarks/production-load.py" 8087 "${BENCHMARK_REQUESTS:-80}"
