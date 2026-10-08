-- KAWADER DATABASE - SPRINT 1

CREATE DATABASE IF NOT EXISTS kawader_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_0900_ai_ci;

USE kawader_db;


-- USER

CREATE TABLE IF NOT EXISTS user (
    user_id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('candidate', 'hr') NOT NULL,

    PRIMARY KEY (user_id),
    UNIQUE (email)
) ENGINE=InnoDB;


-- CANDIDATE PROFILE

CREATE TABLE IF NOT EXISTS candidate_profile (
    profile_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    location VARCHAR(100),
    photo_path VARCHAR(255),

    PRIMARY KEY (profile_id),
    UNIQUE (user_id),

    FOREIGN KEY (user_id)
        REFERENCES user(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- HR PROFILE

CREATE TABLE IF NOT EXISTS hr_profile (
    hr_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(100),

    PRIMARY KEY (hr_id),
    UNIQUE (user_id),

    FOREIGN KEY (user_id)
        REFERENCES user(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- PASSWORD RESET

CREATE TABLE IF NOT EXISTS password_reset (
    reset_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN NOT NULL DEFAULT FALSE,

    PRIMARY KEY (reset_id),

    FOREIGN KEY (user_id)
        REFERENCES user(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- CV FILE

CREATE TABLE IF NOT EXISTS cv_file (
    cv_id INT NOT NULL AUTO_INCREMENT,
    profile_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,

    PRIMARY KEY (cv_id),

    FOREIGN KEY (profile_id)
        REFERENCES candidate_profile(profile_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- CV PARSED

CREATE TABLE IF NOT EXISTS cv_parsed (
    parsed_id INT NOT NULL AUTO_INCREMENT,
    cv_id INT NOT NULL,
    skills TEXT,
    education TEXT,
    experience TEXT,
    certifications TEXT,
    languages TEXT,
    projects TEXT,
    parse_status ENUM('success', 'failed') NOT NULL,

    PRIMARY KEY (parsed_id),
    UNIQUE (cv_id),

    FOREIGN KEY (cv_id)
        REFERENCES cv_file(cv_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;