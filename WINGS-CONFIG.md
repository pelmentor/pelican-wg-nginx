# Wings — Настройка config.yml для этого egg

WireGuard внутри Docker-контейнера требует специальных привилегий,
которые Wings по умолчанию не выдаёт.

## Что нужно изменить

Файл: `/etc/pelican/config.yml` (на Unraid: `/mnt/user/appdata/pelican/config.yml` или аналогичный путь в зависимости от установки)

### Добавить NET_ADMIN capability

```yaml
docker:
  allowed_capabilities:
    - NET_ADMIN
```

**Зачем**: WireGuard использует системный вызов `ioctl` и netlink для создания
сетевого интерфейса `wg0`. Без `NET_ADMIN` контейнер не может создавать
сетевые интерфейсы, менять маршруты и настраивать firewall-правила.

### Добавить /dev/net/tun device

```yaml
docker:
  allowed_devices:
    - /dev/net/tun
```

**Зачем**: `/dev/net/tun` — это character device для создания TUN/TAP интерфейсов.
WireGuard создаёт через него виртуальный сетевой интерфейс для туннеля.
Без этого устройства `wg-quick up` упадёт с ошибкой.

### Полный пример секции docker

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
  # === ДОБАВИТЬ ДЛЯ WIREGUARD ===
  allowed_capabilities:
    - NET_ADMIN
  allowed_devices:
    - /dev/net/tun
```

## После изменения

```bash
sudo systemctl restart wings
```

## Безопасность

**NET_ADMIN** — это мощная capability. Она позволяет контейнеру:
- Создавать/удалять сетевые интерфейсы
- Менять маршруты и firewall-правила
- Включать promiscuous mode

Это **глобальная** настройка Wings — она разрешает NET_ADMIN для **всех** серверов на этой ноде.

Рекомендации:
- Если на ноде есть серверы от недоверенных пользователей — выдели отдельную ноду для WG-контейнеров
- Или используй отдельный инстанс Wings с этой настройкой

## Проверка

После запуска сервера в Pelican, в логах контейнера должно быть:
```
[INFO] Starting WireGuard...
[INFO] WireGuard is up. Interface status:
interface: wg0
  public key: <...>
  private key: (hidden)
  listening port: <...>
```

Если видишь ошибку `RTNETLINK answers: Operation not permitted` — capability не применилась.
Если видишь `Cannot open /dev/net/tun` — устройство не подключено.

## Примечание: запуск от root

WireGuard (wg-quick) требует root для полноценной работы. Pelican/Wings по умолчанию
запускает контейнеры от `container` (UID 1000).

Наш entrypoint обрабатывает оба случая:
- **Если root**: WireGuard работает полностью
- **Если UID 1000**: nginx + php-fpm работают, WG может не запуститься (зависит от capability)

Для полной поддержки WG убедись, что Wings не переопределяет пользователя контейнера,
или настрой egg с `force_outgoing_ip` и необходимыми capabilities.
