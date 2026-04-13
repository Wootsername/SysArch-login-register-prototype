# CCS Sit-in Monitoring System

## College of Computer Studies — University of Cebu

A modern Sit-in Monitoring System built with **React** (CDN) + **PHP/MySQL** backend.

### Features
- **Login** with CCS branding, live clock, active session counters, remember me, form validation
- **Register** with progress bar, all required student fields, password toggle
- **Admin Dashboard** with real-time active sit-in student table, search, and session management
- **Student Dashboard** with backend history, active sessions, reservation requests, and remaining-session tracking
- **Announcements** managed by admin and shown on the student dashboard
- **Admin Reservations** inbox with approve/reject actions that start sit-in sessions
- Glassmorphism dark theme with purple (#5B1FA3) & gold (#D4A017) branding
- Animated glowing orb background, toast notifications, animated counters
- Fully responsive design

### Setup

#### 1. Database
1. Open XAMPP Control Panel and start `Apache` and `MySQL`
2. Open phpMyAdmin at `http://localhost/phpmyadmin`
3. Import `backend/database/schema.sql` to create the `ccs_sitin_monitoring` database and tables
4. If your MySQL credentials are different from XAMPP defaults, update `backend/config/database.php`
	- Host: `localhost`
	- Database: `ccs_sitin_monitoring`
	- Username: `root`
	- Password: empty by default in XAMPP

#### 2. PHP Server
Place the project in your web server's root, such as `C:\xampp\htdocs\SysArch-login-register-prototype`, then open it through Apache.

If you prefer the built-in PHP server, run:
```bash
php -S localhost:8080
```

#### 3. Open
For XAMPP, navigate to:
```text 
http://localhost/SysArch-login-register-prototype/index.html
```

If using the built-in PHP server, navigate to:
```text
http://localhost:8080/index.html
```

### Default Admin
- **ID Number:** `00-0000`
- **Password:** `admin123`

### Project Structure
```
├── index.html                  # React SPA (Login, Register, Dashboard)
├── assets/
│   └── ccs-logo.png            # CCS logo image
├── backend/
│   ├── .htaccess
│   ├── config/
│   │   └── database.php        # DB connection
│   ├── database/
│   │   └── schema.sql          # MySQL schema
│   └── api/
│       └── index.php           # REST API endpoints
└── README.md
```

### Tech Stack
- **Frontend:** React 18 (CDN), Vanilla CSS, Google Fonts (Syne + DM Sans)
- **Backend:** PHP 8+, PDO (MySQL)
- **Database:** MySQL / MariaDB
