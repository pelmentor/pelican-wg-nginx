# Architecture — WireGuard + Nginx + PHP-FPM Egg

## Обзор

Этот egg для Pelican Panel запускает контейнер с тремя сервисами:
- **WireGuard** — VPN-клиент для связи с удалённым MC-сервером
- **Nginx** — веб-сервер для раздачи интерактивной карты Minecraft
- **PHP-FPM** — обработка PHP-скриптов (если карта использует PHP-бэкенд)

## Схема сети

```
                        [Интернет]
                            │
                            ▼
┌───────────────────────────────────────────┐
│            OPNsense (роутер)              │
│  - Управляет gateway, VLAN, firewall      │
│  - Port forward: WG UDP + HTTP TCP        │
│    на Unraid сервер                       │
└───────────────────┬───────────────────────┘
                    │ LAN / VLAN
                    ▼
┌─────────────────────────────────────────────────────────────────┐
│              Unraid OS (Ryzen 3950x, 16 ядер, 16 ГБ RAM)       │
│              Docker bridge network                              │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              Pelican Wings (Docker)                      │    │
│  │              /mnt/user/appdata/pelican/                  │    │
│  │                                                         │    │
│  │  ┌───────────────────────────────────────────────────┐  │    │
│  │  │         Docker-контейнер (этот egg)                │  │    │
│  │  │         NET_ADMIN + /dev/net/tun                   │  │    │
│  │  │                                                   │  │    │
│  │  │  ┌─────────┐   ┌─────────┐   ┌──────────────┐   │  │    │
│  │  │  │WireGuard│   │  Nginx  │   │   PHP-FPM    │   │  │    │
│  │  │  │ (wg0)   │   │ (:8080) │   │ (unix sock)  │   │  │    │
│  │  │  └────┬────┘   └────┬────┘   └──────┬───────┘   │  │    │
│  │  │       │              │               │           │  │    │
│  │  │       │              └───────────────┘           │  │    │
│  │  │       │         nginx проксирует .php            │  │    │
│  │  │       │         на php-fpm через unix socket     │  │    │
│  │  └───────┼───────────────────────────────────────────┘  │    │
│  │          │ WG туннель (UDP)                              │    │
│  └──────────┼───────────────────────────────────────────────┘    │
│             │                                                    │
└─────────────┼────────────────────────────────────────────────────┘
              │
              │  WireGuard UDP туннель
              │  (через интернет, через OPNsense NAT)
              ▼
┌─────────────────────────────┐
│       Чужой VDS             │
│                             │
│  ┌───────────────────────┐  │
│  │   Minecraft сервер    │  │
│  │   + плагин карты      │  │
│  │   (BlueMap/Dynmap)    │  │
│  └───────────┬───────────┘  │
│              │              │
│   Пушит тайлы карты по WG  │
│   туннелю (rsync/scp/http) │
│   на внутренний IP wg0     │
└─────────────────────────────┘
```

## Потоки трафика

### 1. WireGuard туннель (MC-сервер → контейнер)
- **Протокол**: UDP
- **Направление**: MC-сервер на VDS подключается как WG-пир
- **Цель**: MC-сервер пушит данные карты (тайлы) через WG-туннель
- **Порт**: WG слушает на порту, назначенном через Pelican (переменная `SERVER_PORT`)
- **Внутренний IP**: назначается через переменные egg (например `10.0.0.2/24`)

### 2. Веб-трафик (пользователи → Nginx)
- **Протокол**: HTTP
- **Порт**: `SERVER_PORT` (основной порт аллокации Pelican, по умолчанию 8080)
- **Путь**: Nginx слушает на `0.0.0.0:SERVER_PORT` → раздаёт статику из `/home/container/webroot`
- **PHP**: запросы к `.php` файлам проксируются на PHP-FPM через unix socket

### 3. Передача данных карты (через WG туннель)
- MC-сервер может пушить тайлы через:
  - `rsync` по SSH через WG-туннель
  - HTTP PUT/POST на внутренний endpoint
  - `scp` напрямую в `/home/container/webroot/`
- Конкретный метод зависит от плагина карты на стороне MC-сервера

## Порты

| Порт | Протокол | Назначение |
|------|----------|------------|
| `SERVER_PORT` | TCP | Nginx HTTP (основная аллокация) |
| `WG_LISTEN_PORT` | UDP | WireGuard (дополнительная аллокация) |

## Файловая структура контейнера

