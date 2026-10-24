// ============================================================================
// Markets & Metals Widget — v18.5 (API-tolerant · 304-aware · partial OK)
// Feed: https://rc.arthsathi.com/metals.php?format=json
// Parameter: hi | page2 | fx | 2  (space/comma separated)
// ============================================================================

const RAW_PARAM = String(args.widgetParameter || "").trim().toLowerCase();
const TOKENS = new Set(RAW_PARAM.split(/[\s,]+/).filter(Boolean));

const USE_HINDI  = TOKENS.has("hi");
const IS_PAGE_2  = TOKENS.has("page2") || TOKENS.has("fx") || TOKENS.has("2");
const DEBUG      = !config.runsInWidget;

const ACTIVE_LOCALE = USE_HINDI ? "hi-IN" : "en-IN";
const IST_ZONE      = "Asia/Kolkata";
const UPDATE_MINUTES = 15;

const BRAND = {
    label:      "Powered by Arthsathi Limited.",
    shortLabel: "Arthsathi Ltd.",
    link:       "https://arthsathi.com/",
    icon:       "https://arthsathi.com/favicon.ico",
    feed:       "https://rc.arthsathi.com/metals.php?format=json",
    cacheFile:  "arthsathi_icon_v185.png",
    storeFile:  "metals_store_v185.json"
};

const METAL_KEYS = ["gold", "silver", "copper", "brass", "zinc", "steel"];
const FX_KEYS    = ["usd_inr", "eur_inr", "cny_inr", "aed_inr"];
const ALL_KEYS   = METAL_KEYS.concat(FX_KEYS);

const TAX = {
    gold: 0.03, silver: 0.03,
    copper: 0.18, brass: 0.18, zinc: 0.18, steel: 0.18,
    usd_inr: 0, eur_inr: 0, cny_inr: 0, aed_inr: 0
};

const FALLBACK = {
    gold:    { base: 128972, change: -0.64 },
    silver:  { base: 189794, change:  1.32 },
    copper:  { base: 1487.4, change:  1.26 },
    brass:   { base:  624.71, change: 1.26 },
    zinc:    { base:  447.9, change: -0.86 },
    steel:   { base: 47000,  change: -2.09 },
    usd_inr: { base: 94.92,  change: -0.39 },
    eur_inr: { base: 106.60, change: -0.05 },
    cny_inr: { base: 14.21,  change:  0.07 },
    aed_inr: { base: 25.79,  change: -0.56 }
};

const TEXT = {
    "en-IN": {
        heading: "MARKETS & METALS",
        sub: "Bullion • Metals • FX",
        live: "LIVE", cached: "CACHED", offline: "OFFLINE", error: "ERROR",
        note: "Delhi NCR",
        assets: {
            gold:    { name: "Gold 24K",  spec: "99.9% • 10g",  icon: "sparkles",                   color: "#D4AF37" },
            silver:  { name: "Silver",    spec: "999 • 1kg",    icon: "circle.hexagongrid",         color: "#A8A9AD" },
            copper:  { name: "Copper",    spec: "MCX • 1kg",    icon: "bolt.circle.fill",           color: "#D97742" },
            brass:   { name: "Brass",     spec: "Spot • 1kg",   icon: "circle.fill",                color: "#CA8A04" },
            zinc:    { name: "Zinc",      spec: "MCX • 1kg",    icon: "hexagon.fill",               color: "#64748B" },
            steel:   { name: "MS Ingot",  spec: "1 MT",         icon: "square.stack.3d.up",         color: "#475569" },
            usd_inr: { name: "USD / INR", spec: "Forex Spot",   icon: "dollarsign.circle",          color: "#38A169" },
            eur_inr: { name: "EUR / INR", spec: "Forex Spot",   icon: "eurosign.circle",            color: "#3182CE" },
            cny_inr: { name: "CNY / INR", spec: "Forex Spot",   icon: "yensign.circle",             color: "#DD6B20" },
            aed_inr: { name: "AED / INR", spec: "Forex Spot",   icon: "coloncurrencysign.circle",   color: "#319795" }
        }
    },
    "hi-IN": {
        heading: "मार्केट और धातु",
        sub: "बुलियन • धातु • फॉरेक्स",
        live: "लाइव", cached: "कैश्ड", offline: "ऑफलाइन", error: "त्रुटि",
        note: "दिल्ली एनसीआर",
        assets: {
            gold:    { name: "सोना 24K",   spec: "99.9% • 10g",  icon: "sparkles",                   color: "#D4AF37" },
            silver:  { name: "चांदी",      spec: "999 • 1kg",    icon: "circle.hexagongrid",         color: "#A8A9AD" },
            copper:  { name: "तांबा",      spec: "MCX • 1kg",    icon: "bolt.circle.fill",           color: "#D97742" },
            brass:   { name: "पीतल",       spec: "स्पॉट • 1kg",  icon: "circle.fill",                color: "#CA8A04" },
            zinc:    { name: "जस्ता",      spec: "MCX • 1kg",    icon: "hexagon.fill",               color: "#64748B" },
            steel:   { name: "एमएस इंगट",  spec: "1 MT",         icon: "square.stack.3d.up",         color: "#475569" },
            usd_inr: { name: "USD / INR",  spec: "फॉरेक्स",      icon: "dollarsign.circle",          color: "#38A169" },
            eur_inr: { name: "EUR / INR",  spec: "फॉरेक्स",      icon: "eurosign.circle",            color: "#3182CE" },
            cny_inr: { name: "CNY / INR",  spec: "फॉरेक्स",      icon: "yensign.circle",             color: "#DD6B20" },
            aed_inr: { name: "AED / INR",  spec: "फॉरेक्स",      icon: "coloncurrencysign.circle",   color: "#319795" }
        }
    }
};
const T = TEXT[ACTIVE_LOCALE];

