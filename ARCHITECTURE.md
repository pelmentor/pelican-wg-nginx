# Architecture — WireGuard + Nginx + PHP-FPM Egg

## Overview

This Pelican Panel egg runs a container with three services:
- **WireGuard** — VPN client for connecting to a remote Minecraft server
- **Nginx** — web server for serving a Minecraft interactive map
- **PHP-FPM** (PHP 8.1) — handles PHP scripts (if the map plugin uses a PHP backend)

The base image is **Ubuntu 22.04 LTS** with packages installed via apt.

## Network Diagram

```
                        [Internet]
                            |
                            v
+-------------------------------------------+
|            OPNsense (router)              |
|  - Manages gateway, VLAN, firewall        |
|  - Port forward: WG UDP + HTTP TCP        |
|    to the Unraid server                   |
+-------------------+-----------------------+
                    | LAN / VLAN
                    v
+---------------------------------------------------------------+
|              Unraid OS (Ryzen 3950x, 16 cores, 16 GB RAM)     |
|              Docker bridge network                             |
|                                                                |
|  +---------------------------------------------------------+  |
|  |              Pelican Wings (Docker)                      |  |
|  |              /mnt/user/appdata/pelican/                  |  |
|  |                                                          |  |
|  |  +---------------------------------------------------+  |  |
|  |  |         Docker container (this egg)                |  |  |
|  |  |         NET_ADMIN + /dev/net/tun                   |  |  |
|  |  |                                                    |  |  |
|  |  |  +---------+   +---------+   +--------------+     |  |  |
|  |  |  |WireGuard|   |  Nginx  |   |   PHP-FPM    |     |  |  |
|  |  |  | (wg0)   |   | (:8080) |   | (unix sock)  |     |  |  |
|  |  |  +----+----+   +----+----+   +------+-------+     |  |  |
|  |  |       |              |               |             |  |  |
|  |  |       |              +---------------+             |  |  |
|  |  |       |         nginx proxies .php                 |  |  |
|  |  |       |         to php-fpm via unix socket         |  |  |
|  |  +-------+--------------------------------------------+  |  |
|  |          | WG tunnel (UDP)                                |  |
|  +----------+------------------------------------------------+  |
|             |                                                    |
+-------------+----------------------------------------------------+
              |
              |  WireGuard UDP tunnel
              |  (over the internet, through OPNsense NAT)
              v
+-----------------------------+
|       Remote VDS            |
|                             |
|  +-----------------------+  |
|  |   Minecraft server    |  |
|  |   + map plugin        |  |
|  |   (BlueMap/Dynmap)    |  |
|  +-----------+-----------+  |
|              |              |
|   Pushes map tiles over WG  |
|   tunnel (rsync/scp/http)   |
|   to the wg0 internal IP    |
+-----------------------------+
```

## Traffic Flows

### 1. WireGuard Tunnel (MC server -> container)
- **Protocol**: UDP
- **Direction**: MC server on the VDS connects as a WG peer
- **Purpose**: MC server pushes map data (tiles) through the WG tunnel
- **Port**: WG listens on the port assigned by Pelican (variable `SERVER_PORT`)
- **Internal IP**: assigned via egg variables (e.g. `10.0.0.2/24`)

### 2. Web Traffic (users -> Nginx)
- **Protocol**: HTTP
- **Port**: `SERVER_PORT` (primary Pelican allocation port, default 8080)
- **Path**: Nginx listens on `0.0.0.0:SERVER_PORT` and serves static files from `/home/container/webroot`
- **PHP**: requests for `.php` files are proxied to PHP-FPM via a unix socket

### 3. Map Data Transfer (over the WG tunnel)
- The MC server can push tiles via:
  - `rsync` over SSH through the WG tunnel
  - HTTP PUT/POST to an internal endpoint
  - `scp` directly into `/home/container/webroot/`
- The specific method depends on the map plugin on the MC server side

## Ports

| Port | Protocol | Purpose |
|------|----------|---------|
| `SERVER_PORT` | TCP | Nginx HTTP (primary allocation) |
| `WG_LISTEN_PORT` | UDP | WireGuard (additional allocation) |

## Container File Structure

```
/home/container/              <- root, managed via Pelican File Manager
├── webroot/                  <- site documents (map tiles go here)
│   └── index.html
├── wg/
│   └── wg0.conf              <- generated from egg variables at startup
├── nginx/
│   └── nginx.conf             <- Nginx config (editable through the Panel)
├── php/
│   └── php-fpm.conf           <- PHP-FPM config
└── tmp/                       <- writable temp directory for all services
    └── nginx/                 <- Nginx temp files (client body, proxy, etc.)
```

## Host Requirements (Wings)

- **NET_ADMIN capability** — required by WireGuard to create a network interface
- **/dev/net/tun** — tunnel device, mounted into the container
- **sysctl `net.ipv4.conf.all.src_valid_mark=1`** — needed for WG client mode
- Detailed setup: [WINGS-CONFIG.md](WINGS-CONFIG.md)

## Service Lifecycle

