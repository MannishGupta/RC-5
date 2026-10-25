# Asset manifest (RC 5.2.2) — fully self-hosted

All production UI libraries load from **`/assets/vendor/`** or **`/assets/fonts/`**.  
Do **not** add parallel CDN copies for the same library on the main shell.

| Library | File | Version | Load path |
|---------|------|---------|-----------|
| Font Awesome Free | fontawesome.min.css + webfonts/ | 7.x | `/assets/vendor/` |
| Alpine.js | alpine.min.js | 3.x | `/assets/vendor/` (defer) |
| Chart.js | chart.umd.min.js | 4.x | `/assets/vendor/` (lazy) |
| chartjs-plugin-datalabels | chartjs-plugin-datalabels.min.js | 2.2.x | `/assets/vendor/` (lazy) |
| EasyQRCodeJS | easy.qrcode.min.js | 4.x | `/assets/vendor/` (lazy) |
| ~~Bootstrap 5~~ | removed from Status runtime (5.2.5) | — | Use `status/assets/rc-grid.css` + `rc-status-ui.js` |
| Status grid | rc-grid.css | 5.2.5 | `/status/assets/` (~11 KB) |
| SheetJS | xlsx.full.min.js | 0.20.3 | `/assets/vendor/` (lazy, no CDN) |
| Quill | quill.snow.css / quill.min.js | 2.0.x | `/assets/vendor/` (lazy) |
| SortableJS | Sortable.min.js | 1.15.x | `/assets/vendor/` (lazy) |
| Leaflet | leaflet.js / leaflet.css | 1.x | `/assets/vendor/leaflet/` |

## Shell unify
- `rc-shell-unify.css` — bridge Status Bootstrap chrome ↔ RC tokens (see `docs/UI_UNIFICATION.md`)

## Typography (self-hosted)
- English: system UI stack (`Segoe UI` / `system-ui`) — **no Inter CDN**
- Hindi: `Noto Sans Devanagari` via `/assets/fonts/rc-fonts.css` + bundled TTFs

## Forbidden on shell
- Google Fonts / jsDelivr / cdnjs / SheetJS CDN for libraries already in vendor
- Second xlsx major — use vendor 0.20.3 only
- External QR image APIs when EasyQRCodeJS can render client-side

## Intentional external (API, not UI libraries)
- Google Maps JS (runners / dispatch) — only when Maps API key configured
- Google Sheets / Gemini / YouTube Data APIs — server-side with tenant keys
- gstatic favicon helper for domain icons in master directory (optional network)

## Bootstrap scope
Bootstrap CSS/JS is **not** loaded on the main dashboard (Tailwind + Fluent tokens).  
It is used by the **Status** sub-app (`status/index.php`, `status/admin.php`) only — keep vendor files.
