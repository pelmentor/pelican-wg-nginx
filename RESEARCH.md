# Research — Существующие решения и архитектурные паттерны

Этот документ фиксирует результаты исследования существующих Docker-образов
и Pelican/Pterodactyl egg'ов, чтобы обосновать наши архитектурные решения
и не изобретать велосипед.

---

## 1. Docker-образы WireGuard

### 1.1 linuxserver/docker-wireguard

**Репозиторий**: [github.com/linuxserver/docker-wireguard](https://github.com/linuxserver/docker-wireguard)
**Документация**: [docs.linuxserver.io/images/docker-wireguard](https://docs.linuxserver.io/images/docker-wireguard/)

#### Init-система: s6-overlay v3

LinuxServer использует **s6-overlay** с сервис-менеджером `s6-rc`. Порядок загрузки:

```
s6-rc.d/
  init-wireguard-module  (oneshot) → проверяет наличие WG-модуля в ядре
  init-wireguard-confs   (oneshot) → генерирует/обновляет конфиги
  svc-coredns            (longrun) → DNS для пиров
  svc-wireguard          (oneshot) → wg-quick up (WG работает в ядре, не демон)
```

**Ключевой инсайт**: WireGuard — это **oneshot**, а не longrun-сервис.
Он работает в ядре, `wg-quick up` только конфигурирует интерфейс и выходит.
Нет процесса-демона, который нужно мониторить.

#### Генерация конфигов

- Шаблоны в `/config/templates/server.conf` и `/config/templates/peer.conf`
- Ключи хранятся per-peer в `/config/peerN/`
- IP-адреса назначаются итерацией `.2`–`.254` в подсети
- Конфиги перегенерируются при изменении env-переменных (сравнение с `/config/.donoteditthisfile`)
- QR-коды генерируются через `qrencode`

#### Capabilities и устройства

| Требование | Обязательность | Зачем |
|-----------|---------------|-------|
| `NET_ADMIN` | **Обязательно** | Создание WG-интерфейса, управление маршрутами, iptables |
| `SYS_MODULE` | Опционально | Загрузка WG kernel-модуля (если не загружен на хосте) |
| `/dev/net/tun` | **НЕ нужен для WG** | WireGuard использует netlink, а не TUN/TAP. /dev/net/tun нужен OpenVPN |
| `net.ipv4.conf.all.src_valid_mark=1` | Для клиента | sysctl, нужен в client-mode |

**Важное открытие**: `/dev/net/tun` технически **не нужен** для WireGuard!
WG создаёт интерфейс через `ip link add type wireguard` (netlink API),
а не через TUN/TAP device. Однако некоторые ядра/конфигурации могут требовать его —
лучше оставить на всякий случай.

#### Graceful shutdown

Скрипт `svc-wireguard/down` → `svc-wireguard/finish`:
```bash
# Туннели закрываются в ОБРАТНОМ порядке (tac)
for tunnel in $(printf '%s\n' "${WG_CONFS[@]}" | tac ...); do
    wg-quick down "${tunnel}" || :
done
```

#### Пользователь

- Контейнер работает от **root** (WG требует root/NET_ADMIN)
- `PUID`/`PGID` env vars → создают пользователя `abc` для **владения файлами**
- WG-операции всегда выполняются от root

#### Переменные окружения

| Переменная | По умолчанию | Назначение |
|-----------|-------------|-----------|
| `PUID` / `PGID` | 1000 | Владелец файлов |
| `PEERS` | — | Количество/имена пиров (включает режим сервера) |
| `SERVERURL` | `auto` | Внешний IP (auto = определяется через icanhazip.com) |
| `SERVERPORT` | `51820` | Внешний WG-порт |
| `INTERNAL_SUBNET` | `10.13.13.0` | Подсеть VPN |
| `ALLOWEDIPS` | `0.0.0.0/0, ::/0` | AllowedIPs для пиров |
| `PERSISTENTKEEPALIVE_PEERS` | — | Keepalive для указанных пиров |

---

### 1.2 Выводы для нашего проекта

| Решение linuxserver | Наше решение | Обоснование |
|--------------------|-------------|------------|
| s6-overlay | tini + bash | У нас всего 3 сервиса, s6 — overkill. Tini достаточно для zombie reaping и signal forwarding |
| WG как oneshot | WG как oneshot в entrypoint | Согласуется — WG работает в ядре, не нужен отдельный демон |
| Генерация конфигов из env | Генерация из env Pelican | Тот же подход, но через Pelican UI вместо docker-compose |
| PUID/PGID | container (UID 1000) | Pelican стандарт — фиксированный пользователь container |
| `/dev/net/tun` не нужен | Оставляем в документации | На некоторых хостах может понадобиться, безопаснее оставить |

---

## 2. Docker-образы Nginx + PHP-FPM

### 2.1 Официальный образ nginx (nginxinc/docker-nginx)

**Репозиторий**: [github.com/nginxinc/docker-nginx](https://github.com/nginxinc/docker-nginx)

#### STOPSIGNAL SIGQUIT

```dockerfile
STOPSIGNAL SIGQUIT
CMD ["nginx", "-g", "daemon off;"]
```

**Почему SIGQUIT, а не SIGTERM**:
- `SIGQUIT` → **graceful shutdown**: дожидается завершения обработки текущих запросов
- `SIGTERM` → **fast shutdown**: обрывает активные соединения
- Docker отправляет STOPSIGNAL при `docker stop` и ждёт grace period (10s)
- PHP-FPM тоже использует SIGQUIT для graceful — намеренная унификация

#### Логирование через symlink

```dockerfile
ln -sf /dev/stdout /var/log/nginx/access.log
ln -sf /dev/stderr /var/log/nginx/error.log
```

Docker ловит stdout/stderr от PID 1. Симлинки позволяют nginx-логам
попадать в `docker logs` без sidecar'а или volume'а.

#### Entrypoint с `/docker-entrypoint.d/`

Нумерованные скрипты выполняются по порядку перед `exec "$@"`:

| Скрипт | Назначение |
|--------|-----------|
| `10-listen-on-ipv6-by-default.sh` | Включает IPv6 listen если доступен |
| `15-local-resolvers.envsh` | Экспортирует DNS-резолверы контейнера |
| `20-envsubst-on-templates.sh` | `envsubst` на шаблонах из `/etc/nginx/templates/` |
| `30-tune-worker-processes.sh` | Подстраивает workers под CPU-лимиты cgroup |

**Важный паттерн**: `.envsh` файлы **sourced** (`. "$f"`), а не executed —
они могут экспортировать переменные для следующих скриптов.

#### Worker Process Tuning (cgroup-aware)

Скрипт `30-tune-worker-processes.sh` проверяет **5 источников CPU-лимитов**:
1. Количество online CPU (`getconf _NPROCESSORS_ONLN`)
2. cgroup v1 cpuset
3. cgroup v1 CPU quota
4. cgroup v2 cpuset
5. cgroup v2 CPU quota

Берёт **минимум**. Без этого `worker_processes auto` на 64-ядерном хосте
создаст 64 воркера в контейнере с `--cpus=2`.

#### Temp-директории

Официальный образ: `/var/cache/nginx/` (дефолт)
LinuxServer/trafex: перемещают в `/tmp/`:

```nginx
client_body_temp_path /tmp/client_temp;
proxy_temp_path /tmp/proxy_temp_path;
fastcgi_temp_path /tmp/fastcgi_temp;
```

**Причина**: при запуске от non-root пользователя дефолтные пути не writable.

---

### 2.2 linuxserver/docker-baseimage-alpine-nginx

#### s6-overlay мультипроцесс

```
s6-rc.d/
  init-nginx         (oneshot) → копирует дефолты, настраивает resolver, workers
  init-php           (oneshot) → создаёт php-local.ini, www2.conf
  init-permissions   (oneshot) → фиксит ownership
  svc-nginx          (longrun) → nginx-процесс
  svc-php-fpm        (longrun) → php-fpm процесс
```

#### Nginx run-скрипт с zombie cleanup

```bash
#!/usr/bin/with-contenv bash
# Убивает зомби nginx-процессы перед стартом
if pgrep -f "[n]ginx:" >/dev/null; then
    pkill -ef [n]ginx:
    sleep 1
fi
# Если всё ещё живы — SIGKILL
if pgrep -f "[n]ginx:" >/dev/null; then
    pkill -9 -ef [n]ginx:
    sleep 1
fi
exec /usr/sbin/nginx -e stderr
```

#### PHP-FPM: TCP вместо Unix socket

```nginx
fastcgi_pass 127.0.0.1:9000;
```

**Почему TCP**: проще — нет проблем с permissions на socket-файл,
нет stale socket после crash'а. Overhead TCP на localhost минимален.

#### Логирование: реальные файлы + logrotate

В отличие от официального образа, linuxserver пишет в реальные файлы
в `/config/log/nginx/` и использует logrotate.
**Причина**: persistent volume `/config` позволяет смотреть логи после пересоздания контейнера.

#### Конфиги из persistent volume

```
/config/nginx/nginx.conf
/config/nginx/site-confs/*.conf
/config/php/php-local.ini
/config/php/www2.conf
```

Дефолты копируются из `/defaults/` **только при первом запуске**.
Пользователь может кастомизировать всё, и изменения переживут рестарт.

---

### 2.3 Официальный php-fpm (docker-library/php)

#### Socket vs TCP

Дефолт: **TCP 0.0.0.0:9000** (для multi-container архитектуры).

**Когда Unix socket лучше**:
- Nginx и PHP-FPM в одном контейнере → socket на ~5-10% быстрее (нет TCP stack)
- Безопаснее — socket доступен только локально

#### Process Management

| pm режим | Поведение | Когда использовать |
|---------|----------|-------------------|
| `static` | Фиксированное число воркеров | Выделенные high-traffic серверы |
| `dynamic` | Поддерживает spare workers (дефолт) | Общее назначение |
| `ondemand` | Воркеры только при запросе | Ограниченная RAM, бурстовая нагрузка |

#### Логирование

```ini
error_log = /proc/self/fd/2          # stderr
access.log = /proc/self/fd/2         # тоже stderr (не stdout!)
```

**Почему access.log в stderr, а не stdout**: PHP-FPM закрывает stdout при старте
(PHP bug #73886). Запись в `/proc/self/fd/1` не работает.

#### STOPSIGNAL SIGQUIT

Как и nginx — graceful shutdown. PHP-FPM дожидается завершения текущих запросов.

#### Конфиг с приоритетом через zz- префикс

```ini
# zz-docker.conf — загружается ПОСЛЕДНИМ, перезаписывает всё
daemonize = no
```

---

### 2.4 Сравнение init-систем для multi-process контейнеров

| Подход | PID 1 | Zombie reaping | Зависимости | Вес | Пример |
|--------|-------|---------------|-------------|-----|--------|
| **s6-overlay** | s6-svscan | Да | DAG (s6-rc) | ~5MB | linuxserver |
| **supervisord** | supervisord | Нет | Нет | ~50MB (Python) | trafex |
| **tini + bash** | tini | Да | Нет | ~30KB | наш проект |
| **Два контейнера** | каждый свой | Зависит | Docker Compose | 0 | Docker best practice |

#### Почему tini, а не s6-overlay

1. У нас **3 сервиса** (WG oneshot + nginx + php-fpm) — s6 overkill
2. Tini корректно reap'ит зомби и пробрасывает сигналы
3. На 30KB вместо 5MB — меньше attack surface
4. Проще для пользователей Pelican — понятный bash-скрипт vs s6 DSL
5. Pelican сам отправляет SIGTERM при остановке — tini пробросит его всем дочерним

**Компромисс**: если один сервис упадёт (php-fpm), другой (nginx) продолжит работать,
но будет отдавать 502. Мы решаем это через `wait -n` — если любой процесс завершится,
entrypoint выходит и Pelican может перезапустить контейнер.

---

### 2.5 Health Check паттерны

#### Лучший подход: fpm-ping (проверяет всю цепочку)

```ini
# php-fpm.conf
ping.path = /fpm-ping
```

```nginx
location ~ ^/(fpm-status|fpm-ping)$ {
    access_log off;
    allow 127.0.0.1;
    deny all;
    fastcgi_pass unix:/run/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

```dockerfile
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping || exit 1
```

**Почему лучший**: проверяет ВСЮ цепочку — nginx слушает, php-fpm отвечает, socket работает.

---

## 3. Pelican/Pterodactyl Eggs — Паттерны и конвенции

### 3.1 Каталог eggs

**Сайт**: [pelican-eggs.github.io/pelican](https://pelican-eggs.github.io/pelican/)
**Репозиторий**: [github.com/pelican-eggs/eggs](https://github.com/pelican-eggs/eggs)

Каталог содержит **200+ egg'ов** в категориях:
- Game servers (Minecraft, Steam games, standalone)
- Chatbots (Discord, Twitch, TeamSpeak)
- Databases (MongoDB, Redis, MariaDB, PostgreSQL)
- Software (Code-Server, Gitea, Grafana, Uptime Kuma)
- Monitoring (Prometheus, Loki)
- Generic runtimes (Node.js, Python, Java, Go, Rust, etc.)
- Voice servers (Mumble, TeamSpeak)

### 3.2 Формат egg JSON (PTDL_v2)

```jsonc
{
    "_comment": "...",
    "meta": {
        "version": "PTDL_v2",       // единственная версия формата
        "update_url": null            // URL для автообновления egg
    },
    "exported_at": "ISO-8601",
    "name": "Имя egg",
    "author": "email@example.com",
    "description": "Описание",
    "features": null,                 // зарезервировано
    "docker_images": {
        "Display Name": "ghcr.io/org/image:tag"
    },
    "file_denylist": [],              // файлы, скрытые от File Manager
    "startup": "команда запуска с {{ПЕРЕМЕННЫМИ}}",
    "config": {
        "files": "{}",               // парсинг конфигов (json/yaml/ini/xml/file)
        "startup": "{\"done\": \"строка в логе означающая готовность\"}",
        "logs": "{}",
        "stop": "^^C"                // команда остановки (^^C = SIGINT)
    },
    "scripts": {
        "installation": {
            "script": "bash-скрипт установки",
            "container": "образ для установки",
            "entrypoint": "bash"
        }
    },
    "variables": [
        {
            "name": "Отображаемое имя",
            "description": "Описание для пользователя",
            "env_variable": "UPPER_SNAKE_CASE",
            "default_value": "значение",
            "user_viewable": true,    // видна ли пользователю
            "user_editable": true,    // может ли менять
            "rules": "required|string|max:64",  // Laravel validation
            "field_type": "text"      // тип поля в UI
        }
    ]
}
```

### 3.3 Двухфазный процесс: Installation vs Runtime

| Фаза | Контейнер | Пользователь | Директория | Назначение |
|------|-----------|-------------|-----------|-----------|
| **Installation** | Указан в `scripts.installation.container` | root | `/mnt/server/` | Скачивание, сборка, настройка |
| **Runtime** | Указан в `docker_images` | container (UID 1000) | `/home/container/` | Запуск сервера |

**Ключевой момент**: Installation-скрипт пишет в `/mnt/server/`,
который при runtime маунтится как `/home/container/`.
Это разные пути, но один и тот же volume.

### 3.4 Стандартный entrypoint Pelican

```bash
#!/bin/bash
cd /home/container

# Подстановка {{ПЕРЕМЕННЫХ}} в STARTUP
MODIFIED_STARTUP=$(echo -e ${STARTUP} | sed -e 's/{{/${/g' -e 's/}}/}/g')
echo ":/home/container$ ${MODIFIED_STARTUP}"

# Запуск
eval ${MODIFIED_STARTUP}
```

**Паттерн**: egg задаёт `startup` в JSON, Pelican передаёт его в env `STARTUP`,
entrypoint подставляет переменные и выполняет. Это стандартный подход для простых egg'ов.

**Наш случай**: мы НЕ используем этот паттерн, потому что нам нужно:
1. Запустить WG от root перед основными сервисами
2. Запустить два процесса (nginx + php-fpm) параллельно
3. Обработать graceful shutdown
Поэтому наш entrypoint — кастомный скрипт.

### 3.5 Startup detection (done pattern)

```json
"startup": "{\"done\": \"Listening on \"}"
```

Pelican мониторит stdout контейнера и считает сервер запущенным,
когда видит строку из `done`. Примеры из реальных egg'ов:

| Egg | Done pattern |
|-----|-------------|
| Uptime Kuma | `[SERVER] INFO: Listening on ` |
| Gitea | `Listen: ` |
| Minecraft | `Done (` |

**Наш подход**: `"done": "All services started successfully!"` —
эту строку выводит наш entrypoint после запуска всех сервисов.

### 3.6 Stop signals

- `^^C` → отправляет SIGINT (Ctrl+C) — **самый частый паттерн**
- `stop` → отправляет команду "stop" в stdin (для Minecraft)
- `^C` → один SIGINT

### 3.7 Config file parsing

```json
"files": "{\"custom/app.ini\": {\"parser\": \"file\", \"find\": {\"SSH_PORT\": \"SSH_PORT: {{server.build.env.SSH_PORT}}\"}}}"
```

Поддерживаемые парсеры: `file`, `yaml`, `json`, `xml`, `ini`, `properties`.
Позволяет Pelican автоматически подставлять переменные в конфиги перед стартом.

**Наш подход**: мы не используем config parsing — подстановка через `sed`
в entrypoint проще и предсказуемее для нашего случая.

### 3.8 Docker Images (Yolks)

**Репозиторий**: [github.com/pelican-eggs/yolks](https://github.com/pelican-eggs/yolks)

Yolks — коллекция базовых образов для egg'ов:

| Категория | Примеры | Хостинг |
|-----------|---------|---------|
| OS | Alpine, Debian, Ubuntu | `ghcr.io/pelican-eggs/yolks:debian` |
| Runtime | Java 8-25, Node.js 20-24, Python 3.7-3.14 | `ghcr.io/pelican-eggs/yolks:java_21` |
| Games | Source, Rust, Arma3, Valheim | `ghcr.io/pelican-eggs/games:source` |
| Installers | Debian, SteamCMD | `ghcr.io/pelican-eggs/installers:debian` |
| DB | PostgreSQL, MongoDB, MariaDB, Redis | `ghcr.io/pelican-eggs/yolks:postgres_16` |

**Наш подход**: мы используем кастомный образ, потому что ни один yolk
не содержит WireGuard + Nginx + PHP-FPM.

### 3.9 Переменные — паттерны

| Паттерн | Пример env | Пример rules |
|---------|-----------|-------------|
| Порт | `SERVER_PORT` | `required\|integer\|between:1024,65535` |
| Пароль | `RCON_PASSWORD` | `required\|string\|max:64` |
| Версия | `VERSION` | `required\|string\|max:20` |
| Boolean | `AUTO_UPDATE` | `required\|boolean` |
| Enum | `DISABLE_SSH` | `required\|string\|in:true,false` |
| Nullable | `WG_PRIVATE_KEY` | `nullable\|string` |

### 3.10 Installation scripts — паттерны

Общие паттерны из реальных egg'ов:

```bash
#!/bin/ash
# 1. Установка зависимостей
apk add --no-cache git curl jq

# 2. Создание директорий
mkdir -p /mnt/server

# 3. Скачивание/клонирование
git clone --single-branch --branch ${GIT_BRANCH} ${GIT_URL} /mnt/server
# или
curl -sSL "https://github.com/org/repo/releases/download/v${VERSION}/binary" -o /mnt/server/binary

# 4. Установка зависимостей приложения
cd /mnt/server && npm install

# 5. Создание дефолтных конфигов
if [ ! -f /mnt/server/config.json ]; then
    cp /mnt/server/config.example.json /mnt/server/config.json
fi
```

### 3.11 Существующие VPN-egg'и

| Проект | Подход | Capabilities |
|--------|--------|-------------|
| [Pterodactyl-VPS-Egg](https://github.com/ysdragon/Pterodactyl-VPS-Egg) | Полноценный VPS в контейнере | NET_ADMIN, SYS_MODULE и др. |
| [Tailscale egg](https://builtbybit.com/resources/pterodactyl-tailscale-vpn-connection-egg.54337/) | Tailscale mesh VPN | NET_ADMIN, NET_RAW |

**Вывод**: прецеденты использования NET_ADMIN в Pelican/Pterodactyl существуют.
Это не стандартный сценарий, но документированный и рабочий.

---

## 4. Сводная таблица решений

| Аспект | linuxserver/wg | official nginx | linuxserver/nginx | php-fpm official | **Наш проект** | Обоснование |
|--------|---------------|---------------|-------------------|-----------------|---------------|------------|
| Init | s6-overlay | nginx PID 1 | s6-overlay | php-fpm PID 1 | **tini** | Лёгкий, достаточный для 3 сервисов |
| Signal | s6 управляет | SIGQUIT | s6 управляет | SIGQUIT | **SIGTERM→tini** | Pelican шлёт SIGTERM, tini пробрасывает |
| Логи | файлы + logrotate | symlink→stdout | файлы + logrotate | stderr | **файлы в /home/container/logs/** | Видны в Pelican File Manager |
| PHP socket | — | — | TCP 127.0.0.1:9000 | TCP 0.0.0.0:9000 | **Unix socket** | Один контейнер, socket быстрее |
| Temp dirs | — | /var/cache | /tmp/ | — | **/tmp/** | non-root Nginx |
| User | root + PUID | nginx (101) | root + abc (PUID) | www-data | **root → container (1000)** | Pelican стандарт |
| Config | persistent /config | envsubst templates | persistent /config | layered .conf | **persistent /home/container/** | Pelican File Manager |
| Workers | — | cgroup-aware auto | nproc-based | dynamic pm | **auto + ondemand pm** | Простота, низкий трафик |
| Health check | Нет | Нет | Нет | Нет | **fpm-ping (TODO)** | Best practice из trafex |
| WG config | templates + генерация | — | — | — | **генерация из env** | Аналогично linuxserver |

---

## 5. TODO — что стоит добавить на основе ресёрча

На основании исследования, следующие улучшения стоит внести в проект:

### Высокий приоритет
- [ ] **STOPSIGNAL SIGQUIT** в Dockerfile для nginx и php-fpm graceful shutdown
- [ ] **Health check** через fpm-ping (проверяет всю цепочку nginx→php-fpm)
- [ ] **sysctl `net.ipv4.conf.all.src_valid_mark=1`** — нужен для WG client mode
- [ ] **Temp directories** в `/tmp/` для non-root nginx

### Средний приоритет
- [ ] **nginx -s reload** — документировать как перезагрузить конфиг без рестарта
- [ ] **Zombie cleanup** перед стартом nginx (паттерн linuxserver)
- [ ] **`zz-docker.conf`** для PHP-FPM чтобы гарантировать `daemonize = no`

### Низкий приоритет
- [ ] **envsubst** для nginx templates (вместо sed)
- [ ] **Worker process tuning** с учётом cgroup лимитов
- [ ] **QR-code** генерация для WG-конфигов

---

## Источники

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
