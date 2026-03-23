# Architecture -- WG-Nginx v3.0

## Overview

Standalone Docker container running as **root** with `--cap-add=NET_ADMIN`.
No Pelican/Wings dependency -- runs directly on Unraid or any Docker host.

**Stack:** Ubuntu 22.04, Nginx, PHP-FPM 8.1, WireGuard, Go WebSocket server, tini (PID 1)

## Container Layout

```
Container (root, --cap-add=NET_ADMIN)
│
├── tini (PID 1) — zombie reaper, signal forwarder
│   └── entrypoint.sh
│       ├── wg-quick up wg0 (optional, oneshot — configures kernel interface, exits)
│       ├── php-fpm8.1 --nodaemonize (background, www-data)
│       ├── ws-server --port 6790 (background, Go binary)
│       ├── nginx -c ... (background, master=root, workers=www-data)
│       └── tail -F error logs (background, streams to docker logs)
│
├── Nginx server blocks:
│   ├── :USER_PORT (default 7890) → /data/user/webroot/     [Client area]
│   └── :ADMIN_PORT (default 9876) → /app/admin/public/     [Admin area]
│       └── /ws → proxy to 127.0.0.1:6790/ws                [WebSocket]
│
├── Go WebSocket server (port 6790, internal only):
│   └── Tails /data/user/logs/*.log → broadcasts to WebSocket clients
│
└── Volumes:
    ├── /app/   (read-only, baked into image)
    └── /data/  (persistent, mounted from host)
```

## Two-Area Layout

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Container                      │
│                                                         │
│  ┌──────────────────────┐  ┌──────────────────────────┐ │
│  │   CLIENT AREA (blue) │  │    ADMIN AREA (red)      │ │
│  │   :7890              │  │    :9876                  │ │
│  │                      │  │                           │ │
│  │   /data/user/webroot │  │    /app/admin/public      │ │
│  │                      │  │    ├── Dashboard          │ │
│  │   Static + PHP       │  │    ├── Console (WS)       │ │
│  │   content served     │  │    ├── File Manager       │ │
│  │   to end users       │  │    ├── Settings           │ │
│  │                      │  │    ├── Users (admin)      │ │
│  │                      │  │    └── Logs (admin)       │ │
│  └──────────────────────┘  └──────────────────────────┘ │
│                                                         │
│  ┌─────────────────────────────────────────────────────┐ │
│  │                    Shared                            │ │
│  │  PHP-FPM 8.1 (unix socket, www-data)                │ │
│  │  Go WebSocket server (127.0.0.1:6790)               │ │
│  │  WireGuard wg0 (optional)                           │ │
│  └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## /data Structure

```
/data/
├── user/                          — service consumer (the website being served)
│   ├── webroot/                   — document root for :USER_PORT
│   ├── config/                    — generated configs (nginx, php-fpm, wg)
│   ├── logs/                      — user-facing logs (nginx, php, wg)
│   └── tmp/                       — runtime (PID, socket, nginx temp dirs)
│
└── admin/                         — panel owner (management infrastructure)
    ├── users.json                 — user accounts with bcrypt hashes
    ├── .admin_password            — auto-generated password (hidden from FM)
    ├── nginx/                     — admin nginx configs (nginx.conf, admin.conf)
    ├── logs/                      — admin logs (access, error, activity.json)
    ├── sessions/                  — PHP session storage
    └── ratelimit/                 — rate limiter state files
```

**Separation principle:** User data (`user/`) is what the service consumer cares about -- their website files, service logs, and runtime configs. Admin data (`admin/`) is management infrastructure -- accounts, sessions, panel logs. The file manager operates on `user/` by default.

## File Separation

| Path | Lifecycle | Contains |
|------|-----------|----------|
| `/app/` | Image (read-only at runtime) | Admin panel PHP, JS, CSS, nginx/php templates, Go binary |
| `/data/user/` | Persistent volume | User website files, service configs, service logs, runtime |
| `/data/admin/` | Persistent volume | Accounts, sessions, rate limit state, admin logs |
| `/etc/wireguard/` | Runtime (regenerated each start) | Generated wg0.conf from env vars |
| `/usr/local/bin/ws-server` | Image (read-only) | Go WebSocket binary |

