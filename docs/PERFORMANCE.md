# Performance impact of RC 5.2.x exercises (5.2.5)

## Will UI unification / asset work speed up loading?

**Yes — primarily front-end payload and parse cost.** Approximate Status page impact:

| Change | Approx. savings (transfer, brotli/gzip varies) |
|--------|-----------------------------------------------|
| Drop Bootstrap JS (~80 KB min) | **Large** — less JS parse/execute on Status |
| Drop Bootstrap CSS (~232 KB → ~11 KB rc-grid) | **Large** — ~220 KB CSS less on Status |
| Self-host fonts (no Google Fonts RTTs) | **Medium** — fewer DNS/TLS + render-blocking requests on dashboard |
| Local SheetJS / Chart (no CDN) | **Medium latency** on first use; can be *slower* offline-first first hit if vendor not cached, but predictable and cacheable same-origin |
| Theme/CSS bridge files | Small added CSS; negligible vs Bootstrap removal |

**Dashboard** was already not loading Bootstrap; gains there are mostly font/CDN cleanup and cache-buster consistency.

**None of the above replaces server-side or data-layer work.**

## When you need data / backend optimisation

UI work does **not** fix:

| Symptom | Likely cause | Direction |
|---------|--------------|-----------|
| Slow first dashboard after login | Large JSON blobs, PHP boot, session, tenant resolution | Profile `app/bootstrap.php`, reduce payload, OPcache, Redis/APCu if available |
| Team / mediakit sluggish with many records | Client render of huge lists; file JSON scans | Pagination (already improved), indexes if moving to SQL, avoid loading full media metadata at once |
| Optimizer / image OOM or long runs | GD memory, sequential image walk | Already memory-guarded; run off-peak, batch, or queue |
| Metals / live widgets slow | External HTTP APIs | Cache responses (TTL), edge cache |
| Status with thousands of updates | Full JSON load + filter in browser | Cap feed server-side (`feed.per_component` / `max_total` already exist), archive old updates |

RC today is largely **file/JSON + PHP**, not a heavy RDBMS app. “Enhanced databases” only helps if you **introduce** a DB and migrate hot paths. Until then, optimise:

1. **OPcache** enabled on PHP 8.5.12  
2. **JSON read caching** (`DataCache` / filemtime caches where present)  
3. **Do not overwrite** tenant data; keep payloads lean  
4. **CDN/static** for `/assets/*` with long cache (already in web.config /.htaccess)  
5. **Health + monitor** tabs for host limits (256M memory)

## Recommendation

| Priority | Action |
|----------|--------|
| Ship 5.2.5 | Front-end wins on Status are real — deploy |
| Measure | Lighthouse / WebPageTest on Status + dashboard before/after |
| Next backend | Only if TTFB or API times dominate — then profile PHP and JSON sizes, not “add a database” by default |

**Bottom line:** Phases 1–4 improve **asset weight, request count, and Status interactivity**. They are not a substitute for **data access optimisation** if the bottleneck is large tenant JSON or slow PHP I/O.
