<div class="dashboard-header">
  <div>
    <h2>لوحة المشرف الأكاديمي</h2>
    <p class="sub">نظرة عامة على اليوم الدراسي</p>
  </div>
  <div class="date-badge">
    <div class="today-day"><?= $dayName ?></div>
    <div class="today-date"><?= date('d / m / Y') ?></div>
    <div id="live-clock" class="live-clock">--:--:--</div>
  </div>
</div>

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card stat-absent">
    <div class="stat-num"><?= count($absentTeachers) ?></div>
    <div class="stat-label">🚫 غائبون اليوم</div>
  </div>
  <div class="stat-card stat-free">
    <div class="stat-num" id="free-count"><?= count($freeNow) ?></div>
    <div class="stat-label">🟢 متاحون الآن (ح<?= $curPeriod ?? '—' ?>)</div>
  </div>
  <div class="stat-card stat-period">
    <div class="stat-num"><?= $curPeriod ?? '—' ?></div>
    <div class="stat-label">🕐 الحصة الحالية</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($allTeachersCount) ?></div>
    <div class="stat-label">👩‍🏫 إجمالي المدرسين</div>
  </div>
</div>

<!-- Today timeline -->
<div class="panel">
  <div class="panel-header">🕐 توقيت اليوم — <?= $dayName ?></div>
  <div class="timeline">
    <?php foreach ($periods as $key => $p): ?>
    <div class="timeline-slot <?= $p['type'] ?> <?= $key == $curPeriod ? 'active' : '' ?>" title="<?= $p['start'] ?> - <?= $p['end'] ?>">
      <div class="slot-label"><?= $p['type']==='break'?'☕':($p['type']==='assembly'?'🎓':$p['label']) ?></div>
      <div class="slot-time"><?= $p['start'] ?><br><?= $p['end'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Two panels -->
<div class="two-col">

  <!-- Absent -->
  <div class="panel">
    <div class="panel-header">🚫 الغائبون اليوم
      <span style="margin-right:auto;background:rgba(244,63,94,.3);padding:2px 10px;border-radius:20px;font-size:.8rem">
        <?= count($absentTeachers) ?>
      </span>
    </div>
    <div class="panel-body">
      <?php if (empty($absentTeachers)): ?>
        <p class="empty">✅ لا غياب مسجل اليوم</p>
      <?php else: ?>
        <?php foreach ($absentTeachers as $t): ?>
        <div class="teacher-row">
          <div class="t-avatar absent-av" style="width:38px;height:38px;font-size:.85rem"><?= strtoupper(substr($t['name'],0,2)) ?></div>
          <div style="flex:1">
            <div class="t-name"><?= htmlspecialchars($t['name']) ?></div>
            <?php if ($t['reason']): ?><div class="t-reason"><?= htmlspecialchars($t['reason']) ?></div><?php endif; ?>
          </div>
          <a href="coverage.php?date=<?= date('Y-m-d') ?>&tid=<?= $t['id'] ?>" class="btn btn-primary btn-xs">تغطية</a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div class="divider"></div>
      <a href="absences.php" class="btn btn-outline btn-sm">إدارة الغياب الكامل</a>
    </div>
  </div>

  <!-- Free now -->
  <div class="panel">
    <div class="panel-header">🟢 متاحون الآن — حصة <?= $curPeriod ?? '—' ?>
      <span style="margin-right:auto;background:rgba(16,185,129,.25);padding:2px 10px;border-radius:20px;font-size:.8rem">
        <?= count($freeNow) ?>
      </span>
    </div>
    <div class="panel-body">
      <?php if (!$curPeriod): ?>
        <p class="empty">لا توجد حصة نشطة الآن</p>
      <?php elseif (empty($freeNow)): ?>
        <p class="empty">جميع المدرسين مشغولون الآن</p>
      <?php else: ?>
        <?php foreach ($freeNow as $t): ?>
        <div class="teacher-row">
          <div class="t-avatar free-av" style="width:38px;height:38px;font-size:.85rem"><?= strtoupper(substr($t['name'],0,2)) ?></div>
          <span class="t-name"><?= htmlspecialchars($t['name']) ?></span>
          <span class="badge badge-free">فارغ</span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Quick nav cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-top:8px">
  <?php $navCards = [
    ['absences.php',       '🚫', 'الغياب',       'سجّل غياب المدرسين'],
    ['coverage.php',       '🔄', 'التغطية',      'عيّن بدائل للغائبين'],
    ['teacher_schedule.php','📋','جدول مدرس',    'اعرض وعدّل أي جدول'],
    ['reports.php',        '📊', 'التقارير',     'إحصاءات التغطية والغياب'],
  ]; ?>
  <?php foreach ($navCards as [$url, $icon, $title, $desc]): ?>
  <a href="<?= $url ?>" style="background:#fff;border-radius:14px;padding:20px 18px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.05);border:2px solid #e2e8f0;text-decoration:none;color:inherit;transition:all .2s;display:block"
     onmouseover="this.style.borderColor='#1d4ed8';this.style.transform='translateY(-2px)'"
     onmouseout="this.style.borderColor='#e2e8f0';this.style.transform=''">
    <div style="font-size:1.8rem;margin-bottom:8px"><?= $icon ?></div>
    <div style="font-weight:800;font-size:.95rem;color:#0f2545;margin-bottom:4px"><?= $title ?></div>
    <div style="font-size:.8rem;color:#64748b"><?= $desc ?></div>
  </a>
  <?php endforeach; ?>
</div>
