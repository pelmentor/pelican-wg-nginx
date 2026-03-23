#!/bin/bash
# =============================================================================
# Entrypoint: WG-Nginx standalone container
# =============================================================================
# Runs as ROOT — no sudo workarounds, no UID 1000 restrictions.
# wg-quick works natively with --cap-add=NET_ADMIN.
#
# Startup:  WG → PHP-FPM → Nginx → tail logs
# Shutdown: SIGQUIT to nginx/php-fpm → wg-quick down → exit
# =============================================================================

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log_info()  { echo -e "${GREEN}[INFO]${NC} $*"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_error() { echo -e "${RED}[ERROR]${NC} $*"; }
log_step()  { echo -e "${CYAN}[STEP]${NC} $*"; }

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------
DATA="/data"

# ---------------------------------------------------------------------------
# Cleanup handler
# ---------------------------------------------------------------------------
cleanup() {
    log_info "Shutting down..."
    kill "$TAIL_PID" 2>/dev/null || true
    nginx -s quit 2>/dev/null || true
    kill -SIGQUIT "$PHP_FPM_PID" 2>/dev/null || true
    wait "$PHP_FPM_PID" 2>/dev/null || true
    wg-quick down wg0 2>/dev/null || true
    log_info "Shutdown complete."
    exit 0
}
trap cleanup SIGTERM SIGINT

# ===========================================================================
# STEP 0: Prepare
# ===========================================================================
log_step "Preparing environment..."
log_info "Stack: Ubuntu $(cat /etc/lsb-release 2>/dev/null | grep RELEASE | cut -d= -f2), Nginx $(nginx -v 2>&1 | cut -d/ -f2), PHP $(php -r 'echo PHP_VERSION;'), WG $(wg --version 2>/dev/null | head -1)"
log_info "PHP extensions: $(php -m 2>/dev/null | grep -E '^(curl|gd|mbstring|zip|json|xml)$' | tr '\n' ' ')"

# Ensure persistent dirs exist
mkdir -p "${DATA}"/{webroot,wg,nginx,php,logs,tmp/nginx}

# Nginx workers and PHP-FPM both run as www-data.
# /data must be writable by www-data for logs, uploads, configs, socket.
chown -R www-data:www-data "${DATA}"

# Default page if webroot is empty
if [ ! -f "${DATA}/webroot/index.html" ] && [ -z "$(ls -A "${DATA}/webroot/" 2>/dev/null)" ]; then
    cp /app/default-index.html "${DATA}/webroot/index.html"
fi

# Log rotation (>10MB → keep last 1000 lines)
for logfile in "${DATA}/logs/"*.log; do
    if [ -f "$logfile" ] && [ "$(stat -c%s "$logfile" 2>/dev/null || echo 0)" -gt 10485760 ]; then
        log_warn "Rotating $logfile (>10MB)"
        tail -n 1000 "$logfile" > "${logfile}.tmp" && mv "${logfile}.tmp" "$logfile"
    fi
done

# ===========================================================================
# STEP 1: WireGuard
# ===========================================================================
log_step "Configuring WireGuard..."

WG_ENABLED=false
if [ -z "${WG_PRIVATE_KEY:-}" ]; then
    log_warn "WG_PRIVATE_KEY not set — WireGuard disabled"
else
    WG_CONF="/etc/wireguard/wg0.conf"
    mkdir -p /etc/wireguard

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
    chmod 600 "$WG_CONF"

    # Copy to persistent storage for visibility in admin panel
    cp "$WG_CONF" "${DATA}/wg/wg0.conf"

    log_info "Starting WireGuard..."
    if wg-quick up wg0 2>&1 | tee -a "${DATA}/logs/wireguard.log"; then
        WG_ENABLED=true
        log_info "WireGuard is up:"
        wg show wg0 | sed 's/^/  /'
    else
        log_error "WireGuard failed to start! See logs/wireguard.log"
        log_error "Container will continue without VPN."
    fi
fi

# ===========================================================================
# STEP 2: Nginx + PHP-FPM configs
# ===========================================================================
log_step "Configuring Nginx and PHP-FPM..."

USER_PORT="${USER_PORT:-7890}"
ADMIN_PORT="${ADMIN_PORT:-9876}"

# Generate user nginx config from template
sed -e "s/{{USER_PORT}}/${USER_PORT}/g" \
    /app/nginx/user.conf.template > "${DATA}/nginx/user.conf"

# Generate admin nginx config with correct port
sed -e "s/{{ADMIN_PORT}}/${ADMIN_PORT}/g" \
    /app/nginx/admin.conf.template > "${DATA}/nginx/admin.conf"

# Generate master nginx.conf
sed -e "s|{{DATA}}|${DATA}|g" \
    /app/nginx/nginx.conf.template > "${DATA}/nginx/nginx.conf"

# Generate php-fpm config
sed -e "s|{{DATA}}|${DATA}|g" \
    /app/php/php-fpm.conf.template > "${DATA}/php/php-fpm.conf"

# Admin password
if [ -z "${ADMIN_PASSWORD:-}" ]; then
    if [ ! -f "${DATA}/.admin_password" ]; then
        ADMIN_PASSWORD=$(head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 16)
        echo "$ADMIN_PASSWORD" > "${DATA}/.admin_password"
        chmod 600 "${DATA}/.admin_password"
    else
        ADMIN_PASSWORD=$(cat "${DATA}/.admin_password")
    fi
fi
export ADMIN_PASSWORD

# ===========================================================================
# STEP 3: Start PHP-FPM
# ===========================================================================
log_step "Starting PHP-FPM..."

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
"/usr/sbin/php-fpm${PHP_VERSION}" \
    --fpm-config "${DATA}/php/php-fpm.conf" \
    --nodaemonize &
PHP_FPM_PID=$!

for i in $(seq 1 10); do [ -S "${DATA}/tmp/php-fpm.sock" ] && break; sleep 0.5; done
log_info "PHP-FPM started (PID: $PHP_FPM_PID)"

# ===========================================================================
# STEP 4: Start Nginx
# ===========================================================================
log_step "Starting Nginx..."

nginx -c "${DATA}/nginx/nginx.conf" &
NGINX_PID=$!
log_info "Nginx started (PID: $NGINX_PID)"

# ===========================================================================
# Ready
# ===========================================================================
echo ""
log_info "============================================"
log_info "  All services started successfully!"
log_info "  User content:  http://0.0.0.0:${USER_PORT}"
log_info "  Admin panel:   http://0.0.0.0:${ADMIN_PORT}"
if [ "$WG_ENABLED" = true ]; then
    log_info "  WireGuard:     ${WG_ADDRESS:-10.0.0.2/24}"
fi
log_info "  Admin password: ${ADMIN_PASSWORD}"
log_info "============================================"
echo ""

# ===========================================================================
# Tail error logs + wait
# ===========================================================================
touch "${DATA}/logs/nginx-error.log" "${DATA}/logs/php-fpm-error.log"
tail -F "${DATA}/logs/nginx-error.log" "${DATA}/logs/php-fpm-error.log" "${DATA}/logs/admin-error.log" 2>/dev/null &
TAIL_PID=$!

# Wait for services
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
