# Upgrade

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
