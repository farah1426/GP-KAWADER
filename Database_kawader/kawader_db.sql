-- =====================================================================
-- Kawader - new database (matches the ER diagram)
-- MySQL 8.0+   |   run it in phpMyAdmin > Import, or paste it in the SQL tab
-- If you want another database name, replace `kawader1` in the first two lines
-- and in your PHP config file.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS `kawader1` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `kawader1`;

-- ---------------------------------------------------------------- user
CREATE TABLE `user` (
  `user_id`       int          NOT NULL AUTO_INCREMENT,
  `email`        varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name`    varchar(100) NOT NULL,
  `role`         enum('candidate','hr') NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- candidate_profile
CREATE TABLE `candidate_profile` (
  `profile_id`    int          NOT NULL AUTO_INCREMENT,
  `user_id`       int          NOT NULL,
  `phone`         varchar(20)  DEFAULT NULL,
  `date_of_birth` date         DEFAULT NULL,
  `photo_path`    varchar(255) DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_candidate_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- hr_profile
CREATE TABLE `hr_profile` (
  `hr_id`        int          NOT NULL AUTO_INCREMENT,
  `user_id`      int          NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`hr_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_hr_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- email_otp
-- user_id may be empty: at sign-up the account does not exist yet (the code uses the email)
CREATE TABLE `email_otp` (
  `otp_id`     int          NOT NULL AUTO_INCREMENT,
  `user_id`    int          DEFAULT NULL,
  `email`      varchar(100) NOT NULL,
  `purpose`    enum('signup','password_reset') NOT NULL,
  `otp_hash`   varchar(255) NOT NULL,
  `expires_at` datetime     NOT NULL,
  `attempts`   int          NOT NULL DEFAULT 0,
  `verified`   tinyint(1)   NOT NULL DEFAULT 0,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`otp_id`),
  KEY `idx_email_purpose` (`email`,`purpose`),
  KEY `idx_otp_user` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- password_reset
CREATE TABLE `password_reset` (
  `reset_id`   int          NOT NULL AUTO_INCREMENT,
  `user_id`    int          NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime     NOT NULL,
  `used`       tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- job_posting
CREATE TABLE `job_posting` (
  `job_id`           int          NOT NULL AUTO_INCREMENT,
  `hr_id`            int          NOT NULL,
  `title`            varchar(150) NOT NULL,
  `description`      text         NOT NULL,
  `responsibilities` text         DEFAULT NULL,
  `status`           enum('draft','open','closed') NOT NULL DEFAULT 'draft',
  `location`         varchar(100) DEFAULT NULL,
  `created_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at`          datetime     DEFAULT NULL,
  PRIMARY KEY (`job_id`),
  KEY `idx_job_posting_hr` (`hr_id`),
  CONSTRAINT `fk_job_posting_hr` FOREIGN KEY (`hr_id`) REFERENCES `hr_profile` (`hr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- cv_file
-- one CV per candidate (UNIQUE profile_id): uploading a new CV must REPLACE the old row
CREATE TABLE `cv_file` (
  `cv_id`        int          NOT NULL AUTO_INCREMENT,
  `profile_id`   int          NOT NULL,
  `file_name`    varchar(255) NOT NULL,
  `file_path`    varchar(255) NOT NULL,
  `file_size`    int          NOT NULL,
  `parse_status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`cv_id`),
  UNIQUE KEY `uq_cv_profile` (`profile_id`),
  CONSTRAINT `fk_cv_profile` FOREIGN KEY (`profile_id`) REFERENCES `candidate_profile` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- application
-- cv_id = the CV used for this application (empty only if the CV was deleted later)
CREATE TABLE `application` (
  `application_id` int      NOT NULL AUTO_INCREMENT,
  `profile_id`     int      NOT NULL,
  `job_id`         int      NOT NULL,
  `cv_id`          int      DEFAULT NULL,
  `status`         enum('submitted','shortlisted','rejected','hired') NOT NULL DEFAULT 'submitted',
  `applied_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  KEY `idx_application_profile` (`profile_id`),
  KEY `idx_application_job` (`job_id`),
  KEY `idx_application_cv` (`cv_id`),
  -- optional: stop the same candidate from applying twice to the same job
  -- UNIQUE KEY `uq_application_profile_job` (`profile_id`,`job_id`),
  CONSTRAINT `fk_application_profile` FOREIGN KEY (`profile_id`) REFERENCES `candidate_profile` (`profile_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_job`     FOREIGN KEY (`job_id`)     REFERENCES `job_posting` (`job_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_cv`      FOREIGN KEY (`cv_id`)      REFERENCES `cv_file` (`cv_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------- CV details (filled by the CV parser)
CREATE TABLE `skill` (
  `skill_id`   int          NOT NULL AUTO_INCREMENT,
  `cv_id`      int          NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  PRIMARY KEY (`skill_id`),
  KEY `idx_skill_cv` (`cv_id`),
  CONSTRAINT `fk_skill_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `education` (
  `education_id` int         NOT NULL AUTO_INCREMENT,
  `cv_id`        int         NOT NULL,
  `description`  text        DEFAULT NULL,
  `date`         varchar(50) DEFAULT NULL,
  PRIMARY KEY (`education_id`),
  KEY `idx_education_cv` (`cv_id`),
  CONSTRAINT `fk_education_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `experience` (
  `experience_id` int          NOT NULL AUTO_INCREMENT,
  `cv_id`         int          NOT NULL,
  `job_title`     varchar(150) DEFAULT NULL,
  `date`          varchar(50)  DEFAULT NULL,
  `description`   text         DEFAULT NULL,
  PRIMARY KEY (`experience_id`),
  KEY `idx_experience_cv` (`cv_id`),
  CONSTRAINT `fk_experience_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `certification` (
  `certification_id`   int          NOT NULL AUTO_INCREMENT,
  `cv_id`              int          NOT NULL,
  `certification_name` varchar(150) NOT NULL,
  `expiry_date`        date         DEFAULT NULL,
  PRIMARY KEY (`certification_id`),
  KEY `idx_certification_cv` (`cv_id`),
  CONSTRAINT `fk_certification_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `language` (
  `language_id`   int         NOT NULL AUTO_INCREMENT,
  `cv_id`         int         NOT NULL,
  `language_name` varchar(50) NOT NULL,
  PRIMARY KEY (`language_id`),
  KEY `idx_language_cv` (`cv_id`),
  CONSTRAINT `fk_language_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
