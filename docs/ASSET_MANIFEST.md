# Asset manifest (RC 3.2.0) — single source

All production UI libraries load from **`/assets/vendor/`** (self-hosted). Do not add parallel CDN copies for the same library.

| Library | File | Version | SRI (sha384) |
|---------|------|---------|----------------|
| Font Awesome Free | fontawesome.min.css + webfonts/ | 7.3.1 | sha384-iTteL4H3zXIokTJmCoRGgCFBeDfijDRGIxB8SwZjStvagwdABX2j85NZd17zrNV7 |
| Alpine.js | alpine.min.js | 3.x (vendor pin) | sha384-5/joNqFnRyVWzXp99bHot6RHG+EksGp+USSgZwPar7T9SD9PKKER37n/8bXBAZGd |
| Chart.js | chart.umd.min.js | 4.x | sha384-jb8JQMbMoBUzgWatfe6COACi2ljcDdZQ2OxczGA3bGNeWe+6DChMTBJemed7ZnvJ |
| chartjs-plugin-datalabels | chartjs-plugin-datalabels.min.js | 2.2.x | (local) |
| EasyQRCodeJS | easy.qrcode.min.js | 4.x | sha384-+ACTh1wjQ5SAGove5YFrqDcZPJ5t2ylJTxpj7hjCPS4NvdTmo4YVZHGSJcRJphJf |
| Bootstrap | bootstrap.min.css / bootstrap.bundle.min.js | 5.3.8 | css sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB · js sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI |
| SheetJS | xlsx.full.min.js | 0.20.3 | sha384-EnyY0/GSHQGSxSgMwaIPzSESbqoOLSexfnSMN2AP+39Ckmn92stwABZynq1JyzdT |
| Quill | quill.snow.css / quill.min.js | 2.0.3 | js sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8 |
| SortableJS | Sortable.min.js | 1.15.7 | sha384-DgmC6Xe2bSN2WjTDXzWYbUbxyhNP+NNkGDR/g78pCXV7E7rcVTGxVg0uIVCUUcBc |

## Typography
- English: `Segoe UI Variable`, `Segoe UI`, `Aptos`, `system-ui` (no Inter CDN on shell)
- Hindi: `Noto Sans Devanagari` (optional single Google Fonts load with `display=swap` until self-hosted)

## Forbidden on shell
- Duplicate FA from cdnjs/jsDelivr when vendor FA is present
- Second xlsx major (0.18.x) — use 0.20.3 vendor only
- api.qrserver.com when EasyQRCodeJS can render client-side

## 3.2.1 fully self-hosted
- Fonts: `/assets/fonts/rc-fonts.css` + `noto-sans-devanagari/*.ttf`
- QR: `/qr.php` (local SVG) — not qrserver
- No Google Fonts, jsDelivr, cdnjs, or SheetJS CDN required at runtime
