import os

BASE = "school_schedule"

def write(rel_path, content):
    full = os.path.join(BASE, rel_path)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"  [UPDATED] {rel_path}")

# ─────────────────────────────────────────────
print("\n[1/7] New CSS (Tailwind CDN + Cairo font + custom tokens)")
# ─────────────────────────────────────────────
write("public/assets/css/style.css", r"""/* === School Schedule System — Custom layer on top of Tailwind === */
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Cairo+Play:wght@400;600;700&display=swap');

:root {
  --navy:   #0f2545;
  --blue:   #1d4ed8;
  --sky:    #38bdf8;
  --teal:   #0d9488;
  --amber:  #f59e0b;
  --rose:   #f43f5e;
  --green:  #10b981;
  --slate:  #64748b;
  --bg:     #f1f5f9;
  --card:   #ffffff;
  --radius: 14px;
}

*, *::before, *::after { box-sizing: border-box; }

body {
  font-family: 'Cairo', 'Segoe UI', sans-serif;
  background: var(--bg);
  color: #1e293b;
  direction: rtl;
  min-height: 100vh;
}

/* ── NAVBAR ── */
.navbar {
  background: var(--navy);
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: 0 2px 16px rgba(0,0,0,.35);
}
.nav-brand {
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 10px;
  letter-spacing: .3px;
}
.nav-brand .brand-dot {
  width: 8px; height: 8px;
  background: var(--sky);
  border-radius: 50%;
  display: inline-block;
}
.nav-menu { display: flex; align-items: center; gap: 6px; }
.nav-menu a {
  color: rgba(255,255,255,.72);
  font-size: .9rem;
  font-weight: 600;
  padding: 7px 14px;
  border-radius: 8px;
  transition: all .2s;
  white-space: nowrap;
}
.nav-menu a:hover { color: #fff; background: rgba(255,255,255,.1); }
.nav-menu a.active { color: #fff; background: rgba(56,189,248,.2); }
.btn-logout {
  background: rgba(244,63,94,.15) !important;
  color: #fda4af !important;
  border: 1px solid rgba(244,63,94,.3);
}
.btn-logout:hover { background: rgba(244,63,94,.3) !important; }

/* ── CONTAINER ── */
.container { max-width: 1280px; margin: 0 auto; padding: 28px 24px; }

/* ── LOGIN ── */
.login-body {
  background: linear-gradient(135deg, var(--navy) 0%, #1e40af 60%, #0f766e 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
.login-card {
  background: rgba(255,255,255,.97);
  border-radius: 20px;
  padding: 52px 44px;
  width: 430px;
  box-shadow: 0 24px 64px rgba(0,0,0,.28);
}
.login-logo { text-align: center; margin-bottom: 36px; }
.logo-icon  { font-size: 3.4rem; display: block; margin-bottom: 10px; }
.login-logo h1 { font-size: 1.55rem; font-weight: 800; color: var(--navy); }
.login-logo p  { color: var(--slate); font-size: .92rem; margin-top: 4px; }

/* ── FORM ELEMENTS ── */
.form-group { margin-bottom: 20px; }
.form-group label {
  display: block;
  margin-bottom: 7px;
  font-weight: 700;
  font-size: .9rem;
  color: #374151;
}
.form-control {
  width: 100%;
  padding: 11px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: .95rem;
  font-family: 'Cairo', sans-serif;
  transition: border .2s, box-shadow .2s;
  background: #f8fafc;
}
.form-control:focus {
  outline: none;
  border-color: var(--blue);
  box-shadow: 0 0 0 4px rgba(29,78,216,.1);
  background: #fff;
}
.form-control-sm {
  padding: 7px 11px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: .85rem;
  font-family: 'Cairo', sans-serif;
  background: #f8fafc;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

/* ── BUTTONS ── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 22px;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  font-size: .9rem;
  font-weight: 700;
  font-family: 'Cairo', sans-serif;
  transition: all .2s;
  white-space: nowrap;
}
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: #1e40af; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(29,78,216,.35); }
.btn-success { background: var(--green); color: #fff; }
.btn-success:hover { background: #059669; }
.btn-danger  { background: var(--rose); color: #fff; }
.btn-danger:hover  { background: #e11d48; }
.btn-warning { background: var(--amber); color: #fff; }
.btn-outline { background: transparent; border: 2px solid var(--blue); color: var(--blue); }
.btn-outline:hover { background: var(--blue); color: #fff; }
.btn-ghost   { background: #f1f5f9; color: #475569; }
.btn-ghost:hover { background: #e2e8f0; }
.btn-full    { width: 100%; padding: 13px; font-size: 1rem; }
.btn-sm      { padding: 5px 13px; font-size: .82rem; border-radius: 7px; }
.btn-xs      { padding: 3px 9px; font-size: .76rem; border-radius: 6px; }

/* ── PANELS ── */
.panel {
  background: var(--card);
  border-radius: var(--radius);
  box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
  margin-bottom: 22px;
  overflow: hidden;
}
.panel-header {
  background: var(--navy);
  color: #fff;
  padding: 15px 24px;
  font-weight: 700;
  font-size: .97rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.panel-body { padding: 22px; }

/* ── STATS ROW ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 18px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--card);
  border-radius: var(--radius);
  padding: 24px 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
  text-align: center;
  position: relative;
  overflow: hidden;
  border-top: 4px solid var(--blue);
}
.stat-card::before {
  content: '';
  position: absolute;
  top: -30px; right: -30px;
  width: 90px; height: 90px;
  border-radius: 50%;
  background: rgba(29,78,216,.06);
}
.stat-card.stat-absent { border-color: var(--rose); }
.stat-card.stat-absent::before { background: rgba(244,63,94,.06); }
.stat-card.stat-free   { border-color: var(--green); }
.stat-card.stat-free::before { background: rgba(16,185,129,.06); }
.stat-card.stat-period { border-color: var(--amber); }
.stat-card.stat-period::before { background: rgba(245,158,11,.06); }
.stat-num   { font-size: 2.8rem; font-weight: 900; color: var(--navy); line-height: 1; }
.stat-label { color: var(--slate); font-size: .86rem; font-weight: 600; margin-top: 8px; }

/* ── TWO COL ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
@media (max-width: 820px) { .two-col { grid-template-columns: 1fr; } }

/* ── DASHBOARD HEADER ── */
.dashboard-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 26px;
  gap: 16px;
  flex-wrap: wrap;
}
.dashboard-header h2 { font-size: 1.6rem; font-weight: 800; color: var(--navy); }
.dashboard-header .sub { color: var(--slate); font-size: .9rem; margin-top: 2px; }
.date-badge {
  background: var(--navy);
  color: #fff;
  padding: 12px 22px;
  border-radius: 14px;
  text-align: center;
  min-width: 150px;
  box-shadow: 0 4px 14px rgba(15,37,69,.3);
}
.today-day  { font-weight: 800; font-size: 1.05rem; letter-spacing: .5px; }
.today-date { font-size: .82rem; opacity: .8; margin-top: 2px; }
.live-clock { font-size: 1.25rem; font-weight: 900; margin-top: 5px; letter-spacing: 2px; color: var(--sky); }

/* ── TEACHER CARDS (absence grid) ── */
.teachers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.teacher-card {
  background: var(--card);
  border-radius: var(--radius);
  padding: 22px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
  text-align: center;
  border: 2px solid transparent;
  transition: all .25s;
}
.teacher-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.teacher-card.is-absent { border-color: var(--rose); background: #fff5f6; }
.t-avatar {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; font-weight: 800;
  margin: 0 auto 12px;
  box-shadow: 0 4px 10px rgba(15,37,69,.25);
}
.t-avatar.absent-av { background: linear-gradient(135deg, #f43f5e, #e11d48); }
.t-avatar.free-av   { background: linear-gradient(135deg, #10b981, #059669); }
.t-name { font-weight: 700; font-size: .95rem; margin-bottom: 12px; }

/* ── TEACHER ROWS (in panels) ── */
.teacher-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
}
.teacher-row:last-child { border: none; }
.teacher-row .t-name { flex: 1; font-weight: 600; }
.t-reason { font-size: .8rem; color: var(--slate); }

/* ── TIMELINE ── */
.timeline { display: flex; gap: 7px; flex-wrap: wrap; padding: 18px 22px; }
.timeline-slot {
  flex: 1;
  min-width: 72px;
  border-radius: 10px;
  padding: 10px 7px;
  text-align: center;
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  transition: all .25s;
  cursor: default;
}
.timeline-slot.period:hover { border-color: var(--blue); }
.timeline-slot.active {
  background: var(--blue);
  color: #fff;
  border-color: var(--blue);
  transform: scale(1.07);
  box-shadow: 0 6px 18px rgba(29,78,216,.35);
}
.timeline-slot.break   { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.timeline-slot.assembly{ background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.timeline-slot.cca     { background: #fdf4ff; border-color: #e9d5ff; color: #6b21a8; }
.slot-label { font-weight: 800; font-size: 1.05rem; }
.slot-time  { font-size: .68rem; opacity: .8; margin-top: 3px; line-height: 1.3; }
.slot-act   { font-size: .74rem; margin-top: 5px; }

/* ══════════════════════════════════════════
   SCHEDULE CARD GRID  (new teacher view)
═══════════════════════════════════════════ */
.schedule-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  padding: 6px 0;
}
.schedule-card {
  background: var(--card);
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
  overflow: hidden;
  border: 2px solid transparent;
  transition: all .25s;
  position: relative;
}
.schedule-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.schedule-card.is-current {
  border-color: var(--blue);
  box-shadow: 0 6px 24px rgba(29,78,216,.2);
}
.schedule-card.is-break  { border-color: #fde68a; background: #fffbeb; }
.schedule-card.is-supervision { border-color: #6ee7b7; }
.schedule-card.is-nasp   { border-color: #e9d5ff; }
.schedule-card.is-cca    { border-color: #c4b5fd; }
.schedule-card.is-free   { background: #f8fafc; border-color: #e2e8f0; opacity: .7; }

.sc-header {
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sc-period-badge {
  background: var(--navy);
  color: #fff;
  font-size: .78rem;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 20px;
}
.schedule-card.is-current .sc-period-badge { background: var(--blue); }
.schedule-card.is-break .sc-period-badge   { background: var(--amber); }
.schedule-card.is-supervision .sc-period-badge { background: var(--teal); }
.schedule-card.is-nasp .sc-period-badge   { background: #7c3aed; }

.sc-time {
  font-size: .78rem;
  color: var(--slate);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}
.sc-body { padding: 8px 14px 14px; }
.sc-class {
  font-size: 1.6rem;
  font-weight: 900;
  color: var(--navy);
  line-height: 1;
  margin-bottom: 4px;
}
.sc-type {
  font-size: .78rem;
  font-weight: 600;
  color: var(--slate);
  text-transform: uppercase;
  letter-spacing: .5px;
}
.sc-footer {
  padding: 8px 14px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}
.sc-now-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: var(--blue);
  color: #fff;
  font-size: .7rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 20px;
  animation: pulse-badge 2s infinite;
}
@keyframes pulse-badge {
  0%,100% { opacity: 1; } 50% { opacity: .6; }
}

/* ── TEACHER LIST TABLE ── */
.teacher-select-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}
.teacher-select-card {
  background: var(--card);
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px 14px;
  text-align: center;
  cursor: pointer;
  transition: all .2s;
  text-decoration: none;
  color: inherit;
  display: block;
}
.teacher-select-card:hover { border-color: var(--blue); background: #eff6ff; }
.teacher-select-card.selected { border-color: var(--blue); background: #eff6ff; }
.tsc-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; font-weight: 800;
  margin: 0 auto 8px;
}
.tsc-name { font-weight: 700; font-size: .88rem; }
.tsc-load { font-size: .78rem; color: var(--slate); margin-top: 3px; }

/* ── DATA TABLE ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
  background: #f8fafc;
  padding: 12px 18px;
  text-align: right;
  font-size: .85rem;
  font-weight: 700;
  color: var(--slate);
  border-bottom: 2px solid #e2e8f0;
}
.data-table td {
  padding: 12px 18px;
  border-bottom: 1px solid #f1f5f9;
  font-size: .9rem;
}
.data-table tr:hover td { background: #f8fafc; }
.data-table tr:last-child td { border: none; }

/* ── ALERTS ── */
.alert {
  padding: 14px 18px;
  border-radius: 10px;
  margin-bottom: 18px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}
.alert-success { background: #ecfdf5; color: #065f46; border-right: 4px solid var(--green); }
.alert-error   { background: #fff1f2; color: #9f1239; border-right: 4px solid var(--rose); }
.alert-info    { background: #eff6ff; color: #1e40af; border-right: 4px solid var(--blue); }

/* ── BADGES ── */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: .76rem;
  font-weight: 700;
}
.badge-free        { background: #ecfdf5; color: #065f46; }
.badge-class       { background: #eff6ff; color: #1e40af; }
.badge-supervision { background: #ecfdf5; color: #065f46; }
.badge-nasp        { background: #fdf4ff; color: #6b21a8; }
.badge-break       { background: #fffbeb; color: #92400e; }
.badge-cca         { background: #f5f3ff; color: #5b21b6; }

/* ── PAGE HEADER ── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26px;
  flex-wrap: wrap;
  gap: 14px;
}
.page-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--navy); }

/* ── DAY TABS ── */
.day-tabs { display: flex; gap: 7px; flex-wrap: wrap; }
.tab {
  padding: 8px 18px;
  border-radius: 22px;
  background: #f1f5f9;
  color: #475569;
  font-size: .88rem;
  font-weight: 700;
  transition: all .2s;
  border: 2px solid transparent;
  font-family: 'Cairo', sans-serif;
}
.tab:hover  { background: #e2e8f0; }
.tab.active { background: var(--navy); color: #fff; border-color: var(--navy); }

/* ── MISC ── */
.mt-2 { margin-top: 12px; }
.mt-3 { margin-top: 24px; }
.empty { color: var(--slate); text-align: center; padding: 36px 0; font-size: .95rem; font-weight: 600; }
.date-picker {
  padding: 9px 14px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: .9rem;
  font-family: 'Cairo', sans-serif;
  background: #f8fafc;
}
.filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.inline-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.status-assigned  { color: var(--blue); font-weight: 700; }
.status-completed { color: var(--green); font-weight: 700; }
.divider { height: 1px; background: #f1f5f9; margin: 16px 0; }

/* ── MODAL ── */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(15,37,69,.55);
  z-index: 500;
  align-items: center;
  justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff;
  border-radius: 18px;
  padding: 36px 32px;
  width: 500px;
  max-width: 95vw;
  box-shadow: 0 24px 64px rgba(0,0,0,.3);
  position: relative;
  animation: modal-in .2s ease;
}
@keyframes modal-in { from { transform: scale(.94); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.modal-close {
  position: absolute;
  top: 14px; left: 14px;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  display: flex; align-items: center; justify-content: center;
}
.modal-title { font-size: 1.2rem; font-weight: 800; color: var(--navy); margin-bottom: 22px; }
""")

