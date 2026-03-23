# Pelican Egg: WireGuard + Nginx + PHP-FPM

Custom egg for [Pelican Panel](https://pelican.dev) (Pterodactyl fork). Runs a container with a WireGuard VPN client and an Nginx + PHP-FPM web server. Data can be delivered to the web server over a WG tunnel from a remote host.

## Features

- **Nginx + PHP 8.1 FPM** with curl, gd, mbstring, and zip extensions
- **WireGuard VPN client** (optional — leave WG keys empty to run as a plain web server)
- **PresharedKey support** for additional WireGuard encryption
- **Version info at startup** — displays Ubuntu, Nginx, PHP, WireGuard versions and loaded PHP extensions
- **Error logs streamed to Pelican console** in real time (nginx errors, PHP-FPM errors)
- **Log rotation at startup** — log files exceeding 10 MB are truncated to the last 1000 lines
- **Configs editable via Pelican File Manager** — nginx.conf and php-fpm.conf are auto-restored if deleted
- **Default fallback port: 7890** — used when no port allocation is provided

## Quick Start

### 1. Configure Wings

Add to `/etc/pelican/config.yml` on the node:

```yaml
docker:
  network:
    # ... existing settings ...
  container_pid_limit: 512
  installer_limits:
    memory: 1024
    cpu: 100
  overhead:
    default:
      # ... existing settings ...
  allowed_capabilities:
    - NET_ADMIN          # Required for WireGuard interface creation
  allowed_devices:
    - /dev/net/tun       # TUN device for VPN tunnels
```

Then restart Wings:
```bash
sudo systemctl restart wings
```

### 2. Build Docker Image

```bash
cd docker/
docker build -t ghcr.io/pelmentor/pelican-wg-nginx:latest .
```

Or use the pre-built image (if published via GitHub Actions).

### 3. Import Egg into Pelican Panel

1. Go to **Admin → Nests**
2. Click **Import Egg**
3. Upload `egg-wg-nginx.json`
4. Create a server using this egg

### 4. Configure Server Variables

When creating a server in Pelican, fill in:

| Variable | Description | Example |
|----------|-------------|---------|
| `WG_PRIVATE_KEY` | WireGuard private key for this container | `aAbBcC...=` |
| `WG_ADDRESS` | Container IP in the WG network (CIDR) | `10.0.0.2/24` |
| `WG_PEER_PUBLIC_KEY` | Public key of the remote WG peer | `xXyYzZ...=` |
| `WG_PEER_ENDPOINT` | Peer address and port | `vds.example.com:51820` |
| `WG_PEER_ALLOWED_IPS` | IPs allowed through the tunnel | `10.0.0.1/32` |
| `WG_LISTEN_PORT` | WireGuard UDP port | `51820` |
| `WG_PRESHARED_KEY` | (Optional) PresharedKey for extra encryption | `pPqQrR...=` |

All WG variables are optional — leave them empty to run as a plain web server without VPN.

If no port allocation is provided by the panel, the server defaults to port **7890**.

### 5. Upload Website Files

Upload your files to `webroot/` via Pelican File Manager.

Or set up automatic delivery from the remote host over the WG tunnel:
```bash
# Example: rsync every 5 minutes
*/5 * * * * rsync -avz /path/to/data/ user@10.0.0.2:/home/container/webroot/
```

### 6. OPNsense Setup (if applicable)

If the server is behind OPNsense, port forwarding is required.
See [OPNSENSE.md](OPNSENSE.md) for details.

## Log Files

The container writes logs to the `logs/` directory:

| File | Content | Streamed to console |
|------|---------|---------------------|
| `logs/nginx-access.log` | HTTP request log | No (file only) |
| `logs/nginx-error.log` | Nginx errors | Yes |
| `logs/php-fpm-error.log` | PHP errors | Yes |
| `logs/wireguard.log` | WireGuard startup output | No |

**Auto-rotation:** At startup, any log file exceeding 10 MB is truncated to the last 1000 lines.

## Project Structure

```
.
├── README.md              — this file
├── ARCHITECTURE.md        — architecture, traffic flows, design decisions
├── RESEARCH.md            — analysis of existing solutions (linuxserver, official nginx/php, pelican-eggs)
├── CHANGELOG.md           — version history
├── OPNSENSE.md            — OPNsense firewall and port forward setup
├── WINGS-CONFIG.md        — Wings config.yml setup for NET_ADMIN and /dev/net/tun
├── egg-wg-nginx.json      — egg for Pelican import
└── docker/
    ├── Dockerfile         — image build
    ├── entrypoint.sh      — service startup script
    ├── nginx.conf         — Nginx config template
    └── php-fpm.conf       — PHP-FPM config template
```

## Requirements

- **Pelican Panel** + Wings with Docker
- NET_ADMIN capability and /dev/net/tun (see [WINGS-CONFIG.md](WINGS-CONFIG.md))
- WireGuard keys (generate with `wg genkey | tee privatekey | wg pubkey > publickey`)
