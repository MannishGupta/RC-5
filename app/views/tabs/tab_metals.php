<?php
// Version: 3.2.39
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

$th = preg_replace('/[^a-z]/', '', strtolower((string)($_COOKIE['rc_theme'] ?? ''))) ?: '';
?>
<div class="rc-page rc-metals-shell w-full -mx-1" style="min-height:70vh">
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Markets</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">Commodity prices</h1>
      <p class="rc-page-sub text-sm">Delhi NCR & MCX with GST</p>
    </div>
  </header>

  <iframe
    id="rc-metals-frame"
    src="/metals.php?embed=1"
    title="Markets — commodities and INR FX"
    class="w-full rounded-2xl border-0 shadow-sm"
    style="min-height:78vh;height:calc(100vh - 7.5rem);background:transparent"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
<script>
(function () {
  try {
    var th = document.documentElement.getAttribute('data-theme') || 'light';
    var f = document.getElementById('rc-metals-frame');
    if (f && f.src.indexOf('theme=') < 0) {
      f.src = f.src + (f.src.indexOf('?') >= 0 ? '&' : '?') + 'theme=' + encodeURIComponent(th);
    }
  } catch (e) {}
})();
</script>
