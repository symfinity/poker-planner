# symfinity/poker-planner

Realtime planning-poker rooms for Symfony: hidden votes, Mercure + Turbo Streams sync, guest names (v0).

## Features (v0.1)

- UUID room links (`/r/{uuid}`)
- Moderator: story title, reveal, restart (optional vote)
- Estimators: Fibonacci-style deck (`½`, `1`–`21`, `?`, coffee)
- Ephemeral Redis rooms (presence grace + 4h TTL)
- u.make.me.happy palette (see scratch design)

## Requirements

- PHP 8.2+
- `ext-redis` (or dogfood with Redis service)
- `symfony/mercure-bundle`, `symfony/ux-turbo`

## Dogfood

From org checkout root:

```bash
docker compose --env-file .env.docker --profile symfony-ref --profile redis up -d redis
make dogfood-new PACKAGE=symfinity/poker-planner SLUG=poker-planner-lab VERSION='7.4.*'
make dogfood-serve SLUG=poker-planner-lab
```

Start a Mercure hub (see overlay `post_create` hints). Open two browsers on the same room URL.

## License

MIT
