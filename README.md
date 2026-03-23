# WG-Nginx: WireGuard + Nginx + PHP-FPM + Admin Panel

Standalone Docker image with WireGuard VPN client, Nginx web server, PHP 8.1 FPM, and a built-in Pelican-style admin panel. Designed for Unraid but works on any Docker host.

## Features

- **Nginx + PHP 8.1 FPM** (curl, gd, mbstring, zip, xml extensions)
- **WireGuard VPN client** (optional — leave keys empty for a plain web server)
- **PresharedKey support** for additional WG encryption
- **Admin Web Panel** on a separate port with:
  - **Dashboard** — CPU, memory, disk, network stats + service status
  - **Console** — xterm.js terminal with live log streaming + command input
  - **File Manager** — browse, upload, download, edit, delete files
  - **Settings** — edit WG/Nginx/PHP configs, restart services
- **Password-protected** — auto-generated password shown in container logs
- **Version info at startup** — Ubuntu, Nginx, PHP, WG versions displayed
- **Log rotation** at startup (files >10MB truncated)

## Quick Start

```bash
docker run -d \
  --name wg-nginx \
  --cap-add=NET_ADMIN \
  -p 7890:7890 \
  -p 9876:9876 \
  -v /path/to/data:/data \
  ghcr.io/pelmentor/wg-nginx:latest
```

Then open `http://YOUR_IP:9876` and login with the password from `docker logs wg-nginx`.

## Ports

| Port | Purpose |
|------|---------|
| `7890` (TCP) | User web content (Nginx) |
| `9876` (TCP) | Admin panel |
| WG_LISTEN_PORT (UDP) | WireGuard (if configured) |

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `USER_PORT` | `7890` | Port for user web content |
| `ADMIN_PORT` | `9876` | Port for admin panel |
| `ADMIN_PASSWORD` | auto-generated | Admin panel password (shown in logs) |
| `WG_PRIVATE_KEY` | empty | WireGuard private key (leave empty to disable WG) |
| `WG_ADDRESS` | `10.0.0.2/24` | Container IP in the WG network |
| `WG_PEER_PUBLIC_KEY` | empty | Peer public key |
| `WG_PEER_ENDPOINT` | empty | Peer address (host:port) |
| `WG_PEER_ALLOWED_IPS` | `10.0.0.0/24` | IPs routed through the tunnel |
| `WG_PRESHARED_KEY` | empty | Optional preshared key |
| `WG_LISTEN_PORT` | empty | WG listen port (if acting as server) |

## Console Commands

Type these in the admin panel Console page:

| Command | Description |
|---------|-------------|
| `help` | Show available commands |
| `status` | Show all service statuses |
| `wg show` | WireGuard interface status |
| `wg peers` | Peer details (endpoints, handshakes, transfer) |
| `ping <host>` | Ping a host (e.g. `ping 10.0.0.1`) |
| `nginx reload` | Reload Nginx config (tests first) |
| `nginx test` | Test Nginx config for errors |
| `logs access` | Last 30 lines of access log |
| `logs error` | Last 30 lines of error logs |
| `phpinfo` | PHP version and extensions |

## Data Volume

Mount `/data` to persist everything:

```
/data/
├── webroot/     — your website files
├── wg/          — WireGuard config
├── nginx/       — Nginx config (auto-generated, editable)
├── php/         — PHP-FPM config
├── logs/        — all log files
└── tmp/         — runtime files (PID, socket)
```

## Unraid Setup

1. Go to Docker tab → Add Container
2. Repository: `ghcr.io/pelmentor/wg-nginx:latest`
3. Add ports: `7890` (TCP), `9876` (TCP)
4. Add path: `/data` → `/mnt/user/appdata/wg-nginx`
5. Extra parameters: `--cap-add=NET_ADMIN`
6. Optional: add WG env vars if you need VPN

## Testing & Update Cycle

```bash
# Pull and run
docker pull ghcr.io/pelmentor/wg-nginx:latest
docker run -d --name wg-nginx --cap-add=NET_ADMIN \
  -p 7890:7890 -p 9876:9876 \
  -v /mnt/user/appdata/wg-nginx:/data \
  ghcr.io/pelmentor/wg-nginx:latest

# Get admin password
docker logs wg-nginx

# Debug
docker logs wg-nginx --tail 50
docker exec -it wg-nginx bash

# Update to new version
docker stop wg-nginx && docker rm wg-nginx
docker pull ghcr.io/pelmentor/wg-nginx:latest
# then docker run again (same command as above)

# Full cleanup (removes everything including data)
docker stop wg-nginx && docker rm wg-nginx
docker rmi ghcr.io/pelmentor/wg-nginx:latest
rm -rf /mnt/user/appdata/wg-nginx
```

> **Note:** Data in `/mnt/user/appdata/wg-nginx` survives `docker rm`. Only deleting that folder is a full reset.

## Architecture

```
Container (root, --cap-add=NET_ADMIN)
│
├── Nginx (two server blocks)
│   ├── :7890 → /data/webroot/    (user content)
│   └── :9876 → /app/admin/       (admin panel)
│
├── PHP-FPM 8.1 (single pool, unix socket)
│
├── WireGuard (wg-quick, optional)
│
└── /data/ (persistent volume)
```

## Project Structure

```
.
├── Dockerfile           — image build
├── entrypoint.sh        — startup script
├── app/
│   ├── admin/           — admin panel (PHP + JS)
│   │   ├── public/      — front controller + assets
│   │   └── src/         — PHP backend (Router, Auth, Controllers, Services, Views)
│   ├── nginx/           — nginx config templates
│   └── php/             — PHP-FPM config template
├── ARCHITECTURE.md      — detailed architecture docs
├── RESEARCH.md          — analysis of prior art
└── CHANGELOG.md         — version history
```

## Requirements

- Docker with `--cap-add=NET_ADMIN` (for WireGuard)
- Port 7890 and 9876 available
- WireGuard keys (generate with `wg genkey | tee privatekey | wg pubkey > publickey`)
