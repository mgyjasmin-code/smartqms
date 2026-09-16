-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smartqms
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
) ENGINE=InnoDB AUTO_INCREMENT=457 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,2,'client','register','Client registered and OTP was generated',NULL,'::1','2026-06-27 14:21:47'),(2,2,'client','phone_verified','Client verified OTP',NULL,'::1','2026-06-27 14:22:00'),(3,2,'client','login','User signed in',NULL,'::1','2026-06-27 14:22:08'),(4,2,'client','ticket_created','Client joined queue',1,'::1','2026-06-27 14:22:28'),(5,2,'client','logout','User signed out',NULL,'::1','2026-06-27 14:22:56'),(6,1,'admin','login','User signed in',NULL,'::1','2026-06-27 14:23:57'),(7,1,'admin','logout','User signed out',NULL,'::1','2026-06-27 14:24:48'),(8,2,'client','login','User signed in',NULL,'::1','2026-06-27 14:35:58'),(9,2,'client','logout','User signed out',NULL,'::1','2026-06-27 14:52:02'),(10,2,'client','login','User signed in',NULL,'::1','2026-06-27 14:59:21'),(11,2,'client','logout','User signed out',NULL,'::1','2026-06-27 14:59:25'),(12,2,'client','login','User signed in',NULL,'::1','2026-06-27 15:08:47'),(13,2,'client','logout','User signed out',NULL,'::1','2026-06-27 15:09:04'),(14,1,'admin','login','User signed in',NULL,'::1','2026-06-30 08:36:07'),(15,1,'admin','login','User signed in',NULL,'::1','2026-06-30 08:36:26'),(16,1,'admin','logout','User signed out',NULL,'::1','2026-06-30 08:37:54'),(17,2,'client','login','User signed in',NULL,'::1','2026-06-30 08:45:45'),(18,2,'client','logout','User signed out',NULL,'::1','2026-06-30 08:45:56'),(19,2,'client','login','User signed in',NULL,'::1','2026-06-30 08:46:34'),(20,2,'client','logout','User signed out',NULL,'::1','2026-06-30 08:46:44'),(21,3,'client','register','Client registered and OTP was generated',NULL,'::1','2026-06-30 10:54:25'),(23,5,'client','register','Client registered and OTP was generated',NULL,'::1','2026-06-30 16:01:24'),(24,5,'client','email_verified','Client verified email OTP',NULL,'::1','2026-06-30 16:12:52'),(25,5,'client','login','User signed in',NULL,'::1','2026-06-30 16:12:52'),(26,5,'client','logout','User signed out',NULL,'::1','2026-06-30 16:13:02'),(27,5,'client','login','User signed in',NULL,'::1','2026-06-30 16:27:09'),(28,5,'client','logout','User signed out',NULL,'::1','2026-06-30 16:29:16'),(29,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-06-30 16:33:51'),(30,5,'client','login','User signed in',NULL,'::1','2026-06-30 16:34:15'),(31,5,'client','logout','User signed out',NULL,'::1','2026-06-30 16:35:46'),(32,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-01 08:30:08'),(33,5,'client','login','User signed in',NULL,'::1','2026-07-01 08:30:30'),(34,5,'client','logout','User signed out',NULL,'::1','2026-07-01 08:31:11'),(35,5,'client','login','User signed in',NULL,'::1','2026-07-02 05:32:29'),(36,5,'client','logout','User signed out',NULL,'::1','2026-07-02 05:32:45'),(37,5,'client','login','User signed in',NULL,'::1','2026-07-02 05:51:49'),(38,5,'client','logout','User signed out',NULL,'::1','2026-07-02 06:35:25'),(39,5,'client','login','User signed in',NULL,'::1','2026-07-02 06:48:09'),(40,5,'client','logout','User signed out',NULL,'::1','2026-07-02 06:49:46'),(41,5,'client','login','User signed in',NULL,'::1','2026-07-02 11:26:47'),(42,5,'client','logout','User signed out',NULL,'::1','2026-07-02 11:30:35'),(43,5,'client','login','User signed in',NULL,'::1','2026-07-02 11:50:48'),(44,5,'client','logout','User signed out',NULL,'::1','2026-07-02 11:50:51'),(45,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-02 11:53:43'),(46,5,'client','login','User signed in',NULL,'::1','2026-07-02 12:08:51'),(47,5,'client','logout','User signed out',NULL,'::1','2026-07-02 12:13:51'),(48,5,'client','login','User signed in',NULL,'::1','2026-07-02 12:31:11'),(49,5,'client','logout','User signed out',NULL,'::1','2026-07-02 12:31:32'),(50,5,'client','login','User signed in',NULL,'::1','2026-07-02 12:32:07'),(51,5,'client','logout','User signed out',NULL,'::1','2026-07-02 12:32:43'),(52,5,'client','login','User signed in',NULL,'::1','2026-07-02 12:46:13'),(53,5,'client','logout','User signed out',NULL,'::1','2026-07-02 13:23:00'),(54,2,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-02 13:32:36'),(55,2,'client','login','User signed in',NULL,'::1','2026-07-02 13:33:12'),(56,2,'client','logout','User signed out',NULL,'::1','2026-07-02 13:33:21'),(57,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-02 13:44:12'),(58,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-02 13:47:47'),(59,5,'client','login','User signed in',NULL,'::1','2026-07-02 13:47:47'),(60,5,'client','logout','User signed out',NULL,'::1','2026-07-02 13:47:51'),(61,5,'client','login','User signed in',NULL,'::1','2026-07-02 13:48:36'),(62,5,'client','logout','User signed out',NULL,'::1','2026-07-02 13:56:28'),(63,5,'client','login','User signed in',NULL,'::1','2026-07-02 13:57:04'),(64,5,'client','logout','User signed out',NULL,'::1','2026-07-02 14:49:08'),(65,5,'client','login','User signed in',NULL,'::1','2026-07-03 08:33:59'),(66,5,'client','logout','User signed out',NULL,'::1','2026-07-03 08:34:12'),(67,5,'client','login','User signed in',NULL,'::1','2026-07-03 09:48:21'),(68,5,'client','logout','User signed out',NULL,'::1','2026-07-03 09:48:24'),(69,5,'client','login','User signed in',NULL,'::1','2026-07-03 09:49:26'),(70,5,'client','logout','User signed out',NULL,'::1','2026-07-03 09:51:09'),(71,5,'client','login','User signed in',NULL,'::1','2026-07-03 10:13:55'),(72,5,'client','logout','User signed out',NULL,'::1','2026-07-03 10:13:58'),(73,5,'client','login','User signed in',NULL,'::1','2026-07-03 10:17:10'),(74,5,'client','logout','User signed out',NULL,'::1','2026-07-03 10:21:45'),(75,5,'client','login','User signed in',NULL,'::1','2026-07-03 10:31:43'),(76,5,'client','logout','User signed out',NULL,'::1','2026-07-03 10:31:45'),(77,2,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-03 10:34:08'),(78,2,'client','login','User signed in',NULL,'::1','2026-07-03 10:34:08'),(79,2,'client','logout','User signed out',NULL,'::1','2026-07-03 10:34:22'),(80,5,'client','login','User signed in',NULL,'::1','2026-07-03 10:42:23'),(81,5,'client','logout','User signed out',NULL,'::1','2026-07-03 10:42:26'),(82,5,'client','login','User signed in',NULL,'::1','2026-07-06 07:14:17'),(83,5,'client','logout','User signed out',NULL,'::1','2026-07-06 07:14:26'),(84,5,'client','login','User signed in',NULL,'::1','2026-07-06 08:36:18'),(85,5,'client','logout','User signed out',NULL,'::1','2026-07-06 08:43:18'),(86,5,'client','login','User signed in',NULL,'::1','2026-07-06 08:49:25'),(87,5,'client','logout','User signed out',NULL,'::1','2026-07-06 08:51:44'),(88,5,'client','login','User signed in',NULL,'::1','2026-07-06 10:01:44'),(89,5,'client','logout','User signed out',NULL,'::1','2026-07-06 10:18:47'),(90,5,'client','login','User signed in',NULL,'::1','2026-07-06 11:40:44'),(91,5,'client','ticket_created','Client joined queue',2,'::1','2026-07-06 11:40:56'),(92,5,'client','logout','User signed out',NULL,'::1','2026-07-06 12:01:17'),(93,5,'client','login','User signed in',NULL,'::1','2026-07-06 12:02:16'),(94,5,'client','logout','User signed out',NULL,'::1','2026-07-06 12:02:52'),(95,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-06 12:03:38'),(96,5,'client','login','User signed in',NULL,'::1','2026-07-06 12:03:38'),(97,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-07 08:34:17'),(98,5,'client','login','User signed in',NULL,'::1','2026-07-07 08:34:17'),(99,5,'client','logout','User signed out',NULL,'::1','2026-07-07 09:08:59'),(100,2,'client','login','User signed in',NULL,'::1','2026-07-07 09:09:23'),(101,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-08 12:23:03'),(102,5,'client','login','User signed in',NULL,'::1','2026-07-08 12:23:03'),(103,5,'client','logout','User signed out',NULL,'::1','2026-07-08 13:15:50'),(104,5,'client','login','User signed in',NULL,'::1','2026-07-08 13:16:09'),(105,5,'client','logout','User signed out',NULL,'::1','2026-07-08 13:19:52'),(106,5,'client','login','User signed in',NULL,'::1','2026-07-08 13:20:48'),(107,1,'admin','login','User signed in',NULL,'::1','2026-07-09 11:04:28'),(108,1,'admin','logout','User signed out',NULL,'::1','2026-07-09 13:40:30'),(109,1,'admin','login','User signed in',NULL,'::1','2026-07-09 13:46:09'),(110,1,'admin','logout','User signed out',NULL,'::1','2026-07-09 13:47:43'),(111,1,'admin','login','User signed in',NULL,'::1','2026-07-09 13:49:23'),(112,1,'admin','login','User signed in',NULL,'::1','2026-07-09 14:11:24'),(113,1,'admin','logout','User signed out',NULL,'::1','2026-07-09 14:11:49'),(114,1,'admin','login','User signed in',NULL,'::1','2026-07-09 14:11:57'),(115,1,'admin','logout','User signed out',NULL,'::1','2026-07-09 16:11:22'),(116,1,'admin','login','User signed in',NULL,'::1','2026-07-09 16:11:49'),(117,1,'admin','logout','User signed out',NULL,'::1','2026-07-09 16:28:39'),(118,1,'admin','login','User signed in',NULL,'::1','2026-07-09 16:28:52'),(119,1,'admin','login','User signed in',NULL,'::1','2026-07-10 04:44:33'),(120,1,'admin','settings_updated','Updated system settings',NULL,'::1','2026-07-10 04:53:42'),(121,1,'admin','settings_updated','Updated system settings',NULL,'::1','2026-07-10 04:53:42'),(122,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 07:26:00'),(123,1,'admin','login','User signed in',NULL,'::1','2026-07-10 07:26:33'),(124,1,'admin','settings_updated','Updated system settings',NULL,'::1','2026-07-10 07:48:53'),(125,1,'admin','settings_updated','Updated system settings',NULL,'::1','2026-07-10 07:48:54'),(126,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 07:49:15'),(127,1,'admin','login','User signed in',NULL,'::1','2026-07-10 07:49:25'),(128,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 09:49:00'),(129,1,'admin','login','User signed in',NULL,'::1','2026-07-10 09:49:22'),(130,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 10:36:14'),(131,1,'admin','login','User signed in',NULL,'::1','2026-07-10 10:36:26'),(132,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 10:39:40'),(133,1,'admin','login','User signed in',NULL,'::1','2026-07-10 10:40:00'),(134,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 10:43:45'),(135,5,'client','login','User signed in',NULL,'::1','2026-07-10 10:49:14'),(136,5,'client','logout','User signed out',NULL,'::1','2026-07-10 10:50:24'),(137,1,'admin','login','User signed in',NULL,'::1','2026-07-10 10:50:34'),(138,1,'admin','logout','User signed out',NULL,'::1','2026-07-10 14:25:00'),(139,5,'client','login','User signed in',NULL,'::1','2026-07-10 14:27:25'),(140,5,'client','logout','User signed out',NULL,'::1','2026-07-10 14:29:25'),(141,1,'admin','logout','User signed out',NULL,'::1','2026-07-12 08:40:09'),(142,1,'admin','login','User signed in',NULL,'::1','2026-07-12 08:40:21'),(143,1,'admin','logout','User signed out',NULL,'::1','2026-07-12 09:56:58'),(144,1,'admin','login','User signed in',NULL,'::1','2026-07-12 09:57:42'),(145,5,'client','login','User signed in',NULL,'::1','2026-07-12 10:03:58'),(146,5,'client','logout','User signed out',NULL,'::1','2026-07-12 10:13:30'),(147,1,'admin','login','User signed in',NULL,'::1','2026-07-12 10:15:30'),(148,5,'client','login','User signed in',NULL,'::1','2026-07-12 11:32:36'),(149,1,'admin','staff_created','Created staff account for jdoe@gmail.com',NULL,'::1','2026-07-12 15:12:01'),(150,1,'admin','window_created','Created window Window 1',NULL,'::1','2026-07-12 15:12:45'),(151,1,'admin','window_updated','Updated window Window 1',NULL,'::1','2026-07-12 15:12:57'),(152,1,'admin','logout','User signed out',NULL,'::1','2026-07-12 15:14:10'),(153,6,'staff','login','User signed in',NULL,'::1','2026-07-12 15:14:22'),(154,6,'staff','window_opened','Window set to open',NULL,'::1','2026-07-12 15:14:39'),(155,6,'staff','ticket_called','Called ticket A-001',1,'::1','2026-07-12 15:14:43'),(156,6,'staff','ticket_completed','Completed ticket A-001',1,'::1','2026-07-12 15:16:12'),(157,6,'staff','window_opened','Window set to open',NULL,'::1','2026-07-12 15:16:22'),(158,6,'staff','ticket_called','Called ticket A-001',2,'::1','2026-07-12 15:16:24'),(159,6,'staff','ticket_completed','Completed ticket A-001',2,'::1','2026-07-12 15:18:03'),(160,5,'client','ticket_created','Client joined queue',3,'::1','2026-07-12 15:20:18'),(161,1,'admin','login','User signed in',NULL,'::1','2026-07-13 07:25:46'),(162,1,'admin','logout','User signed out',NULL,'::1','2026-07-13 07:37:05'),(163,6,'staff','login','User signed in',NULL,'::1','2026-07-13 07:37:20'),(164,5,'client','login','User signed in',NULL,'::1','2026-07-13 08:01:17'),(165,5,'client','logout','User signed out',NULL,'::1','2026-07-13 08:48:57'),(166,6,'staff','login','User signed in',NULL,'::1','2026-07-13 08:49:13'),(167,1,'admin','service_toggled','service_id=6',NULL,'::1','2026-07-13 08:50:42'),(168,6,'staff','logout','User signed out',NULL,'::1','2026-07-13 08:59:26'),(169,5,'client','login','User signed in',NULL,'::1','2026-07-13 09:00:57'),(170,5,'client','logout','User signed out',NULL,'::1','2026-07-13 09:41:16'),(171,5,'client','login','User signed in',NULL,'::1','2026-07-13 09:41:58'),(172,5,'client','logout','User signed out',NULL,'::1','2026-07-13 11:20:18'),(173,6,'staff','login','User signed in',NULL,'::1','2026-07-13 11:20:29'),(174,6,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-13 11:20:37'),(175,6,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-13 11:20:38'),(176,6,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-13 11:20:39'),(177,6,'staff','window_opened','Window set to open',NULL,'::1','2026-07-13 11:20:40'),(178,1,'admin','logout','User signed out',NULL,'::1','2026-07-13 11:21:05'),(179,6,'staff','login','User signed in',NULL,'::1','2026-07-13 11:21:12'),(180,6,'staff','logout','User signed out',NULL,'::1','2026-07-13 11:21:36'),(181,2,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-07-13 11:26:38'),(182,2,'client','login','User signed in',NULL,'::1','2026-07-13 11:26:38'),(183,6,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-13 11:27:23'),(184,6,'staff','window_opened','Window set to open',NULL,'::1','2026-07-13 11:27:24'),(185,2,'client','ticket_created','Client joined queue',4,'::1','2026-07-13 11:27:24'),(186,6,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-13 11:29:54'),(187,6,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-13 11:29:56'),(188,6,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-13 11:29:57'),(189,6,'staff','window_opened','Window set to open',NULL,'::1','2026-07-13 11:29:58'),(190,6,'staff','logout','User signed out',NULL,'::1','2026-07-13 11:33:02'),(191,1,'admin','login','User signed in',NULL,'::1','2026-07-13 11:33:14'),(192,1,'admin','logout','User signed out',NULL,'::1','2026-07-13 11:35:20'),(193,1,'admin','login','User signed in',NULL,'::1','2026-07-13 11:35:34'),(194,1,'admin','staff_created','Created staff account for peter.parker@smartqms.local',NULL,'::1','2026-07-13 11:36:32'),(195,1,'admin','window_created','Created window Window 2',NULL,'::1','2026-07-13 11:38:30'),(196,1,'admin','logout','User signed out',NULL,'::1','2026-07-13 11:38:37'),(197,1,'admin','login','User signed in',NULL,'::1','2026-07-13 11:40:04'),(198,2,'client','logout','User signed out',NULL,'::1','2026-07-13 11:51:23'),(199,1,'admin','login','User signed in',NULL,'::1','2026-07-13 11:54:48'),(200,1,'admin','login','User signed in',NULL,'::1','2026-07-18 11:43:04'),(201,5,'client','login','User signed in',NULL,'::1','2026-07-21 11:51:23'),(202,5,'client','logout','User signed out',NULL,'::1','2026-07-21 11:51:30'),(203,1,'admin','login','User signed in',NULL,'::1','2026-07-21 11:51:50'),(204,1,'admin','login','User signed in',NULL,'::1','2026-07-21 12:05:13'),(205,1,'admin','login','User signed in',NULL,'::1','2026-07-22 16:00:29'),(206,1,'admin','login','User signed in',NULL,'::1','2026-07-23 10:33:55'),(207,1,'admin','logout','User signed out',NULL,'::1','2026-07-23 10:34:02'),(208,1,'admin','login','User signed in',NULL,'::1','2026-07-23 10:34:26'),(209,1,'admin','logout','User signed out',NULL,'::1','2026-07-23 15:10:00'),(210,1,'admin','login','User signed in',NULL,'::1','2026-07-23 15:10:18'),(211,1,'admin','service_toggled','service_id=6',NULL,'::1','2026-07-23 15:59:24'),(212,1,'admin','service_updated','General Checkup / Consultation',NULL,'::1','2026-07-24 10:23:33'),(213,1,'admin','login','User signed in',NULL,'::1','2026-07-24 12:10:58'),(214,1,'admin','staff_deleted','Removed staff account for peter.parker@smartqms.local by admin user_id=1',NULL,'::1','2026-07-25 05:31:21'),(215,1,'admin','login','User signed in',NULL,'::1','2026-07-25 05:48:57'),(216,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:49:13'),(217,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:50:49'),(218,1,'admin','staff_updated','Updated staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:55:37'),(219,1,'admin','logout','User signed out',NULL,'::1','2026-07-25 05:55:50'),(220,1,'admin','staff_updated','Updated staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:56:16'),(221,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:57:03'),(222,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 05:57:06'),(223,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 08:51:20'),(224,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 08:57:01'),(225,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-25 08:57:09'),(226,1,'admin','login','User signed in',NULL,'::1','2026-07-25 08:58:16'),(227,1,'admin','login','User signed in',NULL,'::1','2026-07-25 14:09:21'),(228,1,'admin','logout','User signed out',NULL,'::1','2026-07-26 12:44:24'),(229,1,'admin','login','User signed in',NULL,'::1','2026-07-26 12:46:20'),(230,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 08:45:39'),(231,5,'client','login','User signed in',NULL,'::1','2026-07-27 08:47:07'),(232,5,'client','logout','User signed out',NULL,'::1','2026-07-27 09:15:32'),(233,1,'admin','login','User signed in',NULL,'::1','2026-07-27 09:20:55'),(234,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 09:22:03'),(235,5,'client','login','User signed in',NULL,'::1','2026-07-27 09:22:32'),(236,1,'admin','login','User signed in',NULL,'::1','2026-07-27 09:22:54'),(237,1,'admin','staff_created','Created staff account for sf2.yuki@gmail.com',NULL,'::1','2026-07-27 09:25:18'),(238,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 09:25:26'),(239,8,'staff','login','User signed in',NULL,'::1','2026-07-27 09:25:35'),(240,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 09:26:37'),(241,1,'admin','login','User signed in',NULL,'::1','2026-07-27 09:26:46'),(242,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 09:27:50'),(243,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-27 09:36:13'),(244,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 09:36:38'),(245,8,'staff','login','User signed in',NULL,'::1','2026-07-27 09:36:44'),(246,8,'staff','ticket_called','Called ticket A-001',3,'::1','2026-07-27 09:36:49'),(247,8,'staff','ticket_completed','Completed ticket A-001',3,'::1','2026-07-27 09:38:49'),(248,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 09:39:16'),(249,1,'admin','login','User signed in',NULL,'::1','2026-07-27 09:39:23'),(250,5,'client','logout','User signed out',NULL,'::1','2026-07-27 10:31:06'),(251,1,'admin','login','User signed in',NULL,'::1','2026-07-27 10:42:32'),(252,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-27 10:43:36'),(253,1,'admin','settings_updated','Updated system settings',NULL,'::1','2026-07-27 10:59:40'),(254,8,'staff','login','User signed in',NULL,'::1','2026-07-27 11:01:06'),(255,8,'staff','ticket_called','Called ticket A-001',4,'::1','2026-07-27 11:01:29'),(256,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:01:58'),(257,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:00'),(258,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:02:01'),(259,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:02'),(260,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 11:02:03'),(261,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:04'),(262,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:02:04'),(263,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:05'),(264,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:07'),(265,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 11:02:09'),(266,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:12'),(267,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:02:13'),(268,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:02:14'),(269,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:02:15'),(270,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 11:02:15'),(271,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:03:20'),(272,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:03:20'),(273,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:03:21'),(274,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 11:03:21'),(275,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 11:03:22'),(276,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 11:03:24'),(277,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 11:03:29'),(278,8,'staff','ticket_completed','Completed ticket A-001',4,'::1','2026-07-27 11:03:42'),(279,1,'admin','staff_updated','Updated staff account for sf2.yuki@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 11:16:59'),(280,1,'admin','staff_updated','Updated staff account for sf2.yuki@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 11:33:22'),(281,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 11:34:01'),(282,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 11:34:14'),(283,1,'admin','service_toggled','service_id=1',NULL,'::1','2026-07-27 11:37:11'),(284,1,'admin','service_toggled','service_id=1',NULL,'::1','2026-07-27 11:37:24'),(285,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 12:25:53'),(286,1,'admin','login','User signed in',NULL,'::1','2026-07-27 12:26:00'),(287,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 13:58:21'),(288,1,'admin','login','User signed in',NULL,'::1','2026-07-27 14:25:32'),(289,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 14:26:25'),(290,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 14:26:33'),(291,5,'client','login','User signed in',NULL,'::1','2026-07-27 14:33:47'),(292,5,'client','logout','User signed out',NULL,'::1','2026-07-27 14:34:03'),(293,1,'admin','login','User signed in',NULL,'::1','2026-07-27 15:28:54'),(294,8,'staff','login','User signed in',NULL,'::1','2026-07-27 15:39:21'),(295,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 15:39:52'),(296,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 15:49:33'),(297,5,'client','login','User signed in',NULL,'::1','2026-07-27 15:54:02'),(298,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 15:54:59'),(299,1,'admin','staff_updated','Updated staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 15:55:18'),(300,5,'client','logout','User signed out',NULL,'::1','2026-07-27 15:55:22'),(301,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 15:57:08'),(302,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 15:57:17'),(303,1,'admin','login','User signed in',NULL,'::1','2026-07-27 15:57:37'),(304,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 15:57:48'),(305,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-27 15:57:56'),(306,1,'admin','staff_updated','Updated staff account for sf2.yuki@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 15:58:15'),(307,1,'admin','staff_updated','Updated staff account for sf2.yuki@gmail.com by admin user_id=1',NULL,'::1','2026-07-27 15:58:43'),(308,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 15:59:20'),(309,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 15:59:24'),(310,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 15:59:34'),(311,5,'client','login','User signed in',NULL,'::1','2026-07-27 15:59:58'),(312,5,'client','feedback_submitted','Rating: 5',3,'::1','2026-07-27 16:07:22'),(313,5,'client','logout','User signed out',NULL,'::1','2026-07-27 16:10:44'),(314,5,'client','login','User signed in',NULL,'::1','2026-07-27 16:11:37'),(315,8,'staff','login','User signed in',NULL,'::1','2026-07-27 16:14:39'),(316,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 16:19:24'),(317,1,'admin','service_toggled','service_id=8',NULL,'::1','2026-07-27 16:19:34'),(318,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 16:44:05'),(319,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 16:44:08'),(320,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 16:44:38'),(321,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 16:44:40'),(322,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 16:50:14'),(323,5,'client','login','User signed in',NULL,'::1','2026-07-27 16:50:36'),(324,5,'client','login','User signed in',NULL,'::1','2026-07-27 17:06:25'),(325,5,'client','ticket_created','Client joined queue',5,'::1','2026-07-27 17:10:28'),(326,5,'client','logout','User signed out',NULL,'::1','2026-07-27 17:10:47'),(327,8,'staff','login','User signed in',NULL,'::1','2026-07-27 17:10:54'),(328,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 17:11:19'),(329,1,'admin','login','User signed in',NULL,'::1','2026-07-27 17:11:26'),(330,1,'admin','staff_created','Created staff account for aduncan@gmail.com',NULL,'::1','2026-07-27 17:13:04'),(331,1,'admin','window_updated','Updated window Window 1',NULL,'::1','2026-07-27 17:13:19'),(332,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-27 17:13:26'),(333,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 17:13:33'),(334,9,'staff','login','User signed in',NULL,'::1','2026-07-27 17:13:49'),(335,9,'staff','ticket_called','Called ticket A-001',5,'::1','2026-07-27 17:14:16'),(336,9,'staff','ticket_completed','Completed ticket A-001',5,'::1','2026-07-27 17:16:17'),(337,9,'staff','logout','User signed out',NULL,'::1','2026-07-27 17:17:12'),(338,8,'staff','login','User signed in',NULL,'::1','2026-07-27 17:17:18'),(339,5,'client','ticket_created','Client joined queue',6,'::1','2026-07-27 17:17:27'),(340,8,'staff','ticket_called','Called ticket A-002',6,'::1','2026-07-27 17:17:52'),(341,8,'staff','ticket_completed','Completed ticket A-002',6,'::1','2026-07-27 17:19:27'),(342,5,'client','feedback_submitted','Rating: 4',6,'::1','2026-07-27 17:19:55'),(343,5,'client','feedback_submitted','Rating: 3',5,'::1','2026-07-27 17:50:00'),(344,5,'client','logout','User signed out',NULL,'::1','2026-07-27 17:56:53'),(345,8,'staff','login','User signed in',NULL,'::1','2026-07-27 17:57:05'),(346,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 18:07:51'),(347,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 18:07:59'),(348,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 18:08:01'),(349,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-07-27 18:08:02'),(350,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 18:08:03'),(351,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 18:08:04'),(352,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 18:12:22'),(353,1,'admin','login','User signed in',NULL,'::1','2026-07-27 18:12:42'),(354,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-07-27 18:36:00'),(355,8,'staff','window_opened','Window set to open',NULL,'::1','2026-07-27 18:36:01'),(356,8,'staff','logout','User signed out',NULL,'::1','2026-07-27 19:01:14'),(357,1,'admin','login','User signed in',NULL,'::1','2026-07-27 19:01:22'),(358,1,'admin','logout','User signed out',NULL,'::1','2026-07-27 19:02:25'),(359,1,'admin','login','User signed in',NULL,'::1','2026-07-28 00:45:34'),(360,5,'client','login','User signed in',NULL,'::1','2026-07-28 00:53:11'),(361,5,'client','ticket_created','Client joined queue',7,'::1','2026-07-28 00:54:20'),(362,1,'admin','logout','User signed out',NULL,'::1','2026-07-28 00:54:43'),(363,8,'staff','login','User signed in',NULL,'::1','2026-07-28 00:54:50'),(364,8,'staff','logout','User signed out',NULL,'::1','2026-07-28 00:55:16'),(365,1,'admin','login','User signed in',NULL,'::1','2026-07-28 00:55:26'),(366,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-28 00:55:49'),(367,1,'admin','logout','User signed out',NULL,'::1','2026-07-28 00:55:55'),(368,8,'staff','login','User signed in',NULL,'::1','2026-07-28 00:56:03'),(369,8,'staff','ticket_called','Called ticket A-003',7,'::1','2026-07-28 00:56:25'),(370,8,'staff','ticket_completed','Completed ticket A-003',7,'::1','2026-07-28 00:57:27'),(371,8,'staff','logout','User signed out',NULL,'::1','2026-07-28 00:57:46'),(372,1,'admin','login','User signed in',NULL,'::1','2026-07-28 00:57:55'),(373,5,'client','feedback_submitted','Rating: 4',7,'::1','2026-07-28 00:58:14'),(374,5,'client','logout','User signed out',NULL,'::1','2026-07-28 02:24:22'),(375,1,'admin','logout','User signed out',NULL,'::1','2026-07-28 02:25:17'),(376,5,'client','login','User signed in',NULL,'::1','2026-07-28 02:29:35'),(377,5,'client','ticket_created','Client joined queue',8,'::1','2026-07-28 02:33:09'),(378,1,'admin','login','User signed in',NULL,'::1','2026-07-28 02:45:01'),(379,1,'admin','logout','User signed out',NULL,'::1','2026-07-28 02:47:45'),(380,8,'staff','login','User signed in',NULL,'::1','2026-07-28 02:47:51'),(381,8,'staff','logout','User signed out',NULL,'::1','2026-07-28 02:48:12'),(382,1,'admin','login','User signed in',NULL,'::1','2026-07-28 02:48:21'),(383,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-07-28 02:48:35'),(384,1,'admin','logout','User signed out',NULL,'::1','2026-07-28 02:48:42'),(385,8,'staff','login','User signed in',NULL,'::1','2026-07-28 02:48:48'),(386,8,'staff','ticket_called','Called ticket A-004',8,'::1','2026-07-28 02:48:51'),(387,8,'staff','ticket_completed','Completed ticket A-004',8,'::1','2026-07-28 02:48:57'),(388,5,'client','ticket_created','Client joined queue',9,'::1','2026-07-28 02:49:14'),(389,1,'admin','login','User signed in',NULL,'::1','2026-08-09 14:49:43'),(390,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-08-09 14:50:20'),(391,8,'staff','login','User signed in',NULL,'::1','2026-08-17 06:21:43'),(392,8,'staff','ticket_called','Called ticket A-005',9,'::1','2026-08-17 06:21:49'),(393,8,'staff','ticket_completed','Completed ticket A-005',9,'::1','2026-08-17 06:23:00'),(394,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-17 06:23:04'),(395,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-08-17 06:23:05'),(396,8,'staff','window_opened','Window set to open',NULL,'::1','2026-08-17 06:23:06'),(397,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-17 06:23:24'),(398,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-08-17 06:23:25'),(399,8,'staff','window_opened','Window set to open',NULL,'::1','2026-08-17 06:23:30'),(400,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-17 06:23:32'),(401,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-08-17 06:23:33'),(402,1,'admin','logout','User signed out',NULL,'::1','2026-08-17 08:04:16'),(403,1,'admin','login','User signed in',NULL,'::1','2026-08-17 08:09:47'),(404,1,'admin','window_created','Created window Window 3',NULL,'::1','2026-08-17 08:11:39'),(405,1,'admin','window_updated','Updated window Window 2',NULL,'::1','2026-08-17 08:11:47'),(406,8,'staff','logout','User signed out',NULL,'::1','2026-08-17 08:11:57'),(407,8,'staff','login','User signed in',NULL,'::1','2026-08-17 08:12:08'),(408,1,'admin','window_updated','Updated window Window 3',NULL,'::1','2026-08-17 08:13:00'),(409,8,'staff','logout','User signed out',NULL,'::1','2026-08-17 09:34:48'),(410,1,'admin','service_updated','General Checkup / Consultation',NULL,'::1','2026-08-18 14:01:01'),(411,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-08-18 14:50:48'),(412,1,'admin','staff_deleted','Removed staff account for jdoe@gmail.com by admin user_id=1',NULL,'::1','2026-08-18 14:51:01'),(413,1,'admin','service_updated','Moved service_id=1 down',NULL,'::1','2026-08-18 15:13:42'),(414,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:09'),(415,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:13'),(416,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:17'),(417,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:19'),(418,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:21'),(419,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:22'),(420,1,'admin','service_updated','Moved service_id=8 up',NULL,'::1','2026-08-18 15:14:24'),(421,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-08-19 14:41:42'),(422,5,'client','login','User signed in',NULL,'::1','2026-08-19 14:41:42'),(423,5,'client','logout','User signed out',NULL,'::1','2026-08-19 14:58:30'),(424,8,'staff','login','User signed in',NULL,'::1','2026-08-19 14:58:35'),(425,8,'staff','logout','User signed out',NULL,'::1','2026-08-19 15:01:33'),(426,1,'admin','login','User signed in',NULL,'::1','2026-08-19 15:01:43'),(427,1,'admin','logout','User signed out',NULL,'::1','2026-08-20 11:30:41'),(428,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-08-20 11:47:38'),(429,5,'client','login','User signed in',NULL,'::1','2026-08-20 11:47:38'),(430,5,'client','ticket_created','Client joined queue',10,'::1','2026-08-20 11:49:42'),(431,8,'staff','login','User signed in',NULL,'::1','2026-08-20 11:50:22'),(432,8,'staff','logout','User signed out',NULL,'::1','2026-08-20 11:50:48'),(433,1,'admin','login','User signed in',NULL,'::1','2026-08-20 11:50:57'),(434,1,'admin','logout','User signed out',NULL,'::1','2026-08-20 12:07:17'),(435,5,'client','logout','User signed out',NULL,'::1','2026-08-21 08:15:04'),(436,1,'admin','login','User signed in',NULL,'::1','2026-08-21 08:15:12'),(437,1,'admin','logout','User signed out',NULL,'::1','2026-08-21 09:02:56'),(438,8,'staff','login','User signed in',NULL,'::1','2026-08-21 09:03:03'),(439,8,'staff','logout','User signed out',NULL,'::1','2026-08-21 10:33:30'),(440,5,'client','password_reset','Client reset password after OTP verification',NULL,'::1','2026-08-21 10:53:36'),(441,5,'client','login','User signed in',NULL,'::1','2026-08-21 10:53:36'),(442,5,'client','logout','User signed out',NULL,'::1','2026-08-21 10:54:08'),(443,1,'admin','login','User signed in',NULL,'::1','2026-08-21 13:00:07'),(444,1,'admin','logout','User signed out',NULL,'::1','2026-08-21 14:10:20'),(445,8,'staff','login','User signed in',NULL,'::1','2026-08-21 14:10:25'),(446,8,'staff','counter_claimed','Claimed Window 1',NULL,'::1','2026-08-21 14:10:31'),(447,8,'staff','counter_claimed','Claimed Window 2',NULL,'::1','2026-08-21 14:11:46'),(448,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-21 14:12:11'),(449,8,'staff','window_opened','Window set to open',NULL,'::1','2026-08-21 14:12:14'),(450,8,'staff','counter_claimed','Claimed Window 1',NULL,'::1','2026-08-21 14:12:20'),(451,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-21 14:13:05'),(452,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-08-21 14:13:06'),(453,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-21 14:13:07'),(454,8,'staff','window_opened','Window set to open',NULL,'::1','2026-08-21 14:13:08'),(455,8,'staff','window_opened','Window set to busy',NULL,'::1','2026-08-21 14:13:19'),(456,8,'staff','window_closed','Window set to closed',NULL,'::1','2026-08-21 14:13:20');
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_attempts`
--

LOCK TABLES `auth_attempts` WRITE;
/*!40000 ALTER TABLE `auth_attempts` DISABLE KEYS */;
INSERT INTO `auth_attempts` VALUES (4,'otp_resend','993d8776b4c6b5a7068811c5038d18204ca5f785644571e40b604bfd89005ec6','::1',1,'2026-07-28 08:52:33','2026-07-28 08:52:33',NULL),(7,'forgot_password','93bd0a67ad24749e0b97e1018ab70ae0b80dc0fbc79f8b533067880e0e33efad','::1',1,'2026-08-21 18:53:00','2026-08-21 18:53:00',NULL),(9,'otp_resend','230ea852904c34938ac36bf97b2c50fcebf5510bcdbe347262dab6ea4f528dcf','::1',5,'2026-07-08 19:40:33','2026-07-08 20:22:20','2026-07-08 21:22:20'),(10,'login','efc54d8912d43f916926073d7d0972bae01880e8ce4aee64139d71dceeb50351','::1',2,'2026-07-09 22:10:31','2026-07-09 22:10:39',NULL),(13,'login','94a51655e1982c703be0581431cbd911a0bcfcfd66d7b9236aca0c37bf256368','::1',1,'2026-07-13 19:23:03','2026-07-13 19:23:03',NULL),(14,'forgot_password','94a51655e1982c703be0581431cbd911a0bcfcfd66d7b9236aca0c37bf256368','::1',1,'2026-07-13 19:25:47','2026-07-13 19:25:47',NULL),(15,'login','eece1b45907362904af4b24a24809fa11bb50668bc41db58431cdb8eb21fb16d','::1',3,'2026-07-13 19:39:18','2026-07-13 19:39:49',NULL),(16,'login','910789803908d9ab8a57b28ff0af7ab81b9607356aef6f18d8499eb1489ed8ab','::1',1,'2026-07-27 23:55:28','2026-07-27 23:55:28',NULL),(22,'otp_verify','c3b3b5eecd0d6715a9c1143ad8ac7def3fe1c69897239229223705e181a2158c','::1',1,'2026-08-21 18:52:12','2026-08-21 18:52:12',NULL),(23,'login','93bd0a67ad24749e0b97e1018ab70ae0b80dc0fbc79f8b533067880e0e33efad','::1',1,'2026-08-21 18:52:54','2026-08-21 18:52:54',NULL);
/*!40000 ALTER TABLE `auth_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `counter_services`
--

DROP TABLE IF EXISTS `counter_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `counter_services` (
  `counter_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  PRIMARY KEY (`counter_id`,`service_id`),
  KEY `fk_counter_services_service` (`service_id`),
  CONSTRAINT `fk_counter_services_service` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_counter_services_window` FOREIGN KEY (`counter_id`) REFERENCES `service_windows` (`window_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counter_services`
--

LOCK TABLES `counter_services` WRITE;
/*!40000 ALTER TABLE `counter_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `counter_services` ENABLE KEYS */;
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
  KEY `idx_email_jobs_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_jobs`
--

LOCK TABLES `email_jobs` WRITE;
/*!40000 ALTER TABLE `email_jobs` DISABLE KEYS */;
INSERT INTO `email_jobs` VALUES (1,NULL,'test@example.com','Test','Message','otp','pending',0,NULL,'2026-07-02 20:13:06',NULL,'2026-07-02 12:13:06','2026-07-02 12:13:06'),(2,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 979634.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','cancelled',0,'Superseded by a newer email.','2026-07-02 20:15:07',NULL,'2026-07-02 12:15:07','2026-07-02 12:21:09'),(3,NULL,'test@example.com','Test','Message','otp','pending',0,NULL,'2026-07-02 20:15:40',NULL,'2026-07-02 12:15:40','2026-07-02 12:15:40'),(4,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 677668.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','cancelled',0,'Superseded by a newer email.','2026-07-02 20:21:09',NULL,'2026-07-02 12:21:09','2026-07-02 12:30:52'),(7,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 627584.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 20:30:52','2026-07-02 20:30:56','2026-07-02 12:30:52','2026-07-02 12:30:56'),(8,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 961763.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 20:31:41','2026-07-02 20:31:45','2026-07-02 12:31:41','2026-07-02 12:31:45'),(9,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 131451.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 20:45:47','2026-07-02 20:45:50','2026-07-02 12:45:47','2026-07-02 12:45:50'),(10,2,'mgyjasmin@gmail.com','Your SmartQMS verification code','Your SmartQMS password reset code is 770009.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:31:50','2026-07-02 21:31:53','2026-07-02 13:31:50','2026-07-02 13:31:53'),(11,2,'mgyjasmin@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 977330.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:32:46','2026-07-02 21:32:49','2026-07-02 13:32:46','2026-07-02 13:32:49'),(12,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS password reset code is 102994.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:42:52','2026-07-02 21:42:56','2026-07-02 13:42:52','2026-07-02 13:42:56'),(13,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS password reset code is 111208.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:47:22','2026-07-02 21:47:26','2026-07-02 13:47:22','2026-07-02 13:47:26'),(14,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 414452.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:48:06','2026-07-02 21:48:09','2026-07-02 13:48:06','2026-07-02 13:48:09'),(15,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 155030.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-02 21:56:45','2026-07-02 21:56:49','2026-07-02 13:56:45','2026-07-02 13:56:49'),(16,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 582771.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 16:33:39','2026-07-03 16:33:43','2026-07-03 08:33:39','2026-07-03 08:33:43'),(17,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 914571.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 17:47:56','2026-07-03 17:48:00','2026-07-03 09:47:56','2026-07-03 09:48:00'),(18,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 339555.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 17:49:09','2026-07-03 17:49:12','2026-07-03 09:49:09','2026-07-03 09:49:12'),(19,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 142528.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:13:39','2026-07-03 18:13:42','2026-07-03 10:13:39','2026-07-03 10:13:42'),(20,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 825492.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:14:34','2026-07-03 18:14:38','2026-07-03 10:14:34','2026-07-03 10:14:38'),(21,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 973254.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:16:35','2026-07-03 18:16:38','2026-07-03 10:16:35','2026-07-03 10:16:38'),(22,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 219861.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:21:50','2026-07-03 18:21:54','2026-07-03 10:21:50','2026-07-03 10:21:54'),(23,2,'mgyjasmin@gmail.com','Your SmartQMS verification code','Your SmartQMS password reset code is 759949.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:32:50','2026-07-03 18:32:54','2026-07-03 10:32:50','2026-07-03 10:32:54'),(24,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 645091.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-03 18:41:44','2026-07-03 18:41:48','2026-07-03 10:41:44','2026-07-03 10:41:48'),(25,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','Your SmartQMS login code is 538405.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.','otp','sent',1,NULL,'2026-07-06 15:14:03','2026-07-06 15:14:07','2026-07-06 07:14:03','2026-07-06 07:14:07'),(26,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:34:48','2026-07-06 16:34:51','2026-07-06 08:34:48','2026-07-06 08:34:51'),(27,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:35:28','2026-07-06 16:35:31','2026-07-06 08:35:28','2026-07-06 08:35:31'),(28,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:35:44','2026-07-06 16:35:47','2026-07-06 08:35:44','2026-07-06 08:35:47'),(29,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:36:06','2026-07-06 16:36:09','2026-07-06 08:36:06','2026-07-06 08:36:09'),(30,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:49:01','2026-07-06 16:49:05','2026-07-06 08:49:01','2026-07-06 08:49:05'),(31,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 16:52:04','2026-07-06 16:52:08','2026-07-06 08:52:04','2026-07-06 08:52:08'),(32,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 18:01:27','2026-07-06 18:01:33','2026-07-06 10:01:27','2026-07-06 10:01:33'),(33,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed.','2026-07-06 18:21:42',NULL,'2026-07-06 10:21:42','2026-07-06 10:21:43'),(34,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed.','2026-07-06 18:22:31',NULL,'2026-07-06 10:22:31','2026-07-06 10:22:33'),(35,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-06 18:26:18',NULL,'2026-07-06 10:26:18','2026-07-06 10:26:20'),(36,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-06 18:39:27',NULL,'2026-07-06 10:39:27','2026-07-06 10:39:29'),(37,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-06 18:50:19',NULL,'2026-07-06 10:50:19','2026-07-06 10:50:22'),(38,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-06 19:06:03',NULL,'2026-07-06 11:06:03','2026-07-06 11:06:06'),(39,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 19:40:18','2026-07-06 19:40:22','2026-07-06 11:40:18','2026-07-06 11:40:22'),(40,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 20:01:47','2026-07-06 20:01:52','2026-07-06 12:01:47','2026-07-06 12:01:52'),(41,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-06 20:03:00','2026-07-06 20:03:07','2026-07-06 12:03:00','2026-07-06 12:03:07'),(42,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-07 16:33:44','2026-07-07 16:33:48','2026-07-07 08:33:44','2026-07-07 08:33:48'),(43,2,'mgyjasmin@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-07 17:09:06','2026-07-07 17:09:10','2026-07-07 09:09:06','2026-07-07 09:09:10'),(44,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-08 19:35:55',NULL,'2026-07-08 11:35:55','2026-07-08 11:35:57'),(45,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-08 19:41:27',NULL,'2026-07-08 11:41:27','2026-07-08 11:41:28'),(46,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-08 20:22:20','2026-07-08 20:22:24','2026-07-08 12:22:20','2026-07-08 12:22:24'),(47,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-08 21:15:57','2026-07-08 21:16:01','2026-07-08 13:15:57','2026-07-08 13:16:01'),(48,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-08 21:20:34','2026-07-08 21:20:38','2026-07-08 13:20:34','2026-07-08 13:20:38'),(49,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-10 18:48:57','2026-07-10 18:49:02','2026-07-10 10:48:57','2026-07-10 10:49:02'),(50,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-10 22:27:02','2026-07-10 22:27:06','2026-07-10 14:27:02','2026-07-10 14:27:06'),(51,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-12 18:03:44','2026-07-12 18:03:48','2026-07-12 10:03:44','2026-07-12 10:03:48'),(52,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','cancelled',0,'Superseded by a newer email.','2026-07-12 18:48:52',NULL,'2026-07-12 10:48:52','2026-07-12 10:50:30'),(53,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','cancelled',0,'Superseded by a newer email.','2026-07-12 18:50:30',NULL,'2026-07-12 10:50:30','2026-07-12 11:32:21'),(54,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-12 19:32:21','2026-07-12 19:32:27','2026-07-12 11:32:21','2026-07-12 11:32:27'),(55,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-13 16:00:32','2026-07-13 16:00:36','2026-07-13 08:00:32','2026-07-13 08:00:36'),(56,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-13 17:00:28','2026-07-13 17:00:32','2026-07-13 09:00:28','2026-07-13 09:00:32'),(57,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-13 17:41:29','2026-07-13 17:41:32','2026-07-13 09:41:29','2026-07-13 09:41:32'),(58,2,'mgyjasmin@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-13 19:25:47','2026-07-13 19:25:50','2026-07-13 11:25:47','2026-07-13 11:25:50'),(59,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-21 19:50:59','2026-07-21 19:51:03','2026-07-21 11:50:59','2026-07-21 11:51:03'),(60,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-27 16:45:53','2026-07-27 16:45:58','2026-07-27 08:45:53','2026-07-27 08:45:58'),(61,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-27 17:22:11','2026-07-27 17:22:16','2026-07-27 09:22:11','2026-07-27 09:22:16'),(62,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-27 22:33:26','2026-07-27 22:33:31','2026-07-27 14:33:26','2026-07-27 14:33:31'),(63,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-27 23:53:47','2026-07-27 23:53:51','2026-07-27 15:53:47','2026-07-27 15:53:51'),(64,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-27 23:59:44','2026-07-27 23:59:48','2026-07-27 15:59:44','2026-07-27 15:59:48'),(65,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 00:10:52','2026-07-28 00:10:57','2026-07-27 16:10:52','2026-07-27 16:10:57'),(66,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 00:11:24','2026-07-28 00:11:28','2026-07-27 16:11:24','2026-07-27 16:11:28'),(67,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 00:50:22','2026-07-28 00:50:27','2026-07-27 16:50:22','2026-07-27 16:50:27'),(68,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 01:06:12','2026-07-28 01:06:17','2026-07-27 17:06:12','2026-07-27 17:06:17'),(69,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-07-28 08:47:29',NULL,'2026-07-28 00:47:29','2026-07-28 00:47:33'),(70,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 08:52:34','2026-07-28 08:52:42','2026-07-28 00:52:34','2026-07-28 00:52:42'),(71,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-07-28 10:28:46','2026-07-28 10:28:59','2026-07-28 02:28:46','2026-07-28 02:28:59'),(72,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-08-19 22:39:16','2026-08-19 22:39:21','2026-08-19 14:39:16','2026-08-19 14:39:21'),(73,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-08-20 19:47:02','2026-08-20 19:47:07','2026-08-20 11:47:02','2026-08-20 11:47:07'),(74,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-08-20 20:07:22','2026-08-20 20:07:26','2026-08-20 12:07:22','2026-08-20 12:07:26'),(75,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','failed',1,'Email send failed. Please check SMTP settings.','2026-08-21 18:51:08',NULL,'2026-08-21 10:51:08','2026-08-21 10:51:19'),(76,5,'jasminmglemmm@gmail.com','Your SmartQMS verification code','[redacted]','otp','sent',1,NULL,'2026-08-21 18:53:00','2026-08-21 18:53:04','2026-08-21 10:53:00','2026-08-21 10:53:04');
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
  `user_id` int(11) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
INSERT INTO `feedback` VALUES (1,3,5,2,2,5,'Test feedback','2026-07-27 16:07:22'),(2,6,5,2,2,4,'TEST FEEDBACK','2026-07-27 17:19:55'),(3,5,5,1,1,3,'TEST','2026-07-27 17:50:00'),(4,7,5,2,7,4,'TEST FEEDBACK','2026-07-28 00:58:14');
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
  `queue_mode` enum('central','specialized') NOT NULL DEFAULT 'central',
  `description` text DEFAULT NULL,
  `fallback_duration_mins` int(11) NOT NULL DEFAULT 15,
  `priority_only` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` tinyint(4) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `service_code` (`service_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `health_services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_services`
--

LOCK TABLES `health_services` WRITE;
/*!40000 ALTER TABLE `health_services` DISABLE KEYS */;
INSERT INTO `health_services` VALUES (1,'SVC-001','General Checkup / Consultation',1,'central',NULL,15,0,0,1,3,NULL,'2026-06-27 12:26:24'),(2,'SVC-002','Vaccination / Immunization',2,'central',NULL,15,0,0,1,2,NULL,'2026-06-27 12:26:24'),(3,'SVC-003','Prenatal / Maternal Care',3,'central',NULL,15,0,0,1,4,NULL,'2026-06-27 12:26:24'),(4,'SVC-004','Dental Services',4,'specialized',NULL,15,0,0,1,5,NULL,'2026-06-27 12:26:24'),(5,'SVC-005','Laboratory / Medical Certificate',5,'central',NULL,15,0,0,1,6,NULL,'2026-06-27 12:26:24'),(6,'SVC-006','Family Planning',6,'central',NULL,15,0,0,1,7,NULL,'2026-06-27 12:26:24'),(7,'SVC-007','Senior Citizen Services',7,'central',NULL,15,1,0,1,8,NULL,'2026-06-27 12:26:24'),(8,'SVC-008','PWD Assessment / Certification',8,'central',NULL,15,1,0,1,1,NULL,'2026-06-27 12:26:24');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ml_comparison_logs`
--

LOCK TABLES `ml_comparison_logs` WRITE;
/*!40000 ALTER TABLE `ml_comparison_logs` DISABLE KEYS */;
INSERT INTO `ml_comparison_logs` VALUES (1,'2026-07-21','Linear Regression',4.4016,5.6995,0.8675,69.23,0,'synthetic_queue_data.csv',5000,'2026-07-21 12:02:50'),(2,'2026-07-21','Decision Tree',2.1586,2.9148,0.9653,21.98,0,'synthetic_queue_data.csv',5000,'2026-07-21 12:02:50'),(3,'2026-07-21','Gradient Boosting',1.6933,2.1848,0.9805,18.98,0,'synthetic_queue_data.csv',5000,'2026-07-21 12:02:50'),(4,'2026-07-21','Random Forest',1.5799,2.0653,0.9826,17.17,1,'synthetic_queue_data.csv',5000,'2026-07-21 12:02:50');
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,5,'Your SmartQMS ticket A-001 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-12 15:14:43'),(2,1,2,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',0,'2026-07-12 15:16:12'),(3,2,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-12 15:18:03'),(4,3,5,'Your SmartQMS ticket A-001 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-12 15:20:18'),(5,4,2,'Your SmartQMS ticket A-001 is almost next. Only 1 ticket ahead.','turn_alert','both','sent',0,'2026-07-13 11:27:24'),(6,3,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-27 09:38:49'),(7,4,2,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',0,'2026-07-27 11:03:42'),(8,5,5,'Your SmartQMS ticket A-001 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-27 17:10:28'),(9,5,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-27 17:16:17'),(10,6,5,'Your SmartQMS ticket A-002 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-27 17:17:27'),(11,6,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-27 17:19:27'),(12,7,5,'Your SmartQMS ticket A-003 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-28 00:54:20'),(13,7,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-28 00:57:27'),(14,8,5,'Your SmartQMS ticket A-004 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-28 02:33:09'),(15,8,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-07-28 02:48:57'),(16,9,5,'Your SmartQMS ticket A-005 is next. Please proceed to the waiting area.','turn_alert','browser','sent',1,'2026-07-28 02:49:14'),(17,9,5,'Your service is complete. Please submit feedback when convenient.','feedback_prompt','browser','pending',1,'2026-08-17 06:23:00'),(18,10,5,'Your SmartQMS ticket A-001 is next. Please proceed to the waiting area.','turn_alert','both','sent',1,'2026-08-20 11:49:42');
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
  `user_id` int(11) DEFAULT NULL,
  `ticket_token` varchar(64) DEFAULT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `window_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `queue_mode` enum('central','specialized') NOT NULL DEFAULT 'central',
  `reference_number` varchar(20) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `ticket_number` varchar(10) NOT NULL,
  `entry_type` enum('walk-in','online') NOT NULL DEFAULT 'online',
  `client_type` enum('regular','senior','pwd') DEFAULT 'regular',
  `priority_level` tinyint(4) DEFAULT 0,
  `status` enum('waiting','serving','completed','voided','skipped') DEFAULT 'waiting',
  `lifecycle_status` enum('scheduled','waiting','calling','in-progress','completed','void') NOT NULL DEFAULT 'waiting',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `called_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  UNIQUE KEY `uq_queue_tickets_token` (`ticket_token`),
  KEY `service_id` (`service_id`),
  KEY `idx_queue_call_central` (`status`,`queue_mode`,`priority_level`,`issued_at`),
  KEY `idx_queue_call_specialized` (`status`,`queue_mode`,`service_id`,`priority_level`,`issued_at`),
  KEY `idx_queue_user_active` (`user_id`,`status`),
  KEY `idx_queue_public_token_status` (`ticket_token`,`lifecycle_status`),
  KEY `idx_queue_counter_lifecycle` (`window_id`,`lifecycle_status`,`called_at`),
  CONSTRAINT `queue_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `queue_tickets_ibfk_2` FOREIGN KEY (`window_id`) REFERENCES `service_windows` (`window_id`) ON DELETE SET NULL,
  CONSTRAINT `queue_tickets_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_tickets`
--

LOCK TABLES `queue_tickets` WRITE;
/*!40000 ALTER TABLE `queue_tickets` DISABLE KEYS */;
INSERT INTO `queue_tickets` VALUES (1,2,NULL,'Mark Glemwell Ysid Jasmin','09670413376',1,1,'central','BHC-2026-0001','assets/qr/BHC-2026-0001-1.svg','A-001','online','regular',0,'completed','completed','2026-06-27 14:22:26','2026-07-12 23:14:43','2026-07-12 23:14:43','2026-07-12 23:14:43','2026-07-12 23:16:12',NULL,NULL),(2,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',1,1,'central','BHC-2026-0002','assets/qr/BHC-2026-0002-2.svg','A-001','online','regular',0,'completed','completed','2026-07-06 11:40:54','2026-07-12 23:16:24','2026-07-12 23:16:24','2026-07-12 23:16:24','2026-07-12 23:18:03',NULL,NULL),(3,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',2,2,'central','BHC-2026-0003','assets/qr/BHC-2026-0003-3.svg','A-001','online','regular',0,'completed','completed','2026-07-12 15:20:15','2026-07-27 17:36:49','2026-07-27 17:36:49','2026-07-27 17:36:49','2026-07-27 17:38:49',NULL,NULL),(4,2,NULL,'Mark Glemwell Ysid Jasmin','09670413376',2,2,'central','BHC-2026-0004','assets/qr/BHC-2026-0004-4.svg','A-001','online','regular',0,'completed','completed','2026-07-13 11:27:19','2026-07-27 19:01:29','2026-07-27 19:01:29','2026-07-27 19:01:29','2026-07-27 19:03:42',NULL,NULL),(5,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',1,1,'central','BHC-2026-0005','assets/qr/BHC-2026-0005-5.svg','A-001','online','senior',1,'completed','completed','2026-07-27 17:10:28','2026-07-28 01:14:16','2026-07-28 01:14:16','2026-07-28 01:14:16','2026-07-28 01:16:17',NULL,NULL),(6,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',2,2,'central','BHC-2026-0006','assets/qr/BHC-2026-0006-6.svg','A-002','online','pwd',1,'completed','completed','2026-07-27 17:17:27','2026-07-28 01:17:52','2026-07-28 01:17:52','2026-07-28 01:17:52','2026-07-28 01:19:27',NULL,NULL),(7,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',2,7,'central','BHC-2026-0007','assets/qr/BHC-2026-0007-7.svg','A-003','online','senior',1,'completed','completed','2026-07-28 00:54:20','2026-07-28 08:56:25','2026-07-28 08:56:25','2026-07-28 08:56:25','2026-07-28 08:57:27',NULL,NULL),(8,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',2,2,'central','BHC-2026-0008','assets/qr/BHC-2026-0008-8.svg','A-004','online','regular',0,'completed','completed','2026-07-28 02:33:06','2026-07-28 10:48:51','2026-07-28 10:48:51','2026-07-28 10:48:51','2026-07-28 10:48:57',NULL,NULL),(9,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',2,2,'central','BHC-2026-0009','assets/qr/BHC-2026-0009-9.svg','A-005','online','senior',1,'completed','completed','2026-07-28 02:49:13','2026-08-17 14:21:49','2026-08-17 14:21:49','2026-08-17 14:21:49','2026-08-17 14:23:00',NULL,NULL),(10,5,NULL,'Mark Glemwell Ysid Jasmin','09252342342',NULL,1,'central','BHC-2026-0010','assets/qr/BHC-2026-0010-10.svg','A-001','online','regular',0,'waiting','waiting','2026-08-20 11:49:42',NULL,NULL,NULL,NULL,NULL,NULL);
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
  `counter_number` int(11) NOT NULL,
  `window_name` varchar(50) NOT NULL,
  `window_type` enum('shared','specialized') NOT NULL DEFAULT 'shared',
  `location_description` varchar(255) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `status` enum('open','busy','closed') DEFAULT 'closed',
  `priority_enabled` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`window_id`),
  UNIQUE KEY `uq_service_windows_counter_number` (`counter_number`),
  UNIQUE KEY `uq_window_runtime_staff` (`staff_id`),
  KEY `service_id` (`service_id`),
  KEY `idx_window_runtime` (`is_active`,`window_type`,`service_id`,`status`,`staff_id`),
  CONSTRAINT `service_windows_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`) ON DELETE SET NULL,
  CONSTRAINT `service_windows_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_windows`
