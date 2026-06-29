# Upgrade

## 0.1.2

Patch release — public roadmap, sponsorship metadata, Flex handbook clarification, and split-mirror CI hygiene.

```bash
composer update symfinity/poker-planner
```

### Highlights

- **ROADMAP.md** — public 0.1.x–1.0.x milestone table on the split mirror
- **Flex recipe docs** — theme `scheme-switch` is optional; no hard `ux-blocks-interactive` requirement in the handbook example
- **CI** — Composer cache and GitHub token auth for reliable matrix installs

No config, recipe, or Redis room document changes. Upgrading from 0.1.1 is documentation and metadata only.

## 0.1.1

Patch release — reveal quorum, moderator button states, session recap tab, and story-queue finish behaviour.

```bash
composer update symfinity/poker-planner
```

### Highlights

- **Reveal** requires at least half the table to vote (UI + server guard)
- Moderator footer buttons disable when the action is not allowed
- **Finish queue** archives estimates and resets the queue for new stories
- Session recap lives under Settings → **Session**

No config or Flex recipe changes. Redis room documents from 0.1.0 remain compatible.

## 0.1.0

First public release. Install with:

```bash
composer require symfinity/poker-planner:^0.1
```

### Requirements

- PHP 8.2+
- Symfony 7.4+ or 8.x
- Redis (ext-redis or predis/predis)
- Mercure hub via symfony/mercure-bundle
- symfinity/ui-kernel, symfinity/ux-blocks, symfinity/ux-blocks-core `^0.1`

### Fresh install checklist

1. Add symfinity/recipes Flex endpoint — [Flex recipe](flex-recipe.md) or [Installation](installation.md).
2. Configure `REDIS_URL` and Mercure env vars; start both services.
3. Run `composer require symfinity/poker-planner`.
4. Open `/`, start a session, verify realtime updates with two browsers — [Realtime sync](realtime.md).

### Scope notes

- Guest sessions only (display name in PHP session cookie) — no OAuth in v0.1
- Room state is ephemeral in Redis — no Doctrine persistence
- External ticket links and long-term recap storage are out of scope

## See also

- [Flex recipe](flex-recipe.md) — symfony-docker turnkey install
- [CHANGELOG](../CHANGELOG.md)
- [Configuration](configuration.md)
