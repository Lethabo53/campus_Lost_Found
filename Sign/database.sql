-- If you already created this database before authentication was added, run:
--   ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email;
-- (then either re-import this file on a fresh DB, or set passwords for existing users manually)

CREATE DATABASE IF NOT EXISTS campus_lost_found
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE campus_lost_found;


-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(150) NOT NULL,

    email VARCHAR(190) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    points INT NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================
-- ITEMS
-- =====================================================

CREATE TABLE items (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    ticket_code VARCHAR(30) NOT NULL UNIQUE,

    report_type ENUM('lost','found') NOT NULL,

    title VARCHAR(200) NOT NULL,

    category VARCHAR(80) NOT NULL,

    color VARCHAR(100) DEFAULT NULL,

    description TEXT NOT NULL,

    brand_model VARCHAR(150) DEFAULT NULL,

    condition_notes VARCHAR(255) DEFAULT NULL,

    event_date DATE NOT NULL,

    location_name VARCHAR(255) NOT NULL,

    latitude DECIMAL(10,7) DEFAULT NULL,

    longitude DECIMAL(10,7) DEFAULT NULL,

    reporter_name VARCHAR(150) NOT NULL,

    reporter_email VARCHAR(190) NOT NULL,

    urgent TINYINT(1) NOT NULL DEFAULT 0,

    status ENUM(
        'open',
        'matched',
        'claimed',
        'reunited',
        'donated',
        'closed'
    ) NOT NULL DEFAULT 'open',

    security_question_1 VARCHAR(255) DEFAULT NULL,

    security_answer_1 VARCHAR(255) DEFAULT NULL,

    security_question_2 VARCHAR(255) DEFAULT NULL,

    security_answer_2 VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_report_type (report_type),

    INDEX idx_status (status),

    INDEX idx_category (category),

    INDEX idx_event_date (event_date),

    INDEX idx_location (location_name)
);


-- =====================================================
-- PHOTOS
-- =====================================================

CREATE TABLE item_photos (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    item_id INT UNSIGNED NOT NULL,

    file_path VARCHAR(500) NOT NULL,

    image_hash VARCHAR(256) DEFAULT NULL,

    average_r INT DEFAULT NULL,

    average_g INT DEFAULT NULL,

    average_b INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- =====================================================
-- SMART MATCHES
-- =====================================================

CREATE TABLE matches (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    lost_item_id INT UNSIGNED NOT NULL,

    found_item_id INT UNSIGNED NOT NULL,

    score DECIMAL(5,2) NOT NULL DEFAULT 0,

    photo_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    category_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    colour_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    brand_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    description_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    location_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    date_score DECIMAL(5,2) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_match (
        lost_item_id,
        found_item_id
    ),

    FOREIGN KEY (lost_item_id)
        REFERENCES items(id)
        ON DELETE CASCADE,

    FOREIGN KEY (found_item_id)
        REFERENCES items(id)
        ON DELETE CASCADE,

    INDEX idx_match_lost (lost_item_id),

    INDEX idx_match_score (score)
);


-- =====================================================
-- CLAIMS
-- =====================================================

CREATE TABLE claims (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    item_id INT UNSIGNED NOT NULL,

    claimant_name VARCHAR(150) NOT NULL,

    claimant_email VARCHAR(190) NOT NULL,

    answer_1 VARCHAR(255) DEFAULT NULL,

    answer_2 VARCHAR(255) DEFAULT NULL,

    verification_score INT DEFAULT 0,

    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- =====================================================
-- DONATIONS
-- =====================================================

CREATE TABLE donations (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    item_id INT UNSIGNED NOT NULL,

    donation_date DATE DEFAULT NULL,

    charity_name VARCHAR(200) DEFAULT 'Campus Charity Drive',

    status ENUM(
        'eligible',
        'approved',
        'donated'
    ) DEFAULT 'eligible',

    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- =====================================================
-- SAMPLE USER
-- =====================================================

INSERT INTO users (
    full_name,
    email,
    password_hash,
    points
)
VALUES (
    'Demo Good Samaritan',
    'demo@campus.edu',
    '$2y$10$6zRkHKFPdHADgfacCIP.tOej9r2gB2aqilDdSe168KQ16Kzd5OHnq',
    10
);
-- Demo account password is: demo1234
