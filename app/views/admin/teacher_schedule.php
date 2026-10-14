<!-- Teacher Selector -->
<div class="page-header">
  <div>
    <h2>جدول المدرس</h2>
    <?php if ($selectedTeacher): ?>
    <p style="color:#64748b;margin-top:4px;font-weight:600"><?= htmlspecialchars($selectedTeacher['name']) ?></p>
    <?php endif; ?>
  </div>
  <?php if ($selectedTeacher): ?>
  <div class="day-tabs">
    <?php foreach (SCHOOL_DAYS as $d): ?>
    <a href="?tid=<?= $selectedTeacherId ?>&day=<?= $d ?>" class="tab <?= $d===$selectedDay?'active':'' ?>"><?= $d ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Teacher picker grid -->
<?php if (!$selectedTeacher): ?>
<div class="panel">
  <div class="panel-header">👩‍🏫 اختر مدرساً</div>
  <div class="panel-body">
    <div class="teacher-select-grid">
    <?php foreach ($allTeachers as $t): ?>
      <?php
        $wkCount = 0;
        // quick load count
      ?>
      <a href="?tid=<?= $t['id'] ?>&day=<?= $selectedDay ?>" class="teacher-select-card">
        <div class="tsc-avatar"><?= strtoupper(substr($t['name'],0,2)) ?></div>
        <div class="tsc-name"><?= htmlspecialchars($t['name']) ?></div>
        <div class="tsc-load">عرض الجدول</div>
      </a>
    <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else: ?>

<!-- Schedule card grid for selected teacher + day -->
<div class="panel">
  <div class="panel-header">
    📋 جدول <?= htmlspecialchars($selectedTeacher['name']) ?> — <?= $selectedDay ?>
    <span style="margin-right:auto">
      <a href="?tid=<?= $selectedTeacherId ?>&day=<?= $selectedDay ?>" style="background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:.8rem;color:#fff;text-decoration:none">تحديث</a>
    </span>
  </div>
  <div class="panel-body">

    <!-- Cards grid -->
    <div class="schedule-cards-grid">
      <?php foreach ($periods as $key => $p):
        $slot   = $scheduleMap[$key] ?? null;
        $isFree = ($slot === null);
        $isCur  = ($key == $curPeriod);
        $typeClass = '';
        if ($p['type'] === 'break') $typeClass = 'is-break';
        elseif ($slot && str_contains(strtolower($slot['activity']), 'supervision')) $typeClass = 'is-supervision';
        elseif ($slot && $slot['activity_type'] === 'nasp') $typeClass = 'is-nasp';
        elseif ($slot && $slot['activity_type'] === 'cca')  $typeClass = 'is-cca';
        elseif ($isFree && $p['type']==='period') $typeClass = 'is-free';
      ?>
      <div class="schedule-card <?= $typeClass ?> <?= $isCur ? 'is-current' : '' ?>">
        <?php if ($isCur): ?><div class="sc-now-badge">الآن</div><?php endif; ?>
        <div class="sc-header">
          <span class="sc-period-badge">
            <?= $p['type']==='break' ? '☕ بريك' : ($p['type']==='assembly' ? '🎓 تجمع' : 'ح ' . $p['label']) ?>
          </span>
          <span class="sc-time">⏱ <?= $p['start'] ?> – <?= $p['end'] ?></span>
        </div>
        <div class="sc-body">
          <?php if ($p['type'] === 'break'): ?>
            <div class="sc-class" style="font-size:1.2rem;color:#92400e">استراحة</div>
          <?php elseif ($slot): ?>
            <div class="sc-class"><?= htmlspecialchars($slot['activity']) ?></div>
            <div class="sc-type">
              <span class="badge badge-<?= $slot['activity_type'] ?>"><?= $slot['activity_type'] ?></span>
            </div>
          <?php else: ?>
            <div class="sc-class" style="font-size:1.1rem;color:#94a3b8">— فارغة —</div>
            <div class="sc-type" style="color:#cbd5e1">لا يوجد نشاط</div>
          <?php endif; ?>
        </div>

        <?php if ($p['type'] !== 'break'): ?>
        <div class="sc-footer">
          <?php if ($slot): ?>
            <!-- Edit button -->
            <button class="btn btn-ghost btn-xs" onclick="openEditModal(<?= $slot['id'] ?>, '<?= htmlspecialchars(addslashes($slot['activity'])) ?>', '<?= $slot['activity_type'] ?>')">✏️ تعديل</button>
            <!-- Delete -->
            <form method="POST" style="display:inline" onsubmit="return confirm('حذف هذه الحصة؟')">
              <input type="hidden" name="action" value="delete_period">
              <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
              <input type="hidden" name="teacher_id" value="<?= $selectedTeacherId ?>">
              <button type="submit" class="btn btn-danger btn-xs">🗑 حذف</button>
            </form>
          <?php else: ?>
            <!-- Add -->
            <button class="btn btn-primary btn-xs" onclick="openAddModal('<?= $key ?>', '<?= $p['start'] ?> – <?= $p['end'] ?>')">＋ إضافة</button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- Weekly load summary -->
