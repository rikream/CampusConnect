CREATE DATABASE IF NOT EXISTS campusfind
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE campusfind;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    college_email VARCHAR(190) NOT NULL UNIQUE,
    student_id VARCHAR(60) NOT NULL UNIQUE,
    department VARCHAR(120) NOT NULL,
    study_year TINYINT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    location_group VARCHAR(80) NOT NULL DEFAULT 'Campus',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('lost', 'found') NOT NULL,
    name VARCHAR(120) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    occurred_at DATETIME NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    location_details VARCHAR(180) NOT NULL DEFAULT '',
    contact_preference ENUM('in_app', 'college_email') NOT NULL DEFAULT 'in_app',
    image_filename VARCHAR(80) NULL,
    review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    item_status ENUM('open', 'recovered') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_items_browse (review_status, item_status, type, created_at),
    INDEX idx_items_user (user_id, created_at),
    INDEX idx_items_category (category_id),
    INDEX idx_items_location (location_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    title VARCHAR(140) NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    INDEX idx_notifications_user (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    details VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name) VALUES
    ('Electronics'), ('Books & Notes'), ('Keys'), ('ID Cards & Wallets'),
    ('Clothing & Accessories'), ('Bags'), ('Stationery'), ('Other');

INSERT IGNORE INTO locations (name, location_group) VALUES
    ('Stone Bench', 'Outdoor'), ('Central Library', 'Academic'),
    ('Food Court', 'Dining'), ('Sanskriti Auditorium', 'Events'),
    ('Aryabhatta Block', 'Academic'), ('Main Block', 'Academic'),
    ('CSE Block', 'Academic'), ('ECE Block', 'Academic'),
    ('IT Block', 'Academic'), ('KPRIET Ground', 'Outdoor'),
    ('Hostel', 'Residential'), ('Main Entrance', 'Campus');
