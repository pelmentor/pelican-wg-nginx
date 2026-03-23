#!/bin/bash
# =============================================================================
# Entrypoint for WireGuard + Nginx + PHP-FPM Pelican Egg
# =============================================================================
#
# ENVIRONMENT CONSTRAINTS (Pelican-specific):
#   - Container runs as UID 1000 (user "container"), NOT root
#   - System dirs (/etc, /run, /var/log) are READ-ONLY
#   - Only /home/container/ is writable (persistent volume from Wings)
#   - Pelican sends "^^C" (SIGINT) to stop the server (egg config.stop)
#
# PROCESS MODEL:
#   PID 1: tini (zombie reaper + signal forwarder, see Dockerfile)
#     └── entrypoint.sh (this script)
#           ├── php-fpm (background, --nodaemonize)
#           ├── nginx (background, daemon off)
#           ├── tail -F (background, streams error logs to Pelican console)
#           └── wait -n (blocks until any child exits)
#
#   WireGuard is NOT a process — it runs in the kernel. wg-quick only
#   configures the interface and exits (oneshot pattern).
#
# STARTUP ORDER:
#   0. Show versions, cleanup zombies, rotate logs, create dirs
#   1. Generate wg0.conf from env vars → wg-quick up (if WG keys set)
#   2. Substitute {{placeholders}} in nginx.conf and php-fpm.conf
#   3. Start PHP-FPM → wait for unix socket to appear
#   4. Start Nginx
#   5. Print "All services started successfully!" (Pelican done marker)
#   6. Tail error logs to Pelican console
#   7. wait -n — block until any child exits → trigger cleanup
#
# SHUTDOWN (trap → cleanup function):
#   1. Kill tail process (log streamer)
#   2. Send SIGQUIT to Nginx (graceful: finishes in-flight requests)
#   3. Send SIGQUIT to PHP-FPM (graceful: finishes active workers)
#   4. Wait for both to exit
#   5. wg-quick down (removes WG interface and routes)
#   6. Exit 0
#
#   Why SIGQUIT and not SIGTERM:
#   SIGQUIT = graceful shutdown in both nginx and php-fpm (official Docker
#   images use STOPSIGNAL SIGQUIT for this reason). SIGTERM would kill
#   them immediately, dropping in-flight requests.
#
# SIGNAL FLOW:
#   Pelican Panel → "^^C" → Wings → SIGINT → Docker → tini (PID 1)
#   tini forwards SIGINT to entrypoint.sh (direct child)
#   entrypoint.sh trap catches SIGINT → calls cleanup()
#   cleanup() sends SIGQUIT to nginx and php-fpm (graceful)
#
#   The -g flag on tini also forwards signals to the entire process group,
#   so even if the trap doesn't fire, all children receive the signal.
# =============================================================================

