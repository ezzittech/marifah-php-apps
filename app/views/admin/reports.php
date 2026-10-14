<div class="page-header">
  <h2>تقارير وإحصاءات</h2>
  <form method="GET" class="filter-bar">
    <label>من</label>
    <input type="date" name="from" value="<?= $from ?>" class="date-picker">
    <label>إلى</label>
    <input type="date" name="to"   value="<?= $to ?>"   class="date-picker">
    <button type="submit" class="btn btn-primary">عرض</button>
  </form>
</div>

<div class="stats-row">
  <div class="stat-card"><div class="stat-num"><?= $totalCoverage ?></div><div class="stat-label">إجمالي التغطيات</div></div>
  <div class="stat-card"><div class="stat-num"><?= count($topAbsent) ?></div><div class="stat-label">مدرسون غابوا</div></div>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-header">الأكثر تغطية</div>
    <table class="data-table">
      <thead><tr><th>المدرس</th><th>عدد الحصص</th></tr></thead>
      <tbody>
      <?php foreach ($topCover as $r): ?>
        <tr><td><?= htmlspecialchars($r['name']) ?></td><td><strong><?= $r['cnt'] ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-header">الأكثر غياباً</div>
    <table class="data-table">
      <thead><tr><th>المدرس</th><th>أيام الغياب</th></tr></thead>
      <tbody>
      <?php foreach ($topAbsent as $r): ?>
        <tr><td><?= htmlspecialchars($r['name']) ?></td><td><strong><?= $r['cnt'] ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel mt-3">
  <div class="panel-header">تفاصيل كل التغطيات</div>
  <table class="data-table">
    <thead><tr><th>التاريخ</th><th>اليوم</th><th>الحصة</th><th>الغائب</th><th>البديل</th><th>الفصل</th></tr></thead>
    <tbody>
    <?php foreach ($stats as $s): ?>
      <tr>
        <td><?= $s['date'] ?></td>
        <td><?= $s['day_name'] ?></td>
        <td><?= $s['period_key'] ?></td>
        <td><?= htmlspecialchars($s['absent_teacher']) ?></td>
        <td><?= htmlspecialchars($s['cover_teacher']) ?></td>
        <td><?= htmlspecialchars($s['cover_class'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
