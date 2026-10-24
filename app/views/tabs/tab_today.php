<?php

declare(strict_types=1);
require_once __DIR__ . '/../partials/tw_page.php';
if (!defined('BASE_PATH')) {
    exit;
}

$h = static function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$company = is_array($viewData['company'] ?? null) ? $viewData['company'] : [];
$coName = trim((string)($company['name'] ?? 'Organisation'));
$team = is_array($viewData['team'] ?? null) ? $viewData['team'] : [];
if ($team === [] && class_exists('AppDB')) {
    $team = AppDB::read('team') ?: [];
}
$teamCount = is_array($team) ? count($team) : 0;
$events = is_array($viewData['events'] ?? null) ? $viewData['events'] : [];
if ($events === [] && class_exists('AppDB')) {
    $events = AppDB::read('events') ?: [];
}

// Celebrations next 14 days (birthday-style from events + DOB on team)
$upcoming = [];
$tz = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);
$end = $now->modify('+14 days');
foreach ($team as $m) {
    if (!is_array($m)) {
        continue;
    }
    $dob = trim((string)($m['dob'] ?? $m['date_of_birth'] ?? ''));
    if ($dob === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $mm)) {
        continue;
    }
    $month = (int)$mm[2];
    $day = (int)$mm[3];
    $thisYear = (int)$now->format('Y');
    foreach ([$thisYear, $thisYear + 1] as $y) {
        try {
            $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $month, $day), $tz);
        } catch (Throwable $e) {
            continue;
        }
        if ($d >= $now->setTime(0, 0) && $d <= $end) {
            $upcoming[] = [
                'kind' => 'Birthday',
                'name' => (string)($m['name'] ?? 'Team member'),
                'when' => $d->format('D, j M'),
                'sort' => $d->format('Y-m-d'),
            ];
            break;
        }
    }
}
foreach ($events as $ev) {
    if (!is_array($ev)) {
        continue;
    }
    $ed = trim((string)($ev['date'] ?? $ev['start'] ?? $ev['when'] ?? ''));
    if ($ed === '') {
        continue;
    }
    try {
        $d = new DateTimeImmutable(substr($ed, 0, 10), $tz);
    } catch (Throwable $e) {
        continue;
    }
    if ($d >= $now->setTime(0, 0) && $d <= $end) {
        $upcoming[] = [
            'kind' => (string)($ev['type'] ?? $ev['category'] ?? 'Event'),
            'name' => (string)($ev['title'] ?? $ev['name'] ?? 'Event'),
            'when' => $d->format('D, j M'),
            'sort' => $d->format('Y-m-d'),
        ];
    }
}
usort($upcoming, static fn($a, $b) => strcmp($a['sort'], $b['sort']));
$upcoming = array_slice($upcoming, 0, 8);