const C = {
    bg1:    Color.dynamic(new Color("#F8FAFC"), new Color("#121418")),
    bg2:    Color.dynamic(new Color("#EEF2F6"), new Color("#090A0D")),
    card:   Color.dynamic(new Color("#FFFFFF", 0.95), new Color("#1E222D", 0.95)),
    border: Color.dynamic(new Color("#CBD5E1"), new Color("#2D323E")),
    text:   Color.dynamic(new Color("#0F172A"), new Color("#F8FAFC")),
    dim:    Color.dynamic(new Color("#64748B"), new Color("#94A3B8")),
    green:  Color.dynamic(new Color("#137333"), new Color("#81C995")),
    red:    Color.dynamic(new Color("#C5221F"), new Color("#F28B82")),
    badge:  Color.dynamic(new Color("#E8F0FE"), new Color("#17223B"))
};
const SP   = { xs: 2, sm: 6, md: 9, lg: 13 };
const FONT = { head: 14, headSm: 12, sub: 10, name: 12.5, spec: 9, price: 13.5, delta: 10, badge: 9, foot: 10 };

const LAYOUT = {
    large:        [["gold","usd_inr"],["silver","eur_inr"],["copper","cny_inr"],["brass","aed_inr"],["zinc","steel"]],
    mediumMetals: [["gold","silver"],["copper","brass"]],
    mediumFx:     [["usd_inr","eur_inr"],["cny_inr","aed_inr"]],
    smallMetals:  [["gold"],["silver"]],
    smallFx:      [["usd_inr"],["eur_inr"]]
};

const log = function() {
    if (DEBUG) {
        console.log.apply(console, arguments);
    }
};

function withTimeout(promise, ms, label) {
    let t;
    const timer = new Promise(function(resolve, reject) {
        t = setTimeout(function() { reject(new Error(label + " timed out")); }, ms);
    });
    return Promise.race([promise, timer]).finally(function() {
        clearTimeout(t);
    });
}

function storePath() {
    const fm = FileManager.local();
    return fm.joinPath(fm.documentsDirectory(), BRAND.storeFile);
}

function saveStore(obj) {
    const fm = FileManager.local();
    const p  = storePath();
    const tmp = p + ".tmp";
    try {
        fm.writeString(tmp, JSON.stringify(obj));
        if (fm.fileExists(p)) {
            fm.remove(p);
        }
        fm.move(tmp, p);
    } catch (e) {
        log("cache write failed", e);
        try {
            if (fm.fileExists(tmp)) {
                fm.remove(tmp);
            }
        } catch (_) {}
    }
}

function loadStore() {
    const fm = FileManager.local();
    const p  = storePath();
    try {
        if (!fm.fileExists(p)) {
            return null;
        }
        return JSON.parse(fm.readString(p));
    } catch (e) {
        log("cache read failed", e);
        return null;
    }
}

function normNum(v, def) {
    const n = Number(v);
    return Number.isFinite(n) ? n : def;
}

