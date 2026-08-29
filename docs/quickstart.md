# Quick start

Run your first planning poker session in a few minutes.

## 1. Install and configure

**symfony-docker (Symfony 7.4):** see [Flex recipe](flex-recipe.md) — symfony/recipes endpoint, `composer require`, accept Docker config, `docker compose up -d --wait`.

**Other Symfony apps:**

```bash
composer require symfinity/poker-planner
```

Set `REDIS_URL` and Mercure env vars; start Redis and your Mercure hub. See [Installation](installation.md).

## 2. Start a room

1. Open `/` in the browser.
2. Enter your display name.
3. Click **Start session**.

You become the **moderator** and land on `/r/{uuid}`.

## 3. Invite estimators

Copy the room URL from **Invite** (or share the `/r/{uuid}` link). Guests open the link, enter a name, and join — no accounts.

When someone joins, other browsers update via Mercure (see [Realtime sync](realtime.md)).

## 4. Estimate a story

1. Moderator sets the story title (header or story queue drawer).
2. Everyone picks a card from the Fibonacci deck (`½`, `1`–`21`, `?`, coffee).
3. Other participants see **card backs** and a “voted” state — never the value.
4. Moderator clicks **Reveal** when ready.

After reveal, cards flip simultaneously and the **consensus strip** shows median, spread, and outliers (numeric votes only).

## 5. Queue and recap

- **Add story** in the queue drawer to plan multiple items.
- **Next story** records the agreed estimate and advances the queue.
- **Add to recap** archives the finished queue and starts a fresh session in the same room.
- Open **Settings → Me** (or the recap panel) to copy a markdown summary.

## Settings overview

| Tab | Who | Purpose |
|-----|-----|---------|
| **Me** | Everyone | Display name |
| **Game** | Moderator | Deck preset, optional cards, rounding, confetti |
| **Room** | Moderator | Team name, save room, delete room |
| **Players** | Moderator | Roster and presence |

## Heartbeat

The room page posts a lightweight heartbeat every 30 seconds (configurable) so Redis can drop idle participants after the grace window.

## See also

- [Configuration](configuration.md) — TTL, topic prefix, Redis key prefix
- [Realtime sync](realtime.md) — Mercure topics, Turbo targets, vote privacy
- [CHANGELOG](../CHANGELOG.md) · [CONTRIBUTING](../CONTRIBUTING.md) · [GitHub Issues](https://github.com/symfinity/poker-planner/issues)
