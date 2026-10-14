<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireLogin();
$user      = Auth::user();
$db        = Database::getInstance();
$today     = date('Y-m-d');
$dayName   = ScheduleHelper::todayName();
$curPeriod = ScheduleHelper::getCurrentPeriod($dayName);
$periods   = ScheduleHelper::getPeriodsForDay($dayName);
$absentTeachers = ScheduleHelper::getAbsentTeachers($today, $db);
$freeNow   = [];
if ($curPeriod && Auth::isAdmin()) {
    $freeNow = ScheduleHelper::getFreeTeachers($dayName, $curPeriod, $today, $db);
}
// Teacher own schedule for today
$mySchedule = $db->fetchAll(
    "SELECT period_key, activity, activity_type FROM schedule_templates WHERE teacher_id=? AND day_name=?",
    [$user['id'], $dayName]
);
$myMap = array_column($mySchedule, null, 'period_key');

$allTeachersCount = $db->fetchAll("SELECT id FROM users WHERE role='teacher' AND is_active=1");
$viewFile = Auth::isAdmin()
    ? __DIR__ . '/../app/views/admin/dashboard.php'
    : __DIR__ . '/../app/views/teacher/dashboard.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
