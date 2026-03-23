# =============================================================================
# WG-Nginx: WireGuard + Nginx + PHP 8.1 FPM + Admin Panel + WebSocket
# =============================================================================
# Standalone Docker image for Unraid (or any Docker host).
# Runs as ROOT with --cap-add=NET_ADMIN.
#
# Two web interfaces:
#   :USER_PORT  (default 7890) — user web content from /data/webroot/
#   :ADMIN_PORT (default 9876) — admin panel + WebSocket console
#
# Process model:
#   Nginx master: root → workers: www-data
#   PHP-FPM: www-data
#   WireGuard: root (oneshot)
#   ws-server: Go binary, streams logs via WebSocket (port 6790, internal)
# =============================================================================

# --- Stage 1: Build Go WebSocket server ---
FROM golang:1.21-alpine AS ws-builder
WORKDIR /build
COPY app/ws/main.go app/ws/go.mod ./
RUN go mod tidy && go build -ldflags="-s -w" -o /ws-server .

# --- Stage 2: Runtime image ---
FROM ubuntu:22.04

LABEL maintainer="pelmentor"
LABEL description="WireGuard + Nginx + PHP 8.1 FPM + Admin Panel"

ENV DEBIAN_FRONTEND=noninteractive \
    USER_PORT=7890 \
    ADMIN_PORT=9876 \
    ADMIN_PASSWORD="" \
    WG_PRIVATE_KEY="" \
    WG_ADDRESS="10.0.0.2/24" \
    WG_PEER_PUBLIC_KEY="" \
    WG_PEER_ENDPOINT="" \
    WG_PEER_ALLOWED_IPS="10.0.0.0/24" \
    WG_PRESHARED_KEY="" \
    WG_LISTEN_PORT=""

# =============================================================================
# Packages — pinned to php8.1-* for reproducibility
# sudo: needed by wg-quick (calls sudo internally even as root)
# =============================================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    wireguard-tools \
    iproute2 \
    iptables \
    sudo \
    nginx \
    php8.1-fpm \
    php8.1-cli \
    php8.1-mbstring \
    php8.1-curl \
    php8.1-gd \
    php8.1-zip \
    php8.1-xml \
    php8.1-opcache \
    curl \
    ca-certificates \
    procps \
    iputils-ping \
    tini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# =============================================================================
# Directory structure
# =============================================================================
RUN mkdir -p /data/{webroot,wg,nginx,php,logs,tmp/nginx} \
    && chown -R www-data:www-data /data \
    && ln -sf /data/logs/nginx-error.log /var/log/nginx/error.log

# Copy Go WebSocket binary from builder stage
COPY --from=ws-builder /ws-server /usr/local/bin/ws-server

# Application code (read-only at runtime)
COPY app/ /app/
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Default page
RUN echo '<!DOCTYPE html><html><head><title>Web Server</title></head><body><h1>Web server is running!</h1><p>Upload files to webroot/ via the admin panel.</p></body></html>' \
    > /app/default-index.html

WORKDIR /data

EXPOSE 7890 9876

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl --silent --fail http://127.0.0.1:${ADMIN_PORT:-9876}/api/health || exit 1

ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/entrypoint.sh"]
