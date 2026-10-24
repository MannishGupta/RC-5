<?php
// Version: 3.2.37
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

if (!class_exists('SalesRegister') && is_file(BASE_PATH . '/app/SalesRegister.php')) {
    require_once BASE_PATH . '/app/SalesRegister.php';
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sheetsCfg = class_exists('SalesRegister') ? SalesRegister::sheetsConfig() : ['enabled' => false, 'spreadsheets' => []];
$sheetsStatus = ['ok' => false, 'configured' => false, 'message' => ''];
$syncNote = '';
if (!class_exists('GoogleSheetsClient') && is_file(BASE_PATH . '/app/GoogleSheetsClient.php')) {
    require_once BASE_PATH . '/app/GoogleSheetsClient.php';
}
if (class_exists('GoogleSheetsClient')) {
    $sheetsStatus = GoogleSheetsClient::status();
}
// Auto-sync only when SA is ready — avoids repeating "Service account not configured" on every page load
if (
    class_exists('SalesRegister')
    && !empty($sheetsCfg['enabled'])
    && !empty($sheetsCfg['spreadsheets'])
    && !empty($sheetsStatus['ok'])
) {
    $syncRes = SalesRegister::syncFromGoogleSheets(false);
    if (!empty($syncRes['message']) && !empty($syncRes['ok'])) {
        $syncNote = (string)$syncRes['message'];
    }
}
$saReady = !empty($sheetsStatus['ok']);
$saOpen = !$saReady; // open Configure when key missing
$rows = class_exists('SalesRegister') ? SalesRegister::all() : [];

$agg = class_exists('SalesRegister') ? SalesRegister::aggregate($rows) : ['by_project' => [], 'by_month' => [], 'months' => [], 'current_month' => date('Y-m'), 'year' => date('Y')];
$projects = class_exists('SalesRegister') ? SalesRegister::PROJECTS : [];
$statuses = class_exists('SalesRegister') ? SalesRegister::STATUSES : [];
$canEdit = !empty($isAdmin) || !empty($isSuperAdmin);
$csrf = '';
if (class_exists('AppAuth')) {
    if (method_exists('AppAuth', 'csrfToken')) {
        $csrf = (string)AppAuth::csrfToken();
    } elseif (method_exists('AppAuth', 'csrf_token')) {
        $csrf = (string)AppAuth::csrf_token();
    }
}
if ($csrf === '' && !empty($_SESSION['csrf_token'])) {
    $csrf = (string)$_SESSION['csrf_token'];
}
if ($csrf === '' && !empty($_SESSION['csrf'])) {
    $csrf = (string)$_SESSION['csrf'];
}

// Chart series: last 12 months keys
$monthKeys = $agg['months'];
if (count($monthKeys) > 12) {
    $monthKeys = array_slice($monthKeys, -12);
}
if ($monthKeys === []) {
    $monthKeys = [$agg['current_month']];
}
$colors = ['#0078D4', '#0f766e', '#b45309', '#7c3aed'];
$seriesRevenue = [];
$seriesUnits = [];
$seriesArea = [];
foreach ($projects as $i => $p) {
    $rev = [];
    $un = [];
    $ar = [];
    foreach ($monthKeys as $mk) {
        $cell = $agg['by_month'][$mk][$p] ?? ['units' => 0, 'area' => 0.0, 'gross' => 0.0];
        $rev[] = (float)$cell['gross'];
        $un[] = (int)$cell['units'];
        $ar[] = (float)$cell['area'];
    }
    $seriesRevenue[] = ['name' => $p, 'color' => $colors[$i % 4], 'data' => $rev];
    $seriesUnits[] = ['name' => $p, 'color' => $colors[$i % 4], 'data' => $un];
    $seriesArea[] = ['name' => $p, 'color' => $colors[$i % 4], 'data' => $ar];
}

function rc_sales_inr(float $n): string
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
<style>
@media print {
  .no-print, .rc-sidebar, aside, header, footer, .mk-actions, button, .rc-chrome-btn { display: none !important; }
  .rc-sales-print, .rc-sales-print * { visibility: visible; }
  body { background: #fff !important; }
}
</style>
<div class="rc-page w-full flex flex-col gap-4 rc-sales-print" x-data="salesRegisterTab()" x-cloak>
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Markets</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">Monthly sales register</h1>
      <p class="rc-page-sub text-sm">Bookings, area and revenue</p>
    </div>
  </header>


  <!-- Google Sheets live link -->
  <div class="rounded-2xl border p-4 md:p-5 shadow-sm" style="border-color:var(--rc-border);background:var(--rc-card)">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-sm font-bold flex items-center gap-2" style="color:var(--rc-ink)">
          <i class="fa-brands fa-google text-[#4285F4]"></i> Live Google Sheets
        </h2>
        <p class="text-xs mt-1" style="color:var(--rc-ink-muted)">
          <?php if ($saReady): ?>
            <span class="font-semibold" style="color:#0f766e">Connected</span>
            <span class="font-mono text-[11px]" style="color:var(--rc-ink)"><?= $h($sheetsStatus['client_email'] ?? '') ?></span>
          <?php elseif (!empty($sheetsStatus['configured'])): ?>
            <span class="font-semibold" style="color:#b45309">Key on disk</span> —
            <span style="color:var(--rc-ink)"><?= $h($sheetsStatus['message'] ?? 'Cannot obtain token') ?></span>
          <?php else: ?>
            <span class="font-semibold" style="color:#b91c1c">Service account not set</span> —
            set the key in <a href="?tab=company#setup-sheets"><strong>Company setup → Google Sheets</strong></a>, or Configure below.
          <?php endif; ?>
        </p>
        <?php if ($syncNote !== ''): ?>
          <p class="text-xs mt-1 font-semibold" style="color:var(--rc-accent,#0078D4)"><?= $h($syncNote) ?></p>
        <?php endif; ?>
        <?php
          $le = trim((string)($sheetsCfg['last_error'] ?? ''));
          if ($le !== '' && stripos($le, 'Service account not configured') === false && stripos($le, 'not configured') === false):
        ?>
          <p class="text-xs mt-1" style="color:#b91c1c"><?= $h($le) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($canEdit): ?>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border" @click="showSheets = !showSheets">Configure</button>
        <button type="button" class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg text-white font-semibold" style="background:var(--rc-accent,#0078D4)" @click="syncSheets()" :disabled="syncing">
          <span x-text="syncing ? 'Syncing…' : 'Sync now'"></span>
        </button>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($canEdit): ?>
    <div x-show="showSheets" x-cloak class="mt-4 border-t border-slate-100 pt-4 space-y-3">
      <div class="rounded-xl border p-3 space-y-3" style="border-color:var(--rc-border);background:var(--rc-paper)">
        <p class="text-[11px] mb-2" style="color:var(--rc-ink-muted)">Preferred: <a href="?tab=company#setup-sheets" style="color:var(--rc-accent,#0078D4);font-weight:700">Company setup · step 6</a> (single place for all tenant keys). Duplicate upload here is optional.</p>
        <div class="text-xs font-bold flex items-center gap-2" style="color:var(--rc-ink)">
          <i class="fa-solid fa-key" style="color:#b45309"></i> Google service account
        </div>
        <ol class="text-[11px] leading-relaxed list-decimal pl-4 space-y-1" style="color:var(--rc-ink-muted)">
          <li>Google Cloud Console → create a <strong>service account</strong></li>
          <li>Enable <strong>Google Sheets API</strong> for the project</li>
          <li>Create a JSON key → upload the file <em>or</em> paste the JSON below</li>
          <li>Share each spreadsheet with the service-account email as <strong>Viewer</strong></li>
        </ol>
        <div class="flex flex-wrap items-center gap-2">
          <label class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border cursor-pointer" style="background:var(--rc-card);color:var(--rc-ink);border-color:var(--rc-border)">
            Upload JSON key
            <input type="file" accept=".json,application/json" class="hidden" @change="uploadSaJson($event)">
          </label>
          <span class="text-[11px]" style="color:var(--rc-ink-muted)" x-text="saHint"></span>
        </div>
        <div>
          <label class="block text-[11px] font-semibold mb-1" style="color:var(--rc-ink-muted)">Or paste JSON key</label>
          <textarea x-model="saJsonPaste" rows="4" class="w-full rounded-lg border px-2 py-1.5 text-[11px] font-mono" style="border-color:var(--rc-border);background:var(--rc-card);color:var(--rc-ink)" placeholder='{ "type": "service_account", "client_email": "...", "private_key": "..." }'></textarea>
          <button type="button" class="mt-2 rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border" style="border-color:var(--rc-border);color:var(--rc-ink)" @click="saveSaPaste()">Save pasted key</button>
        </div>
        <p class="text-[10px] font-mono m-0" style="color:var(--rc-ink-faint)">Stored privately: tenants/&lt;id&gt;/data/config/google_sheets_sa.json</p>
      </div>
      <div class="text-xs font-bold" style="color:var(--rc-ink)">Step 2 — Spreadsheet links</div>
      <label class="flex items-center gap-2 text-sm font-semibold">
        <input type="checkbox" x-model="sheets.enabled" class="rounded border-slate-300"> Enable live sync for this tenant
      </label>
      <p class="text-[11px] text-slate-500">Paste spreadsheet URLs or IDs (one per project workbook). Share each sheet with the service-account email as <strong>Viewer</strong>.</p>
      <template x-for="(s, idx) in sheets.spreadsheets" :key="idx">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
          <input class="md:col-span-5 border rounded-lg px-2 py-1.5 text-xs font-mono" placeholder="Spreadsheet URL or ID" x-model="s.url" @blur="const id=normalizeSheetUrl(s.url); if(id){ s.id=id; s.url='https://docs.google.com/spreadsheets/d/'+id; }">
          <input class="md:col-span-3 border rounded-lg px-2 py-1.5 text-xs" placeholder="Range e.g. Sheet1!A:Z" x-model="s.range">
          <input class="md:col-span-3 border rounded-lg px-2 py-1.5 text-xs" placeholder="Label" x-model="s.label">
          <button type="button" class="md:col-span-1 text-xs text-rose-700 font-bold" @click="sheets.spreadsheets.splice(idx,1)">✕</button>
        </div>
      </template>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="text-xs font-semibold" style="color:var(--rc-accent)" @click="sheets.spreadsheets.push({url:'',range:'A:Z',label:''})">+ Add spreadsheet</button>
        <button type="button" class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border" @click="saveSheetsCfg()">Save connection</button>
      </div>
      <p class="text-[11px] text-slate-500">Module visibility is controlled in Super Admin → Tenant Setup → modules matrix (<code>sales</code>).</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
        <h2 class="text-base font-bold text-slate-800">Monthly Sales Register</h2>
        <p class="text-xs text-slate-500 mt-0.5">Brook Phase 1 · Brook Phase 2 · Rivulet Phase 1 · U:Fairia · <?= $h($agg['year']) ?> YTD</p>
      </div>
      <?php if ($canEdit): ?>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border" @click="showForm = true; editId = ''">+ Add booking</button>
        <label class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border cursor-pointer">
          Import CSV
          <input type="file" accept=".csv,text/csv" class="hidden" @change="importCsv($event)">
        </label>
      </div>
      <?php endif; ?>
    </div>

  
  <!-- MIS period + KPIs -->
  <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5 shadow-sm">
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <h2 class="text-sm font-bold text-slate-800 mr-auto">MIS snapshot</h2>
      <select x-model="misYear" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white" @change="/* client filter uses rows */">
        <template x-for="y in yearOptions" :key="y"><option :value="y" x-text="y"></option></template>
      </select>
      <button type="button" class="rc-chrome-btn text-xs px-3 py-1.5 rounded-lg border" @click="window.print()">Print / PDF</button>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Units (YTD)</div>
        <div class="text-xl font-black tabular-nums text-slate-900 mt-1" x-text="misKpis.unitsYtd"></div>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Area (YTD sq.ft)</div>
        <div class="text-xl font-black tabular-nums text-slate-900 mt-1" x-text="Number(misKpis.areaYtd||0).toLocaleString('en-IN')"></div>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Gross (YTD)</div>
        <div class="text-xl font-black tabular-nums text-slate-900 mt-1" x-text="fmtInr(misKpis.grossYtd)"></div>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">This month</div>
        <div class="text-xl font-black tabular-nums text-slate-900 mt-1" x-text="fmtInr(misKpis.grossMonth)"></div>
      </div>
    </div>
    <p class="text-[11px] text-slate-500 mt-3">Projects: Brook Phase 1 · Brook Phase 2 · Rivulet Phase 1 · U:Fairia. Cancelled units excluded from totals. Import booking rows via Live Sheets or CSV; inventory / broker pivots can be mapped from Sec-12 workbooks into the register schema (unit, buyer, area, status).</p>
  </div>

  <!-- Summary snapshot -->
    <div class="rc-fluent-table-wrap overflow-x-auto">
      <table class="rc-fluent-table w-full text-sm">
        <thead>
          <tr class="text-left text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <th class="py-2 pr-2">Project</th>
            <th class="py-2 pr-2 text-right">Units (<?= $h(date('M Y')) ?>)</th>
            <th class="py-2 pr-2 text-right">Area (sq.ft)</th>
            <th class="py-2 pr-2 text-right">Gross (month)</th>
            <th class="py-2 text-right">YTD revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $tu = $ta = $tg = $ty = 0;
          foreach ($projects as $p):
              $c = $agg['by_project'][$p] ?? ['units_month'=>0,'area_month'=>0,'gross_month'=>0,'gross_ytd'=>0];
              $tu += (int)$c['units_month'];
              $ta += (float)$c['area_month'];
              $tg += (float)$c['gross_month'];
              $ty += (float)$c['gross_ytd'];
          ?>
          <tr class="border-b border-slate-100">
            <td class="py-2.5 pr-2 font-semibold text-slate-800"><?= $h($p) ?></td>
            <td class="py-2.5 pr-2 text-right tabular-nums"><?= (int)$c['units_month'] ?></td>
            <td class="py-2.5 pr-2 text-right tabular-nums"><?= number_format((float)$c['area_month'], 0) ?></td>
            <td class="py-2.5 pr-2 text-right tabular-nums font-medium"><?= $h(rc_sales_inr((float)$c['gross_month'])) ?></td>
            <td class="py-2.5 text-right tabular-nums font-bold text-slate-900"><?= $h(rc_sales_inr((float)$c['gross_ytd'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="bg-slate-50 font-bold">
            <td class="py-2.5 pr-2">Total</td>
            <td class="py-2.5 pr-2 text-right"><?= $tu ?></td>
            <td class="py-2.5 pr-2 text-right"><?= number_format($ta, 0) ?></td>
            <td class="py-2.5 pr-2 text-right"><?= $h(rc_sales_inr($tg)) ?></td>
            <td class="py-2.5 text-right"><?= $h(rc_sales_inr($ty)) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="rc-fluent-chart-card"><div class="rc-sales-chart rc-fluent-chart-canvas" style="height:180px" data-kind="revenue" aria-label="Revenue trend chart"></div>
      <div class="flex flex-wrap gap-2 mt-2 text-[10px]">
        <?php foreach ($seriesRevenue as $s): ?>
          <span class="inline-flex items-center gap-1"><i class="fa-solid fa-circle" style="color:<?= $h($s['color']) ?>;font-size:8px"></i><?= $h($s['name']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="rc-fluent-chart-card"><div class="rc-sales-chart rc-fluent-chart-canvas" style="height:180px" data-kind="units" aria-label="Units sold chart"></div>
    </div>
    <div class="rc-fluent-chart-card"><div class="rc-sales-chart rc-fluent-chart-canvas" style="height:180px" data-kind="area" aria-label="Area sold chart"></div>
    </div>
  </div>

  <!-- Register table -->
  <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5 shadow-sm">
    <div class="flex flex-wrap gap-2 items-center mb-3">
      <h3 class="text-sm font-bold text-slate-800 mr-auto">Sales register</h3>
      <select x-model="filterProject" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
        <option value="">All projects</option>
        <?php foreach ($projects as $p): ?><option value="<?= $h($p) ?>"><?= $h($p) ?></option><?php endforeach; ?>
      </select>
      <input type="month" x-model="filterMonth" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
    </div>
    <div class="rc-table-wrap overflow-x-auto">
      <table class="rc-fluent-table w-full text-xs md:text-sm">
        <thead>
          <tr class="text-left text-[10px] uppercase text-slate-500 border-b">
            <th class="py-2 pr-2">Month</th>
            <th class="py-2 pr-2">Project</th>
            <th class="py-2 pr-2">Unit</th>
            <th class="py-2 pr-2">Customer</th>
            <th class="py-2 pr-2">Type</th>
            <th class="py-2 pr-2 text-right">Area</th>
            <th class="py-2 pr-2 text-right">Gross</th>
            <th class="py-2 pr-2">Status</th>
            <?php if ($canEdit): ?><th class="py-2"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <template x-for="r in filtered" :key="r.id">
            <tr class="border-b border-slate-50 hover:bg-slate-50/80">
              <td class="py-2 pr-2 tabular-nums" x-text="r.month"></td>
              <td class="py-2 pr-2 font-medium" x-text="r.project"></td>
              <td class="py-2 pr-2" x-text="r.unit"></td>
              <td class="py-2 pr-2" x-text="r.customer"></td>
              <td class="py-2 pr-2" x-text="r.unit_type"></td>
              <td class="py-2 pr-2 text-right tabular-nums" x-text="Number(r.area||0).toLocaleString('en-IN')"></td>
              <td class="py-2 pr-2 text-right tabular-nums font-semibold" x-text="fmtInr(r.gross)"></td>
              <td class="py-2 pr-2"><span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100" x-text="r.status"></span></td>
              <?php if ($canEdit): ?>
              <td class="py-2 text-right whitespace-nowrap">
                <button type="button" class="text-[color:var(--rc-accent)] text-xs font-semibold mr-2" @click="editRow(r)">Edit</button>
                <button type="button" class="text-rose-700 text-xs font-semibold" @click="removeRow(r)">Del</button>
              </td>
              <?php endif; ?>
            </tr>
          </template>
        </tbody>
      </table>
      <p class="text-xs text-slate-500 mt-2" x-show="filtered.length === 0">No rows yet. Import a CSV or add a booking.</p>
    </div>
  </div>

  <!-- Modal form -->
  <?php if ($canEdit): ?>
  <div x-show="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.45)" @keydown.escape.window="showForm=false">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 border border-slate-200" @click.stop>
      <h3 class="text-sm font-bold mb-3" x-text="editId ? 'Edit booking' : 'Add booking'"></h3>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <label class="col-span-1">Month<input type="month" x-model="form.month" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-1">Project
          <select x-model="form.project" class="w-full border rounded-lg px-2 py-1.5 mt-1">
            <?php foreach ($projects as $p): ?><option><?= $h($p) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="col-span-1">Unit no.<input x-model="form.unit" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-1">Unit type<input x-model="form.unit_type" placeholder="2 BHK" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-2">Customer<input x-model="form.customer" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-1">Area (sq.ft)<input type="number" step="0.01" x-model="form.area" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-1">Status
          <select x-model="form.status" class="w-full border rounded-lg px-2 py-1.5 mt-1">
            <?php foreach ($statuses as $s): ?><option><?= $h($s) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="col-span-1">BSP (₹)<input type="number" step="1" x-model="form.bsp" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-1">Other charges<input type="number" step="1" x-model="form.other_charges" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
        <label class="col-span-2">Gross (₹)<input type="number" step="1" x-model="form.gross" class="w-full border rounded-lg px-2 py-1.5 mt-1"></label>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button type="button" class="rc-btn px-3 py-1.5 text-sm rounded-lg border border-slate-200" @click="showForm=false">Cancel</button>
        <button type="button" class="rc-btn rc-btn-primary px-3 py-1.5 text-sm rounded-lg font-semibold" style="background:var(--rc-accent,#0078D4)" @click="saveRow()">Save</button>
      </div>
      <p class="text-xs text-rose-600 mt-2" x-text="err" x-show="err"></p>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
window.salesRegisterTab = function () {
  const csrf = <?= json_encode($csrf) ?>;
  async function rcParseJson(r) {
    const t = await r.text();
    if (!t || !String(t).trim()) {
      throw new Error('Empty response (HTTP ' + r.status + '). Reload the page and try again.');
    }
    try { return JSON.parse(t); }
    catch (e) {
      throw new Error((t.slice(0, 180) || ('HTTP ' + r.status)).replace(/<[^>]+>/g, ' '));
    }
  }
  function normalizeSheetUrl(raw) {
    const s = String(raw || '').trim();
    if (!s) return '';
    if (/^[a-zA-Z0-9\-_]{20,}$/.test(s)) return s;
    let m = s.match(/\/spreadsheets\/d\/([a-zA-Z0-9\-_]+)/);
    if (m) return m[1];
    m = s.match(/[?&]id=([a-zA-Z0-9\-_]+)/);
    if (m) return m[1];
    return s;
  }
  function rcCsrfBody(action) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('csrf', csrf);
      body.set('csrf_token', csrf);
    body.set('csrf_token', csrf);
    body.set('_csrf', csrf);
    return body;
  }
  const seed = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
  const chartMonths = <?= json_encode(array_values($monthKeys)) ?>;
  const seriesRevenue = <?= json_encode($seriesRevenue) ?>;
  const seriesUnits = <?= json_encode($seriesUnits) ?>;
  const seriesArea = <?= json_encode($seriesArea) ?>;
  return {
    rows: seed.slice(),
    showSheets: <?= !empty($saOpen) ? 'true' : 'false' ?>,
    saHint: '',
    saJsonPaste: '',
    syncing: false,
    sheets: <?= json_encode([
      'enabled' => !empty($sheetsCfg['enabled']),
      'ttl_seconds' => (int)($sheetsCfg['ttl_seconds'] ?? 300),
      'spreadsheets' => array_map(static function ($s) {
          return [
              'url' => 'https://docs.google.com/spreadsheets/d/' . ($s['id'] ?? ''),
              'id' => $s['id'] ?? '',
              'range' => $s['range'] ?? 'A:Z',
              'label' => $s['label'] ?? '',
          ];
      }, $sheetsCfg['spreadsheets'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    filterProject: '',
    filterMonth: '',
    misYear: '<?= date('Y') ?>',
    showForm: false,
    editId: '',
    err: '',
    form: { month: '<?= date('Y-m') ?>', project: 'Brook Phase 1', unit: '', customer: '', unit_type: '', area: '', bsp: '', other_charges: '', gross: '', status: 'Booked' },
    get filtered() {
      return this.rows.filter(r => {
        if (this.filterProject && r.project !== this.filterProject) return false;
        if (this.filterMonth && r.month !== this.filterMonth) return false;
        return true;
      }).sort((a,b) => String(b.month).localeCompare(String(a.month)));
    },
    get yearOptions() {
      const ys = {};
      (this.rows || []).forEach(r => { const y = String(r.month || '').slice(0, 4); if (/^\d{4}$/.test(y)) ys[y] = true; });
      ys[String(new Date().getFullYear())] = true;
      return Object.keys(ys).sort().reverse();
    },
    get misKpis() {
      const y = String(this.misYear || '');
      const cm = new Date().toISOString().slice(0, 7);
      let unitsYtd = 0, areaYtd = 0, grossYtd = 0, grossMonth = 0;
      (this.rows || []).forEach(r => {
        if ((r.status || '') === 'Cancelled') return;
        const m = String(r.month || '');
        if (m.slice(0, 4) !== y) return;
        unitsYtd++;
        areaYtd += Number(r.area) || 0;
        grossYtd += Number(r.gross) || 0;
        if (m === cm) grossMonth += Number(r.gross) || 0;
      });
      return { unitsYtd, areaYtd, grossYtd, grossMonth };
    },
    fmtInr(n) {
      n = Number(n) || 0;
      if (Math.abs(n) >= 1e7) return '₹' + (n/1e7).toFixed(2) + ' Cr';
      if (Math.abs(n) >= 1e5) return '₹' + (n/1e5).toFixed(2) + ' L';
      return '₹' + Math.round(n).toLocaleString('en-IN');
    },
    async syncSheets() {
      this.syncing = true;
      const body = new URLSearchParams();
      body.set('action', 'sales_sheets_sync');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('force', '1');
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' });
        const j = await r.json();
        if (j.status !== 'success') throw new Error(j.message || 'Sync failed');
        location.href = location.pathname + '?tab=sales&_=' + Date.now();
      } catch (e) {
        alert(String(e.message || e));
        this.syncing = false;
      }
    },
    async saveSaPaste() {
      const raw = (this.saJsonPaste || '').trim();
      if (!raw) { alert('Paste the full service-account JSON first.'); return; }
      this.saHint = 'Saving…';
      const body = new URLSearchParams();
      body.set('action', 'sales_sheets_sa_upload');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('_csrf', csrf);
      body.set('sa_json', raw);
      try {
        const r = await fetch('index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body, credentials: 'same-origin'
        });
        const t = await r.text();
        if (!t.trim()) throw new Error('Empty response (HTTP ' + r.status + '). Reload and try again.');
        let j; try { j = JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 180)); }
        if (j.status !== 'success') throw new Error(j.message || 'Save failed');
        this.saHint = 'Installed: ' + (j.client_email || 'ok');
        alert(j.message || 'Service account saved.');
        location.href = location.pathname + '?tab=sales&_=' + Date.now();
      } catch (e) {
        this.saHint = '';
        alert(String(e.message || e));
      }
    },
    async uploadSaJson(ev) {
      const f = ev.target.files && ev.target.files[0];
      if (!f) return;
      this.saHint = 'Uploading…';
      const fd = new FormData();
      fd.set('action', 'sales_sheets_sa_upload');
      fd.set('csrf', csrf);
      fd.set('csrf_token', csrf);
      fd.set('_csrf', csrf);
      fd.set('sa_json', f);
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd, credentials: 'same-origin' });
        const j = await rcParseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'Upload failed');
        this.saHint = 'Installed: ' + (j.client_email || 'ok');
        alert(j.message || 'Service account saved. Share your sheets with that email, then Sync.');
        location.href = location.pathname + '?tab=sales&_=' + Date.now();
      } catch (e) {
        this.saHint = '';
        alert(String(e.message || e));
      }
      ev.target.value = '';
    },
    async saveSheetsCfg() {
      const body = new URLSearchParams();
      body.set('action', 'sales_sheets_config');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('payload', JSON.stringify(this.sheets));
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' });
        const j = await r.json();
        if (j.status !== 'success') throw new Error(j.message || 'Save failed');
        alert('Sheet connection saved. Click Sync now to pull live data.');
        this.showSheets = false;
      } catch (e) {
        alert(String(e.message || e));
      }
    },
    editRow(r) {
      this.editId = r.id;
      this.form = { month: r.month, project: r.project, unit: r.unit, customer: r.customer, unit_type: r.unit_type, area: r.area, bsp: r.bsp, other_charges: r.other_charges, gross: r.gross, status: r.status };
      this.showForm = true;
    },
    async saveRow() {
      this.err = '';
      const body = new URLSearchParams();
      body.set('action', 'sales_save');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('id', this.editId || '');
      Object.keys(this.form).forEach(k => body.set(k, this.form[k] == null ? '' : String(this.form[k])));
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' });
        const j = await r.json();
        if (j.status !== 'success') throw new Error(j.message || 'Save failed');
        location.href = location.pathname + '?tab=sales&_=' + Date.now();
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
    async removeRow(r) {
      if (!confirm('Delete this booking?')) return;
      const body = new URLSearchParams();
      body.set('action', 'sales_delete');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('id', r.id);
      const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' });
      const j = await res.json();
      if (j.status === 'success') location.reload();
      else alert(j.message || 'Delete failed');
    },
    async importCsv(ev) {
      const f = ev.target.files && ev.target.files[0];
      if (!f) return;
      const text = await f.text();
      const body = new URLSearchParams();
      body.set('action', 'sales_import');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('csv', text);
      const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' });
      const j = await res.json();
      if (j.status === 'success') location.reload();
      else alert(j.message || 'Import failed');
      ev.target.value = '';
    },
    init() {
      this.$nextTick(() => this.drawCharts());
    },
    drawCharts() {
      const packs = {
        revenue: seriesRevenue,
        units: seriesUnits,
        area: seriesArea
      };
      const ensureChart = () => new Promise((resolve) => {
        if (window.Chart) return resolve(window.Chart);
        const s = document.createElement('script');
        s.src = '/assets/vendor/chart.umd.min.js';
        s.onload = () => resolve(window.Chart || null);
        s.onerror = () => resolve(null);
        document.head.appendChild(s);
      });
      ensureChart().then((Chart) => {
        document.querySelectorAll('.rc-sales-chart').forEach(el => {
          const kind = el.getAttribute('data-kind');
          const series = packs[kind] || [];
          if (!Chart) {
            el.innerHTML = this.svgGroupedBars(chartMonths, series);
            return;
          }
          el.innerHTML = '';
          const canvas = document.createElement('canvas');
          canvas.setAttribute('aria-label', el.getAttribute('aria-label') || kind);
          el.appendChild(canvas);
          const datasets = series.map(s => ({
            label: s.name,
            data: s.data,
            backgroundColor: s.color,
            borderColor: s.color,
            borderWidth: 1,
            borderRadius: 3,
            maxBarThickness: 18
          }));
          // destroy prior instance if any
          if (el._rcChart) { try { el._rcChart.destroy(); } catch (e) {} }
          el._rcChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels: chartMonths.map(m => String(m).slice(5)), datasets },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
                tooltip: { mode: 'index', intersect: false }
              },
              scales: {
                x: { stacked: false, grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: 'rgba(0,0,0,.06)' } }
              }
            }
          });
        });
      });
    },
    svgGroupedBars(months, series) {
      const W = 320, H = 160, pad = 28;
      let max = 1;
      series.forEach(s => s.data.forEach(v => { if (v > max) max = v; }));
      const nM = Math.max(1, months.length);
      const nS = Math.max(1, series.length);
      const groupW = (W - pad * 2) / nM;
      const barW = Math.max(2, (groupW * 0.8) / nS);
      let bars = '';
      series.forEach((s, si) => {
        s.data.forEach((v, mi) => {
          const h = (v / max) * (H - pad - 10);
          const x = pad + mi * groupW + si * barW + groupW * 0.1;
          const y = H - pad - h;
          bars += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barW.toFixed(1)}" height="${Math.max(0,h).toFixed(1)}" fill="${s.color}" rx="2"><title>${s.name}: ${v}</title></rect>`;
        });
      });
      let labels = '';
      months.forEach((m, i) => {
        const lab = String(m).slice(5); // MM
        const x = pad + i * groupW + groupW / 2;
        labels += `<text x="${x}" y="${H - 8}" text-anchor="middle" font-size="9" fill="#605E5C">${lab}</text>`;
      });
      return `<svg viewBox="0 0 ${W} ${H}" width="100%" height="160" role="img">${bars}${labels}</svg>`;
    }
  };
};
</script>