# ─────────────────────────────────────────────
print("\n[2/7] main_layout.php — add Tailwind CDN + new navbar markup")
# ─────────────────────────────────────────────
write("app/views/layouts/main_layout.php", r"""<?php $currentUser = Auth::user(); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>نظام إدارة الجداول</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-brand">
    <span class="brand-dot"></span>
    📅 جداول المدرسة
  </div>
  <div class="nav-menu">
    <?php $cur = basename($_SERVER['PHP_SELF']); ?>
    <a href="<?= APP_URL ?>/public/dashboard.php"   class="<?= $cur==='dashboard.php'?'active':'' ?>">الرئيسية</a>
    <a href="<?= APP_URL ?>/public/my_schedule.php" class="<?= $cur==='my_schedule.php'?'active':'' ?>">جدولي</a>
    <?php if ($currentUser['role'] === 'admin'): ?>
    <a href="<?= APP_URL ?>/public/teacher_schedule.php" class="<?= $cur==='teacher_schedule.php'?'active':'' ?>">جدول مدرس</a>
    <a href="<?= APP_URL ?>/public/absences.php"    class="<?= $cur==='absences.php'?'active':'' ?>">الغياب</a>
    <a href="<?= APP_URL ?>/public/coverage.php"    class="<?= $cur==='coverage.php'?'active':'' ?>">التغطية</a>
    <a href="<?= APP_URL ?>/public/reports.php"     class="<?= $cur==='reports.php'?'active':'' ?>">التقارير</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/public/logout.php" class="btn-logout">خروج</a>
  </div>
</nav>

<div class="container">
  <?php if (isset($msg) && $msg): ?>
  <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if (isset($error_msg) && $error_msg): ?>
  <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>
  <?php require $viewFile; ?>
</div>

<script src="<?= APP_URL ?>/public/assets/js/app.js"></script>
</body>
</html>
""")

