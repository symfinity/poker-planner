# Configuration

All options live under `config/packages/symfinity_poker_planner.yaml`:

```yaml
symfinity_poker_planner:
  path_prefix: ''
  mercure_topic_prefix: ''
  storage:
    redis_url: '%env(REDIS_URL)%'
    prefix: poker_planner
  room:
    max_ttl_seconds: 14400
    saved_ttl_seconds: 31536000
    grace_seconds: 600
    heartbeat_seconds: 30
```

## `path_prefix`

Prepended to every bundle route. Empty string keeps the default layout:

- `/` — entry (start / join)
- `/r/{uuid}` — room

Example for `/tools/poker`:

```yaml
symfinity_poker_planner:
  path_prefix: '/tools/poker'
```

Routes become `/tools/poker/` and `/tools/poker/r/{uuid}`.

## `mercure_topic_prefix`

Prepended to Mercure topics for room broadcasts. Default is empty; topics look like `/rooms/{uuid}`.

Set a prefix when multiple apps share one Mercure hub:

```yaml
symfinity_poker_planner:
  mercure_topic_prefix: '/my-app'
```

Topic becomes `/my-app/rooms/{uuid}`. Must match what `turbo_stream_from()` subscribes to on the room page.

## `storage`

| Key | Default | Meaning |
|-----|---------|---------|
| `redis_url` | `%env(REDIS_URL)%` | DSN for Redis or Predis |
| `prefix` | `poker_planner` | Key namespace inside Redis |

Rooms are JSON documents keyed by UUID. No Doctrine entities or migrations.

## `room`

| Key | Default | Meaning |
|-----|---------|---------|
| `max_ttl_seconds` | `14400` (4 h) | TTL for unsaved ephemeral rooms |
| `saved_ttl_seconds` | `31536000` (1 y) | TTL after moderator saves team name |
| `grace_seconds` | `600` (10 min) | How long a participant stays after last heartbeat |
| `heartbeat_seconds` | `30` | Client heartbeat interval (also passed to the room template) |

## Mercure (application config)

Poker Planner publishes **public** updates on the room topic. Configure `symfony/mercure-bundle` so PHP can publish and browsers can subscribe:

```yaml
# config/packages/mercure.yaml
mercure:
  hubs:
    default:
      url: '%env(MERCURE_URL)%'
      public_url: '%env(MERCURE_PUBLIC_URL)%'
      jwt:
        secret: '%env(MERCURE_JWT_SECRET)%'
        publish: ['*']
```

Restrict `publish` in production to the topic prefix you use.

## See also

- [Realtime sync](realtime.md) — topic naming and stream payloads
- [Installation](installation.md)