async function loadIcon() {
    const fm = FileManager.local();
    const path = fm.joinPath(fm.documentsDirectory(), BRAND.cacheFile);
    try {
        if (fm.fileExists(path)) {
            const mtime = fm.modificationDate(path);
            const ageMs = mtime ? Date.now() - mtime.getTime() : Infinity;
            if (ageMs < 7 * 864e5) {
                return fm.readImage(path);
            }
        }
    } catch (e) {
        log("icon read error", e);
    }

    try {
        const r = new Request(BRAND.icon);
        r.timeoutInterval = 3;
        const img = await withTimeout(r.loadImage(), 3500, "icon");
        if (img) {
            try {
                fm.writeImage(path, img);
            } catch (e) {
                log("icon write failed", e);
            }
            return img;
        }
    } catch (e) {
        log("icon fetch failed", e);
    }

    try {
        if (fm.fileExists(path)) {
            return fm.readImage(path);
        }
    } catch (e) {}
    return null;
}

/** Map API v4.1 payload → { gold: {base, change}, usd_inr: {...}, ... } */
function normalize(json) {
    const out = {};
    if (!json || typeof json !== "object") {
        return out;
    }
    const items = json.items || {};
    for (let i = 0; i < METAL_KEYS.length; i++) {
        const k = METAL_KEYS[i];
        const row = items[k];
        if (!row || typeof row !== "object") {
            continue;
        }
        // Prefer basePrice (ex-GST); accept base / price aliases
        const base = normNum(row.basePrice != null ? row.basePrice : (row.base != null ? row.base : row.price), NaN);
        if (!Number.isFinite(base)) {
            continue;
        }
        out[k] = {
            base: base,
            change: normNum(row.change, 0),
            // If API already included GST total, keep for optional use
            totalPrice: normNum(row.totalPrice != null ? row.totalPrice : row.total, NaN)
        };
    }
    const fx = json.fx || {};
    const map = { usd_inr: "USDINR", eur_inr: "EURINR", cny_inr: "CNYINR", aed_inr: "AEDINR" };
    const keys = Object.keys(map);
    for (let i = 0; i < keys.length; i++) {
        const sk = keys[i];
        const ak = map[sk];
        const row = fx[ak] || fx[sk] || fx[ak.toLowerCase()];
        if (!row || typeof row !== "object") {
            continue;
        }
        const base = normNum(row.price != null ? row.price : (row.base != null ? row.base : row.basePrice), NaN);
        if (!Number.isFinite(base)) {
            continue;
        }
        out[sk] = {
            base: base,
            change: normNum(row.change, 0),
            totalPrice: NaN
        };
    }
    return out;
}

function calc(raw) {
    const result = {};
    const stale = [];
    for (let i = 0; i < ALL_KEYS.length; i++) {
        const k = ALL_KEYS[i];
        const src  = raw[k];
        const base = src ? Number(src.base) : Number(FALLBACK[k].base);
        const chg  = src ? Number(src.change || 0) : Number(FALLBACK[k].change || 0);
        if (!src) {
            stale.push(k);
        }

        const tax   = TAX[k] || 0;
        const isFx  = k.indexOf("inr") !== -1;
        const isCu  = k === "copper" || k === "brass" || k === "zinc";
        // Prefer server totalPrice when present (already incl. GST)
        let total;
        if (src && Number.isFinite(src.totalPrice)) {
            total = Number(src.totalPrice);
        } else {
            total = base * (1 + tax);
        }

        result[k] = {
            total:  (isFx || isCu) ? Number(total.toFixed(2)) : Math.round(total),
            change: Number(chg.toFixed(2))
        };
    }
    return { items: result, stale: stale };
}

function readEtag(req) {
    try {
        const h = req.response && req.response.headers;
        if (!h) {
            return null;
        }
        return h["Etag"] || h["etag"] || h["ETag"] || null;
    } catch (_) {
        return null;
    }
}

function readStatusCode(req) {
    try {
        if (req.response && req.response.statusCode != null) {
            return Number(req.response.statusCode);
        }
    } catch (_) {}
    return 0;
}

