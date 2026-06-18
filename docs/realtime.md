# Realtime sync (Mercure + Turbo)

Poker Planner keeps every browser in a room aligned without polling. **Symfony Mercure** delivers HTML fragments; **UX Turbo** applies them as DOM updates. Vote values never appear in Mercure payloads until the moderator reveals.

## System overview

```mermaid
flowchart TB
    subgraph browsers["Browsers in room /r/uuid"]
        B1[Estimator A]
        B2[Estimator B]
        BM[Moderator]
    end

    subgraph symfony["Symfony app"]
        RC[RoomController / EntryController]
        RS[RoomService]
        RTP[RoomTurboPublisher]
        TW[Twig stream templates]
    end

    subgraph infra["Infrastructure"]
        REDIS[(Redis room JSON)]
        HUB[Mercure hub]
    end

    B1 & B2 & BM -->|POST vote reveal join| RC
    RC --> RS
    RS -->|read write| REDIS
    RC --> RTP
    RTP --> TW
    RTP -->|Update HTML topics| HUB
    HUB -->|SSE EventSource| B1 & B2 & BM
    BM & B1 & B2 -.->|turbo_stream_from topic| HUB
```

| Layer | Responsibility |
|-------|----------------|
| **Redis** | Source of truth — `Room`, participants, votes, story queue |
| **PHP controllers** | Validate actions, mutate Redis, render Turbo streams for the acting browser |
| **RoomTurboPublisher** | Render the same stream HTML and `HubInterface::publish()` to other browsers |
| **Mercure** | Fan-out one HTML payload to every subscriber on the room topic |
| **Turbo** | Parse `<turbo-stream>` elements and replace DOM targets by `id` |

## Room topic subscription

Each room page opens one Mercure subscription via UX Turbo:

```twig
{# templates/room/show.html.twig #}
{{ turbo_stream_from(mercure_topic) }}
```

The topic is built server-side:

```text
{mercure_topic_prefix}/rooms/{roomUuid}
```

Example with default config: `/rooms/550e8400-e29b-41d4-a716-446655440000`.

```mermaid
sequenceDiagram
    participant Browser
    participant Symfony
    participant Mercure

    Browser->>Symfony: GET /r/{uuid}
    Symfony->>Browser: HTML + turbo_stream_from(/rooms/{uuid})
    Browser->>Mercure: Subscribe (SSE) to /rooms/{uuid}
    Note over Browser,Mercure: Connection stays open for the session
```

Subscriptions are **public** (`private: false` on publish). Room UUID acts as the capability — only people with the link join.

## Vote privacy model

During **voting** phase, Mercure payloads use `PublicParticipantView`: display name, moderator flag, and `hasVoted` — never the card value.

```mermaid
flowchart LR
    subgraph redis["Redis Participant"]
        V[voteValue: CardValue 8]
    end

    subgraph php["PHP projection Phase::Voting"]
        P[PublicParticipantView<br/>hasVoted: true<br/>voteLabel: null]
    end

    subgraph php2["PHP projection Phase::Revealed"]
        R[PublicParticipantView<br/>voteLabel: 8<br/>voteAccent: blue]
    end

    redis --> php
    redis --> php2
```

After **reveal**, the same projection includes `voteLabel`, `voteAccent`, and optional icon (`?`, coffee).

The acting voter always gets a **direct Turbo response** on their POST (selected card on the deck). Everyone else learns only from Mercure — still no leaked values until reveal.

## Vote flow (one estimator)

```mermaid
sequenceDiagram
    participant Voter as Browser (voter)
    participant Others as Other browsers
    participant App as Symfony
    participant Redis as Redis
    participant Hub as Mercure

    Voter->>App: POST /r/{uuid}/vote card=8
    App->>Redis: persist vote, phase=voting
    App->>Hub: publishGrid → slot-grid HTML
    Hub-->>Others: turbo-stream replace #slot-grid
    App-->>Voter: turbo-stream slot-grid + vote-deck (selected)

    Note over Others: Slots show card back + voted state only
    Note over Voter: Own deck shows selected card
```

`publishGrid` renders `_slot_grid.stream.html.twig` — participant grid only.

## Reveal flow (moderator)

Reveal is a **session sync**: grid, deck, consensus strip, recap, story chrome, and moderator buttons update together.

