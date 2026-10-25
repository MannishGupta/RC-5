<?php
// Version: 5.2.7 — unified chrome title; no duplicate Insights header; no clock widget
if (!defined('BASE_PATH')) {
    exit;
}
require_once __DIR__ . '/../partials/tw_page.php';

$th = preg_replace('/[^a-z]/', '', strtolower((string)($_COOKIE['rc_theme'] ?? ''))) ?: '';
if ($th === '') {
    $th = 'reserve';
}
$src = '/choghadiya.php?embed=1&theme=' . rawurlencode($th);
?>
<div class="rc-page rc-choghadiya-shell w-full -mx-1" style="min-height:70vh">
  <?php /* Chrome bar already shows "Choghadiya" — avoid Insights / subtitle / clock duplication */ ?>
  <h1 class="sr-only">Choghadiya</h1>

  <iframe
    id="rc-choghadiya-frame"
    src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"
    title="Choghadiya and Panchang"
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
    var f = document.getElementById('rc-choghadiya-frame');
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
