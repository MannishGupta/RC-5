# Phase 2 counter-recheck (3.2.0)

| Check | Result |
|-------|--------|
| Tenant data isolation | Vendor/CSS/PHP only under code tree; no writes to tenants in deploy zip |
| Auth keys | Unchanged |
| BC | Status still works with local Bootstrap path; FA already local |
| Performance | Fewer third-party DNS (xlsx/bootstrap/quill local); fonts Inter removed |
| Security | SRI documented; local scripts reduce CDN supply chain |
| Regression | QR falls back to qrserver if EasyQRCode fails; CSP status tightened to self |
| Syntax | php -l clean on package |
