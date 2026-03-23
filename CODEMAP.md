# Codemap -- WG-Nginx v3.0

Complete map of every file, what it does, and how it connects.

## At a Glance

```
Request flow:

Browser ──► Nginx :9876 ──► /app/admin/public/index.php (front controller)
                │                    │
                │                    ├── Auth.php (session check)
                │                    ├── Permission.php (role gate)
                │                    ├── Router.php (dispatch)
                │                    └── Controller/*.php ──► Service/*.php ──► /data/
                │
                └── /ws ──► proxy_pass ──► Go ws-server :6790 ──► tail /data/user/logs/*.log


Process model (inside container):

tini (PID 1)
  └── entrypoint.sh (root)
        ├── wg-quick up wg0          (oneshot, optional)
        ├── php-fpm8.1               (background, www-data)
        ├── ws-server --port 6790    (background, Go binary)
        ├── nginx                    (background, master=root, workers=www-data)
        └── tail -F error logs       (background, → docker logs)
```

---

## Root Files

| File | Purpose |
|------|---------|
| `Dockerfile` | Multi-stage build: Go builder (ws-server) + Ubuntu 22.04 runtime. Installs PHP 8.1, Nginx, WireGuard, sudo. Sets up sudoers for www-data. |
| `entrypoint.sh` | Startup orchestrator: migration → WG → configs → users.json → PHP-FPM → ws-server → Nginx → health loop. Graceful shutdown via trap. |
| `ARCHITECTURE.md` | High-level design: process model, data layout, security model, WebSocket architecture. |
| `CHANGELOG.md` | Version history (v1.x → v2.0 → v3.0). |
| `README.md` | User-facing docs: quick start, env vars, ports, security features. |
| `RESEARCH.md` | Analysis of prior art (existing WG/Nginx images). |

---

## /app/admin/ -- Admin Panel PHP Backend

### Config

| File | What it defines |
|------|-----------------|
| `config.php` | All path constants: `DATA_DIR`, `USER_DIR`, `ADMIN_DIR`, `WEBROOT_DIR`, `USER_CONFIG_DIR`, `USER_LOGS_DIR`, `USER_TMP_DIR`, `ADMIN_LOGS_DIR`, `USERS_FILE`, `SESSIONS_DIR`, `SESSION_LIFETIME` |

### Front Controller

| File | What it does |
|------|-------------|
| `public/index.php` | Routes all requests. Loads classes, sets up rate limiting (login: 5/60s, API: 60/60s), registers 40+ routes, verifies CSRF on POST, enforces auth on protected routes. |

### Auth & Security Layer

| File | Key methods | What it does |
|------|-------------|-------------|
| `src/Auth.php` | `check()`, `requireAuth()`, `login()`, `logout()`, `csrfToken()`, `verifyCsrf()` | Session management with 30-min timeout, bcrypt login via UserManager, CSRF tokens (32 random bytes), session ID regeneration, HttpOnly+SameSite=Strict cookies |
| `src/Permission.php` | `check(role, perm)`, `requirePerm(role, perm)` | Role-based access control. admin=`*`, operator=9 perms, viewer=4 perms. Supports wildcards (`console.*`). Returns 403 JSON on failure. |
| `src/RateLimit.php` | `check()`, `enforce()`, `cleanup()` | File-based rate limiter. Stores timestamps in `/data/admin/ratelimit/{key}.json`. Probabilistic cleanup (1% of requests). |
| `src/Router.php` | `get()`, `post()`, `dispatch()` | Simple path→handler routing. Strips query strings, normalizes slashes. |

### Controllers (Route Handlers)

| File | Routes | Permission | What it does |
|------|--------|-----------|-------------|
| `Controller/DashboardController.php` | `GET /`, `GET /api/stats`, `GET /api/health` | `dashboard.view` (health: none) | Home page with live stats. `/api/health` used by Docker HEALTHCHECK. |
| `Controller/ConsoleController.php` | `GET /console`, `GET /api/console/poll`, `POST /api/console/command` | `console.read`, `console.write` | Terminal page. Poll returns new log lines since byte position. Command executes whitelisted commands via sudo. |
| `Controller/FilesController.php` | `GET /files`, 14 API endpoints under `/api/files/*` | `files.read`, `files.write`, `files.delete` | File manager: list, read, write, upload, delete, rename, mkdir, download, copy, compress, decompress, chmod, search, create. All paths sandboxed to `/data/user/`. |
| `Controller/SettingsController.php` | `GET /settings`, 4 API endpoints under `/api/settings/*` | `settings.view`, `settings.write` | Config editor (nginx, php, wireguard) + service controls (reload, restart, up/down). Atomic saves via tmp+rename. |
| `Controller/UserController.php` | `GET /admin/users`, 5 API endpoints under `/api/users/*` | `users.manage` | CRUD for user accounts. Password requirements: >=8 chars. Prevents self-deletion. |
| `Controller/ActivityController.php` | `GET /activity`, `GET /api/activity` | `activity.view` | Activity log viewer with limit clamping (50-200 entries). |
| `Controller/AdminLogsController.php` | `GET /admin/logs`, `GET /api/admin/logs` | `admin.*` | System log viewer: admin-access.log, admin-error.log, activity.json. Supports search filtering. |
| `Controller/AdminPanelController.php` | `GET /admin/panel`, `GET /api/admin/panel/info` | `admin.*` | System diagnostics: container info, PHP config, Nginx status, WG status, env vars (sensitive masked), disk usage. |

