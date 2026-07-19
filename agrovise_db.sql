-- MySQL dump 10.13  Distrib 8.4.7, for Win64 (x86_64)
--
-- Host: localhost    Database: agrovise_db
-- ------------------------------------------------------
-- Server version	8.4.7

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
-- Current Database: `agrovise_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `agrovise_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `agrovise_db`;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `permissions` text COLLATE utf8mb4_unicode_ci,
  `employee_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'superadmin','$2y$10$vDtHy3sGTqY9ZGIEVRsNKObcI5cyySzS8x32iAgwDxOtaDC6DT1t.',NULL,'admin',NULL,NULL,'2026-07-04 00:40:26'),(9,'sarmad','$2y$12$i2gR7zYO9e5kIlncPp35xOYqIL9gXs.88DASbQwnDCuATAUAbWNYu','sarmad@agrovise.com','admin',NULL,1,'2026-07-07 10:48:15');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banking`
--

DROP TABLE IF EXISTS `banking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banking`
--

LOCK TABLES `banking` WRITE;
/*!40000 ALTER TABLE `banking` DISABLE KEYS */;
INSERT INTO `banking` VALUES (1,'alfalah','',1029999.00,'2026-04-08 09:00:49'),(2,'ubl','',1030000.00,'2026-04-08 09:01:05'),(3,'hbl','12345',10000.00,'2026-07-04 23:31:29');
/*!40000 ALTER TABLE `banking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnic` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `govt_reg_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'mukaram mushahid','sabzi mandi , sadhar','00000-0000000-0','0300-0971488','000-000-000','fsd-01',NULL,'2026-04-08 05:53:05'),(2,'ch','samundri road , fsd','00000-0000000-0','0300-0971488','00-000-00','fsd-01',NULL,'2026-04-08 05:59:19');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `designations`
--

DROP TABLE IF EXISTS `designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `designations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `designations`
--

LOCK TABLES `designations` WRITE;
/*!40000 ALTER TABLE `designations` DISABLE KEYS */;
INSERT INTO `designations` VALUES (1,'Trainee Sales Officer','2026-07-04 18:03:21'),(2,'Area Sales Officer','2026-07-04 18:03:21'),(3,'Regional Sales Officer','2026-07-04 18:03:21'),(4,'Accounts','2026-07-04 18:03:21'),(5,'General Manager','2026-07-04 18:03:21'),(6,'CEO','2026-07-04 18:03:21');
/*!40000 ALTER TABLE `designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnic` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnic` (`cnic`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'sarmad','00000-0000000-0','0000-0000000','lahore','accounts',35000.00,'2026-04-08 08:40:08'),(5,'test','35202-1234567-9','0300-0971488','P Block Johar town','Trainee Sales Officer',1000000.00,'2026-07-04 22:43:40');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_claims`
--

DROP TABLE IF EXISTS `expense_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `bill_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('WAITING','APPROVED','DECLINED','PAID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAITING',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `paid_via` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_transaction_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_claims`
--

LOCK TABLES `expense_claims` WRITE;
/*!40000 ALTER TABLE `expense_claims` DISABLE KEYS */;
INSERT INTO `expense_claims` VALUES (2,5,'Travel Expense',20000.00,NULL,'bill_6a498dc25a3c6.jpg','PAID',1,'2026-07-05 03:49:06','BANK',7,'2026-07-04 22:48:34');
/*!40000 ALTER TABLE `expense_claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `sales_tax` decimal(5,2) NOT NULL DEFAULT '0.00',
  `purchasing_id` int NOT NULL,
  `packs_per_carton` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `product_id` (`product_id`),
  KEY `fk_ii_purchasing` (`purchasing_id`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,1,6,200,150.00,0.00,0,0),(2,1,8,200,1300.00,0.00,0,0),(3,1,9,25,500.00,0.00,0,0),(4,2,6,200,170.00,0.00,0,0),(5,2,11,400,2000.00,0.00,0,0),(6,3,12,1555,175.00,0.00,3,40),(7,4,12,445,200.00,0.00,3,40),(8,5,12,100,90.00,0.00,3,40),(9,6,12,1,100.00,17.00,3,0),(10,7,12,2,50.00,0.00,3,0),(13,9,12,200,100.00,0.00,3,40),(12,10,12,10,250.00,0.00,3,0),(14,11,12,90,200.00,0.00,3,40),(15,12,12,5,100.00,0.00,3,0),(16,13,12,2,100.00,0.00,3,0),(18,14,14,50,450.00,0.00,5,40),(19,15,14,40,500.00,0.00,5,40),(20,16,14,10,256.00,0.00,5,40),(21,17,14,111,456.00,0.00,5,40);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int NOT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `employee_id` int DEFAULT NULL,
  `policy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `client_id` (`client_id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (3,'INV-000001',2,'2026-04-08',272125.00,'2026-04-08 08:38:25',NULL,NULL),(4,'INV-000004',1,'2026-04-08',89000.00,'2026-04-08 09:00:16',NULL,NULL),(5,'INV-000005',1,'2026-07-04',9000.00,'2026-07-04 13:07:34',2,NULL),(9,'INV-000006',2,'2026-07-04',20000.00,'2026-07-04 22:38:03',5,NULL),(11,'INV-000010',1,'2026-07-05',18000.00,'2026-07-05 23:35:24',5,NULL),(14,'INV-000012',1,'2026-07-06',22500.00,'2026-07-06 08:01:57',5,NULL),(15,'INV-000015',2,'2026-07-06',20000.00,'2026-07-06 08:03:30',5,NULL),(16,'INV-000016',1,'2026-07-06',2560.00,'2026-07-06 09:10:39',5,NULL),(17,'INV-000017',1,'2026-07-06',50616.00,'2026-07-06 09:47:06',5,NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expenses.php',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target` (`admin_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (6,1,'test submitted a Travel Expense claim of Rs. 20,000.00 for approval.','expenses.php',1,'2026-07-04 22:48:34'),(7,2,'test submitted a Travel Expense claim of Rs. 20,000.00 for approval.','expenses.php',0,'2026-07-04 22:48:34'),(8,4,'test submitted a Travel Expense claim of Rs. 20,000.00 for approval.','expenses.php',1,'2026-07-04 22:48:34'),(9,7,'Your Travel Expense claim of Rs. 20,000.00 was approved.','expenses.php',0,'2026-07-04 22:49:06'),(10,7,'Your Travel Expense claim of Rs. 20,000.00 has been paid via Bank.','expenses.php',0,'2026-07-04 22:49:40');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outbound_messages`
--

DROP TABLE IF EXISTS `outbound_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbound_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('EMPLOYEE','CLIENT','VENDOR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PENDING','SENT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `sent_via` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outbound_messages`
--

LOCK TABLES `outbound_messages` WRITE;
/*!40000 ALTER TABLE `outbound_messages` DISABLE KEYS */;
INSERT INTO `outbound_messages` VALUES (12,'EMPLOYEE','test','03277503792','Asslam-U-Alikum Mr test, the sudao (Qty: 50) for your client mukaram mushahid has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 08:01 AM\n\nRegards,\nAgrovise Team','INV-000012','SENT','SMS','2026-07-06 08:01:57','2026-07-06 13:01:57'),(13,'CLIENT','mukaram mushahid','0327-7503792','Asslam-U-Alikum Mr mukaram mushahid, the sudao (Qty: 50) has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 08:01 AM\n\nRegards,\nAgrovise Team','INV-000012','SENT','SMS','2026-07-06 08:01:57','2026-07-06 13:01:57'),(14,'EMPLOYEE','test','0300-0971488','Asslam-U-Alikum Mr test, the sudao (Qty: 40) for your client ch has been invoiced successfully and will be dispatched soon for the samundri road , fsd, fsd-01.\nInvoiced on: 06 Jul 2026, 08:03 AM\n\nRegards,\nAgrovise Team','INV-000015','SENT','SMS','2026-07-06 08:03:31','2026-07-06 13:03:31'),(15,'CLIENT','ch','0300-0971488','Asslam-U-Alikum Mr ch, the sudao (Qty: 40) has been invoiced successfully and will be dispatched soon for the samundri road , fsd, fsd-01.\nInvoiced on: 06 Jul 2026, 08:03 AM\n\nRegards,\nAgrovise Team','INV-000015','SENT','SMS','2026-07-06 08:03:31','2026-07-06 13:03:31'),(16,'EMPLOYEE','test','0300-0971488','Asslam-U-Alikum Mr test, the sudao (Qty: 10) for your client mukaram mushahid has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 09:10 AM\n\nRegards,\nAgrovise Team','INV-000016','SENT','SMS','2026-07-06 09:10:39','2026-07-06 14:10:39'),(17,'CLIENT','mukaram mushahid','0300-0971488','Asslam-U-Alikum Mr mukaram mushahid, the sudao (Qty: 10) has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 09:10 AM\n\nRegards,\nAgrovise Team','INV-000016','SENT','SMS','2026-07-06 09:10:39','2026-07-06 14:10:40'),(18,'EMPLOYEE','test','0300-0971488','Asslam-U-Alikum Mr test, the sudao (Qty: 111) for your client mukaram mushahid has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 09:47 AM\n\nRegards,\nAgrovise Team','INV-000017','SENT','SMS','2026-07-06 09:47:06','2026-07-06 14:47:07'),(19,'CLIENT','mukaram mushahid','0300-0971488','Asslam-U-Alikum Mr mukaram mushahid, the sudao (Qty: 111) has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 06 Jul 2026, 09:47 AM\n\nRegards,\nAgrovise Team','INV-000017','SENT','SMS','2026-07-06 09:47:07','2026-07-06 14:47:07'),(20,'CLIENT','mukaram mushahid','0300-0971488','Asslam-U-Alikum Mr mukaram mushahid, the CEEDO (Qty: 10), sudao (Qty: 5) has been invoiced successfully and will be dispatched soon for the sabzi mandi , sadhar, fsd-01.\nInvoiced on: 14 Jul 2026, 10:22 AM\n\nRegards,\nAgrovise Team','INV-000018','PENDING',NULL,'2026-07-14 10:22:35',NULL);
/*!40000 ALTER TABLE `outbound_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packing_materials_used`
--

DROP TABLE IF EXISTS `packing_materials_used`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packing_materials_used` (
  `id` int NOT NULL AUTO_INCREMENT,
  `packing_operation_id` int NOT NULL,
  `material_purchase_id` int NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `material_cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `packing_operation_id` (`packing_operation_id`),
  KEY `material_purchase_id` (`material_purchase_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packing_materials_used`
--

LOCK TABLES `packing_materials_used` WRITE;
/*!40000 ALTER TABLE `packing_materials_used` DISABLE KEYS */;
/*!40000 ALTER TABLE `packing_materials_used` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packing_operations`
--

DROP TABLE IF EXISTS `packing_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packing_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bulk_purchase_id` int NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `bulk_cost` decimal(10,2) NOT NULL,
  `finished_purchase_id` int NOT NULL,
  `total_material_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `packing_cost` decimal(10,2) NOT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `selling_price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bulk_purchase_id` (`bulk_purchase_id`),
  KEY `finished_purchase_id` (`finished_purchase_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packing_operations`
--

LOCK TABLES `packing_operations` WRITE;
/*!40000 ALTER TABLE `packing_operations` DISABLE KEYS */;
INSERT INTO `packing_operations` VALUES (1,1,200.00,15000.00,3,0.00,0.00,'2026-04-08 08:37:18',175.00),(2,4,199.98,64993.50,5,0.00,0.00,'2026-07-06 08:01:19',449.99);
/*!40000 ALTER TABLE `packing_operations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_receipts`
--

DROP TABLE IF EXISTS `payment_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pr_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` int NOT NULL,
  `date` date NOT NULL,
  `amount_received` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `bank_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_no` (`pr_no`),
  KEY `client_id` (`client_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_receipts`
--

LOCK TABLES `payment_receipts` WRITE;
/*!40000 ALTER TABLE `payment_receipts` DISABLE KEYS */;
INSERT INTO `payment_receipts` VALUES (1,'PR-000001',2,'2026-04-08',200000.00,'2026-04-08 06:00:35',1,NULL),(2,'PR-000002',1,'2026-07-04',30000.00,'2026-07-04 13:08:44',2,NULL),(4,'PR-000003',1,'2026-07-04',499999.00,'2026-07-04 22:41:54',1,1);
/*!40000 ALTER TABLE `payment_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `policies`
--

DROP TABLE IF EXISTS `policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pol_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `policies`
--

LOCK TABLES `policies` WRITE;
/*!40000 ALTER TABLE `policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `policy_items`
--

DROP TABLE IF EXISTS `policy_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `policy_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sales_tax` decimal(5,2) NOT NULL DEFAULT '0.00',
  `packs_per_carton` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_pi_policy` (`policy_id`),
  KEY `idx_pi_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `policy_items`
--

LOCK TABLES `policy_items` WRITE;
/*!40000 ALTER TABLE `policy_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `policy_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_views`
--

DROP TABLE IF EXISTS `product_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitor_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pv_product` (`product_id`),
  KEY `idx_pv_visitor` (`visitor_id`),
  KEY `idx_pv_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_views`
--

LOCK TABLES `product_views` WRITE;
/*!40000 ALTER TABLE `product_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `packing_type` enum('Bottle','Bag','None') COLLATE utf8mb4_unicode_ci DEFAULT 'None',
  `avg_packs_per_carton` int DEFAULT '0',
  `is_published` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (16,'AgroKill Pro','insecticides','insecticide_1.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',20,1),(12,'CEEDO','insecticides','product_69d6114d93b9d.jpeg','2026-04-08 08:26:53','2026-04-08 08:26:53','Bottle',40,1),(13,'METALAXYL MENCOZABE','weedicides','product_69d6116b7ec33.jpeg','2026-04-08 08:27:23','2026-04-08 08:27:23','Bag',50,1),(14,'sudao','insecticides','placeholder-product.jpg','2026-05-08 08:32:50','2026-05-08 08:32:50','Bottle',40,1),(15,'gengwei','weedicides','placeholder-product.jpg','2026-05-08 13:25:08','2026-05-08 13:25:08','Bottle',12,1),(17,'BugShield Plus','insecticides','insecticide_2.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',20,1),(18,'PestGuard Supreme','insecticides','insecticide_3.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',24,1),(19,'InsectAway Ultra','insecticides','insecticide_4.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',24,1),(20,'WeedClear Max','weedicides','weedicide_1.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',12,1),(21,'GrassGuard Pro','weedicides','weedicide_2.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',12,1),(22,'WeedFree Total','weedicides','weedicide_3.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',10,1),(23,'Herbicide Xtra','weedicides','weedicide_4.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',10,1),(24,'FungiStop Pro','fungicides','fungicide_1.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',20,1),(25,'MoldGuard Supreme','fungicides','fungicide_2.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bottle',20,1),(26,'CropShield Fungus','fungicides','fungicide_3.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',40,0),(27,'FungiClear Ultra','fungicides','fungicide_4.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',40,0),(28,'GrowGranules Plus','granulars','granular_1.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',5,1),(29,'SoilBoost Granules','granulars','granular_2.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',5,1),(30,'NutriGran Pro','granulars','granular_3.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',4,1),(31,'RootGuard Granules','granulars','granular_4.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',4,1),(32,'MicroMix Essential','micronutrients','micro_1.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',20,1),(33,'ZincBoost Pro','micronutrients','micro_2.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',20,1),(34,'IronGuard Plus','micronutrients','micro_3.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',20,1),(35,'MultiMin Supreme','micronutrients','micro_4.jpg','2026-05-13 08:54:55','2026-05-13 08:54:55','Bag',20,1);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchasing`
--

DROP TABLE IF EXISTS `purchasing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchasing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `batch_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('BULK','PACKING','FINISHED','OTHERS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `date_added` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` date DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchasing`
--

LOCK TABLES `purchasing` WRITE;
/*!40000 ALTER TABLE `purchasing` DISABLE KEYS */;
INSERT INTO `purchasing` VALUES (1,12,'lag-20250111-9','BULK',75.00,0.00,15000.00,'2026-04-08 08:27:55',NULL,NULL),(3,12,'lag-20250111-9','BULK',75.00,609.00,74925.00,'2026-04-08 08:37:18',NULL,NULL),(4,14,'lag-20250111-0','BULK',325.00,0.02,65000.00,'2026-07-06 07:59:33','2027-10-19',1),(5,14,'PK-20260706-61','FINISHED',54.16,989.00,64993.50,'2026-07-06 08:01:19',NULL,NULL);
/*!40000 ALTER TABLE `purchasing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('messaging_enabled','1','2026-07-05 23:13:49'),('messaging_number','03326679047','2026-07-05 23:33:57'),('sms_auto_send','1','2026-07-06 04:09:41'),('sms_gateway_pass','Agrovise123','2026-07-06 09:50:24'),('sms_gateway_type','smsgate','2026-07-06 09:39:41'),('sms_gateway_url','192.168.0.101:8080','2026-07-06 09:39:41'),('sms_gateway_user','agrovise','2026-07-06 09:39:41');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_visits`
--

DROP TABLE IF EXISTS `site_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_visits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitor_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sv_visitor` (`visitor_id`),
  KEY `idx_sv_created` (`created_at`),
  KEY `idx_sv_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_visits`
--

LOCK TABLES `site_visits` WRITE;
/*!40000 ALTER TABLE `site_visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `site_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_employees`
--

DROP TABLE IF EXISTS `transaction_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `employee_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_employees`
--

LOCK TABLES `transaction_employees` WRITE;
/*!40000 ALTER TABLE `transaction_employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaction_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `vendor_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_id` (`bank_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `banking` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,'DEPOSIT','Salaries',50000.00,'','2026-05-08','2026-05-08 12:33:27',NULL,NULL,NULL),(2,1,'WITHDRAWAL','Salaries',100000.00,'','2026-05-08','2026-05-08 12:34:05',NULL,NULL,NULL),(3,2,'DEPOSIT','Client Payment',30000.00,'Payment Received via PR: PR-000002','2026-07-04','2026-07-04 13:08:44',NULL,NULL,NULL),(4,1,'WITHDRAWAL','Salaries',400000.00,'','2026-07-04','2026-07-04 13:17:21',NULL,NULL,NULL),(6,1,'DEPOSIT','Client Payment',499999.00,'Payment Received via PR: PR-000003','2026-07-04','2026-07-04 22:41:54',NULL,NULL,NULL),(7,1,'WITHDRAWAL','Employee Expenses',20000.00,'Expense reimbursement — claim #2 (Travel Expense)','2026-07-04','2026-07-04 22:49:40',NULL,5,NULL);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_readings`
--

DROP TABLE IF EXISTS `vehicle_readings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_readings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vehicle_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `reading_month` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reading_km` decimal(10,1) NOT NULL,
  `meter_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vehicle_month` (`vehicle_id`,`reading_month`),
  KEY `idx_employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_readings`
--

LOCK TABLES `vehicle_readings` WRITE;
/*!40000 ALTER TABLE `vehicle_readings` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_readings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `make` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_of_issue` int DEFAULT NULL,
  `financing` enum('CASH','LOAN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CASH',
  `installment_amount` decimal(12,2) DEFAULT NULL,
  `installments_remaining` int DEFAULT NULL,
  `tenure_months` int DEFAULT NULL,
  `assigned_employee_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  KEY `idx_assigned` (`assigned_employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (2,'ABC-123','suzuki','alto','gray',2026,'LOAN',30000.00,60,60,5,'2026-07-04 18:33:33');
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendors`
--

LOCK TABLES `vendors` WRITE;
/*!40000 ALTER TABLE `vendors` DISABLE KEYS */;
INSERT INTO `vendors` VALUES (1,'LEADER AG','AMIR SHEHZAD','0327-7503792','INDUSTRIAL ESTATE , MULTAN','2026-04-08 08:56:49');
/*!40000 ALTER TABLE `vendors` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-14 15:23:27
