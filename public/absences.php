<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireAdmin();
$db   = Database::getInstance();
$date = $_GET['date'] ?? date('Y-m-d');
$msg  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_absent') {
        ScheduleHelper::markAbsent(
            (int)$_POST['teacher_id'], $date,
            $_POST['reason'] ?? '', Auth::user()['id'], $db
        );
        $msg = 'تم تسجيل الغياب بنجاح';
    } elseif ($action === 'mark_present') {
        $db->query("DELETE FROM teacher_attendance WHERE teacher_id=? AND date=?",
            [(int)$_POST['teacher_id'], $date]);
        $msg = 'تم إلغاء الغياب';
    }
}
$teachers  = $db->fetchAll("SELECT id,name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name");
$absent    = ScheduleHelper::getAbsentTeachers($date, $db);
$absentIds = array_column($absent, 'id');
$viewFile  = __DIR__ . '/../app/views/admin/absences.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
