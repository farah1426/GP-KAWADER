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
-- Dumping data for table `application`
--

LOCK TABLES `application` WRITE;
/*!40000 ALTER TABLE `application` DISABLE KEYS */;
/*!40000 ALTER TABLE `application` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `candidate_profile`
--

LOCK TABLES `candidate_profile` WRITE;
/*!40000 ALTER TABLE `candidate_profile` DISABLE KEYS */;
INSERT INTO `candidate_profile` VALUES (1,1,'+966536338840','2005-07-13',NULL,NULL),(2,2,'+966536338840','2005-07-12',NULL,NULL);
/*!40000 ALTER TABLE `candidate_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `certification`
--

LOCK TABLES `certification` WRITE;
/*!40000 ALTER TABLE `certification` DISABLE KEYS */;
INSERT INTO `certification` VALUES (1,2,'AWS Certified Cloud Practitioner - 2023',NULL),(2,2,'Oracle Certified Associate, Java SE 8 - 2020',NULL),(3,2,'Scrum Fundamentals Certified - 2021',NULL);
/*!40000 ALTER TABLE `certification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `cv_file`
--

LOCK TABLES `cv_file` WRITE;
/*!40000 ALTER TABLE `cv_file` DISABLE KEYS */;
INSERT INTO `cv_file` VALUES (1,1,'CV5_Daniel_Senior_TwoPages.pdf','uploads/cvs/aaa39ba3d10d68c6821954106e2b3f63.pdf',4670,'failed'),(2,2,'CV1_Abdullah_Classic.pdf','uploads/cvs/6c92ac84f9ae4a428bc460155f7c21fd.pdf',3664,'success');
/*!40000 ALTER TABLE `cv_file` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `education`
--

LOCK TABLES `education` WRITE;
/*!40000 ALTER TABLE `education` DISABLE KEYS */;
INSERT INTO `education` VALUES (1,2,'Bachelor of Science in Computer Science - King Saud University, Riyadh 2014 - 2018 | GPA: 4.62 / 5.0 - Second Class Honors',NULL);
/*!40000 ALTER TABLE `education` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `email_otp`
--

LOCK TABLES `email_otp` WRITE;
/*!40000 ALTER TABLE `email_otp` DISABLE KEYS */;
INSERT INTO `email_otp` VALUES (1,NULL,'aleenalzamilz@gmail.com','signup','$2y$10$A5hugo8K8oehTHcf.X4w3OEWc8QfVdvrR8mk8Up0TG5jk1J7larsS','2026-11-27 13:14:52',0,1,'2026-11-27 16:04:52'),(2,NULL,'aleenaldosariz@gmail.com','signup','$2y$10$p5PsSaniIfJZw/YhE2/5TufJJEHiHITmH911UyqZ8H6BoMXOtUK4W','2026-11-27 13:19:06',0,1,'2026-11-27 16:09:06'),(3,NULL,'aleenaldosariz@icloud.com','signup','$2y$10$QdpTquY0TvZlepoYF.MT4OkBUM7C7fHT9FGPVQhb20/BEaGgqHahq','2026-11-27 13:22:59',0,1,'2026-11-27 16:12:59');
/*!40000 ALTER TABLE `email_otp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `experience`
--

LOCK TABLES `experience` WRITE;
/*!40000 ALTER TABLE `experience` DISABLE KEYS */;
INSERT INTO `experience` VALUES (1,2,NULL,NULL,'Software Developer - Tamkeen Technologies, Riyadh Jan 2022 - Present — Developed and maintained REST APIs in Django that serve more than 40,000 monthly users. Reduced average page load time by 35% by optimizing SQL queries and adding Redis caching. Led the migration of two legacy PHP modules to a modern Python service with automated tests. Collaborated with designers and product owners to deliver 12 releases in 2023.'),(2,2,NULL,NULL,'Junior Web Developer - Najd Digital Solutions, Riyadh Jun 2019 - Dec 2021 — Built responsive customer portals using Laravel, MySQL and JavaScript. Implemented online payment integration and an email notification module. Fixed more than 150 reported bugs and wrote documentation for the support team.'),(3,2,NULL,NULL,'Software Engineering Intern - Al-Rawabi Systems, Riyadh Jun 2018 - Aug 2018 — Assisted the team in testing an inventory management system and preparing test reports.');
/*!40000 ALTER TABLE `experience` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `hr_profile`
--

LOCK TABLES `hr_profile` WRITE;
/*!40000 ALTER TABLE `hr_profile` DISABLE KEYS */;
INSERT INTO `hr_profile` VALUES (1,3,'Aleenz');
/*!40000 ALTER TABLE `hr_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `job_posting`
--

LOCK TABLES `job_posting` WRITE;
/*!40000 ALTER TABLE `job_posting` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_posting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `language`
--

LOCK TABLES `language` WRITE;
/*!40000 ALTER TABLE `language` DISABLE KEYS */;
INSERT INTO `language` VALUES (1,2,'Arabic (Native)'),(2,2,'English (Fluent)');
/*!40000 ALTER TABLE `language` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `password_reset`
--

LOCK TABLES `password_reset` WRITE;
/*!40000 ALTER TABLE `password_reset` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `skill`
--

LOCK TABLES `skill` WRITE;
/*!40000 ALTER TABLE `skill` DISABLE KEYS */;
INSERT INTO `skill` VALUES (1,2,'Python'),(2,2,'Java'),(3,2,'JavaScript'),(4,2,'PHP'),(5,2,'SQL'),(6,2,'Django'),(7,2,'Laravel'),(8,2,'React'),(9,2,'Bootstrap'),(10,2,'Git'),(11,2,'Docker'),(12,2,'Jira'),(13,2,'Postman'),(14,2,'Linux'),(15,2,'MySQL'),(16,2,'PostgreSQL'),(17,2,'MongoDB'),(18,2,'Redis');
/*!40000 ALTER TABLE `skill` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'aleenalzamilz@gmail.com','$2y$10$dPr3tSxBHmeJpagbOwapr.hM1B0.zr1f4TaLkp/C0gtM7fGtDaxGy','Aleen Alzamil','candidate'),(2,'aleenaldosariz@gmail.com','$2y$10$wgm7Flq9HPT3JD0FJLdBGejsrUQ5EsvL5Rmk7x1Xbm6vN89tj0Iya','Aleen Alzamil','candidate'),(3,'aleenaldosariz@icloud.com','$2y$10$r2l5b4XiO27bkESEt7eow.XOG5v4G2HMMbLNTvNkejXuZ2w.USH7a','Aleen Alzamil','hr');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-27 16:20:59