### Startup Order

1. `tini` starts the entrypoint script as PID 1 (reaps zombies, forwards signals)
2. Entrypoint runs `wg-quick up wg0` (oneshot — configures the interface and exits)
3. Entrypoint starts `php-fpm` in the background
4. Entrypoint starts `nginx` in the foreground (or both backgrounded with `wait -n`)

### Crash Behavior

If **either Nginx or PHP-FPM exits unexpectedly**, the entrypoint detects it via
`wait -n` (which returns as soon as any child process exits) and terminates the
remaining processes. The container then exits with a non-zero status code.

**Pelican Wings** monitors the container. When it sees the container has exited,
it can automatically restart it according to the server's restart policy configured
in the Panel. This means a crash in any single service leads to a full container
restart, which is the simplest and most reliable recovery strategy for a
three-service container.

### Graceful Shutdown (SIGQUIT)

When Pelican sends **SIGTERM** or **SIGINT** to stop the server, the entrypoint
traps the signal and sends **SIGQUIT** to both Nginx and PHP-FPM. Both services
treat SIGQUIT as a graceful shutdown signal:

- **Nginx** stops accepting new connections and waits for in-flight requests to complete
- **PHP-FPM** finishes processing active requests before exiting

After both services have exited, the entrypoint runs `wg-quick down wg0` to
cleanly tear down the WireGuard interface, then exits.

This matches the behavior of the official Nginx and PHP-FPM Docker images, which
both use `STOPSIGNAL SIGQUIT`.

## Architectural Decisions and Prior Art

Detailed analysis of existing solutions is in [RESEARCH.md](RESEARCH.md).
Below is a summary of key decisions and why they were made.

### Init System: tini (not s6-overlay / supervisord)

| Option | Used by | Pros | Cons | Our decision |
|--------|---------|------|------|-------------|
| s6-overlay | linuxserver/wireguard, linuxserver/nginx | DAG dependencies, readiness probes | +5 MB, complex DSL | Overkill for 3 services |
| supervisord | trafex/php-nginx | Simple configuration | +50 MB (Python), no zombie reaping | Too heavy |
| **tini** | **this project** | 30 KB, zombie reaping, signal forwarding | No supervision | Sufficient: `wait -n` catches crashes |
| Two containers | Docker best practice | Isolation, scaling | More complex for Pelican | Not viable: Pelican = 1 egg = 1 container |

### WireGuard: Oneshot, Not a Daemon

Ref: linuxserver/docker-wireguard defines `svc-wireguard` as **oneshot** in s6.
WireGuard runs in the kernel — `wg-quick up` configures the interface and exits.
There is no daemon process to monitor. We do the same: WG is brought up in the
entrypoint before starting nginx/php-fpm, and torn down separately during cleanup.

### PHP-FPM: Unix Socket (not TCP)

| Option | Used by | Rationale |
|--------|---------|-----------|
| TCP 0.0.0.0:9000 | official php-fpm | For multi-container setups (nginx in a separate container) |
| TCP 127.0.0.1:9000 | linuxserver/nginx | Simple, no permission issues |
| **Unix socket** | **this project**, trafex | ~5-10% faster than TCP; single container makes a socket the logical choice |

### Logging: Files (not stdout symlinks)

| Option | Used by | Rationale |
|--------|---------|-----------|
| symlink -> /dev/stdout | official nginx | Logs appear in `docker logs` |
| Real files + logrotate | linuxserver | Persistent volume, logs survive restarts |
| **Real files** | **this project** | Visible in Pelican File Manager; users can download them |

### Nginx Temp Directories: /home/container/tmp/

Ref: trafex/docker-php-nginx — when running as a non-root user, the default
paths at `/var/cache/nginx/` are not writable. The solution is to point temp
directories to a writable location: `client_body_temp_path /home/container/tmp/nginx/...`

Using `/home/container/tmp/` instead of `/tmp/` keeps all writable paths under
the container home directory, which is consistent with Pelican's file management
model and avoids potential permission issues with the system temp directory.

### Health Check: fpm-ping

Ref: trafex/docker-php-nginx — `/fpm-ping` verifies the entire chain:
nginx is listening -> proxies to php-fpm -> socket is working -> PHP-FPM responds "pong".
This is better than checking only nginx (stub_status) or only a TCP port.

### Graceful Shutdown: SIGQUIT

Ref: official nginx + official php-fpm — both use `STOPSIGNAL SIGQUIT`
for graceful shutdown (waiting for in-flight requests to finish).
Our entrypoint sends SIGQUIT to both processes upon receiving SIGTERM/SIGINT from Pelican.

### Egg Format: PTDL_v2 Conventions

Ref: pelican-eggs/eggs — standard patterns:
- `"done": "string"` in `config.startup` to detect readiness
- `"^^C"` in `config.stop` for SIGINT
- Installation script writes to `/mnt/server/` (mounted as `/home/container/` at runtime)
- Variables: `UPPER_SNAKE_CASE`, Laravel validation rules
- Docker images: key-value mapping display name -> registry URL
