<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireLogin();
$db      = Database::getInstance();
$user    = Auth::user();
$dayName = $_GET['day'] ?? ScheduleHelper::todayName();
$periods = ScheduleHelper::getPeriodsForDay($dayName);
$schedule    = $db->fetchAll(
    "SELECT period_key, activity, activity_type FROM schedule_templates
     WHERE teacher_id=? AND day_name=? ORDER BY period_key",
    [$user['id'], $dayName]
);
$scheduleMap = array_column($schedule, null, 'period_key');
$curPeriod   = ScheduleHelper::getCurrentPeriod($dayName);
$viewFile    = __DIR__ . '/../app/views/teacher/my_schedule.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
