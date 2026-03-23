# Changelog

## [1.1.0] — 2026-03-23

### Добавлено (на основе ресёрча существующих решений)
- RESEARCH.md — исчерпывающий анализ linuxserver/wireguard, official nginx, official php-fpm, trafex/php-nginx, pelican-eggs
- Health check через fpm-ping (паттерн trafex/docker-php-nginx) — проверяет nginx→php-fpm→socket
- nginx-health endpoint (stub_status) для мониторинга Nginx
- Nginx temp directories в /tmp/ (паттерн trafex) — fix для non-root пользователя
- Zombie cleanup в entrypoint (паттерн linuxserver/nginx)
- sysctl src_valid_mark=1 для WG client mode (паттерн linuxserver/wireguard)
- PHP-FPM: catch_workers_output, decorate_workers_output (паттерн official php-fpm)
- OPNSENSE.md — документация port forward для OPNsense
- WINGS-CONFIG.md — подробная настройка Wings config.yml

### Изменено
- Graceful shutdown: SIGQUIT вместо SIGTERM (паттерн official nginx + php-fpm)
- Расширены комментарии в Dockerfile, entrypoint, конфигах — ссылки на prior art
- ARCHITECTURE.md: добавлена секция "Архитектурные решения и Prior Art"
- ARCHITECTURE.md: обновлена схема сети (Unraid + OPNsense)

## [1.0.0] — 2026-03-23

### Добавлено
- Первоначальная версия egg
- Dockerfile на базе Ubuntu 22.04 с WireGuard, Nginx и PHP-FPM
- Entrypoint-скрипт с автозапуском всех сервисов и graceful shutdown
- Egg JSON для Pelican Panel с переменными конфигурации WireGuard
- Конфиги Nginx и PHP-FPM, адаптированные под непривилегированного пользователя
- Документация: README, ARCHITECTURE, комментарии в коде
