<div class="page-header">
  <h2>إدارة التغطية</h2>
  <input type="date" value="<?= $date ?>" onchange="location='coverage.php?date='+this.value" class="date-picker">
</div>

<?php if (empty($absent)): ?>
  <div class="alert">لا يوجد مدرسون غائبون في هذا اليوم</div>
<?php else: ?>
<div class="panel">
  <div class="panel-header">تعيين تغطية جديدة</div>
  <div class="panel-body">
    <form method="POST">
      <input type="hidden" name="action" value="assign">
      <div class="form-row">
        <div class="form-group">
          <label>المدرس الغائب</label>
          <select name="absent_teacher_id" class="form-control" required>
            <?php foreach ($absent as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>الحصة</label>
          <select name="period_key" class="form-control" required>
            <?php foreach ($periods as $k => $p): if ($p['type']==='period'): ?>
            <option value="<?= $k ?>">حصة <?= $p['label'] ?> (<?= $p['start'] ?>)</option>
            <?php endif; endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>المدرس البديل</label>
          <select name="cover_teacher_id" class="form-control" required>
            <?php foreach ($allTeachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>الفصل</label>
          <input type="text" name="cover_class" class="form-control" placeholder="G5...">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">تعيين التغطية</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="panel mt-3">
  <div class="panel-header">سجل التغطية اليوم</div>
  <table class="data-table">
    <thead><tr><th>غائب</th><th>بديل</th><th>الحصة</th><th>الفصل</th><th>الحالة</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
      <td><?= htmlspecialchars($log['absent_name']) ?></td>
      <td><?= htmlspecialchars($log['cover_name']) ?></td>
      <td><?= $log['period_key'] ?></td>
      <td><?= htmlspecialchars($log['cover_class'] ?? '') ?></td>
      <td class="status-<?= $log['status'] ?>"><?= $log['status'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
