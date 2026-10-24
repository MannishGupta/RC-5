<?php
/**
 * Maintenance suite — CCleaner-style professional UI
 * Analyze (scan) → review categories → Clean / Run full optimise
 * APIs unchanged: runOptimise() on parent Alpine, cleanup_scan / cleanup_apply
 */
if (!defined('BASE_PATH')) {
    exit;
}
$optPanelCompact = $optPanelCompact ?? false;
?>
<div class="rc-cc-shell rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">

  <!-- Title bar -->
  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-11 h-11 rounded-xl bg-[#1B75D0] text-white flex items-center justify-center shadow-md shrink-0" aria-hidden="true">
        <i class="fa-solid fa-broom text-lg"></i>
      </div>
      <div class="min-w-0">
        <h2 class="<?= $optPanelCompact ? 'text-base' : 'text-lg' ?> font-black text-slate-900 tracking-tight">Maintenance suite</h2>
        <p class="text-[11px] text-slate-500 mt-0.5">Analyze · Clean · Optimise — professional cleaner workflow</p>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="tools/backup.php"
         class="h-10 px-3 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-blue-400 inline-flex items-center gap-2">
        <i class="fa-solid fa-box-archive text-slate-500" aria-hidden="true"></i> Backup first
      </a>
      <button type="button" @click="runOptimise()" :disabled="optLoading"
              class="h-10 px-4 rounded-lg bg-[#1B75D0] hover:bg-[#155fa8] text-white text-xs font-bold shadow-sm inline-flex items-center gap-2 disabled:opacity-60">
        <i class="fa-solid fa-play" x-show="!optLoading" aria-hidden="true"></i>
        <i class="fa-solid fa-circle-notch fa-spin" x-show="optLoading" x-cloak aria-hidden="true"></i>
        <span x-text="optLoading ? 'Working…' : 'Clean & optimise'"></span>
      </button>
    </div>
  </div>

  <!-- Progress strip -->
  <div class="h-1 bg-slate-100" role="progressbar" :aria-valuenow="optLoading ? 70 : (optLogs.length ? 100 : 0)" aria-valuemin="0" aria-valuemax="100">
    <div class="h-full bg-[#1B75D0] transition-all duration-500"
         :style="optLoading ? 'width:70%' : (optLogs.length ? 'width:100%' : 'width:0%')"></div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[320px]" x-data="cleanupScanner()">
    <!-- Left: cleaner categories -->
    <aside class="lg:col-span-4 border-b lg:border-b-0 lg:border-r border-slate-200 bg-slate-50/80">
      <div class="px-4 py-3 border-b border-slate-200">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Cleaner</p>
        <p class="text-xs text-slate-600 mt-0.5">Select what to review, then Analyze.</p>
      </div>
      <ul class="p-3 space-y-1.5 text-sm" role="list">
        <template x-if="!result || !result.groups || !result.groups.length">
          <div class="space-y-1.5">
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-blue-100 text-blue-700"><i class="fa-solid fa-database"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">System &amp; schema</span>
                <span class="rc-cc-cat__d">Normalise data · rebuild indexes</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-amber-100 text-amber-700"><i class="fa-solid fa-bolt"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">Cache &amp; OPcache</span>
                <span class="rc-cc-cat__d">App cache · PHP opcode</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-slate-200 text-slate-700"><i class="fa-solid fa-user-clock"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">Expired sessions</span>
                <span class="rc-cc-cat__d">Idle login files &gt; 14 days</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-rose-100 text-rose-700"><i class="fa-solid fa-trash-can"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">Junk &amp; temp files</span>
                <span class="rc-cc-cat__d">.bak · OS junk · copies</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-violet-100 text-violet-700"><i class="fa-solid fa-image"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">Orphan media</span>
                <span class="rc-cc-cat__d">Unreferenced images (review)</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
            <li class="rc-cc-cat">
              <span class="rc-cc-cat__icon bg-emerald-100 text-emerald-700"><i class="fa-solid fa-scroll"></i></span>
              <span class="rc-cc-cat__body">
                <span class="rc-cc-cat__t">Logs &amp; weekly backup</span>
                <span class="rc-cc-cat__d">Rotate logs · Sunday archive</span>
              </span>
              <i class="fa-solid fa-circle-check text-slate-300" aria-hidden="true"></i>
            </li>
          </div>
        </template>
        <template x-for="g in (result && result.groups) ? result.groups : []" :key="g.key || g.label">
          <li class="rc-cc-cat" :class="groupAllSelected(g) && 'is-on'">
            <label class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer">
              <input type="checkbox" class="rc-cc-check"
                     :checked="groupAllSelected(g)"
                     @change="toggleGroup(g, $event.target.checked)">
              <span class="rc-cc-cat__icon"
                    :class="g.safety === 'safe' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                <i class="fa-solid" :class="g.safety === 'safe' ? 'fa-shield-halved' : 'fa-triangle-exclamation'" aria-hidden="true"></i>
              </span>
              <span class="rc-cc-cat__body min-w-0">
                <span class="rc-cc-cat__t truncate" x-text="g.label"></span>
                <span class="rc-cc-cat__d" x-text="(g.items || []).length + ' item(s) · ' + human(groupBytes(g))"></span>
              </span>
            </label>
            <button type="button" class="text-slate-400 hover:text-slate-700 p-1" @click="open[g.key || g.label] = !open[g.key || g.label]" :aria-expanded="!!open[g.key || g.label]">
              <i class="fa-solid fa-chevron-down text-xs transition" :class="open[g.key || g.label] && 'rotate-180'"></i>
            </button>
          </li>
          <template x-if="open[g.key || g.label]">
            <ul class="ml-8 mb-2 space-y-1 border-l border-slate-200 pl-3">
              <template x-for="it in (g.items || [])" :key="it.path">
                <li class="flex items-start gap-2 text-[11px] text-slate-600">
                  <input type="checkbox" class="rc-cc-check mt-0.5" :value="it.path" x-model="selected">
                  <span class="min-w-0 break-all" x-text="it.path"></span>
                  <span class="shrink-0 text-slate-400 tabular-nums" x-text="human(it.bytes || 0)"></span>
                </li>
              </template>
            </ul>
          </template>
        </template>
      </ul>
      <div class="px-4 py-3 border-t border-slate-200 text-[11px] text-slate-500">
        <span class="font-bold" :class="selectedCount ? 'text-emerald-700' : 'text-slate-400'">
          <span x-text="selectedCount"></span> selected · <span x-text="selectedHuman"></span>
        </span>
      </div>
    </aside>

    <!-- Right: workspace -->
    <div class="lg:col-span-8 flex flex-col">
      <!-- Summary cards -->
      <div class="grid grid-cols-3 gap-px bg-slate-200 border-b border-slate-200">
        <div class="bg-white px-4 py-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Status</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5"
             x-text="optLoading ? 'Cleaning…' : (scanning ? 'Analyzing…' : (optLogs.length ? 'Last run complete' : 'Ready'))"></p>
        </div>
        <div class="bg-white px-4 py-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">To clean</p>
          <p class="text-sm font-bold text-slate-800 mt-0.5" x-text="selectedCount ? selectedHuman : (result ? human((result.total_bytes)||0) : '—')"></p>
        </div>
        <div class="bg-white px-4 py-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Safety</p>
          <p class="text-sm font-bold text-emerald-700 mt-0.5">Review before delete</p>
        </div>
      </div>

      <!-- Notice -->
      <div class="px-4 py-3 bg-amber-50 border-b border-amber-100 text-[11px] text-amber-900 flex gap-2 items-start">
        <i class="fa-solid fa-circle-info mt-0.5 text-amber-600" aria-hidden="true"></i>
        <p>
          <strong>Clean &amp; optimise</strong> runs a full safe pass (cache, sessions, schema, logs).
          Use <strong>Analyze</strong> first if you want to pick individual junk files, then delete only what you selected.
          Always keep a recent backup.
        </p>
      </div>

      <!-- Results / console -->
      <div class="flex-1 p-4 bg-[#0B1220] text-slate-200 min-h-[220px] relative">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Activity</span>
          <button type="button" x-show="optLogs.length" @click="optLogs = []" class="ml-auto text-[10px] font-bold text-slate-500 hover:text-slate-300 uppercase tracking-wider">Clear</button>
        </div>

        <div x-show="!optLoading && !scanning && optLogs.length === 0 && !(result && result.groups && result.groups.length)"
             class="flex flex-col items-center justify-center py-10 text-center gap-2">
          <div class="w-14 h-14 rounded-2xl bg-slate-800 text-[#5BA3E8] flex items-center justify-center text-xl">
            <i class="fa-solid fa-broom" aria-hidden="true"></i>
          </div>
          <p class="text-sm font-semibold text-slate-300">Ready to analyze</p>
          <p class="text-xs text-slate-500 max-w-xs">Click <strong class="text-slate-300">Analyze</strong> to scan for junk, or <strong class="text-slate-300">Clean &amp; optimise</strong> for a full automatic pass.</p>
        </div>

        <div x-show="scanning || optLoading" x-cloak class="flex items-center gap-3 py-8 justify-center text-sm text-slate-400">
          <i class="fa-solid fa-circle-notch fa-spin text-[#5BA3E8]"></i>
          <span x-text="scanning ? 'Scanning tenants for cleanup candidates…' : 'Running optimiser across tenants…'"></span>
        </div>

        <div class="space-y-1.5 max-h-64 overflow-y-auto font-mono text-[12px] leading-relaxed" x-show="optLogs.length">
          <template x-for="(log, idx) in optLogs" :key="idx">
            <div class="flex gap-2 items-start">
              <span class="text-emerald-400/80 shrink-0">›</span>
              <span class="text-slate-300 break-words" x-text="log"></span>
            </div>
          </template>
        </div>

        <div class="mt-3 space-y-1.5 max-h-40 overflow-y-auto font-mono text-[12px]" x-show="applyLogs.length" x-cloak>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Delete log</p>
          <template x-for="(log, idx) in applyLogs" :key="'a'+idx">
            <div class="text-slate-400" x-text="log"></div>
          </template>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex flex-wrap items-center gap-2">
        <button type="button" @click="applyCleanup()"
                :disabled="!selectedCount || applying || scanning"
                class="h-10 px-4 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fa-solid fa-trash-can" x-show="!applying" aria-hidden="true"></i>
          <i class="fa-solid fa-circle-notch fa-spin" x-show="applying" x-cloak aria-hidden="true"></i>
          <span x-text="applying ? 'Removing…' : ('Clean selected (' + selectedCount + ')')"></span>
        </button>
        <button type="button" @click="scan()" :disabled="scanning || applying"
                class="h-10 px-3 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:border-blue-400">
          Re-analyze
        </button>
        <a href="?tab=team" class="ml-auto text-xs font-semibold text-[#1B75D0] hover:underline inline-flex items-center gap-1">
          Team slug locks <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<style>
  .rc-cc-cat {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.55rem 0.65rem; border-radius: 0.65rem;
    border: 1px solid transparent; background: transparent;
  }
  .rc-cc-cat.is-on { background: #eff6ff; border-color: #bfdbfe; }
  .rc-cc-cat__icon {
    width: 2rem; height: 2rem; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; flex-shrink: 0;
  }
  .rc-cc-cat__body { display: flex; flex-direction: column; min-width: 0; flex: 1; }
  .rc-cc-cat__t { font-size: 0.8rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
  .rc-cc-cat__d { font-size: 0.65rem; color: #64748b; line-height: 1.25; }
  .rc-cc-check {
    width: 1rem; height: 1rem; border-radius: 0.25rem;
    accent-color: #1B75D0; flex-shrink: 0;
  }
  html[data-theme="dark"] .rc-cc-shell,
  [data-theme="dark"] .rc-cc-shell { background: #0f172a; border-color: #1e293b; }
  html[data-theme="dark"] .rc-cc-cat__t,
  [data-theme="dark"] .rc-cc-cat__t { color: #f1f5f9; }
</style>

<script>
function cleanupScanner() {
  return {
    scanning: false,
    applying: false,
    result: null,
    selected: [],
    open: {},
    applyLogs: [],
    human(bytes) {
      const n = Number(bytes) || 0;
      if (n < 1024) return n + ' B';
      if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
      return (n / 1048576).toFixed(2) + ' MB';
    },
    groupBytes(g) {
      return (g.items || []).reduce((t, i) => t + (Number(i.bytes) || 0), 0);
    },
    get selectedCount() { return this.selected.length; },
    get selectedHuman() {
      let t = 0;
      const groups = (this.result && this.result.groups) ? this.result.groups : [];
      groups.forEach(g => (g.items || []).forEach(i => {
        if (this.selected.includes(i.path)) t += (Number(i.bytes) || 0);
      }));
      return this.human(t);
    },
    groupAllSelected(g) {
      const items = g.items || [];
      return items.length > 0 && items.every(i => this.selected.includes(i.path));
    },
    toggleGroup(g, on) {
      const paths = (g.items || []).map(i => i.path);
      this.selected = on
        ? [...new Set([...this.selected, ...paths])]
        : this.selected.filter(p => !paths.includes(p));
    },
    async scan() {
      this.scanning = true;
      this.applyLogs = [];
      this.selected = [];
      try {
        const csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf)
          || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('index.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'cleanup_scan', csrf_token: csrf })
        });
        const j = await r.json();
        this.result = j;
        // Auto-select safe groups
        const pick = [];
        (j.groups || []).forEach(g => {
          if (g.safety === 'safe') (g.items || []).forEach(i => pick.push(i.path));
        });
        this.selected = pick;
      } catch (e) {
        this.result = { groups: [], error: String(e && e.message ? e.message : e) };
        this.applyLogs = ['Analyze failed: ' + (e && e.message ? e.message : e)];
      } finally {
        this.scanning = false;
      }
    },
    async applyCleanup() {
      if (!this.selected.length) return;
      if (!confirm(
        'Permanently delete ' + this.selected.length + ' file(s) (' + this.selectedHuman + ')?\n\nThis cannot be undone. Prefer a backup first.'
      )) return;
      this.applying = true;
      this.applyLogs = [];
      try {
        const csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf)
          || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('index.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'cleanup_apply', paths: this.selected, csrf_token: csrf })
        });
        const j = await r.json();
        this.applyLogs = Array.isArray(j.logs) ? j.logs : [j.message || (j.ok ? 'Done' : 'Finished with issues')];
        // Refresh scan
        await this.scan();
      } catch (e) {
        this.applyLogs = ['Delete failed: ' + (e && e.message ? e.message : e)];
      } finally {
        this.applying = false;
      }
    }
  };
}
// Parent dashboard already defines runOptimise / optLoading / optLogs — no override here.
</script>
