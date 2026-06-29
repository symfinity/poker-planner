# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.2] - 2026-06-29

### Added

- Public `ROADMAP.md` with 0.1.x–1.0.x milestone table
- `SUPPORTERS.md` and `.github/FUNDING.yml` for GitHub Sponsors visibility
- `composer.json` `funding` metadata pointing to [GitHub Sponsors](https://github.com/sponsors/serotoninja)

### Changed

- **Flex recipe handbook** — `scheme-switch` documented as optional when present in your asset map; manifest example no longer requires `symfinity/ux-blocks-interactive`
- **Split mirror CI** — Composer package cache and `COMPOSER_AUTH` for reliable dependency installs across the PHP × Symfony matrix

### Notes

- No functional or API changes — documentation, sponsorship metadata, and CI hygiene

## [0.1.1] - 2026-06-22

### Added

- **Reveal quorum** — moderator **Reveal** stays disabled until at least half the table has voted; server rejects early reveal with a clear error
- **Moderator action states** — **Reveal**, **Restart**, and **Next story** / **Finish queue** buttons reflect live eligibility (`disabled` + `aria-disabled`) and refresh over Mercure during voting
- **Settings Session tab** — dedicated panel for session recap (moved out of the Room tab)

### Changed

- **Vote Turbo stream** — re-voting skips the slot-grid flip animation; consensus strip and moderator actions update when votes change after reveal (`allowChangeAfterReveal`)
- **Finish queue** — archives recorded estimates into the session recap and clears the queue for a fresh story list instead of leaving a stuck complete state
- **Story queue drawer** — scrollable story list inside the panel; header and moderator chrome stay pinned on small screens
- **Slot grid after reveal** — participant names remain visible on revealed cards; vote badges are hidden once values are shown

### Fixed

- Story queue persistence no longer restores `complete: true` when the item list is empty (stale Redis documents)

## [0.1.0] - 2026-06-18

### Added

- Initial release of **Poker Planner** bundle for Symfony — self-hosted planning poker with hidden votes and simultaneous reveal
- **Entry flow** — landing page at `/`, start session or join via `/r/{uuid}` link; display name only (no accounts in v0.1)
- **Room URLs** — UUID paths (`/r/{uuid}`) with share link, invite dialog, and optional QR panel for moderators
- **Fibonacci deck** — `½`, `1`–`21`, `?`, and coffee; deck presets and optional zero / pass / break toggles in game settings
- **Vote privacy** — `PublicParticipantView` strips card values from Mercure payloads until moderator reveal; voters see only their own selection on POST
- **Realtime sync** — Symfony Mercure hub publishes HTML Turbo streams; UX Turbo `turbo_stream_from()` subscribes per room topic (`/rooms/{uuid}` by default)
- **Stream targets** — session sync updates `#slot-grid`, `#vote-deck`, `#consensus-strip`, `#recap-panel`, story queue chrome, and moderator actions without full page reload
- **Ephemeral Redis storage** — JSON room documents with configurable TTL, saved-room extension, presence grace, and client heartbeat
- **Story queue** — append stories, edit current title, remove queued items, advance with recorded estimate, queue-complete state
- **Session recap** — markdown export (copy to clipboard), **Add to recap** to archive finished queue and start a new session in the same room
- **Consensus strip** — median, spread, outlier hints, and optional confetti on unanimous numeric agreement (`?` and coffee excluded from math)
- **Settings dialog** — tabs for Me (display name), Game (deck / rounding / confetti), Room (team name, save, delete), and Players (roster)
- **UI integration** — symfinity/ui-kernel tokens, symfinity/ux-blocks-core atoms on entry (`PageHeading`, `Input`, `Button`, `Flash`), AssetMapper Stimulus controller for heartbeat and share feedback
- **Flex recipe** `symfinity/poker-planner` `0.1` — bundle, Mercure/Redis env, routes, Stimulus/Turbo assets, Redis `compose.yaml` service (symfony-docker)
- **Consumer handbook** — `docs/flex-recipe.md` (symfony-docker turnkey + manifest reference), `installation.md`, `quickstart.md`, `configuration.md`, `realtime.md` (Mercure + Turbo schematics), `upgrade.md`
- **Split mirror CI** — PHP 8.2–8.5 × Symfony 7.4, 8.0, 8.1 (PHPUnit + PHPStan on every matrix cell)

### Notes

- Requires a running **Mercure** hub and **Redis** (or Predis) reachable from PHP
- Depends on `symfinity/ui-kernel`, `symfinity/ux-blocks`, and `symfinity/ux-blocks-core` for theming and entry-page components
- Symfony **7.4+** or **8.x** per `composer.json` constraints
- OAuth, persistent recap history, and external ticket integration are out of scope for v0.1