--

LOCK TABLES `service_windows` WRITE;
/*!40000 ALTER TABLE `service_windows` DISABLE KEYS */;
INSERT INTO `service_windows` VALUES (1,1,'Window 1','shared',NULL,NULL,3,'closed',1,1,'2026-07-12 15:12:45'),(2,2,'Window 2','shared',NULL,NULL,NULL,'closed',1,1,'2026-07-13 11:38:30'),(3,3,'Window 3','shared',NULL,NULL,NULL,'closed',1,1,'2026-08-17 08:11:39');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
INSERT INTO `sms_logs` VALUES (1,2,'09670413376','Your SmartQMS verification code is 669375. It expires in 10 minutes.','otp','simulated',NULL,'2026-06-27 14:21:47'),(2,2,'09670413376','Your SmartQMS login code is 366049. It expires in 10 minutes.','otp','sent',NULL,'2026-06-30 09:49:58'),(3,2,'09670413376','Your SmartQMS login code is 150567. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:50:00'),(4,2,'09670413376','Your SmartQMS login code is 573898. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:50:02'),(5,2,'09670413376','Your SmartQMS login code is 792072. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:50:04'),(6,2,'09670413376','Your SmartQMS login code is 134126. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:50:06'),(7,2,'09670413376','Your SmartQMS login code is 846421. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:50:33'),(8,2,'09670413376','Your SmartQMS login code is 950721. It expires in 10 minutes.','otp','failed',NULL,'2026-06-30 09:51:13'),(9,2,'09670413376','Your SmartQMS password reset code is 712356. It expires in 10 minutes.','otp','sent',NULL,'2026-06-30 10:14:32'),(10,2,'09670413376','Your SmartQMS ticket A-001 is almost next. Only 1 ticket ahead.','notification','simulated',NULL,'2026-07-13 11:27:24'),(11,5,'09252342342','Your SmartQMS ticket A-001 is next. Please proceed to the waiting area.','notification','simulated',NULL,'2026-08-20 11:49:42');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` VALUES (1,6,NULL,NULL,1,'2026-07-12 15:12:01'),(3,8,NULL,NULL,1,'2026-07-27 09:25:18'),(4,9,NULL,NULL,1,'2026-07-27 17:13:04');
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_service_capabilities`
--

