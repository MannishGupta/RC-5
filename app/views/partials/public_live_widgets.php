<?php
// Version: 5.2.0 — Modern live widgets (Fluent / iOS-style)
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$lat = 28.6595;
$lng = 77.3170;
if (class_exists('AppDB')) {
    $co = AppDB::read('company');
    if (is_array($co)) {
        $row = isset($co['name']) ? $co : ($co[0] ?? []);
        if (is_array($row)) {
            if (isset($row['lat']) && is_numeric($row['lat'])) {
                $lat = (float) $row['lat'];
            }
            if (isset($row['lng']) && is_numeric($row['lng'])) {
                $lng = (float) $row['lng'];
            }
        }
    }
}
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="rc-public-widgets" class="contents" role="region" aria-label="Live local information">
  <!-- Wall clock -->
  <article class="rc-mwidget rc-mwidget--clock" aria-label="Wall clock India Standard Time">
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

  <!-- Weather -->
  <article class="rc-mwidget rc-mwidget--wx" aria-label="Local weather">
    <header class="rc-mwidget__bar">
      <span class="rc-mwidget__icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.75"/><path d="M12 3v1.5M12 19.5V21M3 12h1.5M19.5 12H21M5.6 5.6l1.1 1.1M17.3 17.3l1.1 1.1M5.6 18.4l1.1-1.1M17.3 6.7l1.1-1.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </span>
      <span class="rc-mwidget__title">Weather</span>
    </header>
    <div class="rc-mwidget__body rc-mwidget__body--split">
      <div>
        <p id="rc-wx-temp" class="rc-mwidget__hero tabular-nums">—°</p>
        <p id="rc-wx-desc" class="rc-mwidget__sub">Loading…</p>
      </div>
      <span id="rc-wx-icon" class="rc-mwidget__emoji" aria-hidden="true">🌤️</span>
    </div>
  </article>

  <!-- Air quality -->
  <article class="rc-mwidget rc-mwidget--aqi" aria-label="Air quality index">
    <header class="rc-mwidget__bar">
      <span class="rc-mwidget__icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 14h10a3 3 0 1 0 0-6H5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M4 10h12a2.5 2.5 0 1 1 0 5H8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
      </span>
      <span class="rc-mwidget__title">Air quality</span>
      <span id="rc-aqi-badge" class="rc-mwidget__chip">US AQI</span>
    </header>
    <div class="rc-mwidget__body">
      <p class="rc-mwidget__hero-row">
        <span id="rc-aqi-val" class="rc-mwidget__hero tabular-nums">—</span>
        <span id="rc-aqi-label" class="rc-mwidget__pill">—</span>
      </p>
      <p id="rc-aqi-detail" class="rc-mwidget__sub">Loading…</p>
    </div>
  </article>
</div>
<script>
(function () {
  var lat = <?= json_encode($lat) ?>;
  var lng = <?= json_encode($lng) ?>;
  var tz = 'Asia/Kolkata';

  function tickClock() {
    try {
      var now = new Date();
      var t = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
      }).format(now);
      var d = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz, weekday: 'short', day: 'numeric', month: 'short'
      }).format(now);
      var cEl = document.getElementById('rc-wall-clock');
      var dEl = document.getElementById('rc-wall-date');
      if (cEl) cEl.textContent = t.replace(/\s/g, ' ');
      if (dEl) dEl.textContent = d;
    } catch (e) {}
  }
  tickClock();
  setInterval(tickClock, 1000);

  function wxLabel(code) {
    var c = Number(code);
    if (c === 0) return ['Clear', '☀️'];
    if (c <= 3) return ['Partly cloudy', '⛅'];
    if (c <= 48) return ['Fog', '🌫️'];
    if (c <= 57) return ['Drizzle', '🌦️'];
    if (c <= 67) return ['Rain', '🌧️'];
    if (c <= 77) return ['Snow', '❄️'];
    if (c <= 82) return ['Showers', '🌦️'];
    if (c <= 99) return ['Thunderstorm', '⛈️'];
    return ['—', '🌤️'];
  }
  function aqiBand(aqi) {
    aqi = Number(aqi);
    if (isNaN(aqi)) return { label: '—', color: 'var(--rc-ink-muted)', tone: 'muted' };
    if (aqi <= 50) return { label: 'Good', color: '#16a34a', tone: 'good' };
    if (aqi <= 100) return { label: 'Moderate', color: '#ca8a04', tone: 'mod' };
    if (aqi <= 150) return { label: 'Unhealthy*', color: '#ea580c', tone: 'usg' };
    if (aqi <= 200) return { label: 'Unhealthy', color: '#dc2626', tone: 'bad' };
    if (aqi <= 300) return { label: 'Very unhealthy', color: '#9333ea', tone: 'vbad' };
    return { label: 'Hazardous', color: '#7f1d1d', tone: 'haz' };
  }

  fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lng
    + '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=' + encodeURIComponent(tz))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var c = data.current || {};
      var pair = wxLabel(c.weather_code);
      var tEl = document.getElementById('rc-wx-temp');
      var iEl = document.getElementById('rc-wx-icon');
      var dEl = document.getElementById('rc-wx-desc');
      if (tEl && c.temperature_2m != null) tEl.textContent = Math.round(c.temperature_2m) + '°';
      if (iEl) iEl.textContent = pair[1];
      if (dEl) {
        var bits = [pair[0]];
        if (c.relative_humidity_2m != null) bits.push(c.relative_humidity_2m + '% RH');
        if (c.wind_speed_10m != null) bits.push(Math.round(c.wind_speed_10m) + ' km/h');
        dEl.textContent = bits.join(' · ');
      }
    })
    .catch(function () {
      var dEl = document.getElementById('rc-wx-desc');
      if (dEl) dEl.textContent = 'Unavailable';
    });

  fetch('https://air-quality-api.open-meteo.com/v1/air-quality?latitude=' + lat + '&longitude=' + lng
    + '&current=us_aqi,pm2_5,pm10&timezone=' + encodeURIComponent(tz))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var c = data.current || {};
      var aqi = c.us_aqi;
      var band = aqiBand(aqi);
      var vEl = document.getElementById('rc-aqi-val');
      var lEl = document.getElementById('rc-aqi-label');
      var dEl = document.getElementById('rc-aqi-detail');
      if (vEl && aqi != null) {
        vEl.textContent = String(Math.round(aqi));
        vEl.style.color = band.color;
      }
      if (lEl) {
        lEl.textContent = band.label;
        lEl.setAttribute('data-tone', band.tone);
      }
      if (dEl) {
        var pm = [];
        if (c.pm2_5 != null) pm.push('PM2.5 ' + Math.round(c.pm2_5));
        if (c.pm10 != null) pm.push('PM10 ' + Math.round(c.pm10));
        dEl.textContent = pm.length ? pm.join(' · ') + ' µg/m³' : '—';
      }
    })
    .catch(function () {
      var dEl = document.getElementById('rc-aqi-detail');
      if (dEl) dEl.textContent = 'Unavailable';
    });
})();
</script>
