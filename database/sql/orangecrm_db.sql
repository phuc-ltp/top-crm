-- ============================================================================
--  OrangeCRM Enterprise — Database schema + dữ liệu khởi tạo
--  Database : orangecrm_db  (MySQL / MariaDB, InnoDB, utf8mb4)
--  Tạo lúc  : 14/10/2026 15:00
--
--  NỘI DUNG
--   1. Cấu trúc toàn bộ bảng, khóa chính, khóa ngoại, chỉ mục (tương đương Laravel migrations)
--   2. Dữ liệu cấu hình: vai trò, quyền, phòng ban, nguồn lead, giai đoạn pipeline, danh mục, cài đặt
--   3. DỮ LIỆU DEMO phục vụ kiểm thử — mọi bản ghi demo có cột is_demo = 1
--      (xóa bằng lệnh: php artisan crm:purge-demo)
--   4. Bảng `migrations` để Laravel biết cấu trúc đã được tạo (không chạy migrate trùng)
--
--  AN TOÀN
--   - File KHÔNG chứa lệnh DROP DATABASE / DROP TABLE.
--   - Chỉ import vào database TRỐNG. Nếu bảng đã tồn tại, lệnh tạo bảng được bỏ qua.
--   - KHÔNG có tài khoản quản trị và KHÔNG có mật khẩu thật. Tài khoản demo có mật khẩu
--     ngẫu nhiên không thể đoán. Sau khi import, mở trình duyệt vào trang /setup để tự tạo
--     tài khoản Super Admin, hoặc chạy: php artisan crm:create-admin
--
--  CÁCH IMPORT: xem docs/DATABASE_SETUP.md
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `orangecrm_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `orangecrm_db`;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: orangecrm_sqlgen
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_action_index` (`action`),
  KEY `activity_logs_created_at_index` (`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attachments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attachable_type` varchar(255) NOT NULL,
  `attachable_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_attachable_type_attachable_id_index` (`attachable_type`,`attachable_id`),
  KEY `attachments_user_id_foreign` (`user_id`),
  CONSTRAINT `attachments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--


--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_leads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `campaign_leads` (
  `campaign_id` bigint(20) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`campaign_id`,`lead_id`),
  KEY `campaign_leads_lead_id_foreign` (`lead_id`),
  CONSTRAINT `campaign_leads_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_leads_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_leads`
--

INSERT INTO `campaign_leads` (`campaign_id`, `lead_id`, `created_at`) VALUES (1,4,'2026-01-26 15:18:00'),(1,5,'2026-07-25 12:07:00'),(1,13,'2026-09-19 17:16:00'),(1,14,'2026-08-25 12:30:00'),(1,20,'2026-09-21 10:18:00'),(1,21,'2026-06-25 10:54:00'),(1,30,'2025-12-20 17:49:00'),(2,2,'2026-04-25 12:03:00'),(2,23,'2026-02-18 09:56:00'),(2,26,'2026-03-20 09:46:00'),(2,27,'2026-07-12 13:45:00'),(2,28,'2026-01-16 15:36:00'),(3,7,'2026-04-10 17:27:00'),(3,11,'2026-01-17 17:54:00'),(3,19,'2026-07-21 13:22:00'),(3,22,'2026-04-14 09:24:00'),(3,29,'2026-07-16 12:36:00'),(4,3,'2026-09-06 11:08:00'),(4,6,'2026-07-03 11:19:00'),(4,8,'2026-04-16 11:39:00'),(4,9,'2026-02-22 13:58:00'),(4,17,'2026-09-12 17:47:00'),(4,18,'2026-02-15 15:22:00'),(4,24,'2026-08-27 14:30:00'),(4,25,'2026-08-04 08:19:00'),(5,1,'2026-02-13 14:24:00'),(5,10,'2026-07-08 10:36:00'),(5,12,'2025-12-09 13:53:00'),(5,15,'2026-05-02 11:46:00'),(5,16,'2026-08-21 15:03:00');

--
-- Table structure for table `campaigns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('email','social','event','ads','content','referral','other') NOT NULL DEFAULT 'other',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `target_leads` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('planned','active','paused','completed','cancelled') NOT NULL DEFAULT 'planned',
  `description` text DEFAULT NULL,
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaigns_owner_id_foreign` (`owner_id`),
  KEY `campaigns_created_by_foreign` (`created_by`),
  KEY `campaigns_status_index` (`status`),
  KEY `campaigns_is_demo_index` (`is_demo`),
  CONSTRAINT `campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`id`, `name`, `type`, `start_date`, `end_date`, `budget`, `target_leads`, `status`, `description`, `owner_id`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Ra mắt CRM Pro Q1','event','2025-12-18','2026-02-16',50000000.00,40,'completed','Chiến dịch demo.',5,5,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(2,'Quảng cáo Facebook mùa hè','social','2026-05-17','2026-07-16',30000000.00,60,'completed','Chiến dịch demo.',5,5,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(3,'Google Ads doanh nghiệp SME','ads','2026-08-15','2026-11-13',45000000.00,80,'active','Chiến dịch demo.',5,5,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(4,'Email chăm sóc khách hàng cũ','email','2026-09-14','2026-12-13',5000000.00,30,'active','Chiến dịch demo.',5,5,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(5,'Hội thảo chuyển đổi số cuối năm','event','2026-11-03','2026-12-03',80000000.00,100,'planned','Chiến dịch demo.',5,5,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL);

--
-- Table structure for table `contacts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_customer_id_foreign` (`customer_id`),
  KEY `contacts_created_by_foreign` (`created_by`),
  KEY `contacts_email_index` (`email`),
  KEY `contacts_is_demo_index` (`is_demo`),
  CONSTRAINT `contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `customer_id`, `full_name`, `position`, `email`, `phone`, `company`, `notes`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Phí Hoàn Thêu','Trưởng phòng IT','lienhe1@demo.orangecrm.local','0940066563','Ông Hoan Hòa',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(2,2,'Từ Chiêu Liêm','Giám đốc','lienhe2@demo.orangecrm.local','0941196576','Công ty Cổ phần Logistics Trường Sơn',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(3,3,'Lô Xuyến Lệ','Trưởng phòng mua hàng','lienhe3@demo.orangecrm.local','0916079650','Công ty TNHH MTV Logistics Hòa Bình',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(4,4,'Bửu Lộc Bảo','Giám đốc','lienhe4@demo.orangecrm.local','0994679102','Tập đoàn Thực phẩm Sao Mai',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(5,5,'Bảo Quân Nghĩa','Trưởng phòng mua hàng','lienhe5@demo.orangecrm.local','0950784437','Tập đoàn Thực phẩm Nam Việt',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(6,6,'Khu Hỷ Nhi','Giám đốc','lienhe6@demo.orangecrm.local','0936038797','Công ty Cổ phần Thương mại Hòa Bình',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(7,7,'Nguyễn Trân Đại','Trưởng phòng IT','lienhe7@demo.orangecrm.local','0925117432','Mang Uyển Quân',NULL,3,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(8,8,'Thái Sỹ San','Trưởng phòng IT','lienhe8@demo.orangecrm.local','0927396489','Công ty TNHH Thương mại Sao Mai',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(9,9,'Chử Di Hỷ','Giám đốc','lienhe9@demo.orangecrm.local','0998691390','Công ty TNHH MTV Thương mại Việt Tiến',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(10,10,'Chương Trinh Kiên','Kế toán trưởng','lienhe10@demo.orangecrm.local','0916916165','Tập đoàn Thực phẩm An Khang',NULL,3,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(11,11,'Mạch Xuyến Phụng','Trưởng phòng IT','lienhe11@demo.orangecrm.local','0958920416','Công ty Cổ phần Dịch vụ Trường Sơn',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(12,12,'Triệu Hoàn Miên','Kế toán trưởng','lienhe12@demo.orangecrm.local','0931305193','Công ty TNHH MTV Thương mại Tân Á',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(13,13,'Đỗ Nhân Kiên','Trưởng phòng mua hàng','lienhe13@demo.orangecrm.local','0981664394','Trưng Tuấn Hảo',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(14,14,'Tông Phi Định','Trưởng phòng IT','lienhe14@demo.orangecrm.local','0957960423','Công ty TNHH MTV Thực phẩm Hưng Thịnh',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(15,15,'Vi Thuận Triệu','Trưởng phòng mua hàng','lienhe15@demo.orangecrm.local','0955939693','Tập đoàn Logistics Thành Công',NULL,2,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(16,16,'Cung Kiều Khôi','Trưởng phòng mua hàng','lienhe16@demo.orangecrm.local','0997266062','Công ty TNHH Sản xuất Tân Á',NULL,4,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(17,17,'Bảo Độ Vĩnh','Kế toán trưởng','lienhe17@demo.orangecrm.local','0930374249','Công ty TNHH Dịch vụ Hưng Thịnh',NULL,4,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(18,18,'Kiều Uy Phụng','Trưởng phòng IT','lienhe18@demo.orangecrm.local','0939145653','Tập đoàn Thực phẩm Tân Á',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(19,19,'Chu Sáng Linh','Kế toán trưởng','lienhe19@demo.orangecrm.local','0970813813','Trần Xuân Tiển',NULL,4,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(20,20,'Hình Lâm Chi','Trưởng phòng IT','lienhe20@demo.orangecrm.local','0939153277','Công ty TNHH Dịch vụ Đại Dương',NULL,4,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(21,21,'Chiêm Hợp Vọng','Trưởng phòng mua hàng','lienhe21@demo.orangecrm.local','0982252626','Công ty Cổ phần Công nghệ Đại Dương',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(22,22,'Phí Tuấn Thủy','Giám đốc','lienhe22@demo.orangecrm.local','0938928996','Công ty TNHH Dịch vụ Đại Dương',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(23,23,'Đậu Ngọc Lân','Trưởng phòng IT','lienhe23@demo.orangecrm.local','0992540246','Công ty Cổ phần Logistics Thành Công',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(24,24,'Ánh Hòa Trầm','Giám đốc','lienhe24@demo.orangecrm.local','0952314941','Công ty Cổ phần Xây dựng An Khang',NULL,4,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(25,25,'Hạ Ty Hà','Trưởng phòng IT','lienhe25@demo.orangecrm.local','0930612243','Khuất Giang Nam',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(26,26,'Đặng Dân Yên','Trưởng phòng mua hàng','lienhe26@demo.orangecrm.local','0954798889','Công ty TNHH MTV Sản xuất Đại Dương',NULL,4,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(27,27,'Vũ Ngọc Lập','Giám đốc','lienhe27@demo.orangecrm.local','0968623142','Tập đoàn Sản xuất Hoàng Long',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(28,28,'Hoàng Tài Giao','Trưởng phòng IT','lienhe28@demo.orangecrm.local','0990663611','Công ty TNHH Công nghệ Bình Minh',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(29,29,'Hy Hiền Lập','Trưởng phòng IT','lienhe29@demo.orangecrm.local','0918872351','Tập đoàn Xây dựng Bình Minh',NULL,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(30,30,'Mâu Bảo Vy','Giám đốc','lienhe30@demo.orangecrm.local','0987312097','Tập đoàn Sản xuất Đại Dương',NULL,2,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL);

--
-- Table structure for table `customer_tag`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `customer_tag` (
  `customer_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`customer_id`,`tag_id`),
  KEY `customer_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `customer_tag_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_tag`
--

INSERT INTO `customer_tag` (`customer_id`, `tag_id`) VALUES (1,3),(1,4),(2,1),(3,1),(3,3),(4,2),(5,2),(5,5),(6,5),(8,1),(8,4),(9,2),(9,3),(10,4),(13,4),(14,3),(15,2),(15,3),(17,5),(18,1),(19,1),(19,4),(22,1),(22,3),(23,5),(25,4),(26,1),(27,3),(28,4);

--
-- Table structure for table `customers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `type` enum('individual','business') NOT NULL DEFAULT 'individual',
  `lead_source_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','potential','vip','inactive') NOT NULL DEFAULT 'active',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`),
  KEY `customers_lead_source_id_foreign` (`lead_source_id`),
  KEY `customers_assigned_to_foreign` (`assigned_to`),
  KEY `customers_created_by_foreign` (`created_by`),
  KEY `customers_updated_by_foreign` (`updated_by`),
  KEY `customers_created_at_index` (`created_at`),
  KEY `customers_name_index` (`name`),
  KEY `customers_phone_index` (`phone`),
  KEY `customers_email_index` (`email`),
  KEY `customers_type_index` (`type`),
  KEY `customers_status_index` (`status`),
  KEY `customers_is_demo_index` (`is_demo`),
  CONSTRAINT `customers_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `code`, `name`, `phone`, `email`, `address`, `company`, `type`, `lead_source_id`, `status`, `assigned_to`, `created_by`, `updated_by`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'KH00001','Ông Hoan Hòa','0987170998','khachhang1@demo.orangecrm.local','7249 Phố Ty Cát Diệu, Xã Trường Nhuận, Quận Cát Cam\nHải Phòng',NULL,'individual',2,'inactive',4,2,NULL,'Khách hàng demo.',1,'2026-03-14 08:19:00','2026-03-14 08:19:00',NULL),(2,'KH00002','Công ty Cổ phần Logistics Trường Sơn','0996921891','khachhang2@demo.orangecrm.local','8, Ấp Nhạn, Xã 8, Quận Ly Thi\nHậu Giang',NULL,'business',8,'active',4,2,NULL,'Khách hàng demo.',1,'2026-05-12 15:12:00','2026-05-12 15:12:00',NULL),(3,'KH00003','Công ty TNHH MTV Logistics Hòa Bình','0980274382','khachhang3@demo.orangecrm.local','82, Thôn 6, Xã Phi Thanh, Huyện Xa\nSơn La',NULL,'business',2,'active',4,2,NULL,'Khách hàng demo.',1,'2026-05-03 17:16:00','2026-05-03 17:16:00',NULL),(4,'KH00004','Hoàng Hùng Cúc','0912445407','khachhang4@demo.orangecrm.local','7198 Phố Kha, Thôn An Quân, Quận Cầm\nĐà Nẵng','Tập đoàn Thực phẩm Sao Mai','individual',9,'active',4,2,NULL,'Khách hàng demo.',1,'2026-01-14 17:39:00','2026-01-14 17:39:00',NULL),(5,'KH00005','Tập đoàn Thực phẩm Nam Việt','0972110656','khachhang5@demo.orangecrm.local','93 Phố Lại Yên Thanh, Phường Hiên Hạ, Quận 4\nBắc Kạn',NULL,'business',7,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-06-09 08:05:00','2026-06-09 08:05:00',NULL),(6,'KH00006','Công ty Cổ phần Thương mại Hòa Bình','0999814588','khachhang6@demo.orangecrm.local','8 Phố An, Xã Tân, Huyện Thào Mỹ\nTuyên Quang',NULL,'business',7,'active',4,2,NULL,'Khách hàng demo.',1,'2026-08-13 12:37:00','2026-08-13 12:37:00',NULL),(7,'KH00007','Mang Uyển Quân','0941744345','khachhang7@demo.orangecrm.local','5945 Phố Lò, Xã Lương, Huyện Bồ Nhi\nHà Nội',NULL,'individual',9,'vip',3,2,NULL,'Khách hàng demo.',1,'2026-07-09 13:35:00','2026-07-09 13:35:00',NULL),(8,'KH00008','Công ty TNHH Thương mại Sao Mai','0993802851','khachhang8@demo.orangecrm.local','7206 Phố Ma Lĩnh Lan, Phường Hàn Ngôn Dương, Huyện Đài Ngân\nHải Phòng',NULL,'business',3,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-03-04 08:26:00','2026-03-04 08:26:00',NULL),(9,'KH00009','Công ty TNHH MTV Thương mại Việt Tiến','0922426244','khachhang9@demo.orangecrm.local','728 Phố Kim Hữu Thịnh, Xã Bình, Huyện 55\nHòa Bình',NULL,'business',6,'active',2,2,NULL,'Khách hàng demo.',1,'2025-11-07 09:50:00','2025-11-07 09:50:00',NULL),(10,'KH00010','Tào Tường Thông','0968338331','khachhang10@demo.orangecrm.local','174 Phố Bồ Cường Anh, Phường Phan Thiên Lai, Huyện Xuyến Đức\nKon Tum','Tập đoàn Thực phẩm An Khang','individual',6,'potential',3,2,NULL,'Khách hàng demo.',1,'2025-11-08 12:12:00','2025-11-08 12:12:00',NULL),(11,'KH00011','Công ty Cổ phần Dịch vụ Trường Sơn','0924739066','khachhang11@demo.orangecrm.local','8939, Ấp Giao Tông, Xã Tú Danh, Huyện Tòng Vượng Yên\nTrà Vinh',NULL,'business',8,'active',2,2,NULL,'Khách hàng demo.',1,'2026-06-12 15:26:00','2026-06-12 15:26:00',NULL),(12,'KH00012','Công ty TNHH MTV Thương mại Tân Á','0945718800','khachhang12@demo.orangecrm.local','6592, Thôn Thịnh Tín Xuân, Phường Khổng Lĩnh Vinh, Quận Nhiệm\nĐiện Biên',NULL,'business',6,'vip',4,2,NULL,'Khách hàng demo.',1,'2026-01-06 14:01:00','2026-01-06 14:01:00',NULL),(13,'KH00013','Trưng Tuấn Hảo','0927052592','khachhang13@demo.orangecrm.local','920, Thôn Ma, Thôn Hồng Đình, Quận Sa\nĐiện Biên',NULL,'individual',3,'active',2,2,NULL,'Khách hàng demo.',1,'2026-05-12 14:59:00','2026-05-12 14:59:00',NULL),(14,'KH00014','Công ty TNHH MTV Thực phẩm Hưng Thịnh','0914873094','khachhang14@demo.orangecrm.local','84 Phố Huỳnh Nhật Tài, Phường 08, Quận Liễu Thục\nThanh Hóa',NULL,'business',5,'inactive',4,2,NULL,'Khách hàng demo.',1,'2026-09-18 10:53:00','2026-09-18 10:53:00',NULL),(15,'KH00015','Tập đoàn Logistics Thành Công','0922725920','khachhang15@demo.orangecrm.local','508, Ấp Thi Vương, Phường Phúc Tống, Huyện Bùi\nKon Tum',NULL,'business',7,'active',2,2,NULL,'Khách hàng demo.',1,'2026-06-03 14:53:00','2026-06-03 14:53:00',NULL),(16,'KH00016','Sử Xuyến Phượng','0910140404','khachhang16@demo.orangecrm.local','12 Phố Phước, Xã Luận Từ, Huyện Trà Hiếu\nHồ Chí Minh','Công ty TNHH Sản xuất Tân Á','individual',8,'active',4,2,NULL,'Khách hàng demo.',1,'2026-08-14 16:31:00','2026-08-14 16:31:00',NULL),(17,'KH00017','Công ty TNHH Dịch vụ Hưng Thịnh','0927713402','khachhang17@demo.orangecrm.local','1 Phố Chung, Phường Hợp Lô, Quận Thiều\nCao Bằng',NULL,'business',4,'vip',4,2,NULL,'Khách hàng demo.',1,'2026-01-10 13:36:00','2026-01-10 13:36:00',NULL),(18,'KH00018','Tập đoàn Thực phẩm Tân Á','0910300037','khachhang18@demo.orangecrm.local','8, Ấp Tùng Luật, Phường Thảo Ca, Quận Yên\nLong An',NULL,'business',4,'potential',3,2,NULL,'Khách hàng demo.',1,'2026-02-22 17:46:00','2026-02-22 17:46:00',NULL),(19,'KH00019','Trần Xuân Tiển','0985681066','khachhang19@demo.orangecrm.local','7002 Phố Khúc Phúc Thục, Phường Thu Đan, Huyện Đan Khuê\nCần Thơ',NULL,'individual',5,'inactive',4,2,NULL,'Khách hàng demo.',1,'2026-09-02 14:02:00','2026-09-02 14:02:00',NULL),(20,'KH00020','Công ty TNHH Dịch vụ Đại Dương','0958099409','khachhang20@demo.orangecrm.local','5020 Phố Lâm, Phường Sỹ, Huyện Phan\nBà Rịa - Vũng Tàu',NULL,'business',1,'active',4,2,NULL,'Khách hàng demo.',1,'2025-11-22 16:28:00','2025-11-22 16:28:00',NULL),(21,'KH00021','Công ty Cổ phần Công nghệ Đại Dương','0974902804','khachhang21@demo.orangecrm.local','912, Ấp Võ, Phường Kỳ, Huyện 3\nKiên Giang',NULL,'business',5,'active',3,2,NULL,'Khách hàng demo.',1,'2026-08-09 14:55:00','2026-08-09 14:55:00',NULL),(22,'KH00022','Doãn Như Giang','0962007634','khachhang22@demo.orangecrm.local','80 Phố Mạch Hiệp Mi, Phường Lều Thục Ca, Huyện 70\nHải Phòng','Công ty TNHH Dịch vụ Đại Dương','individual',9,'active',3,2,NULL,'Khách hàng demo.',1,'2025-11-25 14:27:00','2025-11-25 14:27:00',NULL),(23,'KH00023','Công ty Cổ phần Logistics Thành Công','0955216935','khachhang23@demo.orangecrm.local','7928 Phố Kha Nữ Hào, Ấp Vĩ Hồng, Quận Chiêm Huy Hoài\nHải Phòng',NULL,'business',1,'vip',3,2,NULL,'Khách hàng demo.',1,'2026-09-10 17:33:00','2026-09-10 17:33:00',NULL),(24,'KH00024','Công ty Cổ phần Xây dựng An Khang','0923412641','khachhang24@demo.orangecrm.local','4 Phố Hạnh, Xã Ngôn Ca, Huyện Kha\nHưng Yên',NULL,'business',4,'inactive',4,2,NULL,'Khách hàng demo.',1,'2026-09-01 08:39:00','2026-09-01 08:39:00',NULL),(25,'KH00025','Khuất Giang Nam','0979833082','khachhang25@demo.orangecrm.local','52 Phố Danh Cát Du, Phường 38, Quận Hợp\nBình Định',NULL,'individual',6,'inactive',3,2,NULL,'Khách hàng demo.',1,'2025-12-28 09:57:00','2025-12-28 09:57:00',NULL),(26,'KH00026','Công ty TNHH MTV Sản xuất Đại Dương','0959846276','khachhang26@demo.orangecrm.local','301 Phố Cổ Thạc Châu, Phường Phương, Huyện Bình Âu\nHải Phòng',NULL,'business',1,'vip',4,2,NULL,'Khách hàng demo.',1,'2025-11-25 14:44:00','2025-11-25 14:44:00',NULL),(27,'KH00027','Tập đoàn Sản xuất Hoàng Long','0917164802','khachhang27@demo.orangecrm.local','7957 Phố Ngạn, Ấp Thanh Tâm, Huyện Hồng\nVĩnh Long',NULL,'business',3,'vip',3,2,NULL,'Khách hàng demo.',1,'2026-04-13 17:42:00','2026-04-13 17:42:00',NULL),(28,'KH00028','Diệp Quế Hòa','0943173054','khachhang28@demo.orangecrm.local','57, Ấp Hạnh Quản, Phường Châu, Quận 4\nBến Tre','Công ty TNHH Công nghệ Bình Minh','individual',9,'potential',3,2,NULL,'Khách hàng demo.',1,'2026-06-30 15:43:00','2026-06-30 15:43:00',NULL),(29,'KH00029','Tập đoàn Xây dựng Bình Minh','0910414899','khachhang29@demo.orangecrm.local','237 Phố Đinh Hoa Lâm, Phường 90, Quận Dụng Viên\nVĩnh Long',NULL,'business',5,'potential',3,2,NULL,'Khách hàng demo.',1,'2026-01-27 08:41:00','2026-01-27 08:41:00',NULL),(30,'KH00030','Tập đoàn Sản xuất Đại Dương','0948497293','khachhang30@demo.orangecrm.local','599 Phố Dân, Xã Ty Thanh Quân, Huyện 02\nCần Thơ',NULL,'business',2,'active',2,2,NULL,'Khách hàng demo.',1,'2026-08-17 13:37:00','2026-08-17 13:37:00',NULL);

--
-- Table structure for table `departments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES (1,'Ban Giám đốc','BGD',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(2,'Phòng Kinh doanh','KD',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(3,'Phòng Marketing','MKT',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(4,'Phòng Chăm sóc khách hàng','CSKH',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(5,'Phòng Kế toán','KT',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48');

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `interactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `interactable_type` varchar(255) NOT NULL,
  `interactable_id` bigint(20) unsigned NOT NULL,
  `type` enum('note','call','email','meeting','other') NOT NULL DEFAULT 'note',
  `subject` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `interactions_interactable_type_interactable_id_index` (`interactable_type`,`interactable_id`),
  KEY `interactions_user_id_foreign` (`user_id`),
  CONSTRAINT `interactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interactions`
--

INSERT INTO `interactions` (`id`, `interactable_type`, `interactable_id`, `type`, `subject`, `content`, `occurred_at`, `user_id`, `created_at`, `updated_at`) VALUES (1,'customer',1,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-03-15 08:19:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(2,'customer',2,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-15 15:12:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(3,'customer',3,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-03 17:16:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(4,'customer',4,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-17 17:39:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(5,'customer',5,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-10 08:05:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(6,'customer',6,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-15 12:37:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(7,'customer',7,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-07-10 13:35:00',3,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(8,'customer',8,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-03-09 08:26:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(9,'customer',9,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-10 09:50:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(10,'customer',10,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-12 12:12:00',3,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(11,'customer',11,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-14 15:26:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(12,'customer',12,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-07 14:01:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(13,'customer',13,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-13 14:59:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(14,'customer',14,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-22 10:53:00',4,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(15,'customer',15,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-08 14:53:00',2,'2026-10-14 20:00:50','2026-10-14 20:00:50'),(16,'customer',16,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-19 16:31:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(17,'customer',17,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-12 13:36:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(18,'customer',18,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-02-26 17:46:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(19,'customer',19,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-05 14:02:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(20,'customer',20,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-27 16:28:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(21,'customer',21,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-14 14:55:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(22,'customer',22,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-28 14:27:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(23,'customer',23,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-15 17:33:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(24,'customer',24,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-03 08:39:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(25,'customer',25,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-12-29 09:57:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(26,'customer',26,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-25 14:44:00',4,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(27,'customer',27,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-04-14 17:42:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(28,'customer',28,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-07-03 15:43:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(29,'customer',29,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-30 08:41:00',3,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(30,'customer',30,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-20 13:37:00',2,'2026-10-14 20:00:51','2026-10-14 20:00:51');

--
-- Table structure for table `invoice_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `invoice_items_product_id_foreign` (`product_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(2,1,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(3,2,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,10.00,9720000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(4,3,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,4,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,0.00,38000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,5,3,'Phần mềm ERP Lite',2.00,45000000.00,10.00,81000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,6,4,'Dịch vụ triển khai',2.00,15000000.00,5.00,28500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,6,8,'Đào tạo người dùng',1.00,5000000.00,5.00,4750000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,6,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,0.00,10800000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(12,8,3,'Phần mềm ERP Lite',1.00,45000000.00,0.00,45000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `invoices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(30) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `sales_order_id` bigint(20) unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_number_unique` (`number`),
  KEY `invoices_customer_id_foreign` (`customer_id`),
  KEY `invoices_sales_order_id_foreign` (`sales_order_id`),
  KEY `invoices_created_by_foreign` (`created_by`),
  KEY `invoices_assigned_to_foreign` (`assigned_to`),
  KEY `invoices_issue_date_index` (`issue_date`),
  KEY `invoices_status_index` (`status`),
  KEY `invoices_payment_status_index` (`payment_status`),
  KEY `invoices_is_demo_index` (`is_demo`),
  CONSTRAINT `invoices_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `number`, `customer_id`, `sales_order_id`, `issue_date`, `due_date`, `status`, `payment_status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `amount_paid`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'HD00001',23,1,'2026-08-29','2026-09-13','issued','partial',74700000.00,0.00,10.00,7470000.00,82170000.00,41085000.00,NULL,3,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(2,'HD00002',10,2,'2026-05-05','2026-05-20','issued','paid',9720000.00,0.00,10.00,972000.00,10692000.00,10692000.00,NULL,3,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(3,'HD00003',27,3,'2026-03-10','2026-03-25','issued','paid',40500000.00,0.00,10.00,4050000.00,44550000.00,44550000.00,NULL,3,3,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(4,'HD00004',11,4,'2026-09-18','2026-10-03','issued','partial',38000000.00,0.00,10.00,3800000.00,41800000.00,20900000.00,NULL,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(5,'HD00005',26,5,'2026-04-19','2026-05-04','issued','paid',81000000.00,0.00,10.00,8100000.00,89100000.00,89100000.00,NULL,4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(6,'HD00006',14,6,'2025-12-24','2026-01-08','issued','paid',44050000.00,0.00,10.00,4405000.00,48455000.00,48455000.00,NULL,4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(7,'HD00007',20,7,'2026-05-16','2026-05-31','issued','unpaid',64200000.00,0.00,10.00,6420000.00,70620000.00,0.00,NULL,4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(8,'HD00008',23,8,'2026-04-15','2026-04-30','issued','unpaid',45000000.00,0.00,10.00,4500000.00,49500000.00,0.00,NULL,3,3,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL);

--
-- Table structure for table `job_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_sources`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `lead_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_sources_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_sources`
--

INSERT INTO `lead_sources` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (1,'Website',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(2,'Facebook',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(3,'Zalo',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(4,'Google Ads',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(5,'Giới thiệu',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(6,'Sự kiện',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(7,'Gọi điện',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(8,'Email',1,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(9,'Khác',1,'2026-10-14 20:00:48','2026-10-14 20:00:48');

--
-- Table structure for table `leads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `lead_source_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('new','contacted','qualified','proposal','converted','lost') NOT NULL DEFAULT 'new',
  `score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `converted_customer_id` bigint(20) unsigned DEFAULT NULL,
  `converted_opportunity_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_code_unique` (`code`),
  KEY `leads_lead_source_id_foreign` (`lead_source_id`),
  KEY `leads_assigned_to_foreign` (`assigned_to`),
  KEY `leads_created_by_foreign` (`created_by`),
  KEY `leads_converted_customer_id_foreign` (`converted_customer_id`),
  KEY `leads_created_at_index` (`created_at`),
  KEY `leads_email_index` (`email`),
  KEY `leads_phone_index` (`phone`),
  KEY `leads_status_index` (`status`),
  KEY `leads_is_demo_index` (`is_demo`),
  KEY `leads_converted_opportunity_id_foreign` (`converted_opportunity_id`),
  CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_customer_id_foreign` FOREIGN KEY (`converted_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_opportunity_id_foreign` FOREIGN KEY (`converted_opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `code`, `name`, `company`, `email`, `phone`, `lead_source_id`, `status`, `score`, `estimated_value`, `assigned_to`, `created_by`, `converted_customer_id`, `converted_opportunity_id`, `converted_at`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'LD00001','Trác Vy Lai','Công ty TNHH Logistics Việt Tiến','lead1@demo.orangecrm.local','0329532340',3,'new',83,114000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-13 14:24:00','2026-02-13 14:24:00',NULL),(2,'LD00002','Nhữ Lạc Thương','Công ty TNHH MTV Thương mại Đại Dương','lead2@demo.orangecrm.local','0343543332',3,'new',73,6000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-25 12:03:00','2026-04-25 12:03:00',NULL),(3,'LD00003','Bì Hà Uyển','Công ty TNHH MTV Dịch vụ Việt Tiến','lead3@demo.orangecrm.local','0388777634',9,'contacted',77,66000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-06 11:08:00','2026-09-06 11:08:00',NULL),(4,'LD00004','Uông Hằng Giáp','Công ty Cổ phần Công nghệ Hoàng Long','lead4@demo.orangecrm.local','0344880316',7,'contacted',53,29000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-26 15:18:00','2026-01-26 15:18:00',NULL),(5,'LD00005','Trưng Diệu Luận','Công ty TNHH MTV Sản xuất Đại Dương','lead5@demo.orangecrm.local','0379647561',4,'qualified',64,91000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-25 12:07:00','2026-07-25 12:07:00',NULL),(6,'LD00006','Tiêu Thịnh Lễ','Tập đoàn Xây dựng Hoàng Long','lead6@demo.orangecrm.local','0358976027',8,'proposal',33,27000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-03 11:19:00','2026-07-03 11:19:00',NULL),(7,'LD00007','Ma Nhã Mỹ','Công ty Cổ phần Thương mại Việt Tiến','lead7@demo.orangecrm.local','0367143741',1,'converted',13,106000000.00,2,5,19,NULL,'2026-04-13 17:27:00','Lead demo.',1,'2026-04-10 17:27:00','2026-04-10 17:27:00',NULL),(8,'LD00008','Diêm Sương Thiện','Công ty TNHH MTV Logistics Hòa Bình','lead8@demo.orangecrm.local','0373545167',2,'lost',75,71000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-16 11:39:00','2026-04-16 11:39:00',NULL),(9,'LD00009','Mạc Trúc Phong','Tập đoàn Công nghệ An Khang','lead9@demo.orangecrm.local','0359110732',9,'new',73,60000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-22 13:58:00','2026-02-22 13:58:00',NULL),(10,'LD00010','Thân Trung Nhi','Công ty TNHH MTV Sản xuất Bình Minh','lead10@demo.orangecrm.local','0364899923',1,'new',33,43000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-08 10:36:00','2026-07-08 10:36:00',NULL),(11,'LD00011','Thạch Dân Thúy','Tập đoàn Thương mại Hòa Bình','lead11@demo.orangecrm.local','0395289835',9,'contacted',45,115000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-17 17:54:00','2026-01-17 17:54:00',NULL),(12,'LD00012','Cự Khuyên Huấn','Công ty TNHH MTV Logistics Hoàng Long','lead12@demo.orangecrm.local','0362076308',6,'contacted',63,103000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2025-12-09 13:53:00','2025-12-09 13:53:00',NULL),(13,'LD00013','Bạc Huyền Khanh','Công ty TNHH Thực phẩm Phú Thịnh','lead13@demo.orangecrm.local','0349581250',2,'qualified',87,83000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-19 17:16:00','2026-09-19 17:16:00',NULL),(14,'LD00014','Thôi Tú Nhiên','Công ty TNHH Công nghệ Hòa Bình','lead14@demo.orangecrm.local','0362531211',5,'proposal',78,7000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-25 12:30:00','2026-08-25 12:30:00',NULL),(15,'LD00015','Lữ Tuyết Bình','Công ty TNHH Logistics Việt Tiến','lead15@demo.orangecrm.local','0338332101',2,'converted',84,103000000.00,2,5,18,NULL,'2026-05-08 11:46:00','Lead demo.',1,'2026-05-02 11:46:00','2026-05-02 11:46:00',NULL),(16,'LD00016','Hy Ca Thông','Tập đoàn Logistics Kim Ngân','lead16@demo.orangecrm.local','0398568291',4,'lost',34,118000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-21 15:03:00','2026-08-21 15:03:00',NULL),(17,'LD00017','Đới Diệu Du','Công ty Cổ phần Logistics Đại Dương','lead17@demo.orangecrm.local','0341190811',6,'new',67,114000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-12 17:47:00','2026-09-12 17:47:00',NULL),(18,'LD00018','Hình Hồng Sương','Công ty TNHH Logistics Nam Việt','lead18@demo.orangecrm.local','0313372341',7,'new',71,44000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-15 15:22:00','2026-02-15 15:22:00',NULL),(19,'LD00019','Bàng Lâm Ngôn','Công ty TNHH MTV Công nghệ Hòa Bình','lead19@demo.orangecrm.local','0327090968',2,'contacted',35,91000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-21 13:22:00','2026-07-21 13:22:00',NULL),(20,'LD00020','Đái Đan Tùng','Công ty TNHH MTV Logistics Đại Dương','lead20@demo.orangecrm.local','0338313952',6,'contacted',10,22000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-21 10:18:00','2026-09-21 10:18:00',NULL),(21,'LD00021','Đinh Tuệ Nguyệt','Tập đoàn Công nghệ Trường Sơn','lead21@demo.orangecrm.local','0391701988',6,'qualified',45,82000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-06-25 10:54:00','2026-06-25 10:54:00',NULL),(22,'LD00022','Diệp Lâm Hoài','Công ty TNHH MTV Xây dựng Trường Sơn','lead22@demo.orangecrm.local','0371593183',3,'proposal',21,82000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-14 09:24:00','2026-04-14 09:24:00',NULL),(23,'LD00023','Trác Vỹ Hải','Công ty TNHH Công nghệ An Khang','lead23@demo.orangecrm.local','0335536034',2,'converted',58,102000000.00,4,5,20,NULL,'2026-03-02 09:56:00','Lead demo.',1,'2026-02-18 09:56:00','2026-02-18 09:56:00',NULL),(24,'LD00024','Phùng Giáp Trang','Công ty TNHH MTV Thực phẩm Kim Ngân','lead24@demo.orangecrm.local','0324921292',8,'lost',26,57000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-27 14:30:00','2026-08-27 14:30:00',NULL),(25,'LD00025','Liễu Ân Mạnh','Công ty Cổ phần Sản xuất Nam Việt','lead25@demo.orangecrm.local','0335863999',4,'new',53,63000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-04 08:19:00','2026-08-04 08:19:00',NULL),(26,'LD00026','Sử Giang Chinh','Tập đoàn Sản xuất Đại Dương','lead26@demo.orangecrm.local','0371198710',8,'new',26,81000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-03-20 09:46:00','2026-03-20 09:46:00',NULL),(27,'LD00027','Hoa Giáp Liên','Công ty Cổ phần Thương mại Tân Á','lead27@demo.orangecrm.local','0381713341',5,'contacted',27,29000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-12 13:45:00','2026-07-12 13:45:00',NULL),(28,'LD00028','Đậu Đan Tuyền','Công ty Cổ phần Sản xuất Minh Phát','lead28@demo.orangecrm.local','0338666907',3,'contacted',16,40000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-16 15:36:00','2026-01-16 15:36:00',NULL),(29,'LD00029','Tông Thiên Sĩ','Tập đoàn Xây dựng Trường Sơn','lead29@demo.orangecrm.local','0331454326',4,'qualified',46,85000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-16 12:36:00','2026-07-16 12:36:00',NULL),(30,'LD00030','Chương Hoài Duy','Công ty Cổ phần Công nghệ Nam Việt','lead30@demo.orangecrm.local','0348329614',3,'proposal',60,40000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2025-12-20 17:49:00','2025-12-20 17:49:00',NULL);

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_13_161955_create_permission_tables',1),(5,'2026_10_13_170000_create_crm_core_tables',1),(6,'2026_10_13_170100_create_sales_tables',1),(7,'2026_10_13_170200_create_work_tables',1),(8,'2026_10_14_090000_add_username_to_users_table',1);

--
-- Table structure for table `model_has_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--


--
-- Table structure for table `model_has_roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES (2,'user',1),(3,'user',2),(4,'user',3),(4,'user',4),(5,'user',5),(6,'user',6),(7,'user',7);

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `opportunities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `opportunities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `title` varchar(255) NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `pipeline_stage_id` bigint(20) unsigned NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `probability` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expected_close_date` date DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(500) DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `opportunities_code_unique` (`code`),
  KEY `opportunities_customer_id_foreign` (`customer_id`),
  KEY `opportunities_contact_id_foreign` (`contact_id`),
  KEY `opportunities_lead_id_foreign` (`lead_id`),
  KEY `opportunities_assigned_to_foreign` (`assigned_to`),
  KEY `opportunities_created_by_foreign` (`created_by`),
  KEY `opportunities_pipeline_stage_id_position_index` (`pipeline_stage_id`,`position`),
  KEY `opportunities_is_demo_index` (`is_demo`),
  CONSTRAINT `opportunities_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opportunities`
--

INSERT INTO `opportunities` (`id`, `code`, `title`, `customer_id`, `contact_id`, `lead_id`, `pipeline_stage_id`, `value`, `probability`, `expected_close_date`, `closed_at`, `lost_reason`, `position`, `assigned_to`, `created_by`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'CH00001','Đào tạo nhân viên - Tập đoàn Sản xuất Đại Dương',30,30,NULL,1,226000000.00,10,'2026-12-11',NULL,NULL,0,2,2,'Cơ hội demo.',1,'2026-06-20 20:00:51','2026-06-20 20:00:51',NULL),(2,'CH00002','Nâng cấp ERP - Ông Hoan Hòa',1,1,NULL,2,54000000.00,25,'2026-12-27',NULL,NULL,1,4,2,'Cơ hội demo.',1,'2026-07-04 20:00:51','2026-07-04 20:00:51',NULL),(3,'CH00003','Nâng cấp ERP - Công ty Cổ phần Xây dựng An Kh',24,24,NULL,3,214000000.00,50,'2026-12-07',NULL,NULL,2,4,2,'Cơ hội demo.',1,'2026-03-10 20:00:51','2026-03-10 20:00:51',NULL),(4,'CH00004','Mua máy chủ - Trưng Tuấn Hảo',13,13,NULL,4,127000000.00,75,'2026-10-28',NULL,NULL,3,2,2,'Cơ hội demo.',1,'2026-03-31 20:00:51','2026-03-31 20:00:51',NULL),(5,'CH00005','Triển khai CRM - Công ty TNHH MTV Thương mại Vi',9,9,NULL,5,48000000.00,100,'2026-10-03','2026-06-13 20:00:51',NULL,4,2,2,'Cơ hội demo.',1,'2026-06-02 20:00:51','2026-06-02 20:00:51',NULL),(6,'CH00006','Triển khai CRM - Công ty Cổ phần Logistics Trườ',2,2,NULL,6,55000000.00,0,'2026-12-11','2026-01-20 20:00:51','Khách chọn nhà cung cấp khác (demo)',5,4,2,'Cơ hội demo.',1,'2025-12-19 20:00:51','2025-12-19 20:00:51',NULL),(7,'CH00007','Triển khai CRM - Mang Uyển Quân',7,7,NULL,1,27000000.00,10,'2026-09-17',NULL,NULL,6,3,2,'Cơ hội demo.',1,'2026-04-27 20:00:51','2026-04-27 20:00:51',NULL),(8,'CH00008','Mua máy chủ - Công ty TNHH MTV Thương mại Tâ',12,12,NULL,2,13000000.00,25,'2026-12-31',NULL,NULL,7,4,2,'Cơ hội demo.',1,'2026-04-19 20:00:51','2026-04-19 20:00:51',NULL),(9,'CH00009','Đào tạo nhân viên - Công ty Cổ phần Thương mại Hòa',6,6,NULL,3,244000000.00,50,'2027-01-07',NULL,NULL,8,4,2,'Cơ hội demo.',1,'2026-06-27 20:00:51','2026-06-27 20:00:51',NULL),(10,'CH00010','Đào tạo nhân viên - Công ty Cổ phần Công nghệ Đại',21,21,NULL,4,129000000.00,75,'2026-10-27',NULL,NULL,9,3,2,'Cơ hội demo.',1,'2026-02-26 20:00:51','2026-02-26 20:00:51',NULL),(11,'CH00011','Trang bị POS - Ông Hoan Hòa',1,1,NULL,5,271000000.00,100,'2026-11-07','2026-06-14 20:00:51',NULL,10,4,2,'Cơ hội demo.',1,'2026-06-04 20:00:51','2026-06-04 20:00:51',NULL),(12,'CH00012','Gói hỗ trợ năm - Công ty Cổ phần Logistics Thàn',23,23,NULL,6,121000000.00,0,'2026-10-20','2026-08-17 20:00:51','Khách chọn nhà cung cấp khác (demo)',11,3,2,'Cơ hội demo.',1,'2026-07-24 20:00:51','2026-07-24 20:00:51',NULL),(13,'CH00013','Mua máy chủ - Tập đoàn Thực phẩm Nam Việt',5,5,NULL,1,130000000.00,10,'2026-10-10',NULL,NULL,12,2,2,'Cơ hội demo.',1,'2026-01-30 20:00:51','2026-01-30 20:00:51',NULL),(14,'CH00014','Gói hỗ trợ năm - Công ty Cổ phần Logistics Thàn',23,23,NULL,2,90000000.00,25,'2026-09-23',NULL,NULL,13,3,2,'Cơ hội demo.',1,'2026-07-25 20:00:51','2026-07-25 20:00:51',NULL),(15,'CH00015','Triển khai CRM - Tập đoàn Sản xuất Đại Dương',30,30,NULL,3,288000000.00,50,'2026-12-10',NULL,NULL,14,2,2,'Cơ hội demo.',1,'2026-03-13 20:00:51','2026-03-13 20:00:51',NULL);

--
-- Table structure for table `opportunity_stage_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `opportunity_stage_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint(20) unsigned NOT NULL,
  `from_stage_id` bigint(20) unsigned DEFAULT NULL,
  `to_stage_id` bigint(20) unsigned NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opportunity_stage_histories_opportunity_id_foreign` (`opportunity_id`),
  KEY `opportunity_stage_histories_from_stage_id_foreign` (`from_stage_id`),
  KEY `opportunity_stage_histories_to_stage_id_foreign` (`to_stage_id`),
  KEY `opportunity_stage_histories_changed_by_foreign` (`changed_by`),
  CONSTRAINT `opportunity_stage_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunity_stage_histories_from_stage_id_foreign` FOREIGN KEY (`from_stage_id`) REFERENCES `pipeline_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunity_stage_histories_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opportunity_stage_histories_to_stage_id_foreign` FOREIGN KEY (`to_stage_id`) REFERENCES `pipeline_stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opportunity_stage_histories`
--

INSERT INTO `opportunity_stage_histories` (`id`, `opportunity_id`, `from_stage_id`, `to_stage_id`, `changed_by`, `created_at`) VALUES (1,1,NULL,1,2,'2026-06-20 20:00:51'),(2,2,NULL,2,2,'2026-07-04 20:00:51'),(3,3,NULL,3,2,'2026-03-10 20:00:51'),(4,4,NULL,4,2,'2026-03-31 20:00:51'),(5,5,NULL,5,2,'2026-06-02 20:00:51'),(6,6,NULL,6,2,'2025-12-19 20:00:51'),(7,7,NULL,1,2,'2026-04-27 20:00:51'),(8,8,NULL,2,2,'2026-04-19 20:00:51'),(9,9,NULL,3,2,'2026-06-27 20:00:51'),(10,10,NULL,4,2,'2026-02-26 20:00:51'),(11,11,NULL,5,2,'2026-06-04 20:00:51'),(12,12,NULL,6,2,'2026-07-24 20:00:51'),(13,13,NULL,1,2,'2026-01-30 20:00:51'),(14,14,NULL,2,2,'2026-07-25 20:00:51'),(15,15,NULL,3,2,'2026-03-13 20:00:51');

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('cash','bank_transfer','card','other') NOT NULL DEFAULT 'bank_transfer',
  `reference` varchar(100) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_recorded_by_foreign` (`recorded_by`),
  KEY `payments_payment_date_index` (`payment_date`),
  KEY `payments_is_demo_index` (`is_demo`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `amount`, `payment_date`, `method`, `reference`, `notes`, `recorded_by`, `is_demo`, `created_at`, `updated_at`) VALUES (1,1,41085000.00,'2026-09-03','bank_transfer','DEMO-1',NULL,2,1,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(2,2,10692000.00,'2026-05-06','bank_transfer','DEMO-2',NULL,2,1,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(3,3,44550000.00,'2026-03-22','bank_transfer','DEMO-3',NULL,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(4,4,20900000.00,'2026-09-29','bank_transfer','DEMO-4',NULL,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,5,89100000.00,'2026-04-25','bank_transfer','DEMO-5',NULL,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,6,48455000.00,'2026-01-05','bank_transfer','DEMO-6',NULL,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'dashboard.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(2,'customers.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(3,'customers.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(4,'customers.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(5,'customers.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(6,'customers.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(7,'customers.export','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(8,'customers.assign','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(9,'contacts.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(10,'contacts.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(11,'contacts.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(12,'contacts.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(13,'contacts.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(14,'contacts.export','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(15,'leads.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(16,'leads.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(17,'leads.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(18,'leads.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(19,'leads.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(20,'leads.export','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(21,'leads.assign','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(22,'opportunities.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(23,'opportunities.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(24,'opportunities.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(25,'opportunities.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(26,'opportunities.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(27,'opportunities.export','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(28,'opportunities.assign','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(29,'tasks.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(30,'tasks.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(31,'tasks.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(32,'tasks.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(33,'tasks.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(34,'tasks.assign','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(35,'products.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(36,'products.create','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(37,'products.update','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(38,'products.delete','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(39,'products.export','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(40,'quotations.view','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(41,'quotations.view_all','web','2026-10-14 20:00:47','2026-10-14 20:00:47'),(42,'quotations.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(43,'quotations.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(44,'quotations.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(45,'quotations.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(46,'quotations.approve','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(47,'orders.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(48,'orders.view_all','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(49,'orders.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(50,'orders.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(51,'orders.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(52,'orders.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(53,'orders.approve','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(54,'invoices.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(55,'invoices.view_all','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(56,'invoices.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(57,'invoices.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(58,'invoices.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(59,'invoices.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(60,'campaigns.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(61,'campaigns.view_all','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(62,'campaigns.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(63,'campaigns.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(64,'campaigns.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(65,'campaigns.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(66,'tickets.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(67,'tickets.view_all','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(68,'tickets.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(69,'tickets.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(70,'tickets.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(71,'tickets.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(72,'tickets.assign','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(73,'reports.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(74,'reports.export','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(75,'users.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(76,'users.create','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(77,'users.update','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(78,'users.delete','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(79,'users.manage','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(80,'activity_logs.view','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(81,'settings.manage','web','2026-10-14 20:00:48','2026-10-14 20:00:48');

--
-- Table structure for table `pipeline_stages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `pipeline_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `probability` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `color` varchar(20) NOT NULL DEFAULT '#6B7280',
  `is_won` tinyint(1) NOT NULL DEFAULT 0,
  `is_lost` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pipeline_stages_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pipeline_stages`
--

INSERT INTO `pipeline_stages` (`id`, `name`, `slug`, `position`, `probability`, `color`, `is_won`, `is_lost`, `created_at`, `updated_at`) VALUES (1,'Cơ hội mới','new',1,10,'#6B7280',0,0,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(2,'Đánh giá nhu cầu','qualification',2,25,'#3B82F6',0,0,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(3,'Gửi đề xuất','proposal',3,50,'#F59E0B',0,0,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(4,'Đàm phán','negotiation',4,75,'#FF6B00',0,0,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(5,'Thắng','won',5,100,'#16A34A',1,0,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(6,'Thất bại','lost',6,0,'#DC2626',0,1,'2026-10-14 20:00:48','2026-10-14 20:00:48');

--
-- Table structure for table `product_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,'Phần mềm',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(2,'Dịch vụ',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(3,'Phần cứng',NULL,'2026-10-14 20:00:48','2026-10-14 20:00:48'),(4,'Đào tạo',NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49'),(5,'Gói hỗ trợ',NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49');

--
-- Table structure for table `products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `product_category_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(30) NOT NULL DEFAULT 'cái',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `image` varchar(255) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_product_category_id_foreign` (`product_category_id`),
  KEY `products_status_index` (`status`),
  KEY `products_is_demo_index` (`is_demo`),
  CONSTRAINT `products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `product_category_id`, `description`, `price`, `unit`, `status`, `image`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'SW-CRM-STD','Phần mềm CRM - Gói Tiêu chuẩn',1,'Sản phẩm demo: Phần mềm CRM - Gói Tiêu chuẩn',12000000.00,'gói','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(2,'SW-CRM-PRO','Phần mềm CRM - Gói Chuyên nghiệp',1,'Sản phẩm demo: Phần mềm CRM - Gói Chuyên nghiệp',25000000.00,'gói','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(3,'SW-ERP-LITE','Phần mềm ERP Lite',1,'Sản phẩm demo: Phần mềm ERP Lite',45000000.00,'gói','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(4,'SV-IMPL','Dịch vụ triển khai',2,'Sản phẩm demo: Dịch vụ triển khai',15000000.00,'dự án','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(5,'SV-CUSTOM','Phát triển tính năng theo yêu cầu',2,'Sản phẩm demo: Phát triển tính năng theo yêu cầu',800000.00,'giờ','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(6,'HW-SERVER','Máy chủ Dell PowerEdge T150',3,'Sản phẩm demo: Máy chủ Dell PowerEdge T150',38000000.00,'cái','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(7,'HW-POS','Máy bán hàng POS',3,'Sản phẩm demo: Máy bán hàng POS',9500000.00,'cái','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(8,'TR-USER','Đào tạo người dùng',4,'Sản phẩm demo: Đào tạo người dùng',5000000.00,'buổi','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(9,'SP-YEAR','Gói hỗ trợ kỹ thuật 12 tháng',5,'Sản phẩm demo: Gói hỗ trợ kỹ thuật 12 tháng',18000000.00,'năm','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(10,'SP-CLOUD','Lưu trữ đám mây 1TB',5,'Sản phẩm demo: Lưu trữ đám mây 1TB',3600000.00,'năm','active',NULL,1,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL);

--
-- Table structure for table `quotation_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_items_quotation_id_foreign` (`quotation_id`),
  KEY `quotation_items_product_id_foreign` (`product_id`),
  CONSTRAINT `quotation_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotation_items_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(2,1,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(3,2,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,10.00,9720000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(4,3,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,4,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,0.00,38000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,5,3,'Phần mềm ERP Lite',2.00,45000000.00,10.00,81000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,6,4,'Dịch vụ triển khai',2.00,15000000.00,5.00,28500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,6,8,'Đào tạo người dùng',1.00,5000000.00,5.00,4750000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,6,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,0.00,10800000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(12,8,3,'Phần mềm ERP Lite',1.00,45000000.00,0.00,45000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(13,9,2,'Phần mềm CRM - Gói Chuyên nghiệp',1.00,25000000.00,0.00,25000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(14,9,4,'Dịch vụ triển khai',1.00,15000000.00,0.00,15000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(15,10,6,'Máy chủ Dell PowerEdge T150',2.00,38000000.00,10.00,68400000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(16,10,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(17,11,8,'Đào tạo người dùng',1.00,5000000.00,5.00,4750000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(18,11,10,'Lưu trữ đám mây 1TB',1.00,3600000.00,0.00,3600000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(19,12,3,'Phần mềm ERP Lite',1.00,45000000.00,5.00,42750000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(20,12,7,'Máy bán hàng POS',2.00,9500000.00,5.00,18050000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(21,12,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,5.00,10260000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `quotations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(30) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `opportunity_id` bigint(20) unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotations_number_unique` (`number`),
  KEY `quotations_customer_id_foreign` (`customer_id`),
  KEY `quotations_opportunity_id_foreign` (`opportunity_id`),
  KEY `quotations_created_by_foreign` (`created_by`),
  KEY `quotations_assigned_to_foreign` (`assigned_to`),
  KEY `quotations_status_index` (`status`),
  KEY `quotations_is_demo_index` (`is_demo`),
  CONSTRAINT `quotations_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `quotations_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `number`, `customer_id`, `opportunity_id`, `issue_date`, `valid_until`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'BG00001',23,NULL,'2026-08-26','2026-09-25','accepted',74700000.00,0.00,10.00,7470000.00,82170000.00,'Báo giá demo.',3,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(2,'BG00002',10,NULL,'2026-05-02','2026-06-01','accepted',9720000.00,0.00,10.00,972000.00,10692000.00,'Báo giá demo.',3,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(3,'BG00003',27,NULL,'2026-03-07','2026-04-06','accepted',40500000.00,0.00,10.00,4050000.00,44550000.00,'Báo giá demo.',3,3,1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(4,'BG00004',11,NULL,'2026-09-15','2026-10-15','accepted',38000000.00,0.00,10.00,3800000.00,41800000.00,'Báo giá demo.',2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(5,'BG00005',26,NULL,'2026-04-16','2026-05-16','accepted',81000000.00,0.00,10.00,8100000.00,89100000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(6,'BG00006',14,NULL,'2025-12-21','2026-01-20','accepted',44050000.00,0.00,10.00,4405000.00,48455000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(7,'BG00007',20,NULL,'2026-05-13','2026-06-12','accepted',64200000.00,0.00,10.00,6420000.00,70620000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(8,'BG00008',23,NULL,'2026-04-12','2026-05-12','accepted',45000000.00,0.00,10.00,4500000.00,49500000.00,'Báo giá demo.',3,3,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(9,'BG00009',20,NULL,'2026-06-04','2026-07-04','accepted',40000000.00,0.00,10.00,4000000.00,44000000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(10,'BG00010',3,NULL,'2025-11-08','2025-12-08','rejected',76950000.00,0.00,10.00,7695000.00,84645000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(11,'BG00011',3,NULL,'2026-07-10','2026-08-09','sent',8350000.00,0.00,10.00,835000.00,9185000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(12,'BG00012',24,NULL,'2026-01-13','2026-02-12','rejected',71060000.00,0.00,10.00,7106000.00,78166000.00,'Báo giá demo.',4,4,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL);

--
-- Table structure for table `role_has_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(3,1),(3,2),(3,3),(3,5),(3,6),(3,7),(4,1),(4,2),(4,3),(4,4),(5,1),(5,2),(5,3),(5,4),(5,6),(6,1),(6,2),(6,3),(7,1),(7,2),(7,3),(7,5),(8,1),(8,2),(8,3),(9,1),(9,2),(9,3),(9,4),(9,5),(9,6),(9,7),(10,1),(10,2),(10,3),(10,5),(10,6),(10,7),(11,1),(11,2),(11,3),(11,4),(11,6),(12,1),(12,2),(12,3),(12,4),(12,6),(13,1),(13,2),(13,3),(14,1),(14,2),(14,3),(15,1),(15,2),(15,3),(15,4),(15,5),(15,7),(16,1),(16,2),(16,3),(16,5),(16,7),(17,1),(17,2),(17,3),(17,4),(17,5),(18,1),(18,2),(18,3),(18,4),(18,5),(19,1),(19,2),(19,3),(20,1),(20,2),(20,3),(20,5),(21,1),(21,2),(21,3),(21,5),(22,1),(22,2),(22,3),(22,4),(22,7),(23,1),(23,2),(23,3),(23,7),(24,1),(24,2),(24,3),(24,4),(25,1),(25,2),(25,3),(25,4),(26,1),(26,2),(26,3),(27,1),(27,2),(27,3),(28,1),(28,2),(28,3),(29,1),(29,2),(29,3),(29,4),(29,5),(29,6),(29,7),(30,1),(30,2),(30,3),(30,7),(31,1),(31,2),(31,3),(31,4),(31,5),(31,6),(32,1),(32,2),(32,3),(32,4),(32,5),(32,6),(33,1),(33,2),(33,3),(34,1),(34,2),(34,3),(35,1),(35,2),(35,3),(35,4),(35,5),(35,6),(35,7),(36,1),(36,2),(37,1),(37,2),(38,1),(38,2),(39,1),(39,2),(39,3),(40,1),(40,2),(40,3),(40,4),(40,7),(41,1),(41,2),(41,3),(41,7),(42,1),(42,2),(42,3),(42,4),(43,1),(43,2),(43,3),(43,4),(44,1),(44,2),(44,3),(45,1),(45,2),(45,3),(46,1),(46,2),(46,3),(47,1),(47,2),(47,3),(47,4),(47,7),(48,1),(48,2),(48,3),(48,7),(49,1),(49,2),(49,3),(49,4),(50,1),(50,2),(50,3),(50,4),(51,1),(51,2),(51,3),(52,1),(52,2),(52,3),(53,1),(53,2),(53,3),(54,1),(54,2),(54,3),(54,4),(54,7),(55,1),(55,2),(55,3),(55,7),(56,1),(56,2),(56,3),(57,1),(57,2),(57,3),(58,1),(58,2),(58,3),(59,1),(59,2),(59,3),(60,1),(60,2),(60,3),(60,5),(60,7),(61,1),(61,2),(61,3),(61,5),(61,7),(62,1),(62,2),(62,5),(63,1),(63,2),(63,5),(64,1),(64,2),(64,5),(65,1),(65,2),(65,5),(66,1),(66,2),(66,3),(66,4),(66,6),(66,7),(67,1),(67,2),(67,3),(67,6),(67,7),(68,1),(68,2),(68,4),(68,6),(69,1),(69,2),(69,6),(70,1),(70,2),(70,6),(71,1),(71,2),(71,6),(72,1),(72,2),(72,6),(73,1),(73,2),(73,3),(73,5),(73,6),(73,7),(74,1),(74,2),(74,3),(75,1),(75,2),(75,3),(76,1),(76,2),(77,1),(77,2),(78,1),(78,2),(79,1),(79,2),(80,1),(80,2),(81,1),(81,2);

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'Super Admin','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(2,'Administrator','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(3,'Sales Manager','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(4,'Sales Staff','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(5,'Marketing Staff','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(6,'Customer Support','web','2026-10-14 20:00:48','2026-10-14 20:00:48'),(7,'Viewer','web','2026-10-14 20:00:48','2026-10-14 20:00:48');

--
-- Table structure for table `sales_order_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sales_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_items_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `sales_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_order_items_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_items`
--

INSERT INTO `sales_order_items` (`id`, `sales_order_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(2,1,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(3,2,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,10.00,9720000.00,'2026-10-14 20:00:51','2026-10-14 20:00:51'),(4,3,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,4,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,0.00,38000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,5,3,'Phần mềm ERP Lite',2.00,45000000.00,10.00,81000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,6,4,'Dịch vụ triển khai',2.00,15000000.00,5.00,28500000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,6,8,'Đào tạo người dùng',1.00,5000000.00,5.00,4750000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,6,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,0.00,10800000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(12,8,3,'Phần mềm ERP Lite',1.00,45000000.00,0.00,45000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(13,9,2,'Phần mềm CRM - Gói Chuyên nghiệp',1.00,25000000.00,0.00,25000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(14,9,4,'Dịch vụ triển khai',1.00,15000000.00,0.00,15000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `sales_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sales_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(30) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned DEFAULT NULL,
  `order_date` date NOT NULL,
  `status` enum('pending','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_number_unique` (`number`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_quotation_id_foreign` (`quotation_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`),
  KEY `sales_orders_assigned_to_foreign` (`assigned_to`),
  KEY `sales_orders_approved_by_foreign` (`approved_by`),
  KEY `sales_orders_status_index` (`status`),
  KEY `sales_orders_is_demo_index` (`is_demo`),
  CONSTRAINT `sales_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `sales_orders_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `number`, `customer_id`, `quotation_id`, `order_date`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `approved_by`, `approved_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'DH00001',23,1,'2026-08-28','completed',74700000.00,0.00,10.00,7470000.00,82170000.00,NULL,3,3,2,'2026-08-28 20:00:51',1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(2,'DH00002',10,2,'2026-05-04','completed',9720000.00,0.00,10.00,972000.00,10692000.00,NULL,3,3,2,'2026-05-04 20:00:51',1,'2026-10-14 20:00:51','2026-10-14 20:00:51',NULL),(3,'DH00003',27,3,'2026-03-09','completed',40500000.00,0.00,10.00,4050000.00,44550000.00,NULL,3,3,2,'2026-03-09 20:00:51',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(4,'DH00004',11,4,'2026-09-17','completed',38000000.00,0.00,10.00,3800000.00,41800000.00,NULL,2,2,2,'2026-09-17 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(5,'DH00005',26,5,'2026-04-18','completed',81000000.00,0.00,10.00,8100000.00,89100000.00,NULL,4,4,2,'2026-04-18 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(6,'DH00006',14,6,'2025-12-23','completed',44050000.00,0.00,10.00,4405000.00,48455000.00,NULL,4,4,2,'2025-12-23 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(7,'DH00007',20,7,'2026-05-15','completed',64200000.00,0.00,10.00,6420000.00,70620000.00,NULL,4,4,2,'2026-05-15 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(8,'DH00008',23,8,'2026-04-14','confirmed',45000000.00,0.00,10.00,4500000.00,49500000.00,NULL,3,3,2,'2026-04-14 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(9,'DH00009',20,9,'2026-06-06','confirmed',40000000.00,0.00,10.00,4000000.00,44000000.00,NULL,4,4,2,'2026-06-06 20:00:52',1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL);

--
-- Table structure for table `sales_targets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sales_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `target_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_targets_user_id_year_month_unique` (`user_id`,`year`,`month`),
  CONSTRAINT `sales_targets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_targets`
--

INSERT INTO `sales_targets` (`id`, `user_id`, `year`, `month`, `target_amount`, `created_at`, `updated_at`) VALUES (1,2,2026,10,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(2,2,2026,9,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(3,2,2026,8,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(4,3,2026,10,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,3,2026,9,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,3,2026,8,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,4,2026,10,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,4,2026,9,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,4,2026,8,150000000.00,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `created_at`, `updated_at`) VALUES (1,'company_name','TOP CRM','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(2,'brand_name','TOP','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(3,'brand_tagline','TECHNOLOGY OF PROWESS','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(4,'brand_slogan','TOP CRM - Quản lý khách hàng, Kiến tạo tăng trưởng','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(5,'login_headline','Kết nối khách hàng','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(6,'login_headline_accent','Kiến tạo tăng trưởng','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(7,'login_description','TOP CRM giúp doanh nghiệp quản lý khách hàng, tối ưu quy trình bán hàng và nâng cao hiệu quả kinh doanh với công nghệ hiện đại.','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(8,'login_stats','10K+|Khách hàng tin dùng\n300%|Tăng hiệu suất\n99.9%|Bảo mật dữ liệu\n24/7|Hỗ trợ khách hàng','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(9,'login_background','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(10,'copyright_owner','Trịnh Phú Phúc','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(11,'copyright_text','TOP — Technology of Prowess','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(12,'page_terms','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(13,'page_privacy','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(14,'page_support','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(15,'google_client_id','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(16,'google_client_secret','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(17,'facebook_client_id','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(18,'facebook_client_secret','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(19,'company_email','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(20,'company_phone','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(21,'company_address','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(22,'company_logo','','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(23,'brand_color','#FF6B00','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(24,'timezone','Asia/Ho_Chi_Minh','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(25,'currency','VND','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(26,'date_format','d/m/Y','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(27,'language','vi','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(28,'default_tax_rate','10','general','2026-10-14 20:00:49','2026-10-14 20:00:49'),(29,'notify_lead_assigned','1','notifications','2026-10-14 20:00:49','2026-10-14 20:00:49'),(30,'notify_task_due','1','notifications','2026-10-14 20:00:49','2026-10-14 20:00:49'),(31,'notify_deal_stage','1','notifications','2026-10-14 20:00:49','2026-10-14 20:00:49'),(32,'notify_ticket_new','1','notifications','2026-10-14 20:00:49','2026-10-14 20:00:49');

--
-- Table structure for table `support_tickets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_code_unique` (`code`),
  KEY `support_tickets_customer_id_foreign` (`customer_id`),
  KEY `support_tickets_contact_id_foreign` (`contact_id`),
  KEY `support_tickets_assigned_to_foreign` (`assigned_to`),
  KEY `support_tickets_created_by_foreign` (`created_by`),
  KEY `support_tickets_created_at_index` (`created_at`),
  KEY `support_tickets_priority_index` (`priority`),
  KEY `support_tickets_status_index` (`status`),
  KEY `support_tickets_is_demo_index` (`is_demo`),
  CONSTRAINT `support_tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `code`, `customer_id`, `contact_id`, `subject`, `description`, `priority`, `status`, `assigned_to`, `created_by`, `resolved_at`, `closed_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'TK00001',4,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','closed',6,6,'2026-09-03 06:00:52','2026-09-04 20:00:52',1,'2026-08-31 20:00:52','2026-08-31 20:00:52',NULL),(2,'TK00002',9,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','waiting',6,6,NULL,NULL,1,'2026-08-13 20:00:52','2026-08-13 20:00:52',NULL),(3,'TK00003',8,NULL,'Máy POS không in hóa đơn','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-09-10 20:00:52','2026-09-10 20:00:52',NULL),(4,'TK00004',8,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','medium','waiting',6,6,NULL,NULL,1,'2026-07-26 20:00:52','2026-07-26 20:00:52',NULL),(5,'TK00005',24,NULL,'Không đăng nhập được','Mô tả sự cố demo.','urgent','closed',6,6,'2026-09-15 21:00:52','2026-09-18 20:00:52',1,'2026-09-14 20:00:52','2026-09-14 20:00:52',NULL),(6,'TK00006',16,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-07-24 20:00:52','2026-07-24 20:00:52',NULL),(7,'TK00007',22,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','urgent','waiting',6,6,NULL,NULL,1,'2026-09-13 20:00:52','2026-09-13 20:00:52',NULL),(8,'TK00008',16,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','medium','closed',6,6,'2026-09-27 03:00:52','2026-09-30 20:00:52',1,'2026-09-26 20:00:52','2026-09-26 20:00:52',NULL),(9,'TK00009',20,NULL,'Không đăng nhập được','Mô tả sự cố demo.','low','in_progress',6,6,NULL,NULL,1,'2026-09-05 20:00:52','2026-09-05 20:00:52',NULL),(10,'TK00010',13,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','open',6,6,NULL,NULL,1,'2026-07-23 20:00:52','2026-07-23 20:00:52',NULL);

--
-- Table structure for table `tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#FF6B00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `color`, `created_at`, `updated_at`) VALUES (1,'VIP','#FF6B00','2026-10-14 20:00:49','2026-10-14 20:00:49'),(2,'Doanh nghiệp lớn','#2563EB','2026-10-14 20:00:49','2026-10-14 20:00:49'),(3,'Khách quen','#16A34A','2026-10-14 20:00:49','2026-10-14 20:00:49'),(4,'Cần chăm sóc','#F59E0B','2026-10-14 20:00:49','2026-10-14 20:00:49'),(5,'Đối tác','#7C3AED','2026-10-14 20:00:49','2026-10-14 20:00:49');

--
-- Table structure for table `task_checklist_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_checklist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `content` varchar(255) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_checklist_items_task_id_foreign` (`task_id`),
  CONSTRAINT `task_checklist_items_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_checklist_items`
--

INSERT INTO `task_checklist_items` (`id`, `task_id`, `content`, `is_done`, `position`, `created_at`, `updated_at`) VALUES (1,1,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(2,1,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(3,2,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(4,2,'Liên hệ khách hàng',1,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,3,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,3,'Liên hệ khách hàng',1,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,4,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,4,'Liên hệ khách hàng',1,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,5,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(10,5,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(11,6,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(12,6,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(13,7,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(14,7,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(15,8,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(16,8,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(17,9,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(18,9,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(19,10,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(20,10,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(21,11,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(22,11,'Liên hệ khách hàng',1,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(23,12,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(24,12,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(25,13,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(26,13,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(27,14,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(28,14,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(29,15,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(30,15,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(31,16,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(32,16,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(33,17,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(34,17,'Liên hệ khách hàng',1,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(35,18,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(36,18,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(37,19,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(38,19,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(39,20,'Chuẩn bị tài liệu',1,1,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(40,20,'Liên hệ khách hàng',0,2,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `task_comments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_comments_task_id_foreign` (`task_id`),
  KEY `task_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `task_comments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_comments`
--


--
-- Table structure for table `tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reminded_at` timestamp NULL DEFAULT NULL,
  `related_type` varchar(255) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_related_type_related_id_index` (`related_type`,`related_id`),
  KEY `tasks_assigned_to_foreign` (`assigned_to`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_priority_index` (`priority`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_due_date_index` (`due_date`),
  KEY `tasks_is_demo_index` (`is_demo`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `priority`, `status`, `due_date`, `completed_at`, `reminded_at`, `related_type`, `related_id`, `assigned_to`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Gọi điện tư vấn - Ông Hoan Hòa','Công việc demo.','low','in_progress','2026-10-18 14:00:00',NULL,NULL,'customer',1,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(2,'Hẹn gặp demo sản phẩm - Trưng Tuấn Hảo','Công việc demo.','medium','completed','2026-10-26 15:00:00','2026-10-25 15:00:00',NULL,'customer',13,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(3,'Gọi điện tư vấn - Khuất Giang Nam','Công việc demo.','low','completed','2026-10-13 08:00:00','2026-10-12 08:00:00',NULL,'customer',25,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(4,'Hẹn gặp demo sản phẩm - Sử Xuyến Phượng','Công việc demo.','urgent','completed','2026-10-12 15:00:00','2026-10-11 15:00:00',NULL,'customer',16,4,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(5,'Gọi điện tư vấn - Tập đoàn Xây dựng Bình Minh','Công việc demo.','medium','cancelled','2026-10-09 16:00:00',NULL,NULL,'customer',29,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(6,'Theo dõi thanh toán - Công ty Cổ phần Công nghệ Đại','Công việc demo.','low','overdue','2026-10-05 10:00:00',NULL,NULL,'customer',21,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(7,'Gọi điện tư vấn - Sử Xuyến Phượng','Công việc demo.','high','pending','2026-11-07 14:00:00',NULL,NULL,'customer',16,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(8,'Khảo sát nhu cầu - Tập đoàn Logistics Thành Công','Công việc demo.','urgent','overdue','2026-10-04 16:00:00',NULL,NULL,'customer',15,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(9,'Chuẩn bị hợp đồng - Công ty TNHH MTV Thực phẩm Hưn','Công việc demo.','medium','pending','2026-10-21 08:00:00',NULL,NULL,'customer',14,4,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(10,'Khảo sát nhu cầu - Công ty TNHH MTV Sản xuất Đại','Công việc demo.','high','overdue','2026-10-02 10:00:00',NULL,NULL,'customer',26,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(11,'Khảo sát nhu cầu - Công ty TNHH Dịch vụ Đại Dương','Công việc demo.','medium','completed','2026-10-21 08:00:00','2026-10-20 08:00:00',NULL,'customer',20,4,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(12,'Chuẩn bị hợp đồng - Tập đoàn Thực phẩm Tân Á','Công việc demo.','urgent','overdue','2026-10-11 12:00:00',NULL,NULL,'customer',18,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(13,'Theo dõi thanh toán - Công ty TNHH MTV Thương mại Vi','Công việc demo.','medium','pending','2026-10-23 10:00:00',NULL,NULL,'customer',9,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(14,'Gọi điện tư vấn - Tào Tường Thông','Công việc demo.','high','in_progress','2026-10-22 15:00:00',NULL,NULL,'customer',10,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(15,'Theo dõi thanh toán - Trần Xuân Tiển','Công việc demo.','low','cancelled','2026-11-03 11:00:00',NULL,NULL,'customer',19,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(16,'Chuẩn bị hợp đồng - Công ty TNHH MTV Thương mại Vi','Công việc demo.','medium','pending','2026-10-24 12:00:00',NULL,NULL,'customer',9,4,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(17,'Theo dõi thanh toán - Trưng Tuấn Hảo','Công việc demo.','medium','completed','2026-10-16 10:00:00','2026-10-15 10:00:00',NULL,'customer',13,4,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(18,'Theo dõi thanh toán - Ông Hoan Hòa','Công việc demo.','urgent','in_progress','2026-10-23 17:00:00',NULL,NULL,'customer',1,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(19,'Khảo sát nhu cầu - Tập đoàn Sản xuất Đại Dương','Công việc demo.','urgent','pending','2026-11-02 08:00:00',NULL,NULL,'customer',30,2,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL),(20,'Khảo sát nhu cầu - Tập đoàn Xây dựng Bình Minh','Công việc demo.','urgent','overdue','2026-09-28 08:00:00',NULL,NULL,'customer',29,3,2,1,'2026-10-14 20:00:52','2026-10-14 20:00:52',NULL);

--
-- Table structure for table `ticket_replies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_replies_support_ticket_id_foreign` (`support_ticket_id`),
  KEY `ticket_replies_user_id_foreign` (`user_id`),
  CONSTRAINT `ticket_replies_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_replies`
--

INSERT INTO `ticket_replies` (`id`, `support_ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`) VALUES (1,1,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(2,2,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(3,3,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(4,4,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(5,5,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(6,6,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(7,7,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(8,8,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(9,9,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52'),(10,10,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-14 20:00:52','2026-10-14 20:00:52');

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','locked') NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_department_id_foreign` (`department_id`),
  KEY `users_status_index` (`status`),
  KEY `users_is_demo_index` (`is_demo`),
  CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `email_verified_at`, `password`, `phone`, `position`, `avatar`, `department_id`, `status`, `last_login_at`, `last_login_ip`, `is_demo`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Trần Minh Quân','quantri@demo.orangecrm.local',NULL,NULL,'$2y$12$wxX1nKPT51/W3vDdCh4N9eTtkW5fOVD0t0YwBT.LYHPg5hvFevkwK','0952082305','Quản trị hệ thống',NULL,1,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49',NULL),(2,'Lê Thu Hà','truongkinhdoanh@demo.orangecrm.local',NULL,NULL,'$2y$12$AMm3Bji2i6.dvi5uFxQZfePKeeotq7OwCPmfJpSscNk5doJYvjoy2','0962560774','Trưởng phòng kinh doanh',NULL,2,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49',NULL),(3,'Nguyễn Văn Nam','kinhdoanh1@demo.orangecrm.local',NULL,NULL,'$2y$12$33usvPB3Ot73HqvYFe0I5.oMwObQeF.QfbR7C2KxOrGsXqtpJ6jI6','0973871898','Nhân viên kinh doanh',NULL,2,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49',NULL),(4,'Phạm Thị Lan','kinhdoanh2@demo.orangecrm.local',NULL,NULL,'$2y$12$Jz1F1oRJdYwcP9aQ5dqhmurVmTOqzBmUkyY5PE2BKB7s8h4CSmUKq','0988273336','Nhân viên kinh doanh',NULL,2,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:49','2026-10-14 20:00:49',NULL),(5,'Hoàng Đức Huy','marketing@demo.orangecrm.local',NULL,NULL,'$2y$12$xJwH.O1tNMtHRyURXILOC.ZhMxdRfiJ33uU3lX8p9lElDkerDF1GO','0964617421','Chuyên viên marketing',NULL,3,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(6,'Vũ Ngọc Anh','hotro@demo.orangecrm.local',NULL,NULL,'$2y$12$4//JBqvuVg5MlfGr.2Xwi.1hDMC5iQQR1t8qpzp2DFT/3HynrnG/u','0995491917','Chuyên viên CSKH',NULL,4,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL),(7,'Đỗ Thanh Tùng','xem@demo.orangecrm.local',NULL,NULL,'$2y$12$Mbsf4osyBaCuNK7L0gjnwOirN/.aAHd7WmhqW.r8lCRJFxQDLdotC','0931818397','Kế toán',NULL,5,'active',NULL,NULL,1,NULL,'2026-10-14 20:00:50','2026-10-14 20:00:50',NULL);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-14 20:00:52
