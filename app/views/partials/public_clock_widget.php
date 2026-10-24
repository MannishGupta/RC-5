<?php
// Clock-only strip for Choghadiya (and any tab that needs IST wall clock alone)
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
if (defined('RC_CLOCK_WIDGET_RENDERED')) {
    return;
}
define('RC_CLOCK_WIDGET_RENDERED', true);
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="rc-top-widgets rc-clock-only mb-3" role="region" aria-label="India Standard Time">
  <article class="rc-mwidget rc-mwidget--clock" aria-label="Wall clock India Standard Time" style="max-width:16rem">
    <header class="rc-mwidget__bar">
      <span class="rc-mwidget__icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.75"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
      </span>
      <span class="rc-mwidget__title">Clock</span>
      <span class="rc-mwidget__chip">IST</span>
    </header>
    <div class="rc-mwidget__body">
      <p id="rc-wall-clock" class="rc-mwidget__hero tabular-nums" aria-live="polite">--:--:--</p>
      <p id="rc-wall-date" class="rc-mwidget__sub">—</p>
    </div>
  </article>
</div>
<?php
// Reuse the same clock tick script from public_live_widgets if not already on page
if (!defined('RC_CLOCK_TICK_JS')):
  define('RC_CLOCK_TICK_JS', true);
?>
<script>
(function(){
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function tick(){
    try {
      var fmt = new Intl.DateTimeFormat('en-IN', { timeZone:'Asia/Kolkata', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false });
      var dfmt = new Intl.DateTimeFormat('en-IN', { timeZone:'Asia/Kolkata', weekday:'short', day:'numeric', month:'short', year:'numeric' });
      var now = new Date();
      var el = document.getElementById('rc-wall-clock');
      var de = document.getElementById('rc-wall-date');
      if (el) el.textContent = fmt.format(now);
      if (de) de.textContent = dfmt.format(now);
    } catch (e) {}
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