async function getData() {
    const cached = loadStore();

    try {
        const req = new Request(BRAND.feed);
        req.timeoutInterval = 8;
        req.headers = {
            "Accept": "application/json",
            "User-Agent": "Scriptable-MetalsWidget/18.5"
        };
        if (cached && cached.etag) {
            req.headers["If-None-Match"] = cached.etag;
        }

        // loadJSON throws on 304 empty body on some iOS builds — catch and use cache
        let json = null;
        try {
            json = await withTimeout(req.loadJSON(), 9000, "feed");
        } catch (loadErr) {
            const code = readStatusCode(req);
            log("loadJSON", loadErr, "status", code);
            if (code === 304 && cached && cached.items) {
                const copy = Object.assign({}, cached);
                copy.status = "CACHED";
                return copy;
            }
            throw loadErr;
        }

        const code = readStatusCode(req);
        if (code === 304 && cached && cached.items) {
            const copy = Object.assign({}, cached);
            copy.status = "CACHED";
            return copy;
        }

        if (json && typeof json === "object") {
            const normalized = normalize(json);
            const metalCount = METAL_KEYS.filter(function(k) { return !!normalized[k]; }).length;
            const fxCount    = FX_KEYS.filter(function(k) { return !!normalized[k]; }).length;
            // v18.5: accept partial (was rejecting any incomplete set → perpetual OFFLINE)
            const usable = metalCount >= 1 || fxCount >= 1;

            if (usable) {
                const parsed = calc(normalized);
                const etag = readEtag(req);
                let status = "LIVE";
                const apiSt = String(json.status || "").toUpperCase();
                if (apiSt === "CACHED" || apiSt === "PARTIAL") {
                    status = apiSt === "PARTIAL" ? "LIVE" : "CACHED";
                }
                if (metalCount < METAL_KEYS.length || fxCount < FX_KEYS.length) {
                    // Still show live data we have; missing keys flagged stale via calc()
                    status = "LIVE";
                }
                const payload = {
                    status: status,
                    time:   json.timestamp || json.formattedTime || new Date().toISOString(),
                    etag:   etag || (cached && cached.etag) || null,
                    items:  parsed.items,
                    stale:  parsed.stale,
                    metalCount: metalCount,
                    fxCount: fxCount
                };
                saveStore(payload);
                return payload;
            }
            log("empty normalize", Object.keys(normalized), "keys in items", json.items && Object.keys(json.items));
        }
    } catch (e) {
        log("fetch error", e);
    }

    if (cached && cached.items) {
        const copy = Object.assign({}, cached);
        copy.status = "CACHED";
        return copy;
    }

    const fb = calc({});
    return {
        status: "OFFLINE",
        time:   new Date().toISOString(),
        items:  fb.items,
        stale:  fb.stale
    };
}

