-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: kawader_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `cv_id` int DEFAULT NULL,
  `job_id` varchar(5) NOT NULL,
  `status` varchar(30) DEFAULT NULL,
  `applied_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  KEY `fk_application_cv` (`cv_id`),
  KEY `fk_application_job` (`job_id`),
  KEY `fk_application_profile` (`profile_id`),
  CONSTRAINT `fk_application_cv` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_application_job` FOREIGN KEY (`job_id`) REFERENCES `job_posting` (`job_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_profile` FOREIGN KEY (`profile_id`) REFERENCES `candidate_profile` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `candidate_profile`
--

DROP TABLE IF EXISTS `candidate_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidate_profile` (
  `profile_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `candidate_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certification`
--

DROP TABLE IF EXISTS `certification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certification` (
  `certification_id` int NOT NULL AUTO_INCREMENT,
  `cv_id` int NOT NULL,
  `certification_name` varchar(200) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`certification_id`),
  KEY `fk_certification_cv_cascade` (`cv_id`),
  CONSTRAINT `fk_certification_cv_cascade` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cv_file`
--

DROP TABLE IF EXISTS `cv_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cv_file` (
  `cv_id` int NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int NOT NULL,
  `parse_status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`cv_id`),
  KEY `profile_id` (`profile_id`),
  CONSTRAINT `cv_file_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `candidate_profile` (`profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `education`
--

DROP TABLE IF EXISTS `education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `education` (
  `education_id` int NOT NULL AUTO_INCREMENT,
  `cv_id` int NOT NULL,
  `description` text,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`education_id`),
  KEY `fk_education_cv_cascade` (`cv_id`),
  CONSTRAINT `fk_education_cv_cascade` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_otp`
--

DROP TABLE IF EXISTS `email_otp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_otp` (
  `otp_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `purpose` enum('signup','password_reset') NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`otp_id`),
  KEY `idx_email_purpose` (`email`,`purpose`),
  KEY `idx_otp_user` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `experience`
--

DROP TABLE IF EXISTS `experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `experience` (
  `experience_id` int NOT NULL AUTO_INCREMENT,
  `cv_id` int NOT NULL,
  `job_title` varchar(200) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`experience_id`),
  KEY `fk_experience_cv_cascade` (`cv_id`),
  CONSTRAINT `fk_experience_cv_cascade` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hr_profile`
--

DROP TABLE IF EXISTS `hr_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_profile` (
  `hr_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`hr_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `hr_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_posting`
--

DROP TABLE IF EXISTS `job_posting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_posting` (
  `job_id` varchar(5) NOT NULL,
  `hr_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `responsibilities` text,
  `status` varchar(30) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `created_at` date NOT NULL,
  `ends_at` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_type` varchar(30) DEFAULT NULL,
  `work_arrangement` varchar(20) DEFAULT NULL,
  `min_experience_years` int DEFAULT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `required_skills` json DEFAULT NULL,
  `preferred_skills` json DEFAULT NULL,
  `mandatory_experience` tinyint(1) NOT NULL DEFAULT '0',
  `mandatory_education` tinyint(1) NOT NULL DEFAULT '0',
  `mandatory_certification` tinyint(1) NOT NULL DEFAULT '0',
  `mandatory_criteria` text,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `required_certifications` text,
  PRIMARY KEY (`job_id`),
  KEY `fk_job_posting_hr` (`hr_id`),
  CONSTRAINT `fk_job_posting_hr` FOREIGN KEY (`hr_id`) REFERENCES `hr_profile` (`hr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `language`
--

DROP TABLE IF EXISTS `language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `language` (
  `language_id` int NOT NULL AUTO_INCREMENT,
  `cv_id` int NOT NULL,
  `language_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`language_id`),
  KEY `fk_language_cv_cascade` (`cv_id`),
  CONSTRAINT `fk_language_cv_cascade` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset` (
  `reset_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skill`
--

DROP TABLE IF EXISTS `skill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skill` (
  `skill_id` int NOT NULL AUTO_INCREMENT,
  `cv_id` int NOT NULL,
  `skill_name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`skill_id`),
  KEY `fk_skill_cv_cascade` (`cv_id`),
  CONSTRAINT `fk_skill_cv_cascade` FOREIGN KEY (`cv_id`) REFERENCES `cv_file` (`cv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('candidate','hr') NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-27 16:34:27
