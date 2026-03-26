-- CCS Sit-in Monitoring System Database Schema
CREATE DATABASE IF NOT EXISTS ccs_sitin_monitoring;
USE ccs_sitin_monitoring;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT '',
    course ENUM('BSIT', 'BSCS', 'BSIS', 'ACT') NOT NULL,
    year_level INT NOT NULL CHECK (year_level BETWEEN 1 AND 4),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    address TEXT DEFAULT '',
    remaining_sessions INT NOT NULL DEFAULT 30,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sit-in sessions table
CREATE TABLE IF NOT EXISTS sitin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lab_room VARCHAR(50) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    time_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    time_out TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reservation requests table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lab_room VARCHAR(50) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    preferred_date DATE DEFAULT NULL,
    preferred_time TIME DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin account (password: admin123)
INSERT IGNORE INTO users (id_number, last_name, first_name, course, year_level, email, password_hash, remaining_sessions, role)
VALUES ('00-0000', 'Admin', 'System', 'BSIT', 4, 'admin@ccs.uc.edu.ph',
    '$2y$10$edebnPJKP8wVmMPQhJKFke0MUqCZfvZQXOtLfRgLD6I3zI3t/VulW', 30, 'admin');