## Account System Architecture

Accounts are stored in `/data/admin/users.json` as a JSON file with bcrypt-hashed passwords (cost 12).

```
users.json
{
  "users": [
    {
      "id": "u_admin",
      "username": "admin",
      "password_hash": "$2y$12$...",
      "role": "admin",
      "created_at": 1711152000,
      "last_login": null
    }
  ]
}
```

On first boot, `entrypoint.sh` creates the default admin account using PHP's `password_hash()` to avoid shell escaping issues with bcrypt output.

**UserManager** (`/app/admin/src/Service/UserManager.php`) handles CRUD operations on accounts. All writes use `LOCK_EX` for atomicity.

## Permission Model

Three roles with a deny-by-default model. Admin has wildcard (`*`) access.

```
admin    → *  (full access)
operator → dashboard.view, console.read, console.write, files.read, files.write,
           files.delete, settings.view, logs.view, activity.view
viewer   → dashboard.view, console.read, files.read, activity.view
```

`Permission::check($role, $perm)` supports exact match and wildcard patterns (e.g., `console.*` matches `console.read`).

Every controller method calls `Permission::requirePerm()` before processing. On failure, returns HTTP 403 with JSON error.

## WebSocket Architecture

```
Browser (xterm.js)
  ↕ WebSocket
Nginx :9876 /ws
  ↕ proxy_pass (HTTP/1.1 upgrade)
Go ws-server 127.0.0.1:6790/ws
  ↕ file tail (200ms poll)
/data/user/logs/*.log
```

**Why Go instead of PHP:** PHP-FPM workers are designed for short-lived requests (~50ms). A WebSocket connection is long-lived (minutes/hours). Holding a PHP-FPM worker would starve other requests. The Go binary handles thousands of concurrent connections in a single process, adding ~5MB to the image.

**How it works:**
1. Go binary starts with `--port 6790 --logdir /data/user/logs`
2. On startup, it discovers all `*.log` files and starts a goroutine per file that seeks to EOF and tails new lines
3. Every 10 seconds, it checks for newly created log files and starts tailing them
4. Each new line is broadcast to all connected WebSocket clients as JSON: `{"source":"nginx-error","line":"...","time":"15:04:05"}`
5. Nginx proxies `/ws` on the admin port to `127.0.0.1:6790/ws` with WebSocket upgrade headers and a 1-hour read/write timeout

**Console commands** are separate from the WebSocket stream. They are POST requests to `/api/console/command` handled by PHP, which executes whitelisted commands and returns the output as JSON.

## Security

### CSRF Protection
Per-session token generated via `random_bytes(32)`. Verified on all POST requests by checking either the `_csrf` body field or the `X-CSRF-Token` header. Token is regenerated on login (session regeneration prevents fixation).

### Rate Limiting
File-based limiter in `/data/admin/ratelimit/`. Each key (e.g., `login:<ip>`) gets a JSON file tracking attempt timestamps. Stale files are cleaned up probabilistically (1 in 100 requests). `RateLimit::enforce()` returns HTTP 429 when exceeded.

### Session Management
- 30-minute inactivity timeout (`Auth::SESSION_TIMEOUT`)
- Session ID regenerated on login (`session_regenerate_id(true)`)
- Secure cookie params: HttpOnly, SameSite=Strict, Secure (when HTTPS)
- User existence validated on every authenticated request
- Sessions stored in `/data/admin/sessions/`

### Security Headers (Admin Area)
```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
```

### File Manager Security
- **Path traversal:** `realpath()` resolves symlinks and `..`, then `str_starts_with()` confirms result is inside the sandbox root
- **Zip Slip:** Every archive entry is checked for `..` sequences and resolved against the extraction directory before extraction
- **Filename sanitization:** Uploaded files have special characters replaced with underscores
- **Size limit:** Files >10MB cannot be opened in the editor
- **Sensitive file hiding:** `.admin_password` is excluded from directory listings