# ─────────────────────────────────────────────
print("\n[3/7] login_layout.php — redesigned")
# ─────────────────────────────────────────────
write("app/views/layouts/login_layout.php", r"""<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تسجيل الدخول — نظام الجداول</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
</head>
<body class="login-body">

  <!-- Decorative blobs -->
  <div style="position:fixed;top:-120px;right:-120px;width:400px;height:400px;border-radius:50%;background:rgba(56,189,248,.12);pointer-events:none"></div>
  <div style="position:fixed;bottom:-80px;left:-80px;width:300px;height:300px;border-radius:50%;background:rgba(13,148,136,.12);pointer-events:none"></div>

  <div class="login-card">
    <div class="login-logo">
      <span class="logo-icon">📅</span>
      <h1>نظام إدارة الجداول</h1>
      <p>School Schedule Management</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>اسم المستخدم</label>
        <input type="text" name="username" class="form-control" placeholder="أدخل اسم المستخدم" required autofocus>
      </div>
      <div class="form-group">
        <label>كلمة المرور</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;font-size:1.05rem;padding:14px">
        دخول ←
      </button>
    </form>

    <p style="text-align:center;margin-top:22px;font-size:.82rem;color:#94a3b8">
      نظام إدارة الجداول المدرسية · 2026-2027
    </p>
  </div>
</body>
</html>
""")

