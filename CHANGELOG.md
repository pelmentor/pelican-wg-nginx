# Changelog

## [2.0.0] — 2026-03-23

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

## [1.x] — 2026-03-23

Historical Pelican egg versions. See git history.
