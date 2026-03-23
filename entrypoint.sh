#!/bin/bash
# =============================================================================
# Entrypoint: WG-Nginx standalone container
# =============================================================================
#
# ENVIRONMENT:
#   Runs as ROOT with --cap-add=NET_ADMIN.
#   No Pelican/Wings restrictions — wg-quick works natively.
#
# PROCESS MODEL:
#   tini (PID 1)
#     └── entrypoint.sh (this script)
#           ├── wg-quick up wg0 (oneshot — configures kernel interface, exits)
#           ├── php-fpm8.1 --nodaemonize (background)
#           ├── nginx (background, master=root, workers=www-data)
#           └── tail -F logs (background, streams errors to docker logs)
#
# USER MODEL:
#   Nginx master: root (binds ports <1024 if needed)
#   Nginx workers: www-data (handles HTTP requests)
#   PHP-FPM: www-data (processes PHP, accesses /data/)
#   Socket: www-data:www-data 0660 (both sides match = no permission issues)
#   WireGuard: root (kernel interface management)
#
# STARTUP ORDER:
#   0. Print versions, create dirs, chown /data, rotate logs
#   1. WireGuard (optional, if WG_PRIVATE_KEY set)
#   2. Generate nginx/php configs from templates (sed substitution)
#   3. Start PHP-FPM, wait for socket
#   4. Start Nginx
#   5. Print admin password
#   6. Tail error logs to docker stdout
#   7. Health loop (check PIDs every 2s)
#
# SHUTDOWN (signal flow):
#   Docker stop → SIGTERM → tini → entrypoint.sh → trap → cleanup()
#   cleanup():
#     1. kill tail (stop log streaming)
#     2. nginx -s quit (SIGQUIT = graceful: finishes in-flight requests)
#     3. kill -SIGQUIT php-fpm (graceful: finishes active workers)
#     4. wait for php-fpm to exit
#     5. wg-quick down wg0 (removes interface, cleans routes)
#     6. exit 0
#
# WHY SIGQUIT (not SIGTERM):
#   Both nginx and php-fpm treat SIGQUIT as graceful shutdown —
#   they finish serving current requests before exiting.
#   SIGTERM would kill them immediately, dropping connections.
#   This matches the official Docker images (STOPSIGNAL SIGQUIT).
#
# WHY "|| true" after every kill/wait:
#   The process might already be dead (crashed, or never started).
#   Without "|| true", set -e would exit the script on error,
#   preventing cleanup of remaining services.
# =============================================================================

# -e: exit on error  -u: error on unset vars  -o pipefail: fail on pipe errors
set -euo pipefail

# ---------------------------------------------------------------------------
# Logging helpers — colored output visible in docker logs and admin console
# ---------------------------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log_info()  { echo -e "${GREEN}[INFO]${NC} $*"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_error() { echo -e "${RED}[ERROR]${NC} $*"; }
log_step()  { echo -e "${CYAN}[STEP]${NC} $*"; }

# ---------------------------------------------------------------------------
# Paths — all persistent data under /data (mounted from host)
# /app is read-only (baked into image, contains admin panel + templates)
# ---------------------------------------------------------------------------
DATA="/data"

# ---------------------------------------------------------------------------
# Signal trap — registered BEFORE any services start.
#
# trap catches SIGTERM (from docker stop) and SIGINT (from Ctrl+C).
# When either arrives, bash interrupts the current command (the sleep
# in the health loop) and jumps to cleanup().
#
# tini (PID 1) also forwards signals to the entire process group
# via its -g flag, so even if the trap doesn't fire, children
# receive the signal directly.
# ---------------------------------------------------------------------------
cleanup() {
    log_info "Shutting down..."
    kill "$TAIL_PID" 2>/dev/null || true            # Stop log streaming
    kill "$WS_PID" 2>/dev/null || true              # Stop WebSocket server
    nginx -s quit 2>/dev/null || true                # Graceful nginx stop (SIGQUIT)
    kill -SIGQUIT "$PHP_FPM_PID" 2>/dev/null || true # Graceful PHP-FPM stop
    wait "$PHP_FPM_PID" 2>/dev/null || true          # Wait for PHP-FPM to finish
    wg-quick down wg0 2>/dev/null || true            # Tear down WG interface
    log_info "Shutdown complete."
    exit 0
}
trap cleanup SIGTERM SIGINT

# ===========================================================================
# STEP 0: Prepare environment
# ===========================================================================
log_step "Preparing environment..."

