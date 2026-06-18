# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Per-team progress on the album checklist** — each team/section header now shows
  how many stickers you own out of the total, the number still missing
  ("X manq.") and a thin completion bar, so you can see at a glance which teams are
  nearly done. The team header updates live (Turbo Stream) when you add/remove a
  sticker, alongside the album totals.
- **Achievement badges (trophies)** — a "Trophées" section on your profile shows
  earned and still-locked achievements (with progress) derived from your collection
  and trades: first album followed, 100 stickers owned, album completed, 3 albums
  completed, a full team collected, 50 duplicates, first trade and 10 trades. Earned
  badges also appear on your public share page. Computed on the fly, no extra storage.

## [1.4.0] - 2026-06-11

### Changed
- **Replaced Google SSO with the self-hosted Authelia SSO** (OIDC, `auth.zebbox.net`).
  The login page now offers a "zebbox SSO" button instead of Google; accounts created
  through Authelia are **auto-approved** (identities are already curated in Authelia).
  `form_login` (e-mail + password) and the moderated `/register` flow are unchanged.
  - App config: generic OIDC client (`OIDC_ISSUER`/`OIDC_CLIENT_ID`/`OIDC_CLIENT_SECRET`),
    `App\Security\AutheliaAuthenticator`, `/connect/authelia[/check]`.
  - DB: `user.google_id` → `user.authelia_id` (migration `Version20260611120000`).
  - Requires registering a confidential OIDC client in Authelia (redirect
    `https://<host>/connect/authelia/check`, scopes `openid profile email groups`,
    `userinfo_signed_response_alg: none`).

## [1.3.4] - 2026-06-07

### Fixed
- **PWA service worker failed to register** (the page-side `workbox-window` 404'd
  because Workbox was loaded from the CDN but the helper resolved it locally), so the
  app wasn't actually installable. Host Workbox + workbox-window + idb locally
  (`use_cdn: false`), compiled by `pwa:compile`.

## [1.3.3] - 2026-06-07

### Fixed
- **Login (and form submissions) appeared to require a manual refresh.** Behind the
  Cloudflare Tunnel the app saw the request as HTTP and generated `http://` redirects;
  Turbo's `fetch` to that URL was blocked as mixed content on the HTTPS page, so the
  page never updated. The app now trusts the reverse proxy and the forwarded scheme
  (`trusted_proxies` + `trusted_headers`), and Traefik forwards `X-Forwarded-Proto:
  https` for the public host — so redirects are https and Turbo works. (This was the
  real cause; the 1.3.1/1.3.2 service-worker fixes were unrelated red herrings.)

## [1.3.2] - 2026-06-07

### Fixed
- Serve the PWA **service worker and manifest with `Cache-Control: no-cache`** so a
  CDN/browser can't pin an outdated service worker (Cloudflare was edge-caching
  `/sw.js` for hours, which kept the 1.3.1 fix from reaching clients). The manifest
  is now also served with the correct `application/manifest+json` MIME type.

## [1.3.1] - 2026-06-07

### Fixed
- **PWA service worker served stale HTML**, so a login (or a wrong-password error)
  only appeared after a manual refresh. Navigations now use **NetworkFirst** instead
  of StaleWhileRevalidate — pages are fetched fresh when online and only fall back to
  cache when offline. The worker also activates immediately (`skip_waiting`) and drops
  its old caches.

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

[Unreleased]: https://github.com/zebby76/stickers-manager/compare/1.3.4...HEAD
[1.3.4]: https://github.com/zebby76/stickers-manager/compare/1.3.3...1.3.4
[1.3.3]: https://github.com/zebby76/stickers-manager/compare/1.3.2...1.3.3
[1.3.2]: https://github.com/zebby76/stickers-manager/compare/1.3.1...1.3.2
[1.3.1]: https://github.com/zebby76/stickers-manager/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/zebby76/stickers-manager/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/zebby76/stickers-manager/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/zebby76/stickers-manager/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/zebby76/stickers-manager/releases/tag/1.0.0
