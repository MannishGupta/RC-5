<?php
/**
 * Health & Tools — single operations hub
 * Sections: status · maintenance (optimiser + cleanup + cache) · reports · advanced
 */
if (!defined('BASE_PATH')) {
    exit;
}
$__isSa = !empty($isSuperAdmin);
$__isAd = !empty($isAdmin) || $__isSa;
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="space-y-6" x-data="valueHealth()" x-init="run()">

  <!-- Header -->
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Operations</p>
      <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Health &amp; tools</h1>
      <p class="text-sm text-slate-500 mt-1 max-w-xl">
        One place for system health, cleanup, cache refresh, backups, and printable reports.
        Prefer these buttons over typing file paths.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" @click="run()"
              class="h-9 px-3 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-blue-400">
        <i class="fa-solid fa-stethoscope mr-1" aria-hidden="true"></i> Recheck health
      </button>
      <button type="button" @click="backup()" x-show="isAdmin" x-cloak
              class="h-9 px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500">
        <i class="fa-solid fa-file-zipper mr-1" aria-hidden="true"></i> Quick backup
      </button>
    </div>
  </div>

  <p class="text-xs font-semibold"
     :class="msg.indexOf('fail')>=0 || msg.indexOf('FAIL')>=0 ? 'text-rose-700' : 'text-emerald-700'"
     x-text="msg"
     role="status"
     aria-live="polite"></p>

  <!-- 1. Health status chips (from valueHealth) -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" aria-labelledby="rc-health-status">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 id="rc-health-status" class="text-sm font-bold text-slate-800">System health</h2>
        <p class="text-[11px] text-slate-500">Quick read on storage, sessions, and tenant readiness.</p>
      </div>
    </div>
    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3" x-show="checks && checks.length" x-cloak>
      <template x-for="c in checks" :key="c.id || c.label">
        <div class="rounded-xl border px-3 py-3"
             :class="c.ok === false ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50'">
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500" x-text="c.label"></div>
          <div class="mt-1 text-sm font-bold" :class="c.ok === false ? 'text-rose-700' : 'text-slate-800'" x-text="c.detail || (c.ok === false ? 'Needs attention' : 'OK')"></div>
        </div>
      </template>
    </div>
    <div class="p-4 text-sm text-slate-500" x-show="!checks || !checks.length">
      Run <strong>Recheck health</strong> to refresh this summary.
    </div>
  </section>

  <?php if ($__isAd): ?>
  <!-- 2. Maintenance — single optimiser suite -->
  <section id="rc-maintain" class="scroll-mt-20 space-y-3" aria-labelledby="rc-health-maintain">
    <div class="px-1">
      <h2 id="rc-health-maintain" class="text-sm font-bold text-slate-800">Maintenance suite</h2>
      <p class="text-[11px] text-slate-500 mt-0.5">
        One run handles schema tidy-up, junk file removal, expired sessions, cache rebuild, and OPcache flush.
        Take a backup before destructive cleanup.
      </p>
    </div>
    <?php
      $optPanelCompact = false;
      $optPartial = __DIR__ . '/../partials/optimizer_panel.php';
      if (is_file($optPartial)) {
          require $optPartial;
      } else {
          echo '<div class="p-4 rounded-xl border border-amber-200 bg-amber-50 text-sm text-amber-900">Optimiser panel is missing on this host.</div>';
      }
    ?>
  </section>
  <?php endif; ?>

  <!-- 3. Reports — people / print / export -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" aria-labelledby="rc-health-reports">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
      <h2 id="rc-health-reports" class="text-sm font-bold text-slate-800">Reports</h2>
      <p class="text-[11px] text-slate-500 mt-0.5">Printable and downloadable outputs for people and compliance — not system maintenance.</p>
    </div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <a href="/tools/print_directory.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-print text-slate-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Print directory</span>
        <span class="rc-tool-tile__d">Team photo cards for notice boards</span>
      </a>
      <a href="/tools/export_team_csv.php" class="rc-tool-tile">
        <i class="fa-solid fa-file-csv text-slate-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Team CSV</span>
        <span class="rc-tool-tile__d">Spreadsheet of the directory</span>
      </a>
      <a href="/tools/export_vcf.php" class="rc-tool-tile">
        <i class="fa-solid fa-address-book text-emerald-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">vCard pack</span>
        <span class="rc-tool-tile__d">Import contacts into phones</span>
      </a>
      <a href="/blood_report.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-droplet text-rose-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Blood group roster</span>
        <span class="rc-tool-tile__d">Signed-in report for HR / admin</span>
      </a>
      <a href="?tab=statistics" class="rc-tool-tile">
        <i class="fa-solid fa-chart-pie text-indigo-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Statistics panel</span>
        <span class="rc-tool-tile__d">Counts and breakdowns inside the portal</span>
      </a>
      <a href="?tab=audit" class="rc-tool-tile">
        <i class="fa-solid fa-clipboard-list text-slate-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Audit ledger</span>
        <span class="rc-tool-tile__d">Who changed what (admin)</span>
      </a>
    </div>
  </section>

  <!-- 4. Tools — everyday links (not the optimiser) -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" aria-labelledby="rc-health-tools">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
      <h2 id="rc-health-tools" class="text-sm font-bold text-slate-800">Tools</h2>
      <p class="text-[11px] text-slate-500 mt-0.5">Handy links. Heavy cleanup stays in <strong>Maintenance suite</strong> above.</p>
    </div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <a href="/health.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-heart-pulse text-emerald-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Uptime probe</span>
        <span class="rc-tool-tile__d">Public JSON for external monitors</span>
      </a>
      <?php if ($__isAd): ?>
      <button type="button" @click="backup()" class="rc-tool-tile text-left w-full">
        <i class="fa-solid fa-file-zipper text-emerald-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Backup data zip</span>
        <span class="rc-tool-tile__d">Snapshot this organisation’s data</span>
      </button>
      <a href="/tools/backup.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-download text-blue-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Backup page</span>
        <span class="rc-tool-tile__d">Alternate download UI</span>
      </a>
      <a href="/tools/diagnostics.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-stethoscope text-indigo-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Host diagnostics</span>
        <span class="rc-tool-tile__d">PHP extensions, paths, limits</span>
      </a>
      <a href="#rc-maintain" class="rc-tool-tile">
        <i class="fa-solid fa-broom text-amber-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Cache &amp; cleanup</span>
        <span class="rc-tool-tile__d">Use Maintenance suite — one run does it all</span>
      </a>
      <?php endif; ?>
      <a href="?tab=team" class="rc-tool-tile">
        <i class="fa-solid fa-users text-blue-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Team directory</span>
        <span class="rc-tool-tile__d">People, cards, signatures</span>
      </a>
      <a href="?tab=numero" class="rc-tool-tile">
        <i class="fa-solid fa-wand-magic-sparkles text-violet-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Numerology</span>
        <span class="rc-tool-tile__d">Open the numero workspace</span>
      </a>
      <a href="/janam_patri.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-om text-amber-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Janam Patri</span>
        <span class="rc-tool-tile__d">Chart form (or from a team profile)</span>
      </a>
    </div>
  </section>

  <?php if ($__isSa): ?>
  <!-- 5. Super Admin only -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" aria-labelledby="rc-health-sa">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
      <h2 id="rc-health-sa" class="text-sm font-bold text-slate-800">Control plane</h2>
      <p class="text-[11px] text-slate-500 mt-0.5">Super Admin only — tenants, live monitor, brand assets.</p>
    </div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <a href="?tab=monitor" class="rc-tool-tile">
        <i class="fa-solid fa-heart-pulse text-rose-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Live monitor</span>
        <span class="rc-tool-tile__d">Infrastructure diagnostics</span>
      </a>
      <a href="?tab=tenants" class="rc-tool-tile">
        <i class="fa-solid fa-building-user text-slate-700" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Tenant setup</span>
        <span class="rc-tool-tile__d">Provision and map organisations</span>
      </a>
      <a href="?tab=brandlogos" class="rc-tool-tile">
        <i class="fa-solid fa-trademark text-blue-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Brand logos</span>
        <span class="rc-tool-tile__d">Master logo library</span>
      </a>
      <a href="?tab=access" class="rc-tool-tile">
        <i class="fa-solid fa-user-shield text-indigo-600" aria-hidden="true"></i>
        <span class="rc-tool-tile__t">Access mode</span>
        <span class="rc-tool-tile__d">Role / pack controls</span>
      </a>
    </div>
  </section>
  <?php endif; ?>

