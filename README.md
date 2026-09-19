# Smart-Campus

Centralized College Management System connecting Students, Faculty, Examination Cell, Administration, Office, and Student Activity Council (SAC).

## Features
- **Student Portal**: Attendance, Timetable, Internal & Semester Marks, Fee Status & Receipts, Hall Tickets, Learning Materials.
- **Faculty Portal**: Attendance Management, Marks Entry, Timetable, Learning Material Uploads.
- **Exam Cell**: Exam Scheduling, Hall Ticket Generation, Result Processing.
- **Office / Accounts**: Fee Collections, Dues Tracking, Student Records, Receipt Generation.
- **SAC (Student Activity Council)**: Events, Clubs, Announcements, Campus Activities.
- **Admin Portal**: System Configuration, User Management, Access Control, Reports.

## Local Setup (XAMPP)
1. Clone or place this repository in `xampp/htdocs/SmartCampus`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Import the database schema from `database/smartcampus.sql` into MySQL.
4. Configure database credentials in `config/database.php` or set environment variables.
5. Access the application at `http://localhost/SmartCampus/`.

## Cloud Deployment (Render Blueprint)
This repository includes a `render.yaml` Blueprint and a `Dockerfile` for seamless deployment on [Render](https://render.com).

1. Push this repository to GitHub.
2. Go to your [Render Dashboard](https://dashboard.render.com).
3. Click **New +** -> **Blueprint**.
4. Select this repository (`Smart-Campus`).
5. Render will automatically read `render.yaml`, build the Docker container, and deploy the application.
6. Configure your database environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`) in Render settings.
