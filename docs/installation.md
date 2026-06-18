# Installation

## Prerequisites

Add the [symfinity/recipes](https://github.com/symfinity/recipes) Flex endpoint to your project's `composer.json` (see [recipes README](https://github.com/symfinity/recipes/blob/main/README.md)).

Poker Planner needs:

| Piece | Role |
|-------|------|
| **Redis** (or Predis) | Ephemeral room state — participants, votes, story queue |
| **Mercure** | Push HTML Turbo streams to every browser in a room |
| **symfinity/ui-kernel** + **ux-blocks** | Theme tokens and entry-page components (Composer deps) |

The Flex recipe copies bundle config, Mercure hub YAML, routes, Stimulus/Turbo assets, and (when you accept Docker config) a **Redis** service for [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker).

**Full recipe reference:** [Flex recipe](flex-recipe.md) — symfony/recipes endpoint, manifest steps, Compose fragments, `controllers.json` merge guide.

---

## Symfony Docker {#symfony-docker}

Turnkey path for a **Symfony 7.4** webapp on [symfony-docker](https://github.com/dunglas/symfony-docker). Mercure is **already built into Caddy** — the recipe does **not** add a second Mercure container.

**Canonical walkthrough:** [Flex recipe](flex-recipe.md) (symfony/recipes endpoint, full manifest table, asset merge, troubleshooting).

### Quick steps

```bash
git clone https://github.com/dunglas/symfony-docker my-poker-app && cd my-poker-app
# Symfony 7.4 webapp per symfony-docker README, then add symfinity/recipes endpoint — see flex-recipe.md
docker compose run --rm php composer require symfinity/poker-planner
# Accept Flex Docker configuration when prompted
docker compose up -d --wait
# Open https://localhost/
```

### Env reference (symfony-docker)

| Variable | Recipe default | Notes |
|----------|----------------|-------|
| `REDIS_URL` | `redis://redis:6379` | Matches Compose service name `redis` |
| `MERCURE_URL` | `http://php/.well-known/mercure` | Internal publish URL (Caddy in `php` service) |
| `MERCURE_PUBLIC_URL` | `https://localhost/.well-known/mercure` | Browser SSE URL — match your `SERVER_NAME` |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | Must match `CADDY_MERCURE_JWT_SECRET` in symfony-docker |

symfony-docker already injects Mercure env on the `php` service; `.env` values keep CLI and documentation in sync.

---

## Generic Symfony install

Without symfony-docker:

```bash
composer require symfinity/poker-planner
```

Ensure **Redis** and a **Mercure hub** are reachable. If you do not use symfony-docker, install [symfony/mercure-bundle](https://github.com/symfony/mercure-bundle) and either run the official Mercure recipe (standalone `dunglas/mercure` container) or your own hub.

Example `.env` (non-Docker local):

```dotenv
REDIS_URL=redis://127.0.0.1:6379
MERCURE_URL=http://127.0.0.1/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeMe!
```

Copy asset wiring from `vendor/symfinity/poker-planner/install/assets/` if Flex did not run.

---

## What the Flex recipe applies

See [Flex recipe](flex-recipe.md) for the full manifest reference, Compose YAML fragments, and asset merge guide.

| Artifact | Source |
|----------|--------|
| `config/packages/poker_planner.yaml` | Package default |
| `config/packages/mercure.yaml` | Package default |
| `config/routes/poker_planner.yaml` | Host + bundle routes |
| `assets/app.poker_planner.js` | Package `install/assets/` |
| `assets/controllers.json` | Package `install/assets/` |
| Redis service | Recipe `docker-compose` → `compose.yaml` |
| `predis/predis`, `symfony/ux-icons` | Recipe `require` |

Constraint: `symfinity/recipes` folder **`0.1`** for `^0.1`.

---

## Manual installation

When Flex is unavailable:

1. `composer require symfinity/poker-planner predis/predis symfony/ux-icons`
2. Register `Symfinity\Bundle\PokerPlanner\PokerPlannerBundle` in `config/bundles.php`
3. Copy `config/packages/poker_planner.yaml` and `config/packages/mercure.yaml` from the package
4. Copy `config/routes/poker_planner.yaml` and `config/routes/poker_planner_host.yaml`
5. Copy `install/assets/*` into `assets/` and import `app.poker_planner.js` from `assets/app.js`
6. Run `php bin/console importmap:install` and `php bin/console ux:icons:import hugeicons:joker`

---

## Next steps

- [Flex recipe](flex-recipe.md) — symfony-docker turnkey path and manifest reference
- [Quick start](quickstart.md) — first session walkthrough
- [Configuration](configuration.md) — `poker_planner.yaml` reference
- [Realtime sync](realtime.md) — Mercure topics and Turbo streams
