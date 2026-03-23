# Changelog

## [1.3.0] — 2026-03-23

### Added
- PresharedKey support via `WG_PRESHARED_KEY` variable for optional symmetric encryption layer on top of WireGuard's standard key exchange
- Version display at startup: prints Ubuntu, Nginx, PHP, WireGuard versions and loaded PHP extensions
- Error log tailing to Pelican console — `nginx-error.log` and `php-fpm-error.log` are streamed in real time via `tail -F`
- WireGuard output logging to `logs/wireguard.log` (also printed to console at startup)
- Log rotation at startup: log files exceeding 10 MB are truncated to the last 1000 lines
- Comprehensive code comments throughout the entrypoint covering trap handlers, cleanup flow, `wait -n` behavior, and signal propagation

### Changed
- Egg description now shows PHP 8.1, Ubuntu 22.04, and lists available PHP extensions

### Removed
- All Minecraft, BlueMap, and Dynmap references from public-facing files

## [1.2.0] — 2026-03-23

### Changed
- Migrated Nginx temp directories from `/tmp/` to `/home/container/tmp/nginx/` — all
  temp paths (`client_body_temp_path`, `proxy_temp_path`, `fastcgi_temp_path`) now live
  under the container home directory, avoiding permission issues and `/tmp` cleanup
  interference on the host
- Moved PHP-FPM socket from `/tmp/php/php-fpm.sock` to `/home/container/tmp/php-fpm.sock`
- Moved Nginx PID file from `/tmp/nginx.pid` to `/home/container/tmp/nginx.pid`
- All runtime paths are now relative to `/home/container/tmp/` so the container works
  correctly as UID 1000 (non-root) without relying on world-writable `/tmp`

## [1.1.0] — 2026-03-23

### Added (based on research of existing solutions)
- RESEARCH.md — comprehensive analysis of linuxserver/wireguard, official nginx, official php-fpm, trafex/php-nginx, pelican-eggs
- Health check via fpm-ping (trafex/docker-php-nginx pattern) — verifies the nginx -> php-fpm -> socket chain
- Nginx health endpoint (stub_status) for Nginx monitoring
- Nginx temp directories under /home/container/tmp/ (trafex pattern) — fix for non-root user
- Zombie process cleanup in entrypoint (linuxserver/nginx pattern)
- sysctl src_valid_mark=1 for WG client mode (linuxserver/wireguard pattern)
- PHP-FPM: catch_workers_output, decorate_workers_output (official php-fpm pattern)
- OPNSENSE.md — port forward documentation for OPNsense
- WINGS-CONFIG.md — detailed Wings config.yml setup guide

### Changed
- Graceful shutdown: SIGQUIT instead of SIGTERM (official nginx + php-fpm pattern)
- Extended comments in Dockerfile, entrypoint, and configs with prior art references
- ARCHITECTURE.md: added "Architectural Decisions and Prior Art" section
- ARCHITECTURE.md: updated network diagram (Unraid + OPNsense)

## [1.0.0] — 2026-03-23

### Added
- Initial egg release
- Dockerfile based on Ubuntu 22.04 with WireGuard, Nginx, and PHP-FPM
- Entrypoint script with auto-start of all services and graceful shutdown
- Egg JSON for Pelican Panel with WireGuard configuration variables
- Nginx and PHP-FPM configs adapted for unprivileged user
- Documentation: README, ARCHITECTURE, inline code comments
