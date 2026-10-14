<?php
// php database/seeds/seed_data.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();

// Admin
$db->query(
    "INSERT OR IGNORE INTO users (name,username,password,role) VALUES (?,?,?,?)",
    ['Admin','admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']
);

// Teachers from Excel
$teachers = [
    'AMAL','Afnan','Andrea','Nurul Ain','Serena','Abrar','Azhar',
    'SUMIYA','Rihanah','Puh Art','Abdulraqeeb','Tahani','Shadha',
    'Mahdi','Omnia','Belal AR','MUBINA','HEBA','Shajiha','Mahmood',
    'Feras','Andrew PE','ALAADDIN'
];

foreach ($teachers as $name) {
    $username = strtolower(preg_replace('/\s+/', '_', $name));
    $db->query(
        "INSERT OR IGNORE INTO users (name,username,password,role) VALUES (?,?,?,?)",
        [$name, $username, password_hash('teacher123', PASSWORD_DEFAULT), 'teacher']
    );
}

// Sample schedule entries (Monday primary section)
$schedule = [
    ['AMAL','MONDAY','2','G2','class'],
    ['AMAL','MONDAY','3','G1','class'],
    ['AMAL','MONDAY','B1','Supervision B1','supervision'],
    ['AMAL','MONDAY','5','G1','class'],
    ['AMAL','MONDAY','6','G3','class'],
    ['AMAL','MONDAY','7','G3','class'],
    ['Afnan','MONDAY','2','G1','class'],
    ['Afnan','MONDAY','5','G3','class'],
    ['Afnan','MONDAY','6','G4','class'],
    ['Afnan','MONDAY','B2','Supervision B2','supervision'],
    ['Afnan','MONDAY','7','G2','class'],
    ['Andrea','MONDAY','1','G2','class'],
    ['Andrea','MONDAY','2','G4','class'],
    ['Andrea','MONDAY','3','G2','class'],
    ['Andrea','MONDAY','B1','Supervision B1','supervision'],
    ['Andrea','MONDAY','4','G2','class'],
    ['Andrea','MONDAY','5','G2','class'],
    ['Andrea','MONDAY','8','G2','class'],
    ['Nurul Ain','MONDAY','1','G3','class'],
    ['Nurul Ain','MONDAY','2','G3','class'],
    ['Nurul Ain','MONDAY','B1','Supervision B1','supervision'],
    ['Nurul Ain','MONDAY','4','G3','class'],
    ['Nurul Ain','MONDAY','8','G3','class'],
    ['Serena','MONDAY','1','G1','class'],
    ['Serena','MONDAY','3','G5','class'],
    ['Serena','MONDAY','B1','Supervision B1','supervision'],
    ['Serena','MONDAY','4','G1','class'],
    ['Serena','MONDAY','6','G1','class'],
    ['Serena','MONDAY','7','G1','class'],
    ['Serena','MONDAY','8','G1','class'],
    ['Abrar','MONDAY','3','NASP 4 Q','nasp'],
    ['Abrar','MONDAY','5','NASP 1 Q','nasp'],
    ['Abrar','MONDAY','6','NASP 1 A','nasp'],
    ['Abrar','MONDAY','7','NASP 3 Q','nasp'],
    ['Azhar','MONDAY','2','NASP 4 A','nasp'],
    ['Azhar','MONDAY','7','NASP 3 A','nasp'],
    // Friday short day
    ['AMAL','FRIDAY','1','G2','class'],
    ['AMAL','FRIDAY','2','G3','class'],
    ['AMAL','FRIDAY','B1','Supervision B1','supervision'],
    ['AMAL','FRIDAY','3','G1','class'],
    ['AMAL','FRIDAY','5','G2','cca'],
    ['Serena','FRIDAY','5','CCA','cca'],
];

foreach ($schedule as [$tname, $day, $pkey, $activity, $type]) {
    $teacher = $db->fetchOne("SELECT id FROM users WHERE name=? AND role='teacher'", [$tname]);
    if ($teacher) {
        $db->query(
            "INSERT OR IGNORE INTO schedule_templates
             (teacher_id,day_name,period_key,activity,activity_type) VALUES (?,?,?,?,?)",
            [$teacher['id'], $day, $pkey, $activity, $type]
        );
    }
}

echo "Seed data inserted OK\n";