# Show versions so user knows exactly what's inside the image
log_info "Stack: Ubuntu $(cat /etc/lsb-release 2>/dev/null | grep RELEASE | cut -d= -f2), Nginx $(nginx -v 2>&1 | cut -d/ -f2), PHP $(php -r 'echo PHP_VERSION;'), WG $(wg --version 2>/dev/null | head -1)"
log_info "PHP extensions: $(php -m 2>/dev/null | grep -E '^(curl|gd|mbstring|zip|json|xml)$' | tr '\n' ' ')"

# Create persistent directory structure.
# These dirs live on the host volume — they survive container restarts.
mkdir -p "${DATA}/user"/{webroot,config,logs,tmp/nginx}
mkdir -p "${DATA}/admin"/{logs,sessions,ratelimit,nginx}

# ---------------------------------------------------------------------------
# Migration — move files from old flat /data layout to new structure.
# Only runs if old /data/webroot exists (pre-restructure container).
# ---------------------------------------------------------------------------
if [ -d "${DATA}/webroot" ] && [ ! -L "${DATA}/webroot" ]; then
    log_warn "Migrating old /data layout to new /data/user + /data/admin structure..."
    # User webroot
    cp -a "${DATA}/webroot/." "${DATA}/user/webroot/" 2>/dev/null || true
    # WireGuard config
    [ -f "${DATA}/wg/wg0.conf" ] && cp -a "${DATA}/wg/wg0.conf" "${DATA}/user/config/wg0.conf" 2>/dev/null || true
    # Logs
    for f in nginx-access.log nginx-error.log php-fpm-error.log wireguard.log; do
        [ -f "${DATA}/logs/$f" ] && mv "${DATA}/logs/$f" "${DATA}/user/logs/$f" 2>/dev/null || true
    done
    for f in admin-access.log admin-error.log activity.json; do
        [ -f "${DATA}/logs/$f" ] && mv "${DATA}/logs/$f" "${DATA}/admin/logs/$f" 2>/dev/null || true
    done
    # Admin password
    [ -f "${DATA}/.admin_password" ] && mv "${DATA}/.admin_password" "${DATA}/admin/.admin_password" 2>/dev/null || true
    # Clean up old dirs
    rm -rf "${DATA}/webroot" "${DATA}/wg" "${DATA}/nginx" "${DATA}/php" "${DATA}/logs" "${DATA}/tmp"
    log_info "Migration complete."
fi

# Both nginx workers and PHP-FPM run as www-data.
# /data must be writable by www-data for: logs, uploads, configs, socket, PID.
# This runs on every start because host-mounted volumes may have root ownership.
chown -R www-data:www-data "${DATA}"

# Provide a default index page if webroot is empty (first run)
if [ ! -f "${DATA}/user/webroot/index.html" ] && [ -z "$(ls -A "${DATA}/user/webroot/" 2>/dev/null)" ]; then
    cp /app/default-index.html "${DATA}/user/webroot/index.html"
fi

# ---------------------------------------------------------------------------
# Log rotation — simple startup-time check.
# Why not logrotate: minimal image, no cron, one-time check is enough.
# Why 10MB threshold: prevents disk fill on long-running containers.
# Why keep 1000 lines: preserves recent context for debugging.
# For runtime log limits, Docker's own log driver handles it
# (see Wings config: docker.log_config.max-size).
# ---------------------------------------------------------------------------
for logdir in "${DATA}/user/logs" "${DATA}/admin/logs"; do
    for logfile in "${logdir}/"*.log; do
        if [ -f "$logfile" ] && [ "$(stat -c%s "$logfile" 2>/dev/null || echo 0)" -gt 10485760 ]; then
            log_warn "Rotating $logfile (>10MB)"
            tail -n 1000 "$logfile" > "${logfile}.tmp" && mv "${logfile}.tmp" "$logfile"
        fi
    done
done

# ===========================================================================
# STEP 1: WireGuard (optional)
# ===========================================================================
log_step "Configuring WireGuard..."

# ---------------------------------------------------------------------------
# WireGuard — persistent config at /data/user/config/wg0.conf
#
# Config source of truth is the persistent file, NOT env vars.
# Env vars only bootstrap the initial config on first run.
# After that, users edit the config via the admin panel form.
# wg-quick accepts a full path, so no copy to /etc/wireguard/ needed.
# ---------------------------------------------------------------------------
WG_ENABLED=false
WG_CONF="${DATA}/user/config/wg0.conf"

if [ -n "${WG_PRIVATE_KEY:-}" ] && [ ! -f "$WG_CONF" ]; then
    # First run with env vars — generate persistent config
    log_info "Generating WireGuard config from environment variables..."

    WG_LISTEN_PORT_LINE=""
    [ -n "${WG_LISTEN_PORT:-}" ] && WG_LISTEN_PORT_LINE="ListenPort = ${WG_LISTEN_PORT}"
    WG_ENDPOINT_LINE=""
    [ -n "${WG_PEER_ENDPOINT:-}" ] && WG_ENDPOINT_LINE="Endpoint = ${WG_PEER_ENDPOINT}"
    WG_PSK_LINE=""
    [ -n "${WG_PRESHARED_KEY:-}" ] && WG_PSK_LINE="PresharedKey = ${WG_PRESHARED_KEY}"

    cat > "$WG_CONF" <<EOF
