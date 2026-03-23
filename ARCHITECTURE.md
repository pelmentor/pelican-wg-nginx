# Architecture — WG-Nginx

## Overview

Standalone Docker container running as **root** with `--cap-add=NET_ADMIN`.
No Pelican/Wings dependency — runs directly on Unraid or any Docker host.

**Stack:** Ubuntu 22.04, Nginx 1.18, PHP-FPM 8.1, WireGuard, tini (PID 1)

## Container Layout

```
Container (root, --cap-add=NET_ADMIN)
│
├── tini (PID 1) — zombie reaper, signal forwarder
│   └── entrypoint.sh
│       ├── wg-quick up wg0 (optional, oneshot)
│       ├── php-fpm8.1 (background)
│       ├── nginx (background, two server blocks)
│       └── tail -F logs (background)
│
├── Nginx server blocks:
│   ├── :USER_PORT (default 7890) → /data/webroot/
│   └── :ADMIN_PORT (default 8443) → /app/admin/public/
│
└── Volumes:
    ├── /app/   (read-only, baked into image)
    └── /data/  (persistent, mounted from host)
```

## File Separation

| Path | Lifecycle | Contains |
|------|-----------|----------|
| `/app/` | Image (read-only) | Admin panel PHP, JS, CSS, nginx/php templates |
| `/data/` | Persistent volume | User files, configs, logs, runtime (PID, socket) |
| `/etc/wireguard/` | Runtime | Generated wg0.conf (from env vars) |

## Admin Panel

```
Browser → Nginx :8443 → index.php (front controller)
                            ├── Router → Controller
                            ├── Auth (session-based password)
                            ├── DashboardController → /api/stats
                            ├── ConsoleController → /api/console/stream (SSE)
                            ├── FilesController → /api/files/*
                            └── SettingsController → /api/settings/*
```

- **Console:** xterm.js + Server-Sent Events for real-time log streaming
- **Files:** PHP FileManager sandboxed to /data/ with path traversal protection
- **Stats:** Read /proc/* directly (CPU, memory, network, disk)
- **Auth:** Password from env var or auto-generated at first boot

## Service Lifecycle

**Startup:** tini → versions → log rotation → WG up → configs → PHP-FPM → Nginx → tail logs

**Shutdown:** SIGTERM → nginx quit → php-fpm SIGQUIT → wg-quick down → exit

**Crash:** PID check every 2s, exit on any service death, Docker restart policy handles recovery

## Security

- Admin panel on separate port (isolated from user content)
- Console commands whitelisted (no arbitrary shell)
- File manager sandboxed to /data/
- Only `NET_ADMIN` capability required
