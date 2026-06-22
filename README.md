<div align="center">

# Poker Planner

### Self-hosted planning poker for Symfony - hidden votes, Mercure realtime, story queue

[![PHP Version](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)](composer.json)
[![Symfony](https://img.shields.io/badge/Symfony-7.4+-343434?style=flat&logo=symfony&logoColor=white)](composer.json)
<br/>
[![CI](https://github.com/symfinity/poker-planner/actions/workflows/ci.yml/badge.svg)](https://github.com/symfinity/poker-planner/actions/workflows/ci.yml)
<br/>
[![Release](https://img.shields.io/packagist/v/symfinity/poker-planner.svg?style=flat&logo=packagist&logoColor=white)](https://packagist.org/packages/symfinity/poker-planner)
[![Downloads](https://img.shields.io/packagist/dt/symfinity/poker-planner.svg?style=flat&logo=packagist&logoColor=white)](https://packagist.org/packages/symfinity/poker-planner)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat)](LICENSE)

</div>

> [!NOTE]
> **Read-only mirror.**
> See [CONTRIBUTING.md](CONTRIBUTING.md) for how to propose changes.

## Features

- **Frictionless sessions** - share `/r/{uuid}` links; guests join with a display name only
- **Hidden votes** - no vote values leak before moderator reveal (Mercure + Turbo streams)
- **Fibonacci deck** - `½`, `1`–`21`, `?`, and coffee with simultaneous card flip
- **Story queue** - multi-story refinement with recorded estimates per round
- **Consensus feedback** - median, spread, and outlier hints after reveal
- **Session recap** - copy markdown summary to your tracker or notes
- **ui-kernel theming** - scheme switcher and token-driven room chrome
- **Ephemeral Redis rooms** - no database migrations in v0.1

## Prerequisites

Add the [symfinity/recipes](https://github.com/symfinity/recipes) Flex endpoint to your project's `composer.json` (see [recipes README](https://github.com/symfinity/recipes/blob/main/README.md)).

**Recommended:** [symfony-docker](https://github.com/dunglas/symfony-docker) (Symfony 7.4) - the Flex recipe adds Redis, Mercure env, routes, and Stimulus/Turbo assets. See [Flex recipe](docs/flex-recipe.md) (turnkey walkthrough) or [Installation](docs/installation.md#symfony-docker).

Otherwise you need **Redis** (or Predis) and a **Mercure** hub - see [Installation](docs/installation.md).

## Installation

```bash
composer require symfinity/poker-planner
```

On symfony-docker: accept Flex **Docker configuration**, then `docker compose up -d --wait`.

## Quick Start

1. Open `/` (symfony-docker: `https://localhost/`), enter your name, and **Start session**.
2. Share the room URL; estimators vote; moderator **Reveal** then **Next story**.

See [Quick start](docs/quickstart.md) for the full walkthrough.

## Documentation

- **[Flex recipe](docs/flex-recipe.md)** - symfony-docker turnkey, manifest reference, asset merge
- **[Installation](docs/installation.md)** - Composer, Flex recipe overview, infrastructure
- **[Quick start](docs/quickstart.md)** - first session in minutes
- **[Configuration](docs/configuration.md)** - `symfinity_poker_planner.yaml` reference
- **[Realtime sync](docs/realtime.md)** - Mercure topics, Turbo streams, vote privacy (schematic diagrams)
- **[Upgrade](docs/upgrade.md)** - release notes (`0.1.1`, `0.1.0`)

## Requirements

- PHP 8.2+
- Symfony 7.4+ or 8.x
- Redis or Predis
- Mercure hub (symfony/mercure-bundle)
- symfinity/ui-kernel, symfinity/ux-blocks, symfinity/ux-blocks-core

## Support

- [GitHub Issues](https://github.com/symfinity/poker-planner/issues)
- [Security](.github/SECURITY.md)
- [Contributing](CONTRIBUTING.md)

## License

[MIT](LICENSE)