// Sales snapshot
$salesUnits = 0;
$salesGross = 0.0;
if (class_exists('SalesRegister') || is_file(BASE_PATH . '/app/SalesRegister.php')) {
    if (!class_exists('SalesRegister')) {
        require_once BASE_PATH . '/app/SalesRegister.php';
    }
    try {
        $rows = SalesRegister::all();
        $cm = $now->format('Y-m');
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $mk = (string)($r['month'] ?? $r['booking_month'] ?? '');
            if ($mk === '' && !empty($r['date'])) {
                $mk = substr((string)$r['date'], 0, 7);
            }
            if ($mk !== $cm) {
                continue;
            }
            $salesUnits++;
            $salesGross += (float)($r['gross'] ?? $r['amount'] ?? 0);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// Status snapshot (tenant status JSON if present)
$statusProjects = 0;
$statusUpdated = '';
$statusDataFile = (defined('DATA_PATH') ? DATA_PATH : '') . '/status/data.json';
if ($statusDataFile !== '/status/data.json' && is_file($statusDataFile)) {
    $raw = @file_get_contents($statusDataFile);
    $sj = $raw ? json_decode($raw, true) : null;
    if (is_array($sj)) {
        $towers = $sj['towers'] ?? $sj['projects'] ?? $sj['items'] ?? [];
        if (is_array($towers)) {
            $statusProjects = count($towers);
        }
        $statusUpdated = (string)($sj['updated'] ?? $sj['updated_at'] ?? $sj['last_update'] ?? '');
    }
}

// Tenant health (admin)
$health = [];
$logo = trim((string)($company['logo'] ?? $company['logo_url'] ?? ''));
$health[] = ['ok' => $coName !== '' && strcasecmp($coName, 'Organisation') !== 0, 'label' => 'Company name'];
$health[] = ['ok' => $logo !== '', 'label' => 'Primary logo'];
$health[] = ['ok' => $teamCount > 0, 'label' => 'Team roster (' . $teamCount . ')'];
$web = trim((string)($company['website'] ?? ''));
$health[] = ['ok' => $web !== '', 'label' => 'Website'];
$saPath = (defined('DATA_PATH') ? DATA_PATH : '') . '/config/google_sheets_sa.json';
$health[] = ['ok' => is_file($saPath), 'label' => 'Google Sheets key'];
$healthScore = 0;
foreach ($health as $hh) {
    if (!empty($hh['ok'])) {
        $healthScore++;
    }
}
$healthPct = (int)round(100 * $healthScore / max(1, count($health)));

$greeting = 'Good morning';
$hour = (int)$now->format('G');
if ($hour >= 12 && $hour < 17) {
    $greeting = 'Good afternoon';
} elseif ($hour >= 17) {
    $greeting = 'Good evening';
}
$userName = '';
if (!empty($rcUser['name'])) {
    $userName = (string)$rcUser['name'];
} elseif (!empty($_SESSION['rc_user']['name'])) {
    $userName = (string)$_SESSION['rc_user']['name'];
}

function rc_today_inr(float $n): string
{
    if (abs($n) >= 10000000) {
        return '₹' . number_format($n / 10000000, 2) . ' Cr';
    }
    if (abs($n) >= 100000) {
        return '₹' . number_format($n / 100000, 2) . ' L';
    }
    return '₹' . number_format($n, 0);
}
?>

<div class="rc-page w-full flex flex-col gap-4 rc-today" x-data="rcTodayHub()" x-init="init()">
  <!-- Live strip: clock · weather · AQI · celebrations (Today only) -->
  <div class="rc-top-widgets rc-today-widgets mb-1 gap-2">
    <?php
      $wxPartial = __DIR__ . '/../partials/public_live_widgets.php';
      if (is_file($wxPartial)) { require $wxPartial; }
      $bdayPartial = __DIR__ . '/../partials/value_birthday_strip.php';
      if (is_file($bdayPartial)) { require $bdayPartial; }
    ?>
  </div>

  <header class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-wider" style="color:var(--rc-ink-muted)"><?= $h($now->format('l, j F Y')) ?> · IST</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold mt-0.5 tracking-tight" style="color:var(--rc-ink)">
        <?= $h($greeting) ?><?= $userName !== '' ? ', ' . $h($userName) : '' ?>
      </h1>
      <p class="text-sm mt-1" style="color:var(--rc-ink-muted)"><?= $h($coName) ?> — today’s operating brief</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="?tab=team" class="rc-today-chip"><i class="fa-solid fa-users" aria-hidden="true"></i> Team</a>
      <a href="?tab=sales" class="rc-today-chip"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Sales</a>
      <a href="?tab=status" class="rc-today-chip"><i class="fa-solid fa-building" aria-hidden="true"></i> Status</a>
      <a href="?tab=metals" class="rc-today-chip"><i class="fa-solid fa-coins" aria-hidden="true"></i> Markets</a>
      <a href="?tab=choghadiya" class="rc-today-chip"><i class="fa-solid fa-sun" aria-hidden="true"></i> Choghadiya</a>
    </div>
  </header>

  <div class="rc-fluent-kpis">
    <div class="rc-fluent-kpi">
      <div class="label">Human capital</div>
      <div class="value"><?= (int)$teamCount ?></div>
      <a href="?tab=team" class="text-xs font-semibold mt-1 inline-block" style="color:var(--rc-accent)">Open directory →</a>
    </div>
    <div class="rc-fluent-kpi">
      <div class="label">Sales this month</div>
      <div class="value" style="font-size:1.05rem"><?= $h(rc_today_inr($salesGross)) ?></div>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)"><?= (int)$salesUnits ?> unit(s) · <?= $h($now->format('M Y')) ?></p>
    </div>
    <div class="rc-fluent-kpi">
      <div class="label">Construction</div>
      <div class="value"><?= (int)$statusProjects ?></div>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)"><?= $statusUpdated !== '' ? 'Updated ' . $h($statusUpdated) : 'Projects tracked' ?></p>
    </div>
    <div class="rc-fluent-kpi">
      <div class="label">Setup health</div>
      <div class="value"><?= (int)$healthPct ?>%</div>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)"><?= (int)$healthScore ?>/<?= count($health) ?> checks</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center gap-2"><i class="fa-solid fa-champagne-glasses" style="color:var(--rc-accent)" aria-hidden="true"></i> Upcoming (14 days)</h3>
      <?php if ($upcoming === []): ?>
        <p class="text-sm py-6 text-center" style="color:var(--rc-ink-muted)">No birthdays or events in the next two weeks.</p>
      <?php else: ?>
        <ul class="divide-y" style="border-color:var(--rc-border)">
          <?php foreach ($upcoming as $u): ?>
            <li class="flex justify-between gap-3 py-2 text-sm">
              <span class="font-semibold" style="color:var(--rc-ink)"><?= $h($u['name']) ?></span>
              <span class="shrink-0 text-xs font-bold" style="color:var(--rc-ink-muted)"><?= $h($u['kind']) ?> · <?= $h($u['when']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center justify-between gap-2">
        <span><i class="fa-solid fa-chart-simple" style="color:var(--rc-accent)" aria-hidden="true"></i> Markets snapshot</span>
        <a href="?tab=metals" class="text-xs font-semibold" style="color:var(--rc-accent)">Full board →</a>
      </h3>
      <div id="rc-today-metals" class="text-sm min-h-[120px]" style="color:var(--rc-ink-muted)">Loading commodity rates…</div>
      <div class="mt-3 pt-3 border-t" style="border-color:var(--rc-border)">
        <div class="flex justify-between items-center mb-1">
          <span class="text-xs font-bold uppercase tracking-wide" style="color:var(--rc-ink-muted)">Choghadiya</span>
          <a href="?tab=choghadiya" class="text-xs font-semibold" style="color:var(--rc-accent)">Open →</a>
        </div>
        <div id="rc-today-chog" class="text-sm" style="color:var(--rc-ink)">Loading…</div>
      </div>
    </section>
  </div>


  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center justify-between gap-2">
        <span><i class="fa-solid fa-helmet-safety" style="color:var(--rc-accent)" aria-hidden="true"></i> Construction</span>
        <a href="?tab=status" class="text-xs font-semibold" style="color:var(--rc-accent)">Open →</a>
      </h3>
      <p class="text-2xl font-bold tabular-nums mt-1" style="color:var(--rc-ink)"><?= (int)$statusProjects ?></p>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)"><?= $statusUpdated !== '' ? 'Last update: ' . $h($statusUpdated) : 'Tower / project progress' ?></p>
      <a href="/status/" class="inline-block mt-2 text-xs font-semibold" style="color:var(--rc-accent)">Public status page ↗</a>
    </section>
    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center justify-between gap-2">
        <span><i class="fa-solid fa-photo-film" style="color:var(--rc-accent)" aria-hidden="true"></i> Media Kit</span>
        <a href="?tab=mediakit" class="text-xs font-semibold" style="color:var(--rc-accent)">Open →</a>
      </h3>
      <?php
        $mkCount = 0;
        if (class_exists('AppDB')) {
          $mk = AppDB::read('mediakit') ?: AppDB::read('media_kit') ?: [];
          $mkCount = is_array($mk) ? count($mk) : 0;
        }
      ?>
      <p class="text-2xl font-bold tabular-nums mt-1" style="color:var(--rc-ink)"><?= (int)$mkCount ?></p>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)">Brand assets ready to share</p>
    </section>
    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center justify-between gap-2">
        <span><i class="fa-solid fa-calendar-days" style="color:var(--rc-accent)" aria-hidden="true"></i> Calendar</span>
        <a href="?tab=events" class="text-xs font-semibold" style="color:var(--rc-accent)">Open →</a>
      </h3>
      <?php
        $evCount = is_array($events) ? count($events) : 0;
      ?>
      <p class="text-2xl font-bold tabular-nums mt-1" style="color:var(--rc-ink)"><?= (int)$evCount ?></p>
      <p class="text-xs mt-1" style="color:var(--rc-ink-muted)">Logged events · see Upcoming above</p>
    </section>
    <section class="rc-fluent-chart-card">
      <h3 class="flex items-center gap-2"><i class="fa-solid fa-bolt" style="color:var(--rc-accent)" aria-hidden="true"></i> Quick actions</h3>
      <div class="flex flex-wrap gap-2 mt-2">
        <a href="?tab=team" class="rc-today-chip"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Directory</a>
        <a href="?tab=sales" class="rc-today-chip"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Sales MIS</a>
        <a href="?tab=mediakit" class="rc-today-chip"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Share asset</a>
        <a href="?tab=company" class="rc-today-chip"><i class="fa-solid fa-building" aria-hidden="true"></i> Org setup</a>
        <a href="?tab=ai" class="rc-today-chip"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> AI assist</a>
        <a href="/metals.php" target="_blank" rel="noopener" class="rc-today-chip"><i class="fa-solid fa-coins" aria-hidden="true"></i> Metals PWA</a>
      </div>
    </section>
    <section class="rc-fluent-chart-card md:col-span-2">
      <h3 class="flex items-center justify-between gap-2">
        <span><i class="fa-solid fa-sun" style="color:var(--rc-accent)" aria-hidden="true"></i> Auspicious window</span>
        <a href="?tab=choghadiya" class="text-xs font-semibold" style="color:var(--rc-accent)">Full day →</a>
      </h3>
      <div id="rc-today-chog-detail" class="text-sm mt-2" style="color:var(--rc-ink)">Loading Rahu / Shubh…</div>
    </section>
  </div>

  <?php if (!empty($isAdmin) || !empty($isSuperAdmin)): ?>
  <section class="rc-fluent-chart-card">
    <h3 class="flex items-center gap-2"><i class="fa-solid fa-heart-pulse" style="color:var(--rc-accent)" aria-hidden="true"></i> Tenant health</h3>
    <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 mt-2">
      <?php foreach ($health as $hh): ?>
        <li class="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg border" style="border-color:var(--rc-border);background:var(--rc-paper)">
          <i class="fa-solid <?= !empty($hh['ok']) ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-amber-600' ?>" aria-hidden="true"></i>
          <span style="color:var(--rc-ink)"><?= $h($hh['label']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs mt-3" style="color:var(--rc-ink-muted)">Complete Organisation Setup for logo, website, and Sheets live sync.</p>
  </section>
  <?php endif; ?>
</div>
<style>
.rc-today-chip {
  display: inline-flex; align-items: center; gap: 6px;
  height: 32px; padding: 0 12px; border-radius: 8px;
  border: 1px solid var(--rc-border, #e1dfdd);
  background: var(--rc-card, #fff); color: var(--rc-ink, #201f1e);
  font-size: 12px; font-weight: 600; text-decoration: none;
}
.rc-today-chip:hover { border-color: var(--rc-accent, #0078D4); color: var(--rc-accent, #0078D4); }
.rc-today-metal-row { display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid color-mix(in srgb, var(--rc-border) 70%, transparent); }
.rc-today-metal-row:last-child { border-bottom:0; }
</style>
<script>
window.rcTodayHub = function () {
  return {
    init() {
      var self = this;
      var run = function () { self.loadMetals(); self.loadChog(); self.loadChogDetail(); };
      if ('requestIdleCallback' in window) requestIdleCallback(run, { timeout: 2500 });
      else window.addEventListener('load', function () { setTimeout(run, 50); });
    },
    async loadMetals() {
      const el = document.getElementById('rc-today-metals');
      if (!el) return;
      try {
        const r = await fetch('/metals.php?format=json', { credentials: 'same-origin', cache: 'no-store' });
        const j = await r.json();
        const items = j.items || j.commodities || [];
        const list = Array.isArray(items) ? items.slice(0, 5) : [];
        if (!list.length) {
          el.textContent = 'Rates unavailable right now.';
          return;
        }
        const t = j.formattedTime || j.time || j.fetched_at || '';
        let html = list.map(it => {
          const name = it.name || it.label || it.key || '—';
          const price = it.totalPrice != null ? it.totalPrice : (it.price || it.total || '—');
          const chg = it.change != null ? Number(it.change) : null;
          const chgS = chg == null || isNaN(chg) ? '' : ((chg >= 0 ? '+' : '') + chg.toFixed(2) + '%');
          return '<div class="rc-today-metal-row"><span class="font-semibold" style="color:var(--rc-ink)">' +
            String(name).replace(/</g,'') + '</span><span class="tabular-nums">' +
            String(price) + (chgS ? ' <small style="color:var(--rc-ink-muted)">' + chgS + '</small>' : '') +
            '</span></div>';
        }).join('');
        if (t) html += '<p class="text-[11px] mt-2" style="color:var(--rc-ink-muted)">As of ' + String(t).replace(/</g,'') + ' IST</p>';
        el.innerHTML = html;
      } catch (e) {
        el.textContent = 'Could not load rates. Open Markets tab.';
      }
    },
    
    async loadChogDetail() {
      const el = document.getElementById('rc-today-chog-detail');
      if (!el) return;
      try {
        const r = await fetch('/choghadiya.php?format=json', { credentials: 'same-origin', cache: 'no-store' });
        const j = await r.json();
        const cur = j.current || {};
        const name = cur.name || cur.label || '—';
        const rahu = (j.rahu && (j.rahu.label || j.rahu.name || j.rahu.when)) || '';
        const ti = j.tithi || {};
        const tithi = ti.name || ti.label || '';
        const pak = ti.pakshaFull || ti.paksha || '';
        const shubh = j.shubh || j.amrit || j.auspicious || null;
        let html = '<div class="font-semibold text-base">Now · ' + String(name).replace(/</g,'') + '</div>';
        if (tithi) html += '<div class="text-xs mt-1" style="color:var(--rc-ink-muted)">Tithi · ' + String(tithi).replace(/</g,'')
          + (pak ? ' · ' + String(pak).replace(/</g,'') : '') + '</div>';
        if (rahu) html += '<div class="text-xs mt-1" style="color:var(--rc-ink-muted)">Rahu Kaal · ' + String(rahu).replace(/</g,'') + '</div>';
        if (shubh) html += '<div class="text-xs mt-1" style="color:var(--rc-ink-muted)">Shubh · ' + String(typeof shubh === 'object' ? (shubh.label || shubh.name || JSON.stringify(shubh)) : shubh).replace(/</g,'') + '</div>';
        el.innerHTML = html;
      } catch (e) {
        el.textContent = 'Open Choghadiya for full windows.';
      }
    },
    async loadChog() {
      const el = document.getElementById('rc-today-chog');
      if (!el) return;
      try {
        const r = await fetch('/choghadiya.php?format=json', { credentials: 'same-origin', cache: 'no-store' });
        const j = await r.json();
        const cur = j.current || {};
        const name = cur.name || cur.label || '—';
        const rahu = (j.rahu && (j.rahu.label || j.rahu.name)) || '';
        const ti = j.tithi || {};
        const tithi = ti.name || ti.label || '';
        const pak = ti.pakshaFull || ti.paksha || '';
        let line = '<strong>Now: ' + String(name).replace(/</g,'') + '</strong>';
        if (tithi) {
          line += '<br><span style="color:var(--rc-ink-muted);font-size:12px">Tithi: ' + String(tithi).replace(/</g,'')
            + (pak ? ' · ' + String(pak).replace(/</g,'') : '') + '</span>';
        }
        if (rahu) line += '<br><span style="color:var(--rc-ink-muted);font-size:12px">Rahu Kaal: ' + String(rahu).replace(/</g,'') + '</span>';
        el.innerHTML = line;
      } catch (e) {
        el.textContent = 'Open Choghadiya for live windows.';
      }
    }
  };
};
</script>