### Services (Business Logic)

| File | Key methods | What it does |
|------|-------------|-------------|
| `Service/UserManager.php` | `getAll()`, `getById()`, `getByUsername()`, `create()`, `update()`, `delete()`, `verifyPassword()`, `setPassword()`, `sanitize()` | CRUD on `/data/admin/users.json`. Bcrypt cost 12. `withLock()` wraps read-modify-write in `LOCK_EX` to prevent race conditions. |
| `Service/FileManager.php` | `listDirectory()`, `readFile()`, `writeFile()`, `delete()`, `upload()`, `download()`, `compress()`, `decompress()`, `chmod()`, `search()`, `copy()`, `createFile()` | All file operations sandboxed to `/data/user/`. `resolve()` uses `realpath()` + prefix check for path traversal prevention. Zip Slip protection on decompress. |
| `Service/ActivityLog.php` | `log(action, detail)`, `getRecent(limit)` | Append-only audit log in `/data/admin/logs/activity.json`. 500 entry cap. File-locked writes. |
| `Service/StatsService.php` | `getAll()`, `getCpu()`, `getMemory()`, `getDisk()`, `getNetwork()`, `getUptime()` | Reads `/proc/meminfo`, `/proc/net/dev`, `/proc/uptime`, `sys_getloadavg()`. WG status via `/sys/class/net/wg0`. |
| `Service/LogStreamer.php` | `poll(logFiles, positions)` | Efficient log tailing via byte offsets. Reads max 64KB per file per poll. Detects log rotation (size < position → reset). |
| `Service/ServiceManager.php` | `control(service, action)`, `getAllStatus()` | Manages nginx, php-fpm, wireguard via sudo. Sudoers whitelist: `/etc/sudoers.d/www-data-services`. |

### Views (PHP Templates)

All views use `layout.php` as the master template (except `login.php`).

| File | Page | JS file | What it renders |
|------|------|---------|----------------|
| `View/layout.php` | (master) | `app.js` | Dark theme shell: sidebar nav (client blue / admin red), CSRF meta tag, toast container, responsive mobile menu. Shows username + role. |
| `View/login.php` | `/login` | (inline) | Standalone login form with gradient background. Displays error messages. |
| `View/dashboard.php` | `/` | `dashboard.js` | 4 stat cards (CPU, Memory, Disk, Network) with animated bars. Service status dots. Recent activity widget. |
| `View/console.php` | `/console` | `console.js` | xterm.js terminal, mini-stats bar, command input, Ctrl+F search, Restart/Stop buttons. |
| `View/files.php` | `/files` | `files.js` | File table with breadcrumb, search, upload progress, edit/rename/delete modals. |
| `View/settings.php` | `/settings` | `settings.js` | Service control buttons + tabbed config editor (nginx/php/wg) with validate/save. |
| `View/activity.php` | `/activity` | `activity.js` | Activity table with icons, color-coded actions, search filter. |
| `View/admin_users.php` | `/admin/users` | `admin_users.js` | User table with create/edit/delete/password modals. Role badges. |
| `View/admin_panel.php` | `/admin/panel` | `admin_panel.js` | System info cards: container, PHP, Nginx, WG, env vars, disk. |
| `View/admin_logs.php` | `/admin/logs` | `admin_logs.js` | Log viewer with type tabs, line count selector, search. |

---

## /app/admin/public/assets/ -- Frontend

### JavaScript

