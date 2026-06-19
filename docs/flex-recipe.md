# Flex recipe

Symfony Flex recipe **`symfinity/poker-planner`** version **`0.1`** (`^0.1`). Sources: [symfinity/recipes](https://github.com/symfinity/recipes) on branch `main`; Flex endpoint on `flex/main`.

Designed for a **Symfony 7.4** webapp on [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker) — Redis, Mercure (Caddy), routes, and frontend wiring in a few steps.

---

## 1. Enable symfinity/recipes

Add to your project's `composer.json` (see [symfinity/recipes README](https://github.com/symfinity/recipes/blob/main/README.md)):

```json
{
    "extra": {
        "symfony": {
            "allow-contrib": true,
            "endpoint": [
                "https://api.github.com/repos/symfinity/recipes/contents/index.json?ref=flex/main",
                "flex://defaults"
            ]
        }
    }
}
```

Without this endpoint, `composer require symfinity/poker-planner` installs the package but **does not** apply the recipe.

---

## 2. symfony-docker quick path

### Create the app

```bash
git clone https://github.com/dunglas/symfony-docker my-poker-app
cd my-poker-app
```

Install Symfony **7.4** per symfony-docker README — typically:

```bash
docker compose run --rm php composer create-project symfony/skeleton .
docker compose run --rm php composer require webapp
```

Add the **symfinity/recipes** endpoint (section 1) to `composer.json`, then:

```bash
docker compose run --rm php composer require symfinity/poker-planner
```

When Flex asks to apply **Docker configuration**, answer **yes**.

### Start and verify

```bash
docker compose up -d --wait
```

Open `https://localhost/` (or your `SERVER_NAME`). Start a session → share `/r/{uuid}` → vote → reveal in a second browser.

### Why no extra Mercure container?

symfony-docker ships Mercure as a **Caddy module** inside the `php` service. The recipe wires `MERCURE_*` env to match that setup — it does **not** add a standalone `dunglas/mercure` service (unlike the stock symfony/mercure-bundle recipe for plain Symfony apps).

---

## 3. What the recipe applies

| Configurator | What it does |
|--------------|--------------|
| **bundles** | Registers `PokerPlannerBundle` for `all` envs |
| **copy-from-package** | Copies config, routes, and asset stubs from the package (see table below) |
| **require** | Adds `predis/predis` and `symfony/ux-icons` |
| **env** | Sets `REDIS_URL`, `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET` in `.env` |
| **docker-compose** | Appends `redis` service + `php` → `redis` health `depends_on` to `compose.yaml`; Redis port on `compose.override.yaml` |
| **add-lines** | Appends `import './app.poker_planner.js';` to `assets/app.js` |
| **composer-scripts** | Runs `cache:clear`, `importmap:install`, `assets:install` |
| **composer-commands** | Runs `ux:icons:import hugeicons:joker` |

### Files copied from the package

| Package path | Project path |
|--------------|--------------|
| `config/packages/symfinity_poker_planner.yaml` | `config/packages/symfinity_poker_planner.yaml` |
| `config/packages/mercure.yaml` | `config/packages/mercure.yaml` |
| `config/routes/poker_planner.yaml` | `config/routes/poker_planner.yaml` |
| `install/assets/app.poker_planner.js` | `assets/app.poker_planner.js` |
| `install/assets/controllers.json` | `assets/controllers.json` |

`config/routes/poker_planner.yaml` imports:

- `poker_planner_host.yaml` — ui-kernel scheme switcher route (required by the room layout)
- `@PokerPlannerBundle/config/routes.yaml` — entry `/` and rooms `/r/{uuid}`

### Docker Compose (recipe fragment)

Appended to **`compose.yaml`**:

```yaml
services:
    redis:
        image: redis:7-alpine
        restart: unless-stopped
        healthcheck:
            test: ['CMD', 'redis-cli', 'ping']
            timeout: 3s
            retries: 5
            start_period: 5s
    php:
        depends_on:
            redis:
                condition: service_healthy
```

Appended to **`compose.override.yaml`** (dev):

```yaml
services:
    redis:
        ports:
            - '6379'
```

### Environment defaults

| Variable | Recipe value | symfony-docker notes |
|----------|--------------|----------------------|
| `REDIS_URL` | `redis://redis:6379` | Hostname = Compose service `redis` |
| `MERCURE_URL` | `http://php/.well-known/mercure` | Internal publish URL (Caddy in `php`) |
| `MERCURE_PUBLIC_URL` | `https://localhost/.well-known/mercure` | Browser SSE — match `SERVER_NAME` / `HTTPS_PORT` |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | Must match `CADDY_MERCURE_JWT_SECRET` |

symfony-docker also sets Mercure on the `php` service in `compose.yaml`; keep `.env` aligned for CLI and documentation.

---

## 4. Frontend wiring

### `assets/app.poker_planner.js`

Copied by the recipe. Contents:

```javascript
import '@hotwired/turbo';

if (typeof Turbo !== 'undefined') {
    Turbo.session.drive = false;
}
```

`Turbo.session.drive = false` is required — full-page Turbo drive breaks Mercure stream updates in the room.

The recipe appends to **`assets/app.js`**:

```javascript
import './app.poker_planner.js';
```

### `assets/controllers.json`

Copied by the recipe. Required Stimulus controllers:

```json
{
    "controllers": {
        "@symfony/ux-turbo": {
            "turbo-core": {
                "enabled": true,
                "fetch": "eager",
                "autoimport": {
                    "@symfony/ux-turbo/dist/mercure_stream_source_element.js": true
                }
            },
            "mercure-turbo-stream": {
                "enabled": false,
                "fetch": "eager"
            }
        },
        "@symfinity/ux-blocks-interactive": {
            "scheme-switch": {
                "enabled": true,
                "fetch": "eager"
            }
        },
        "@symfinity/poker-planner": {
            "poker-planner": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    },
    "entrypoints": []
}
```

| Controller | Why |
|------------|-----|
| `turbo-core` + Mercure autoimport | Subscribes to room topics via `turbo_stream_from()` |
| `scheme-switch` | Theme switcher in poker-planner layout |
| `poker-planner` | Heartbeat, share link, queue drawer, confetti |

---

## 5. Existing apps — merge, don't overwrite

On **greenfield** symfony-docker + webapp, the recipe can replace `assets/controllers.json` safely.

If you already customized **`assets/app.js`** or **`controllers.json`**, merge instead of letting Flex overwrite:

### `assets/app.js`

Add at the bottom (or after your Stimulus bootstrap import):

```javascript
import './app.poker_planner.js';
```

Reference file: `vendor/symfinity/poker-planner/install/assets/app.poker_planner.js`

### `assets/controllers.json`

Merge the three blocks from section 4 into your existing `controllers` object. Reference: `vendor/symfinity/poker-planner/install/assets/controllers.json`.

Then run:

```bash
docker compose exec php php bin/console importmap:install
docker compose exec php php bin/console ux:icons:import hugeicons:joker --no-interaction
```

---

## 6. Generic Symfony (no symfony-docker)

```bash
composer require symfinity/poker-planner
```

- Accept Docker config if you use Compose — only **Redis** is added; Mercure is your responsibility.
- Without symfony-docker: install [symfony/mercure-bundle](https://github.com/symfony/mercure-bundle) and use its recipe (standalone Mercure container) or your own hub.
- Copy anything missing from `vendor/symfinity/poker-planner/install/assets/` and config paths in section 3.

See [Installation](installation.md) for non-Docker `.env` examples.

---

## 7. Manual install (no Flex)

1. `composer require symfinity/poker-planner predis/predis symfony/ux-icons`
2. Register `Symfinity\Bundle\PokerPlanner\PokerPlannerBundle` in `config/bundles.php`
3. Copy package `config/packages/symfinity_poker_planner.yaml`, `config/packages/mercure.yaml`
4. Copy `config/routes/poker_planner.yaml` and `config/routes/poker_planner_host.yaml`
5. Copy `install/assets/*` → `assets/` and wire `app.poker_planner.js` in `app.js`
6. `php bin/console importmap:install` and `php bin/console ux:icons:import hugeicons:joker`

---

## 8. Troubleshooting

| Symptom | Check |
|---------|--------|
| Flex did nothing | symfinity/recipes endpoint in `composer.json`? |
| Redis connection refused | `docker compose ps redis` — recipe Docker config accepted? `REDIS_URL=redis://redis:6379`? |
| Room never updates live | `MERCURE_PUBLIC_URL` reachable in browser? JWT secret matches Caddy? Turbo Mercure autoimport in `controllers.json`? |
| Missing theme / scheme route | `config/routes/poker_planner.yaml` present? symfinity/ui-kernel + ux-blocks-core installed? |
| Joker / icons missing | `php bin/console ux:icons:import hugeicons:joker` |

---

## See also

- [Installation](installation.md) — overview and env reference
- [Quick start](quickstart.md) — first session
- [Realtime sync](realtime.md) — Mercure + Turbo architecture
- [Configuration](configuration.md) — `symfinity_poker_planner.yaml`