### Other
- Console commands are whitelisted (no arbitrary shell; `ping` host is validated via regex)
- `server_tokens off` in Nginx (hides version)
- `/src/` directory blocked in admin Nginx config
- Hidden files (`/\.`) blocked in user Nginx config
- Nginx master runs as root (port binding), workers run as www-data
- PHP-FPM runs as www-data, shares unix socket with Nginx workers

## Service Lifecycle

### Startup
```
tini (PID 1)
  └── entrypoint.sh
        0. Print versions, create dirs, chown /data, migrate old layout, rotate logs
        1. WireGuard — generate /etc/wireguard/wg0.conf from env vars, wg-quick up (optional)
        2. Generate configs — sed templates → /data/user/config/ and /data/admin/nginx/
        3. Admin password — auto-generate or read from file, create users.json if first run
        4. PHP-FPM — start, wait for unix socket (up to 5s)
        5. WebSocket server — start Go binary on 127.0.0.1:6790
        6. Nginx — start with generated config
        7. Print connection info + credentials (first run only)
        8. Tail error logs to docker stdout
        9. Health loop — check nginx + php-fpm PIDs every 2s
```

### Shutdown
```
SIGTERM/SIGINT → trap → cleanup()
  1. kill tail (stop log streaming)
  2. kill ws-server (stop WebSocket)
  3. nginx -s quit (SIGQUIT — graceful, finishes in-flight requests)
  4. kill -SIGQUIT php-fpm (graceful, finishes active workers)
  5. wait for php-fpm to exit
  6. wg-quick down wg0 (removes interface, cleans routes)
  7. exit 0
```

Both Nginx and PHP-FPM use SIGQUIT for graceful shutdown (finish serving current requests before exiting), matching official Docker images.

### Crash Recovery
The health loop checks PIDs every 2 seconds. If either Nginx or PHP-FPM exits unexpectedly, the container exits and relies on Docker's restart policy (`--restart unless-stopped`) for a clean recovery. Partial recovery (e.g., Nginx up but PHP-FPM down) would cause 502 errors, so a full restart is preferred.

### Migration
On startup, if `/data/webroot` exists (v2.0 flat layout), the entrypoint automatically migrates files to the v3.0 `user/` + `admin/` structure and removes old directories.

## Admin Panel Request Flow

```
Browser → Nginx :9876 → /app/admin/public/index.php (front controller)
                              │
                              ├── RateLimit::cleanup() (probabilistic)
                              ├── Router dispatches by URL
                              │
                              ├── Public routes:
                              │   ├── GET /login → Auth::handleLogin()
                              │   ├── POST /login → Auth::handleLogin() + RateLimit
                              │   └── GET /api/health → JSON {"status":"ok"}
                              │
                              └── Authenticated routes (Auth::requireAuth):
                                  ├── Auth::verifyCsrf() on POST
                                  ├── Permission::requirePerm() per action
                                  │
                                  ├── GET / → DashboardController
                                  ├── GET /console → ConsoleController
                                  ├── GET /files → FilesController
                                  ├── GET /settings → SettingsController
                                  ├── GET /activity → ActivityController
                                  │
                                  ├── Admin-only:
                                  │   ├── GET /admin/users → UserController
                                  │   ├── GET /admin/logs → AdminLogsController
                                  │   └── GET /admin/panel → AdminPanelController
                                  │
                                  └── API endpoints:
                                      ├── /api/stats, /api/console/*, /api/files/*
                                      ├── /api/settings/*, /api/users/*
                                      └── /ws (proxied to Go WebSocket server)
```

## CI/CD Pipeline

GitHub Actions workflow (`.github/workflows/docker-publish.yml`):

1. **Trigger:** Push to `main` (Dockerfile, entrypoint.sh, or app/** changed) or manual dispatch
2. **Build:** Multi-stage Docker build (Go builder + Ubuntu runtime)
3. **Scan:** Trivy vulnerability scanner (CRITICAL + HIGH severity), results uploaded to GitHub Security tab
4. **Push:** Image pushed to `ghcr.io/pelmentor/wg-nginx:latest` + SHA-tagged
