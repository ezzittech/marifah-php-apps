<?php
class ScheduleHelper {

    public static function todayName() {
        $map = [1=>'MONDAY',2=>'TUESDAY',3=>'WEDNESDAY',4=>'THURSDAY',5=>'FRIDAY'];
        $dow = (int) date('N');
        return $map[$dow] ?? 'MONDAY';
    }

    public static function getPeriodsForDay($dayName) {
        $periods = DAY_PERIODS[strtoupper($dayName)] ?? DAY_PERIODS['MONDAY'];
        return $periods;
    }

    public static function getCurrentPeriod($dayName = null) {
        $day  = $dayName ?? self::todayName();
        $now  = date('H:i');
        foreach (self::getPeriodsForDay($day) as $key => $p) {
            if ($now >= $p['start'] && $now < $p['end']) return $key;
        }
        return null;
    }

    public static function getAbsentTeachers($date, $db) {
        return $db->fetchAll(
            "SELECT u.id, u.name, a.status, a.reason
             FROM teacher_attendance a
             JOIN users u ON u.id = a.teacher_id
             WHERE a.date=? AND a.status IN ('absent','excused')
             ORDER BY u.name",
            [$date]
        );
    }

    public static function getFreeTeachers($dayName, $periodKey, $date, $db) {
        return $db->fetchAll(
            "SELECT u.id, u.name
             FROM users u
             WHERE u.role='teacher' AND u.is_active=1
             AND u.id NOT IN (
                 SELECT teacher_id FROM schedule_templates
                 WHERE day_name=? AND period_key=?
             )
             AND u.id NOT IN (
                 SELECT teacher_id FROM teacher_attendance
                 WHERE date=? AND status IN ('absent','excused')
             )
             ORDER BY u.name",
            [$dayName, $periodKey, $date]
        );
    }

    public static function getBusyTeachers($dayName, $periodKey, $db) {
        return $db->fetchAll(
            "SELECT u.id, u.name, st.activity, st.activity_type
             FROM schedule_templates st
             JOIN users u ON u.id = st.teacher_id
             WHERE st.day_name=? AND st.period_key=? AND u.is_active=1
             ORDER BY u.name",
            [$dayName, $periodKey]
        );
    }

    public static function markAbsent($teacherId, $date, $reason, $recordedBy, $db) {
        $db->query(
            "INSERT OR REPLACE INTO teacher_attendance
             (teacher_id,date,status,reason,recorded_by) VALUES (?,?,?,?,?)",
            [$teacherId, $date, 'absent', $reason, $recordedBy]
        );
    }

    public static function getCoverageStats($from, $to, $db) {
        return $db->fetchAll(
            "SELECT c.*, u.name AS absent_teacher, cv.name AS cover_teacher
             FROM coverage_log c
             JOIN users u  ON u.id  = c.absent_teacher_id
             JOIN users cv ON cv.id = c.cover_teacher_id
             WHERE c.date BETWEEN ? AND ?
             ORDER BY c.date, c.period_key",
            [$from, $to]
        );
    }
}
