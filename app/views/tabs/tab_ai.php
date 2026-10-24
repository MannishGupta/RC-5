<?php
// Version: 3.2.56 — expanded AI assistant scope
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

if (!class_exists('AiClient') && is_file(BASE_PATH . '/app/AiClient.php')) {
    require_once BASE_PATH . '/app/AiClient.php';
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cfg = class_exists('AiClient') ? AiClient::config() : [];
$ready = class_exists('AiClient') && AiClient::isReady();
$canCfg = !empty($isAdmin) || !empty($isSuperAdmin);
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
$coName = '';
if (class_exists('AppDB')) {
    $co = AppDB::read('company') ?: [];
    if (isset($co[0]) && is_array($co[0])) {
        $co = $co[0];
    }
    $coName = trim((string)($co['name'] ?? ''));
}
?>
<style>
.rc-ai{color:var(--rc-ink);width:100%;max-width:none}
.rc-ai-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:1rem}
@media(min-width:960px){.rc-ai-grid{grid-template-columns:240px minmax(0,1fr)}}
.rc-ai-card{border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-card);overflow:hidden}
.rc-ai-hd{padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);background:var(--rc-paper);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem}
.rc-ai-hd h2{margin:0;font-size:.95rem;font-weight:800;color:var(--rc-ink)}
.rc-ai-bd{padding:1rem}
.rc-ai-chip{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--rc-border);background:var(--rc-paper);color:var(--rc-ink);border-radius:999px;padding:.35rem .7rem;font-size:11px;font-weight:700;cursor:pointer;text-align:left;width:100%;margin-bottom:.35rem}
.rc-ai-chip:hover{border-color:var(--rc-accent);color:var(--rc-accent)}
.rc-ai-chip i{width:1rem;text-align:center;color:var(--rc-accent)}
.rc-ai-scroll{height:min(52vh,28rem);overflow:auto;padding:.75rem;background:var(--rc-paper);border-radius:.75rem;border:1px solid var(--rc-border)}
.rc-ai-msg{margin-bottom:.75rem;max-width:92%}
.rc-ai-msg.user{margin-left:auto}
.rc-ai-bubble{padding:.65rem .85rem;border-radius:.85rem;font-size:13px;line-height:1.5;white-space:pre-wrap;word-break:break-word}
.rc-ai-msg.user .rc-ai-bubble{background:var(--rc-accent);color:#fff}
.rc-ai-msg.assistant .rc-ai-bubble{background:var(--rc-card);border:1px solid var(--rc-border);color:var(--rc-ink)}
.rc-ai-input{width:100%;min-height:4.5rem;border:1px solid var(--rc-border);border-radius:.75rem;padding:.65rem .75rem;background:var(--rc-card);color:var(--rc-ink);font-size:13px;resize:vertical}
.rc-ai-meta{font-size:11px;color:var(--rc-ink-muted)}
.rc-ai-actions{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem}
.rc-ai-btn{height:2.15rem;padding:0 .85rem;border-radius:.65rem;font-size:12px;font-weight:800;border:1px solid var(--rc-border);background:var(--rc-card);color:var(--rc-ink);cursor:pointer}
.rc-ai-btn-pri{background:var(--rc-accent);border-color:var(--rc-accent);color:#fff}
.rc-ai-btn:disabled{opacity:.5;cursor:not-allowed}
.rc-ai-field{display:block;font-size:11px;font-weight:700;color:var(--rc-ink-muted);margin:.5rem 0 .25rem}
.rc-ai-field input,.rc-ai-field select{width:100%;height:2.15rem;border:1px solid var(--rc-border);border-radius:.55rem;padding:0 .6rem;background:var(--rc-card);color:var(--rc-ink);font-size:12px}
.rc-ai-sec{font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--rc-ink-muted);margin:0 0 .5rem}
.rc-ai-connect{display:grid;grid-template-columns:repeat(auto-fill,minmax(11.5rem,1fr));gap:.65rem;margin-bottom:1rem}
.rc-ai-cbtn{display:flex;flex-direction:column;align-items:flex-start;gap:.25rem;text-align:left;padding:.75rem .85rem;border-radius:.85rem;border:1px solid var(--rc-border);background:var(--rc-paper);color:var(--rc-ink);cursor:pointer;font-size:12px;line-height:1.35}
.rc-ai-cbtn:hover{border-color:var(--rc-accent)}
.rc-ai-cbtn.is-on{border-color:var(--rc-accent);box-shadow:0 0 0 2px color-mix(in srgb, var(--rc-accent) 25%, transparent)}
.rc-ai-cbtn strong{font-size:13px;font-weight:800}
.rc-ai-cbtn .tag{font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;opacity:.85}
.rc-ai-cbtn .tag.paid{color:#0f766e}
.rc-ai-cbtn .tag.free{color:#1d4ed8}
.rc-ai-cbtn small{font-size:11px;color:var(--rc-ink-muted)}

</style>

<div class="rc-page rc-ai w-full flex flex-col gap-4" x-data="rcAiTab()" x-cloak>
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Insights</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">AI Assistant</h1>
      <p class="rc-page-sub text-sm">Drafts and briefs from live tenant data</p>
    </div>
  </header>

  <div class="rc-ai-hd" style="border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-card);margin-bottom:1rem;padding:.9rem 1rem">
    <div>
      <h2 style="margin:0;font-size:1.05rem;font-weight:800;color:var(--rc-ink)">
        <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--rc-accent)"></i>
        AI Assistant
        <?php if ($coName !== ''): ?>
          <span class="rc-ai-meta" style="font-weight:600">· <?= $h($coName) ?></span>
        <?php endif; ?>
      </h2>
      <p class="rc-ai-meta" style="margin:.35rem 0 0">
        Sales MIS · Media kit copy · HR summaries · Construction language · Commodities · Choghadiya · Email / WhatsApp drafts
      </p>
    </div>
    <div class="rc-ai-actions" style="margin:0">
      <?php if ($ready): ?>
        <span class="rc-ai-meta" style="color:#0f766e;font-weight:800">● Ready</span>
      <?php else: ?>
        <span class="rc-ai-meta" style="color:#b45309;font-weight:800">○ Configure API key</span>
      <?php endif; ?>
      <?php if ($canCfg): ?>
        <a href="?tab=company#setup-ai" class="rc-ai-btn" style="text-decoration:none;display:inline-flex;align-items:center">Setup in Company ↗</a>
        <button type="button" class="rc-ai-btn" @click="showCfg=!showCfg">Quick settings</button>
      <?php endif; ?>
      <button type="button" class="rc-ai-btn" @click="clearChat()" :disabled="!messages.length">Clear</button>
    </div>
  </div>

  <?php if ($canCfg): ?>
  <div class="rc-ai-card" x-show="showCfg" x-cloak style="margin-bottom:1rem">
    <div class="rc-ai-hd">
      <h2>Connect an AI provider</h2>
      <span class="rc-ai-meta">One active connection per tenant · key stays private</span>
    </div>
    <div class="rc-ai-bd">
      <p class="rc-ai-meta" style="margin:0 0 .75rem">
        <strong>ChatGPT Pro</strong> and <strong>Gemini Pro</strong> apps are separate from API access.
        You still need an <em>API key</em> (OpenAI platform / Google AI Studio). Free tiers below work for everyday RC tasks.
      </p>

      <p class="rc-ai-sec">Your paid plans — recommended</p>
      <div class="rc-ai-connect">
        <button type="button" class="rc-ai-cbtn" :class="cfg.provider==='openai' && 'is-on'" @click="connectProvider('openai')">
          <span class="tag paid">API · paid usage</span>
          <strong><i class="fa-solid fa-robot"></i> ChatGPT / OpenAI</strong>
          <small>Best with ChatGPT Plus/Pro billing on platform.openai.com</small>
        </button>
        <button type="button" class="rc-ai-cbtn" :class="cfg.provider==='gemini' && 'is-on'" @click="connectProvider('gemini')">
          <span class="tag paid">API · free tier + Pro</span>
          <strong><i class="fa-solid fa-gem"></i> Google Gemini</strong>
          <small>Key from Google AI Studio · Gemini 2.0 Flash default</small>
        </button>
      </div>

      <p class="rc-ai-sec">Free / low-cost options</p>
      <div class="rc-ai-connect">
        <button type="button" class="rc-ai-cbtn" :class="cfg.provider==='groq' && 'is-on'" @click="connectProvider('groq')">
          <span class="tag free">Free tier</span>
          <strong><i class="fa-solid fa-bolt"></i> Groq</strong>
          <small>Fast Llama models · console.groq.com</small>
        </button>
        <button type="button" class="rc-ai-cbtn" :class="cfg.provider==='xai' && 'is-on'" @click="connectProvider('xai')">
          <span class="tag free">Credits / paid</span>
          <strong><i class="fa-solid fa-x"></i> xAI Grok</strong>
          <small>console.x.ai · OpenAI-compatible</small>
        </button>
        <button type="button" class="rc-ai-cbtn" :class="cfg.provider==='ollama' && 'is-on'" @click="connectProvider('ollama')">
          <span class="tag free">Free local</span>
          <strong><i class="fa-solid fa-server"></i> Ollama</strong>
          <small>Runs on your server · no cloud key</small>
        </button>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.75rem;margin-top:.5rem">
        <label class="rc-ai-field">Enabled
          <select x-model="cfg.enabled">
            <option :value="true">On</option>
            <option :value="false">Off</option>
          </select>
        </label>
        <label class="rc-ai-field">Provider id
          <select x-model="cfg.provider" @change="applyPreset()">
            <option value="openai">openai (ChatGPT API)</option>
            <option value="gemini">gemini</option>
            <option value="groq">groq</option>
            <option value="xai">xai</option>
            <option value="ollama">ollama</option>
            <option value="custom">custom</option>
          </select>
        </label>
        <label class="rc-ai-field">Base URL
          <input x-model="cfg.base_url" placeholder="https://…">
        </label>
        <label class="rc-ai-field">Model
          <input x-model="cfg.model" placeholder="model id">
        </label>
        <label class="rc-ai-field" style="grid-column:1/-1">
          <span x-text="cfg.provider==='ollama' ? 'API key (optional for Ollama)' : 'API key (required)'"></span>
          <input type="password" x-model="cfg.api_key" autocomplete="off" :placeholder="keyPlaceholder()">
        </label>
        <div style="grid-column:1/-1" class="rc-ai-actions">
          <button type="button" class="rc-ai-btn rc-ai-btn-pri" @click="saveCfg()" :disabled="busy">Save &amp; connect</button>
          <a class="rc-ai-btn" style="text-decoration:none;display:inline-flex;align-items:center" :href="keyHelpUrl()" target="_blank" rel="noopener">Get API key ↗</a>
          <span class="rc-ai-meta" x-text="cfgErr" style="color:#b91c1c"></span>
        </div>
        <p class="rc-ai-meta" style="grid-column:1/-1;margin:0" x-text="connectHint()"></p>
        <p class="rc-ai-meta" style="grid-column:1/-1;margin:0">Stored at tenant <code>data/config/ai.json</code> only — never in Git.</p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="rc-ai-grid">
    <aside class="rc-ai-card">
      <div class="rc-ai-hd"><h2>Playbooks</h2></div>
      <div class="rc-ai-bd">
        <p class="rc-ai-sec">Sales & MIS</p>
        <button type="button" class="rc-ai-chip" @click="quick('sales_brief')"><i class="fa-solid fa-chart-column"></i> Leadership MIS brief</button>
        <button type="button" class="rc-ai-chip" @click="quick('sales_risks')"><i class="fa-solid fa-triangle-exclamation"></i> Risks & slow projects</button>
        <button type="button" class="rc-ai-chip" @click="quick('sales_board')"><i class="fa-solid fa-landmark"></i> Board pack bullets</button>

        <p class="rc-ai-sec" style="margin-top:.75rem">Media & social</p>
        <button type="button" class="rc-ai-chip" @click="quick('caption_ig')"><i class="fa-brands fa-instagram"></i> Instagram captions</button>
        <button type="button" class="rc-ai-chip" @click="quick('caption_li')"><i class="fa-brands fa-linkedin"></i> LinkedIn post</button>
        <button type="button" class="rc-ai-chip" @click="quick('caption_wa')"><i class="fa-brands fa-whatsapp"></i> WhatsApp status lines</button>
        <button type="button" class="rc-ai-chip" @click="quick('mediakit_plan')"><i class="fa-solid fa-photo-film"></i> Media kit launch plan</button>

        <p class="rc-ai-sec" style="margin-top:.75rem">People & org</p>
        <button type="button" class="rc-ai-chip" @click="quick('team_summary')"><i class="fa-solid fa-users"></i> Team readiness summary</button>
        <button type="button" class="rc-ai-chip" @click="quick('onboarding')"><i class="fa-solid fa-user-plus"></i> New joiner checklist</button>

        <p class="rc-ai-sec" style="margin-top:.75rem">Projects & markets</p>
        <button type="button" class="rc-ai-chip" @click="quick('status_update')"><i class="fa-solid fa-helmet-safety"></i> Construction update tone</button>
        <button type="button" class="rc-ai-chip" @click="quick('metals_explain')"><i class="fa-solid fa-coins"></i> Commodity prices explain</button>
        <button type="button" class="rc-ai-chip" @click="quick('choghadiya')"><i class="fa-solid fa-sun"></i> Choghadiya plain language</button>

        <p class="rc-ai-sec" style="margin-top:.75rem">Communications</p>
        <button type="button" class="rc-ai-chip" @click="quick('email_directors')"><i class="fa-solid fa-envelope"></i> Email to directors</button>
        <button type="button" class="rc-ai-chip" @click="quick('email_customer')"><i class="fa-solid fa-house"></i> Customer update email</button>
        <button type="button" class="rc-ai-chip" @click="quick('dpdp_notice')"><i class="fa-solid fa-shield-halved"></i> DPDP-aware wording</button>
        <button type="button" class="rc-ai-chip" @click="quick('translate_hi')"><i class="fa-solid fa-language"></i> Translate last → Hindi</button>
      </div>
    </aside>

    <section class="rc-ai-card">
      <div class="rc-ai-hd">
        <h2>Chat</h2>
        <span class="rc-ai-meta" x-text="meta"></span>
      </div>
      <div class="rc-ai-bd">
        <div id="rc-ai-scroll" class="rc-ai-scroll">
          <template x-if="!messages.length">
            <p class="rc-ai-meta" style="margin:0">
              Ask anything about this organisation’s RC data, or pick a playbook on the left.
              Context is attached automatically (sales figures, media kit inventory, headcount — never passwords or full PII dumps).
            </p>
          </template>
          <template x-for="(m, i) in messages" :key="i">
            <div class="rc-ai-msg" :class="m.role">
              <div class="rc-ai-bubble" x-text="m.content"></div>
              <div class="rc-ai-actions" x-show="m.role==='assistant'">
                <button type="button" class="rc-ai-btn" @click="copyText(m.content)">Copy</button>
              </div>
            </div>
          </template>
        </div>
        <div style="margin-top:.75rem">
          <textarea class="rc-ai-input" x-model="input" placeholder="Message… (Shift+Enter for newline)"
            @keydown.enter.exact.prevent="send()"></textarea>
          <div class="rc-ai-actions">
            <button type="button" class="rc-ai-btn rc-ai-btn-pri" @click="send()" :disabled="busy || !input.trim()">Send</button>
            <button type="button" class="rc-ai-btn" @click="send(null,'full')" :disabled="busy || !input.trim()">Send + full context</button>
            <span class="rc-ai-meta" x-show="busy">Thinking…</span>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
window.rcAiTab = function () {
  const csrf = <?= json_encode($csrf) ?>;
  const initialCfg = <?= json_encode([
      'enabled' => !empty($cfg['enabled']),
      'provider' => (string)($cfg['provider'] ?? 'openai'),
      'base_url' => (string)($cfg['base_url'] ?? 'https://api.openai.com/v1'),
      'model' => (string)($cfg['model'] ?? 'gpt-4o-mini'),
      'api_key' => '',
  ], JSON_UNESCAPED_SLASHES) ?>;

  const playbooks = {
    sales_brief: { kind: 'sales', text: 'Using the sales context, write a short leadership MIS brief: YTD vs this month, project highlights, and 3 action points. Use INR and bullets. Do not invent numbers.' },
    sales_risks: { kind: 'sales', text: 'From the sales context, list projects or patterns that look soft or risky (low units, cancelled-heavy). Suggest checks the sales team should run. No invented figures.' },
    sales_board: { kind: 'sales', text: 'Produce board-ready bullet points from the sales context only: headline KPI, project mix, quality of bookings. Flag what data is missing.' },
    caption_ig: { kind: 'mediakit', text: 'Suggest 6 Instagram captions for a real-estate / construction brand in India using our media kit categories if provided. Mix polished English and light Hinglish. Max 2 hashtags each.' },
    caption_li: { kind: 'mediakit', text: 'Draft one LinkedIn post (120–180 words) for project progress credibility. Professional, no hype, no false RERA claims.' },
    caption_wa: { kind: 'mediakit', text: 'Write 5 WhatsApp status lines (under 80 characters) for site updates / brand presence. Clean and shareable.' },
    mediakit_plan: { kind: 'mediakit', text: 'Using media kit inventory counts/categories, propose a 2-week content plan (platform × asset type). Be practical for a small team.' },
    team_summary: { kind: 'team', text: 'Summarise organisational readiness from headcount / blood-group coverage / photo coverage only. No names. Suggest data hygiene next steps for HR.' },
    onboarding: { kind: 'team', text: 'Give a new-joiner checklist for this portal: what admins should complete in RC (company, team card, location, media kit). Numbered steps.' },
    status_update: { kind: 'status', text: 'Suggest a neutral, professional tone template for a monthly construction progress note to customers. Include disclaimer that photos are informational, not legal completion certificates.' },
    metals_explain: { kind: 'metals', text: 'Explain how Delhi NCR / MCX style commodity cards work for end users (ex-GST vs incl-GST, why cache exists). Do not invent live prices — tell them to open Commodity Prices for numbers.' },
    choghadiya: { kind: 'calendar', text: 'Explain Choghadiya in plain language for business users in India: what it is, how to use the RC module, and that it is cultural guidance not financial advice.' },
    email_directors: { kind: 'comms', text: 'Draft an email to directors with subject options + short body for monthly operations update. Placeholders in [brackets] for figures they must fill from Sales Register.' },
    email_customer: { kind: 'comms', text: 'Draft a courteous customer email about construction progress photos being available on the portal. Calm, transparent, no over-promising dates.' },
    dpdp_notice: { kind: 'company', text: 'Give short DPDP-aware wording for internal staff: what personal data RC may hold, that access is role-based, and to contact the grievance channel for correction/erasure. No legal overclaim.' },
    translate_hi: { kind: 'general', text: 'Translate the previous assistant answer into clear Hindi (Devanagari). Keep structure and bullets. If there was no previous answer, say so.' }
  };

  async function parseJson(r) {
    const t = await r.text();
    if (!t.trim()) throw new Error('Empty response (HTTP ' + r.status + '). Reload and try again.');
    try { return JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 180)); }
  }

  return {
    ready: <?= $ready ? 'true' : 'false' ?>,
    showCfg: <?= $ready ? 'false' : 'true' ?>,
    busy: false,
    cfgErr: '',
    cfg: Object.assign({}, initialCfg, { enabled: !!initialCfg.enabled }),
    input: '',
    messages: [],
    meta: '',
    applyPreset() {
      const p = this.cfg.provider;
      if (p === 'openai') { this.cfg.base_url = 'https://api.openai.com/v1'; this.cfg.model = 'gpt-4o-mini'; }
      if (p === 'gemini') { this.cfg.base_url = 'https://generativelanguage.googleapis.com/v1beta/openai'; this.cfg.model = 'gemini-2.0-flash'; }
      if (p === 'xai') { this.cfg.base_url = 'https://api.x.ai/v1'; this.cfg.model = 'grok-2-latest'; }
      if (p === 'groq') { this.cfg.base_url = 'https://api.groq.com/openai/v1'; this.cfg.model = 'llama-3.3-70b-versatile'; }
      if (p === 'ollama') { this.cfg.base_url = 'http://127.0.0.1:11434/v1'; this.cfg.model = 'llama3.2'; }
    },
    connectProvider(id) {
      this.cfg.provider = id;
      this.cfg.enabled = true;
      this.applyPreset();
      this.showCfg = true;
      this.meta = 'Selected ' + id + ' — paste API key and Save & connect';
      this.$nextTick(() => {
        const el = document.querySelector('input[type=password]');
        if (el) el.focus();
      });
    },
    keyPlaceholder() {
      const p = this.cfg.provider;
      if (p === 'openai') return 'sk-… from platform.openai.com';
      if (p === 'gemini') return 'AIza… from aistudio.google.com';
      if (p === 'groq') return 'gsk_… from console.groq.com';
      if (p === 'xai') return 'xAI API key from console.x.ai';
      if (p === 'ollama') return 'optional (default: ollama)';
      return 'API key';
    },
    keyHelpUrl() {
      const p = this.cfg.provider;
      if (p === 'openai') return 'https://platform.openai.com/api-keys';
      if (p === 'gemini') return 'https://aistudio.google.com/apikey';
      if (p === 'groq') return 'https://console.groq.com/keys';
      if (p === 'xai') return 'https://console.x.ai/';
      if (p === 'ollama') return 'https://ollama.com/download';
      return 'https://platform.openai.com/api-keys';
    },
    connectHint() {
      const p = this.cfg.provider;
      if (p === 'openai') return 'ChatGPT Pro (chat.openai.com) ≠ API. Create a key at platform.openai.com (billing may use the same OpenAI account).';
      if (p === 'gemini') return 'Gemini app subscription ≠ API. Create a free AI Studio key; upgrade project quotas if you need higher limits.';
      if (p === 'groq') return 'Generous free tier — good default for RC playbooks when you want zero cloud cost.';
      if (p === 'xai') return 'Grok via xAI API — OpenAI-compatible base URL is prefilled.';
      if (p === 'ollama') return 'Install Ollama on the RC server, pull a model (e.g. llama3.2), then Save. No cloud charges.';
      return '';
    },
    async saveCfg() {
      this.cfgErr = '';
      this.busy = true;
      const body = new URLSearchParams();
      body.set('action', 'ai_config_save');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('payload', JSON.stringify({
        enabled: this.cfg.enabled === true || this.cfg.enabled === 'true',
        provider: this.cfg.provider,
        base_url: this.cfg.base_url,
        model: this.cfg.model,
        api_key: this.cfg.api_key
      }));
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body, credentials: 'same-origin' });
        const j = await parseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'Save failed');
        this.ready = !!j.ready;
        this.showCfg = false;
        this.meta = 'Settings saved';
        this.cfg.api_key = '';
      } catch (e) {
        this.cfgErr = String(e.message || e);
      }
      this.busy = false;
    },
    clearChat() { this.messages = []; this.meta = ''; },
    async copyText(t) {
      try { await navigator.clipboard.writeText(t); this.meta = 'Copied'; } catch (e) { this.meta = 'Copy failed'; }
    },
    async send(extraUser, forceKind) {
      const text = (extraUser != null ? extraUser : this.input).trim();
      if (!text || this.busy) return;
      if (!this.ready) { alert('Configure and enable AI in Settings first.'); return; }
      this.messages.push({ role: 'user', content: text });
      if (extraUser == null) this.input = '';
      this.busy = true;
      this.meta = '';
      const body = new URLSearchParams();
      body.set('action', 'ai_chat');
      body.set('csrf', csrf);
      body.set('csrf_token', csrf);
      body.set('messages', JSON.stringify(this.messages.slice(-16)));
      if (forceKind) body.set('context_kind', forceKind);
      try {
        const r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body, credentials: 'same-origin' });
        const j = await parseJson(r);
        if (j.status !== 'success') throw new Error(j.message || 'AI error');
        this.messages.push({ role: 'assistant', content: j.text || '' });
        this.meta = [j.model ? ('Model: ' + j.model) : '', j.kind ? ('Context: ' + j.kind) : ''].filter(Boolean).join(' · ');
        this.$nextTick(() => {
          const el = document.getElementById('rc-ai-scroll');
          if (el) el.scrollTop = el.scrollHeight;
        });
      } catch (e) {
        this.messages.push({ role: 'assistant', content: '⚠️ ' + String(e.message || e) });
      }
      this.busy = false;
    },
    quick(id) {
      const p = playbooks[id];
      if (!p) return;
      // special: translate uses last assistant message
      if (id === 'translate_hi') {
        let last = '';
        for (let i = this.messages.length - 1; i >= 0; i--) {
          if (this.messages[i].role === 'assistant' && this.messages[i].content && this.messages[i].content.indexOf('⚠️') !== 0) {
            last = this.messages[i].content; break;
          }
        }
        if (!last) { this.send('No previous answer to translate. Reply with a short Hindi greeting instead.', 'general'); return; }
        this.send('Translate this into clear Hindi (Devanagari), keep bullets:\n\n' + last, 'general');
        return;
      }
      this.send(p.text, p.kind);
    }
  };
};
</script>
