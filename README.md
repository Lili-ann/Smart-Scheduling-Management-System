# Smart Scheduling Management System

A PHP and MySQL event scheduling system for managing events, staff assignments, visitor access, and event-related communication. The project is designed to run locally with XAMPP and includes role-based pages for administrators, staff members, and visitors.

## Features

- Role-based authentication for Admin and Staff users
- Admin dashboard for managing events, users, staff assignments, and visitor messages
- Event creation, editing, deletion, scheduling, and room/location management
- Staff dashboard showing assigned upcoming and past events
- Calendar view for staff event schedules
- Event detail pages with event poster and gallery image support
- Visitor access flow using invitation/access codes
- Visitor message/request form for contacting staff or admins
- Message forwarding from staff to admin
- Password reset flow with verification code handling
- Password validation and secure password hashing
- Progressive Web App assets, including `manifest.json` and `sw.js`
- Optional Grunt build tasks for minifying CSS and service worker files

## Tech Stack

- PHP
- MySQL / MariaDB
- HTML, CSS, and JavaScript
- XAMPP Apache server
- Node.js and Grunt for optional asset builds

## Project Structure

```text
Scheduler/
|-- admin.php              # Admin dashboard
|-- admin_event.php        # Event editing and gallery management
|-- db.php                 # Database connection and table creation
|-- events.php             # Visitor-accessible events page
|-- event_details.php      # Event details page
|-- login.php              # Login page
|-- signup.php             # Staff/Admin account registration
|-- reset_password.php     # Password reset flow
|-- user.php               # Staff dashboard
|-- visitor.php            # Visitor access and request page
|-- scheduler.sql          # Database schema and seed data
|-- styles.css             # Main stylesheet
|-- manifest.json          # PWA manifest
|-- sw.js                  # Service worker
|-- uploads/               # Uploaded event images
`-- dist/                  # Minified build output
```

## Requirements

- XAMPP, or another local PHP and MySQL environment
- PHP 8 or newer recommended
- MySQL or MariaDB
- Node.js and npm, only if you want to run the asset build commands

## How to Run Locally

1. Clone or copy the project into your XAMPP `htdocs` directory:

```bash
C:/xampp/htdocs/Scheduler
```

2. Start Apache and MySQL from the XAMPP Control Panel.

3. Create and import the database:

- Open `http://localhost/phpmyadmin`
- Import `scheduler.sql`
- The import creates a database named `scheduler`

4. Open the application in your browser:

```text
http://localhost/Scheduler/login.php
```

## Main Pages

- `login.php` - Staff/Admin login
- `signup.php` - Staff/Admin account registration
- `visitor.php` - Visitor access code entry and request form
- `admin.php` - Admin dashboard
- `user.php` - Staff dashboard
- `events.php` - Event listing for authorized visitors

## Optional Asset Build

Install dependencies:

```bash
npm install
```

Build minified assets:

```bash
npm run build
```

Watch asset files and rebuild automatically:

```bash
npm run watch
```

The build process validates `manifest.json`, minifies `styles.css` into `dist/styles.min.css`, and minifies `sw.js` into `dist/sw.min.js`.

## Notes

- Uploaded event images are stored in the `uploads/` directory.
- `db.php` also creates required tables automatically if they do not already exist.
- For production use, update database credentials, restrict public signup if needed, configure real email sending, and review file upload validation/security settings.
