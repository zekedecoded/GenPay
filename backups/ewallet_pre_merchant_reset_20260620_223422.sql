-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: ewallet
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `archived_rejections`
--

DROP TABLE IF EXISTS `archived_rejections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `archived_rejections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `original_application_id` int(10) unsigned NOT NULL,
  `rejected_at_step` tinyint(3) unsigned NOT NULL,
  `business_name` varchar(120) NOT NULL,
  `proprietor_name` varchar(120) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `email` varchar(255) NOT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `business_permit` varchar(500) DEFAULT NULL,
  `sanitary_permit` varchar(500) DEFAULT NULL,
  `gjc_requirements` varchar(500) DEFAULT NULL,
  `clearance` varchar(500) DEFAULT NULL,
  `rejection_reason` text NOT NULL,
  `rejected_by` int(10) unsigned NOT NULL,
  `rejected_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reactivated` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ar_original` (`original_application_id`),
  KEY `idx_ar_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archived_rejections`
--

LOCK TABLES `archived_rejections` WRITE;
/*!40000 ALTER TABLE `archived_rejections` DISABLE KEYS */;
INSERT INTO `archived_rejections` VALUES (1,2,1,'Maria Snack Bar','Maria Garces','09612329812','noahgray430@gmail.com','uploads/stall_applications/2/profile_picture_17815094906145.jpg','uploads/stall_applications/2/business_permit_17815094903780.png','uploads/stall_applications/2/sanitary_permit_17815094906446.png','uploads/stall_applications/2/gjc_requirements_17815094903150.png','uploads/stall_applications/2/clearance_17815094902240.png','Rejected',16,'2026-06-15 21:07:59','2026-06-20 21:36:14',0);
/*!40000 ALTER TABLE `archived_rejections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cap_increase_log`
--

DROP TABLE IF EXISTS `cap_increase_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cap_increase_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `super_admin_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.id -- must be super-admin role',
  `old_cap` decimal(15,2) NOT NULL,
  `new_cap` decimal(15,2) NOT NULL,
  `amount_added` decimal(15,2) NOT NULL,
  `reason` text NOT NULL COMMENT 'Mandatory justification for audit compliance',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cap_increase_log`
--