# ─────────────────────────────────────────────
print("\n[4/7] admin/dashboard.php — redesigned")
# ─────────────────────────────────────────────
write("app/views/admin/dashboard.php", r"""<div class="dashboard-header">
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
""")

# ─────────────────────────────────────────────
print("\n[5/7] New page: teacher_schedule.php (public) + view")
# ─────────────────────────────────────────────
write("public/teacher_schedule.php", r"""<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireAdmin();

$db  = Database::getInstance();
$msg = '';
$error_msg = '';

// Handle ADD period
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_period') {
        $tid      = (int)$_POST['teacher_id'];
        $day      = $_POST['day_name'];
        $pkey     = $_POST['period_key'];
        $activity = trim($_POST['activity']);
        $atype    = $_POST['activity_type'] ?? 'class';
        // Check duplicate
        $exists = $db->fetchOne(
            "SELECT id FROM schedule_templates WHERE teacher_id=? AND day_name=? AND period_key=?",
            [$tid, $day, $pkey]
        );
        if ($exists) {
            $error_msg = 'هذه الحصة موجودة بالفعل لهذا المدرس في هذا اليوم';
        } else {
            $db->query(
                "INSERT INTO schedule_templates (teacher_id,day_name,period_key,activity,activity_type)
                 VALUES (?,?,?,?,?)",
                [$tid, $day, $pkey, $activity, $atype]
            );
            $msg = 'تمت إضافة الحصة بنجاح';
        }
    }

    if ($action === 'delete_period') {
        $db->query("DELETE FROM schedule_templates WHERE id=?", [(int)$_POST['slot_id']]);
        $msg = 'تم حذف الحصة';
    }

    if ($action === 'edit_period') {
        $db->query(
            "UPDATE schedule_templates SET activity=?, activity_type=? WHERE id=?",
            [trim($_POST['activity']), $_POST['activity_type'], (int)$_POST['slot_id']]
        );
        $msg = 'تم تحديث الحصة';
    }
}

// Selected teacher
$selectedTeacherId = (int)($_GET['tid'] ?? $_POST['teacher_id'] ?? 0);
$selectedDay       = $_GET['day'] ?? ScheduleHelper::todayName();
$allTeachers       = $db->fetchAll("SELECT id,name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name");
$periods           = ScheduleHelper::getPeriodsForDay($selectedDay);
$curPeriod         = ScheduleHelper::getCurrentPeriod($selectedDay);
$schedule          = [];
$scheduleMap       = [];
$selectedTeacher   = null;

if ($selectedTeacherId) {
    $selectedTeacher = $db->fetchOne("SELECT * FROM users WHERE id=?", [$selectedTeacherId]);
    $rows = $db->fetchAll(
        "SELECT * FROM schedule_templates WHERE teacher_id=? AND day_name=? ORDER BY period_key",
        [$selectedTeacherId, $selectedDay]
    );
    $scheduleMap = array_column($rows, null, 'period_key');

    // Weekly summary
    $weekSummary = $db->fetchAll(
        "SELECT day_name, COUNT(*) as cnt FROM schedule_templates
         WHERE teacher_id=? GROUP BY day_name",
        [$selectedTeacherId]
    );
}

$viewFile = __DIR__ . '/../app/views/admin/teacher_schedule.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
""")