DROP TABLE IF EXISTS `staff_service_capabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_service_capabilities` (
  `capability_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`capability_id`),
  UNIQUE KEY `uq_staff_service_capability` (`staff_id`,`service_id`),
  KEY `idx_capability_service_active` (`service_id`,`is_active`,`staff_id`),
  KEY `fk_capability_admin` (`assigned_by`),
  CONSTRAINT `fk_capability_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_capability_service` FOREIGN KEY (`service_id`) REFERENCES `health_services` (`service_id`),
  CONSTRAINT `fk_capability_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_service_capabilities`
--

LOCK TABLES `staff_service_capabilities` WRITE;
/*!40000 ALTER TABLE `staff_service_capabilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_service_capabilities` ENABLE KEYS */;
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
INSERT INTO `system_settings` VALUES (1,'bhc_name','Barangay Health Center','Health Center Name','info',1,'2026-07-10 04:53:41'),(2,'bhc_address','','Health Center Address','info',1,'2026-07-10 04:53:41'),(3,'bhc_contact','','Contact Number','info',1,'2026-07-10 04:53:41'),(4,'bhc_barangay','','Barangay Name','info',1,'2026-07-10 04:53:41'),(5,'queue_open_time','07:00','Queue Opens At','queue',1,'2026-07-10 04:53:41'),(6,'queue_close_time','17:00','Queue Closes At','queue',1,'2026-07-10 04:53:41'),(7,'max_queue_per_day','100','Max Queue per Day','queue',1,'2026-07-10 04:53:41'),(8,'void_timeout_minutes','10','Void Timeout (minutes)','queue',1,'2026-07-10 04:53:41'),(9,'priority_queue_enabled','1','Priority Queue (Senior/PWD)','queue',1,'2026-07-10 04:53:42'),(10,'sms_enabled','0','Enable SMS Notifications','sms',1,'2026-07-10 04:53:42'),(11,'sms_api_key','','SMS API Key (Semaphore)','sms',1,'2026-07-10 04:53:42'),(12,'sms_sender_name','BHCQMS','SMS Sender Name','sms',1,'2026-07-10 04:53:42'),(13,'display_board_token','changeme_random_token','Display Board Access Token','display',1,'2026-07-10 04:53:41'),(14,'ml_last_trained','','Model Last Trained','ml',1,'2026-07-10 04:53:41'),(15,'ml_dataset_used','','Training Dataset Used','ml',1,'2026-07-10 04:53:41');
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
  `username` varchar(100) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `client_type` enum('regular','senior','pwd') NOT NULL DEFAULT 'regular',
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('client','staff','admin') DEFAULT 'client',
  `job_title` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone_number` (`phone_number`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@smartqms.local','System','Administrator',NULL,'09000000000','regular','admin@smartqms.local','$2y$10$lWDHsGY7Ww8iq6SYWc/vber.G0zd6G0Mam/kLgCnV.XJMjlSprNmm',0,'admin',NULL,1,NULL,NULL,NULL,1,'2026-08-21 21:00:07','2026-06-27 12:26:24'),(2,'mgyjasmin@gmail.com','Mark Glemwell Ysid','Jasmin','Pacinos','09670413376','regular','mgyjasmin@gmail.com','$2y$10$9xUcD3Jr0SgelvFVHpQzW.HpoeN5xtwsUW1p4KpGdwGr27HMmmJ4y',0,'client',NULL,1,NULL,NULL,NULL,1,'2026-07-13 19:26:38','2026-06-27 14:21:47'),(3,'markglemwellysidpjasmin@gmail.com','Mark Glemwell Ysid','Jasmin','Pacinos','09691505548','regular','markglemwellysidpjasmin@gmail.com','$2y$10$Imj.AMqvRa.i/tcMDTA5Mu/lAYbKT52wCSeQNWrrG51U.GlCduDBO',0,'client',NULL,0,'195775',NULL,'2026-06-30 22:15:19',1,NULL,'2026-06-30 10:54:25'),(5,'jasminmglemmm@gmail.com','Mark Glemwell Ysid','Jasmin',NULL,'09252342342','regular','jasminmglemmm@gmail.com','$2y$10$s1Jjyt/T/KtfN1hb7Ofxeee80GHapf.8oQQscQzYfpKqzMaj4kQgG',0,'client',NULL,1,NULL,NULL,NULL,1,'2026-08-21 18:53:36','2026-06-30 16:01:24'),(6,'jdoe@gmail.com','John','Doe',NULL,NULL,'regular','jdoe@gmail.com','$2y$10$IP0FWEubsAETwe21zEw34uusfVtwpGmLsb1K26AUeSuHEHEx4tbVu',0,'staff',NULL,1,NULL,NULL,NULL,0,'2026-07-13 19:21:12','2026-07-12 15:12:01'),(8,'sf2.yuki@gmail.com','Yuki','Shimizu',NULL,'09123456789','regular','sf2.yuki@gmail.com','$2y$10$rntrMkp3FfzQGo8jC.MireS.3CzYtsBfDN5Q2Yvg58qQ8Lf02tVXC',0,'staff',NULL,1,NULL,NULL,NULL,1,'2026-08-21 22:10:25','2026-07-27 09:25:18'),(9,'aduncan@gmail.com','Axel','Duncan',NULL,'09345764564','regular','aduncan@gmail.com','$2y$10$kOHSsWkjvIxKuJ61zArk1.6FFM0gdwFc0tbvNiIAmREVBTqnQF7Eq',0,'staff',NULL,1,NULL,NULL,NULL,1,'2026-07-28 01:13:49','2026-07-27 17:13:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wait_time_logs`
--

LOCK TABLES `wait_time_logs` WRITE;
/*!40000 ALTER TABLE `wait_time_logs` DISABLE KEYS */;
INSERT INTO `wait_time_logs` VALUES (1,1,1,0,22,6,1,0,1,5.00,2.00,9999.99,89,'Random Forest','2026-06-27 14:22:28'),(2,2,1,1,19,1,1,0,1,5.00,5.00,8857.00,99,'Random Forest','2026-07-06 11:40:56'),(3,3,3,0,23,0,2,0,1,5.00,2.00,9999.99,120,'Random Forest','2026-07-12 15:20:18'),(4,4,3,1,19,1,2,0,1,5.00,5.00,9999.99,133,'Random Forest','2026-07-13 11:27:24'),(5,5,4,0,1,2,2,1,1,1.57,2.00,5.00,121,'Random Forest','2026-07-27 17:10:28'),(6,6,3,0,1,2,2,2,1,2.11,2.00,2.00,95,'Random Forest','2026-07-27 17:17:27'),(7,7,3,0,8,2,7,1,1,5.00,2.00,2.08,62,'Fallback heuristic','2026-07-28 00:54:20'),(8,8,3,0,10,2,2,0,1,1.93,2.00,15.75,6,'Fallback heuristic','2026-07-28 02:33:09'),(9,9,3,0,10,2,2,1,1,1.48,2.00,9999.99,71,'Fallback heuristic','2026-07-28 02:49:14'),(10,10,NULL,0,19,4,1,0,0,1.47,2.00,NULL,NULL,'Fallback heuristic','2026-08-20 11:49:42');
/*!40000 ALTER TABLE `wait_time_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'smartqms'
--

--
-- Final view structure for view `v_ml_latest_comparison`
--

/*!50001 DROP VIEW IF EXISTS `v_ml_latest_comparison`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
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
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_staff_productivity_today` AS select `s`.`staff_id` AS `staff_id`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `staff_name`,`sw`.`window_name` AS `window_name`,count(`wl`.`log_id`) AS `tickets_served`,round(avg(`wl`.`actual_wait_min`),2) AS `avg_wait_min`,round(avg(`wl`.`actual_service_dur`) / 60,2) AS `avg_service_min` from ((((`staff` `s` join `users` `u` on(`s`.`user_id` = `u`.`user_id`)) left join `service_windows` `sw` on(`sw`.`staff_id` = `s`.`staff_id`)) left join `wait_time_logs` `wl` on(`wl`.`staff_id` = `s`.`staff_id`)) left join `queue_tickets` `qt` on(`wl`.`ticket_id` = `qt`.`ticket_id` and cast(`qt`.`completed_at` as date) = curdate())) group by `s`.`staff_id`,concat(`u`.`first_name`,' ',`u`.`last_name`),`sw`.`window_name` */;
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
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_today_queue` AS select `qt`.`ticket_id` AS `ticket_id`,`qt`.`reference_number` AS `reference_number`,`qt`.`ticket_number` AS `ticket_number`,coalesce(nullif(`qt`.`client_name`,''),concat_ws(' ',`u`.`first_name`,`u`.`last_name`),'Queue client') AS `client_name`,coalesce(nullif(`qt`.`phone_number`,''),`u`.`phone_number`) AS `phone_number`,`qt`.`client_type` AS `client_type`,`qt`.`priority_level` AS `priority_level`,`hs`.`service_name` AS `service_name`,`sw`.`window_name` AS `window_name`,`qt`.`status` AS `status`,`qt`.`issued_at` AS `issued_at`,`qt`.`called_at` AS `called_at`,`qt`.`completed_at` AS `completed_at`,timestampdiff(MINUTE,`qt`.`issued_at`,coalesce(`qt`.`completed_at`,current_timestamp())) AS `total_minutes_in_system` from (((`queue_tickets` `qt` left join `users` `u` on(`qt`.`user_id` = `u`.`user_id`)) join `health_services` `hs` on(`qt`.`service_id` = `hs`.`service_id`)) left join `service_windows` `sw` on(`qt`.`window_id` = `sw`.`window_id`)) where cast(`qt`.`issued_at` as date) = curdate() */;
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

-- Dump completed on 2026-08-21 23:18:57
