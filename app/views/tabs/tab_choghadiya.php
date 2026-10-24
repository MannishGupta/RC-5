<?php
// Version: 3.2.38
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

?>
<div class="rc-page rc-choghadiya-shell w-full -mx-1" style="min-height:70vh">
  <header class="rc-page-header flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p class="rc-page-kicker text-[11px] font-bold uppercase tracking-wider">Insights</p>
      <h1 class="rc-page-title text-xl md:text-2xl font-bold tracking-tight">Choghadiya</h1>
      <p class="rc-page-sub text-sm">Tithi, Rahu Kaal and auspicious windows</p>
    </div>
  </header>

  <iframe
    src="/choghadiya.php?embed=1"
    title="Choghadiya and Panchang"
    class="w-full rounded-2xl border-0 shadow-sm"
    style="min-height:78vh;height:calc(100vh - 7.5rem);background:transparent"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
