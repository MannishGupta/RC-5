<?php
// Version: 4.4.6 — Blood report in right pane (iframe embed)
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

$src = 'blood_report.php?embed=1';
foreach (['slug', 'name', 'group', 'blood', 'id'] as $qk) {
    if (!empty($_GET[$qk])) {
        $src .= '&' . $qk . '=' . rawurlencode((string)$_GET[$qk]);
    }
}
?>
<div class="rc-page rc-blood-shell w-full -mx-1 flex flex-col" style="min-height:70vh">
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Insights</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">Blood report</h1>
      <p class="rc-page-sub text-sm">Group profile · educational brief</p>
    </div>
  </header>

  <iframe
    id="rc-blood-frame"
    title="Blood report"
    src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
    class="w-full flex-1 rounded-2xl border-0 shadow-sm"
    style="min-height:78vh;height:calc(100vh - 7.5rem);background:var(--rc-paper,#FAF9F8)"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
<script>
(function () {
  function applyTheme() {
    try {
      var th = document.documentElement.getAttribute('data-theme') || 'light';
      var f = document.getElementById('rc-blood-frame');
      if (!f) return;
      var u = new URL(f.getAttribute('src') || 'blood_report.php?embed=1', location.origin);
      u.searchParams.set('embed', '1');
      u.searchParams.set('theme', th);
      var next = u.pathname.replace(/^\//,'') + '?' + u.searchParams.toString();
      // keep relative path
      next = 'blood_report.php?' + u.searchParams.toString();
      if ((f.getAttribute('src') || '') !== next) f.setAttribute('src', next);
    } catch (e) {}
  }
  applyTheme();
  try {
    new MutationObserver(applyTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
  } catch (e) {}
})();
</script>