# Exit immediately on error. Treat unset variables as errors.
# Fail pipelines on first error (not just last command).
set -euo pipefail

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()  { echo -e "${GREEN}[INFO]${NC} $*"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_error() { echo -e "${RED}[ERROR]${NC} $*"; }
log_step()  { echo -e "${CYAN}[STEP]${NC} $*"; }

# ---------------------------------------------------------------------------
# Paths — all under /home/container/ (persistent Pelican volume)
# No /tmp, /run, /etc — they are ephemeral or read-only in Pelican.
# ---------------------------------------------------------------------------
HC="/home/container"
WG_CONF="${HC}/wg/wg0.conf"
PHP_FPM_SOCK="${HC}/tmp/php-fpm.sock"
NGINX_PID_FILE="${HC}/tmp/nginx.pid"
NGINX_TEMP="${HC}/tmp/nginx"

# ---------------------------------------------------------------------------
# Graceful shutdown handler
# ---------------------------------------------------------------------------
# This function is called when the container receives a stop signal.
# It is registered as a trap for SIGTERM and SIGINT (see below).
#
# Shutdown order matters:
#   1. Kill tail first — stop streaming logs (cosmetic, avoids noise)
#   2. SIGQUIT to nginx — stops accepting new connections, finishes current
#   3. SIGQUIT to php-fpm — finishes processing active PHP requests
#   4. wait — ensure both have fully exited before proceeding
#   5. wg-quick down — removes WG interface, cleans up routes and iptables
#
# Why "|| true" after every kill/wait:
#   The process might already be dead (crashed earlier, or never started).
#   Without "|| true", the script would exit on error due to set -e.
#
# Ref: official nginx image uses STOPSIGNAL SIGQUIT
# Ref: official php-fpm image uses STOPSIGNAL SIGQUIT
# Ref: linuxserver/wireguard tears down tunnels in reverse order
# ---------------------------------------------------------------------------
cleanup() {
    log_info "Shutting down..."
    kill "$TAIL_PID" 2>/dev/null || true        # 1. Stop log tail
    kill -SIGQUIT "$NGINX_PID" 2>/dev/null || true    # 2. Graceful nginx stop
    kill -SIGQUIT "$PHP_FPM_PID" 2>/dev/null || true  # 3. Graceful php-fpm stop
    wait "$NGINX_PID" 2>/dev/null || true        # 4. Wait for nginx exit
    wait "$PHP_FPM_PID" 2>/dev/null || true      #    Wait for php-fpm exit
    wg-quick down "$WG_CONF" 2>/dev/null || true # 5. Tear down WG interface
    log_info "Shutdown complete."
    exit 0
}

# Register the cleanup function as a signal handler.
# SIGTERM: sent by Docker on "docker stop" (default)
# SIGINT:  sent by Pelican via "^^C" (egg config.stop)
# When either signal arrives, bash interrupts the current command (wait -n)
# and executes cleanup() instead of the default behavior (terminate).
trap cleanup SIGTERM SIGINT

# ===========================================================================
# STEP 0: Prepare directories
# ===========================================================================
log_step "Preparing environment..."

# Show versions — so the user knows exactly what's running
log_info "Stack: Ubuntu $(cat /etc/lsb-release 2>/dev/null | grep RELEASE | cut -d= -f2 || echo '22.04'), Nginx $(nginx -v 2>&1 | cut -d/ -f2), PHP $(php -r 'echo PHP_VERSION;'), WG $(wg --version 2>/dev/null | head -1 || echo 'tools installed')"
log_info "PHP extensions: $(php -m 2>/dev/null | grep -E '^(curl|gd|mbstring|zip|json|xml)$' | tr '\n' ' ')"

# Ref: linuxserver/nginx — zombie cleanup before start
for proc in nginx php-fpm; do
    if pgrep -x "$proc" >/dev/null 2>&1; then
        log_warn "Stale $proc processes found, killing..."
        pkill -x "$proc" 2>/dev/null || true
        sleep 1
    fi
done

# All runtime dirs under /home/container/ — persistent and visible in File Manager
mkdir -p "${HC}"/{webroot,wg,nginx,php,logs,tmp}
mkdir -p "${NGINX_TEMP}"

# Clean stale socket/pid from previous run
rm -f "$PHP_FPM_SOCK" "$NGINX_PID_FILE"

# ---------------------------------------------------------------------------
# Log rotation — simple startup-time rotation to prevent disk fill.
# ---------------------------------------------------------------------------
# Why not logrotate? This is a minimal image — installing logrotate would
# add cron, config files, and complexity. Instead, we check log sizes
# once at each container start.
#
# How it works:
#   - For each .log file in /home/container/logs/
#   - If the file exceeds 10MB (10485760 bytes)
#   - Keep only the last 1000 lines, discard the rest
#   - This preserves recent logs while reclaiming disk space
#
# For long-running containers between restarts, Docker's own log driver
# limits output (see Wings config: docker.log_config.max-size).
# ---------------------------------------------------------------------------
LOG_MAX_BYTES=10485760  # 10MB
for logfile in "${HC}/logs/"*.log; do
    if [ -f "$logfile" ] && [ "$(stat -c%s "$logfile" 2>/dev/null || echo 0)" -gt "$LOG_MAX_BYTES" ]; then
        log_warn "Rotating $logfile (exceeded 10MB)"
        tail -n 1000 "$logfile" > "${logfile}.tmp"
        mv "${logfile}.tmp" "$logfile"
    fi
done

# Default page if webroot is empty
if [ ! -f "${HC}/webroot/index.html" ] && [ -z "$(ls -A "${HC}/webroot/" 2>/dev/null)" ]; then
    cat > "${HC}/webroot/index.html" <<'DEFAULTPAGE'
<!DOCTYPE html>
<html>
<head><title>Web Server</title></head>
<body>
<h1>Web server is running!</h1>
<p>Upload your files to the <code>webroot/</code> directory via Pelican File Manager.</p>
</body>
</html>
DEFAULTPAGE
fi

# ===========================================================================
# STEP 1: WireGuard
# ===========================================================================
log_step "Configuring WireGuard..."

# Ref: linuxserver/wireguard — sysctl for WG client mode
sysctl -w net.ipv4.conf.all.src_valid_mark=1 2>/dev/null || \
    log_warn "Cannot set sysctl src_valid_mark (not critical if WG is server-mode)"

WG_ENABLED=false

if [ -z "${WG_PRIVATE_KEY:-}" ]; then
    log_warn "WG_PRIVATE_KEY not set — WireGuard disabled"
    log_warn "Set WireGuard variables in Pelican Panel to enable VPN"
else
    # ListenPort — optional. Without it WG works as pure client.
    WG_LISTEN_PORT_LINE=""
    if [ -n "${WG_LISTEN_PORT:-}" ]; then
        WG_LISTEN_PORT_LINE="ListenPort = ${WG_LISTEN_PORT}"
    fi

    # Endpoint — optional. Not needed if the peer connects to us.
    WG_ENDPOINT_LINE=""
    if [ -n "${WG_PEER_ENDPOINT:-}" ]; then
        WG_ENDPOINT_LINE="Endpoint = ${WG_PEER_ENDPOINT}"
    fi

    # PresharedKey — optional. Extra layer of symmetric-key encryption.
    WG_PRESHARED_KEY_LINE=""
    if [ -n "${WG_PRESHARED_KEY:-}" ]; then
        WG_PRESHARED_KEY_LINE="PresharedKey = ${WG_PRESHARED_KEY}"
    fi

    cat > "$WG_CONF" <<EOF
# Auto-generated by entrypoint.sh from Pelican env vars
# Changes will be overwritten on container restart

[Interface]
PrivateKey = ${WG_PRIVATE_KEY}
Address = ${WG_ADDRESS:-10.0.0.2/24}
${WG_LISTEN_PORT_LINE}

[Peer]
PublicKey = ${WG_PEER_PUBLIC_KEY:-}
${WG_PRESHARED_KEY_LINE}
${WG_ENDPOINT_LINE}
AllowedIPs = ${WG_PEER_ALLOWED_IPS:-10.0.0.0/24}
PersistentKeepalive = 25
EOF
    chmod 600 "$WG_CONF"

    WG_LOG="${HC}/logs/wireguard.log"

    log_info "Starting WireGuard..."
    # Log wg-quick output to both console and file for debugging
    if wg-quick up "$WG_CONF" 2>&1 | tee -a "$WG_LOG"; then
        WG_ENABLED=true
        log_info "WireGuard is up:"
        wg show wg0 2>&1 | tee -a "$WG_LOG" | sed 's/^/  /'
    else
        log_error "WireGuard failed to start! Check your keys and endpoint."
        log_error "See ${WG_LOG} for details."
        log_error "Container will continue without VPN."
    fi
fi

# ===========================================================================
# STEP 2: Configure Nginx and PHP-FPM
# ===========================================================================
log_step "Configuring Nginx and PHP-FPM..."

# Pelican provides the primary allocation port via SERVER_PORT.
log_info "Port env: SERVER_PORT=${SERVER_PORT:-<unset>}, P_SERVER_PORT=${P_SERVER_PORT:-<unset>}"

SERVER_PORT="${SERVER_PORT:-${P_SERVER_PORT:-7890}}"

# If Pelican passed 0 (no allocation), fall back to 7890
if [ "$SERVER_PORT" = "0" ] || [ -z "$SERVER_PORT" ]; then
    log_warn "SERVER_PORT is 0 or empty — defaulting to 7890"
    SERVER_PORT=7890
fi

# Detect PHP-FPM binary version (8.1 on Ubuntu 22.04)
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_FPM_BIN="/usr/sbin/php-fpm${PHP_VERSION}"

# Restore default configs if user deleted them via File Manager
if [ ! -f "${HC}/nginx/nginx.conf" ]; then
    log_warn "nginx.conf missing — restoring default"
    cp /defaults/nginx.conf "${HC}/nginx/nginx.conf"
fi
if [ ! -f "${HC}/php/php-fpm.conf" ]; then
    log_warn "php-fpm.conf missing — restoring default"
    cp /defaults/php-fpm.conf "${HC}/php/php-fpm.conf"
fi

# Substitute placeholders in configs
sed -i "s/{{SERVER_PORT}}/${SERVER_PORT}/g" "${HC}/nginx/nginx.conf"
sed -i "s|{{PHP_FPM_SOCK}}|${PHP_FPM_SOCK}|g" "${HC}/nginx/nginx.conf"
sed -i "s|{{PHP_FPM_SOCK}}|${PHP_FPM_SOCK}|g" "${HC}/php/php-fpm.conf"
sed -i "s|{{NGINX_PID_FILE}}|${NGINX_PID_FILE}|g" "${HC}/nginx/nginx.conf"
sed -i "s|{{NGINX_TEMP}}|${NGINX_TEMP}|g" "${HC}/nginx/nginx.conf"

# ===========================================================================
# STEP 3: Start PHP-FPM
# ===========================================================================
log_step "Starting PHP-FPM ${PHP_VERSION}..."

"$PHP_FPM_BIN" \
    --fpm-config "${HC}/php/php-fpm.conf" \
    --nodaemonize &
PHP_FPM_PID=$!

# Wait for socket creation (~1s)
for i in $(seq 1 10); do
    [ -S "$PHP_FPM_SOCK" ] && break
    sleep 0.5
done

if [ -S "$PHP_FPM_SOCK" ]; then
    log_info "PHP-FPM started (PID: $PHP_FPM_PID, socket: $PHP_FPM_SOCK)"
else
    log_error "PHP-FPM socket not created after 5s! Check php-fpm.conf"
fi

# ===========================================================================
# STEP 4: Start Nginx
# ===========================================================================
log_step "Starting Nginx on port ${SERVER_PORT}..."

nginx -c "${HC}/nginx/nginx.conf" &
NGINX_PID=$!
log_info "Nginx started (PID: $NGINX_PID)"

# ===========================================================================
# Ready!
# This line is REQUIRED — Pelican watches stdout for it to mark
# the server as "Running" (see egg config.startup.done).
# ===========================================================================
echo ""
log_info "============================================"
log_info "  All services started successfully!"
log_info "  Web server: http://0.0.0.0:${SERVER_PORT}"
if [ "$WG_ENABLED" = true ]; then
    log_info "  WireGuard:  ${WG_ADDRESS:-10.0.0.2/24}"
fi
log_info "============================================"
echo ""

# ===========================================================================
# STEP 5: Stream error logs to Pelican console
# ===========================================================================
# tail -F follows log files even if they are recreated (unlike tail -f).
# Only error logs are streamed — access log would flood the console.
# Access logs are still available in File Manager: logs/nginx-access.log
#
# touch ensures the files exist before tail starts (tail -F would wait
# for them, but touch avoids a confusing "file not found" message).
# ===========================================================================
touch "${HC}/logs/nginx-error.log" "${HC}/logs/php-fpm-error.log"
tail -F "${HC}/logs/nginx-error.log" "${HC}/logs/php-fpm-error.log" 2>/dev/null &
TAIL_PID=$!

# ===========================================================================
# STEP 6: Wait for service exit
# ===========================================================================
# "wait -n" (bash 4.3+) blocks until ANY of the listed PIDs exits.
# This is the core supervision mechanism:
#
#   - If nginx crashes → wait -n returns → cleanup runs → container exits
#   - If php-fpm crashes → same
#   - If SIGTERM/SIGINT arrives → trap fires → cleanup runs → container exits
#
# Why not restart the crashed service?
#   In a single-container Pelican model, a partial restart is risky.
#   Pelican's crash detection (Wings) can restart the entire container,
#   which gives a clean state. This is simpler and more reliable than
#   in-container service supervision (which would need s6-overlay).
#
# The "|| true" prevents set -e from killing the script if wait -n
# returns a non-zero exit code (which it does when a child crashes).
# ===========================================================================
wait -n "$PHP_FPM_PID" "$NGINX_PID" 2>/dev/null || true
log_error "A service has exited unexpectedly!"
cleanup
