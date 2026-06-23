# AGENTS.md

Guide for AI coding agents (and humans) working on **Stickers Manager**.
Read this before making changes. Claude Code loads it automatically via `CLAUDE.md`
(which does `@AGENTS.md`); other tools read this file directly.

## What this is

A self-hosted web app to manage **football sticker collections** (albums, owned
stickers, duplicates, swaps between collectors). Symfony 8 / PHP 8.4+, built on the
`smalswebtech/base-php` image. Deployed as a signed multi-arch Docker image; a
release is cut by pushing a `X.Y.Z` git tag.

## ⚠️ Hard constraints (do not break)

- **Branding**: the word **"Panini" must NEVER appear** in user-facing text, code,
  templates, fixtures or data. The product is generically "Stickers Manager". This is
  a trademark constraint — treat any occurrence as a bug.
- **Secrets**: real secrets live only in `.env.local` (gitignored). **Never** commit a
  `.env*` file containing a real secret. Config keys go in `.env` with placeholder/empty
  values; values are injected per host.
- **Read-only runtime**: production runs on a **read-only root filesystem** (tmpfs for
  cache/sessions). Never write to disk at runtime outside the framework's tmp/cache
  dirs. Sessions + Doctrine result cache live in **Valkey** in prod (file-based in
  dev/test).
- Keep responses on the public share page (`/u/{token}`) **cacheable and anonymous** —
  no per-user/private data leaks there.

## Stack & layout

- **Symfony 8**, PHP 8.4+, Twig, Doctrine ORM (PostgreSQL), AssetMapper (no Node build).
- **Bootstrap 5.3** + **Bootstrap Icons** (`bi bi-*`) + flag-icons (`fi fi-<cc>`).
- **Turbo / Turbo Streams** for partial updates (the +/- checklist).
- Installable **PWA** (Workbox service worker, hosted locally — not from CDN).
- Auth: `form_login` (email + password), moderated `/register`, and **Authelia OIDC**
  SSO (`App\Security\AutheliaAuthenticator`, `/connect/authelia[/check]`).

Code lives under `src/` (Controller / Service / Repository / Entity / Enum / Security),
templates under `templates/`, tests under `tests/`.

## Dev workflow (everything via Docker + Make)

```bash
make up                              # start dev stack (postgres + app + adminer + mailer)
make down / make restart / make logs
make console ARGS="cache:clear"      # bin/console in the cli-dev tooling container
make test                            # PHPUnit (APP_ENV=test)
make fixtures                        # load fixtures (DROPS data)
make db-reset                        # drop + create + migrate + fixtures
make diff / make migrate             # generate / run migrations
make composer ARGS="require ..."
```

- Dev app: **http://localhost:8080** (container nginx :9000 → host :8080).
- Tooling runs inside the `cli-dev` container with `--network host`.

### Preparing the test DB (first run / after schema changes)

```bash
APP_ENV=test make console ARGS="doctrine:database:create --if-not-exists"
APP_ENV=test make console ARGS="doctrine:migrations:migrate --no-interaction"
APP_ENV=test make fixtures
make test
```

## Before you commit — validation gate

Run all three and make sure they pass:

```bash
make console ARGS="lint:container"   # service wiring
make console ARGS="lint:twig templates"
make test                            # full PHPUnit suite
```

Update `CHANGELOG.md` (under `## [Unreleased]` → `### Added/Changed/Fixed`) for any
user-visible change. Releases are cut by tagging `X.Y.Z` (no VERSION file); a tag
triggers the Docker build + GitHub Release workflows.

### Commit conventions

- Conventional commits: `feat(scope): …`, `fix(scope): …`, etc. (see `git log`).
- End commit messages with:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
- Branch off `main` before committing if asked to open a PR; otherwise commit to the
  branch you're on only when the user asks.

## Conventions & patterns learned

- **Computed-on-read value objects** instead of new tables where possible. Immutable
  `final readonly` VOs mirror `App\Service\AlbumProgress`:
  `TeamProgress`, `Badge`, `Reputation`. Derive from existing entities each request.
- **No N+1**: when a feature touches a list of users/albums, add a **batch query** to
  the repository (e.g. `TradeProposalRepository::completedCountsForUsers()` using
  `IDENTITY()` to select FK ids) rather than querying per-row.
- **Turbo Streams**: partial updates return `<turbo-stream action="replace"
  target="…">` blocks targeting **stable DOM ids**. A shared id helper on the VO
  (e.g. `TeamProgress::domId()`) keeps the initial render and the stream in sync.
  Reuse the same Twig partial for first render *and* the stream block
  (e.g. `templates/album/_team_header.html.twig`).
- **Twig helpers**: `country_code(team)` → ISO code for flag rendering
  (`<span class="fi fi-{{ cc }}">`); `avatar_url(email)` for DiceBear pixel avatars.
- **Bootstrap gotcha**: `text-bg-*` has **no `-subtle` variant**. For subtle danger use
  `bg-danger-subtle text-danger-emphasis border border-danger-subtle`.
- Group/section "no team" bucket constant: `CollectionStats::UNGROUPED` (`'Divers'`).

## Key domain objects (orient yourself)

- Entities: `Album`, `Sticker`, `User`, `UserAlbum`, `UserSticker`, `TradeProposal`,
  `TradeProposalItem`. Enum: `App\Enum\TradeStatus` (`pending → accepted → completed`).
- Services: `CollectionStats` (progress, packs-needed coupon-collector estimate,
  `teamBreakdown()`, `completedTeamCount()`), `TradeMatcher` (give/receive lists
  between two users), `BadgeService`, plus the VOs above.
- Public page: `PublicCollectionController` → `/u/{token}` (tokenized, cacheable).

## Tests

Integration/smoke tests live in `tests/SmokeTest.php` (boots the kernel, hits routes,
asserts rendered HTML and repository queries). When a feature has no fixture data
(e.g. trades), **create the rows in-test and clean them up** (`em->remove`) so state
doesn't leak between tests. Assert on stable markers (DOM ids, labels), not layout.

## Backlog & memory

Project-private context (personal preferences, the live feature backlog, secrets to
rotate) is kept in the agent's out-of-repo memory, **not here**. This file holds only
shared, versioned conventions. Don't duplicate secrets or personal notes into git.
