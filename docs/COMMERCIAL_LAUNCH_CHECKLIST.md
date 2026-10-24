# Resource Centre 5.1.0 — Commercial launch checklist

## Runtime
- [ ] Every host: `php -v` reports **8.5.12+**
- [ ] Deploy **without** overwriting `tenants/*/data/`
- [ ] Hard refresh / CDN purge after deploy
- [ ] Footer shows **5.1.0**

## Smoke tests (per tenant host)
- [ ] Login page renders (no white screen)
- [ ] Login success + logout works
- [ ] `/health.php` → JSON `status:ok`, version 5.1.0
- [ ] Team directory loads; print directory opens
- [ ] Organisation setup saves logo/favicon/cover
- [ ] Mediakit lists assets with previews (no OOM)
- [ ] Metals `?format=json` + board UI
- [ ] Choghadiya `?format=json` + tab UI
- [ ] Status module (if enabled) shows project data
- [ ] Blood report: anonymous → 403; signed-in → OK
- [ ] Themes: light / dark / reserve readable
- [ ] Mobile: sidebar + logout usable

## Security
- [ ] `data/auth_config.php` **not** world-readable via HTTP
- [ ] No `psi_token.txt` left open after demos
- [ ] HTTPS enforced
- [ ] Backup once via tools before go-live; test restore on empty tenant

## SEO / compliance
- [ ] `/robots.txt`, `/llms.txt`, sitemap reachable
- [ ] Privacy / DPDP page linked in footer with real tenant contact