| File | Used on | API endpoints called | What it does |
|------|---------|---------------------|-------------|
| `js/app.js` | All pages | (utility) | Toast notifications, `api.get()`/`api.post()` with CSRF headers, `formatBytes()`, `formatUptime()`, `formatDate()`. Auto-redirects on 401. |
| `js/dashboard.js` | Dashboard | `/api/stats`, `/api/activity` | Polls stats every 2s, activity every 30s. Animates stat bars + service dots. |
| `js/console.js` | Console | `/ws`, `/api/console/command`, `/api/stats` | xterm.js terminal + WebSocket connection. Color-codes log sources. Command history (localStorage, 50 entries). Mini-stats bar. |
| `js/files.js` | Files | `/api/files/*` (14 endpoints) | FileManager class: navigate dirs, render table, handle uploads (progress bar), edit modal, all CRUD operations. |
| `js/settings.js` | Settings | `/api/settings/*` (4 endpoints) | Tabbed config editor, validate before save, service control buttons. |
| `js/activity.js` | Activity | `/api/activity` | Action metadata (icons, colors, labels), renders activity table. |
| `js/admin_users.js` | Users | `/api/users/*` (5 endpoints) | UserManager class: CRUD modals, role badges, password change. |
| `js/admin_panel.js` | Panel | `/api/admin/panel/info` | Renders system info sections. |
| `js/admin_logs.js` | Logs | `/api/admin/logs` | Log type tabs, search filtering, line count control. |

### CSS

| File | What it does |
|------|-------------|
| `css/app.css` | Custom scrollbars, sidebar transitions, table hover effects, xterm.js overrides, modal backdrop, toast animations, loading spinner. Complements Tailwind. |

### Vendor Libraries (bundled)

| File | Version | Used by |
|------|---------|--------|
| `vendor/xterm/xterm.js` | 5.5.0 | Console page |
| `vendor/xterm/xterm.css` | 5.5.0 | Console page |
| `vendor/xterm/xterm-addon-fit.js` | 0.10.0 | Console page (auto-resize) |

---

## /app/nginx/ -- Nginx Config Templates

| File | Generates | Served on |
|------|-----------|-----------|
| `nginx.conf.template` | `/data/admin/nginx/nginx.conf` | Master config (daemon off, www-data workers, gzip, includes) |
| `admin.conf.template` | `/data/admin/nginx/admin.conf` | `:ADMIN_PORT` → `/app/admin/public/` (PHP front controller, WebSocket proxy, security headers) |
| `user.conf.template` | `/data/user/config/nginx-site.conf` | `:USER_PORT` → `/data/user/webroot/` (static + PHP, hidden files blocked) |

Template variables: `{{USER_PORT}}`, `{{ADMIN_PORT}}`, `{{DATA}}` (replaced by `sed` in entrypoint.sh).

---

## /app/php/ -- PHP-FPM Template

| File | Generates | Key settings |
|------|-----------|-------------|
| `php-fpm.conf.template` | `/data/user/config/php-fpm.conf` | ondemand pool, max 10 children, www-data, unix socket, 128MB memory, 100MB upload, sessions in `/data/admin/sessions/` |

---

## /app/ws/ -- Go WebSocket Server

| File | What it does |
|------|-------------|
| `main.go` | Tails `/data/user/logs/*.log` files, broadcasts new lines as JSON to WebSocket clients. Hub pattern for concurrent connections. Auto-discovers new log files every 10s. Detects log rotation. Listens on `127.0.0.1:6790`. |
| `go.mod` | Module def. Depends on `golang.org/x/net` (WebSocket). |

**Message format:** `{"source":"nginx-error","line":"[error] ...","time":"14:30:45"}`

---

## .github/workflows/

| File | Trigger | What it does |
|------|---------|-------------|
| `docker-publish.yml` | Push to main (Dockerfile/entrypoint/app changes) or manual | Build multi-stage image → Trivy scan (CRITICAL/HIGH) → push to `ghcr.io/pelmentor/wg-nginx:latest` + SHA tag |

---

## /data/ -- Persistent Volume (host-mounted)

```
/data/
├── user/                          SERVICE CONSUMER
│   ├── webroot/                   Document root for :USER_PORT
│   ├── config/
│   │   ├── nginx-site.conf        User vhost (generated from template)
│   │   ├── php-fpm.conf           PHP-FPM pool (generated from template)
│   │   └── wg0.conf               WireGuard config copy (if WG enabled)
│   ├── logs/
│   │   ├── nginx-access.log       User HTTP access
│   │   ├── nginx-error.log        User Nginx errors
│   │   ├── php-fpm-error.log      PHP errors
│   │   └── wireguard.log          WG startup/runtime log
│   └── tmp/
│       ├── php-fpm.sock           Nginx ↔ PHP-FPM communication
│       ├── nginx.pid              Nginx master PID
│       └── nginx/                 Nginx temp dirs (client, proxy, fastcgi)
│
└── admin/                         PANEL OWNER
    ├── users.json                 User accounts (bcrypt hashes)
    ├── .admin_password            Auto-generated password (hidden from FM)
    ├── nginx/
    │   ├── nginx.conf             Master Nginx config (generated)
    │   └── admin.conf             Admin vhost (generated)
    ├── logs/
    │   ├── admin-access.log       Panel HTTP access
    │   ├── admin-error.log        Panel PHP errors
    │   └── activity.json          Audit trail (JSON array)
    ├── sessions/                  PHP session files
    └── ratelimit/                 Rate limit state ({key}.json)
```

