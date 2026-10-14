<?php
// Run: php install.php
// OR visit: http://localhost/school_schedule/install.php
echo "<pre style='font-family:monospace;padding:30px;background:#1e1e1e;color:#d4d4d4;min-height:100vh'>";
echo "==============================================\n";
echo " School Schedule System - Installer\n";
echo "==============================================\n\n";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
echo "Running migrations...\n";
require __DIR__ . '/database/migrations/001_create_tables.php';
echo "Seeding data...\n";
require __DIR__ . '/database/seeds/seed_data.php';
echo "\n==============================================\n";
echo "Installation complete!\n\n";
echo "Admin login   : admin / admin123\n";
echo "Teacher login : amal  / teacher123\n";
echo "==============================================\n\n";
echo "<a href='public/login.php' style='color:#61dafb;font-size:1.1rem'>Go to Login →</a>";
echo "</pre>";
