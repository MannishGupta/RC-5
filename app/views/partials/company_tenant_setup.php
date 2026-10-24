<?php
/**
 * Version: 3.2.59 — user-friendly tenant connections hub
 * Secrets only under DATA_PATH/config/
 */
if (!defined('BASE_PATH')) {
    exit;
}
if (empty($isAdmin) && empty($isSuperAdmin)) {
    return;
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if (!class_exists('AiClient') && is_file(BASE_PATH . '/app/AiClient.php')) {
    require_once BASE_PATH . '/app/AiClient.php';
}
if (!class_exists('GoogleSheetsClient') && is_file(BASE_PATH . '/app/GoogleSheetsClient.php')) {
    require_once BASE_PATH . '/app/GoogleSheetsClient.php';
}
if (!class_exists('SalesRegister') && is_file(BASE_PATH . '/app/SalesRegister.php')) {
    require_once BASE_PATH . '/app/SalesRegister.php';
}

$aiCfg = class_exists('AiClient') ? AiClient::config() : [];
$aiReady = class_exists('AiClient') && AiClient::isReady();
$sheetsSt = class_exists('GoogleSheetsClient') ? GoogleSheetsClient::status() : ['ok' => false, 'configured' => false, 'message' => ''];
$sheetsOk = !empty($sheetsSt['ok']);
$sheetsConfigured = !empty($sheetsSt['configured']);

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

$doneAi = $aiReady ? 1 : 0;
$doneSheets = $sheetsOk ? 1 : 0;
$doneCount = $doneAi + $doneSheets;
?>
<style>
.rc-uf{--uf-accent:var(--rc-accent,#0078D4);--uf-ink:var(--rc-ink,#201f1e);--uf-muted:var(--rc-ink-muted,#605e5c);--uf-card:var(--rc-card,#fff);--uf-paper:var(--rc-paper,#faf9f8);--uf-border:var(--rc-border,#e1dfdd);color:var(--uf-ink);max-width:40rem}
.rc-uf *{box-sizing:border-box}
.rc-uf-hero{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1rem}
.rc-uf-hero h2{margin:0;font-size:1.15rem;font-weight:800;letter-spacing:-.02em}
.rc-uf-hero p{margin:.3rem 0 0;font-size:13px;color:var(--uf-muted);line-height:1.45;max-width:28rem}
.rc-uf-progress{display:flex;align-items:center;gap:.5rem;font-size:12px;font-weight:700;color:var(--uf-muted)}
.rc-uf-progress b{color:var(--uf-accent);font-size:1.1rem}
.rc-uf-bar{height:6px;border-radius:99px;background:var(--uf-border);overflow:hidden;width:7rem}
.rc-uf-bar>i{display:block;height:100%;background:var(--uf-accent);border-radius:99px}
.rc-uf-card{border:1px solid var(--uf-border);border-radius:1rem;background:var(--uf-card);margin-bottom:.75rem;overflow:hidden}
.rc-uf-card.is-done{border-color:color-mix(in srgb, #0f766e 45%, var(--uf-border))}
.rc-uf-sum{display:flex;align-items:center;gap:.75rem;width:100%;padding:.9rem 1rem;background:transparent;border:0;cursor:pointer;text-align:left;color:inherit;font:inherit}
.rc-uf-sum:hover{background:var(--uf-paper)}
.rc-uf-num{width:1.85rem;height:1.85rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;background:var(--uf-paper);border:1px solid var(--uf-border);color:var(--uf-muted)}
.rc-uf-card.is-done .rc-uf-num{background:#ecfdf5;border-color:#a7f3d0;color:#0f766e}
.rc-uf-card.is-open .rc-uf-num{background:var(--uf-accent);border-color:var(--uf-accent);color:#fff}
.rc-uf-sum-body{flex:1;min-width:0}
.rc-uf-sum-body strong{display:block;font-size:14px;font-weight:800}
.rc-uf-sum-body span{display:block;font-size:12px;color:var(--uf-muted);margin-top:.1rem}
.rc-uf-badge{font-size:11px;font-weight:800;padding:.2rem .55rem;border-radius:999px;white-space:nowrap}
.rc-uf-badge.ok{background:#ecfdf5;color:#0f766e}
.rc-uf-badge.wait{background:#fff7ed;color:#c2410c}
.rc-uf-panel{padding:0 1rem 1rem 1rem;border-top:1px solid var(--uf-border);background:var(--uf-paper)}
.rc-uf-panel p{font-size:13px;line-height:1.5;color:var(--uf-muted);margin:.75rem 0}
.rc-uf-panel ol{margin:.5rem 0 .75rem;padding-left:1.2rem;font-size:13px;line-height:1.55;color:var(--uf-ink)}
.rc-uf-panel ol li{margin:.25rem 0}
.rc-uf-choices{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin:.75rem 0}
@media(max-width:520px){.rc-uf-choices{grid-template-columns:1fr}}
.rc-uf-choice{border:1px solid var(--uf-border);border-radius:.85rem;padding:.7rem .8rem;background:var(--uf-card);cursor:pointer;text-align:left;color:inherit}
.rc-uf-choice:hover{border-color:var(--uf-accent)}
.rc-uf-choice.on{border-color:var(--uf-accent);box-shadow:0 0 0 2px color-mix(in srgb, var(--uf-accent) 22%, transparent)}
.rc-uf-choice strong{display:block;font-size:13px;font-weight:800}
.rc-uf-choice small{display:block;font-size:11px;color:var(--uf-muted);margin-top:.2rem;line-height:1.35}
.rc-uf-label{display:block;font-size:12px;font-weight:700;margin:.65rem 0 .25rem;color:var(--uf-ink)}
.rc-uf-input,.rc-uf-ta{width:100%;border:1px solid var(--uf-border);border-radius:.65rem;padding:.55rem .7rem;font-size:13px;background:var(--uf-card);color:var(--uf-ink)}
.rc-uf-ta{min-height:5rem;font-family:ui-monospace,Menlo,monospace;font-size:11px}
.rc-uf-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.85rem}
.rc-uf-btn{height:2.35rem;padding:0 1rem;border-radius:.7rem;font-size:13px;font-weight:800;border:1px solid var(--uf-border);background:var(--uf-card);color:var(--uf-ink);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem}
.rc-uf-btn-main{background:var(--uf-accent);border-color:var(--uf-accent);color:#fff}
.rc-uf-btn:disabled{opacity:.55;cursor:not-allowed}
.rc-uf-msg{font-size:12px;color:var(--uf-muted)}
.rc-uf-msg.err{color:#b91c1c}
.rc-uf-msg.ok{color:#0f766e;font-weight:700}
.rc-uf-tip{font-size:12px;background:color-mix(in srgb, var(--uf-accent) 8%, var(--uf-card));border:1px solid color-mix(in srgb, var(--uf-accent) 22%, var(--uf-border));border-radius:.75rem;padding:.65rem .8rem;margin:.5rem 0;color:var(--uf-ink);line-height:1.45}
</style>

<section class="rc-uf mt-8 pt-6 border-t" id="tenant-setup-integrations" style="border-color:var(--rc-border,#e1dfdd)"
  x-data="rcCompanyTenantSetup()" x-cloak>
  <div class="rc-uf-hero">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--uf-accent)">Optional extras</div>
      <h2>Connect tools for this organisation</h2>
      <p>Only needed if you want AI help or live Sales from Google Sheets. Everything is saved privately for this company — not shared with other tenants.</p>
    </div>
    <div class="rc-uf-progress" title="Optional connections completed">
      <b><?= (int)$doneCount ?></b><span>/ 2 ready</span>
      <div class="rc-uf-bar" aria-hidden="true"><i style="width:<?= (int)($doneCount * 50) ?>%"></i></div>
    </div>
  </div>

  <!-- AI -->
  <div class="rc-uf-card <?= $aiReady ? 'is-done' : '' ?>" :class="open==='ai' && 'is-open'">
    <button type="button" class="rc-uf-sum" @click="open = open==='ai' ? '' : 'ai'" aria-expanded="false">
      <span class="rc-uf-num"><?= $aiReady ? '✓' : '1' ?></span>
      <span class="rc-uf-sum-body">
        <strong>AI helper</strong>
        <span>Draft MIS notes, captions, and emails from your RC data</span>
      </span>
      <span class="rc-uf-badge <?= $aiReady ? 'ok' : 'wait' ?>"><?= $aiReady ? 'Connected' : 'Not set' ?></span>
    </button>
    <div class="rc-uf-panel" x-show="open==='ai'" x-cloak>
      <p>Pick the service you already use, paste its <strong>API key</strong>, then save. (ChatGPT or Gemini <em>apps</em> are different from API access — you need a key from their developer site.)</p>
      <div class="rc-uf-choices">
        <button type="button" class="rc-uf-choice" :class="ai.provider==='openai' && 'on'" @click="pickAi('openai')">
          <strong>ChatGPT (OpenAI)</strong>
          <small>Best if you pay for OpenAI API · key starts with sk-</small>
        </button>
        <button type="button" class="rc-uf-choice" :class="ai.provider==='gemini' && 'on'" @click="pickAi('gemini')">
          <strong>Google Gemini</strong>
          <small>Free key from AI Studio · good everyday choice</small>
        </button>
        <button type="button" class="rc-uf-choice" :class="ai.provider==='groq' && 'on'" @click="pickAi('groq')">
          <strong>Groq — free</strong>
          <small>Fast · no credit card for light use</small>
        </button>
        <button type="button" class="rc-uf-choice" :class="ai.provider==='ollama' && 'on'" @click="pickAi('ollama')">
          <strong>Ollama — on your server</strong>
          <small>Fully free · needs Ollama installed on the host</small>
        </button>
      </div>
      <label class="rc-uf-label" x-text="ai.provider==='ollama' ? 'Key (optional)' : 'Paste your API key'"></label>
      <input class="rc-uf-input" type="password" x-model="ai.api_key" autocomplete="off" :placeholder="aiKeyPh()">
      <div class="rc-uf-tip" x-show="ai.provider==='openai'">Open <strong>platform.openai.com → API keys</strong>, create a key, copy it here. ChatGPT Plus/Pro alone is not enough.</div>
      <div class="rc-uf-tip" x-show="ai.provider==='gemini'">Open <strong>aistudio.google.com → Get API key</strong>, create one, paste here.</div>
      <div class="rc-uf-tip" x-show="ai.provider==='groq'">Open <strong>console.groq.com → API Keys</strong> and create a free key.</div>
      <div class="rc-uf-tip" x-show="ai.provider==='ollama'">Install Ollama on the same server as RC, run <code>ollama pull llama3.2</code>, then save. No cloud key needed.</div>
      <div class="rc-uf-actions">
        <button type="button" class="rc-uf-btn rc-uf-btn-main" @click="saveAi()" :disabled="busy">Save AI connection</button>
        <a class="rc-uf-btn" :href="aiHelp()" target="_blank" rel="noopener">Open key page ↗</a>
        <a class="rc-uf-btn" href="?tab=ai">Try AI Assistant</a>
        <span class="rc-uf-msg" :class="aiMsgClass" x-text="aiMsg"></span>
      </div>
    </div>
  </div>

  <!-- Sheets -->
  <div class="rc-uf-card <?= $sheetsOk ? 'is-done' : '' ?>" :class="open==='sheets' && 'is-open'">
    <button type="button" class="rc-uf-sum" @click="open = open==='sheets' ? '' : 'sheets'">
      <span class="rc-uf-num"><?= $sheetsOk ? '✓' : '2' ?></span>
      <span class="rc-uf-sum-body">
        <strong>Live Sales from Google Sheets</strong>
        <span>Keep Brook / Rivulet / U:Fairia numbers in sync automatically</span>
      </span>
      <span class="rc-uf-badge <?= $sheetsOk ? 'ok' : 'wait' ?>"><?= $sheetsOk ? 'Connected' : ($sheetsConfigured ? 'Key needs fix' : 'Not set') ?></span>
    </button>
    <div class="rc-uf-panel" x-show="open==='sheets'" x-cloak>
      <p>One-time setup. After this, add your sheet links on the Sales page and press Sync.</p>
      <ol>
        <li>In Google Cloud, create a <strong>service account</strong> and download its JSON key.</li>
        <li>Enable <strong>Google Sheets API</strong> for that project.</li>
        <li>Share each sales spreadsheet with the service account email (Viewer is enough).</li>
        <li>Upload or paste that JSON below.</li>
      </ol>
      <?php if ($sheetsOk): ?>
        <div class="rc-uf-tip">Connected as <strong><?= $h((string)($sheetsSt['client_email'] ?? '')) ?></strong>. You can replace the key anytime.</div>
      <?php elseif ($sheetsConfigured): ?>
        <div class="rc-uf-tip">A key file is present but not working yet: <?= $h((string)($sheetsSt['message'] ?? 'check the JSON')) ?></div>
      <?php endif; ?>
      <div class="rc-uf-actions">
        <label class="rc-uf-btn rc-uf-btn-main" style="cursor:pointer">
          Choose JSON file
          <input type="file" accept=".json,application/json" class="hidden" @change="uploadSa($event)">
        </label>
        <a class="rc-uf-btn" href="?tab=sales">Open Sales Register</a>
        <span class="rc-uf-msg" :class="saMsgClass" x-text="saMsg"></span>
      </div>
      <label class="rc-uf-label">Or paste the JSON text</label>
      <textarea class="rc-uf-ta" x-model="saPaste" placeholder='Paste the whole service-account JSON here…'></textarea>
      <div class="rc-uf-actions">
        <button type="button" class="rc-uf-btn rc-uf-btn-main" @click="saveSaPaste()" :disabled="busy">Save key</button>
      </div>
    </div>
  </div>

  <p class="text-xs mt-2" style="color:var(--uf-muted)">
    Module on/off for each role is under
    <a href="?tab=tenants" style="color:var(--uf-accent);font-weight:700">Tenant Setup</a>
    (Super Admin). Brand logos and company profile are above on this same page.
  </p>
</section>

<script>
window.rcCompanyTenantSetup = function () {
  const csrf = <?= json_encode($csrf) ?>;
  async function parseJson(r) {
    const t = await r.text();
    if (!t.trim()) throw new Error('Server returned an empty answer. Refresh the page and try once more.');
    try { return JSON.parse(t); } catch (e) { throw new Error('Could not read server reply. Try again after a hard refresh.'); }
  }
  function csrfBody(action) {
    const b = new URLSearchParams();
    b.set('action', action);
    b.set('csrf', csrf);
    b.set('csrf_token', csrf);
    b.set('_csrf', csrf);
    return b;
  }
  const startOpen = <?= json_encode(!$aiReady ? 'ai' : (!$sheetsOk ? 'sheets' : '')) ?>;
  return {
    open: startOpen || (location.hash === '#setup-sheets' ? 'sheets' : (location.hash === '#setup-ai' ? 'ai' : startOpen)),
    busy: false,
    aiMsg: '',
    aiMsgClass: '',
    saMsg: '',
    saMsgClass: '',
    saPaste: '',
    ai: {
      enabled: true,
      provider: <?= json_encode((string)($aiCfg['provider'] ?? 'gemini')) ?>,
      base_url: <?= json_encode((string)($aiCfg['base_url'] ?? '')) ?>,
      model: <?= json_encode((string)($aiCfg['model'] ?? 'gemini-2.0-flash')) ?>,
      api_key: ''
    },
    pickAi(id) {
      this.ai.provider = id;
      this.ai.enabled = true;
      if (id === 'openai') { this.ai.base_url = 'https://api.openai.com/v1'; this.ai.model = 'gpt-4o-mini'; }
      if (id === 'gemini') { this.ai.base_url = 'https://generativelanguage.googleapis.com/v1beta/openai'; this.ai.model = 'gemini-2.0-flash'; }
      if (id === 'groq') { this.ai.base_url = 'https://api.groq.com/openai/v1'; this.ai.model = 'llama-3.3-70b-versatile'; }
      if (id === 'ollama') { this.ai.base_url = 'http://127.0.0.1:11434/v1'; this.ai.model = 'llama3.2'; }
      this.aiMsg = '';
    },
    aiKeyPh() {
      const p = this.ai.provider;
      if (p === 'openai') return 'sk-…';
      if (p === 'gemini') return 'AIza…';
      if (p === 'groq') return 'gsk_…';
      return 'Leave blank for local Ollama';
    },
    aiHelp() {
      const p = this.ai.provider;
      if (p === 'gemini') return 'https://aistudio.google.com/apikey';
      if (p === 'groq') return 'https://console.groq.com/keys';
      if (p === 'ollama') return 'https://ollama.com/download';
      return 'https://platform.openai.com/api-keys';
    },
    async saveAi() {
      if (this.ai.provider !== 'ollama' && !(this.ai.api_key || '').trim()) {
        this.aiMsg = 'Paste an API key first (or choose Ollama).';
        this.aiMsgClass = 'err';
        return;
      }
      this.busy = true; this.aiMsg = 'Saving…'; this.aiMsgClass = '';
      const body = csrfBody('ai_config_save');
      body.set('payload', JSON.stringify(this.ai));
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body, credentials: 'same-origin' });
        const j = await parseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'Could not save');
        this.aiMsg = j.ready ? 'All set — you can use AI Assistant now.' : 'Saved. If chat fails, check the key.';
        this.aiMsgClass = 'ok';
        this.ai.api_key = '';
        setTimeout(function () { location.href = location.pathname + '?tab=company&_=' + Date.now() + '#tenant-setup-integrations'; }, 700);
      } catch (e) {
        this.aiMsg = String(e.message || e);
        this.aiMsgClass = 'err';
      }
      this.busy = false;
    },
    async uploadSa(ev) {
      const f = ev.target.files && ev.target.files[0];
      if (!f) return;
      this.busy = true; this.saMsg = 'Uploading…'; this.saMsgClass = '';
      const fd = new FormData();
      fd.set('action', 'sales_sheets_sa_upload');
      fd.set('csrf', csrf); fd.set('csrf_token', csrf); fd.set('_csrf', csrf);
      fd.set('sa_json', f);
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd, credentials: 'same-origin' });
        const j = await parseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'Upload failed');
        this.saMsg = 'Key installed. Next: open Sales Register and add sheet links.';
        this.saMsgClass = 'ok';
        setTimeout(function () { location.href = location.pathname + '?tab=company&_=' + Date.now() + '#setup-sheets'; }, 800);
      } catch (e) {
        this.saMsg = String(e.message || e);
        this.saMsgClass = 'err';
      }
      this.busy = false;
      ev.target.value = '';
    },
    async saveSaPaste() {
      const raw = (this.saPaste || '').trim();
      if (!raw) {
        this.saMsg = 'Paste the JSON key, or use Choose JSON file.';
        this.saMsgClass = 'err';
        return;
      }
      this.busy = true; this.saMsg = 'Saving…'; this.saMsgClass = '';
      const body = csrfBody('sales_sheets_sa_upload');
      body.set('sa_json', raw);
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body, credentials: 'same-origin' });
        const j = await parseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'Save failed');
        this.saMsg = 'Key installed. Next: open Sales Register.';
        this.saMsgClass = 'ok';
        setTimeout(function () { location.href = location.pathname + '?tab=company&_=' + Date.now() + '#setup-sheets'; }, 800);
      } catch (e) {
        this.saMsg = String(e.message || e);
        this.saMsgClass = 'err';
      }
      this.busy = false;
    }
  };
};
</script>
