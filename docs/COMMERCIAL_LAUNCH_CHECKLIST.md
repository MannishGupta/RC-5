# Resource Centre 5.2.2 — Commercial launch checklist

## Runtime
- [ ] Every host: `php -v` reports **8.5.12+**
- [ ] Deploy **without** overwriting `tenants/*/data/`
- [ ] Hard refresh after deploy (asset `?v=` follows APP_VERSION)
- [ ] Footer / health shows **5.2.2**

## Smoke tests (per tenant host)
- [ ] Login page renders (no white screen)
- [ ] Login success + logout works
- [ ] `/health.php` → JSON `status:ok`, version 5.2.2
- [ ] Team directory loads; page size fills grid rows (no sparse blanks)
- [ ] Themes: **light / dark / reserve** — body text readable on all main tabs
- [ ] Sidebar footer: Light / Dark / Reserve / Logout **one compact row**
- [ ] Organisation setup saves logo/favicon/cover
- [ ] Mediakit lists assets with previews (no OOM on large images)
- [ ] Excel import/export works **offline** (local SheetJS vendor)
- [ ] Metals `?format=json` + board UI
- [ ] Choghadiya `?format=json` + tab UI
- [ ] Status module (if enabled) shows project data (Bootstrap self-hosted)
- [ ] Blood report: anonymous → 403; signed-in → OK
- [ ] Mobile: sidebar + logout usable

## Security
- [ ] `data/auth_config.php` **not** world-readable via HTTP
- [ ] `data/`, `app/`, `tenants/`, `docs/` blocked (web.config / .htaccess)
- [ ] No demo tokens left open after trials
- [ ] HTTPS enforced in production
- [ ] Backup once via tools before go-live; test restore on empty tenant
- [ ] Set strong `RC_DIAG_KEY` / rotate default secrets

## SEO / compliance
- [ ] `/robots.txt`, `/llms.txt`, sitemap reachable
- [ ] Privacy / DPDP page linked in footer with real tenant contact

## Assets
- [ ] No Google Fonts request in Network tab on dashboard
- [ ] No cdn.sheetjs.com / jsdelivr chart requests on dashboard/tenants
- [ ] Font Awesome webfonts load from `/assets/vendor/webfonts/`
