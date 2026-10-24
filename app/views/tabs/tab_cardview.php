<?php
// Version: 4.5.0 — Digital cards / reports inside right pane
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

$allowed = ['visiting', 'business', 'id', 'qr', 'signature', 'numero'];
$card = strtolower(preg_replace('/[^a-z]/', '', (string)($_GET['card'] ?? 'visiting')));
if (!in_array($card, $allowed, true)) {
    $card = 'visiting';
}
$q = ['card' => $card, 'embed' => '1'];
foreach (['slug', 'name', 'id', 'dob', 'group', 'lang'] as $k) {
    if (!empty($_GET[$k])) {
        $q[$k] = (string)$_GET[$k];
    }
}
$src = '/index.php?' . http_build_query($q);
$labels = [
    'visiting' => 'Visiting card',
    'business' => 'Business card',
    'id' => 'ID card',
    'qr' => 'QR contact',
    'signature' => 'Email signature',
    'numero' => 'Numerology report',
];
$title = $labels[$card] ?? 'Card';
?>
<div class="rc-page rc-cardview-shell w-full -mx-1 flex flex-col" style="min-height:70vh">
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Human Capital</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="rc-page-sub text-sm">Opens inside the portal · follows active theme</p>
    </div>
  </header>
  <iframe
    id="rc-cardview-frame"
    title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
    src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
    class="w-full flex-1 rounded-2xl border border-slate-200 shadow-sm"
    style="min-height:78vh;height:calc(100vh - 7.5rem);background:var(--rc-paper,#FAF9F8);border-color:var(--rc-border,#E1DFDD)"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
<script>
(function () {
  function applyTheme() {
    try {
      var th = document.documentElement.getAttribute('data-theme') || 'light';
      var f = document.getElementById('rc-cardview-frame');
      if (!f) return;
      var u = new URL(f.getAttribute('src') || location.href, location.origin);
      u.searchParams.set('embed', '1');
      u.searchParams.set('theme', th);
      var next = u.pathname + '?' + u.searchParams.toString();
      if ((f.getAttribute('src') || '') !== next) f.setAttribute('src', next);
    } catch (e) {}
  }
  applyTheme();
  try {
    new MutationObserver(applyTheme).observe(document.documentElement, {
      attributes: true, attributeFilter: ['data-theme', 'class']
    });
  } catch (e) {}
})();
</script>
