# Pelican Egg: WireGuard + Nginx + PHP-FPM

Кастомный egg для [Pelican Panel](https://pelican.dev) (форк Pterodactyl). Запускает контейнер с WireGuard VPN-клиентом и веб-сервером Nginx + PHP-FPM — идеально для хостинга интерактивной карты Minecraft (BlueMap, Dynmap, Squaremap и т.д.), данные которой пушатся по WG-туннелю с удалённого MC-сервера.

## Быстрый старт

### 1. Подготовка Wings

В файле `/etc/pelican/config.yml` на ноде добавь:

```yaml
docker:
  network:
    # ... существующие настройки ...
  container_pid_limit: 512
  installer_limits:
    memory: 1024
    cpu: 100
  overhead:
    default:
      # ... существующие настройки ...
  allowed_capabilities:
    - NET_ADMIN          # ← WireGuard нужен для создания wg-интерфейса
  allowed_devices:
    - /dev/net/tun       # ← устройство для VPN-туннелей
```

После изменения перезапусти Wings:
```bash
sudo systemctl restart wings
```

### 2. Сборка Docker-образа

```bash
cd docker/
docker build -t ghcr.io/pelmentor/pelican-wg-nginx:latest .
```

Или используй готовый образ (если опубликован).

### 3. Импорт Egg в Pelican Panel

1. Перейди в **Admin → Nests**
2. Нажми **Import Egg**
3. Загрузи файл `egg-wg-nginx.json`
4. Создай сервер на основе этого egg

### 4. Настройка переменных сервера

При создании сервера в Pelican заполни:

| Переменная | Описание | Пример |
|------------|----------|--------|
| `WG_PRIVATE_KEY` | Приватный ключ WireGuard этого контейнера | `aAbBcC...=` |
| `WG_ADDRESS` | IP-адрес контейнера в WG-сети | `10.0.0.2/24` |
| `WG_PEER_PUBLIC_KEY` | Публичный ключ пира (MC-сервера) | `xXyYzZ...=` |
| `WG_PEER_ENDPOINT` | Адрес и порт WG на стороне пира | `vds.example.com:51820` |
| `WG_PEER_ALLOWED_IPS` | Разрешённые IP через туннель | `10.0.0.1/32` |
| `WG_LISTEN_PORT` | Порт WireGuard (UDP) | `51820` |

### 5. Загрузка файлов карты

Через Pelican File Manager загрузи файлы карты в папку `webroot/`.

Или настрой на MC-сервере автоматический пуш через WG-туннель:
```bash
# Пример: rsync тайлов BlueMap каждые 5 минут
*/5 * * * * rsync -avz /path/to/bluemap/web/ user@10.0.0.2:/home/container/webroot/
```

### 6. Настройка OPNsense (если применимо)

Если Unraid-сервер стоит за OPNsense — нужно пробросить порты.
Подробности в [OPNSENSE.md](OPNSENSE.md).

## Структура проекта

```
.
├── README.md              ← этот файл
├── ARCHITECTURE.md        ← схема архитектуры, решения и prior art
├── RESEARCH.md            ← анализ существующих решений (linuxserver, official nginx/php, pelican-eggs)
├── CHANGELOG.md           ← история изменений
├── OPNSENSE.md            ← настройка firewall и port forward на OPNsense
├── WINGS-CONFIG.md        ← подробная настройка Wings config.yml
├── egg-wg-nginx.json      ← egg для импорта в Pelican
└── docker/
    ├── Dockerfile         ← сборка образа
    ├── entrypoint.sh      ← скрипт запуска сервисов
    ├── nginx.conf         ← шаблон конфига Nginx
    └── php-fpm.conf       ← шаблон конфига PHP-FPM
```

## Требования

- **Unraid OS** с Docker
- **Pelican Panel** + Wings
- **OPNsense** (или другой роутер) с настроенным port forward
- NET_ADMIN capability и /dev/net/tun (см. [WINGS-CONFIG.md](WINGS-CONFIG.md))
- Сгенерированные WireGuard ключи (см. `wg genkey | tee privatekey | wg pubkey > publickey`)
