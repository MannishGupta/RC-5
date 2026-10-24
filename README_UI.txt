RC UI fix — single header title + theme row + default Reserve
- app/views/dashboard.php
  * Default theme = reserve (html + localStorage fallback)
  * Theme icons + logout on ONE row (small 28px controls)
  * Sidebar section labels only when group has 2+ items
  * Search hidden on health/audit (no double chrome)
  * health header title: Health & Tools
- tab_*.php: removed in-page H1/H2 that repeated the main header title

Clear site data or localStorage key rc-theme once if an old theme is stuck.
