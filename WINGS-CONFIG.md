# Wings — config.yml Setup for This Egg

WireGuard inside a Docker container requires special privileges
that Wings does not grant by default.

## What Needs to Be Changed

File: `/etc/pelican/config.yml` (on Unraid: `/mnt/user/appdata/pelican/config.yml` or a similar path depending on your installation)

### Add the NET_ADMIN Capability

```yaml
docker:
  allowed_capabilities:
    - NET_ADMIN
```

**Why**: WireGuard uses the `ioctl` system call and netlink to create
the `wg0` network interface. Without `NET_ADMIN` the container cannot create
network interfaces, modify routes, or configure firewall rules.

### Add the /dev/net/tun Device

```yaml
docker:
  allowed_devices:
    - /dev/net/tun
```

**Why**: `/dev/net/tun` is a character device used to create TUN/TAP interfaces.
WireGuard uses it to create a virtual network interface for the tunnel.
Without this device, `wg-quick up` will fail with an error.

### Full Example of the docker Section

```yaml
docker:
  network:
    interface: 172.18.0.1
    dns:
      - 1.1.1.1
      - 1.0.0.1
    name: pelican_nw
    ispn: false
    driver: bridge
    network_mode: ""
    is_internal: false
    enable_icc: true
    network_mtu: 1500
    interfaces:
      v4:
        subnet: 172.18.0.0/16
        gateway: 172.18.0.1
  container_pid_limit: 512
  installer_limits:
    memory: 1024
    cpu: 100
  overhead:
    default:
      memory: 0
      cpu: 0
  # === ADD FOR WIREGUARD ===
  allowed_capabilities:
    - NET_ADMIN
  allowed_devices:
    - /dev/net/tun
```

## After Making Changes

```bash
sudo systemctl restart wings
```

## Security

**NET_ADMIN** is a powerful capability. It allows the container to:
- Create/delete network interfaces
- Modify routes and firewall rules
- Enable promiscuous mode

This is a **global** Wings setting -- it enables NET_ADMIN for **all** servers on this node.

Recommendations:
- If the node hosts servers from untrusted users, dedicate a separate node for WG containers
- Or run a separate Wings instance with this configuration

## Verification

After starting the server in Pelican, the container logs should show:
```
[INFO] Starting WireGuard...
[INFO] WireGuard is up. Interface status:
interface: wg0
  public key: <...>
  private key: (hidden)
  listening port: <...>
```

If you see `RTNETLINK answers: Operation not permitted` -- the capability was not applied.
If you see `Cannot open /dev/net/tun` -- the device was not attached.

## Note: PHP 8.1 Requirement

This egg uses **PHP 8.1** (php8.1-fpm). Make sure the base image or system packages
include the `php8.1-fpm` package. The PHP-FPM pool is configured to listen on a Unix
socket at `/home/container/tmp/php-fpm.sock` and runs under the `container` user.

## Note: Container User and UID

Pelican/Wings runs containers as the `container` user (**UID 1000**, non-root) by default.
All runtime paths (PID files, sockets, Nginx temp directories) are placed under
`/home/container/tmp/` so they are writable without root privileges.

Our entrypoint handles both cases:
- **If root**: WireGuard works fully (wg-quick requires root for netlink operations)
- **If UID 1000 (non-root)**: Nginx + PHP-FPM work normally; WG may fail to start
  depending on whether the NET_ADMIN capability is granted

For full WG support, make sure Wings does not override the container user,
or configure the egg with `force_outgoing_ip` and the required capabilities.