<?php if (!empty($weekSummary)): ?>
<div class="panel">
  <div class="panel-header">📊 الحمل الأسبوعي</div>
  <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap">
    <?php
    $dayMap = [];
    foreach ($weekSummary as $r) $dayMap[$r['day_name']] = $r['cnt'];
    foreach (SCHOOL_DAYS as $d):
      $cnt = $dayMap[$d] ?? 0;
    ?>
    <div style="text-align:center;background:#f8fafc;border-radius:12px;padding:14px 20px;min-width:100px;border:2px solid <?= $d===$selectedDay?'#1d4ed8':'#e2e8f0' ?>">
      <div style="font-size:1.6rem;font-weight:900;color:#0f2545"><?= $cnt ?></div>
      <div style="font-size:.8rem;color:#64748b;font-weight:700"><?= $d ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Change teacher link -->
<a href="teacher_schedule.php" class="btn btn-ghost btn-sm">← اختر مدرساً آخر</a>

<!-- ── ADD MODAL ── -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    <div class="modal-title">➕ إضافة حصة</div>
    <form method="POST">
      <input type="hidden" name="action" value="add_period">
      <input type="hidden" name="teacher_id" value="<?= $selectedTeacherId ?>">
      <input type="hidden" name="day_name" value="<?= $selectedDay ?>">
      <input type="hidden" name="period_key" id="add_period_key">
      <div class="form-group">
        <label>الحصة</label>
        <input type="text" id="add_period_label" class="form-control" readonly style="background:#f8fafc">
      </div>
      <div class="form-group">
        <label>النشاط / الفصل</label>
        <input type="text" name="activity" class="form-control" placeholder="مثال: G5، NASP 1Q، Supervision B1" required>
      </div>
      <div class="form-group">
        <label>نوع النشاط</label>
        <select name="activity_type" class="form-control">
          <option value="class">حصة دراسية</option>
          <option value="supervision">إشراف</option>
          <option value="nasp">NASP</option>
          <option value="cca">CCA</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-full">حفظ الحصة</button>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    <div class="modal-title">✏️ تعديل الحصة</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_period">
      <input type="hidden" name="teacher_id" value="<?= $selectedTeacherId ?>">
      <input type="hidden" name="day_name" value="<?= $selectedDay ?>">
      <input type="hidden" name="slot_id" id="edit_slot_id">
      <div class="form-group">
        <label>النشاط / الفصل</label>
        <input type="text" name="activity" id="edit_activity" class="form-control" required>
      </div>
      <div class="form-group">
        <label>نوع النشاط</label>
        <select name="activity_type" id="edit_activity_type" class="form-control">
          <option value="class">حصة دراسية</option>
          <option value="supervision">إشراف</option>
          <option value="nasp">NASP</option>
          <option value="cca">CCA</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-full">حفظ التعديل</button>
    </form>
  </div>
</div>

<?php endif; ?>