write("app/views/admin/teacher_schedule.php", r"""<!-- Teacher Selector -->
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
""")

# ─────────────────────────────────────────────
print("\n[6/7] teacher/my_schedule.php — card grid style")
# ─────────────────────────────────────────────
write("app/views/teacher/my_schedule.php", r"""<div class="page-header">
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
""")

# ─────────────────────────────────────────────
print("\n[7/7] app.js — add modal helpers + refresh")
# ─────────────────────────────────────────────
write("public/assets/js/app.js", r"""// School Schedule System — Frontend JS

// ── Live Clock ──
function updateClock() {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('ar-SA');
}
setInterval(updateClock, 1000);
updateClock();

// ── Auto-refresh free count ──
(function() {
    const el = document.getElementById('free-count');
    if (!el) return;
    setInterval(function() {
        fetch('api.php?action=free_now')
            .then(r => r.json())
            .then(d => { if (d.teachers) el.textContent = d.teachers.length; })
            .catch(() => {});
    }, 60000);
}());

// ── Modals ──
function openAddModal(periodKey, periodLabel) {
    document.getElementById('add_period_key').value   = periodKey;
    document.getElementById('add_period_label').value = 'حصة ' + periodKey + '  (' + periodLabel + ')';
    document.getElementById('addModal').classList.add('open');
}

function openEditModal(slotId, activity, activityType) {
    document.getElementById('edit_slot_id').value        = slotId;
    document.getElementById('edit_activity').value       = activity;
    document.getElementById('edit_activity_type').value  = activityType;
    document.getElementById('editModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Escape to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// ── Confirm deletes ──
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});
""")

# Fix dashboard.php to add allTeachersCount variable
print("\n[+] Patching public/dashboard.php — add allTeachersCount")
with open("school_schedule/public/dashboard.php", "r", encoding="utf-8") as f:
    dash = f.read()

insert = "\n$allTeachersCount = $db->fetchAll(\"SELECT id FROM users WHERE role='teacher' AND is_active=1\");\n"
dash = dash.replace(
    "$viewFile = Auth::isAdmin()",
    insert + "$viewFile = Auth::isAdmin()"
)
with open("school_schedule/public/dashboard.php", "w", encoding="utf-8") as f:
    f.write(dash)
print("  [PATCHED] public/dashboard.php")

print("\n" + "="*52)
print("  UI Upgrade complete! Files updated in-place.")
print("="*52)
print("""
Screens updated:
  ✅ CSS — Cairo font, new tokens, card system
  ✅ Login layout — redesigned
  ✅ Main layout — Tailwind CDN + active nav links
  ✅ Admin dashboard — stats, timeline, quick-nav
  ✅ Teacher schedule page (NEW) — card grid + add/edit/delete modals
  ✅ My schedule (teacher view) — card grid
  ✅ app.js — modal helpers, clock, auto-refresh
""")
