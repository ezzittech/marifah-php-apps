<?php
define('APP_NAME',    'School Schedule System');
define('APP_VERSION', '1.0.0');
// AUTO-DETECT URL - works on any server without manual editing
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script   = $_SERVER['SCRIPT_NAME'] ?? '';
// Walk up from /public/xxx.php to project root
$_dir      = dirname(dirname($_script)); // removes filename + /public
define('APP_URL', $_protocol . '://' . $_host . $_dir);
define('TIMEZONE',    'Asia/Kuala_Lumpur');
define('DEBUG_MODE',  true);
define('DB_PATH',     __DIR__ . '/../database/school_schedule.db');
define('SESSION_NAME',     'sched_sess');
define('SESSION_LIFETIME', 3600 * 8);
define('SCHOOL_DAYS', ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY']);

// Each day has DIFFERENT period timings (key insight from Excel)
define('DAY_PERIODS', [
    'MONDAY' => [
        1    => ['label'=>'1',     'start'=>'08:00','end'=>'08:20','type'=>'assembly'],
        2    => ['label'=>'2',     'start'=>'08:20','end'=>'09:05','type'=>'period'],
        3    => ['label'=>'3',     'start'=>'09:05','end'=>'09:50','type'=>'period'],
        'B1' => ['label'=>'Break', 'start'=>'09:50','end'=>'10:15','type'=>'break'],
        4    => ['label'=>'4',     'start'=>'10:15','end'=>'11:00','type'=>'period'],
        5    => ['label'=>'5',     'start'=>'11:00','end'=>'11:45','type'=>'period'],
        6    => ['label'=>'6',     'start'=>'11:45','end'=>'12:30','type'=>'period'],
        'B2' => ['label'=>'Break', 'start'=>'12:30','end'=>'12:45','type'=>'break'],
        7    => ['label'=>'7',     'start'=>'12:45','end'=>'13:30','type'=>'period'],
        8    => ['label'=>'8',     'start'=>'13:30','end'=>'14:15','type'=>'period'],
    ],
    'TUESDAY' => [
        1    => ['label'=>'1',     'start'=>'08:00','end'=>'08:20','type'=>'assembly'],
        2    => ['label'=>'2',     'start'=>'08:20','end'=>'09:05','type'=>'period'],
        3    => ['label'=>'3',     'start'=>'09:05','end'=>'09:50','type'=>'period'],
        'B1' => ['label'=>'Break', 'start'=>'09:50','end'=>'10:15','type'=>'break'],
        4    => ['label'=>'4',     'start'=>'10:15','end'=>'11:00','type'=>'period'],
        5    => ['label'=>'5',     'start'=>'11:00','end'=>'11:45','type'=>'period'],
        6    => ['label'=>'6',     'start'=>'11:45','end'=>'12:30','type'=>'period'],
        'B2' => ['label'=>'Break', 'start'=>'12:30','end'=>'12:45','type'=>'break'],
        7    => ['label'=>'7',     'start'=>'12:45','end'=>'13:30','type'=>'period'],
        8    => ['label'=>'8',     'start'=>'13:30','end'=>'14:15','type'=>'period'],
    ],
    'WEDNESDAY' => [
        // NO period 1 on Wednesday! starts at 08:25
        2    => ['label'=>'2',     'start'=>'08:25','end'=>'09:10','type'=>'period'],
        3    => ['label'=>'3',     'start'=>'09:10','end'=>'09:55','type'=>'period'],
        'B1' => ['label'=>'Break', 'start'=>'09:55','end'=>'10:20','type'=>'break'],
        4    => ['label'=>'4',     'start'=>'10:20','end'=>'11:05','type'=>'period'],
        5    => ['label'=>'5',     'start'=>'11:05','end'=>'11:50','type'=>'period'],
        6    => ['label'=>'6',     'start'=>'11:50','end'=>'12:35','type'=>'period'],
        'B2' => ['label'=>'Break', 'start'=>'12:35','end'=>'12:50','type'=>'break'],
        7    => ['label'=>'7',     'start'=>'12:50','end'=>'13:35','type'=>'period'],
        8    => ['label'=>'8',     'start'=>'13:35','end'=>'14:20','type'=>'period'],
    ],
    'THURSDAY' => [
        1    => ['label'=>'1',     'start'=>'08:00','end'=>'08:20','type'=>'assembly'],
        2    => ['label'=>'2',     'start'=>'08:20','end'=>'09:05','type'=>'period'],
        3    => ['label'=>'3',     'start'=>'09:05','end'=>'09:50','type'=>'period'],
        'B1' => ['label'=>'Break', 'start'=>'09:50','end'=>'10:15','type'=>'break'],
        4    => ['label'=>'4',     'start'=>'10:15','end'=>'11:00','type'=>'period'],
        5    => ['label'=>'5',     'start'=>'11:00','end'=>'11:45','type'=>'period'],
        6    => ['label'=>'6',     'start'=>'11:45','end'=>'12:30','type'=>'period'],
        'B2' => ['label'=>'Break', 'start'=>'12:30','end'=>'12:45','type'=>'break'],
        7    => ['label'=>'7',     'start'=>'12:45','end'=>'13:30','type'=>'period'],
        8    => ['label'=>'8',     'start'=>'13:30','end'=>'14:15','type'=>'period'],
    ],
    'FRIDAY' => [
        // Short day - 5 periods only
        1    => ['label'=>'1',     'start'=>'08:00','end'=>'08:45','type'=>'period'],
        2    => ['label'=>'2',     'start'=>'08:45','end'=>'09:30','type'=>'period'],
        'B1' => ['label'=>'Break', 'start'=>'09:30','end'=>'09:50','type'=>'break'],
        3    => ['label'=>'3',     'start'=>'09:50','end'=>'10:35','type'=>'period'],
        4    => ['label'=>'4',     'start'=>'10:35','end'=>'11:20','type'=>'period'],
        5    => ['label'=>'5',     'start'=>'11:20','end'=>'12:20','type'=>'cca'],
    ],
]);

date_default_timezone_set(TIMEZONE);

spl_autoload_register(function ($class) {
    foreach ([
        __DIR__ . '/../app/models/',
        __DIR__ . '/../app/controllers/',
        __DIR__ . '/../app/helpers/',
    ] as $path) {
        if (file_exists($path . $class . '.php')) {
            require_once $path . $class . '.php';
            return;
        }
    }
});
