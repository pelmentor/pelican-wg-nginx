# OPNsense — Firewall and Port Forward Configuration

OPNsense sits in front of the Unraid server and manages all incoming traffic.
This egg requires two ports to be forwarded.

## Required Port Forward Rules

### 1. HTTP (web map)

Allows users to access the map from the internet.

| Parameter | Value |
|-----------|-------|
| Interface | WAN |
| Protocol | TCP |
| Source | any |
| Destination | WAN address |
| Destination port | chosen external port (e.g. `8080`) |
| Redirect target IP | Unraid server LAN IP (e.g. `192.168.1.10`) |
| Redirect target port | port assigned to the server in Pelican (SERVER_PORT) |

**OPNsense path**: Firewall -> NAT -> Port Forward -> Add

### 2. WireGuard (VPN tunnel)

Allows the MC server on the VDS to connect to the WG instance inside the container.
Required **only if the container acts as a WG server** (listening for incoming connections).
If the container connects to the VDS as a client, no port forward is needed.

| Parameter | Value |
|-----------|-------|
| Interface | WAN |
| Protocol | UDP |
| Source | VDS server IP (or any) |
| Destination | WAN address |
| Destination port | WG_LISTEN_PORT (e.g. `51820`) |
| Redirect target IP | Unraid server LAN IP |
| Redirect target port | WG port assigned in Pelican |

**OPNsense path**: Firewall -> NAT -> Port Forward -> Add

## Firewall Rules

Port forwards in OPNsense automatically create an associated firewall rule.
Make sure that:

1. The WAN interface rule allows incoming traffic on the specified ports
2. No LAN interface rules block traffic to the Unraid server

## Traffic Flow Diagram

```
User (browser)
    |
    | HTTP TCP :8080
    v
[OPNsense WAN] --NAT--> [Unraid:8080] --Docker--> [Container Nginx :8080]
                                                        |
                                                        v
                                                   webroot/index.html


MC server (VDS)
    |
    | WG UDP :51820
    v
[OPNsense WAN] --NAT--> [Unraid:51820] --Docker--> [Container WireGuard wg0]
```

## Notes

- If you use VLANs on OPNsense, make sure the Unraid server and Pelican Wings
  are in the correct VLAN and that firewall rules between VLANs allow the required traffic
- `PersistentKeepalive=25` in the WG config keeps the NAT mapping alive on OPNsense
  (without it the UDP session can expire and the tunnel will drop)
