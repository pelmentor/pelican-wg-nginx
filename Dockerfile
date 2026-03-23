# =============================================================================
# WG-Nginx: WireGuard + Nginx + PHP 8.1 FPM + Admin Panel
# =============================================================================
# Standalone Docker image for Unraid (or any Docker host).
# Runs as ROOT with --cap-add=NET_ADMIN.
#
# Two web interfaces:
#   :USER_PORT  (default 7890) — user web content from /data/webroot/
#   :ADMIN_PORT (default 9876) — admin panel
#
# Process model:
#   Nginx master: root (binds ports, spawns workers)
#   Nginx workers: www-data (handles requests)
#   PHP-FPM:       www-data (same user — socket permissions just work)
#   WireGuard:     root (kernel interface, oneshot)
#
# Persistent volume: /data/ (mount to host)
# =============================================================================

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
# Packages
# =============================================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    wireguard-tools \
    iproute2 \
    iptables \
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
# /data owned by www-data so Nginx workers and PHP-FPM can write to it.
# Entrypoint also runs chown at startup for volumes mounted from host.
RUN mkdir -p /data/{webroot,wg,nginx,php,logs,tmp/nginx} \
    && chown -R www-data:www-data /data \
    && ln -sf /data/logs/nginx-error.log /var/log/nginx/error.log

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
