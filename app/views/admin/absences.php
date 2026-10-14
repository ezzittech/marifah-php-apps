<div class="page-header">
  <h2>إدارة الغياب</h2>
  <input type="date" value="<?= $date ?>" onchange="location='absences.php?date='+this.value" class="date-picker">
</div>
<div class="teachers-grid">
<?php foreach ($teachers as $t): ?>
<div class="teacher-card <?= in_array($t['id'], $absentIds) ? 'is-absent' : '' ?>">
  <div class="t-avatar <?= in_array($t['id'], $absentIds) ? 'absent-av' : '' ?>"><?= strtoupper(substr($t['name'],0,2)) ?></div>
  <div class="t-name"><?= htmlspecialchars($t['name']) ?></div>
  <div class="t-status">
    <?php if (in_array($t['id'], $absentIds)): ?>
    <form method="POST">
      <input type="hidden" name="action" value="mark_present">
      <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
      <button class="btn btn-success btn-sm">✅ حاضر</button>
    </form>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="action" value="mark_absent">
      <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
      <input type="text" name="reason" placeholder="السبب..." class="form-control-sm">
      <button class="btn btn-danger btn-sm">🚫 غائب</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
