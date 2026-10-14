<!DOCTYPE html>
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
