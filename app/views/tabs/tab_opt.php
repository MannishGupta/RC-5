<?php
/**
 * Legacy Optimisation Suite tab — folded into Health & Tools.
 * Old bookmarks (?tab=opt) still render the panel with a clear move notice.
 */
if (!defined('BASE_PATH')) {
    exit;
}
?>
<div class="space-y-3">
  <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
    <strong>Moved:</strong> Platform optimisation now lives under
    <a class="font-bold underline" href="?tab=health#rc-maintain">Health &amp; tools → Maintenance suite</a>.
    One place for optimiser, cache flush, junk cleanup, and backups.
  </div>
  <?php
    $optPanelCompact = false;
    $optPartial = __DIR__ . '/../partials/optimizer_panel.php';
    if (is_file($optPartial)) {
        require $optPartial;
    }
  ?>
</div>
