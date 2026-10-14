# نظام إدارة الجداول المدرسية

## Quick Start
1. Copy `school_schedule/` to your web server (htdocs / www / public_html)
2. Edit `config/config.php` → set `APP_URL`
3. Visit `http://localhost/school_schedule/install.php`
4. Login: **admin / admin123**

## Features
- Admin dashboard: who is free RIGHT NOW, absent teachers, one-click coverage
- Teacher dashboard: personal daily timeline with current period highlighted
- Absence management: mark absent/present with reason
- Coverage assignment: pick free teacher → assign to absent teacher's class
- Reports: daily/weekly/monthly coverage stats
- API endpoint: `public/api.php` ready for mobile app + Odoo integration

## Key Notes from Excel
- Wednesday has NO Period 1 (starts 08:25)
- Friday is short day (5 periods, ends 12:20 with CCA)
- Break times differ per day
- Supervision duties tracked separately from teaching periods