</div>

<style>
  .rc-tool-tile {
    display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;
    padding: 0.9rem 1rem; border-radius: 0.9rem; border: 1px solid #e2e8f0;
    background: #fff; text-decoration: none; color: inherit;
    transition: border-color .15s, box-shadow .15s;
    min-height: 5.5rem;
  }
  .rc-tool-tile:hover { border-color: #93c5fd; box-shadow: 0 4px 14px rgba(15,23,42,.06); }
  .rc-tool-tile i { font-size: 1.1rem; margin-bottom: 0.2rem; }
  .rc-tool-tile__t { font-size: 0.85rem; font-weight: 800; color: #0f172a; }
  .rc-tool-tile__d { font-size: 0.7rem; color: #64748b; line-height: 1.35; }
  html[data-theme="dark"] .rc-tool-tile,
  [data-theme="dark"] .rc-tool-tile { background: #0f172a; border-color: #1e293b; }
  html[data-theme="dark"] .rc-tool-tile__t,
  [data-theme="dark"] .rc-tool-tile__t { color: #f1f5f9; }
  html[data-theme="dark"] .rc-tool-tile__d,
  [data-theme="dark"] .rc-tool-tile__d { color: #94a3b8; }
</style>

<script>
document.addEventListener('alpine:init', () => {
  if (window.__valueHealthRegistered) return;
  window.__valueHealthRegistered = true;
  Alpine.data('valueHealth', () => ({
    msg: '',
    checks: [],
    isAdmin: !!(window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.auth && window.__DASHBOARD_STATE__.auth.isAdmin),
    async run() {
      this.msg = 'Checking…';
      try {
        const r = await fetch('api_value.php?action=health', { credentials: 'same-origin', cache: 'no-store' });
        const j = await r.json();
        this.checks = Array.isArray(j.checks) ? j.checks : (j.items || []);
        this.msg = j.ok === false ? (j.message || 'Health check reported issues') : (j.message || 'All checked areas look fine');
      } catch (e) {
        this.msg = 'Could not reach health API — try again';
        this.checks = [];
      }
    },
    async backup() {
      this.msg = 'Preparing backup…';
      try {
        const csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('api_value.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'backup', csrf_token: csrf })
        });
        const j = await r.json();
        if (j.download_url || j.url) {
          window.location.href = j.download_url || j.url;
          this.msg = 'Backup started';
        } else {
          this.msg = j.message || (j.ok ? 'Backup ready' : 'Backup failed');
        }
      } catch (e) {
        this.msg = 'Backup request failed';
      }
    }
  }));
});
// Deep-link from old ?tab=opt bookmarks
if (location.hash === '#rc-maintain' || new URLSearchParams(location.search).get('section') === 'maintain') {
  requestAnimationFrame(() => {
    const el = document.getElementById('rc-maintain');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}
</script>
