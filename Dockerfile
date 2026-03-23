# =============================================================================
# WG-Nginx: WireGuard + Nginx + PHP-FPM + Admin Panel
# =============================================================================
# Standalone Docker image for Unraid (or any Docker host).
# Runs as ROOT with --cap-add=NET_ADMIN — no Pelican/Wings restrictions.
#
# Two web interfaces:
#   :USER_PORT (default 7890) — user web content from /data/webroot/
#   :ADMIN_PORT (default 8443) — admin panel (Pelican-like UI)
#
# Persistent volume: /data/ (mount to host for persistence)
# =============================================================================

FROM ubuntu:22.04

LABEL maintainer="pelmentor"
LABEL description="WireGuard + Nginx + PHP 8.1 FPM + Admin Panel"

ENV DEBIAN_FRONTEND=noninteractive \
    USER_PORT=7890 \
    ADMIN_PORT=8443 \
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
    # WireGuard — runs as root, wg-quick works natively
    wireguard-tools \
    iproute2 \
    iptables \
    # Nginx — serves both user content and admin panel
    nginx \
    # PHP-FPM 8.1 + extensions for admin panel and user scripts
    php-fpm \
    php-cli \
    php-mbstring \
    php-curl \
    php-gd \
    php-zip \
    php-xml \
    php-json \
    # Utilities
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
# /app/ — application code (baked into image, read-only at runtime)
# /data/ — persistent volume (user content, configs, logs)
RUN mkdir -p /data/{webroot,wg,nginx,php,logs,tmp/nginx} \
    && ln -sf /data/logs/nginx-error.log /var/log/nginx/error.log

# Copy application code into image
COPY app/ /app/
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Default page
RUN echo '<!DOCTYPE html><html><head><title>Web Server</title></head><body><h1>Web server is running!</h1><p>Upload files to webroot/ via the admin panel.</p></body></html>' \
    > /app/default-index.html

WORKDIR /data

EXPOSE 7890 8443

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl --silent --fail http://127.0.0.1:${ADMIN_PORT:-8443}/api/health || exit 1

ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/entrypoint.sh"]
