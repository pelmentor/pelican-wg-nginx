# OPNsense — Настройка firewall и port forward

OPNsense стоит перед Unraid-сервером и управляет всем входящим трафиком.
Для работы этого egg нужно пробросить два порта.

## Необходимые Port Forward правила

### 1. HTTP (веб-карта)

Чтобы пользователи могли открыть карту из интернета.

| Параметр | Значение |
|----------|----------|
| Interface | WAN |
| Protocol | TCP |
| Source | any |
| Destination | WAN address |
| Destination port | выбранный внешний порт (напр. `8080`) |
| Redirect target IP | IP Unraid-сервера в LAN (напр. `192.168.1.10`) |
| Redirect target port | порт, назначенный серверу в Pelican (SERVER_PORT) |

**Путь в OPNsense**: Firewall → NAT → Port Forward → Add

### 2. WireGuard (VPN-туннель)

Чтобы MC-сервер на VDS мог подключиться к WG внутри контейнера.
Нужен **только если контейнер выступает WG-сервером** (слушает входящие).
Если контейнер подключается к VDS как клиент — проброс не нужен.

| Параметр | Значение |
|----------|----------|
| Interface | WAN |
| Protocol | UDP |
| Source | IP VDS-сервера (или any) |
| Destination | WAN address |
| Destination port | WG_LISTEN_PORT (напр. `51820`) |
| Redirect target IP | IP Unraid-сервера в LAN |
| Redirect target port | порт WG, назначенный в Pelican |

**Путь в OPNsense**: Firewall → NAT → Port Forward → Add

## Firewall Rules

Port forward в OPNsense автоматически создаёт связанное firewall-правило.
Убедись что:

1. На интерфейсе WAN правило разрешает входящий трафик на указанные порты
2. На интерфейсе LAN нет правил, блокирующих трафик к Unraid-серверу

## Схема прохождения трафика

```
Пользователь (браузер)
    │
    │ HTTP TCP :8080
    ▼
[OPNsense WAN] ──NAT──→ [Unraid:8080] ──Docker──→ [Контейнер Nginx :8080]
                                                        │
                                                        ▼
                                                   webroot/index.html


MC-сервер (VDS)
    │
    │ WG UDP :51820
    ▼
[OPNsense WAN] ──NAT──→ [Unraid:51820] ──Docker──→ [Контейнер WireGuard wg0]
```

## Примечания

- Если используешь VLAN на OPNsense — убедись что Unraid-сервер и Pelican Wings
  находятся в правильном VLAN и firewall-правила между VLAN разрешают нужный трафик
- PersistentKeepalive=25 в WG-конфиге поддерживает NAT-маппинг на OPNsense
  (без него UDP-сессия может протухнуть и туннель отвалится)
