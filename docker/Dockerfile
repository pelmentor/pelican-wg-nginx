# =============================================================================
# Pelican Egg: WireGuard + Nginx + PHP-FPM
# =============================================================================
# Базовый образ — Ubuntu 22.04 (LTS). Выбран потому что:
# 1. Стабильная база с долгой поддержкой
# 2. Полноценный пакетный менеджер (в отличие от Alpine, где WG имеет нюансы)
# 3. Совместимость с Pelican/Pterodactyl (большинство egg на Debian/Ubuntu)
# =============================================================================

FROM ubuntu:22.04

LABEL maintainer="pelmentor"
LABEL description="WireGuard VPN client + Nginx + PHP-FPM for Pelican Panel"

ENV DEBIAN_FRONTEND=noninteractive

# =============================================================================
# Установка пакетов — один RUN-слой для минимизации размера.
# =============================================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    # WireGuard
    wireguard-tools \
    iproute2 \
    iptables \
    # Nginx
    nginx \
    # PHP-FPM + модули (BlueMap/Dynmap могут использовать PHP)
    php-fpm \
    php-cli \
    php-mbstring \
    php-curl \
    php-gd \
    php-zip \
    # Утилиты
    curl \
    ca-certificates \
    procps \
    tini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# =============================================================================
# Пользователь container (UID 1000) — стандарт Pelican/Pterodactyl.
# Ref: pterodactyl.io/community/config/eggs/creating_a_custom_image.html
# "All containers must have a user named container with home /home/container"
# =============================================================================
RUN useradd -m -d /home/container -s /bin/bash -u 1000 container

# =============================================================================
# Директории.
# /defaults/ — бэкап конфигов, entrypoint копирует их если пользователь удалил
# Ref: linuxserver — паттерн /defaults/ для восстановления конфигов
# =============================================================================
RUN mkdir -p \
    /home/container/{webroot,wg,nginx,php,logs,tmp} \
    /defaults \
    /var/lib/nginx/{body,proxy,fastcgi} \
    /run/nginx /run/php /tmp/nginx \
    && chown -R container:container \
    /home/container /var/lib/nginx /var/log/nginx \
    /run/nginx /run/php /tmp/nginx

# Конфиги: копируем И в /home/container (рабочие), И в /defaults (бэкап)
COPY nginx.conf /defaults/nginx.conf
COPY php-fpm.conf /defaults/php-fpm.conf
COPY --chown=container:container nginx.conf /home/container/nginx/nginx.conf
COPY --chown=container:container php-fpm.conf /home/container/php/php-fpm.conf

# Entrypoint
COPY --chmod=755 entrypoint.sh /entrypoint.sh

WORKDIR /home/container

# =============================================================================
# Health check: проверяет цепочку nginx → php-fpm → socket.
# Ref: trafex/docker-php-nginx — fpm-ping лучший паттерн.
# =============================================================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl --silent --fail http://127.0.0.1:${SERVER_PORT:-8080}/fpm-ping || exit 1

# =============================================================================
# Tini как PID 1.
# Ref: RESEARCH.md §2.4 — tini vs s6-overlay vs supervisord.
# -g = пробрасывает сигнал всей process group.
# =============================================================================
ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/entrypoint.sh"]
