# Verification

**Integration profile:** P1 — routes OOTB (Redis + Mercure env for realtime).

Checks after installing **symfinity/poker-planner** in a Symfony app with Redis and a Mercure hub configured.

## Local commands

```bash
composer test
composer phpstan
php bin/console debug:router | grep poker
```

## Clean-app smoke

On a fresh Symfony 7.4+ project with `REDIS_URL` and Mercure env vars set (see [Installation](installation.md)):

```bash
composer require symfinity/poker-planner
```

1. Open `GET /` — expect the planning poker entry UI (HTTP 200).
2. Start a session and open the share link `/r/{uuid}` in a second browser — expect realtime vote updates without full page reload.

## See also

- [Quick start](quickstart.md)
- [Realtime sync](realtime.md)
- [CHANGELOG](../CHANGELOG.md)
