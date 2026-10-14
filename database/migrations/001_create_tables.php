<?php
// php database/migrations/001_create_tables.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    username   TEXT UNIQUE NOT NULL,
    password   TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'teacher',
    email      TEXT,
    phone      TEXT,
    is_active  INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS schedule_templates (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id    INTEGER NOT NULL,
    day_name      TEXT NOT NULL,
    period_key    TEXT NOT NULL,
    activity      TEXT,
    activity_type TEXT DEFAULT 'class',
    academic_year TEXT DEFAULT '2026-2027',
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS teacher_attendance (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id  INTEGER NOT NULL,
    date        TEXT NOT NULL,
    status      TEXT DEFAULT 'present',
    reason      TEXT,
    recorded_by INTEGER,
    recorded_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    UNIQUE(teacher_id, date)
);

CREATE TABLE IF NOT EXISTS coverage_log (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    date              TEXT NOT NULL,
    day_name          TEXT NOT NULL,
    period_key        TEXT NOT NULL,
    absent_teacher_id INTEGER NOT NULL,
    cover_teacher_id  INTEGER NOT NULL,
    original_activity TEXT,
    cover_class       TEXT,
    status            TEXT DEFAULT 'assigned',
    notes             TEXT,
    assigned_by       INTEGER,
    created_at        TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (absent_teacher_id) REFERENCES users(id),
    FOREIGN KEY (cover_teacher_id)  REFERENCES users(id),
    FOREIGN KEY (assigned_by)       REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS activity_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER,
    action     TEXT NOT NULL,
    details    TEXT,
    ip_address TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

echo "Tables created OK\n";
