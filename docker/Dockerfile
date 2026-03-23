# =============================================================================
# Pelican Egg: WireGuard + Nginx + PHP-FPM
# =============================================================================
# Base: Ubuntu 22.04 LTS
# - Stable, long-term support
# - Full package manager (unlike Alpine where WG has quirks)
# - Compatible with Pelican/Pterodactyl ecosystem
#
# IMPORTANT: Pelican runs containers as UID 1000 (non-root).
# System dirs (/etc, /run, /var/log) are read-only.
# ALL runtime files go to /home/container/ (persistent volume).
# =============================================================================

FROM ubuntu:22.04

LABEL maintainer="pelmentor"
LABEL description="WireGuard VPN client + Nginx + PHP-FPM for Pelican Panel"

ENV DEBIAN_FRONTEND=noninteractive

# =============================================================================
# Packages — single RUN layer to minimize image size
# =============================================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    # WireGuard — sudo is required because wg-quick calls it internally
    # (line 85 of wg-quick: "exec sudo ...") even when running as non-root
    wireguard-tools \
    iproute2 \
    iptables \
    sudo \
    # Nginx
    nginx \
    # PHP-FPM + common modules for web applications
    php-fpm \
    php-cli \
    php-mbstring \
    php-curl \
    php-gd \
    php-zip \
    # Utilities
    curl \
    ca-certificates \
    procps \
    tini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# =============================================================================
# User container (UID 1000) — Pelican/Pterodactyl standard.
# Ref: pterodactyl.io/community/config/eggs/creating_a_custom_image.html
# =============================================================================
RUN useradd -m -d /home/container -s /bin/bash -u 1000 container \
    # wg-quick uses "sudo" to run wg, ip, and sysctl commands.
    # Grant passwordless sudo for WG-related binaries only (least privilege).
    && echo "container ALL=(ALL) NOPASSWD: /usr/bin/wg, /usr/bin/wg-quick, /sbin/ip, /usr/sbin/sysctl, /sbin/iptables, /sbin/iptables-restore" \
       > /etc/sudoers.d/wireguard \
    && chmod 440 /etc/sudoers.d/wireguard

# =============================================================================
# Directories — ALL under /home/container/ (persistent Pelican volume).
# /defaults/ — config backups, entrypoint restores them if user deletes.
# Ref: linuxserver — /defaults/ pattern for config recovery.
#
# /home/container/tmp/ — runtime files (PID, sockets, nginx temp).
# We do NOT use /tmp or /run — they are ephemeral/read-only in Pelican.
# =============================================================================
RUN mkdir -p \
    /home/container/{webroot,wg,nginx,php,logs,tmp/nginx} \
    /defaults \
    /var/lib/nginx/{body,proxy,fastcgi} \
    && chown -R container:container /home/container /var/lib/nginx \
    # Symlink nginx startup log to our writable location.
    # Nginx writes to /var/log/nginx/error.log BEFORE reading config.
    && ln -sf /home/container/logs/nginx-error.log /var/log/nginx/error.log

# Configs: copy to both /home/container (working) and /defaults (backup)
COPY nginx.conf /defaults/nginx.conf
COPY php-fpm.conf /defaults/php-fpm.conf
COPY --chown=container:container nginx.conf /home/container/nginx/nginx.conf
COPY --chown=container:container php-fpm.conf /home/container/php/php-fpm.conf

# Entrypoint
COPY --chmod=755 entrypoint.sh /entrypoint.sh

WORKDIR /home/container

# =============================================================================
# Health check: validates full chain nginx → socket → php-fpm.
# Ref: trafex/docker-php-nginx — fpm-ping is the best pattern.
# =============================================================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl --silent --fail http://127.0.0.1:${SERVER_PORT:-7890}/fpm-ping || exit 1

# =============================================================================
# Tini as PID 1. Ref: RESEARCH.md §2.4
# -g = forward signals to entire process group.
# =============================================================================
ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/entrypoint.sh"]
