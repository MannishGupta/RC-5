<?php
// Version: 5.2.7 — unified chrome title (Markets); no stacked kicker/subtitle
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

$th = preg_replace('/[^a-z]/', '', strtolower((string)($_COOKIE['rc_theme'] ?? ''))) ?: '';
if ($th === '') {
    $th = 'reserve';
}
$src = '/metals.php?embed=1&theme=' . rawurlencode($th);
?>
<div class="rc-page rc-metals-shell w-full -mx-1" style="min-height:70vh">
  <?php /* Chrome bar shows "Markets" — single title surface */ ?>
  <h1 class="sr-only">Markets — Delhi NCR &amp; MCX with GST</h1>

  <iframe
    id="rc-metals-frame"
    src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
    title="Markets — commodities and INR FX"
    class="w-full rounded-2xl border-0 shadow-sm"
    style="min-height:82vh;height:calc(100vh - 5.5rem);background:transparent"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
<script>
(function () {
  try {
    var th = document.documentElement.getAttribute('data-theme') || 'light';
    var f = document.getElementById('rc-metals-frame');
    if (!f) return;
    var u = new URL(f.src, location.origin);
    if (u.searchParams.get('theme') !== th) {
      u.searchParams.set('theme', th);
      u.searchParams.set('embed', '1');
      f.src = u.pathname + u.search;
    }
  } catch (e) {}
})();
</script>