---

## Permission Matrix

| Permission | admin | operator | viewer | Used by |
|-----------|-------|----------|--------|---------|
| `*` (wildcard) | yes | - | - | Everything |
| `dashboard.view` | * | yes | yes | Dashboard, /api/stats |
| `console.read` | * | yes | yes | Console page, /api/console/poll |
| `console.write` | * | yes | - | /api/console/command |
| `files.read` | * | yes | yes | Files page, list/read/download/search |
| `files.write` | * | yes | - | write/upload/rename/mkdir/copy/compress/decompress/chmod/create |
| `files.delete` | * | yes | - | /api/files/delete |
| `settings.view` | * | yes | - | Settings page, get config, service status |
| `settings.write` | * | - | - | Save config, service control |
| `logs.view` | * | yes | - | (reserved) |
| `activity.view` | * | yes | yes | Activity page |
| `users.manage` | * | - | - | Users page, all /api/users/* |
| `admin.*` | * | - | - | Panel info, system logs |

---

## API Endpoint Reference

### Public (no auth)

| Method | Path | Handler |
|--------|------|---------|
| GET | `/login` | Auth::handleLogin |
| POST | `/login` | Auth::handleLogin |
| GET | `/api/health` | DashboardController::health |

### Authenticated

| Method | Path | Permission | Handler |
|--------|------|-----------|---------|
| GET | `/` | dashboard.view | DashboardController::index |
| GET | `/console` | console.read | ConsoleController::index |
| GET | `/files` | files.read | FilesController::index |
| GET | `/settings` | settings.view | SettingsController::index |
| GET | `/activity` | activity.view | ActivityController::index |
| GET | `/admin/users` | users.manage | UserController::index |
| GET | `/admin/panel` | admin.* | AdminPanelController::index |
| GET | `/admin/logs` | admin.* | AdminLogsController::index |
| GET | `/api/stats` | dashboard.view | DashboardController::stats |
| GET | `/api/console/poll` | console.read | ConsoleController::poll |
| POST | `/api/console/command` | console.write | ConsoleController::command |
| GET | `/api/files/list` | files.read | FilesController::listDir |
| GET | `/api/files/read` | files.read | FilesController::read |
| POST | `/api/files/write` | files.write | FilesController::write |
| POST | `/api/files/upload` | files.write | FilesController::upload |
| POST | `/api/files/delete` | files.delete | FilesController::delete |
| POST | `/api/files/rename` | files.write | FilesController::renamePath |
| POST | `/api/files/mkdir` | files.write | FilesController::mkdirPath |
| GET | `/api/files/download` | files.read | FilesController::download |
| POST | `/api/files/copy` | files.write | FilesController::copy |
| POST | `/api/files/compress` | files.write | FilesController::compress |
| POST | `/api/files/decompress` | files.write | FilesController::decompress |
| POST | `/api/files/chmod` | files.write | FilesController::chmodPath |
| GET | `/api/files/search` | files.read | FilesController::search |
| POST | `/api/files/create` | files.write | FilesController::createFile |
| GET | `/api/settings/config` | settings.view | SettingsController::getConfig |
| POST | `/api/settings/config` | settings.write | SettingsController::saveConfig |
| POST | `/api/settings/validate` | settings.view | SettingsController::validateConfig |
| POST | `/api/settings/service` | settings.write | SettingsController::serviceAction |
| GET | `/api/settings/status` | settings.view | SettingsController::serviceStatus |
| GET | `/api/activity` | activity.view | ActivityController::recent |
| GET | `/api/users` | users.manage | UserController::list |
| POST | `/api/users` | users.manage | UserController::create |
| POST | `/api/users/update` | users.manage | UserController::update |
| POST | `/api/users/delete` | users.manage | UserController::delete |
| POST | `/api/users/password` | (auth only) | UserController::changePassword |
| GET | `/api/admin/panel/info` | admin.* | AdminPanelController::info |
| GET | `/api/admin/logs` | admin.* | AdminLogsController::getLogs |
| WS | `/ws` | (nginx proxy) | Go ws-server :6790 |
