# CCS Sit-in Monitoring System

## College of Computer Studies — University of Cebu

A modern Sit-in Monitoring System built with **React** (CDN) + **PHP/MySQL** backend.

### Features
- **Login** with CCS branding, live clock, active session counters, remember me, form validation
- **Register** with progress bar, all required student fields, password toggle
- **Admin Dashboard** with real-time active sit-in student table, search, and session management
- Glassmorphism dark theme with purple (#5B1FA3) & gold (#D4A017) branding
- Animated glowing orb background, toast notifications, animated counters
- Fully responsive design

### Setup

#### 1. Database
1. Start MySQL (XAMPP, WAMP, or standalone)
2. Run `backend/database/schema.sql` to create the database and tables
3. Update `backend/config/database.php` with your MySQL credentials if needed

#### 2. PHP Server
Place the project in your web server's root (e.g., `htdocs/` for XAMPP), or run:
```bash
php -S localhost:8080
```

#### 3. Open
Navigate to `http://localhost:8080/index.html`

### Default Admin
- **ID Number:** `00-0000`
- **Password:** `password`

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