```mermaid
sequenceDiagram
    participant Mod as Browser (moderator)
    participant Others as Other browsers
    participant App as Symfony
    participant Redis as Redis
    participant Hub as Mercure

    Mod->>App: POST /r/{uuid}/reveal
    App->>Redis: phase=revealed
    App->>App: ConsensusCalculator → median spread outliers
    App->>Hub: publishSessionSync revealed + consensus
    Hub-->>Others: _session_sync.stream.html.twig
    App-->>Mod: _phase_change.stream.html.twig (+ moderator-actions)

    Note over Others,Mod: Cards flip, consensus strip appears
```

## Turbo DOM targets

Session sync replaces multiple elements by stable `id`:

```mermaid
flowchart TB
    subgraph stream["_session_sync.stream.html.twig"]
        S1["replace #slot-grid"]
        S2["replace #vote-deck"]
        S3["replace #consensus-strip"]
        S4["replace #recap-panel"]
        S5["replace #story-title + queue lists"]
        S6["replace #story-queue-drawer (when flagged)"]
        S7["replace #pp-brand-suffix"]
        S8["replace #pp-room-meta confetti flag"]
    end

    subgraph page["Room page"]
        G[slot-grid]
        D[vote-deck]
        C[consensus-strip]
        R[recap-panel]
        Q[story queue drawer]
    end

    S1 --> G
    S2 --> D
    S3 --> C
    S4 --> R
    S5 --> Q
    S6 --> Q
```

| Stream template | Typical trigger | Mercure? | Also returns to actor? |
|-----------------|-----------------|----------|----------------------|
| `_slot_grid.stream` | vote, clear, rename, join | yes (`publishGrid`) | vote/clear: `_vote_update` |
| `_session_sync.stream` | reveal, restart, next story, settings, new session | yes (`publishSessionSync`) | reveal/restart/next: `_phase_change` |
| `_story_audience.stream` | story title / queue edits | yes (`publishStory`) | `_story.stream` to moderator UI |
| `_phase_change.stream` | moderator phase actions | via included session sync | yes |
| `_moderator_actions.stream` | reveal, restart, next | bundled in phase change | moderator only |

## Publisher API

`RoomTurboPublisher` centralizes Mercure publishes:

| Method | Renders | Publishes when |
|--------|---------|----------------|
| `publishGrid($room)` | `_slot_grid.stream` | Vote, clear, rename, join |
| `publishSessionSync($room, $revealed, $consensus, $refreshQueue)` | `_session_sync.stream` | Reveal, restart, next, settings, archive queue |
| `publishStory($room)` | `_story_audience.stream` | Story title / queue add / remove |

Publish errors are logged and swallowed so a down Mercure hub does not break POST handlers.

## Join flow

New participants trigger `publishGrid` from `EntryController::join` so existing estimators see an extra slot without refreshing.

```mermaid
sequenceDiagram
    participant New as New browser
    participant App as Symfony
    participant Hub as Mercure
    participant Room as Existing browsers

    New->>App: POST /join room_id + name
    App->>App: Redis add participant
    App->>Hub: publishGrid
    Hub-->>Room: updated slot-grid
    App-->>New: redirect GET /r/{uuid}
    New->>App: GET /r/{uuid} + subscribe Mercure
```

## Heartbeat (not Mercure)

Presence uses HTTP POST, not Mercure:

```text
POST /r/{uuid}/heartbeat  →  RoomService::heartbeat  →  Redis lastSeen
```

Stimulus on the room page fires on an interval from `heartbeat_seconds`. Missed heartbeats plus `grace_seconds` remove ghost participants on the next grid publish.

## Failure modes

| Symptom | Likely cause |
|---------|----------------|
| Others never see votes | Mercure hub down, wrong `MERCURE_PUBLIC_URL`, or publish JWT |
| Only actor updates | Subscriber not connected — check browser devtools EventSource |
| Stale roster | Heartbeat / grace — participant dropped after idle |
| Works locally, not in prod | Reverse proxy must expose `/.well-known/mercure` to browsers |

## See also

- [Configuration](configuration.md) — `mercure_topic_prefix`, heartbeat TTL
- [Quick start](quickstart.md) — moderator workflow
- [Symfony Mercure](https://symfony.com/doc/current/mercure.html)
- [UX Turbo streams](https://symfony.com/bundles/ux-turbo/current/index.html#turbo-streams)
