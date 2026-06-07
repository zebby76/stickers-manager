# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-06-07

### Added
- **Installable PWA**: web app manifest, a Workbox-based service worker (offline
  shell + asset/image caching), app icons (8 sizes + maskable) and Apple touch
  icons. The app can be added to the home screen on Android/iOS and stays usable
  offline for already-visited pages.

### Changed
- **Touch-friendly checklist**: owned stickers now show a − button to remove a
  copy, so decrement works on phones/tablets (there is no right-click on touch).
  Tap still adds +1; on desktop left-click +1 / right-click −1 are unchanged.

## [1.2.0] - 2026-06-07

### Changed
- **HTTP sessions and the application cache (Doctrine result cache) now live in
  Valkey** (Redis-compatible, BSD-licensed) in production, instead of PostgreSQL
  and the in-container filesystem. Sessions survive restarts and the cache stays
  **warm across scale-to-zero cold starts**, while offloading session churn from
  the database. Dev/test keep file-based sessions and a filesystem cache.

### Removed
- The `sessions` PostgreSQL table (superseded by Valkey) — dropped via migration.

## [1.1.0] - 2026-06-07

### Added
- **Password reset** — self-service "forgot password" flow with an e-mailed,
  single-use, expiring link (`symfonycasts/reset-password-bundle`). Account
  existence is never revealed.
- **Version footer** — the running version is shown on every page (sourced from
  the deployed image tag in production).

### Changed
- **HTTP sessions are now stored in PostgreSQL** (`PdoSessionHandler`) so they
  survive container restarts — important on the read-only, tmpfs-backed runtime
  and behind scale-to-zero.

## [1.0.0] - 2026-06-06

First public release.

### Added
- **Collections** — follow albums, mark stickers as owned, track quantities.
- **Duplicates** — copies beyond the first are swappable doubles.
- **Statistics** — completion progress and a packs-needed estimate
  (coupon-collector formula).
- **Swaps** — trade proposals (give / receive) with a
  `pending → accepted → completed` flow.
- **Public share page** — tokenized, cacheable page listing a collector's
  missing stickers and duplicates (`/u/{token}`), no login required.
- **Accounts & roles** — moderated sign-up, admin user management, optional
  Google SSO.
- **Checklist UX** — Turbo Streams (left-click +1 / right-click −1) with an
  animated counter; client-side search & filters.
- **Album import** — JSON (full album) or CSV (stickers), web (`/albums/import`)
  and CLI (`app:album:import`).
- **Pixel / retro UI** theme with DiceBear pixel-art avatars.

### Packaging & operations
- Runs on the `smalswebtech/base-php` image (PHP 8.5, nginx + php-fpm,
  read-only root filesystem).
- FastCGI micro-cache, rate limiting and security headers wired in the vhost.
- Prometheus metrics and OpenTelemetry tracing (auto-instrumented).
- Multi-arch image (linux/amd64 + linux/arm64), **cosign**-signed and
  **Trivy**-scanned.

### CI/CD
- GitHub Actions: lint + PHPUnit, `docker buildx bake` (native per-arch build →
  push by digest → manifest merge), cosign signing, Trivy scan, automated
  GitHub Release, scheduled run cleanup, grouped Dependabot updates.

[Unreleased]: https://github.com/zebby76/stickers-manager/compare/1.3.0...HEAD
[1.3.0]: https://github.com/zebby76/stickers-manager/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/zebby76/stickers-manager/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/zebby76/stickers-manager/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/zebby76/stickers-manager/releases/tag/1.0.0
