# Changelog

## [3.0.0] -- 2026-03-23

### Major: Multi-user accounts, two-area layout, WebSocket console, production hardening

Complete overhaul of the admin panel with multi-user support, restructured data layout, real-time WebSocket console, and resolution of 26 security audit issues.

### Added
- **Multi-user account system** with admin, operator, viewer roles
- **Role-based permissions** (Permission class with wildcard support)
- **Users management page** (admin-only) -- create, edit, delete accounts
- **Admin logs page** (admin-only) -- view activity log with filtering
- **Admin panel settings page** (admin-only) -- panel configuration
- **Two-area layout** -- client area (blue, :7890) + admin area (red, :9876)
- **Real-time WebSocket console** -- Go binary tails log files, Nginx proxies /ws
- **File manager enhancements:** compress (zip), decompress (with Zip Slip protection), chmod, search, copy, create file, rename
- **CSRF protection** -- per-session tokens on all POST requests (body + header)
- **Rate limiting** -- file-based limiter for login and API endpoints
- **Session inactivity timeout** (30 minutes) with session ID regeneration
- **Security headers** -- X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy no-referrer
- **Bcrypt password hashing** (cost 12) via PHP `password_hash()`
- **Activity logging** with JSON storage and toast notifications in UI
- **Docker healthcheck** (`/api/health` endpoint)
- **Trivy vulnerability scanning** in CI pipeline (CRITICAL + HIGH)
- **Auto-migration** from v2.0 flat `/data` layout to v3.0 structure
- **First-run detection** -- password shown in logs only on initial boot

### Changed
- **Data structure reorganized:** `/data/` split into `/data/user/` (service consumer) and `/data/admin/` (panel owner)
- **Authentication:** migrated from single env-var password to multi-user accounts with bcrypt
- **Console:** replaced SSE (Server-Sent Events) with Go WebSocket server for real-time log streaming
- **Password storage:** moved from `/data/.admin_password` to `/data/admin/.admin_password` + `/data/admin/users.json`
- **Nginx configs:** split into `user.conf.template` and `admin.conf.template` with WebSocket proxy block
- **Entrypoint:** added WebSocket server startup, user creation, migration logic
- **Dockerfile:** multi-stage build (Go builder stage + Ubuntu runtime stage)

### Security (26 audit issues resolved)
- Path traversal protection with `realpath()` + prefix validation
- Zip Slip protection on archive extraction
- Filename sanitization on uploads (special chars replaced with underscores)
- Console command whitelisting (no arbitrary shell execution)
- `ping` host validation via regex to prevent injection
- `server_tokens off` in Nginx
- `/src/` directory blocked in admin Nginx config
- Hidden files blocked in user Nginx config
- Sensitive files (`.admin_password`) excluded from file manager listings
- Session cookie: HttpOnly, SameSite=Strict, Secure (when HTTPS)
- Atomic file writes with `LOCK_EX`
- PHP source directory blocked from direct HTTP access

### Removed
- SSE-based console streaming (replaced by WebSocket)
- Single-password authentication model

## [2.0.0] -- 2026-03-23

### Major: Standalone Docker image with admin panel

Complete pivot from Pelican egg to standalone Docker container with built-in admin UI.

### Added
- Admin web panel (port 9876): Dashboard, Console, Files, Settings
- xterm.js terminal with SSE real-time log streaming
- File manager (upload, download, edit, delete, mkdir)
- Config editor for WG, Nginx, PHP with service controls
- Dashboard with CPU, memory, disk, network stats
- Password auth (auto-generated or env var)
- WireGuard via wg-quick (root, no capability issues)

### Removed
- Pelican egg JSON, WINGS-CONFIG.md, OPNSENSE.md
- All Pelican/Wings workarounds
- docker/ subdirectory

### Changed
- Runs as root (not UID 1000)
- Data at /data/ (not /home/container/)
- Image: ghcr.io/pelmentor/wg-nginx:latest

## [1.x] -- 2026-03-23

Historical Pelican egg versions. See git history.
