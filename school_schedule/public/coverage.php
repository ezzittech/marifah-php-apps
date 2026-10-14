<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireAdmin();
$db      = Database::getInstance();
$date    = $_GET['date'] ?? date('Y-m-d');
$dayName = strtoupper(date('l', strtotime($date)));
$msg     = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'assign') {
    $db->query(
        "INSERT INTO coverage_log
         (date,day_name,period_key,absent_teacher_id,cover_teacher_id,cover_class,assigned_by)
         VALUES (?,?,?,?,?,?,?)",
        [$date, $dayName, $_POST['period_key'],
         (int)$_POST['absent_teacher_id'], (int)$_POST['cover_teacher_id'],
         $_POST['cover_class'] ?? '', Auth::user()['id']]
    );
    $msg = 'تم تعيين التغطية بنجاح';
}
$absent      = ScheduleHelper::getAbsentTeachers($date, $db);
$allTeachers = $db->fetchAll("SELECT id,name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name");
$periods     = ScheduleHelper::getPeriodsForDay($dayName);
$logs        = $db->fetchAll(
    "SELECT c.*, u.name AS absent_name, cv.name AS cover_name
     FROM coverage_log c
     JOIN users u  ON u.id  = c.absent_teacher_id
     JOIN users cv ON cv.id = c.cover_teacher_id
     WHERE c.date=? ORDER BY c.period_key",
    [$date]
);
$viewFile = __DIR__ . '/../app/views/admin/coverage.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
