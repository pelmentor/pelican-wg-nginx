# Research — Existing Solutions and Architectural Patterns

> **Note:** This research was conducted during the Pelican egg phase (v1.x).
> The project has since pivoted to a standalone Docker image (v2.0), but the
> findings about WireGuard, Nginx, and PHP-FPM Docker patterns remain relevant.

This document captures findings from analyzing existing Docker images and
Pelican/Pterodactyl eggs to inform our architectural decisions.

**Stack versions in this project:** Ubuntu 22.04 LTS, Nginx 1.18, PHP-FPM 8.1, WireGuard tools.

---

## 1. WireGuard Docker Images

### 1.1 linuxserver/docker-wireguard

**Repository**: [github.com/linuxserver/docker-wireguard](https://github.com/linuxserver/docker-wireguard)
**Docs**: [docs.linuxserver.io/images/docker-wireguard](https://docs.linuxserver.io/images/docker-wireguard/)

#### Init system: s6-overlay v3

LinuxServer uses **s6-overlay** with the `s6-rc` service manager. Boot order:

```
s6-rc.d/
  init-wireguard-module  (oneshot) → checks if WG kernel module is loaded
  init-wireguard-confs   (oneshot) → generates/updates configs
  svc-coredns            (longrun) → DNS for peers
  svc-wireguard          (oneshot) → wg-quick up (WG runs in-kernel, not a daemon)
```

**Key insight**: WireGuard is a **oneshot**, not a longrun service.
It runs in the kernel — `wg-quick up` configures the interface and exits.
There is no daemon process to monitor.

#### Config generation

- Templates in `/config/templates/server.conf` and `/config/templates/peer.conf`
- Keys stored per-peer in `/config/peerN/`
- IP addresses assigned by iterating `.2`–`.254` in the subnet
- Configs regenerated when env vars change (compared against `/config/.donoteditthisfile`)
- QR codes generated via `qrencode`

#### Capabilities and devices

| Requirement | Required? | Purpose |
|------------|-----------|---------|
| `NET_ADMIN` | **Yes** | Create WG interface, manage routes, iptables |
| `SYS_MODULE` | Optional | Load WG kernel module (if not loaded on host) |
| `/dev/net/tun` | **Not needed for WG** | WireGuard uses netlink, not TUN/TAP. /dev/net/tun is for OpenVPN |
| `net.ipv4.conf.all.src_valid_mark=1` | For client mode | sysctl required in client-mode |

**Important finding**: `/dev/net/tun` is technically **not needed** for WireGuard.
WG creates interfaces via `ip link add type wireguard` (netlink API),
not TUN/TAP. However, some kernels may require it — safer to keep.

#### Graceful shutdown

Script `svc-wireguard/down` → `svc-wireguard/finish`:
```bash
# Tunnels brought down in REVERSE order (tac)
for tunnel in $(printf '%s\n' "${WG_CONFS[@]}" | tac ...); do
    wg-quick down "${tunnel}" || :
done
```

#### User model

- Container runs as **root** (WG requires root/NET_ADMIN)
- `PUID`/`PGID` env vars → create user `abc` for **file ownership**
- WG operations always execute as root

#### Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `PUID` / `PGID` | 1000 | File ownership |
| `PEERS` | — | Number/names of peers (enables server mode) |
| `SERVERURL` | `auto` | External IP (auto = detected via icanhazip.com) |
| `SERVERPORT` | `51820` | External WG port |
| `INTERNAL_SUBNET` | `10.13.13.0` | VPN subnet |
| `ALLOWEDIPS` | `0.0.0.0/0, ::/0` | AllowedIPs for peers |
| `PERSISTENTKEEPALIVE_PEERS` | — | Keepalive for specified peers |

---

### 1.2 Takeaways for our project

| linuxserver decision | Our decision | Rationale |
|---------------------|-------------|-----------|
| s6-overlay | tini + bash | Only 3 services, s6 is overkill. Tini is enough for zombie reaping and signal forwarding |
| WG as oneshot | WG as oneshot in entrypoint | Consistent — WG runs in-kernel, no separate daemon needed |
| Config generation from env | Generation from Pelican env | Same approach, but via Pelican UI instead of docker-compose |
| PUID/PGID | container (UID 1000) | Pelican standard — fixed user `container` |
| `/dev/net/tun` not needed | Keep in documentation | Some hosts may require it, safer to keep |

---

## 2. Nginx + PHP-FPM Docker Images

### 2.1 Official nginx (nginxinc/docker-nginx)

**Repository**: [github.com/nginxinc/docker-nginx](https://github.com/nginxinc/docker-nginx)

#### STOPSIGNAL SIGQUIT

```dockerfile
STOPSIGNAL SIGQUIT
CMD ["nginx", "-g", "daemon off;"]
```

**Why SIGQUIT instead of SIGTERM**:
- `SIGQUIT` → **graceful shutdown**: waits for in-flight requests to finish
- `SIGTERM` → **fast shutdown**: drops active connections
- Docker sends STOPSIGNAL on `docker stop` and waits for grace period (10s)
- PHP-FPM also uses SIGQUIT for graceful — intentional alignment

#### Log forwarding via symlink

```dockerfile
ln -sf /dev/stdout /var/log/nginx/access.log
ln -sf /dev/stderr /var/log/nginx/error.log
```

Docker captures stdout/stderr from PID 1. Symlinks route nginx logs
into `docker logs` without a sidecar or volume.

#### Entrypoint with `/docker-entrypoint.d/`

Numbered scripts execute in order before `exec "$@"`:

| Script | Purpose |
|--------|---------|
| `10-listen-on-ipv6-by-default.sh` | Enable IPv6 listen if available |
| `15-local-resolvers.envsh` | Export container DNS resolvers |
| `20-envsubst-on-templates.sh` | `envsubst` on templates from `/etc/nginx/templates/` |
| `30-tune-worker-processes.sh` | Tune workers to match cgroup CPU limits |

**Key pattern**: `.envsh` files are **sourced** (`. "$f"`), not executed —
they can export variables for subsequent scripts.

#### Worker process tuning (cgroup-aware)

Script `30-tune-worker-processes.sh` checks **5 CPU limit sources**:
1. Online CPU count (`getconf _NPROCESSORS_ONLN`)
2. cgroup v1 cpuset
3. cgroup v1 CPU quota
4. cgroup v2 cpuset
5. cgroup v2 CPU quota

Takes the **minimum**. Without this, `worker_processes auto` on a 64-core
host would create 64 workers in a container with `--cpus=2`.

#### Temp directories

Official image: `/var/cache/nginx/` (default)
LinuxServer/trafex: relocate to writable paths.

**Our approach**: we use `/home/container/tmp/nginx/` — persistent, writable,
visible in Pelican File Manager. Not `/tmp` (ephemeral) or `/var/cache` (read-only).

---

### 2.2 linuxserver/docker-baseimage-alpine-nginx

#### s6-overlay multi-process

```
s6-rc.d/
  init-nginx         (oneshot) → copy defaults, configure resolver, workers
  init-php           (oneshot) → create php-local.ini, www2.conf
  init-permissions   (oneshot) → fix ownership
  svc-nginx          (longrun) → nginx process
  svc-php-fpm        (longrun) → php-fpm process
```

#### Nginx run script with zombie cleanup

```bash
#!/usr/bin/with-contenv bash
# Kill zombie nginx processes before start
if pgrep -f "[n]ginx:" >/dev/null; then
    pkill -ef [n]ginx:
    sleep 1
fi
# If still alive — SIGKILL
if pgrep -f "[n]ginx:" >/dev/null; then
    pkill -9 -ef [n]ginx:
    sleep 1
fi
exec /usr/sbin/nginx -e stderr
```

#### PHP-FPM: TCP instead of Unix socket

```nginx
fastcgi_pass 127.0.0.1:9000;
```

**Why TCP**: simpler — no socket file permission issues,
no stale socket after crash. TCP overhead on localhost is minimal.

#### Logging: real files + logrotate

Unlike the official image, linuxserver writes to real files in
`/config/log/nginx/` and uses logrotate.
**Reason**: persistent volume `/config` lets users inspect logs after container recreation.

#### Configs from persistent volume

```
/config/nginx/nginx.conf
/config/nginx/site-confs/*.conf
/config/php/php-local.ini
/config/php/www2.conf
```

Defaults copied from `/defaults/` **only on first run**.
User can customize everything and changes survive restarts.

---

### 2.3 Official php-fpm (docker-library/php)

**PHP-FPM** = PHP FastCGI Process Manager. It runs as a separate process
that manages a pool of PHP worker processes. Nginx cannot execute PHP
directly — it forwards `.php` requests to FPM via a unix socket or TCP.

**Our image uses PHP 8.1** from Ubuntu 22.04 packages.

#### Socket vs TCP

Default: **TCP 0.0.0.0:9000** (for multi-container architecture).

**When Unix socket is better**:
- Nginx and PHP-FPM in the same container → socket is ~5-10% faster (no TCP stack)
- More secure — socket is only accessible locally

**Our approach**: Unix socket at `/home/container/tmp/php-fpm.sock` —
persistent path, single container, better performance.

#### Process management

| pm mode | Behavior | Best for |
|---------|----------|----------|
| `static` | Fixed number of workers | Dedicated high-traffic servers |
| `dynamic` | Maintains spare workers (default) | General purpose |
| `ondemand` | Workers spawned only on request | Low RAM, bursty traffic |

**Our choice**: `ondemand` with `max_children=5` — PHP requests are
infrequent for static file serving, saves memory when idle.

#### Logging

```ini
error_log = /proc/self/fd/2          # stderr
access.log = /proc/self/fd/2         # also stderr (NOT stdout!)
```

**Why access.log goes to stderr, not stdout**: PHP-FPM closes stdout on startup
(PHP bug #73886). Writing to `/proc/self/fd/1` does not work.

#### STOPSIGNAL SIGQUIT

Same as nginx — graceful shutdown. PHP-FPM waits for current requests to finish.

#### Config priority via zz- prefix

```ini
# zz-docker.conf — loaded LAST, overrides everything
daemonize = no
```

---

### 2.4 Init system comparison for multi-process containers

| Approach | PID 1 | Zombie reaping | Dependencies | Size | Example |
|----------|-------|---------------|-------------|------|---------|
| **s6-overlay** | s6-svscan | Yes | DAG (s6-rc) | ~5MB | linuxserver |
| **supervisord** | supervisord | No | None | ~50MB (Python) | trafex |
| **tini + bash** | tini | Yes | None | ~30KB | **our project** |
| **Two containers** | each own | Depends | Docker Compose | 0 | Docker best practice |

#### Why tini, not s6-overlay

1. Only **3 services** (WG oneshot + nginx + php-fpm) — s6 is overkill
2. Tini properly reaps zombies and forwards signals
3. 30KB vs 5MB — smaller attack surface
4. Simpler for Pelican users — readable bash script vs s6 DSL
5. Pelican sends SIGTERM on stop — tini forwards to all children

**Trade-off**: if one service crashes (php-fpm), the other (nginx) would
continue but return 502 errors. We solve this with `wait -n` — if any
process exits, the entrypoint exits too and Pelican can restart the container.

---

### 2.5 Health check patterns

#### Best approach: fpm-ping (validates full chain)

```ini
# php-fpm.conf
ping.path = /fpm-ping
```

```nginx
location ~ ^/(fpm-status|fpm-ping)$ {
    access_log off;
    allow 127.0.0.1;
    deny all;
    fastcgi_pass unix:/home/container/tmp/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

```dockerfile
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:7890/fpm-ping || exit 1
```

**Why best**: validates the FULL chain — nginx listens, php-fpm responds, socket works.

---

## 3. Pelican/Pterodactyl Eggs — Patterns and Conventions

### 3.1 Egg catalog

**Website**: [pelican-eggs.github.io/pelican](https://pelican-eggs.github.io/pelican/)
**Repository**: [github.com/pelican-eggs/eggs](https://github.com/pelican-eggs/eggs)

The catalog contains **200+ eggs** across categories:
- Game servers (Minecraft, Steam games, standalone)
- Chatbots (Discord, Twitch, TeamSpeak)
- Databases (MongoDB, Redis, MariaDB, PostgreSQL)
- Software (Code-Server, Gitea, Grafana, Uptime Kuma)
- Monitoring (Prometheus, Loki)
- Generic runtimes (Node.js, Python, Java, Go, Rust, etc.)
- Voice servers (Mumble, TeamSpeak)

### 3.2 Egg JSON format (PTDL_v2)

```jsonc
{
    "_comment": "...",
    "meta": {
        "version": "PTDL_v2",       // only format version
        "update_url": null            // URL for egg auto-update
    },
    "exported_at": "ISO-8601",
    "name": "Egg name",
    "author": "email@example.com",
    "description": "Description",
    "features": null,                 // reserved
    "docker_images": {
        "Display Name": "ghcr.io/org/image:tag"
    },
    "file_denylist": [],              // files hidden from File Manager
    "startup": "startup command with {{VARIABLES}}",
    "config": {
        "files": "{}",               // config parsing (json/yaml/ini/xml/file)
        "startup": "{\"done\": \"log line indicating readiness\"}",
        "logs": "{}",
        "stop": "^^C"                // stop command (^^C = SIGINT)
    },
    "scripts": {
        "installation": {
            "script": "bash installation script",
            "container": "image for installation",
            "entrypoint": "bash"
        }
    },
    "variables": [
        {
            "name": "Display name",
            "description": "Description for user",
            "env_variable": "UPPER_SNAKE_CASE",
            "default_value": "value",
            "user_viewable": true,    // visible to user
            "user_editable": true,    // user can modify
            "rules": "required|string|max:64",  // Laravel validation
            "field_type": "text"      // UI field type
        }
    ]
}
```

### 3.3 Two-phase process: Installation vs Runtime

| Phase | Container | User | Directory | Purpose |
|-------|-----------|------|-----------|---------|
| **Installation** | From `scripts.installation.container` | root | `/mnt/server/` | Download, build, configure |
| **Runtime** | From `docker_images` | container (UID 1000) | `/home/container/` | Run the server |

**Key point**: Installation script writes to `/mnt/server/`,
which at runtime is mounted as `/home/container/`.
Different paths, same volume.

**Important for Pelican eggs**: Runtime containers are **non-root** (UID 1000).
System directories (`/etc`, `/run`, `/var/log`) are **read-only**.
All runtime files must go to `/home/container/` (the persistent volume).

### 3.4 Standard Pelican entrypoint

```bash
#!/bin/bash
cd /home/container

# Substitute {{VARIABLES}} in STARTUP
MODIFIED_STARTUP=$(echo -e ${STARTUP} | sed -e 's/{{/${/g' -e 's/}}/}/g')
echo ":/home/container$ ${MODIFIED_STARTUP}"

# Execute
eval ${MODIFIED_STARTUP}
```

**Pattern**: egg sets `startup` in JSON, Pelican passes it as env `STARTUP`,
entrypoint substitutes variables and executes. Standard approach for simple eggs.

**Our case**: we do NOT use this pattern because we need to:
1. Run WG setup before main services
2. Run two processes (nginx + php-fpm) in parallel
3. Handle graceful shutdown with SIGQUIT
Our entrypoint is a custom script.

### 3.5 Startup detection (done pattern)

```json
"startup": "{\"done\": \"Listening on \"}"
```

Pelican monitors container stdout and marks the server as running
when it sees the string from `done`. Examples from real eggs:

| Egg | Done pattern |
|-----|-------------|
| Uptime Kuma | `[SERVER] INFO: Listening on ` |
| Gitea | `Listen: ` |
| Minecraft | `Done (` |

**Our approach**: `"done": "All services started successfully!"` —
printed by our entrypoint after all services start.

### 3.6 Stop signals

- `^^C` → sends SIGINT (Ctrl+C) — **most common pattern**
- `stop` → sends "stop" command to stdin (for Minecraft)
- `^C` → single SIGINT

### 3.7 Config file parsing

```json
"files": "{\"custom/app.ini\": {\"parser\": \"file\", \"find\": {\"SSH_PORT\": \"SSH_PORT: {{server.build.env.SSH_PORT}}\"}}}"
```

Supported parsers: `file`, `yaml`, `json`, `xml`, `ini`, `properties`.
Allows Pelican to auto-substitute variables in configs before startup.

**Our approach**: we don't use config parsing — `sed` substitution
in the entrypoint is simpler and more predictable for our case.

### 3.8 Docker Images (Yolks)

**Repository**: [github.com/pelican-eggs/yolks](https://github.com/pelican-eggs/yolks)

Yolks is a collection of base images for eggs:

| Category | Examples | Registry |
|----------|---------|---------|
| OS | Alpine, Debian, Ubuntu | `ghcr.io/pelican-eggs/yolks:debian` |
| Runtime | Java 8-25, Node.js 20-24, Python 3.7-3.14 | `ghcr.io/pelican-eggs/yolks:java_21` |
| Games | Source, Rust, Arma3, Valheim | `ghcr.io/pelican-eggs/games:source` |
| Installers | Debian, SteamCMD | `ghcr.io/pelican-eggs/installers:debian` |
| DB | PostgreSQL, MongoDB, MariaDB, Redis | `ghcr.io/pelican-eggs/yolks:postgres_16` |

**Our approach**: custom image because no yolk contains WireGuard + Nginx + PHP-FPM 8.1.

### 3.9 Variable patterns

| Pattern | Example env | Example rules |
|---------|-----------|-------------|
| Port | `SERVER_PORT` | `required\|integer\|between:1024,65535` |
| Password | `RCON_PASSWORD` | `required\|string\|max:64` |
| Version | `VERSION` | `required\|string\|max:20` |
| Boolean | `AUTO_UPDATE` | `required\|boolean` |
| Enum | `DISABLE_SSH` | `required\|string\|in:true,false` |
| Nullable | `WG_PRIVATE_KEY` | `nullable\|string` |

### 3.10 Installation script patterns

Common patterns from real eggs:

```bash
#!/bin/ash
# 1. Install dependencies
apk add --no-cache git curl jq

# 2. Create directories
mkdir -p /mnt/server

# 3. Download/clone
git clone --single-branch --branch ${GIT_BRANCH} ${GIT_URL} /mnt/server

# 4. Install app dependencies
cd /mnt/server && npm install

# 5. Create default configs (don't overwrite existing)
if [ ! -f /mnt/server/config.json ]; then
    cp /mnt/server/config.example.json /mnt/server/config.json
fi
```

### 3.11 Existing VPN eggs

| Project | Approach | Capabilities |
|---------|----------|-------------|
| [Pterodactyl-VPS-Egg](https://github.com/ysdragon/Pterodactyl-VPS-Egg) | Full VPS in container | NET_ADMIN, SYS_MODULE, etc. |
| [Tailscale egg](https://builtbybit.com/resources/pterodactyl-tailscale-vpn-connection-egg.54337/) | Tailscale mesh VPN | NET_ADMIN, NET_RAW |

**Conclusion**: precedents for NET_ADMIN in Pelican/Pterodactyl exist.
Not standard, but documented and working.

---

## 4. Decision Summary

| Aspect | linuxserver/wg | official nginx | linuxserver/nginx | php-fpm official | **Our project** | Rationale |
|--------|---------------|---------------|-------------------|-----------------|---------------|-----------|
| Init | s6-overlay | nginx PID 1 | s6-overlay | php-fpm PID 1 | **tini** | Lightweight, sufficient for 3 services |
| Signal | s6 manages | SIGQUIT | s6 manages | SIGQUIT | **SIGQUIT via trap** | Graceful shutdown for nginx + php-fpm |
| Logs | files + logrotate | symlink→stdout | files + logrotate | stderr | **files in /home/container/logs/** | Visible in Pelican File Manager |
| PHP socket | — | — | TCP 127.0.0.1:9000 | TCP 0.0.0.0:9000 | **Unix socket** | Single container, socket is faster |
| Temp dirs | — | /var/cache | /tmp/ | — | **/home/container/tmp/** | Persistent, not ephemeral /tmp |
| User | root + PUID | nginx (101) | root + abc (PUID) | www-data | **container (UID 1000)** | Pelican standard, non-root |
| Config | persistent /config | envsubst templates | persistent /config | layered .conf | **persistent /home/container/** | Pelican File Manager |
| Workers | — | cgroup-aware auto | nproc-based | dynamic pm | **auto + ondemand pm** | Simple, low traffic expected |
| Health check | None | None | None | None | **fpm-ping** | Validates full nginx→php-fpm chain |
| WG config | templates + gen | — | — | — | **gen from env to /home/container/wg/** | Persistent, visible, like linuxserver |
| PHP version | — | — | auto | multiple tags | **8.1 (Ubuntu 22.04)** | LTS, stable |

---

## 5. Implementation status

Based on research, these improvements have been implemented:

### Done
- [x] Health check via fpm-ping (validates nginx→php-fpm→socket chain)
- [x] sysctl `net.ipv4.conf.all.src_valid_mark=1` for WG client mode
- [x] Zombie cleanup before nginx/php-fpm start (linuxserver pattern)
- [x] SIGQUIT for graceful shutdown of nginx and php-fpm
- [x] All runtime files in /home/container/ (persistent, no /tmp)
- [x] PHP-FPM catch_workers_output, decorate_workers_output
- [x] Default page restoration if user deletes configs

### Not implemented (low priority)
- [ ] envsubst for nginx templates (sed works fine)
- [ ] Worker process tuning with cgroup limits
- [ ] QR-code generation for WG configs
- [ ] nginx -s reload documentation (config reload without restart)

---

## Sources

- [linuxserver/docker-wireguard](https://github.com/linuxserver/docker-wireguard)
- [docs.linuxserver.io/images/docker-wireguard](https://docs.linuxserver.io/images/docker-wireguard/)
- [nginxinc/docker-nginx](https://github.com/nginxinc/docker-nginx)
- [linuxserver/docker-baseimage-alpine-nginx](https://github.com/linuxserver/docker-baseimage-alpine-nginx)
- [docker-library/php (fpm)](https://github.com/docker-library/php)
- [trafex/docker-php-nginx](https://github.com/TrafeX/docker-php-nginx)
- [pelican-eggs/eggs](https://github.com/pelican-eggs/eggs)
- [pelican-eggs/yolks](https://github.com/pelican-eggs/yolks)
- [Pterodactyl: Creating a Custom Egg](https://pterodactyl.io/community/config/eggs/creating_a_custom_egg.html)
- [Pterodactyl: Creating a Custom Image](https://pterodactyl.io/community/config/eggs/creating_a_custom_image.html)
- [pelican-eggs.github.io/pelican](https://pelican-eggs.github.io/pelican/)
- [Pterodactyl-VPS-Egg](https://github.com/ysdragon/Pterodactyl-VPS-Egg)
- [DeepWiki: pelican-eggs/eggs](https://deepwiki.com/pelican-eggs/eggs)
