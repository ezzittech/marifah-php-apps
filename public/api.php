<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
header('Content-Type: application/json');
Auth::start();
if (!Auth::isLoggedIn()) { echo json_encode(['error'=>'Unauthorized']); exit; }
$db     = Database::getInstance();
$action = $_GET['action'] ?? '';
$date   = $_GET['date']   ?? date('Y-m-d');
$day    = strtoupper(date('l', strtotime($date)));
switch ($action) {
    case 'free_now':
        $period = ScheduleHelper::getCurrentPeriod($day);
        echo json_encode(['period'=>$period,'day'=>$day,
            'teachers'=>$period ? ScheduleHelper::getFreeTeachers($day,$period,$date,$db) : []]);
        break;
    case 'absent':
        echo json_encode(ScheduleHelper::getAbsentTeachers($date,$db));
        break;
    case 'coverage_count':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? $date;
        echo json_encode(['count'=>count(ScheduleHelper::getCoverageStats($from,$to,$db))]);
        break;
    default:
        echo json_encode(['error'=>'Unknown action']);
}