```
/home/container/              ← корень, управляемый через Pelican File Manager
├── webroot/                  ← документы сайта (сюда кладутся тайлы карты)
│   └── index.html
├── wg/
│   └── wg0.conf              ← генерируется из переменных egg при старте
├── nginx/
│   └── nginx.conf             ← конфиг Nginx (можно редактировать через Panel)
└── php/
    └── php-fpm.conf           ← конфиг PHP-FPM
```

## Требования к хосту (Wings)

- **NET_ADMIN capability** — нужен WireGuard для создания сетевого интерфейса
- **/dev/net/tun** — устройство для туннелей, монтируется в контейнер
- **sysctl `net.ipv4.conf.all.src_valid_mark=1`** — для WG client mode
- Подробная настройка: [WINGS-CONFIG.md](WINGS-CONFIG.md)

## Архитектурные решения и Prior Art

Подробный анализ существующих решений — в [RESEARCH.md](RESEARCH.md).
Здесь — краткая сводка ключевых решений и почему они приняты.

### Init-система: tini (а не s6-overlay / supervisord)

| Вариант | Используют | Плюсы | Минусы | Наше решение |
|---------|-----------|-------|--------|-------------|
| s6-overlay | linuxserver/wireguard, linuxserver/nginx | DAG зависимостей, readiness probes | +5MB, сложный DSL | Overkill для 3 сервисов |
| supervisord | trafex/php-nginx | Простая конфигурация | +50MB (Python), нет zombie reaping | Слишком тяжёлый |
| **tini** | **наш проект** | 30KB, zombie reaping, signal forwarding | Нет supervision | Достаточно: `wait -n` ловит crash |
| Два контейнера | Docker best practice | Изоляция, масштабирование | Сложнее для Pelican | Не подходит: Pelican = 1 egg = 1 контейнер |

### WireGuard: oneshot, не демон

Ref: linuxserver/docker-wireguard определяет `svc-wireguard` как **oneshot** в s6.
WireGuard работает в ядре — `wg-quick up` конфигурирует интерфейс и выходит.
Нет процесса-демона для мониторинга. Мы делаем то же: запускаем WG в entrypoint
до старта nginx/php-fpm, и отдельно гасим в cleanup.

### PHP-FPM: Unix socket (а не TCP)

| Вариант | Используют | Обоснование |
|---------|-----------|------------|
| TCP 0.0.0.0:9000 | official php-fpm | Для multi-container (nginx в отдельном контейнере) |
| TCP 127.0.0.1:9000 | linuxserver/nginx | Простота, нет проблем с permissions |
| **Unix socket** | **наш проект**, trafex | ~5-10% быстрее TCP, один контейнер — socket логичнее |

### Логирование: файлы (а не stdout symlinks)

| Вариант | Используют | Обоснование |
|---------|-----------|------------|
| symlink → /dev/stdout | official nginx | Логи в `docker logs` |
| Реальные файлы + logrotate | linuxserver | Persistent volume, логи после рестарта |
| **Реальные файлы** | **наш проект** | Видны в Pelican File Manager, пользователь может скачать |

### Nginx temp directories: /tmp/

Ref: trafex/docker-php-nginx — при запуске от non-root пользователя стандартные
пути `/var/cache/nginx/` не writable. Решение: `client_body_temp_path /tmp/nginx/...`

### Health check: fpm-ping

Ref: trafex/docker-php-nginx — `/fpm-ping` проверяет всю цепочку:
nginx слушает → проксирует на php-fpm → socket работает → PHP-FPM отвечает "pong".
Это лучше, чем проверка только nginx (stub_status) или только tcp-порта.

### Graceful shutdown: SIGQUIT

Ref: official nginx + official php-fpm — оба используют `STOPSIGNAL SIGQUIT`
для graceful shutdown (дожидаются завершения in-flight запросов).
Наш entrypoint отправляет SIGQUIT обоим процессам при получении SIGTERM/SIGINT от Pelican.

### Egg формат: PTDL_v2 conventions

Ref: pelican-eggs/eggs — стандартные паттерны:
- `"done": "строка"` в `config.startup` для определения готовности
- `"^^C"` в `config.stop` для SIGINT
- Installation script пишет в `/mnt/server/` (маунтится как `/home/container/` при runtime)
- Переменные: `UPPER_SNAKE_CASE`, Laravel validation rules
- Docker images: key-value mapping display name → registry URL
