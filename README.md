# ⚽ Stickers Manager

A web app to manage **football sticker collections**: albums, owned / missing
tracking, duplicates, completion statistics, and **swaps between collectors**.

Built with **Symfony 8** (Twig + Symfony UX / Stimulus + AssetMapper) on
**PostgreSQL**, and packaged on the [`smalswebtech/base-php`](https://github.com/Smals-Webtech/base-php)
base image (PHP 8.5, nginx + php-fpm, supervisord, designed to run read-only).

## Features

- **Collections** — follow albums, mark stickers as owned, track quantities.
- **Duplicates** — anything beyond the first copy is a swappable double.
- **Statistics** — completion progress and an estimate of the number of packs
  still needed (coupon-collector formula).
- **Swaps** — propose trades (give / receive) with a `pending → accepted → completed` flow.
- **Public share page** — a tokenized, cacheable page listing a collector's
  missing stickers and duplicates (`/u/{token}`), no login required.
- **Accounts & roles** — moderated sign-up, admin user management, optional Google SSO.
- **Observability-ready** — Prometheus metrics, and OpenTelemetry tracing
  (auto-instrumented). See the deployment stack `stickers-manager-local`.

## Tech stack

| Area      | Choice |
|-----------|--------|
| Language  | PHP 8.5 |
| Framework | Symfony 8 (Twig, UX/Stimulus, Turbo, AssetMapper) |
| Database  | PostgreSQL 16 (Doctrine ORM) |
| Runtime   | `smalswebtech/base-php` (nginx + php-fpm + supervisord, read-only) |
| Tooling   | Docker, `docker buildx bake`, GitHub Actions |

No local PHP or Composer is required — all tooling runs in the
`base-php:8.5-cli-dev` container through `bin/dev`.

## Quick start (development)

```bash
make build-app   # install vendor + compile assets (Composer + AssetMapper)
make up          # start postgres + app (bind-mounted, live reload) + adminer
make fixtures    # optional: load demo data
```

- Application: <http://localhost:8080>
- Adminer (database UI): <http://localhost:8081>
- Metrics: <http://localhost:9090/metrics>

> Database migrations run automatically when the container starts
> (`.docker/bin/container-entrypoint.d/10-stickers-setup.sh`).

Demo accounts (password `password`):

| Email | Role |
|-------|------|
| `alice@example.com` | **admin** — manages the catalog and users |
| `bob@example.com`, `carol@example.com` | collectors |
| `dave@example.com` | **pending approval** (cannot sign in yet) |

## Project layout

The Symfony application lives at the **repository root**; packaging sits alongside
it (same model as `elasticms-admin` / `elasticms-web`). At runtime the app is
served from `/app/src/stickers`.

```
stickers-manager/
├── src/ config/ public/ templates/ migrations/ assets/   # Symfony application
├── composer.json  importmap.php  bin/console
├── Dockerfile            # multi-stage: builder (cli-dev) → prd (nginx) / dev (nginx-dev)
├── docker-bake.hcl       # multi-arch build, tags & labels
├── compose.yaml          # dev stack: base-php:nginx-dev (bind-mount) + Postgres + Adminer
├── Makefile              # build-app / build-image / bake + dev workflow
├── bin/dev               # run a command inside base-php:8.5-cli-dev (→ /app/src/stickers)
└── .docker/              # overlay copied into the image's /opt
    ├── config/nginx/sites-enabled/zz-stickers.conf.tmpl   # vhost → public/
    └── bin/container-entrypoint.d/10-stickers-setup.sh    # cache + migrations on boot
```

## Accounts & roles

- **Moderated sign-up** — a new account is created as *pending* and cannot sign in
  until an admin approves it (the login page explains why).
- **Deactivate / delete** — an admin can disable (reversible ban) or permanently
  delete an account.
- **Profile** (`/profile`) — display name, country (flag), password change, stats.
- **User management** (`/admin/users`, admin) — approve, ban/reactivate,
  promote/demote admins, delete. A badge flags pending accounts.

| Role | Capabilities |
|------|--------------|
| `ROLE_ADMIN` | Catalog (albums/stickers, import) + user management |
| `ROLE_USER`  | Follow albums, mark owned/duplicates, swap |

```bash
make console ARGS="app:user:admin bob@example.com"            # grant ROLE_ADMIN
make console ARGS="app:user:admin bob@example.com --revoke"   # revoke ROLE_ADMIN
```

### Google SSO (optional)

A "Sign in with Google" button appears only when configured. Create OAuth
credentials in the [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
(redirect URI: `https://<host>/connect/google/check`), then set them in `.env.local`:

```dotenv
GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxx
```

Accounts created through Google are also subject to admin approval.

## Packaging

```bash
make build-image   # builds zebby76/stickers-manager:snapshot (prd) + :snapshot-dev
make bake          # docker buildx bake (multi-arch, see docker-bake.hcl)
```

The `builder` stage (`base-php:8.5-cli-dev`) lays the app out at `/app/src/stickers`
and copies `.docker/` to `/opt/`. The `prd` (`8.5-nginx`) and `dev` (`8.5-nginx-dev`)
stages then pull in `/app` + `/opt`. The nginx vhost and the setup script are
rendered/run on boot by the base image entrypoint. The image enables FastCGI
micro-cache, rate limiting and the OpenTelemetry extension by default.

## CI/CD (GitHub Actions)

- **`ci.yml`** — on every push/PR to `main`: lint (Twig / container / YAML) and the
  PHPUnit suite, inside `base-php:8.5-cli-dev` with a PostgreSQL service.
- **`docker.yml`** — builds images with `docker buildx bake` and publishes to
  **`zebby76/stickers-manager`**. Each architecture is built **in parallel on a
  native runner** (`ubuntu-24.04` for amd64, `ubuntu-24.04-arm` for arm64 — no QEMU),
  pushed *by digest*, then the digests are merged into a multi-arch manifest
  (GHA cache + SBOM + provenance):
  - **pull request** → validation build, no push;
  - **push to `main`** → `:latest` / `:latest-dev`;
  - **tag `X.Y.Z`** → `:X.Y.Z` / `:X.Y.Z-dev` (+ `latest`);
  - **workflow_dispatch** → manual version.

  After push, images are **signed with cosign** (keyless / OIDC) and **scanned with
  Trivy** (SARIF uploaded to the *Security* tab). Verify a signature:

  ```bash
  cosign verify zebby76/stickers-manager:latest \
    --certificate-identity-regexp "https://github.com/zebby76/stickers-manager/.github/workflows/docker.yml@.*" \
    --certificate-oidc-issuer https://token.actions.githubusercontent.com
  ```

- **`release.yml`** — on tag `X.Y.Z`: creates a **GitHub Release** (auto-generated
  notes + `docker pull` commands for the published images).

Required repository secrets (Settings → Secrets → Actions): `DOCKERHUB_USERNAME`
and `DOCKERHUB_TOKEN` (a Docker Hub token with *push* access to `zebby76/stickers-manager`).

## Domain model

- **User** — a collector (email/password authentication).
- **Album** / **Sticker** — shared catalog; stickers are identified by number and
  carry a type (common / shiny / crest / legend).
- **UserAlbum** — albums a user follows.
- **UserSticker** — ownership and quantity (`quantity − 1` = duplicates).
- **TradeProposal** / **TradeProposalItem** — swap offers (give / receive),
  `pending → accepted → completed`.

## Implementation notes

- **Turbo checklist** — the +/− controls on a sticker use Turbo Streams
  (`CollectionController` + `templates/collection/adjust.stream.html.twig`) to update
  the cell and counters without a full reload (graceful fallback if JS is disabled).
- **Album import** (admin) — JSON (full album) or CSV (stickers) via
  `App\Service\AlbumImporter`, at `/albums/import` or `app:album:import <file>`.

## Testing

```bash
make test     # PHPUnit (functional tests, transactional isolation via DAMA)
```

## Useful commands

| Command | Description |
|---------|-------------|
| `make up` / `make down` | Start / stop the dev stack |
| `make logs` | Tail application logs |
| `make console ARGS="…"` | Run `bin/console` in the tooling container |
| `make composer ARGS="…"` | Run Composer |
| `make diff` / `make migrate` | Generate / apply migrations |
| `make fixtures` | Load demo data |
| `make console ARGS="app:album:import <file>"` | Import an album (JSON/CSV) |
| `make db-reset` | Drop + create + migrate + fixtures |
| `make shell` | Interactive shell (cli-dev) |
| `make db-shell` | `psql` into the database |

## Production / deployment

Build a production image with `make build-image` (or `make bake` for multi-arch),
or pull a published tag from `zebby76/stickers-manager`. A complete, ready-to-run
deployment stack (Traefik + PostgreSQL + optional observability: Prometheus,
Grafana, OpenTelemetry, Elasticsearch, Kibana) lives in the companion repository
**`stickers-manager-local`**.

## License

Released under the [MIT License](LICENSE).
