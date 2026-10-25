# UI unification — Dashboard ↔ Status (RC 5.2.5)

## Goal
One product feel: fonts, type scale, colours, control density, theme modes, focus rings, interaction language.

## Phase status

| Phase | Status | What shipped |
|-------|--------|----------------|
| **1 — Bridge** | Done | `rc-shell-unify.css`, shared fonts + theme |
| **2 — Status chrome** | Done | `rc-status-chrome.css` |
| **3 — Drop Bootstrap JS** | Done | `rc-status-ui.js` |
| **4 — Drop Bootstrap CSS** | Done (5.2.5) | `status/assets/rc-grid.css` (~11 KB) replaces `bootstrap.min.css` (~232 KB) |

## Bootstrap fully removed from Status runtime

| Asset | Status |
|-------|--------|
| `bootstrap.bundle.min.js` | Not loaded |
| `bootstrap.min.css` | Not loaded (file may remain in vendor for legacy; unused) |

Status uses: `rc-grid.css` + `app.css` + `rc-fonts` + `rc-shell-unify` + `rc-status-chrome` + `rc-status-ui.js`.

## Device / browser / orientation

Covered in `rc-grid.css` + `rc-compat.css` + `rc-status-chrome.css`:

- Safe-area insets (notch / home indicator)
- Coarse pointer min 44px targets
- Landscape short viewport (`max-height: 500px`) modal/theme adjustments
- `prefers-reduced-motion`
- Print: hide chrome
- Flexbox grid (no CSS Grid requirement) — works on Safari 12+, Chrome, Firefox, Edge, Samsung Internet
- `text-size-adjust` for iOS font inflation

**Not supported as targets:** IE11 (PHP 8.5 hosts will not target IE).

## Verify Phase 4

1. Status layout: sidebar + main columns at ≥992px; stacked/offcanvas below  
2. Consent modal + mobile directory without Bootstrap in Network  
3. Rotate phone landscape: modal scrollable, theme chips usable  
4. Admin tables/forms still aligned  
