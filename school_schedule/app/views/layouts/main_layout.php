<?php $currentUser = Auth::user(); ?>
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
