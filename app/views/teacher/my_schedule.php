<div class="page-header">
  <div>
    <h2>جدولي الأسبوعي</h2>
    <p style="color:#64748b;font-weight:600;margin-top:2px"><?= htmlspecialchars($user['name']) ?></p>
  </div>
  <div class="day-tabs">
    <?php foreach (SCHOOL_DAYS as $d): ?>
    <a href="my_schedule.php?day=<?= $d ?>" class="tab <?= $d===$dayName?'active':'' ?>"><?= $d ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Summary bar -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <?php
    $periodCount = 0;
    foreach ($scheduleMap as $k => $s) { if ($s['activity_type']!=='break') $periodCount++; }
  ?>
  <div style="background:#fff;border-radius:10px;padding:10px 18px;box-shadow:0 1px 3px rgba(0,0,0,.06);display:flex;align-items:center;gap:8px">
    <span style="font-size:1.3rem;font-weight:900;color:#0f2545"><?= $periodCount ?></span>
    <span style="font-size:.85rem;color:#64748b;font-weight:600">حصة اليوم</span>
  </div>
  <?php if ($curPeriod && isset($scheduleMap[$curPeriod])): ?>
  <div style="background:#eff6ff;border:2px solid #1d4ed8;border-radius:10px;padding:10px 18px;display:flex;align-items:center;gap:8px">
    <span style="font-size:.9rem;font-weight:700;color:#1d4ed8">الآن: <?= htmlspecialchars($scheduleMap[$curPeriod]['activity']) ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- Cards -->
<div class="schedule-cards-grid">
  <?php foreach ($periods as $key => $p):
    $slot = $scheduleMap[$key] ?? null;
    $isCur = ($key == $curPeriod);
    $typeClass = '';
    if ($p['type']==='break') $typeClass = 'is-break';
    elseif ($p['type']==='assembly') $typeClass = '';
    elseif ($slot && str_contains(strtolower($slot['activity']??''), 'supervision')) $typeClass = 'is-supervision';
    elseif ($slot && ($slot['activity_type']==='nasp')) $typeClass = 'is-nasp';
    elseif ($slot && ($slot['activity_type']==='cca'))  $typeClass = 'is-cca';
    elseif (!$slot) $typeClass = 'is-free';
  ?>
  <div class="schedule-card <?= $typeClass ?> <?= $isCur ? 'is-current' : '' ?>">
    <?php if ($isCur): ?><div class="sc-now-badge">الآن</div><?php endif; ?>
    <div class="sc-header">
      <span class="sc-period-badge">
        <?= $p['type']==='break' ? '☕ بريك' : ($p['type']==='assembly' ? '🎓' : 'ح '.$p['label']) ?>
      </span>
      <span class="sc-time">⏱ <?= $p['start'] ?> – <?= $p['end'] ?></span>
    </div>
    <div class="sc-body">
      <?php if ($p['type']==='break'): ?>
        <div class="sc-class" style="font-size:1.1rem;color:#92400e">استراحة ☕</div>
      <?php elseif ($slot): ?>
        <div class="sc-class"><?= htmlspecialchars($slot['activity']) ?></div>
        <span class="badge badge-<?= $slot['activity_type'] ?>"><?= match($slot['activity_type']) {
          'class' => 'حصة', 'supervision' => 'إشراف', 'nasp' => 'NASP', 'cca' => 'CCA', default => $slot['activity_type']
        } ?></span>
      <?php else: ?>
        <div class="sc-class" style="font-size:1rem;color:#94a3b8">— فارغة —</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