[Interface]
PrivateKey = ${WG_PRIVATE_KEY}
Address = ${WG_ADDRESS:-10.0.0.2/24}
${WG_LISTEN_PORT_LINE}

[Peer]
PublicKey = ${WG_PEER_PUBLIC_KEY:-}
${WG_PSK_LINE}
${WG_ENDPOINT_LINE}
AllowedIPs = ${WG_PEER_ALLOWED_IPS:-10.0.0.0/24}
PersistentKeepalive = 25
EOF
fi

if [ -f "$WG_CONF" ]; then
    # Config exists (from env vars, previous run, or admin panel) — start WG
    chmod 600 "$WG_CONF"
    log_info "Starting WireGuard from ${WG_CONF}..."

    if wg-quick up "$WG_CONF" 2>&1 | tee -a "${DATA}/user/logs/wireguard.log"; then
        WG_ENABLED=true
        log_info "WireGuard is up:"
        wg show wg0 | sed 's/^/  /'
    else
        log_error "WireGuard failed to start! See user/logs/wireguard.log"
        log_error "Container will continue without VPN."
    fi
else
    log_warn "No WireGuard config found — configure via admin panel Settings > WireGuard"
fi

# ===========================================================================
# STEP 2: Generate Nginx + PHP-FPM configs from templates
# ===========================================================================
# Templates live at /app/ (baked into image, read-only).
# Generated configs go to /data/ (persistent, editable via admin panel).
# sed replaces {{PLACEHOLDERS}} with actual values from env vars.
# ===========================================================================
log_step "Configuring Nginx and PHP-FPM..."

USER_PORT="${USER_PORT:-7890}"
ADMIN_PORT="${ADMIN_PORT:-9876}"

sed -e "s/{{USER_PORT}}/${USER_PORT}/g" \
    /app/nginx/user.conf.template > "${DATA}/user/config/nginx-site.conf"

sed -e "s/{{ADMIN_PORT}}/${ADMIN_PORT}/g" \
    /app/nginx/admin.conf.template > "${DATA}/admin/nginx/admin.conf"

sed -e "s|{{DATA}}|${DATA}|g" \
    /app/nginx/nginx.conf.template > "${DATA}/admin/nginx/nginx.conf"

sed -e "s|{{DATA}}|${DATA}|g" \
    /app/php/php-fpm.conf.template > "${DATA}/user/config/php-fpm.conf"

# ---------------------------------------------------------------------------
# Admin password — auto-generated on first run if not provided via env var.
# Stored in /data/admin/.admin_password (persists across restarts).
# Hidden from file manager (FileManager.php skips .admin_password).
# Printed in startup output so user can find it in docker logs.
# ---------------------------------------------------------------------------
if [ -z "${ADMIN_PASSWORD:-}" ]; then
    if [ ! -f "${DATA}/admin/.admin_password" ]; then
        ADMIN_PASSWORD=$(head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 16)
        echo "$ADMIN_PASSWORD" > "${DATA}/admin/.admin_password"
        # Readable by www-data (PHP-FPM) for auth fallback if env var not available
        chown www-data:www-data "${DATA}/admin/.admin_password"
        chmod 640 "${DATA}/admin/.admin_password"
    else
        ADMIN_PASSWORD=$(cat "${DATA}/admin/.admin_password")
    fi
fi
export ADMIN_PASSWORD

# ---------------------------------------------------------------------------
# Default admin user — create users.json with bcrypt-hashed password.
# SECURITY: PHP generates the ENTIRE JSON to avoid shell escaping issues with
# special characters in passwords or bcrypt hashes. Using bash interpolation
# (echo/sed) would break on $ or " in the password or bcrypt's $2y$ prefix.
if [ ! -f "${DATA}/admin/users.json" ]; then
    php -r '
        $pass = getenv("ADMIN_PASSWORD");
        $hash = password_hash($pass, PASSWORD_BCRYPT, ["cost" => 12]);
        $data = ["users" => [[
            "id" => "u_admin",
            "username" => "admin",
            "password_hash" => $hash,
            "role" => "admin",
            "created_at" => time(),
            "last_login" => null,
        ]]];
        file_put_contents("/data/admin/users.json", json_encode($data, JSON_PRETTY_PRINT));
    '
    chown www-data:www-data "${DATA}/admin/users.json"
    chmod 640 "${DATA}/admin/users.json"
    FIRST_RUN_PASSWORD=true
    log_info "Default admin account created (username: admin)"
