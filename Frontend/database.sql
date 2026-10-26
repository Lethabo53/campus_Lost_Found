-- =====================================================
-- Campus Lost & Found — full database rebuild
-- Team: Tech Titans — Richfield Centurion Campus
-- =====================================================
-- Running this whole file will DROP and RECREATE every
-- table below, deleting any existing data. Only run this
-- if you're okay starting fresh (or this is your first
-- time setting up the database).
--
-- Includes everything added so far:
--   * users: password reset token (forgot password),
--            points + snack_credits (rewards)
--   * items: status 'closed' ("I found my item" button)
--   * matches: per-factor smart-match scores
--   * claims: security-question verification
--   * donations: unclaimed items after 7 days
--   * redemptions: snack credit codes (SN-XXXXXXXX)
--   * sample users: 1 demo + 10 test students
--
-- How to run: phpMyAdmin -> SQL tab -> paste -> Go
-- =====================================================

CREATE DATABASE IF NOT EXISTS campus_lost_found
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE campus_lost_found;

-- Drop in reverse dependency order (children before parents)
DROP TABLE IF EXISTS redemptions;
DROP TABLE IF EXISTS donations;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS item_photos;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;


-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(150) NOT NULL,

    email VARCHAR(190) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    reset_token_hash VARCHAR(255) DEFAULT NULL,

    reset_token_expires DATETIME DEFAULT NULL,

    points INT NOT NULL DEFAULT 0,

    snack_credits INT NOT NULL DEFAULT 0,

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
-- SNACK CREDIT REDEMPTIONS
-- =====================================================

CREATE TABLE redemptions (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    code VARCHAR(30) NOT NULL UNIQUE,

    credits INT NOT NULL,

    status ENUM(
        'pending',
        'fulfilled',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    fulfilled_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


-- =====================================================
-- SAMPLE USERS (for testing and screenshots only)
-- Passwords are stored as bcrypt hashes, exactly like the
-- real sign-up page does. Sign in with the plain passwords
-- listed below.
-- =====================================================

-- Demo user
--   Email:    402311500@my.richfield.ac.za
--   Password: demo1234
INSERT INTO users (full_name, email, password_hash, points)
VALUES (
    'Demo Student',
    '402311500@my.richfield.ac.za',
    '$2y$10$6zRkHKFPdHADgfacCIP.tOej9r2gB2aqilDdSe168KQ16Kzd5OHnq',
    10
);

-- 10 test students (student number = 40 + 7 digits)
--   402311501@my.richfield.ac.za  Thabo Mokoena      Thabo@Campus01
--   402311502@my.richfield.ac.za  Naledi Dlamini     Naledi#Find02
--   402311503@my.richfield.ac.za  Sipho Nkosi        Sipho!Lost03
--   402311504@my.richfield.ac.za  Lerato Molefe      Lerato$Keys04
--   402311505@my.richfield.ac.za  Kagiso Sithole     Kagiso&Bag05
--   402311506@my.richfield.ac.za  Zanele Khumalo     Zanele*Card06
--   402311507@my.richfield.ac.za  Bongani Mahlangu   Bongani@Phone07
--   402311508@my.richfield.ac.za  Palesa Mabena      Palesa#Wallet08
--   402311509@my.richfield.ac.za  Tshepo Radebe      Tshepo!Books09
--   402311510@my.richfield.ac.za  Nomsa Zulu         Nomsa$Charger10
INSERT INTO users (full_name, email, password_hash) VALUES
('Thabo Mokoena', '402311501@my.richfield.ac.za', '$2y$10$HuWxv6LYxe48dqJIJAmyqeETtCchL3z.FraDqmb84.gIPTwD1I41O'),
('Naledi Dlamini', '402311502@my.richfield.ac.za', '$2y$10$pnxNdFCCoGjX48eLR9mxkOETdcCj9avxyY9sEg8i919lqqKaGmp3y'),
('Sipho Nkosi', '402311503@my.richfield.ac.za', '$2y$10$fBPNerJD0/.vSf7WN5lVHOlATI3ddhObXXzoRR7L1RlU.S8X9W5wi'),
('Lerato Molefe', '402311504@my.richfield.ac.za', '$2y$10$gbPopqBkqvqcp1lGAUfwfeKnlLENP9GnA9NcTsvBdpVHiAXsQEd5K'),
('Kagiso Sithole', '402311505@my.richfield.ac.za', '$2y$10$cALRChp02xIZ94nZ/qFFF.NFZNJF3OuJ9EdmLQRBdr.yp2VYtSkpa'),
('Zanele Khumalo', '402311506@my.richfield.ac.za', '$2y$10$XekvKAiCxbB3jowbgxv4sOIh.uN.gGCarCFW6lGM.5FisSjr8bqHi'),
('Bongani Mahlangu', '402311507@my.richfield.ac.za', '$2y$10$fz.zzUNNNkB4GUpuVzQHNeqfAZnrmEcQ0L8d08Z17ipZCW2yrasm.'),
('Palesa Mabena', '402311508@my.richfield.ac.za', '$2y$10$fdsGr9gshpzbL/69ytwVZesu6I5WHCyyaOIhiUbsIabPaooHcHW5y'),
('Tshepo Radebe', '402311509@my.richfield.ac.za', '$2y$10$O8eAWSJbxZVoXKL/Bj7plu2A2aR5OgMZFRmLHcc9xQLhwmu/nk0JC'),
('Nomsa Zulu', '402311510@my.richfield.ac.za', '$2y$10$eImTVSRSEw1zg81fBGyssOCwvjlxqEW82s92rchw2TsH6W28i4dea');


-- =====================================================
-- UPGRADING AN EXISTING DATABASE WITHOUT DELETING DATA
-- (only needed if you do NOT want to run the full rebuild;
--  run each line once, skip any that say the column exists)
-- =====================================================
-- ALTER TABLE users ADD COLUMN reset_token_hash VARCHAR(255) NULL;
-- ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL;
-- ALTER TABLE users ADD COLUMN snack_credits INT NOT NULL DEFAULT 0;
-- ALTER TABLE items MODIFY status ENUM('open','matched','claimed','reunited','donated','closed') NOT NULL DEFAULT 'open';
