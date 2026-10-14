<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
require_once __DIR__ . '/../app/helpers/ScheduleHelper.php';
Auth::start(); Auth::requireAdmin();
$db   = Database::getInstance();
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$stats         = ScheduleHelper::getCoverageStats($from, $to, $db);
$totalCoverage = count($stats);
$topCover  = $db->fetchAll(
    "SELECT cv.name, COUNT(*) AS cnt FROM coverage_log c
     JOIN users cv ON cv.id = c.cover_teacher_id
     WHERE c.date BETWEEN ? AND ? GROUP BY c.cover_teacher_id ORDER BY cnt DESC LIMIT 10",
    [$from, $to]
);
$topAbsent = $db->fetchAll(
    "SELECT u.name, COUNT(*) AS cnt FROM teacher_attendance a
     JOIN users u ON u.id = a.teacher_id
     WHERE a.date BETWEEN ? AND ? AND a.status='absent'
     GROUP BY a.teacher_id ORDER BY cnt DESC LIMIT 10",
    [$from, $to]
);
$viewFile = __DIR__ . '/../app/views/admin/reports.php';
require __DIR__ . '/../app/views/layouts/main_layout.php';