fi

# ===========================================================================
# STEP 3: Start PHP-FPM
# ===========================================================================
log_step "Starting PHP-FPM..."

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
# --nodaemonize: stay in foreground so we can track the PID.
# & at the end: run in background so this script continues.
"/usr/sbin/php-fpm${PHP_VERSION}" \
    --fpm-config "${DATA}/user/config/php-fpm.conf" \
    --nodaemonize &
PHP_FPM_PID=$!

# Wait for the unix socket to appear (PHP-FPM needs ~1s to create it).
# Without the socket, nginx can't proxy PHP requests → 502 Bad Gateway.
for i in $(seq 1 10); do [ -S "${DATA}/user/tmp/php-fpm.sock" ] && break; sleep 0.5; done

if [ -S "${DATA}/user/tmp/php-fpm.sock" ]; then
    log_info "PHP-FPM started (PID: $PHP_FPM_PID)"
else
    log_error "PHP-FPM socket not created after 5s! Check php-fpm.conf"
fi

# ===========================================================================
# STEP 4: Start WebSocket server (Go binary for real-time console)
# ===========================================================================
log_step "Starting WebSocket server..."

# The Go binary tails all .log files in /data/user/logs/ and streams new lines
# to connected WebSocket clients. Listens on 127.0.0.1:6790 (internal only).
# Nginx proxies /ws → 127.0.0.1:6790/ws for the admin panel.
ws-server --port 6790 --logdir "${DATA}/user/logs" &
WS_PID=$!
log_info "WebSocket server started (PID: $WS_PID, port: 6790)"

# ===========================================================================
# STEP 5: Start Nginx
# ===========================================================================
log_step "Starting Nginx..."

# Nginx reads the generated config from /data/admin/nginx/nginx.conf which
# includes both server blocks (user content + admin panel).
# "daemon off" is set in the config, so nginx stays in foreground.
# & runs it in background so this script can continue to the health loop.
nginx -c "${DATA}/admin/nginx/nginx.conf" &
NGINX_PID=$!
log_info "Nginx started (PID: $NGINX_PID)"

# ===========================================================================
# Ready — print connection info
# ===========================================================================
echo ""
log_info "============================================"
log_info "  All services started successfully!"
log_info "  User content:  http://0.0.0.0:${USER_PORT}"
log_info "  Admin panel:   http://0.0.0.0:${ADMIN_PORT}"
if [ "$WG_ENABLED" = true ]; then
    log_info "  WireGuard:     ${WG_ADDRESS:-10.0.0.2/24}"
fi
# Only show password on first run (when we just created the account).
# After that, user should have changed it or stored it securely.
if [ "${FIRST_RUN_PASSWORD:-}" = "true" ]; then
    log_info "  Admin login:   admin / ${ADMIN_PASSWORD}"
    log_info "  CHANGE THIS PASSWORD after first login!"
else
    log_info "  Admin login:   admin (password set previously)"
fi
log_info "============================================"
echo ""

# ===========================================================================
# STEP 5: Tail error logs to docker stdout
# ===========================================================================
# tail -F follows files even if they're recreated (log rotation).
# Only error logs — access log would flood docker logs.
# touch ensures files exist before tail starts.
# ===========================================================================
touch "${DATA}/user/logs/nginx-error.log" "${DATA}/user/logs/php-fpm-error.log" "${DATA}/admin/logs/admin-error.log"
tail -F "${DATA}/user/logs/nginx-error.log" "${DATA}/user/logs/php-fpm-error.log" "${DATA}/admin/logs/admin-error.log" 2>/dev/null &
TAIL_PID=$!

# ===========================================================================
# STEP 6: Health loop — monitor service PIDs
# ===========================================================================
# Check every 2 seconds if nginx and php-fpm are still alive.
# If either exits, break out of the loop and run cleanup.
#
# Why not "wait -n":
#   wait -n waits for any child to exit, but it also catches the tail
#   process. The PID-check loop lets us monitor specific processes.
#
# Why the container exits on crash:
#   In a single-container model, partial recovery is risky (e.g. nginx
#   up but php-fpm down = 502 errors). A full restart via Docker's
#   restart policy (--restart unless-stopped) gives a clean state.
# ===========================================================================
while true; do
    if ! kill -0 "$NGINX_PID" 2>/dev/null; then
        log_error "Nginx exited unexpectedly!"
        break
    fi
    if ! kill -0 "$PHP_FPM_PID" 2>/dev/null; then
        log_error "PHP-FPM exited unexpectedly!"
        break
    fi
    sleep 2
done

cleanup
