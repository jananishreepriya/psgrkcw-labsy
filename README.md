# PSGRKCW LABSY - Lab Booking Management System

A complete lab booking system for colleges with role-based dashboards (Admin, Head, Staff), instant booking, email notifications, academic calendar with 6-day rotation, and support request system.

## Features
- Staff can book labs (regular or instant period-wise)
- Head approval → Admin final approval workflow
- Automatic email notifications (PHPMailer)
- Academic calendar with day order rotation
- Block days (holidays/exams)
- Bulk upload staff/heads/timetable via Excel
- Support requests from login page and dashboards

## Tech Stack
- PHP 7.4+ / MySQL
- HTML5/CSS3/JavaScript
- PHPMailer, PHPSpreadsheet, vlucas/phpdotenv

## Local Setup
1. Clone the repo
2. Run `composer install`
3. Copy `.env.example` to `.env` and fill in your credentials
4. Import `psgrkcw_labsy.sql` into MySQL
5. Start Apache/MySQL (XAMPP)
6. Access `http://localhost/psgrkcw-labsy/login.html`

## Default Admin Login (after database import)
- Email: admin@example.com
- Password: admin123

## License
Educational project – Developed by Janani Shree Priya M