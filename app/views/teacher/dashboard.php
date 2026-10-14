<div class="dashboard-header">
  <div>
    <h2>مرحباً، <?= htmlspecialchars($user['name']) ?></h2>
  </div>
  <div class="date-badge">
    <div class="today-day"><?= $dayName ?></div>
    <div class="today-date"><?= date('d/m/Y') ?></div>
    <div id="live-clock" class="live-clock"></div>
  </div>
</div>
<div class="stats-row">
  <div class="stat-card stat-period">
    <div class="stat-num"><?= $curPeriod ?? '—' ?></div>
    <div class="stat-label">الحصة الحالية</div>
  </div>
</div>
<div class="panel">
  <div class="panel-header">جدولي اليوم — <?= $dayName ?></div>
  <div class="timeline">
    <?php foreach ($periods as $k => $p): ?>
    <?php $act = $myMap[$k]['activity'] ?? '—'; ?>
    <div class="timeline-slot <?= $p['type'] ?> <?= $k==$curPeriod?'active':'' ?>">
      <div class="slot-label"><?= $p['label'] ?></div>
      <div class="slot-time"><?= $p['start'] ?><br><?= $p['end'] ?></div>
      <div class="slot-act"><?= htmlspecialchars($act) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