function money(key, val) {
    const is2dp = key.indexOf("inr") !== -1 || key === "copper" || key === "brass" || key === "zinc";
    const decimals = is2dp ? 2 : 0;
    return "₹" + Number(val).toLocaleString("en-IN", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function delta(v) {
    const n = Number(v) || 0;
    if (Math.abs(n) < 0.005) {
        return { text: "— 0.00%", color: C.dim };
    }
    const up = n > 0;
    return {
        text: (up ? "▲ +" : "▼ ") + Math.abs(n).toFixed(2) + "%",
        color: up ? C.green : C.red
    };
}

function clock(iso) {
    try {
        const d = iso ? new Date(iso) : new Date();
        if (isNaN(d.getTime()) && typeof iso === "string" && iso.indexOf(":") !== -1) {
            return String(iso);
        }
        return new Intl.DateTimeFormat(ACTIVE_LOCALE, {
            timeZone: IST_ZONE,
            hour: "2-digit", minute: "2-digit", hour12: true
        }).format(isNaN(d.getTime()) ? new Date() : d);
    } catch (e) {
        return "--:--";
    }
}

function symbol(name) {
    const candidates = [name, "circle.fill", "circle"];
    for (let i = 0; i < candidates.length; i++) {
        try {
            const s = SFSymbol.named(candidates[i]);
            if (s && s.image) {
                return s;
            }
        } catch (_) {}
    }
    return null;
}

function addHeader(w, status, small) {
    const row = w.addStack();
    row.layoutHorizontally();
    row.centerAlignContent();

    const left = row.addStack();
    left.layoutVertically();

    const t = left.addText(T.heading);
    t.font = Font.boldSystemFont(small ? FONT.headSm : FONT.head);
    t.textColor = C.text;

    if (!small) {
        const s = left.addText(T.sub);
        s.font = Font.mediumSystemFont(FONT.sub);
        s.textColor = C.dim;
    }

    row.addSpacer();

    const badge = row.addStack();
    badge.setPadding(3, 8, 3, 8);
    badge.cornerRadius = 5;
    badge.backgroundColor = C.badge;

    let word = T.offline;
    let col = C.dim;
    if (status === "LIVE") { word = T.live; col = C.green; }
    else if (status === "CACHED") { word = T.cached; col = C.dim; }
    else if (status === "ERROR") { word = T.error; col = C.red; }

    const b = badge.addText("● " + word);
    b.font = Font.boldSystemFont(FONT.badge);
    b.textColor = col;
}

function addCard(parent, key, data, staleSet, scale) {
    const info = T.assets[key];
    if (!info) {
        return;
    }
    const m = data[key] || { total: 0, change: 0 };
    const isStale = staleSet.has(key);
    const s = scale || 1;

    const card = parent.addStack();
    card.layoutHorizontally();
    card.centerAlignContent();
    card.setPadding(8, 10, 8, 10);
    card.cornerRadius = 10;
    card.backgroundColor = C.card;
    card.borderColor = C.border;
    card.borderWidth = 0.5;

    const left = card.addStack();
    left.layoutVertically();

    const top = left.addStack();
    top.layoutHorizontally();
    top.centerAlignContent();

    const sym = symbol(info.icon);
    if (sym) {
        const img = top.addImage(sym.image);
        img.imageSize = new Size(12 * s, 12 * s);
        img.tintColor = new Color(info.color);
        top.addSpacer(5);
    } else {
        const dot = top.addText("●");
        dot.font = Font.systemFont(10 * s);
        dot.textColor = new Color(info.color);
        top.addSpacer(5);
    }

    const name = top.addText(info.name);
    name.font = Font.boldSystemFont(FONT.name * s);
    name.textColor = isStale ? C.dim : C.text;
    name.lineLimit = 1;

    if (isStale) {
        const warn = top.addText(" ⚠");
        warn.font = Font.systemFont(9 * s);
        warn.textColor = C.dim;
    }

    left.addSpacer(SP.xs);
    const spec = left.addText(info.spec);
    spec.font = Font.mediumSystemFont(FONT.spec * s);
    spec.textColor = C.dim;
    spec.lineLimit = 1;

    card.addSpacer();

    const right = card.addStack();
    right.layoutVertically();

    const priceRow = right.addStack();
    priceRow.layoutHorizontally();
    priceRow.addSpacer();
    const price = priceRow.addText(money(key, m.total));
    price.font = Font.boldSystemFont(FONT.price * s);
    price.textColor = isStale ? C.dim : C.text;

    right.addSpacer(SP.xs);

    const d = delta(m.change);
    const chgRow = right.addStack();
    chgRow.layoutHorizontally();
    chgRow.addSpacer();
    const chg = chgRow.addText(d.text);
    chg.font = Font.boldSystemFont(FONT.delta * s);
    chg.textColor = isStale ? C.dim : d.color;
}

function addFooter(w, time, icon, small) {
    w.addSpacer(SP.sm);
    const row = w.addStack();
    row.layoutHorizontally();
    row.centerAlignContent();
    row.url = BRAND.link;

    if (icon) {
        const img = row.addImage(icon);
        img.imageSize = new Size(small ? 12 : 14, small ? 12 : 14);
        row.addSpacer(5);
    }

    const lbl = row.addText(small ? BRAND.shortLabel : BRAND.label);
    lbl.font = Font.mediumSystemFont(small ? FONT.spec : FONT.foot);
    lbl.textColor = C.dim;

    row.addSpacer();
    const clk = row.addText(clock(time));
    clk.font = Font.mediumSystemFont(FONT.spec);
    clk.textColor = C.dim;
}

function renderRows(w, rows, data, staleSet, scale, gap) {
    rows.forEach(function(row, i) {
        const r = w.addStack();
        r.layoutHorizontally();
        row.forEach(function(key, j) {
            addCard(r, key, data, staleSet, scale);
            if (j < row.length - 1) {
                r.addSpacer(gap);
            }
        });
        if (i < rows.length - 1) {
            w.addSpacer(SP.sm);
        }
    });
}

function smallLayout(w, data, icon) {
    w.setPadding(11, 13, 11, 13);
    addHeader(w, data.status, true);
    w.addSpacer(SP.sm);
    const rows = IS_PAGE_2 ? LAYOUT.smallFx : LAYOUT.smallMetals;
    renderRows(w, rows, data.items, new Set(data.stale || []), 1.0, SP.sm);
    w.addSpacer();
    addFooter(w, data.time, icon, true);
}

function mediumLayout(w, data, icon) {
    w.setPadding(11, 13, 11, 13);
    addHeader(w, data.status, false);
    w.addSpacer(SP.sm);
    const rows = IS_PAGE_2 ? LAYOUT.mediumFx : LAYOUT.mediumMetals;
    renderRows(w, rows, data.items, new Set(data.stale || []), 0.95, SP.sm);
    w.addSpacer();
    addFooter(w, data.time, icon, false);
}

function largeLayout(w, data, icon) {
    w.setPadding(13, 15, 11, 15);
    addHeader(w, data.status, false);
    w.addSpacer(SP.md);
    renderRows(w, LAYOUT.large, data.items, new Set(data.stale || []), 0.98, SP.sm);
    w.addSpacer();
    addFooter(w, data.time, icon, false);
}

function errorWidget(err) {
    const w = new ListWidget();
    const g = new LinearGradient();
    g.locations = [0, 1];
    g.colors = [C.bg1, C.bg2];
    w.backgroundGradient = g;
    w.url = BRAND.link;
    w.setPadding(13, 15, 13, 15);

    const title = w.addText("⚠ " + T.heading);
    title.font = Font.boldSystemFont(FONT.head);
    title.textColor = C.red;

    w.addSpacer(SP.sm);
    const errText = (err && err.message) ? err.message : String(err || "Unknown error");
    const msg = w.addText(errText);
    msg.font = Font.systemFont(10);
    msg.textColor = C.dim;
    msg.lineLimit = 4;

    w.addSpacer();
    const foot = w.addText(BRAND.shortLabel + " • " + clock(new Date().toISOString()));
    foot.font = Font.mediumSystemFont(FONT.spec);
    foot.textColor = C.dim;

    w.refreshAfterDate = new Date(Date.now() + UPDATE_MINUTES * 60 * 1000);
    return w;
}

async function create(size) {
    const results = await Promise.all([
        getData().catch(function(e) { log("getData error", e); return null; }),
        loadIcon().catch(function() { return null; })
    ]);

    const data = results[0];
    const icon = results[1];

    const safe = data || {
        status: "OFFLINE",
        time: new Date().toISOString(),
        items: calc({}).items,
        stale: ALL_KEYS
    };

    const w = new ListWidget();
    const g = new LinearGradient();
    g.locations = [0, 1];
    g.colors = [C.bg1, C.bg2];
    w.backgroundGradient = g;
    w.url = BRAND.link;
    w.refreshAfterDate = new Date(Date.now() + UPDATE_MINUTES * 60 * 1000);

    const family = size || config.widgetFamily || "large";
    if (family === "small") {
        smallLayout(w, safe, icon);
    } else if (family === "medium") {
        mediumLayout(w, safe, icon);
    } else {
        largeLayout(w, safe, icon);
    }

    return w;
}

async function run() {
    try {
        if (config.runsInWidget) {
            const widget = await create(config.widgetFamily);
            Script.setWidget(widget);
        } else {
            // Debug: log feed once
            try {
                const probe = new Request(BRAND.feed);
                probe.timeoutInterval = 8;
                probe.headers = { Accept: "application/json" };
                const j = await probe.loadJSON();
                const n = normalize(j);
                log("probe status", j && j.status, "metals", Object.keys(n).filter(function(k){ return METAL_KEYS.indexOf(k)>=0; }).length, "fx", Object.keys(n).filter(function(k){ return FX_KEYS.indexOf(k)>=0; }).length);
            } catch (pe) {
                log("probe failed", pe);
            }

            const a = new Alert();
            a.title = "Markets & Metals";
            a.message = "Choose preview size";
            a.addAction("Large");
            a.addAction("Medium");
            a.addAction("Small");
            a.addCancelAction("Cancel");
            const i = await a.presentSheet();
            if (i === -1) {
                Script.complete();
                return;
            }
            const sizes = ["large", "medium", "small"];
            const widget = await create(sizes[i]);
            if (sizes[i] === "small") {
                await widget.presentSmall();
            } else if (sizes[i] === "medium") {
                await widget.presentMedium();
            } else {
                await widget.presentLarge();
            }
        }
    } catch (e) {
        log("run failed", e);
        if (config.runsInWidget) {
            Script.setWidget(errorWidget(e));
        } else {
            const a = new Alert();
            a.title = "Widget error";
            a.message = (e && e.message) ? e.message : String(e);
            a.addCancelAction("OK");
            await a.presentAlert();
        }
    }
    Script.complete();
}

run();
