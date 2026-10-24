<?php

declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$team = [];
if (class_exists('AppDB')) {
    $raw = AppDB::read('team');
    if (is_array($raw)) {
        $team = array_values(array_filter($raw, 'is_array'));
    }
}
$tz = new DateTimeZone('Asia/Kolkata');
$today = new DateTime('today', $tz);
$horizon = (clone $today)->modify('+14 days');
$items = [];

foreach ($team as $m) {
    $name = trim((string)($m['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $slug = (string)($m['slug'] ?? $m['id'] ?? '');
    $phone = (string)($m['phone'] ?? $m['mobile'] ?? '');
    $photo = (string)($m['photo'] ?? $m['image'] ?? '');

     
    $dob = trim((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
    if ($dob !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $dm)) {
        try {
            $mmdd = $dm[2] . '-' . $dm[3];
            $next = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $mmdd, $tz);
            if (!$next) {
                continue;
            }
            if ($next < $today) {
                $next->modify('+1 year');
            }
            if ($next <= $horizon) {
                $days = (int)$today->diff($next)->days;
                $items[] = [
                    'kind' => 'birthday',
                    'icon' => '🎂',
                    'label' => 'Birthday',
                    'name' => $name,
                    'slug' => $slug,
                    'phone' => $phone,
                    'photo' => $photo,
                    'when' => $next->format('Y-m-d'),
                    'when_label' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $next->format('D, d M')),
                    'days' => $days,
                    'sort' => $days,
                ];
            }
        } catch (Throwable $e) {
        }
    }

     
    $doj = trim((string)($m['doj'] ?? $m['joining_date'] ?? $m['date_of_joining'] ?? $m['join_date'] ?? ''));
    if ($doj !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $doj, $jm)) {
        try {
            $mmdd = $jm[2] . '-' . $jm[3];
            $next = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $mmdd, $tz);
            if (!$next) {
                continue;
            }
            if ($next < $today) {
                $next->modify('+1 year');
            }
            if ($next <= $horizon) {
                $years = (int)$today->format('Y') - (int)$jm[1];
                if ($next->format('Y') > $today->format('Y') || ($next == $today && (int)$jm[2] === (int)$today->format('m') && (int)$jm[3] === (int)$today->format('d'))) {
                     
                    $years = (int)$next->format('Y') - (int)$jm[1];
                }
                $days = (int)$today->diff($next)->days;
                $items[] = [
                    'kind' => 'anniversary',
                    'icon' => '🏅',
                    'label' => $years > 0 ? ($years . 'y work anniversary') : 'Work anniversary',
                    'name' => $name,
                    'slug' => $slug,
                    'phone' => $phone,
                    'photo' => $photo,
                    'when' => $next->format('Y-m-d'),
                    'when_label' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $next->format('D, d M')),
                    'days' => $days,
                    'sort' => $days,
                ];
            }
        } catch (Throwable $e) {
        }
    }
}

usort($items, static fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['name'], $b['name']));
$items = array_slice($items, 0, 12);
if (!$items) {
    return;
}
?>
<?php
$rangeLabel = $today->format('d M') . ' – ' . $horizon->format('d M');
?>
<article id="rc-bday-strip" class="rc-mwidget rc-mwidget--celeb" role="region" aria-label="Upcoming birthdays and work anniversaries">
  <header class="rc-mwidget__bar rc-mwidget__bar--celeb">
    <span class="rc-mwidget__icon" aria-hidden="true">🎉</span>
    <span class="rc-mwidget__title">Celebrations</span>
    <span class="rc-mwidget__chip tabular-nums"><?= (int) count($items) ?></span>
    <span class="rc-mwidget__range tabular-nums"><?= $h($rangeLabel) ?></span>
  </header>
  <div class="rc-celeb-scroll" style="-webkit-overflow-scrolling:touch">
<?php foreach ($items as $it):
  $wa = '';
  if ($it['phone'] !== '') {
    $digits = preg_replace('/\D+/', '', $it['phone']);
    if (strlen($digits) >= 10) {
      if (strlen($digits) === 10) {
        $digits = '91' . $digits;
      }
      $msg = rawurlencode(
        $it['kind'] === 'birthday'
          ? ('Happy Birthday ' . $it['name'] . '! Wishing you a wonderful year ahead.')
          : ('Congratulations ' . $it['name'] . ' on your work anniversary!')
      );
      $wa = 'https://wa.me/' . $digits . '?text=' . $msg;
    }
  }
  $isToday = ((int) $it['days'] === 0);
?>
    <div class="rc-celeb-card<?= $isToday ? ' rc-celeb-card--today' : '' ?>">
      <div class="rc-celeb-card__media">
        <div class="rc-celeb-avatar" aria-hidden="true">
          <?php if ($it['photo'] !== ''): ?>
            <img src="/media_serve.php?f=<?= $h(rawurlencode(basename($it['photo']))) ?>" alt="" width="48" height="48" loading="lazy" onerror="this.remove()">
          <?php else: ?>
            <?= $h(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?>
          <?php endif; ?>
        </div>
        <span class="rc-celeb-when<?= $isToday ? ' rc-celeb-when--today' : '' ?>"><?= $h($it['when_label']) ?></span>
      </div>
      <div class="rc-celeb-card__text">
        <p class="rc-celeb-name"><?= $h($it['name']) ?></p>
        <p class="rc-celeb-kind"><?= $h($it['icon'] . ' ' . $it['label']) ?></p>
      </div>
      <?php if ($wa !== ''): ?>
        <a class="rc-celeb-wa" href="<?= $h($wa) ?>" target="_blank" rel="noopener noreferrer" title="Wish on WhatsApp" aria-label="Wish <?= $h($it['name']) ?> on WhatsApp">WA</a>
      <?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
</article>
