<?php
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