LOCK TABLES `cap_increase_log` WRITE;
/*!40000 ALTER TABLE `cap_increase_log` DISABLE KEYS */;
INSERT INTO `cap_increase_log` VALUES (1,7,0.00,200000.00,200000.00,'Initial system capitalization. Starting circulation cap set to 200,000.00 for S.Y. 2025-2026.','2026-04-29 10:52:26');
/*!40000 ALTER TABLE `cap_increase_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course`
--

DROP TABLE IF EXISTS `course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course` (
  `courseID` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(255) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  PRIMARY KEY (`courseID`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course`
--

LOCK TABLES `course` WRITE;
/*!40000 ALTER TABLE `course` DISABLE KEYS */;
INSERT INTO `course` VALUES (1,'BSA','Bachelor of Science in Accountancy'),(2,'BSMA','Bachelor of Science in Management Accounting'),(3,'BSAIS','Bachelor of Science in Accounting Information Systems'),(4,'BSIA','Bachelor of Science in Internal Auditing'),(5,'BSBA-FM','BSBA major in Financial Management'),(6,'BSBA-MM','BSBA major in Marketing Management'),(7,'BSBA-HRM','BSBA major in Human Resource Management'),(8,'BSBA-OM','BSBA major in Operations Management'),(9,'BS-ENTREP','Bachelor of Science in Entrepreneurship'),(10,'BSOA','Bachelor of Science in Office Administration'),(11,'BSIT','Bachelor of Science in Information Technology'),(12,'BEED','Bachelor of Elementary Education'),(13,'BSED-ENG','Bachelor of Secondary Education major in English'),(14,'BSED-MATH','Bachelor of Secondary Education major in Mathematics'),(15,'BSED-SCI','Bachelor of Secondary Education major in Science'),(16,'BPED','Bachelor of Physical Education'),(17,'BSHM','Bachelor of Science in Hospitality Management');
/*!40000 ALTER TABLE `course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encashment_requests`
--

DROP TABLE IF EXISTS `encashment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `encashment_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `merchant_wallet_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` varchar(80) NOT NULL DEFAULT 'Cashier Release',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reference_no` varchar(40) DEFAULT NULL,
  `released_by` int(10) unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_encash_user` (`user_id`),
  KEY `idx_encash_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encashment_requests`
--

LOCK TABLES `encashment_requests` WRITE;
/*!40000 ALTER TABLE `encashment_requests` DISABLE KEYS */;
INSERT INTO `encashment_requests` VALUES (1,8,1,4250.00,'Cashier Release','released','TXN-20260513-63526',7,'2026-05-13 16:08:11',NULL,NULL,'2026-05-13 16:07:59');
/*!40000 ALTER TABLE `encashment_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imported_student_registry`
--

DROP TABLE IF EXISTS `imported_student_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `imported_student_registry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` varchar(14) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id_number` varchar(80) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `course_program` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(40) DEFAULT NULL,
  `import_status` enum('imported','duplicate','failed') NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `imported_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_import_batch` (`import_batch_id`),
  KEY `idx_import_student` (`student_id_number`),
  KEY `idx_import_email` (`email`),
  KEY `idx_import_status` (`import_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imported_student_registry`
--

LOCK TABLES `imported_student_registry` WRITE;
/*!40000 ALTER TABLE `imported_student_registry` DISABLE KEYS */;
/*!40000 ALTER TABLE `imported_student_registry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant`
--

DROP TABLE IF EXISTS `merchant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant` (
  `merchantID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `stall_name` varchar(255) NOT NULL,
  `stall_id` varchar(10) DEFAULT NULL COMMENT 'FK -> stalls.stall_id',
  `operational_status` enum('active','temporarily_closed','suspended','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`merchantID`),
  KEY `merchant.usersFK` (`userID`),
  KEY `fk_merchant_stall` (`stall_id`),
  CONSTRAINT `fk_merchant_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`stall_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `merchant.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant`
--

LOCK TABLES `merchant` WRITE;
/*!40000 ALTER TABLE `merchant` DISABLE KEYS */;
INSERT INTO `merchant` VALUES (1,8,'Aling Mirna\'s',NULL,'active','2026-06-15 14:34:42','2026-06-15 14:34:42',NULL),(2,9,'Shake Shake',NULL,'active','2026-06-15 14:34:42','2026-06-15 14:34:42',NULL),(3,13,'Ernesto\'s Cookery',NULL,'active','2026-06-15 14:34:42','2026-06-15 14:34:42',NULL),(4,17,'Lola Flora\'s School Supply','A1','active','2026-06-15 16:38:17','2026-06-15 16:38:17',NULL),(6,19,'KZ','A3','active','2026-06-15 23:14:01','2026-06-15 23:14:01',NULL),(8,21,'AlfaMark','A2','active','2026-06-16 00:36:55','2026-06-16 00:36:55',NULL),(11,26,'Itadori\'s','A4','active','2026-06-20 22:07:06','2026-06-20 22:07:06',NULL),(12,27,'Itadori\'s','B2','active','2026-06-20 22:21:23','2026-06-20 22:21:23',NULL);
/*!40000 ALTER TABLE `merchant` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_accounts`
--

DROP TABLE IF EXISTS `merchant_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL COMMENT 'FK -> stall_applications.id',
  `user_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (the new merchant account)',
  `temp_password_plain` varchar(100) DEFAULT NULL COMMENT 'Plaintext temp password - cleared after merchant changes password',
  `created_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (admin who executed Final Approval)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ma_application` (`application_id`),
  UNIQUE KEY `uq_ma_user` (`user_id`),
  KEY `idx_ma_created_by` (`created_by`),
  CONSTRAINT `fk_ma_application` FOREIGN KEY (`application_id`) REFERENCES `stall_applications` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit bridge: stall application -> users account created on Final Approval';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_accounts`
--

LOCK TABLES `merchant_accounts` WRITE;
/*!40000 ALTER TABLE `merchant_accounts` DISABLE KEYS */;
INSERT INTO `merchant_accounts` VALUES (1,1,17,'Ve9vMKqF',12,'2026-06-15 16:38:17'),(3,6,19,'px7qNPHQ6H',16,'2026-06-15 23:14:01'),(4,3,21,'uGEZcbdXEG',16,'2026-06-16 00:36:55'),(7,13,26,'hUGPH9KAeQ',12,'2026-06-20 22:07:06'),(8,14,27,'VZcFcnkxRZ',12,'2026-06-20 22:21:23');
/*!40000 ALTER TABLE `merchant_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_applications`
--

DROP TABLE IF EXISTS `merchant_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_name` varchar(120) NOT NULL,
  `owner_name` varchar(120) NOT NULL,
  `owner_email` varchar(255) NOT NULL,
  `owner_contact` varchar(20) NOT NULL,
  `stall_number` varchar(30) DEFAULT NULL,
  `product_types` text NOT NULL COMMENT 'Comma-separated list of products to be sold',
  `stage` enum('submitted','compliance_review','exec_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  `compliance_notes` text DEFAULT NULL,
  `exec_notes` text DEFAULT NULL,
  `compliance_by` int(10) unsigned DEFAULT NULL COMMENT 'Admin who did compliance review',
  `compliance_at` datetime DEFAULT NULL,
  `exec_by` int(10) unsigned DEFAULT NULL COMMENT 'Super Admin who did exec sign-off',
  `exec_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `generated_user_id` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID â€” set when account is auto-created on approval',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app_stage` (`stage`),
  KEY `idx_app_email` (`owner_email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Multi-stage merchant onboarding pipeline with compliance and exec sign-off';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_applications`
--

LOCK TABLES `merchant_applications` WRITE;
/*!40000 ALTER TABLE `merchant_applications` DISABLE KEYS */;
INSERT INTO `merchant_applications` VALUES (1,'Microsoft Corporation','Chienna Mae Gamboa','monicaemata118@gmail.com','09614708712',NULL,'Fries','submitted',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-10 00:04:39','2026-06-10 00:04:39');
/*!40000 ALTER TABLE `merchant_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_inventory`
--

DROP TABLE IF EXISTS `merchant_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_inventory` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_user_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (Merchant Admin)',
  `sku` varchar(50) DEFAULT NULL COMMENT 'Optional stock-keeping unit code',
  `product_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'general',
  `unit` varchar(30) NOT NULL DEFAULT 'piece' COMMENT 'piece, pack, bottle, kg, litre, etc.',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock_alert` int(11) NOT NULL DEFAULT 5 COMMENT 'Low-stock warning threshold',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_restricted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Set to 1 if matched against restricted_products',
  `restriction_note` varchar(255) DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID (admin who cleared item)',
  `restricted_by` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID (admin who restricted the item)',
  `restricted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inv_merchant` (`merchant_user_id`),
  KEY `idx_inv_category` (`category`),
  KEY `idx_inv_restricted` (`is_restricted`),
  KEY `idx_inv_available` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Per-merchant detailed product catalog with restriction checking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_inventory`
--

LOCK TABLES `merchant_inventory` WRITE;
/*!40000 ALTER TABLE `merchant_inventory` DISABLE KEYS */;
INSERT INTO `merchant_inventory` VALUES (1,13,'RM-001','Tapsilog','Tapa with itlog','food','serving',75.00,30,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 22:38:49','2026-06-09 22:38:49'),(2,13,'DRINK-001','Smart C','Calamansi Juice','beverage','bottle',46.00,21,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 22:39:46','2026-06-09 23:23:53'),(3,13,'SNCK-001','Piattos','Diamond shape snack','snack','pack',22.00,10,5,0,0,NULL,NULL,NULL,NULL,'2026-06-09 22:40:44','2026-06-09 23:48:55'),(4,13,'SD-001','Soda','Coca-Coal','beverage','bottle',15.00,19,5,0,1,'High sugar content â€” DepEd nutritional guidelines',NULL,NULL,NULL,'2026-06-09 23:52:24','2026-06-09 23:52:24'),(5,13,'RICE-MEAL-001','Chicken Rice Meal',NULL,'food','serving',65.00,40,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(6,13,'RICE-MEAL-002','Pork Adobo Rice Meal',NULL,'food','serving',70.00,35,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(7,13,'RICE-MEAL-003','Vegetable Rice Bowl',NULL,'food','serving',55.00,30,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(8,13,'SNACK-001','Cheese Sandwich',NULL,'snack','piece',35.00,45,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(9,13,'SNACK-002','Egg Sandwich',NULL,'snack','piece',38.00,45,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(10,13,'SNACK-003','Banana Cue',NULL,'snack','piece',20.00,50,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(11,13,'SNACK-004','Turon',NULL,'snack','piece',18.00,50,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(12,13,'BEV-001','Bottled Water',NULL,'beverage','bottle',15.00,100,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(13,13,'BEV-002','Fresh Buko Juice',NULL,'beverage','cup',30.00,35,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(14,13,'BEV-003','Calamansi Juice',NULL,'beverage','cup',25.00,40,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(15,13,'BEV-004','Iced Tea',NULL,'beverage','cup',28.00,40,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(16,13,'SUP-001','Ballpen',NULL,'supplies','piece',12.00,80,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(17,13,'SUP-002','Intermediate Pad',NULL,'supplies','pad',25.00,60,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(18,13,'SUP-003','Notebook',NULL,'supplies','piece',35.00,50,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(19,13,'SRV-001','Phone Charging',NULL,'service','session',10.00,999,5,1,0,NULL,NULL,NULL,NULL,'2026-06-09 23:55:42','2026-06-09 23:55:42'),(20,8,'RM-001','Tapsilog','Tapa with Egg','food','serving',75.00,47,5,1,0,NULL,NULL,NULL,NULL,'2026-06-15 22:05:20','2026-06-15 23:01:06');
/*!40000 ALTER TABLE `merchant_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_leases`
--

DROP TABLE IF EXISTS `merchant_leases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_leases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_user_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (Merchant Admin)',
  `stall_number` varchar(30) NOT NULL,
  `stall_id` varchar(10) DEFAULT NULL COMMENT 'FK -> stalls.stall_id - NULL for pre-registry leases',
  `stall_name` varchar(120) NOT NULL,
  `monthly_rent` decimal(15,2) NOT NULL,
  `deposit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `lease_start` date NOT NULL,
  `lease_end` date NOT NULL,
  `next_due_date` date NOT NULL,
  `status` enum('active','expired','terminated','pending') NOT NULL DEFAULT 'pending',
  `contract_notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (admin who created)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lease_merchant` (`merchant_user_id`),
  KEY `idx_lease_status` (`status`),
  KEY `idx_lease_due` (`next_due_date`),
  KEY `fk_lease_stall` (`stall_id`),
  CONSTRAINT `fk_lease_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`stall_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Institutional vendor stall lease contracts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_leases`
--

LOCK TABLES `merchant_leases` WRITE;
/*!40000 ALTER TABLE `merchant_leases` DISABLE KEYS */;
INSERT INTO `merchant_leases` VALUES (1,13,'STALL-001',NULL,'Eernesto\'s Cookery',10000.00,2.00,'2026-05-01','2026-06-10','2026-05-01','pending','',12,'2026-06-10 01:11:03','2026-06-10 01:13:50');
/*!40000 ALTER TABLE `merchant_leases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_qr_orders`
--

DROP TABLE IF EXISTS `merchant_qr_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_qr_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `merchant_user_id` int(10) unsigned NOT NULL,
  `merchant_wallet_id` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `items_json` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `paid_by` int(10) unsigned DEFAULT NULL,
  `paid_ref` varchar(40) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_mqo_token` (`token`),
  KEY `idx_mqo_status_expiry` (`status`,`expires_at`),
  KEY `idx_mqo_merchant` (`merchant_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_qr_orders`
--

LOCK TABLES `merchant_qr_orders` WRITE;
/*!40000 ALTER TABLE `merchant_qr_orders` DISABLE KEYS */;
INSERT INTO `merchant_qr_orders` VALUES (1,'68c19420876ee0aa5030ee0a0df3f3b2',8,1,'1x Tapsilog','[{\"id\":20,\"name\":\"Tapsilog\",\"qty\":1,\"price\":75}]',75.00,'pending','2026-06-15 23:08:24',NULL,NULL,NULL,'2026-06-15 22:53:24'),(2,'b3784baf02b053a6270352d2ac042aa5',8,1,'1x Tapsilog','[{\"id\":20,\"name\":\"Tapsilog\",\"qty\":1,\"price\":75}]',75.00,'pending','2026-06-15 23:10:12',NULL,NULL,NULL,'2026-06-15 22:55:12'),(3,'d00249d84dc002c8309b8870cd74c531',8,1,'3x Tapsilog','[{\"id\":20,\"name\":\"Tapsilog\",\"qty\":3,\"price\":75}]',225.00,'paid','2026-06-15 23:16:01',2,'POS-20260615-37E9DF','2026-06-15 23:01:06','2026-06-15 23:01:01');
/*!40000 ALTER TABLE `merchant_qr_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_rent_payments`
--

DROP TABLE IF EXISTS `merchant_rent_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_rent_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lease_id` int(10) unsigned NOT NULL COMMENT 'FK -> merchant_leases.id',
  `amount_paid` decimal(15,2) NOT NULL,
  `period_covered` varchar(20) NOT NULL COMMENT 'e.g. 2026-06',
  `payment_date` date NOT NULL,
  `payment_method` varchar(40) NOT NULL DEFAULT 'cash',
  `received_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (admin)',
  `reference_no` varchar(40) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_rent_lease` (`lease_id`),
  KEY `idx_rent_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail of rent payments by merchants';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_rent_payments`
--

LOCK TABLES `merchant_rent_payments` WRITE;
/*!40000 ALTER TABLE `merchant_rent_payments` DISABLE KEYS */;
INSERT INTO `merchant_rent_payments` VALUES (1,1,3000.00,'2026-05','2026-06-04','cash',12,'RENT-20260610-D475EF','Down','2026-06-10 01:12:17'),(2,1,3000.00,'2026-05','2026-06-04','cash',12,'RENT-20260610-A13D73','Down','2026-06-10 01:12:22'),(3,1,4000.00,'2026-06','2026-06-10','cash',12,'RENT-20260610-F4A6CA','fully paid','2026-06-10 01:13:27');
/*!40000 ALTER TABLE `merchant_rent_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchant_wallets`
--

DROP TABLE IF EXISTS `merchant_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_wallets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.id (merchant role)',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Collected points pending settlement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_wallets`
--

LOCK TABLES `merchant_wallets` WRITE;
/*!40000 ALTER TABLE `merchant_wallets` DISABLE KEYS */;
INSERT INTO `merchant_wallets` VALUES (1,8,225.00,'2026-05-13 00:00:00','2026-06-15 23:01:06'),(2,9,0.00,'2026-05-13 00:00:00','2026-05-13 00:00:00'),(3,13,0.00,'2026-06-09 22:35:54','2026-06-09 22:35:54'),(4,17,0.00,'2026-06-15 16:38:17','2026-06-15 16:38:17'),(6,19,0.00,'2026-06-15 23:14:01','2026-06-15 23:14:01'),(8,21,0.00,'2026-06-16 00:36:55','2026-06-16 00:36:55'),(11,26,0.00,'2026-06-20 22:07:06','2026-06-20 22:07:06'),(12,27,0.00,'2026-06-20 22:21:23','2026-06-20 22:21:23');
/*!40000 ALTER TABLE `merchant_wallets` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_guard_merchant_balance` BEFORE UPDATE ON `merchant_wallets` FOR EACH ROW BEGIN
    DECLARE v_total DECIMAL(15,2);
    DECLARE v_cap   DECIMAL(15,2);

    IF NEW.balance < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'NEGATIVE_BALANCE: merchant_wallets.balance cannot go below zero.';
    END IF;

    SELECT
        (SELECT cashier_vault_points FROM system_settings WHERE id = 1)
        + COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
        + (SELECT COALESCE(SUM(balance), 0) FROM merchant_wallets WHERE id != NEW.id)
        + NEW.balance
        + COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0)
    INTO v_total;

    SELECT total_circulation_cap INTO v_cap FROM system_settings WHERE id = 1;

    IF v_total > v_cap + 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_EXCEEDED: This merchant balance update would violate the circulation cap.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `p2p_transfers`
--

DROP TABLE IF EXISTS `p2p_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `p2p_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(40) NOT NULL,
  `from_wallet_id` int(10) unsigned NOT NULL COMMENT 'FK -> student_wallets.id',
  `to_wallet_id` int(10) unsigned NOT NULL COMMENT 'FK -> student_wallets.id',
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `status` enum('completed','failed','reversed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_p2p_from` (`from_wallet_id`),
  KEY `idx_p2p_to` (`to_wallet_id`),
  KEY `idx_p2p_from_user` (`from_user_id`),
  KEY `idx_p2p_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Peer-to-peer token transfers between students (atomic, transactionally safe)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `p2p_transfers`
--

LOCK TABLES `p2p_transfers` WRITE;
/*!40000 ALTER TABLE `p2p_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `p2p_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_verifications`
--

DROP TABLE IF EXISTS `payment_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_verifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL COMMENT 'FK -> stall_applications.id',
  `amount` decimal(10,2) NOT NULL DEFAULT 150.00 COMMENT 'Processing fee in PHP',
  `gcash_ref_number` varchar(60) NOT NULL COMMENT 'Admin-entered GCash reference number',
  `verified_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (admin who recorded the payment)',
  `verified_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pv_application` (`application_id`),
  KEY `idx_pv_verified_by` (`verified_by`),
  CONSTRAINT `fk_pv_application` FOREIGN KEY (`application_id`) REFERENCES `stall_applications` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Admin-recorded GCash payment proof for stall application processing fee';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_verifications`
--

LOCK TABLES `payment_verifications` WRITE;
/*!40000 ALTER TABLE `payment_verifications` DISABLE KEYS */;
INSERT INTO `payment_verifications` VALUES (1,1,150.00,'GCASH123456',12,'2026-06-15 16:38:10','Test payment notes','2026-06-15 16:38:10');
/*!40000 ALTER TABLE `payment_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_tokens`
--

DROP TABLE IF EXISTS `qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_tokens` (
  `qrID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `qr_data` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`qrID`),
  KEY `qr.usersFK` (`userID`),
  CONSTRAINT `qr.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_tokens`
--

LOCK TABLES `qr_tokens` WRITE;
/*!40000 ALTER TABLE `qr_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restricted_products`
--

DROP TABLE IF EXISTS `restricted_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restricted_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_name` varchar(120) NOT NULL COMMENT 'Exact or partial name to match against inventory',
  `category` varchar(60) NOT NULL DEFAULT 'general' COMMENT 'e.g. beverage, snack, junk_food',
  `reason` varchar(255) NOT NULL COMMENT 'Nutritional / health policy reason',
  `match_type` enum('exact','contains') NOT NULL DEFAULT 'contains' COMMENT 'exact=full name match, contains=substring match',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `flagged_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.userID (admin)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rp_name` (`product_name`),
  KEY `idx_rp_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Global nutritional compliance blacklist â€” blocks merchant inventory encoding';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restricted_products`
--

LOCK TABLES `restricted_products` WRITE;
/*!40000 ALTER TABLE `restricted_products` DISABLE KEYS */;
INSERT INTO `restricted_products` VALUES (1,'Coca-Cola','beverage','High sugar content â€” DepEd nutritional guidelines','contains',1,7,'2026-06-09 13:23:03','2026-06-09 23:53:12'),(2,'Energy Drink','beverage','High caffeine content â€” prohibited on campus','contains',1,7,'2026-06-09 13:23:03','2026-06-09 13:23:03'),(3,'Junk Food','snack','Low nutritional value â€” institutional health guidelines','contains',1,7,'2026-06-09 13:23:03','2026-06-09 13:23:03');
/*!40000 ALTER TABLE `restricted_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role` (
  `roleID` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) NOT NULL,
  PRIMARY KEY (`roleID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role`
--

LOCK TABLES `role` WRITE;
/*!40000 ALTER TABLE `role` DISABLE KEYS */;
INSERT INTO `role` VALUES (1,'student'),(2,'merchant'),(3,'finance'),(4,'finance'),(5,'merchant_admin'),(6,'merchant_staff');
/*!40000 ALTER TABLE `role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_revenue_ledger`
--

DROP TABLE IF EXISTS `school_revenue_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_revenue_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `topup_ref` varchar(40) NOT NULL COMMENT 'Reference from topup_requests',
  `user_id` int(10) unsigned NOT NULL COMMENT 'Student who topped up',
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 2.00,
  `gross_amount` decimal(15,2) NOT NULL COMMENT 'Cash paid by student',
  `net_credited` decimal(15,2) NOT NULL COMMENT 'Tokens credited after fee',
  `credited_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rev_user` (`user_id`),
  KEY `idx_rev_date` (`credited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks service fee revenue per automated top-up';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_revenue_ledger`
--

LOCK TABLES `school_revenue_ledger` WRITE;
/*!40000 ALTER TABLE `school_revenue_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `school_revenue_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stall_applications`
--

DROP TABLE IF EXISTS `stall_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stall_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `stall_id` varchar(10) DEFAULT NULL,
  `business_name` varchar(120) NOT NULL,
  `proprietor_name` varchar(120) NOT NULL,
  `contact_number` varchar(15) NOT NULL COMMENT '09XXXXXXXXX format',
  `email` varchar(255) NOT NULL,
  `profile_picture` varchar(500) NOT NULL COMMENT 'Relative path to upload',
  `business_permit` varchar(500) NOT NULL COMMENT 'Relative path to upload',
  `sanitary_permit` varchar(500) NOT NULL COMMENT 'Relative path to upload',
  `gjc_requirements` varchar(500) NOT NULL COMMENT 'Relative path to upload',
  `clearance` varchar(500) NOT NULL COMMENT 'Relative path to upload',
  `terms_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('review','meeting','down_payment','approval','active','expired') NOT NULL DEFAULT 'review',
  `current_step` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `reviewed_by` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID',
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `contract_ref` varchar(40) DEFAULT NULL COMMENT 'Auto-generated: SA-{zero-padded-id}-{year}',
  `signed_at` datetime DEFAULT NULL COMMENT 'Set when admin confirms contract in Step 2.2',
  `initially_approved_by` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID (admin who clicked Initial Approval)',
  `initially_approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `meetup_scheduled_at` datetime DEFAULT NULL,
  `meetup_location` varchar(255) DEFAULT NULL,
  `meetup_notes` text DEFAULT NULL,
  `meetup_scheduled_by` int(10) unsigned DEFAULT NULL,
  `meetup_scheduled_email_sent_at` datetime DEFAULT NULL,
  `down_payment_amount` decimal(10,2) DEFAULT NULL,
  `down_payment_reference` varchar(80) DEFAULT NULL,
  `down_payment_notes` text DEFAULT NULL,
  `down_payment_recorded_by` int(10) unsigned DEFAULT NULL,
  `down_payment_recorded_at` datetime DEFAULT NULL,
  `merchant_user_id` int(10) unsigned DEFAULT NULL,
  `temp_password_plain` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sa_stall` (`stall_id`),
  KEY `idx_sa_status` (`status`),
  KEY `idx_sa_email` (`email`),
  KEY `idx_sa_created` (`created_at`),
  CONSTRAINT `fk_stallapps_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`stall_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Public stall applications with file paths';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stall_applications`
--

LOCK TABLES `stall_applications` WRITE;
/*!40000 ALTER TABLE `stall_applications` DISABLE KEYS */;
INSERT INTO `stall_applications` VALUES (1,'A1','Lola Flora\'s School Supply','Cardo Dalisay','09614708398','ezekielclarence06@gmail.com','uploads/stall_applications/1/profile_picture_17815066751467.png','uploads/stall_applications/1/business_permit_17815066756912.png','uploads/stall_applications/1/sanitary_permit_17815066755424.png','uploads/stall_applications/1/gjc_requirements_17815066757159.png','uploads/stall_applications/1/clearance_17815066757697.png',1,'active',4,12,'2026-06-15 16:38:17',NULL,'SA-00001-2026','2026-06-15 16:38:10',12,'2026-06-15 16:37:56','2026-06-15 14:57:55','2026-06-20 21:36:14',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,'A2','AlfaMark','Mark Reyes','09614709203','ezekielclarencesantiago68@gmail.com','uploads/stall_applications/3/profile_picture_17815291266389.jpg','uploads/stall_applications/3/business_permit_17815291269031.jpg','uploads/stall_applications/3/sanitary_permit_17815291268926.jpg','uploads/stall_applications/3/gjc_requirements_17815291263810.png','uploads/stall_applications/3/clearance_17815291268794.jpg',1,'active',4,16,'2026-06-16 00:36:55',NULL,NULL,NULL,NULL,NULL,'2026-06-15 21:12:06','2026-06-20 21:36:14','2026-06-19 00:37:00','GJC','Monica appointment',16,'2026-06-16 00:35:48',500.00,'GCASH123123412','Okay na',16,'2026-06-16 00:36:36',21,'uGEZcbdXEG'),(6,'A3','KZ','Makima','09614708398','virgelopez611@gmail.com','uploads/stall_applications/6/profile_picture_17815361511127.png','uploads/stall_applications/6/business_permit_17815361512090.png','uploads/stall_applications/6/sanitary_permit_17815361517359.png','uploads/stall_applications/6/gjc_requirements_17815361519118.png','uploads/stall_applications/6/clearance_17815361514782.png',1,'active',4,16,'2026-06-15 23:14:01',NULL,NULL,NULL,NULL,NULL,'2026-06-15 23:09:11','2026-06-20 21:36:14','2026-06-16 10:09:00','GJC','SAbihin mo sa card pasok lang ako',16,'2026-06-15 23:10:52',500.00,'GCASH1231234124','DOwn and dip',16,'2026-06-15 23:12:11',19,'px7qNPHQ6H'),(12,NULL,'White Chicks','Terry Crews','09614708398','ezekielclarence06@gmail.com','uploads/stall_applications/12/profile_picture_17819638929869.png','uploads/stall_applications/12/business_permit_17819638922681.png','uploads/stall_applications/12/sanitary_permit_17819638924277.pdf','uploads/stall_applications/12/gjc_requirements_17819638924633.jpg','uploads/stall_applications/12/clearance_17819638929694.jpg',1,'approval',4,12,'2026-06-20 21:59:10',NULL,NULL,NULL,NULL,NULL,'2026-06-20 21:58:12','2026-06-20 22:00:26','2026-06-08 10:59:00','GJC','Walk-in',12,'2026-06-20 21:59:38',100.00,'HuH?','thanks',12,'2026-06-20 22:00:26',NULL,NULL),(13,'A4','Itadori\'s','Yuji Itadori','09614708712','ezekielclarence6@gmail.com','uploads/stall_applications/13/profile_picture_17819643648500.png','uploads/stall_applications/13/business_permit_17819643641393.png','uploads/stall_applications/13/sanitary_permit_17819643644290.pdf','uploads/stall_applications/13/gjc_requirements_17819643649223.jpg','uploads/stall_applications/13/clearance_17819643642851.jpg',1,'active',4,12,'2026-06-20 22:07:06',NULL,NULL,NULL,NULL,NULL,'2026-06-20 22:06:04','2026-06-20 22:07:06','2026-06-21 11:06:00','GJC',NULL,12,'2026-06-20 22:06:39',100.00,'LOL',NULL,12,'2026-06-20 22:07:03',26,'hUGPH9KAeQ'),(14,'B2','Itadori\'s','Yuji Itadori','09614708712','kujo7397@gmail.com','uploads/stall_applications/14/profile_picture_17819650579222.png','uploads/stall_applications/14/business_permit_17819650579049.pdf','uploads/stall_applications/14/sanitary_permit_17819650571561.jpg','uploads/stall_applications/14/gjc_requirements_17819650575937.pdf','uploads/stall_applications/14/clearance_17819650579306.jpg',1,'active',4,12,'2026-06-20 22:21:23',NULL,NULL,NULL,NULL,NULL,'2026-06-20 22:17:37','2026-06-20 22:21:23','2026-06-14 10:19:00','GJC',NULL,12,'2026-06-20 22:18:36',1000.00,'Nothing',NULL,12,'2026-06-20 22:21:17',27,'VZcFcnkxRZ');
/*!40000 ALTER TABLE `stall_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stalls`
--

DROP TABLE IF EXISTS `stalls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stalls` (
  `stall_id` varchar(10) NOT NULL COMMENT 'Alphanumeric e.g. A1, B3',
  `label` varchar(60) NOT NULL COMMENT 'Display label e.g. Stall A1',
  `row_label` char(1) NOT NULL COMMENT 'Grid row: A or B',
  `col_number` tinyint(3) unsigned NOT NULL COMMENT 'Grid column: 1-5',
  `area_sqm` decimal(6,2) DEFAULT NULL,
  `monthly_rate` decimal(15,2) DEFAULT NULL,
  `status` enum('vacant','occupied','pending_application') NOT NULL DEFAULT 'vacant',
  `merchant_id` int(11) DEFAULT NULL COMMENT 'FK -> merchant.merchantID',
  `pending_expires_at` datetime DEFAULT NULL COMMENT 'NOW()+15min when pending_application',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`stall_id`),
  KEY `idx_stall_status` (`status`),
  KEY `idx_stall_merchant` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Physical stall registry - source of truth for the public stall map';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stalls`
--

LOCK TABLES `stalls` WRITE;
/*!40000 ALTER TABLE `stalls` DISABLE KEYS */;
INSERT INTO `stalls` VALUES ('A1','Stall A1','A',1,12.00,2500.00,'occupied',4,NULL,'2026-06-15 14:34:42','2026-06-15 16:38:17'),('A2','Stall A2','A',2,12.00,2500.00,'occupied',8,NULL,'2026-06-15 14:34:42','2026-06-16 00:36:55'),('A3','Stall A3','A',3,12.00,2500.00,'occupied',6,NULL,'2026-06-15 14:34:42','2026-06-15 23:14:01'),('A4','Stall A4','A',4,12.00,2500.00,'occupied',11,NULL,'2026-06-15 14:34:42','2026-06-20 22:07:06'),('A5','Stall A5','A',5,12.00,2500.00,'vacant',NULL,NULL,'2026-06-15 14:34:42','2026-06-15 14:34:42'),('B1','Stall B1','B',1,12.00,2500.00,'vacant',NULL,NULL,'2026-06-15 14:34:42','2026-06-15 14:34:42'),('B2','Stall B2','B',2,12.00,2500.00,'occupied',12,NULL,'2026-06-15 14:34:42','2026-06-20 22:21:23'),('B3','Stall B3','B',3,12.00,2500.00,'vacant',NULL,NULL,'2026-06-15 14:34:42','2026-06-20 22:27:38'),('B4','Stall B4','B',4,12.00,2500.00,'vacant',NULL,NULL,'2026-06-15 14:34:42','2026-06-15 14:34:42'),('B5','Stall B5','B',5,12.00,2500.00,'vacant',NULL,NULL,'2026-06-15 14:34:42','2026-06-15 14:34:42');
/*!40000 ALTER TABLE `stalls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_info`
--

DROP TABLE IF EXISTS `student_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_info` (
  `stud_infoID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `studentID` varchar(255) NOT NULL,
  `yr_lvl` varchar(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  PRIMARY KEY (`stud_infoID`),
  KEY `student.courseFK` (`courseID`),
  KEY `student.usersFK` (`userID`),
  CONSTRAINT `student.courseFK` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`),
  CONSTRAINT `student.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_info`
--

LOCK TABLES `student_info` WRITE;
/*!40000 ALTER TABLE `student_info` DISABLE KEYS */;
INSERT INTO `student_info` VALUES (1,23,'GJC-STUDENT-001','1',1);
/*!40000 ALTER TABLE `student_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_wallets`
--

DROP TABLE IF EXISTS `student_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_wallets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'FK -> users.id (student role)',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Current spendable balance in PHP points',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_wallets`
--

LOCK TABLES `student_wallets` WRITE;
/*!40000 ALTER TABLE `student_wallets` DISABLE KEYS */;
INSERT INTO `student_wallets` VALUES (1,2,25.00,'2026-05-13 00:00:00','2026-06-15 23:01:06'),(2,1,2500.00,'2026-05-13 00:00:00','2026-05-13 17:44:54'),(3,10,0.00,'2026-06-09 21:32:05','2026-06-09 21:32:05'),(4,23,0.00,'2026-06-16 01:10:47','2026-06-16 01:10:47');
/*!40000 ALTER TABLE `student_wallets` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_guard_student_balance` BEFORE UPDATE ON `student_wallets` FOR EACH ROW BEGIN
    DECLARE v_total DECIMAL(15,2);
    DECLARE v_cap   DECIMAL(15,2);

    IF NEW.balance < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'NEGATIVE_BALANCE: student_wallets.balance cannot go below zero.';
    END IF;

    SELECT
        (SELECT cashier_vault_points FROM system_settings WHERE id = 1)
        + (SELECT COALESCE(SUM(balance), 0) FROM student_wallets WHERE id != NEW.id)
        + NEW.balance
        + COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
        + COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0)
    INTO v_total;

    SELECT total_circulation_cap INTO v_cap FROM system_settings WHERE id = 1;

    IF v_total > v_cap + 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_EXCEEDED: This student balance update would violate the circulation cap.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `total_circulation_cap` decimal(15,2) NOT NULL DEFAULT 200000.00 COMMENT 'Total money supply cap -- super-admin only',
  `cashier_vault_points` decimal(15,2) NOT NULL DEFAULT 200000.00 COMMENT 'Unsold points sitting in the cashiers vault',
  `last_cap_increased_by` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.id of the super-admin who last raised the cap',
  `last_cap_increased_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `token_rate` decimal(10,4) NOT NULL DEFAULT 0.1000 COMMENT '1 PHP = 0.1 Tokens (â‚±10 per token). Cosmetic display only.',
  `service_fee` decimal(10,2) NOT NULL DEFAULT 2.00 COMMENT 'Fee deducted from credited amount on each top-up (â‚±2)',
  `school_revenue_balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Cumulative service fee revenue collected by the school',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,200000.00,195850.00,7,'2026-04-29 10:52:26','2026-06-08 19:16:19',0.1000,2.00,0.00);
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_guard_vault_update` BEFORE UPDATE ON `system_settings` FOR EACH ROW BEGIN
    IF NEW.cashier_vault_points > NEW.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VAULT_EXCEEDS_CAP: cashier_vault_points cannot exceed total_circulation_cap';
    END IF;

    IF NEW.total_circulation_cap < OLD.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_DECREASE_FORBIDDEN: total_circulation_cap can only be increased';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `systemic_audit_trail`
--

DROP TABLE IF EXISTS `systemic_audit_trail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `systemic_audit_trail` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_role` enum('GJC Admin','Student','Merchant','Vendor/Staff') NOT NULL,
  `action_type` enum('LOGIN','LOGOUT','PASSWORD_CHANGE','TRANSACTION','MENU_MUTATION','STALL_UPDATE','USER_IMPORT','MERCHANT_CREATE') NOT NULL,
  `stall_id` varchar(20) DEFAULT NULL,
  `affected_table` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_timestamp` (`timestamp`),
  KEY `idx_audit_role_action` (`user_role`,`action_type`),
  KEY `fk_systemic_audit_user` (`user_id`),
  CONSTRAINT `fk_systemic_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemic_audit_trail`
--

LOCK TABLES `systemic_audit_trail` WRITE;
/*!40000 ALTER TABLE `systemic_audit_trail` DISABLE KEYS */;
INSERT INTO `systemic_audit_trail` VALUES (1,16,'GJC Admin','LOGIN',NULL,'users',NULL,'{\"userID\":16,\"email\":\"finance@gjc.edu.ph\",\"roleID\":4,\"sub_role\":\"super_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 14:01:08'),(2,16,'GJC Admin','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 14:01:43'),(3,8,'Merchant','LOGIN',NULL,'users',NULL,'{\"userID\":8,\"email\":\"carlos.villanueva@email.com\",\"roleID\":2,\"sub_role\":\"merchant_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 14:02:39'),(4,8,'Merchant','MENU_MUTATION',NULL,'menu_items',NULL,'{\"id\":20,\"merchant_user_id\":8,\"sku\":\"RM-001\",\"product_name\":\"Tapsilog\",\"description\":\"Tapa with Egg\",\"category\":\"food\",\"unit\":\"serving\",\"price\":75,\"stock_qty\":50,\"min_stock_alert\":5,\"is_available\":1,\"is_restricted\":0,\"restriction_note\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 14:05:20'),(5,2,'Student','LOGIN',NULL,'users',NULL,'{\"userID\":2,\"email\":\"otto.cruz@email.com\",\"roleID\":1,\"sub_role\":\"student\"}','::1','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36','2026-06-15 14:07:46'),(6,2,'Student','LOGIN',NULL,'users',NULL,'{\"userID\":2,\"email\":\"otto.cruz@email.com\",\"roleID\":1,\"sub_role\":\"student\"}','::1','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36','2026-06-15 14:53:18'),(7,2,'Student','TRANSACTION',NULL,'e_wallet_transactions',NULL,'{\"reference_no\":\"POS-20260615-37E9DF\",\"transaction_type\":\"payment\",\"amount\":225,\"student_wallet_id\":1,\"merchant_wallet_id\":1,\"items\":[{\"id\":20,\"name\":\"Tapsilog\",\"qty\":3,\"price\":75}],\"status\":\"completed\"}','::1','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36','2026-06-15 15:01:06'),(8,8,'Merchant','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 15:08:17'),(9,16,'GJC Admin','STALL_UPDATE','A3','stalls','{\"stall_id\":\"A3\",\"label\":\"Stall A3\",\"row_label\":\"A\",\"col_number\":3,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"pending_application\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 23:09:11\"}','{\"stall_id\":\"A3\",\"label\":\"Stall A3\",\"row_label\":\"A\",\"col_number\":3,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":6,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 23:09:11\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 15:14:01'),(10,19,'Merchant','LOGIN',NULL,'users',NULL,'{\"userID\":19,\"email\":\"virgelopez611@gmail.com\",\"roleID\":2,\"sub_role\":\"merchant_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 15:14:51'),(11,19,'Merchant','PASSWORD_CHANGE',NULL,'users','{\"force_password_change\":1,\"is_first_login\":1,\"password_changed\":0}','{\"force_password_change\":0,\"is_first_login\":0,\"password_changed\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 15:15:56'),(12,19,'Merchant','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 16:34:48'),(13,16,'GJC Admin','STALL_UPDATE','A2','stalls','{\"stall_id\":\"A2\",\"label\":\"Stall A2\",\"row_label\":\"A\",\"col_number\":2,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"pending_application\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 21:12:06\"}','{\"stall_id\":\"A2\",\"label\":\"Stall A2\",\"row_label\":\"A\",\"col_number\":2,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":8,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 21:12:06\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 16:36:55'),(14,21,'Merchant','LOGIN',NULL,'users',NULL,'{\"userID\":21,\"email\":\"ezekielclarencesantiago68@gmail.com\",\"roleID\":2,\"sub_role\":\"merchant_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 16:37:38'),(15,21,'Merchant','PASSWORD_CHANGE',NULL,'users','{\"force_password_change\":1,\"is_first_login\":1,\"password_changed\":0}','{\"force_password_change\":0,\"is_first_login\":0,\"password_changed\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 16:38:07'),(16,21,'Merchant','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:07:50'),(17,22,'Merchant','LOGIN',NULL,'users',NULL,'{\"userID\":22,\"email\":\"noahgray430@gmail.com\",\"roleID\":6,\"sub_role\":\"merchant_staff\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:08:03'),(18,22,'Merchant','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:08:45'),(19,16,'GJC Admin','LOGIN',NULL,'users',NULL,'{\"userID\":16,\"email\":\"finance@gjc.edu.ph\",\"roleID\":4,\"sub_role\":\"super_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:39:02'),(20,16,'GJC Admin','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:39:18'),(21,23,'Student','LOGIN',NULL,'users',NULL,'{\"userID\":23,\"email\":\"student@gjc.edu.ph\",\"roleID\":1,\"sub_role\":\"student\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:39:36'),(22,23,'Student','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-15 17:40:59'),(23,7,'GJC Admin','STALL_UPDATE','A4','stalls','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"vacant\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-16 01:41:03\"}','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":9,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-16 01:41:03\"}','0.0.0.0','Unknown','2026-06-20 13:37:43'),(24,7,'GJC Admin','STALL_UPDATE','A4','stalls','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"vacant\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-20 21:38:38\"}','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":10,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-20 21:38:38\"}','0.0.0.0','Unknown','2026-06-20 13:47:42'),(25,12,'GJC Admin','LOGIN',NULL,'users',NULL,'{\"userID\":12,\"email\":\"superadmin@gjc.edu.ph\",\"roleID\":4,\"sub_role\":\"super_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 13:48:27'),(26,12,'GJC Admin','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 13:48:58'),(27,12,'GJC Admin','LOGIN',NULL,'users',NULL,'{\"userID\":12,\"email\":\"superadmin@gjc.edu.ph\",\"roleID\":4,\"sub_role\":\"super_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 13:50:51'),(28,12,'GJC Admin','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 13:56:59'),(29,12,'GJC Admin','LOGIN',NULL,'users',NULL,'{\"userID\":12,\"email\":\"superadmin@gjc.edu.ph\",\"roleID\":4,\"sub_role\":\"super_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 13:58:37'),(30,12,'GJC Admin','STALL_UPDATE','A4','stalls','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"vacant\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-20 21:48:58\"}','{\"stall_id\":\"A4\",\"label\":\"Stall A4\",\"row_label\":\"A\",\"col_number\":4,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":11,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-20 21:48:58\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 14:07:06'),(31,12,'GJC Admin','STALL_UPDATE','B2','stalls','{\"stall_id\":\"B2\",\"label\":\"Stall B2\",\"row_label\":\"B\",\"col_number\":2,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"vacant\",\"merchant_id\":null,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 14:34:42\"}','{\"stall_id\":\"B2\",\"label\":\"Stall B2\",\"row_label\":\"B\",\"col_number\":2,\"area_sqm\":\"12.00\",\"monthly_rate\":\"2500.00\",\"status\":\"occupied\",\"merchant_id\":12,\"pending_expires_at\":null,\"created_at\":\"2026-06-15 14:34:42\",\"updated_at\":\"2026-06-15 14:34:42\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 14:21:24'),(32,27,'Merchant','LOGIN',NULL,'users',NULL,'{\"userID\":27,\"email\":\"kujo7397@gmail.com\",\"roleID\":2,\"sub_role\":\"merchant_admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 14:22:07'),(33,27,'Merchant','PASSWORD_CHANGE',NULL,'users','{\"force_password_change\":1,\"is_first_login\":1,\"password_changed\":0}','{\"force_password_change\":0,\"is_first_login\":0,\"password_changed\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 14:22:22'),(34,27,'Merchant','LOGOUT',NULL,'users','{\"session\":\"active\"}','{\"session\":\"destroyed\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-20 14:28:29');
/*!40000 ALTER TABLE `systemic_audit_trail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topup`
--

DROP TABLE IF EXISTS `topup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topup` (
  `topupID` int(11) NOT NULL AUTO_INCREMENT,
  `adminID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `date_time` datetime NOT NULL,
  PRIMARY KEY (`topupID`),
  KEY `adminFK` (`adminID`),
  KEY `toptup.usersFK` (`userID`),
  CONSTRAINT `adminFK` FOREIGN KEY (`adminID`) REFERENCES `users` (`userID`),
  CONSTRAINT `toptup.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topup`
--

LOCK TABLES `topup` WRITE;
/*!40000 ALTER TABLE `topup` DISABLE KEYS */;
/*!40000 ALTER TABLE `topup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topup_requests`
--

DROP TABLE IF EXISTS `topup_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topup_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `student_wallet_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(80) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reference_no` varchar(40) DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_topup_user` (`user_id`),
  KEY `idx_topup_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topup_requests`
--

LOCK TABLES `topup_requests` WRITE;
/*!40000 ALTER TABLE `topup_requests` DISABLE KEYS */;
INSERT INTO `topup_requests` VALUES (1,2,1,1000.00,'Cash at Cashier','approved','TXN-20260513-04199',7,'2026-05-13 16:01:50',NULL,NULL,'2026-05-13 16:01:32'),(2,1,2,2000.00,'Cash at Cashier','approved','TXN-20260513-02871',7,'2026-05-13 16:04:48',NULL,NULL,'2026-05-13 16:02:38'),(3,1,2,2000.00,'GCash','approved','TXN-20260513-58155',7,'2026-05-13 16:05:16',NULL,NULL,'2026-05-13 16:04:22'),(4,1,2,2000.00,'Maya','approved','TXN-20260513-21460',7,'2026-05-13 17:44:54',NULL,NULL,'2026-05-13 16:04:39');
/*!40000 ALTER TABLE `topup_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction` (
  `transactionID` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` int(11) NOT NULL,
  `merchantID` int(11) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `date_time` datetime NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference` varchar(255) NOT NULL,
  PRIMARY KEY (`transactionID`),
  KEY `transaction.usersFK` (`wallet_id`),
  KEY `transaction.merchantFK` (`merchantID`),
  CONSTRAINT `transaction.merchantFK` FOREIGN KEY (`merchantID`) REFERENCES `merchant` (`merchantID`),
  CONSTRAINT `transaction.usersFK` FOREIGN KEY (`wallet_id`) REFERENCES `users` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction`
--

LOCK TABLES `transaction` WRITE;
/*!40000 ALTER TABLE `transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(30) NOT NULL,
  `transaction_type` enum('cash_in','payment','voucher_payment','merchant_settle','voucher_create','voucher_expire','cap_increase','p2p_transfer','service_fee') NOT NULL,
  `initiated_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.id -- who triggered this transaction',
  `student_wallet_id` int(10) unsigned DEFAULT NULL,
  `merchant_wallet_id` int(10) unsigned DEFAULT NULL,
  `voucher_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `vault_before` decimal(15,2) NOT NULL COMMENT 'Vault snapshot before',
  `vault_after` decimal(15,2) NOT NULL COMMENT 'Vault snapshot after',
  `total_in_circulation` decimal(15,2) NOT NULL COMMENT 'vault_after + all wallet balances + all active voucher balances',
  `status` enum('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `idx_txn_type` (`transaction_type`),
  KEY `idx_txn_student` (`student_wallet_id`),
  KEY `idx_txn_merchant` (`merchant_wallet_id`),
  KEY `idx_txn_voucher` (`voucher_id`),
  KEY `idx_txn_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'TXN-20260513-04199','cash_in',7,1,NULL,NULL,1000.00,200000.00,199000.00,200000.00,'completed',NULL,'2026-05-13 16:01:50'),(2,'TXN-20260513-86618','payment',2,1,1,NULL,750.00,199000.00,199000.00,200000.00,'completed',NULL,'2026-05-13 16:03:02'),(3,'TXN-20260513-02871','cash_in',7,2,NULL,NULL,2000.00,199000.00,197000.00,200000.00,'completed',NULL,'2026-05-13 16:04:48'),(4,'TXN-20260513-58155','cash_in',7,2,NULL,NULL,2000.00,197000.00,195000.00,200000.00,'completed',NULL,'2026-05-13 16:05:16'),(5,'TXN-20260513-41051','payment',1,2,1,NULL,3500.00,195000.00,195000.00,200000.00,'completed',NULL,'2026-05-13 16:06:58'),(6,'TXN-20260513-63526','merchant_settle',7,NULL,1,NULL,4250.00,195000.00,199250.00,200000.00,'completed',NULL,'2026-05-13 16:08:11'),(7,'TXN-20260513-21460','cash_in',7,2,NULL,NULL,2000.00,199250.00,197250.00,200000.00,'completed',NULL,'2026-05-13 17:44:54'),(8,'VOU-20260514-00001','voucher_create',7,NULL,NULL,1,500.00,197250.00,196750.00,200000.00,'completed','Voucher VCH-9F948010 issued to Ezekiel Clarence · exp 2026-05-15 03:38:25','2026-05-14 03:38:25'),(9,'VOU-20260608-00002','voucher_create',7,NULL,NULL,2,900.00,196750.00,195850.00,200000.00,'completed','Voucher VCH-EC2381D1 issued to Paolo Varon - exp 2026-06-09 19:16:19','2026-06-08 19:16:19'),(11,'POS-20260615-37E9DF','payment',2,1,1,NULL,225.00,195850.00,195850.00,200000.00,'completed','POS QR Sale: 3x Tapsilog','2026-06-15 23:01:06');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_guard_transaction_cap` BEFORE INSERT ON `transactions` FOR EACH ROW BEGIN
    DECLARE v_cap DECIMAL(15,2);
    SELECT total_circulation_cap INTO v_cap
    FROM system_settings WHERE id = 1;

    IF NEW.total_in_circulation > v_cap + 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_EXCEEDED: total_in_circulation would exceed total_circulation_cap. Transaction blocked.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `userID` int(11) NOT NULL AUTO_INCREMENT,
  `last_name` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) NOT NULL DEFAULT '',
  `suffix` varchar(20) NOT NULL DEFAULT '',
  `contact_number` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(80) DEFAULT NULL,
  `roleID` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mint_pin` varchar(255) DEFAULT NULL COMMENT 'bcrypt hash of the super-admin mint PIN -- required above monthly limit',
  `profile_img` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sub_role` varchar(30) DEFAULT NULL COMMENT 'Granular role: super_admin | merchant_admin | merchant_staff | student',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = merchant must change password on next login',
  `merchant_owner_id` int(10) unsigned DEFAULT NULL COMMENT 'FK -> users.userID â€” links Merchant Staff to their Merchant Admin',
  `is_first_login` tinyint(1) NOT NULL DEFAULT 0,
  `password_changed` tinyint(1) NOT NULL DEFAULT 1,
  `temp_password` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`userID`),
  KEY `users_roleFK` (`roleID`),
  CONSTRAINT `users_roleFK` FOREIGN KEY (`roleID`) REFERENCES `role` (`roleID`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Banez','Michael Keith','Garciua','',9171234567,'michael@email.com',NULL,1,'$2y$10$3DbXBT4q2/EuPLc7lWBvO.aJsGq9VULf1jkV51Y5naIJ4vQXVLhj2',NULL,'','2026-04-29 13:46:44','student',0,NULL,0,1,NULL),(2,'Clarence','Zeke','Dela','',9179876543,'otto.cruz@email.com',NULL,1,'pass123',NULL,'','2026-04-29 13:46:44','student',0,NULL,0,1,NULL),(5,'Ramos','Maria','Bautista','',9175556789,'maria.ramos@email.com',NULL,1,'pass123',NULL,'f','2026-04-29 13:46:44','student',0,NULL,0,1,NULL),(6,'Garcia','Jose','Mendoza','',9176667890,'jose.garcia@email.com',NULL,1,'pass123',NULL,'f','2026-04-29 13:46:44','student',0,NULL,0,1,NULL),(7,'Reyes','Ana','Lopez','',9170001111,'ana.reyes@email.com',NULL,3,'adminpass',NULL,'f','2026-04-29 13:46:44','super_admin',0,NULL,0,1,NULL),(8,'Villanueva','Carlos','Aquino','',9171230000,'carlos.villanueva@email.com',NULL,2,'merchantpass',NULL,'f','2026-04-29 13:46:44','merchant_admin',0,NULL,0,1,NULL),(9,'Fernandez','Laura','Torres','',9171230001,'laura.fernandez@email.com',NULL,2,'merchantpass',NULL,'f','2026-04-29 13:46:44','merchant_admin',0,NULL,0,1,NULL),(10,'Samson','Jasmin','','',0,'daitodump@gmail.com',NULL,1,'$2y$10$VMHPpleHKD2PvMFeNLGt8uUPT3KNY/zJYCRyIGruWGYPN216uydxC',NULL,'','2026-06-09 13:32:05',NULL,0,NULL,0,1,NULL),(11,'Super','Admin','','',9547843511,'super@gmail.com',NULL,4,'superpass',NULL,'','2026-06-09 14:15:01',NULL,0,NULL,0,1,NULL),(12,'Admin','Super','','',9000000000,'superadmin@gjc.edu.ph',NULL,4,'$2y$10$HXxe1p1chWQCTzPfQXwEmem4S829sehxF57zaavcHC/Y3oRZTGNAu',NULL,'default.png','2026-06-09 14:18:17','super_admin',0,NULL,0,1,NULL),(13,'Admin','Merchant','','',9110002026,'merchantadmin@gjc.edu.ph',NULL,5,'$2y$10$5e4ozzFKkyB56yQggKhg5..fH2T7OO9aoWPzvlxh66Q3JpqfYBn3K',NULL,'default.png','2026-06-09 14:35:54','merchant_admin',0,NULL,0,1,NULL),(14,'Emata','Monica','','',9614708712,'monicaemata118@gmail.com',NULL,6,'$2y$10$Jh1RP/dMuAE/CsU99e0ndO9iceWOGkwMHJg8aMziQ9qBlI6uWy17C',NULL,'','2026-06-09 15:02:40','merchant_staff',0,13,0,1,NULL),(15,'Admin','Merchant2','','',9110003000,'merchantadmin2@gjc.edu.ph',NULL,5,'$2y$10$abcdefghijklmnopqrstuuVwXyZ0123456789ABCDEFGHIJKLMNOP.',NULL,'default.png','2026-06-10 02:32:19','merchant_admin',0,NULL,0,1,NULL),(16,'Office','Finance','','',9000000001,'finance@gjc.edu.ph','finance',4,'$2y$10$6LBKZuJmqkcGXh8C0tqasesF5lPAm0v.W7K93IAbqpjPyy4xZ0HSC',NULL,'','2026-06-15 08:33:54','super_admin',0,NULL,0,1,NULL),(17,'Dalisay','Cardo','','',9614708398,'ezekielclarence06@gmail.com',NULL,2,'$2y$10$SsKC9Ek..DlTNeGygCrhOOrvVvmmW/cJX4yhRWUsmaU.RQ7zhaDhy',NULL,'uploads/stall_applications/1/profile_picture_17815066751467.png','2026-06-15 08:38:17','merchant_admin',1,NULL,0,1,NULL),(19,'Makima','Makima','','',9614708398,'virgelopez611@gmail.com',NULL,2,'$2y$10$atF6KfUH6HbD79XkwVT1YOO9aOsXckOn6UHTtcC9iBYxewFiYuLAK',NULL,'uploads/stall_applications/6/profile_picture_17815361511127.png','2026-06-15 15:14:01','merchant_admin',0,NULL,0,1,NULL),(21,'Reyes','Mark','','',9614709203,'ezekielclarencesantiago68@gmail.com',NULL,2,'$2y$10$sQl8qz7cfzTf8gWH2aGbceLDcRtFt6NseH6QdBu7Y3.46TxuSPZSG',NULL,'uploads/stall_applications/3/profile_picture_17815291266389.jpg','2026-06-15 16:36:55','merchant_admin',0,NULL,0,1,NULL),(22,'Emata','Monica','','',9614708712,'noahgray430@gmail.com',NULL,6,'$2y$10$WrU41xA9Xf73oypjWNA0HeKXFcUUiIbVR2yNufNZ7gDMJGptKIluy',NULL,'','2026-06-15 17:06:44','merchant_staff',0,21,0,1,NULL),(23,'Santiago','Ezekiel Clarence','','',9000000002,'student@gjc.edu.ph','student',1,'$2y$10$BfCMeXL/6QLTQbK2mpPks.zuiWLObZsxvvA51QgHpWWNvpYAGbzhu',NULL,'','2026-06-15 17:10:47','student',0,NULL,0,1,NULL),(26,'Itadori','Yuji','','',9614708712,'ezekielclarence6@gmail.com',NULL,2,'$2y$10$K8onjWldzsjy06arJAHaaOKhJH3Quw7YnpWBt2eXqjORED3ZdpY7i',NULL,'assets/merchant_logos/11.png','2026-06-20 14:07:06','merchant_admin',1,NULL,1,0,'hUGPH9KAeQ'),(27,'Itadori','Yuji','','',9614708712,'kujo7397@gmail.com',NULL,2,'$2y$10$pmn7ohzR1ffamKIJYqlsYuJQgWoAu4bNLVzrGnlQGlvPP./n4pR7C',NULL,'assets/merchant_logos/12.png','2026-06-20 14:21:23','merchant_admin',0,NULL,0,1,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_circulation_health`
--

DROP TABLE IF EXISTS `v_circulation_health`;
/*!50001 DROP VIEW IF EXISTS `v_circulation_health`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_circulation_health` AS SELECT 
 1 AS `cap`,
 1 AS `vault`,
 1 AS `student_wallets_total`,
 1 AS `merchant_wallets_total`,
 1 AS `active_vouchers_total`,
 1 AS `total_in_circulation`,
 1 AS `circulation_drift`,
 1 AS `minted_this_month`,
 1 AS `mint_events_this_month`,
 1 AS `monthly_soft_limit`,
 1 AS `remaining_mint_budget`,
 1 AS `as_of`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_circulation_snapshot`
--

DROP TABLE IF EXISTS `v_circulation_snapshot`;
/*!50001 DROP VIEW IF EXISTS `v_circulation_snapshot`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_circulation_snapshot` AS SELECT 
 1 AS `cap`,
 1 AS `vault`,
 1 AS `student_wallets_total`,
 1 AS `merchant_wallets_total`,
 1 AS `active_vouchers_total`,
 1 AS `total_in_circulation`,
 1 AS `circulation_drift`,
 1 AS `as_of`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_p2p_daily_totals`
--

DROP TABLE IF EXISTS `v_p2p_daily_totals`;
/*!50001 DROP VIEW IF EXISTS `v_p2p_daily_totals`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_p2p_daily_totals` AS SELECT 
 1 AS `from_user_id`,
 1 AS `transfer_date`,
 1 AS `daily_total`,
 1 AS `transfer_count`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_vouchers_active`
--

DROP TABLE IF EXISTS `v_vouchers_active`;
/*!50001 DROP VIEW IF EXISTS `v_vouchers_active`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_vouchers_active` AS SELECT 
 1 AS `id`,
 1 AS `voucher_code`,
 1 AS `visitor_name`,
 1 AS `visitor_contact`,
 1 AS `initial_value`,
 1 AS `remaining_balance`,
 1 AS `status`,
 1 AS `is_refundable`,
 1 AS `created_at`,
 1 AS `expires_at`,
 1 AS `minutes_until_expiry`,
 1 AS `computed_status`,
 1 AS `issued_by_name`,
 1 AS `use_count`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `voucher_payment_log`
--

DROP TABLE IF EXISTS `voucher_payment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voucher_payment_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` int(10) unsigned NOT NULL,
  `merchant_wallet_id` int(10) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `scanned_by` int(10) unsigned DEFAULT NULL,
  `transaction_ref` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpl_voucher` (`voucher_id`),
  KEY `idx_vpl_merchant` (`merchant_wallet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voucher_payment_log`
--

LOCK TABLES `voucher_payment_log` WRITE;
/*!40000 ALTER TABLE `voucher_payment_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `voucher_payment_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `voucher_code` varchar(64) NOT NULL,
  `issued_by` int(10) unsigned NOT NULL COMMENT 'FK -> users.id (cashier or admin who created it)',
  `is_refundable` tinyint(1) NOT NULL DEFAULT 0,
  `visitor_name` varchar(120) NOT NULL,
  `visitor_contact` varchar(60) DEFAULT NULL,
  `initial_value` decimal(15,2) DEFAULT NULL,
  `original_amount` decimal(10,2) NOT NULL COMMENT 'Points pulled from the vault at creation time',
  `remaining_balance` decimal(10,2) NOT NULL COMMENT 'Unspent points -- stays in the economy, non-refundable',
  `status` enum('active','used','expired','void') NOT NULL DEFAULT 'active',
  `is_non_refundable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Always 1 -- architectural constant, never override',
  `expires_at` datetime NOT NULL,
  `expired_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `use_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_code` (`voucher_code`),
  UNIQUE KEY `qr_code_hash` (`qr_code_hash`),
  KEY `idx_v_hash` (`qr_code_hash`),
  KEY `idx_v_status` (`status`),
  KEY `idx_v_expiry` (`expires_at`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
INSERT INTO `vouchers` VALUES (1,'b18e113a3760541abe1ca05777f4faebf1c7241d575a17a62a5aeb56de97a014','VCH-9F948010',7,1,'Ezekiel Clarence','09610912764',500.00,0.00,500.00,'active',1,'2026-05-15 03:38:25',NULL,NULL,NULL,0,'2026-05-14 03:38:25','2026-05-14 03:38:25'),(2,'28f2b60aea6ba408da195d7d7e6104a0274845171c20c70b4db1457682801e7f','VCH-EC2381D1',7,1,'Paolo Varon','',900.00,0.00,900.00,'active',1,'2026-06-09 19:16:19',NULL,NULL,NULL,0,'2026-06-08 19:16:19','2026-06-08 19:16:19');
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_block_expired_voucher_use` BEFORE UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.remaining_balance < OLD.remaining_balance
       AND OLD.status IN ('expired', 'cancelled', 'redeemed')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VOUCHER_INACTIVE: Cannot deduct from an expired, redeemed, or cancelled voucher.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_recycle_expired_voucher` AFTER UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.status = 'expired'
       AND OLD.status != 'expired'
       AND NEW.remaining_balance > 0
       AND NEW.is_refundable = 0
    THEN
        UPDATE system_settings
           SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
         WHERE id = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `wallet`
--

DROP TABLE IF EXISTS `wallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet` (
  `wallet_id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `balance` int(11) NOT NULL,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`wallet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet`
--

LOCK TABLES `wallet` WRITE;
/*!40000 ALTER TABLE `wallet` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `v_circulation_health`
--

/*!50001 DROP VIEW IF EXISTS `v_circulation_health`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_circulation_health` AS select `ss`.`total_circulation_cap` AS `cap`,`ss`.`cashier_vault_points` AS `vault`,coalesce(`sw`.`student_total`,0) AS `student_wallets_total`,coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`,coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`,`ss`.`cashier_vault_points` + coalesce(`sw`.`student_total`,0) + coalesce(`mw`.`merchant_total`,0) + coalesce(`vo`.`voucher_total`,0) AS `total_in_circulation`,`ss`.`total_circulation_cap` - `ss`.`cashier_vault_points` - coalesce(`sw`.`student_total`,0) - coalesce(`mw`.`merchant_total`,0) - coalesce(`vo`.`voucher_total`,0) AS `circulation_drift`,coalesce(`cm`.`minted_this_month`,0) AS `minted_this_month`,coalesce(`cm`.`mint_events`,0) AS `mint_events_this_month`,50000.00 AS `monthly_soft_limit`,greatest(0,50000.00 - coalesce(`cm`.`minted_this_month`,0)) AS `remaining_mint_budget`,`ss`.`updated_at` AS `as_of` from ((((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where `vouchers`.`status` = 'active') `vo`) left join (select sum(`cap_increase_log`.`amount_added`) AS `minted_this_month`,count(0) AS `mint_events` from `cap_increase_log` where month(`cap_increase_log`.`created_at`) = month(curdate()) and year(`cap_increase_log`.`created_at`) = year(curdate())) `cm` on(1)) where `ss`.`id` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_circulation_snapshot`
--

/*!50001 DROP VIEW IF EXISTS `v_circulation_snapshot`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_circulation_snapshot` AS select `ss`.`total_circulation_cap` AS `cap`,`ss`.`cashier_vault_points` AS `vault`,coalesce(`sw`.`student_total`,0) AS `student_wallets_total`,coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`,coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`,`ss`.`cashier_vault_points` + coalesce(`sw`.`student_total`,0) + coalesce(`mw`.`merchant_total`,0) + coalesce(`vo`.`voucher_total`,0) AS `total_in_circulation`,`ss`.`total_circulation_cap` - `ss`.`cashier_vault_points` - coalesce(`sw`.`student_total`,0) - coalesce(`mw`.`merchant_total`,0) - coalesce(`vo`.`voucher_total`,0) AS `circulation_drift`,`ss`.`updated_at` AS `as_of` from (((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where `vouchers`.`status` = 'active') `vo`) where `ss`.`id` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_p2p_daily_totals`
--

/*!50001 DROP VIEW IF EXISTS `v_p2p_daily_totals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_p2p_daily_totals` AS select `p2p_transfers`.`from_user_id` AS `from_user_id`,cast(`p2p_transfers`.`created_at` as date) AS `transfer_date`,sum(`p2p_transfers`.`amount`) AS `daily_total`,count(0) AS `transfer_count` from `p2p_transfers` where `p2p_transfers`.`status` = 'completed' group by `p2p_transfers`.`from_user_id`,cast(`p2p_transfers`.`created_at` as date) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_vouchers_active`
--

/*!50001 DROP VIEW IF EXISTS `v_vouchers_active`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_vouchers_active` AS select `v`.`id` AS `id`,`v`.`voucher_code` AS `voucher_code`,`v`.`visitor_name` AS `visitor_name`,`v`.`visitor_contact` AS `visitor_contact`,`v`.`initial_value` AS `initial_value`,`v`.`remaining_balance` AS `remaining_balance`,`v`.`status` AS `status`,`v`.`is_refundable` AS `is_refundable`,`v`.`created_at` AS `created_at`,`v`.`expires_at` AS `expires_at`,timestampdiff(MINUTE,current_timestamp(),`v`.`expires_at`) AS `minutes_until_expiry`,case when `v`.`status` <> 'active' then `v`.`status` when current_timestamp() > `v`.`expires_at` then 'expired_pending' when `v`.`remaining_balance` <= 0 then 'fully_redeemed' else 'active' end AS `computed_status`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `issued_by_name`,`v`.`use_count` AS `use_count` from (`vouchers` `v` left join `users` `u` on(`u`.`userID` = `v`.`issued_by`)) */;
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

-- Dump completed on 2026-06-20 22:34:23
