-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smartqms_test
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(10) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `ticket_id` (`ticket_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`ticket_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_attempts`
--

DROP TABLE IF EXISTS `auth_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_scope` varchar(40) NOT NULL,
  `identifier_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `last_attempt_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  UNIQUE KEY `uniq_attempt_scope_identifier_ip` (`attempt_scope`,`identifier_hash`,`ip_address`),
  KEY `idx_auth_attempts_locked_until` (`locked_until`),
  KEY `idx_auth_attempts_last_attempt_at` (`last_attempt_at`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_attempts`
--

LOCK TABLES `auth_attempts` WRITE;
/*!40000 ALTER TABLE `auth_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_jobs`
--

DROP TABLE IF EXISTS `email_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_jobs` (
  `job_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'notification',
  `status` enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `error_msg` varchar(255) DEFAULT NULL,
  `available_at` datetime DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`job_id`),
  KEY `idx_email_jobs_status_user` (`status`,`user_id`,`available_at`),
  KEY `idx_email_jobs_created_at` (`created_at`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `email_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_jobs`
--

LOCK TABLES `email_jobs` WRITE;
/*!40000 ALTER TABLE `email_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `window_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  KEY `window_id` (`window_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`ticket_id`),
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`window_id`) REFERENCES `service_windows` (`window_id`) ON DELETE SET NULL,
  CONSTRAINT `feedback_ibfk_4` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_services`
--

DROP TABLE IF EXISTS `health_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_code` varchar(10) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_encoded` tinyint(4) NOT NULL,
  `description` text DEFAULT NULL,
  `priority_only` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` tinyint(4) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `service_code` (`service_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `health_services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_services`
--

LOCK TABLES `health_services` WRITE;
/*!40000 ALTER TABLE `health_services` DISABLE KEYS */;
INSERT INTO `health_services` VALUES (1,'SVC-001','General Checkup / Consultation',1,NULL,0,1,1,NULL,'2026-07-22 15:55:02'),(2,'SVC-002','Vaccination / Immunization',2,NULL,0,1,2,NULL,'2026-07-22 15:55:02'),(3,'SVC-003','Prenatal / Maternal Care',3,NULL,0,1,3,NULL,'2026-07-22 15:55:02'),(4,'SVC-004','Dental Services',4,NULL,0,1,4,NULL,'2026-07-22 15:55:02'),(5,'SVC-005','Laboratory / Medical Certificate',5,NULL,0,1,5,NULL,'2026-07-22 15:55:02'),(6,'SVC-006','Family Planning',6,NULL,0,1,6,NULL,'2026-07-22 15:55:02'),(7,'SVC-007','Senior Citizen Services',7,NULL,1,1,7,NULL,'2026-07-22 15:55:02'),(8,'SVC-008','PWD Assessment / Certification',8,NULL,1,1,8,NULL,'2026-07-22 15:55:02');
/*!40000 ALTER TABLE `health_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ml_comparison_logs`
--

DROP TABLE IF EXISTS `ml_comparison_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ml_comparison_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_date` date NOT NULL,
  `algorithm` varchar(50) NOT NULL,
  `mae` decimal(8,4) DEFAULT NULL,
  `rmse` decimal(8,4) DEFAULT NULL,
  `r2` decimal(6,4) DEFAULT NULL,
  `mape` decimal(6,2) DEFAULT NULL,
  `is_best` tinyint(1) DEFAULT 0,
  `dataset_used` varchar(100) DEFAULT NULL,
  `sample_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ml_comparison_logs`
--

LOCK TABLES `ml_comparison_logs` WRITE;
/*!40000 ALTER TABLE `ml_comparison_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ml_comparison_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `channel` enum('browser','sms','both') DEFAULT 'both',
  `delivery_status` enum('pending','sent','failed') DEFAULT 'pending',
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notif_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`ticket_id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=316 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_tickets`
--

DROP TABLE IF EXISTS `queue_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queue_tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `window_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `reference_number` varchar(20) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `ticket_number` varchar(10) NOT NULL,
  `client_type` enum('regular','senior','pwd') DEFAULT 'regular',
  `priority_level` tinyint(4) DEFAULT 0,
  `status` enum('waiting','serving','completed','voided','skipped') DEFAULT 'waiting',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `called_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `user_id` (`user_id`),
  KEY `window_id` (`window_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `queue_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `queue_tickets_ibfk_2` FOREIGN KEY (`window_id`) REFERENCES `service_windows` (`window_id`) ON DELETE SET NULL,
  CONSTRAINT `queue_tickets_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4919 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_tickets`
--

LOCK TABLES `queue_tickets` WRITE;
/*!40000 ALTER TABLE `queue_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `queue_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_windows`
--

DROP TABLE IF EXISTS `service_windows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_windows` (
  `window_id` int(11) NOT NULL AUTO_INCREMENT,
  `window_name` varchar(50) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `status` enum('open','busy','closed') DEFAULT 'closed',
  `priority_enabled` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`window_id`),
  KEY `service_id` (`service_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `service_windows_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`) ON DELETE SET NULL,
  CONSTRAINT `service_windows_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1018 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_windows`
--

LOCK TABLES `service_windows` WRITE;
/*!40000 ALTER TABLE `service_windows` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_windows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_logs`
--

DROP TABLE IF EXISTS `sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `message` text NOT NULL,
  `type` enum('otp','notification','test') DEFAULT 'notification',
  `status` enum('sent','failed','simulated') DEFAULT 'simulated',
  `error_msg` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `shift` varchar(50) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `added_by` (`added_by`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1283 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_val` varchar(500) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'bhc_name','Barangay Health Center','Health Center Name','info',NULL,'2026-07-22 15:55:02'),(2,'bhc_address','','Health Center Address','info',NULL,'2026-07-22 15:55:02'),(3,'bhc_contact','','Contact Number','info',NULL,'2026-07-22 15:55:02'),(4,'bhc_barangay','','Barangay Name','info',NULL,'2026-07-22 15:55:02'),(5,'queue_open_time','07:00','Queue Opens At','queue',NULL,'2026-07-22 15:55:02'),(6,'queue_close_time','17:00','Queue Closes At','queue',NULL,'2026-07-22 15:55:02'),(7,'max_queue_per_day','100','Max Queue per Day','queue',NULL,'2026-07-22 15:55:02'),(8,'void_timeout_minutes','10','Void Timeout (minutes)','queue',NULL,'2026-07-22 15:55:02'),(9,'priority_queue_enabled','1','Priority Queue (Senior/PWD)','queue',NULL,'2026-07-22 15:55:02'),(10,'sms_enabled','0','Enable SMS Notifications','sms',NULL,'2026-07-22 15:55:02'),(11,'sms_api_key','','SMS API Key (Semaphore)','sms',NULL,'2026-07-22 15:55:02'),(12,'sms_sender_name','BHCQMS','SMS Sender Name','sms',NULL,'2026-07-22 15:55:02'),(13,'display_board_token','','Display Board Access Token','display',NULL,'2026-07-22 15:55:02'),(14,'ml_last_trained','','Model Last Trained','ml',NULL,'2026-07-22 15:55:02'),(15,'ml_dataset_used','','Training Dataset Used','ml',NULL,'2026-07-22 15:55:02');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('client','staff','admin') DEFAULT 'client',
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB AUTO_INCREMENT=10337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System','Administrator',NULL,NULL,'admin@smartqms.local','$2y$10$0dVVbYM0md/nlp53WdgET./DnO2eae3DZLJaMdU6OZeU29mXR7rHK','admin',1,NULL,NULL,NULL,1,NULL,'2026-07-22 15:55:02');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_ml_latest_comparison`
--

DROP TABLE IF EXISTS `v_ml_latest_comparison`;
/*!50001 DROP VIEW IF EXISTS `v_ml_latest_comparison`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_ml_latest_comparison` AS SELECT
 1 AS `id`,
  1 AS `run_date`,
  1 AS `algorithm`,
  1 AS `mae`,
  1 AS `rmse`,
  1 AS `r2`,
  1 AS `mape`,
  1 AS `is_best`,
  1 AS `dataset_used`,
  1 AS `sample_size`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_staff_productivity_today`
--

DROP TABLE IF EXISTS `v_staff_productivity_today`;
/*!50001 DROP VIEW IF EXISTS `v_staff_productivity_today`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_staff_productivity_today` AS SELECT
 1 AS `staff_id`,
  1 AS `staff_name`,
  1 AS `window_name`,
  1 AS `tickets_served`,
  1 AS `avg_wait_min`,
  1 AS `avg_service_min` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_today_queue`
--

DROP TABLE IF EXISTS `v_today_queue`;
/*!50001 DROP VIEW IF EXISTS `v_today_queue`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_today_queue` AS SELECT
 1 AS `ticket_id`,
  1 AS `reference_number`,
  1 AS `ticket_number`,
  1 AS `client_name`,
  1 AS `phone_number`,
  1 AS `client_type`,
  1 AS `priority_level`,
  1 AS `service_name`,
  1 AS `window_name`,
  1 AS `status`,
  1 AS `issued_at`,
  1 AS `called_at`,
  1 AS `completed_at`,
  1 AS `total_minutes_in_system` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `wait_time_logs`
--

DROP TABLE IF EXISTS `wait_time_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wait_time_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `queue_length` int(11) DEFAULT NULL,
  `hour_of_day` tinyint(4) DEFAULT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL,
  `service_type_encoded` tinyint(4) DEFAULT NULL,
  `client_type_encoded` tinyint(4) DEFAULT NULL,
  `active_windows` tinyint(4) DEFAULT NULL,
  `avg_service_time` decimal(5,2) DEFAULT NULL,
  `predicted_wait_min` decimal(6,2) DEFAULT NULL,
  `actual_wait_min` decimal(6,2) DEFAULT NULL,
  `actual_service_dur` int(11) DEFAULT NULL,
  `algorithm_used` varchar(50) DEFAULT 'Random Forest',
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `ticket_id` (`ticket_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `wait_time_logs_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `queue_tickets` (`ticket_id`),
  CONSTRAINT `wait_time_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=440 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wait_time_logs`
--

LOCK TABLES `wait_time_logs` WRITE;
/*!40000 ALTER TABLE `wait_time_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `wait_time_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `v_ml_latest_comparison`
--

/*!50001 DROP VIEW IF EXISTS `v_ml_latest_comparison`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ml_latest_comparison` AS select `ml`.`id` AS `id`,`ml`.`run_date` AS `run_date`,`ml`.`algorithm` AS `algorithm`,`ml`.`mae` AS `mae`,`ml`.`rmse` AS `rmse`,`ml`.`r2` AS `r2`,`ml`.`mape` AS `mape`,`ml`.`is_best` AS `is_best`,`ml`.`dataset_used` AS `dataset_used`,`ml`.`sample_size` AS `sample_size`,`ml`.`created_at` AS `created_at` from (`ml_comparison_logs` `ml` join (select `ml_comparison_logs`.`algorithm` AS `algorithm`,max(`ml_comparison_logs`.`run_date`) AS `latest_run` from `ml_comparison_logs` group by `ml_comparison_logs`.`algorithm`) `latest` on(`ml`.`algorithm` = `latest`.`algorithm` and `ml`.`run_date` = `latest`.`latest_run`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_staff_productivity_today`
--

/*!50001 DROP VIEW IF EXISTS `v_staff_productivity_today`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_staff_productivity_today` AS select `s`.`staff_id` AS `staff_id`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `staff_name`,`sw`.`window_name` AS `window_name`,count(`qt`.`ticket_id`) AS `tickets_served`,round(avg(case when `qt`.`ticket_id` is not null then `wl`.`actual_wait_min` end),2) AS `avg_wait_min`,round(avg(case when `qt`.`ticket_id` is not null then `wl`.`actual_service_dur` end) / 60,2) AS `avg_service_min` from ((((`staff` `s` join `users` `u` on(`s`.`user_id` = `u`.`user_id`)) left join `service_windows` `sw` on(`sw`.`staff_id` = `s`.`staff_id`)) left join `wait_time_logs` `wl` on(`wl`.`staff_id` = `s`.`staff_id`)) left join `queue_tickets` `qt` on(`wl`.`ticket_id` = `qt`.`ticket_id` and cast(`qt`.`completed_at` as date) = curdate())) group by `s`.`staff_id`,concat(`u`.`first_name`,' ',`u`.`last_name`),`sw`.`window_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_today_queue`
--

/*!50001 DROP VIEW IF EXISTS `v_today_queue`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_today_queue` AS select `qt`.`ticket_id` AS `ticket_id`,`qt`.`reference_number` AS `reference_number`,`qt`.`ticket_number` AS `ticket_number`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `client_name`,`u`.`phone_number` AS `phone_number`,`qt`.`client_type` AS `client_type`,`qt`.`priority_level` AS `priority_level`,`hs`.`service_name` AS `service_name`,`sw`.`window_name` AS `window_name`,`qt`.`status` AS `status`,`qt`.`issued_at` AS `issued_at`,`qt`.`called_at` AS `called_at`,`qt`.`completed_at` AS `completed_at`,timestampdiff(MINUTE,`qt`.`issued_at`,coalesce(`qt`.`completed_at`,current_timestamp())) AS `total_minutes_in_system` from (((`queue_tickets` `qt` join `users` `u` on(`qt`.`user_id` = `u`.`user_id`)) join `health_services` `hs` on(`qt`.`service_id` = `hs`.`service_id`)) left join `service_windows` `sw` on(`qt`.`window_id` = `sw`.`window_id`)) where cast(`qt`.`issued_at` as date) = curdate() order by `qt`.`priority_level` desc,`qt`.`issued_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-18 22:47:46
