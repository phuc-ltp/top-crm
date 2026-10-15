-- ============================================================================
--  TOP CRM — Database schema + dữ liệu khởi tạo
--  Database : orangecrm_db  (MariaDB 10.11 / MySQL 8, InnoDB, utf8mb4)
--  Tạo lúc  : 15/10/2026 21:10  bằng scripts/generate-sql.ps1
--
--  NỘI DUNG
--   1. Cấu trúc toàn bộ bảng, khóa chính, khóa ngoại, chỉ mục (tương đương Laravel migrations)
--   2. Dữ liệu cấu hình: tổ chức mặc định, vai trò, quyền, phòng ban, nguồn lead, giai đoạn, danh mục, cài đặt
--   3. DỮ LIỆU DEMO phục vụ kiểm thử — mọi bản ghi demo có cột is_demo = 1
--      (xóa bằng lệnh: php artisan crm:purge-demo)
--   4. Bảng `migrations` để Laravel biết cấu trúc đã được tạo (không chạy migrate trùng)
--
--  AN TOÀN
--   - KHÔNG chứa dữ liệu thật của doanh nghiệp (tạo từ database tạm chỉ có dữ liệu demo).
--   - KHÔNG có lệnh DROP. Bảng đã tồn tại thì lệnh tạo bảng được bỏ qua; bản ghi trùng được bỏ qua.
--   - KHÔNG có tài khoản quản trị, KHÔNG có mật khẩu thật, KHÔNG có khóa API / phiên / lịch sử đăng nhập.
--     Tài khoản demo có mật khẩu ngẫu nhiên không thể đoán. Sau khi import, mở /setup để tạo
--     tài khoản quản trị, hoặc chạy: php artisan crm:create-admin
--
--  CÁCH IMPORT: xem docs/DATABASE_SETUP.md
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `orangecrm_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `orangecrm_db`;
/*M!999999\- enable the sandbox mode */ 

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `activity_logs_organization_id_index` (`organization_id`),
  CONSTRAINT `activity_logs_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `attachments_organization_id_index` (`organization_id`),
  CONSTRAINT `attachments_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `attachments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `campaigns_organization_id_index` (`organization_id`),
  CONSTRAINT `campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `campaigns_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `contacts_organization_id_index` (`organization_id`),
  CONSTRAINT `contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `customer_tag` (
  `customer_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`customer_id`,`tag_id`),
  KEY `customer_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `customer_tag_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `customers_org_code_unique` (`organization_id`,`code`),
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
  KEY `customers_organization_id_index` (`organization_id`),
  CONSTRAINT `customers_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `customers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_org_code_unique` (`organization_id`,`code`),
  KEY `departments_organization_id_index` (`organization_id`),
  CONSTRAINT `departments_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `interactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `interactions_organization_id_index` (`organization_id`),
  CONSTRAINT `interactions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `interactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `invoices_org_number_unique` (`organization_id`,`number`),
  KEY `invoices_customer_id_foreign` (`customer_id`),
  KEY `invoices_sales_order_id_foreign` (`sales_order_id`),
  KEY `invoices_created_by_foreign` (`created_by`),
  KEY `invoices_assigned_to_foreign` (`assigned_to`),
  KEY `invoices_issue_date_index` (`issue_date`),
  KEY `invoices_status_index` (`status`),
  KEY `invoices_payment_status_index` (`payment_status`),
  KEY `invoices_is_demo_index` (`is_demo`),
  KEY `invoices_organization_id_index` (`organization_id`),
  CONSTRAINT `invoices_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `invoices_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `invoices_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `lead_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_sources_org_name_unique` (`organization_id`,`name`),
  KEY `lead_sources_organization_id_index` (`organization_id`),
  CONSTRAINT `lead_sources_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `leads_org_code_unique` (`organization_id`,`code`),
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
  KEY `leads_organization_id_index` (`organization_id`),
  CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_customer_id_foreign` FOREIGN KEY (`converted_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_opportunity_id_foreign` FOREIGN KEY (`converted_opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `login_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(30) NOT NULL,
  `method` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `device_hash` char(64) DEFAULT NULL,
  `is_new_device` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_events_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `login_events_user_id_device_hash_index` (`user_id`,`device_hash`),
  KEY `login_events_created_at_index` (`created_at`),
  CONSTRAINT `login_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`organization_id`,`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_permissions_permission_id_foreign` (`permission_id`),
  KEY `model_has_permissions_team_foreign_key_index` (`organization_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`organization_id`,`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_roles_role_id_foreign` (`role_id`),
  KEY `model_has_roles_team_foreign_key_index` (`organization_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `opportunities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `opportunities_org_code_unique` (`organization_id`,`code`),
  KEY `opportunities_customer_id_foreign` (`customer_id`),
  KEY `opportunities_contact_id_foreign` (`contact_id`),
  KEY `opportunities_lead_id_foreign` (`lead_id`),
  KEY `opportunities_assigned_to_foreign` (`assigned_to`),
  KEY `opportunities_created_by_foreign` (`created_by`),
  KEY `opportunities_pipeline_stage_id_position_index` (`pipeline_stage_id`,`position`),
  KEY `opportunities_is_demo_index` (`is_demo`),
  KEY `opportunities_organization_id_index` (`organization_id`),
  CONSTRAINT `opportunities_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `opportunities_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `organization_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` enum('active','invited','suspended') NOT NULL DEFAULT 'active',
  `joined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_user_organization_id_user_id_unique` (`organization_id`,`user_id`),
  KEY `organization_user_user_id_foreign` (`user_id`),
  CONSTRAINT `organization_user_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organization_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_slug_unique` (`slug`),
  KEY `organizations_owner_id_foreign` (`owner_id`),
  KEY `organizations_status_index` (`status`),
  CONSTRAINT `organizations_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `payments_organization_id_index` (`organization_id`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `last_used_ip` varchar(45) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_organization_id_foreign` (`organization_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`),
  CONSTRAINT `personal_access_tokens_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `pipeline_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `pipeline_stages_org_slug_unique` (`organization_id`,`slug`),
  KEY `pipeline_stages_organization_id_index` (`organization_id`),
  CONSTRAINT `pipeline_stages_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_org_name_unique` (`organization_id`,`name`),
  KEY `product_categories_organization_id_index` (`organization_id`),
  CONSTRAINT `product_categories_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `products_org_sku_unique` (`organization_id`,`sku`),
  KEY `products_product_category_id_foreign` (`product_category_id`),
  KEY `products_status_index` (`status`),
  KEY `products_is_demo_index` (`is_demo`),
  KEY `products_organization_id_index` (`organization_id`),
  CONSTRAINT `products_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `quotations_org_number_unique` (`organization_id`,`number`),
  KEY `quotations_customer_id_foreign` (`customer_id`),
  KEY `quotations_opportunity_id_foreign` (`opportunity_id`),
  KEY `quotations_created_by_foreign` (`created_by`),
  KEY `quotations_assigned_to_foreign` (`assigned_to`),
  KEY `quotations_status_index` (`status`),
  KEY `quotations_is_demo_index` (`is_demo`),
  KEY `quotations_organization_id_index` (`organization_id`),
  CONSTRAINT `quotations_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `quotations_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_organization_id_name_guard_name_unique` (`organization_id`,`name`,`guard_name`),
  KEY `roles_team_foreign_key_index` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sales_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `sales_orders_org_number_unique` (`organization_id`,`number`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_quotation_id_foreign` (`quotation_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`),
  KEY `sales_orders_assigned_to_foreign` (`assigned_to`),
  KEY `sales_orders_approved_by_foreign` (`approved_by`),
  KEY `sales_orders_status_index` (`status`),
  KEY `sales_orders_is_demo_index` (`is_demo`),
  KEY `sales_orders_organization_id_index` (`organization_id`),
  CONSTRAINT `sales_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `sales_orders_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `sales_orders_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sales_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `target_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_targets_org_user_id_year_month_unique` (`organization_id`,`user_id`,`year`,`month`),
  KEY `sales_targets_organization_id_index` (`organization_id`),
  KEY `sales_targets_user_id_index` (`user_id`),
  CONSTRAINT `sales_targets_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `sales_targets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `support_tickets_org_code_unique` (`organization_id`,`code`),
  KEY `support_tickets_customer_id_foreign` (`customer_id`),
  KEY `support_tickets_contact_id_foreign` (`contact_id`),
  KEY `support_tickets_assigned_to_foreign` (`assigned_to`),
  KEY `support_tickets_created_by_foreign` (`created_by`),
  KEY `support_tickets_created_at_index` (`created_at`),
  KEY `support_tickets_priority_index` (`priority`),
  KEY `support_tickets_status_index` (`status`),
  KEY `support_tickets_is_demo_index` (`is_demo`),
  KEY `support_tickets_organization_id_index` (`organization_id`),
  CONSTRAINT `support_tickets_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `support_tickets_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#FF6B00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_org_name_unique` (`organization_id`,`name`),
  KEY `tags_organization_id_index` (`organization_id`),
  CONSTRAINT `tags_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
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
  KEY `tasks_organization_id_index` (`organization_id`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `is_platform_admin` tinyint(1) NOT NULL DEFAULT 0,
  `locale` varchar(5) DEFAULT NULL,
  `theme` varchar(10) DEFAULT NULL,
  `current_organization_id` bigint(20) unsigned DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `two_factor_last_step` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_department_id_foreign` (`department_id`),
  KEY `users_status_index` (`status`),
  KEY `users_is_demo_index` (`is_demo`),
  KEY `users_current_organization_id_foreign` (`current_organization_id`),
  CONSTRAINT `users_current_organization_id_foreign` FOREIGN KEY (`current_organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---------------------------------------------------------------- DỮ LIỆU
/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `campaign_leads` WRITE;
/*!40000 ALTER TABLE `campaign_leads` DISABLE KEYS */;
INSERT IGNORE INTO `campaign_leads` (`campaign_id`, `lead_id`, `created_at`) VALUES (1,10,'2026-07-09 03:36:00'),
(1,11,'2026-01-18 10:54:00'),
(1,12,'2025-12-10 06:53:00'),
(1,18,'2026-02-16 08:22:00'),
(1,24,'2026-08-28 07:30:00'),
(1,25,'2026-08-05 01:19:00'),
(1,30,'2025-12-21 10:49:00'),
(2,1,'2026-02-14 07:24:00'),
(2,2,'2026-04-26 05:03:00'),
(2,5,'2026-07-26 05:07:00'),
(2,9,'2026-02-23 06:58:00'),
(2,21,'2026-06-26 03:54:00'),
(3,8,'2026-04-17 04:39:00'),
(3,14,'2026-08-26 05:30:00'),
(3,15,'2026-05-03 04:46:00'),
(3,16,'2026-08-22 08:03:00'),
(3,17,'2026-09-13 10:47:00'),
(3,19,'2026-07-22 06:22:00'),
(3,29,'2026-07-17 05:36:00'),
(4,4,'2026-01-27 08:18:00'),
(4,6,'2026-07-04 04:19:00'),
(4,20,'2026-09-22 03:18:00'),
(4,22,'2026-04-15 02:24:00'),
(4,27,'2026-07-13 06:45:00'),
(5,3,'2026-09-07 04:08:00'),
(5,7,'2026-04-11 10:27:00'),
(5,13,'2026-09-20 10:16:00'),
(5,23,'2026-02-19 02:56:00'),
(5,26,'2026-03-21 02:46:00'),
(5,28,'2026-01-17 08:36:00');
/*!40000 ALTER TABLE `campaign_leads` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
INSERT IGNORE INTO `campaigns` (`id`, `organization_id`, `name`, `type`, `start_date`, `end_date`, `budget`, `target_leads`, `status`, `description`, `owner_id`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Ra mắt CRM Pro Q1','event','2025-12-19','2026-02-17',50000000.00,40,'completed','Chiến dịch demo.',5,5,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(2,1,'Quảng cáo Facebook mùa hè','social','2026-05-18','2026-07-17',30000000.00,60,'completed','Chiến dịch demo.',5,5,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(3,1,'Google Ads doanh nghiệp SME','ads','2026-08-16','2026-11-14',45000000.00,80,'active','Chiến dịch demo.',5,5,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(4,1,'Email chăm sóc khách hàng cũ','email','2026-09-15','2026-12-14',5000000.00,30,'active','Chiến dịch demo.',5,5,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(5,1,'Hội thảo chuyển đổi số cuối năm','event','2026-11-04','2026-12-04',80000000.00,100,'planned','Chiến dịch demo.',5,5,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL);
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT IGNORE INTO `contacts` (`id`, `organization_id`, `customer_id`, `full_name`, `position`, `email`, `phone`, `company`, `notes`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,1,'Phí Hoàn Thêu','Trưởng phòng IT','lienhe1@demo.orangecrm.local','0940066563','Ông Hoan Hòa',NULL,3,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(2,1,2,'Từ Chiêu Liêm','Giám đốc','lienhe2@demo.orangecrm.local','0941196576','Công ty Cổ phần Logistics Trường Sơn',NULL,3,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(3,1,3,'Lô Xuyến Lệ','Trưởng phòng mua hàng','lienhe3@demo.orangecrm.local','0916079650','Công ty TNHH MTV Logistics Hòa Bình',NULL,2,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(4,1,4,'Bửu Lộc Bảo','Giám đốc','lienhe4@demo.orangecrm.local','0994679102','Tập đoàn Thực phẩm Sao Mai',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(5,1,5,'Bảo Quân Nghĩa','Trưởng phòng mua hàng','lienhe5@demo.orangecrm.local','0950784437','Tập đoàn Thực phẩm Nam Việt',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(6,1,6,'Khu Hỷ Nhi','Giám đốc','lienhe6@demo.orangecrm.local','0936038797','Công ty Cổ phần Thương mại Hòa Bình',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(7,1,7,'Nguyễn Trân Đại','Trưởng phòng IT','lienhe7@demo.orangecrm.local','0925117432','Mang Uyển Quân',NULL,4,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(8,1,8,'Thái Sỹ San','Trưởng phòng IT','lienhe8@demo.orangecrm.local','0927396489','Công ty TNHH Thương mại Sao Mai',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(9,1,9,'Chử Di Hỷ','Giám đốc','lienhe9@demo.orangecrm.local','0998691390','Công ty TNHH MTV Thương mại Việt Tiến',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(10,1,10,'Chương Trinh Kiên','Kế toán trưởng','lienhe10@demo.orangecrm.local','0916916165','Tập đoàn Thực phẩm An Khang',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(11,1,11,'Mạch Xuyến Phụng','Trưởng phòng IT','lienhe11@demo.orangecrm.local','0958920416','Công ty Cổ phần Dịch vụ Trường Sơn',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(12,1,12,'Triệu Hoàn Miên','Kế toán trưởng','lienhe12@demo.orangecrm.local','0931305193','Công ty TNHH MTV Thương mại Tân Á',NULL,4,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(13,1,13,'Đỗ Nhân Kiên','Trưởng phòng mua hàng','lienhe13@demo.orangecrm.local','0981664394','Trưng Tuấn Hảo',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(14,1,14,'Tông Phi Định','Trưởng phòng IT','lienhe14@demo.orangecrm.local','0957960423','Công ty TNHH MTV Thực phẩm Hưng Thịnh',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(15,1,15,'Vi Thuận Triệu','Trưởng phòng mua hàng','lienhe15@demo.orangecrm.local','0955939693','Tập đoàn Logistics Thành Công',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(16,1,16,'Cung Kiều Khôi','Trưởng phòng mua hàng','lienhe16@demo.orangecrm.local','0997266062','Công ty TNHH Sản xuất Tân Á',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(17,1,17,'Bảo Độ Vĩnh','Kế toán trưởng','lienhe17@demo.orangecrm.local','0930374249','Công ty TNHH Dịch vụ Hưng Thịnh',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(18,1,18,'Kiều Uy Phụng','Trưởng phòng IT','lienhe18@demo.orangecrm.local','0939145653','Tập đoàn Thực phẩm Tân Á',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(19,1,19,'Chu Sáng Linh','Kế toán trưởng','lienhe19@demo.orangecrm.local','0970813813','Trần Xuân Tiển',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(20,1,20,'Hình Lâm Chi','Trưởng phòng IT','lienhe20@demo.orangecrm.local','0939153277','Công ty TNHH Dịch vụ Đại Dương',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(21,1,21,'Chiêm Hợp Vọng','Trưởng phòng mua hàng','lienhe21@demo.orangecrm.local','0982252626','Công ty Cổ phần Công nghệ Đại Dương',NULL,4,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(22,1,22,'Phí Tuấn Thủy','Giám đốc','lienhe22@demo.orangecrm.local','0938928996','Công ty TNHH Dịch vụ Đại Dương',NULL,4,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(23,1,23,'Đậu Ngọc Lân','Trưởng phòng IT','lienhe23@demo.orangecrm.local','0992540246','Công ty Cổ phần Logistics Thành Công',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(24,1,24,'Ánh Hòa Trầm','Giám đốc','lienhe24@demo.orangecrm.local','0952314941','Công ty Cổ phần Xây dựng An Khang',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(25,1,25,'Hạ Ty Hà','Trưởng phòng IT','lienhe25@demo.orangecrm.local','0930612243','Khuất Giang Nam',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(26,1,26,'Đặng Dân Yên','Trưởng phòng mua hàng','lienhe26@demo.orangecrm.local','0954798889','Công ty TNHH MTV Sản xuất Đại Dương',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(27,1,27,'Vũ Ngọc Lập','Giám đốc','lienhe27@demo.orangecrm.local','0968623142','Tập đoàn Sản xuất Hoàng Long',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(28,1,28,'Hoàng Tài Giao','Trưởng phòng IT','lienhe28@demo.orangecrm.local','0990663611','Công ty TNHH Công nghệ Bình Minh',NULL,2,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(29,1,29,'Hy Hiền Lập','Trưởng phòng IT','lienhe29@demo.orangecrm.local','0918872351','Tập đoàn Xây dựng Bình Minh',NULL,4,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL),
(30,1,30,'Mâu Bảo Vy','Giám đốc','lienhe30@demo.orangecrm.local','0987312097','Tập đoàn Sản xuất Đại Dương',NULL,3,1,'2026-10-15 14:10:42','2026-10-15 14:10:42',NULL);
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `customer_tag` WRITE;
/*!40000 ALTER TABLE `customer_tag` DISABLE KEYS */;
INSERT IGNORE INTO `customer_tag` (`customer_id`, `tag_id`) VALUES (1,1),
(1,4),
(2,5),
(3,4),
(3,5),
(4,2),
(5,2),
(5,3),
(6,3),
(8,1),
(8,5),
(9,2),
(9,4),
(10,1),
(13,1),
(14,4),
(15,2),
(15,4),
(17,3),
(18,5),
(19,1),
(19,5),
(22,4),
(22,5),
(23,3),
(25,1),
(26,5),
(27,4),
(28,1);
/*!40000 ALTER TABLE `customer_tag` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT IGNORE INTO `customers` (`id`, `organization_id`, `code`, `name`, `phone`, `email`, `address`, `company`, `type`, `lead_source_id`, `status`, `assigned_to`, `created_by`, `updated_by`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'KH00001','Ông Hoan Hòa','0987170998','khachhang1@demo.orangecrm.local','7249 Phố Ty Cát Diệu, Xã Trường Nhuận, Quận Cát Cam\nHải Phòng',NULL,'individual',2,'inactive',3,2,NULL,'Khách hàng demo.',1,'2026-03-15 01:19:00','2026-03-15 01:19:00',NULL),
(2,1,'KH00002','Công ty Cổ phần Logistics Trường Sơn','0996921891','khachhang2@demo.orangecrm.local','8, Ấp Nhạn, Xã 8, Quận Ly Thi\nHậu Giang',NULL,'business',1,'active',3,2,NULL,'Khách hàng demo.',1,'2026-05-13 08:12:00','2026-05-13 08:12:00',NULL),
(3,1,'KH00003','Công ty TNHH MTV Logistics Hòa Bình','0980274382','khachhang3@demo.orangecrm.local','82, Thôn 6, Xã Phi Thanh, Huyện Xa\nSơn La',NULL,'business',2,'active',2,2,NULL,'Khách hàng demo.',1,'2026-05-04 10:16:00','2026-05-04 10:16:00',NULL),
(4,1,'KH00004','Hoàng Hùng Cúc','0912445407','khachhang4@demo.orangecrm.local','7198 Phố Kha, Thôn An Quân, Quận Cầm\nĐà Nẵng','Tập đoàn Thực phẩm Sao Mai','individual',6,'active',3,2,NULL,'Khách hàng demo.',1,'2026-01-15 10:39:00','2026-01-15 10:39:00',NULL),
(5,1,'KH00005','Tập đoàn Thực phẩm Nam Việt','0972110656','khachhang5@demo.orangecrm.local','93 Phố Lại Yên Thanh, Phường Hiên Hạ, Quận 4\nBắc Kạn',NULL,'business',4,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-06-10 01:05:00','2026-06-10 01:05:00',NULL),
(6,1,'KH00006','Công ty Cổ phần Thương mại Hòa Bình','0999814588','khachhang6@demo.orangecrm.local','8 Phố An, Xã Tân, Huyện Thào Mỹ\nTuyên Quang',NULL,'business',4,'active',2,2,NULL,'Khách hàng demo.',1,'2026-08-14 05:37:00','2026-08-14 05:37:00',NULL),
(7,1,'KH00007','Mang Uyển Quân','0941744345','khachhang7@demo.orangecrm.local','5945 Phố Lò, Xã Lương, Huyện Bồ Nhi\nHà Nội',NULL,'individual',6,'vip',4,2,NULL,'Khách hàng demo.',1,'2026-07-10 06:35:00','2026-07-10 06:35:00',NULL),
(8,1,'KH00008','Công ty TNHH Thương mại Sao Mai','0993802851','khachhang8@demo.orangecrm.local','7206 Phố Ma Lĩnh Lan, Phường Hàn Ngôn Dương, Huyện Đài Ngân\nHải Phòng',NULL,'business',9,'vip',3,2,NULL,'Khách hàng demo.',1,'2026-03-05 01:26:00','2026-03-05 01:26:00',NULL),
(9,1,'KH00009','Công ty TNHH MTV Thương mại Việt Tiến','0922426244','khachhang9@demo.orangecrm.local','728 Phố Kim Hữu Thịnh, Xã Bình, Huyện 55\nHòa Bình',NULL,'business',7,'active',2,2,NULL,'Khách hàng demo.',1,'2025-11-08 02:50:00','2025-11-08 02:50:00',NULL),
(10,1,'KH00010','Tào Tường Thông','0968338331','khachhang10@demo.orangecrm.local','174 Phố Bồ Cường Anh, Phường Phan Thiên Lai, Huyện Xuyến Đức\nKon Tum','Tập đoàn Thực phẩm An Khang','individual',7,'potential',2,2,NULL,'Khách hàng demo.',1,'2025-11-09 05:12:00','2025-11-09 05:12:00',NULL),
(11,1,'KH00011','Công ty Cổ phần Dịch vụ Trường Sơn','0924739066','khachhang11@demo.orangecrm.local','8939, Ấp Giao Tông, Xã Tú Danh, Huyện Tòng Vượng Yên\nTrà Vinh',NULL,'business',1,'active',2,2,NULL,'Khách hàng demo.',1,'2026-06-13 08:26:00','2026-06-13 08:26:00',NULL),
(12,1,'KH00012','Công ty TNHH MTV Thương mại Tân Á','0945718800','khachhang12@demo.orangecrm.local','6592, Thôn Thịnh Tín Xuân, Phường Khổng Lĩnh Vinh, Quận Nhiệm\nĐiện Biên',NULL,'business',7,'vip',4,2,NULL,'Khách hàng demo.',1,'2026-01-07 07:01:00','2026-01-07 07:01:00',NULL),
(13,1,'KH00013','Trưng Tuấn Hảo','0927052592','khachhang13@demo.orangecrm.local','920, Thôn Ma, Thôn Hồng Đình, Quận Sa\nĐiện Biên',NULL,'individual',9,'active',3,2,NULL,'Khách hàng demo.',1,'2026-05-13 07:59:00','2026-05-13 07:59:00',NULL),
(14,1,'KH00014','Công ty TNHH MTV Thực phẩm Hưng Thịnh','0914873094','khachhang14@demo.orangecrm.local','84 Phố Huỳnh Nhật Tài, Phường 08, Quận Liễu Thục\nThanh Hóa',NULL,'business',3,'inactive',3,2,NULL,'Khách hàng demo.',1,'2026-09-19 03:53:00','2026-09-19 03:53:00',NULL),
(15,1,'KH00015','Tập đoàn Logistics Thành Công','0922725920','khachhang15@demo.orangecrm.local','508, Ấp Thi Vương, Phường Phúc Tống, Huyện Bùi\nKon Tum',NULL,'business',4,'active',3,2,NULL,'Khách hàng demo.',1,'2026-06-04 07:53:00','2026-06-04 07:53:00',NULL),
(16,1,'KH00016','Sử Xuyến Phượng','0910140404','khachhang16@demo.orangecrm.local','12 Phố Phước, Xã Luận Từ, Huyện Trà Hiếu\nHồ Chí Minh','Công ty TNHH Sản xuất Tân Á','individual',1,'active',3,2,NULL,'Khách hàng demo.',1,'2026-08-15 09:31:00','2026-08-15 09:31:00',NULL),
(17,1,'KH00017','Công ty TNHH Dịch vụ Hưng Thịnh','0927713402','khachhang17@demo.orangecrm.local','1 Phố Chung, Phường Hợp Lô, Quận Thiều\nCao Bằng',NULL,'business',5,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-01-11 06:36:00','2026-01-11 06:36:00',NULL),
(18,1,'KH00018','Tập đoàn Thực phẩm Tân Á','0910300037','khachhang18@demo.orangecrm.local','8, Ấp Tùng Luật, Phường Thảo Ca, Quận Yên\nLong An',NULL,'business',5,'potential',3,2,NULL,'Khách hàng demo.',1,'2026-02-23 10:46:00','2026-02-23 10:46:00',NULL),
(19,1,'KH00019','Trần Xuân Tiển','0985681066','khachhang19@demo.orangecrm.local','7002 Phố Khúc Phúc Thục, Phường Thu Đan, Huyện Đan Khuê\nCần Thơ',NULL,'individual',3,'inactive',3,2,NULL,'Khách hàng demo.',1,'2026-09-03 07:02:00','2026-09-03 07:02:00',NULL),
(20,1,'KH00020','Công ty TNHH Dịch vụ Đại Dương','0958099409','khachhang20@demo.orangecrm.local','5020 Phố Lâm, Phường Sỹ, Huyện Phan\nBà Rịa - Vũng Tàu',NULL,'business',8,'active',2,2,NULL,'Khách hàng demo.',1,'2025-11-23 09:28:00','2025-11-23 09:28:00',NULL),
(21,1,'KH00021','Công ty Cổ phần Công nghệ Đại Dương','0974902804','khachhang21@demo.orangecrm.local','912, Ấp Võ, Phường Kỳ, Huyện 3\nKiên Giang',NULL,'business',3,'active',4,2,NULL,'Khách hàng demo.',1,'2026-08-10 07:55:00','2026-08-10 07:55:00',NULL),
(22,1,'KH00022','Doãn Như Giang','0962007634','khachhang22@demo.orangecrm.local','80 Phố Mạch Hiệp Mi, Phường Lều Thục Ca, Huyện 70\nHải Phòng','Công ty TNHH Dịch vụ Đại Dương','individual',6,'active',4,2,NULL,'Khách hàng demo.',1,'2025-11-26 07:27:00','2025-11-26 07:27:00',NULL),
(23,1,'KH00023','Công ty Cổ phần Logistics Thành Công','0955216935','khachhang23@demo.orangecrm.local','7928 Phố Kha Nữ Hào, Ấp Vĩ Hồng, Quận Chiêm Huy Hoài\nHải Phòng',NULL,'business',8,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-09-11 10:33:00','2026-09-11 10:33:00',NULL),
(24,1,'KH00024','Công ty Cổ phần Xây dựng An Khang','0923412641','khachhang24@demo.orangecrm.local','4 Phố Hạnh, Xã Ngôn Ca, Huyện Kha\nHưng Yên',NULL,'business',5,'inactive',3,2,NULL,'Khách hàng demo.',1,'2026-09-02 01:39:00','2026-09-02 01:39:00',NULL),
(25,1,'KH00025','Khuất Giang Nam','0979833082','khachhang25@demo.orangecrm.local','52 Phố Danh Cát Du, Phường 38, Quận Hợp\nBình Định',NULL,'individual',7,'inactive',3,2,NULL,'Khách hàng demo.',1,'2025-12-29 02:57:00','2025-12-29 02:57:00',NULL),
(26,1,'KH00026','Công ty TNHH MTV Sản xuất Đại Dương','0959846276','khachhang26@demo.orangecrm.local','301 Phố Cổ Thạc Châu, Phường Phương, Huyện Bình Âu\nHải Phòng',NULL,'business',8,'vip',3,2,NULL,'Khách hàng demo.',1,'2025-11-26 07:44:00','2025-11-26 07:44:00',NULL),
(27,1,'KH00027','Tập đoàn Sản xuất Hoàng Long','0917164802','khachhang27@demo.orangecrm.local','7957 Phố Ngạn, Ấp Thanh Tâm, Huyện Hồng\nVĩnh Long',NULL,'business',9,'vip',2,2,NULL,'Khách hàng demo.',1,'2026-04-14 10:42:00','2026-04-14 10:42:00',NULL),
(28,1,'KH00028','Diệp Quế Hòa','0943173054','khachhang28@demo.orangecrm.local','57, Ấp Hạnh Quản, Phường Châu, Quận 4\nBến Tre','Công ty TNHH Công nghệ Bình Minh','individual',6,'potential',2,2,NULL,'Khách hàng demo.',1,'2026-07-01 08:43:00','2026-07-01 08:43:00',NULL),
(29,1,'KH00029','Tập đoàn Xây dựng Bình Minh','0910414899','khachhang29@demo.orangecrm.local','237 Phố Đinh Hoa Lâm, Phường 90, Quận Dụng Viên\nVĩnh Long',NULL,'business',3,'potential',4,2,NULL,'Khách hàng demo.',1,'2026-01-28 01:41:00','2026-01-28 01:41:00',NULL),
(30,1,'KH00030','Tập đoàn Sản xuất Đại Dương','0948497293','khachhang30@demo.orangecrm.local','599 Phố Dân, Xã Ty Thanh Quân, Huyện 02\nCần Thơ',NULL,'business',2,'active',3,2,NULL,'Khách hàng demo.',1,'2026-08-18 06:37:00','2026-08-18 06:37:00',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT IGNORE INTO `departments` (`id`, `organization_id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES (1,1,'Ban Giám đốc','BGD',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,'Phòng Kinh doanh','KD',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,'Phòng Marketing','MKT',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,1,'Phòng Chăm sóc khách hàng','CSKH',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,1,'Phòng Kế toán','KT',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `interactions` WRITE;
/*!40000 ALTER TABLE `interactions` DISABLE KEYS */;
INSERT IGNORE INTO `interactions` (`id`, `organization_id`, `interactable_type`, `interactable_id`, `type`, `subject`, `content`, `occurred_at`, `user_id`, `created_at`, `updated_at`) VALUES (1,1,'customer',1,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-03-16 01:19:00',3,'2026-10-15 14:10:41','2026-10-15 14:10:41'),
(2,1,'customer',2,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-16 08:12:00',3,'2026-10-15 14:10:41','2026-10-15 14:10:41'),
(3,1,'customer',3,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-04 10:16:00',2,'2026-10-15 14:10:41','2026-10-15 14:10:41'),
(4,1,'customer',4,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-18 10:39:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(5,1,'customer',5,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-11 01:05:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(6,1,'customer',6,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-16 05:37:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(7,1,'customer',7,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-07-11 06:35:00',4,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(8,1,'customer',8,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-03-10 01:26:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(9,1,'customer',9,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-11 02:50:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(10,1,'customer',10,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-13 05:12:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(11,1,'customer',11,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-15 08:26:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(12,1,'customer',12,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-08 07:01:00',4,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(13,1,'customer',13,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-05-14 07:59:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(14,1,'customer',14,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-23 03:53:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(15,1,'customer',15,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-06-09 07:53:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(16,1,'customer',16,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-20 09:31:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(17,1,'customer',17,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-13 06:36:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(18,1,'customer',18,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-02-27 10:46:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(19,1,'customer',19,'call','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-06 07:02:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(20,1,'customer',20,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-28 09:28:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(21,1,'customer',21,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-15 07:55:00',4,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(22,1,'customer',22,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-29 07:27:00',4,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(23,1,'customer',23,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-16 10:33:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(24,1,'customer',24,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-09-04 01:39:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(25,1,'customer',25,'meeting','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-12-30 02:57:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(26,1,'customer',26,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2025-11-26 07:44:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(27,1,'customer',27,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-04-15 10:42:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(28,1,'customer',28,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-07-04 08:43:00',2,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(29,1,'customer',29,'note','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-01-31 01:41:00',4,'2026-10-15 14:10:42','2026-10-15 14:10:42'),
(30,1,'customer',30,'email','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.','2026-08-21 06:37:00',3,'2026-10-15 14:10:42','2026-10-15 14:10:42');
/*!40000 ALTER TABLE `interactions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT IGNORE INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,8,'Đào tạo người dùng',1.00,5000000.00,10.00,4500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,2,3,'Phần mềm ERP Lite',3.00,45000000.00,10.00,121500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,3,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,10.00,10800000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,4,10,'Lưu trữ đám mây 1TB',1.00,3600000.00,0.00,3600000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,5,2,'Phần mềm CRM - Gói Chuyên nghiệp',2.00,25000000.00,10.00,45000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,6,4,'Dịch vụ triển khai',1.00,15000000.00,5.00,14250000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,6,7,'Máy bán hàng POS',3.00,9500000.00,0.00,28500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(12,8,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,0.00,12000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT IGNORE INTO `invoices` (`id`, `organization_id`, `number`, `customer_id`, `sales_order_id`, `issue_date`, `due_date`, `status`, `payment_status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `amount_paid`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'HD00001',4,1,'2026-08-30','2026-09-14','issued','partial',45000000.00,0.00,10.00,4500000.00,49500000.00,24750000.00,NULL,3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(2,1,'HD00002',27,2,'2026-05-06','2026-05-21','issued','paid',121500000.00,0.00,10.00,12150000.00,133650000.00,133650000.00,NULL,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(3,1,'HD00003',29,3,'2026-03-11','2026-03-26','issued','paid',10800000.00,0.00,10.00,1080000.00,11880000.00,11880000.00,NULL,4,4,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(4,1,'HD00004',27,4,'2026-09-19','2026-10-04','issued','partial',3600000.00,0.00,10.00,360000.00,3960000.00,1980000.00,NULL,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(5,1,'HD00005',8,5,'2026-04-20','2026-05-05','issued','paid',45000000.00,0.00,10.00,4500000.00,49500000.00,49500000.00,NULL,3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(6,1,'HD00006',7,6,'2025-12-25','2026-01-09','issued','paid',128250000.00,0.00,10.00,12825000.00,141075000.00,141075000.00,NULL,4,4,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(7,1,'HD00007',6,7,'2026-05-17','2026-06-01','issued','unpaid',64200000.00,0.00,10.00,6420000.00,70620000.00,0.00,NULL,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(8,1,'HD00008',15,8,'2026-04-16','2026-05-01','issued','unpaid',12000000.00,0.00,10.00,1200000.00,13200000.00,0.00,NULL,3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `lead_sources` WRITE;
/*!40000 ALTER TABLE `lead_sources` DISABLE KEYS */;
INSERT IGNORE INTO `lead_sources` (`id`, `organization_id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (1,1,'Website',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,'Facebook',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,'Zalo',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,1,'Google Ads',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,1,'Giới thiệu',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(6,1,'Sự kiện',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(7,1,'Gọi điện',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(8,1,'Email',1,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(9,1,'Khác',1,'2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `lead_sources` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT IGNORE INTO `leads` (`id`, `organization_id`, `code`, `name`, `company`, `email`, `phone`, `lead_source_id`, `status`, `score`, `estimated_value`, `assigned_to`, `created_by`, `converted_customer_id`, `converted_opportunity_id`, `converted_at`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'LD00001','Trác Vy Lai','Công ty TNHH Logistics Việt Tiến','lead1@demo.orangecrm.local','0329532340',9,'new',83,114000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-14 07:24:00','2026-02-14 07:24:00',NULL),
(2,1,'LD00002','Nhữ Lạc Thương','Công ty TNHH MTV Thương mại Đại Dương','lead2@demo.orangecrm.local','0343543332',9,'new',73,6000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-26 05:03:00','2026-04-26 05:03:00',NULL),
(3,1,'LD00003','Bì Hà Uyển','Công ty TNHH MTV Dịch vụ Việt Tiến','lead3@demo.orangecrm.local','0388777634',6,'contacted',77,66000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-07 04:08:00','2026-09-07 04:08:00',NULL),
(4,1,'LD00004','Uông Hằng Giáp','Công ty Cổ phần Công nghệ Hoàng Long','lead4@demo.orangecrm.local','0344880316',4,'contacted',53,29000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-27 08:18:00','2026-01-27 08:18:00',NULL),
(5,1,'LD00005','Trưng Diệu Luận','Công ty TNHH MTV Sản xuất Đại Dương','lead5@demo.orangecrm.local','0379647561',5,'qualified',64,91000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-26 05:07:00','2026-07-26 05:07:00',NULL),
(6,1,'LD00006','Tiêu Thịnh Lễ','Tập đoàn Xây dựng Hoàng Long','lead6@demo.orangecrm.local','0358976027',1,'proposal',33,27000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-04 04:19:00','2026-07-04 04:19:00',NULL),
(7,1,'LD00007','Ma Nhã Mỹ','Công ty Cổ phần Thương mại Việt Tiến','lead7@demo.orangecrm.local','0367143741',8,'converted',13,106000000.00,4,5,6,NULL,'2026-04-14 10:27:00','Lead demo.',1,'2026-04-11 10:27:00','2026-04-11 10:27:00',NULL),
(8,1,'LD00008','Diêm Sương Thiện','Công ty TNHH MTV Logistics Hòa Bình','lead8@demo.orangecrm.local','0373545167',2,'lost',75,71000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-17 04:39:00','2026-04-17 04:39:00',NULL),
(9,1,'LD00009','Mạc Trúc Phong','Tập đoàn Công nghệ An Khang','lead9@demo.orangecrm.local','0359110732',6,'new',73,60000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-23 06:58:00','2026-02-23 06:58:00',NULL),
(10,1,'LD00010','Thân Trung Nhi','Công ty TNHH MTV Sản xuất Bình Minh','lead10@demo.orangecrm.local','0364899923',8,'new',33,43000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-09 03:36:00','2026-07-09 03:36:00',NULL),
(11,1,'LD00011','Thạch Dân Thúy','Tập đoàn Thương mại Hòa Bình','lead11@demo.orangecrm.local','0395289835',6,'contacted',45,115000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-18 10:54:00','2026-01-18 10:54:00',NULL),
(12,1,'LD00012','Cự Khuyên Huấn','Công ty TNHH MTV Logistics Hoàng Long','lead12@demo.orangecrm.local','0362076308',7,'contacted',63,103000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2025-12-10 06:53:00','2025-12-10 06:53:00',NULL),
(13,1,'LD00013','Bạc Huyền Khanh','Công ty TNHH Thực phẩm Phú Thịnh','lead13@demo.orangecrm.local','0349581250',2,'qualified',87,83000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-20 10:16:00','2026-09-20 10:16:00',NULL),
(14,1,'LD00014','Thôi Tú Nhiên','Công ty TNHH Công nghệ Hòa Bình','lead14@demo.orangecrm.local','0362531211',3,'proposal',78,7000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-26 05:30:00','2026-08-26 05:30:00',NULL),
(15,1,'LD00015','Lữ Tuyết Bình','Công ty TNHH Logistics Việt Tiến','lead15@demo.orangecrm.local','0338332101',2,'converted',84,103000000.00,3,5,12,NULL,'2026-05-09 04:46:00','Lead demo.',1,'2026-05-03 04:46:00','2026-05-03 04:46:00',NULL),
(16,1,'LD00016','Hy Ca Thông','Tập đoàn Logistics Kim Ngân','lead16@demo.orangecrm.local','0398568291',5,'lost',34,118000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-22 08:03:00','2026-08-22 08:03:00',NULL),
(17,1,'LD00017','Đới Diệu Du','Công ty Cổ phần Logistics Đại Dương','lead17@demo.orangecrm.local','0341190811',7,'new',67,114000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-13 10:47:00','2026-09-13 10:47:00',NULL),
(18,1,'LD00018','Hình Hồng Sương','Công ty TNHH Logistics Nam Việt','lead18@demo.orangecrm.local','0313372341',4,'new',71,44000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-02-16 08:22:00','2026-02-16 08:22:00',NULL),
(19,1,'LD00019','Bàng Lâm Ngôn','Công ty TNHH MTV Công nghệ Hòa Bình','lead19@demo.orangecrm.local','0327090968',2,'contacted',35,91000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-22 06:22:00','2026-07-22 06:22:00',NULL),
(20,1,'LD00020','Đái Đan Tùng','Công ty TNHH MTV Logistics Đại Dương','lead20@demo.orangecrm.local','0338313952',7,'contacted',10,22000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-09-22 03:18:00','2026-09-22 03:18:00',NULL),
(21,1,'LD00021','Đinh Tuệ Nguyệt','Tập đoàn Công nghệ Trường Sơn','lead21@demo.orangecrm.local','0391701988',7,'qualified',45,82000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-06-26 03:54:00','2026-06-26 03:54:00',NULL),
(22,1,'LD00022','Diệp Lâm Hoài','Công ty TNHH MTV Xây dựng Trường Sơn','lead22@demo.orangecrm.local','0371593183',9,'proposal',21,82000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-04-15 02:24:00','2026-04-15 02:24:00',NULL),
(23,1,'LD00023','Trác Vỹ Hải','Công ty TNHH Công nghệ An Khang','lead23@demo.orangecrm.local','0335536034',2,'converted',58,102000000.00,4,5,26,NULL,'2026-03-03 02:56:00','Lead demo.',1,'2026-02-19 02:56:00','2026-02-19 02:56:00',NULL),
(24,1,'LD00024','Phùng Giáp Trang','Công ty TNHH MTV Thực phẩm Kim Ngân','lead24@demo.orangecrm.local','0324921292',1,'lost',26,57000000.00,3,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-28 07:30:00','2026-08-28 07:30:00',NULL),
(25,1,'LD00025','Liễu Ân Mạnh','Công ty Cổ phần Sản xuất Nam Việt','lead25@demo.orangecrm.local','0335863999',5,'new',53,63000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-08-05 01:19:00','2026-08-05 01:19:00',NULL),
(26,1,'LD00026','Sử Giang Chinh','Tập đoàn Sản xuất Đại Dương','lead26@demo.orangecrm.local','0371198710',1,'new',26,81000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-03-21 02:46:00','2026-03-21 02:46:00',NULL),
(27,1,'LD00027','Hoa Giáp Liên','Công ty Cổ phần Thương mại Tân Á','lead27@demo.orangecrm.local','0381713341',3,'contacted',27,29000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-13 06:45:00','2026-07-13 06:45:00',NULL),
(28,1,'LD00028','Đậu Đan Tuyền','Công ty Cổ phần Sản xuất Minh Phát','lead28@demo.orangecrm.local','0338666907',9,'contacted',16,40000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-01-17 08:36:00','2026-01-17 08:36:00',NULL),
(29,1,'LD00029','Tông Thiên Sĩ','Tập đoàn Xây dựng Trường Sơn','lead29@demo.orangecrm.local','0331454326',5,'qualified',46,85000000.00,4,5,NULL,NULL,NULL,'Lead demo.',1,'2026-07-17 05:36:00','2026-07-17 05:36:00',NULL),
(30,1,'LD00030','Chương Hoài Duy','Công ty Cổ phần Công nghệ Nam Việt','lead30@demo.orangecrm.local','0348329614',9,'proposal',60,40000000.00,2,5,NULL,NULL,NULL,'Lead demo.',1,'2025-12-21 10:49:00','2025-12-21 10:49:00',NULL);
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_10_13_161955_create_permission_tables',1),
(5,'2026_10_13_170000_create_crm_core_tables',1),
(6,'2026_10_13_170100_create_sales_tables',1),
(7,'2026_10_13_170200_create_work_tables',1),
(8,'2026_10_14_090000_add_username_to_users_table',1),
(9,'2026_10_15_100000_mark_app_initialized',1),
(10,'2026_10_15_110000_add_locale_to_users_table',1),
(11,'2026_10_15_120000_create_organizations_tables',1),
(12,'2026_10_15_120100_add_organization_id_to_business_tables',1),
(13,'2026_10_16_100000_rbac_teams_and_platform_admin',1),
(14,'2026_10_17_100000_add_theme_to_users_table',1),
(15,'2026_10_17_110000_add_account_security_tables',1),
(16,'2026_10_18_100000_create_personal_access_tokens_table',1),
(17,'2026_10_18_100100_grant_api_permission',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT IGNORE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`, `organization_id`) VALUES (2,'user',1,1),
(3,'user',2,1),
(4,'user',3,1),
(4,'user',4,1),
(6,'user',5,1),
(8,'user',6,1),
(14,'user',7,1);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `opportunities` WRITE;
/*!40000 ALTER TABLE `opportunities` DISABLE KEYS */;
INSERT IGNORE INTO `opportunities` (`id`, `organization_id`, `code`, `title`, `customer_id`, `contact_id`, `lead_id`, `pipeline_stage_id`, `value`, `probability`, `expected_close_date`, `closed_at`, `lost_reason`, `position`, `assigned_to`, `created_by`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'CH00001','Đào tạo nhân viên - Tập đoàn Logistics Thành Công',15,15,NULL,1,226000000.00,10,'2026-12-12',NULL,NULL,0,3,2,'Cơ hội demo.',1,'2026-06-21 14:10:42','2026-06-21 14:10:42',NULL),
(2,1,'CH00002','Nâng cấp ERP - Tập đoàn Xây dựng Bình Minh',29,29,NULL,2,54000000.00,25,'2026-12-28',NULL,NULL,1,4,2,'Cơ hội demo.',1,'2026-07-05 14:10:42','2026-07-05 14:10:42',NULL),
(3,1,'CH00003','Nâng cấp ERP - Công ty Cổ phần Logistics Thàn',23,23,NULL,3,214000000.00,50,'2026-12-08',NULL,NULL,2,2,2,'Cơ hội demo.',1,'2026-03-11 14:10:42','2026-03-11 14:10:42',NULL),
(4,1,'CH00004','Mua máy chủ - Tập đoàn Sản xuất Hoàng Long',27,27,NULL,4,127000000.00,75,'2026-10-29',NULL,NULL,3,2,2,'Cơ hội demo.',1,'2026-04-01 14:10:42','2026-04-01 14:10:42',NULL),
(5,1,'CH00005','Triển khai CRM - Công ty Cổ phần Logistics Trườ',2,2,NULL,5,48000000.00,100,'2026-10-04','2026-06-14 14:10:42',NULL,4,3,2,'Cơ hội demo.',1,'2026-06-03 14:10:42','2026-06-03 14:10:42',NULL),
(6,1,'CH00006','Triển khai CRM - Công ty TNHH MTV Thương mại Tâ',12,12,NULL,6,55000000.00,0,'2026-12-12','2026-01-21 14:10:42','Khách chọn nhà cung cấp khác (demo)',5,4,2,'Cơ hội demo.',1,'2025-12-20 14:10:42','2025-12-20 14:10:42',NULL),
(7,1,'CH00007','Triển khai CRM - Tập đoàn Logistics Thành Công',15,15,NULL,1,27000000.00,10,'2026-09-18',NULL,NULL,6,3,2,'Cơ hội demo.',1,'2026-04-28 14:10:42','2026-04-28 14:10:42',NULL),
(8,1,'CH00008','Mua máy chủ - Tập đoàn Thực phẩm Nam Việt',5,5,NULL,2,13000000.00,25,'2027-01-01',NULL,NULL,7,2,2,'Cơ hội demo.',1,'2026-04-20 14:10:42','2026-04-20 14:10:42',NULL),
(9,1,'CH00009','Đào tạo nhân viên - Công ty TNHH Dịch vụ Đại Dương',20,20,NULL,3,244000000.00,50,'2027-01-08',NULL,NULL,8,2,2,'Cơ hội demo.',1,'2026-06-28 14:10:43','2026-06-28 14:10:43',NULL),
(10,1,'CH00010','Đào tạo nhân viên - Tập đoàn Thực phẩm Tân Á',18,18,NULL,4,129000000.00,75,'2026-10-28',NULL,NULL,9,3,2,'Cơ hội demo.',1,'2026-02-27 14:10:43','2026-02-27 14:10:43',NULL),
(11,1,'CH00011','Trang bị POS - Công ty TNHH MTV Thương mại Tâ',12,12,NULL,5,271000000.00,100,'2026-11-08','2026-06-15 14:10:43',NULL,10,4,2,'Cơ hội demo.',1,'2026-06-05 14:10:43','2026-06-05 14:10:43',NULL),
(12,1,'CH00012','Gói hỗ trợ năm - Công ty TNHH MTV Logistics Hòa',3,3,NULL,6,121000000.00,0,'2026-10-21','2026-08-18 14:10:43','Khách chọn nhà cung cấp khác (demo)',11,2,2,'Cơ hội demo.',1,'2026-07-25 14:10:43','2026-07-25 14:10:43',NULL),
(13,1,'CH00013','Mua máy chủ - Ông Hoan Hòa',1,1,NULL,1,130000000.00,10,'2026-10-11',NULL,NULL,12,3,2,'Cơ hội demo.',1,'2026-01-31 14:10:43','2026-01-31 14:10:43',NULL),
(14,1,'CH00014','Gói hỗ trợ năm - Tập đoàn Logistics Thành Công',15,15,NULL,2,90000000.00,25,'2026-09-24',NULL,NULL,13,3,2,'Cơ hội demo.',1,'2026-07-26 14:10:43','2026-07-26 14:10:43',NULL),
(15,1,'CH00015','Triển khai CRM - Trần Xuân Tiển',19,19,NULL,3,288000000.00,50,'2026-12-11',NULL,NULL,14,3,2,'Cơ hội demo.',1,'2026-03-14 14:10:43','2026-03-14 14:10:43',NULL);
/*!40000 ALTER TABLE `opportunities` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `opportunity_stage_histories` WRITE;
/*!40000 ALTER TABLE `opportunity_stage_histories` DISABLE KEYS */;
INSERT IGNORE INTO `opportunity_stage_histories` (`id`, `opportunity_id`, `from_stage_id`, `to_stage_id`, `changed_by`, `created_at`) VALUES (1,1,NULL,1,2,'2026-06-21 14:10:42'),
(2,2,NULL,2,2,'2026-07-05 14:10:42'),
(3,3,NULL,3,2,'2026-03-11 14:10:42'),
(4,4,NULL,4,2,'2026-04-01 14:10:42'),
(5,5,NULL,5,2,'2026-06-03 14:10:42'),
(6,6,NULL,6,2,'2025-12-20 14:10:42'),
(7,7,NULL,1,2,'2026-04-28 14:10:42'),
(8,8,NULL,2,2,'2026-04-20 14:10:42'),
(9,9,NULL,3,2,'2026-06-28 14:10:43'),
(10,10,NULL,4,2,'2026-02-27 14:10:43'),
(11,11,NULL,5,2,'2026-06-05 14:10:43'),
(12,12,NULL,6,2,'2026-07-25 14:10:43'),
(13,13,NULL,1,2,'2026-01-31 14:10:43'),
(14,14,NULL,2,2,'2026-07-26 14:10:43'),
(15,15,NULL,3,2,'2026-03-14 14:10:43');
/*!40000 ALTER TABLE `opportunity_stage_histories` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `organization_user` WRITE;
/*!40000 ALTER TABLE `organization_user` DISABLE KEYS */;
INSERT IGNORE INTO `organization_user` (`id`, `organization_id`, `user_id`, `status`, `joined_at`, `created_at`, `updated_at`) VALUES (1,1,1,'active','2026-10-15 14:10:40','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,2,'active','2026-10-15 14:10:40','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,3,'active','2026-10-15 14:10:41','2026-10-15 14:10:41','2026-10-15 14:10:41'),
(4,1,4,'active','2026-10-15 14:10:41','2026-10-15 14:10:41','2026-10-15 14:10:41'),
(5,1,5,'active','2026-10-15 14:10:41','2026-10-15 14:10:41','2026-10-15 14:10:41'),
(6,1,6,'active','2026-10-15 14:10:41','2026-10-15 14:10:41','2026-10-15 14:10:41'),
(7,1,7,'active','2026-10-15 14:10:41','2026-10-15 14:10:41','2026-10-15 14:10:41');
/*!40000 ALTER TABLE `organization_user` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT IGNORE INTO `organizations` (`id`, `name`, `slug`, `status`, `owner_id`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'TOP CRM','top','active',NULL,'2026-10-15 14:10:32','2026-10-15 14:10:32',NULL);
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT IGNORE INTO `payments` (`id`, `organization_id`, `invoice_id`, `amount`, `payment_date`, `method`, `reference`, `notes`, `recorded_by`, `is_demo`, `created_at`, `updated_at`) VALUES (1,1,1,24750000.00,'2026-09-04','bank_transfer','DEMO-1',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,2,133650000.00,'2026-05-07','bank_transfer','DEMO-2',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,1,3,11880000.00,'2026-03-23','bank_transfer','DEMO-3',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,1,4,1980000.00,'2026-09-30','bank_transfer','DEMO-4',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,1,5,49500000.00,'2026-04-26','bank_transfer','DEMO-5',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,1,6,141075000.00,'2026-01-06','bank_transfer','DEMO-6',NULL,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT IGNORE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'api.use','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(2,'dashboard.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(3,'customers.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(4,'customers.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(5,'customers.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(6,'customers.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(7,'customers.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(8,'customers.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(9,'customers.assign','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(10,'contacts.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(11,'contacts.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(12,'contacts.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(13,'contacts.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(14,'contacts.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(15,'contacts.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(16,'leads.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(17,'leads.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(18,'leads.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(19,'leads.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(20,'leads.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(21,'leads.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(22,'leads.assign','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(23,'opportunities.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(24,'opportunities.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(25,'opportunities.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(26,'opportunities.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(27,'opportunities.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(28,'opportunities.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(29,'opportunities.assign','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(30,'tasks.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(31,'tasks.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(32,'tasks.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(33,'tasks.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(34,'tasks.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(35,'tasks.assign','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(36,'products.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(37,'products.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(38,'products.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(39,'products.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(40,'products.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(41,'quotations.view','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(42,'quotations.view_all','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(43,'quotations.create','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(44,'quotations.update','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(45,'quotations.delete','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(46,'quotations.export','web','2026-10-15 14:10:38','2026-10-15 14:10:38'),
(47,'quotations.approve','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(48,'orders.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(49,'orders.view_all','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(50,'orders.create','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(51,'orders.update','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(52,'orders.delete','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(53,'orders.export','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(54,'orders.approve','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(55,'invoices.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(56,'invoices.view_all','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(57,'invoices.create','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(58,'invoices.update','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(59,'invoices.delete','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(60,'invoices.export','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(61,'campaigns.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(62,'campaigns.view_all','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(63,'campaigns.create','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(64,'campaigns.update','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(65,'campaigns.delete','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(66,'campaigns.export','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(67,'tickets.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(68,'tickets.view_all','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(69,'tickets.create','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(70,'tickets.update','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(71,'tickets.delete','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(72,'tickets.export','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(73,'tickets.assign','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(74,'reports.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(75,'reports.export','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(76,'users.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(77,'users.create','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(78,'users.update','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(79,'users.delete','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(80,'users.manage','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(81,'activity_logs.view','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(82,'settings.manage','web','2026-10-15 14:10:39','2026-10-15 14:10:39');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `pipeline_stages` WRITE;
/*!40000 ALTER TABLE `pipeline_stages` DISABLE KEYS */;
INSERT IGNORE INTO `pipeline_stages` (`id`, `organization_id`, `name`, `slug`, `position`, `probability`, `color`, `is_won`, `is_lost`, `created_at`, `updated_at`) VALUES (1,1,'Cơ hội mới','new',1,10,'#6B7280',0,0,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,'Đánh giá nhu cầu','qualification',2,25,'#3B82F6',0,0,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,'Gửi đề xuất','proposal',3,50,'#F59E0B',0,0,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,1,'Đàm phán','negotiation',4,75,'#FF6B00',0,0,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,1,'Thắng','won',5,100,'#16A34A',1,0,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(6,1,'Thất bại','lost',6,0,'#DC2626',0,1,'2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `pipeline_stages` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT IGNORE INTO `product_categories` (`id`, `organization_id`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,1,'Phần mềm',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,'Dịch vụ',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,'Phần cứng',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,1,'Đào tạo',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,1,'Gói hỗ trợ',NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT IGNORE INTO `products` (`id`, `organization_id`, `sku`, `name`, `product_category_id`, `description`, `price`, `unit`, `status`, `image`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'SW-CRM-STD','Phần mềm CRM - Gói Tiêu chuẩn',1,'Sản phẩm demo: Phần mềm CRM - Gói Tiêu chuẩn',12000000.00,'gói','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(2,1,'SW-CRM-PRO','Phần mềm CRM - Gói Chuyên nghiệp',1,'Sản phẩm demo: Phần mềm CRM - Gói Chuyên nghiệp',25000000.00,'gói','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(3,1,'SW-ERP-LITE','Phần mềm ERP Lite',1,'Sản phẩm demo: Phần mềm ERP Lite',45000000.00,'gói','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(4,1,'SV-IMPL','Dịch vụ triển khai',2,'Sản phẩm demo: Dịch vụ triển khai',15000000.00,'dự án','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(5,1,'SV-CUSTOM','Phát triển tính năng theo yêu cầu',2,'Sản phẩm demo: Phát triển tính năng theo yêu cầu',800000.00,'giờ','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(6,1,'HW-SERVER','Máy chủ Dell PowerEdge T150',3,'Sản phẩm demo: Máy chủ Dell PowerEdge T150',38000000.00,'cái','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(7,1,'HW-POS','Máy bán hàng POS',3,'Sản phẩm demo: Máy bán hàng POS',9500000.00,'cái','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(8,1,'TR-USER','Đào tạo người dùng',4,'Sản phẩm demo: Đào tạo người dùng',5000000.00,'buổi','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(9,1,'SP-YEAR','Gói hỗ trợ kỹ thuật 12 tháng',5,'Sản phẩm demo: Gói hỗ trợ kỹ thuật 12 tháng',18000000.00,'năm','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(10,1,'SP-CLOUD','Lưu trữ đám mây 1TB',5,'Sản phẩm demo: Lưu trữ đám mây 1TB',3600000.00,'năm','active',NULL,1,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT IGNORE INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,8,'Đào tạo người dùng',1.00,5000000.00,10.00,4500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,2,3,'Phần mềm ERP Lite',3.00,45000000.00,10.00,121500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,3,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,10.00,10800000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,4,10,'Lưu trữ đám mây 1TB',1.00,3600000.00,0.00,3600000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,5,2,'Phần mềm CRM - Gói Chuyên nghiệp',2.00,25000000.00,10.00,45000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,6,4,'Dịch vụ triển khai',1.00,15000000.00,5.00,14250000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,6,7,'Máy bán hàng POS',3.00,9500000.00,0.00,28500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(12,8,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,0.00,12000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(13,9,2,'Phần mềm CRM - Gói Chuyên nghiệp',1.00,25000000.00,0.00,25000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(14,9,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(15,10,5,'Phát triển tính năng theo yêu cầu',2.00,800000.00,10.00,1440000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(16,10,10,'Lưu trữ đám mây 1TB',1.00,3600000.00,10.00,3240000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(17,11,3,'Phần mềm ERP Lite',1.00,45000000.00,5.00,42750000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(18,11,7,'Máy bán hàng POS',1.00,9500000.00,0.00,9500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(19,12,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,5.00,11400000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(20,12,2,'Phần mềm CRM - Gói Chuyên nghiệp',2.00,25000000.00,5.00,47500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(21,12,10,'Lưu trữ đám mây 1TB',3.00,3600000.00,5.00,10260000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT IGNORE INTO `quotations` (`id`, `organization_id`, `number`, `customer_id`, `opportunity_id`, `issue_date`, `valid_until`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'BG00001',4,NULL,'2026-08-27','2026-09-26','accepted',45000000.00,0.00,10.00,4500000.00,49500000.00,'Báo giá demo.',3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(2,1,'BG00002',27,NULL,'2026-05-03','2026-06-02','accepted',121500000.00,0.00,10.00,12150000.00,133650000.00,'Báo giá demo.',2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(3,1,'BG00003',29,NULL,'2026-03-08','2026-04-07','accepted',10800000.00,0.00,10.00,1080000.00,11880000.00,'Báo giá demo.',4,4,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(4,1,'BG00004',27,NULL,'2026-09-16','2026-10-16','accepted',3600000.00,0.00,10.00,360000.00,3960000.00,'Báo giá demo.',2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(5,1,'BG00005',8,NULL,'2026-04-17','2026-05-17','accepted',45000000.00,0.00,10.00,4500000.00,49500000.00,'Báo giá demo.',3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(6,1,'BG00006',7,NULL,'2025-12-22','2026-01-21','accepted',128250000.00,0.00,10.00,12825000.00,141075000.00,'Báo giá demo.',4,4,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(7,1,'BG00007',6,NULL,'2026-05-14','2026-06-13','accepted',64200000.00,0.00,10.00,6420000.00,70620000.00,'Báo giá demo.',2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(8,1,'BG00008',15,NULL,'2026-04-13','2026-05-13','accepted',12000000.00,0.00,10.00,1200000.00,13200000.00,'Báo giá demo.',3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(9,1,'BG00009',4,NULL,'2026-06-05','2026-07-05','accepted',30000000.00,0.00,10.00,3000000.00,33000000.00,'Báo giá demo.',3,3,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(10,1,'BG00010',5,NULL,'2025-11-09','2025-12-09','rejected',4680000.00,0.00,10.00,468000.00,5148000.00,'Báo giá demo.',2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(11,1,'BG00011',27,NULL,'2026-07-11','2026-08-10','sent',52250000.00,0.00,10.00,5225000.00,57475000.00,'Báo giá demo.',2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(12,1,'BG00012',21,NULL,'2026-01-14','2026-02-13','rejected',69160000.00,0.00,10.00,6916000.00,76076000.00,'Báo giá demo.',4,4,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL);
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES (1,1),
(1,2),
(2,1),
(2,2),
(2,3),
(2,4),
(2,5),
(2,6),
(2,7),
(2,8),
(2,9),
(2,10),
(2,11),
(2,12),
(2,13),
(2,14),
(3,1),
(3,2),
(3,3),
(3,4),
(3,5),
(3,6),
(3,7),
(3,8),
(3,9),
(3,10),
(3,12),
(3,13),
(3,14),
(4,1),
(4,2),
(4,3),
(4,5),
(4,6),
(4,7),
(4,8),
(4,9),
(4,10),
(4,12),
(4,14),
(5,1),
(5,2),
(5,3),
(5,4),
(6,1),
(6,2),
(6,3),
(6,4),
(6,7),
(6,8),
(7,1),
(7,2),
(7,3),
(8,1),
(8,2),
(8,3),
(8,5),
(8,6),
(8,7),
(8,9),
(9,1),
(9,2),
(9,3),
(10,1),
(10,2),
(10,3),
(10,4),
(10,5),
(10,6),
(10,7),
(10,8),
(10,13),
(10,14),
(11,1),
(11,2),
(11,3),
(11,5),
(11,6),
(11,7),
(11,8),
(11,14),
(12,1),
(12,2),
(12,3),
(12,4),
(12,7),
(12,8),
(13,1),
(13,2),
(13,3),
(13,4),
(13,7),
(13,8),
(14,1),
(14,2),
(14,3),
(14,7),
(15,1),
(15,2),
(15,3),
(15,5),
(15,7),
(16,1),
(16,2),
(16,3),
(16,4),
(16,5),
(16,6),
(16,14),
(17,1),
(17,2),
(17,3),
(17,5),
(17,6),
(17,14),
(18,1),
(18,2),
(18,3),
(18,4),
(18,5),
(18,6),
(19,1),
(19,2),
(19,3),
(19,4),
(19,5),
(19,6),
(20,1),
(20,2),
(20,3),
(20,5),
(21,1),
(21,2),
(21,3),
(21,5),
(21,6),
(22,1),
(22,2),
(22,3),
(22,5),
(22,6),
(23,1),
(23,2),
(23,3),
(23,4),
(23,12),
(23,14),
(24,1),
(24,2),
(24,3),
(24,12),
(24,14),
(25,1),
(25,2),
(25,3),
(25,4),
(26,1),
(26,2),
(26,3),
(26,4),
(27,1),
(27,2),
(27,3),
(28,1),
(28,2),
(28,3),
(29,1),
(29,2),
(29,3),
(30,1),
(30,2),
(30,3),
(30,4),
(30,5),
(30,6),
(30,7),
(30,8),
(30,9),
(30,10),
(30,11),
(30,12),
(30,13),
(30,14),
(31,1),
(31,2),
(31,3),
(31,5),
(31,7),
(31,12),
(31,14),
(32,1),
(32,2),
(32,3),
(32,4),
(32,5),
(32,6),
(32,7),
(32,8),
(32,9),
(32,10),
(32,11),
(32,12),
(32,13),
(33,1),
(33,2),
(33,3),
(33,4),
(33,5),
(33,6),
(33,7),
(33,8),
(33,9),
(33,10),
(33,11),
(33,12),
(33,13),
(34,1),
(34,2),
(34,3),
(34,5),
(34,7),
(34,12),
(35,1),
(35,2),
(35,3),
(35,5),
(35,7),
(35,12),
(36,1),
(36,2),
(36,3),
(36,4),
(36,5),
(36,6),
(36,7),
(36,8),
(36,9),
(36,10),
(36,11),
(36,13),
(36,14),
(37,1),
(37,2),
(37,11),
(38,1),
(38,2),
(38,11),
(39,1),
(39,2),
(39,11),
(40,1),
(40,2),
(40,3),
(40,11),
(41,1),
(41,2),
(41,3),
(41,4),
(41,9),
(41,14),
(42,1),
(42,2),
(42,3),
(42,9),
(42,14),
(43,1),
(43,2),
(43,3),
(43,4),
(44,1),
(44,2),
(44,3),
(44,4),
(45,1),
(45,2),
(45,3),
(46,1),
(46,2),
(46,3),
(46,9),
(47,1),
(47,2),
(47,3),
(48,1),
(48,2),
(48,3),
(48,4),
(48,9),
(48,10),
(48,11),
(48,14),
(49,1),
(49,2),
(49,3),
(49,9),
(49,10),
(49,11),
(49,14),
(50,1),
(50,2),
(50,3),
(50,4),
(51,1),
(51,2),
(51,3),
(51,4),
(52,1),
(52,2),
(52,3),
(53,1),
(53,2),
(53,3),
(53,9),
(54,1),
(54,2),
(54,3),
(54,9),
(55,1),
(55,2),
(55,3),
(55,4),
(55,9),
(55,10),
(55,14),
(56,1),
(56,2),
(56,3),
(56,9),
(56,10),
(56,14),
(57,1),
(57,2),
(57,3),
(57,9),
(57,10),
(58,1),
(58,2),
(58,3),
(58,9),
(58,10),
(59,1),
(59,2),
(59,3),
(59,9),
(60,1),
(60,2),
(60,3),
(60,9),
(60,10),
(61,1),
(61,2),
(61,3),
(61,5),
(61,6),
(61,14),
(62,1),
(62,2),
(62,3),
(62,5),
(62,6),
(62,14),
(63,1),
(63,2),
(63,5),
(63,6),
(64,1),
(64,2),
(64,5),
(64,6),
(65,1),
(65,2),
(65,5),
(65,6),
(66,1),
(66,2),
(66,5),
(66,6),
(67,1),
(67,2),
(67,3),
(67,4),
(67,7),
(67,8),
(67,14),
(68,1),
(68,2),
(68,3),
(68,7),
(68,8),
(68,14),
(69,1),
(69,2),
(69,4),
(69,7),
(69,8),
(70,1),
(70,2),
(70,7),
(70,8),
(71,1),
(71,2),
(71,7),
(71,8),
(72,1),
(72,2),
(72,7),
(72,8),
(73,1),
(73,2),
(73,7),
(73,8),
(74,1),
(74,2),
(74,3),
(74,5),
(74,6),
(74,7),
(74,8),
(74,9),
(74,10),
(74,11),
(74,12),
(74,14),
(75,1),
(75,2),
(75,3),
(75,5),
(75,7),
(75,9),
(75,10),
(76,1),
(76,2),
(76,3),
(76,5),
(76,7),
(76,9),
(76,12),
(77,1),
(77,2),
(78,1),
(78,2),
(79,1),
(79,2),
(80,1),
(80,2),
(81,1),
(81,2),
(81,14),
(82,1),
(82,2);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT IGNORE INTO `roles` (`id`, `organization_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,1,'Organization Owner','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(2,1,'Organization Administrator','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(3,1,'Sales Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(4,1,'Sales Employee','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(5,1,'Marketing Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(6,1,'Marketing Employee','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(7,1,'Customer Support Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(8,1,'Support Agent','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(9,1,'Finance Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(10,1,'Accountant','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(11,1,'Inventory Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(12,1,'Project Manager','web','2026-10-15 14:10:39','2026-10-15 14:10:39'),
(13,1,'Employee','web','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(14,1,'Read-only Auditor','web','2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_order_items` WRITE;
/*!40000 ALTER TABLE `sales_order_items` DISABLE KEYS */;
INSERT IGNORE INTO `sales_order_items` (`id`, `sales_order_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,8,'Đào tạo người dùng',1.00,5000000.00,10.00,4500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,2,3,'Phần mềm ERP Lite',3.00,45000000.00,10.00,121500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,3,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,10.00,10800000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,4,10,'Lưu trữ đám mây 1TB',1.00,3600000.00,0.00,3600000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,5,2,'Phần mềm CRM - Gói Chuyên nghiệp',2.00,25000000.00,10.00,45000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,6,4,'Dịch vụ triển khai',1.00,15000000.00,5.00,14250000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,6,7,'Máy bán hàng POS',3.00,9500000.00,0.00,28500000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(10,7,4,'Dịch vụ triển khai',2.00,15000000.00,0.00,30000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(11,7,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,10.00,34200000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(12,8,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,0.00,12000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(13,9,2,'Phần mềm CRM - Gói Chuyên nghiệp',1.00,25000000.00,0.00,25000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(14,9,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `sales_order_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
INSERT IGNORE INTO `sales_orders` (`id`, `organization_id`, `number`, `customer_id`, `quotation_id`, `order_date`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `approved_by`, `approved_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'DH00001',4,1,'2026-08-29','completed',45000000.00,0.00,10.00,4500000.00,49500000.00,NULL,3,3,2,'2026-08-29 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(2,1,'DH00002',27,2,'2026-05-05','completed',121500000.00,0.00,10.00,12150000.00,133650000.00,NULL,2,2,2,'2026-05-05 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(3,1,'DH00003',29,3,'2026-03-10','completed',10800000.00,0.00,10.00,1080000.00,11880000.00,NULL,4,4,2,'2026-03-10 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(4,1,'DH00004',27,4,'2026-09-18','completed',3600000.00,0.00,10.00,360000.00,3960000.00,NULL,2,2,2,'2026-09-18 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(5,1,'DH00005',8,5,'2026-04-19','completed',45000000.00,0.00,10.00,4500000.00,49500000.00,NULL,3,3,2,'2026-04-19 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(6,1,'DH00006',7,6,'2025-12-24','completed',128250000.00,0.00,10.00,12825000.00,141075000.00,NULL,4,4,2,'2025-12-24 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(7,1,'DH00007',6,7,'2026-05-16','completed',64200000.00,0.00,10.00,6420000.00,70620000.00,NULL,2,2,2,'2026-05-16 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(8,1,'DH00008',15,8,'2026-04-15','confirmed',12000000.00,0.00,10.00,1200000.00,13200000.00,NULL,3,3,2,'2026-04-15 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(9,1,'DH00009',4,9,'2026-06-07','confirmed',30000000.00,0.00,10.00,3000000.00,33000000.00,NULL,3,3,2,'2026-06-07 14:10:43',1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL);
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_targets` WRITE;
/*!40000 ALTER TABLE `sales_targets` DISABLE KEYS */;
INSERT IGNORE INTO `sales_targets` (`id`, `organization_id`, `user_id`, `year`, `month`, `target_amount`, `created_at`, `updated_at`) VALUES (1,1,2,2026,10,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,2,2026,9,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,1,2,2026,8,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,1,3,2026,10,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,1,3,2026,9,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,1,3,2026,8,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,1,4,2026,10,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,1,4,2026,9,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,1,4,2026,8,150000000.00,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `sales_targets` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT IGNORE INTO `settings` (`id`, `key`, `value`, `group`, `created_at`, `updated_at`) VALUES (1,'company_name','TOP CRM','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,'brand_name','TOP','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,'brand_tagline','TECHNOLOGY OF PROWESS','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,'brand_slogan','TOP CRM - Quản lý khách hàng, Kiến tạo tăng trưởng','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,'login_headline','Kết nối khách hàng','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(6,'login_headline_accent','Kiến tạo tăng trưởng','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(7,'login_description','TOP CRM giúp doanh nghiệp quản lý khách hàng, tối ưu quy trình bán hàng và nâng cao hiệu quả kinh doanh với công nghệ hiện đại.','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(8,'login_stats','10K+|Khách hàng tin dùng\n300%|Tăng hiệu suất\n99.9%|Bảo mật dữ liệu\n24/7|Hỗ trợ khách hàng','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(9,'login_background','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(10,'copyright_owner','Trịnh Phú Phúc','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(11,'copyright_text','TOP — Technology of Prowess','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(12,'page_terms','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(13,'page_privacy','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(14,'page_support','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(15,'google_client_id','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(16,'google_client_secret','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(17,'facebook_client_id','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(18,'facebook_client_secret','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(19,'company_email','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(20,'company_phone','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(21,'company_address','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(22,'company_logo','','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(23,'brand_color','#D9823B','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(24,'timezone','Asia/Ho_Chi_Minh','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(25,'currency','VND','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(26,'date_format','d/m/Y','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(27,'language','vi','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(28,'default_tax_rate','10','general','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(29,'notify_lead_assigned','1','notifications','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(30,'notify_task_due','1','notifications','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(31,'notify_deal_stage','1','notifications','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(32,'notify_ticket_new','1','notifications','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(33,'require_2fa_admins','0','general','2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
INSERT IGNORE INTO `support_tickets` (`id`, `organization_id`, `code`, `customer_id`, `contact_id`, `subject`, `description`, `priority`, `status`, `assigned_to`, `created_by`, `resolved_at`, `closed_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'TK00001',13,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','closed',6,6,'2026-09-04 00:10:43','2026-09-05 14:10:43',1,'2026-09-01 14:10:43','2026-09-01 14:10:43',NULL),
(2,1,'TK00002',28,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','waiting',6,6,NULL,NULL,1,'2026-08-14 14:10:43','2026-08-14 14:10:43',NULL),
(3,1,'TK00003',3,NULL,'Máy POS không in hóa đơn','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-09-11 14:10:43','2026-09-11 14:10:43',NULL),
(4,1,'TK00004',20,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','medium','waiting',6,6,NULL,NULL,1,'2026-07-27 14:10:43','2026-07-27 14:10:43',NULL),
(5,1,'TK00005',18,NULL,'Không đăng nhập được','Mô tả sự cố demo.','urgent','closed',6,6,'2026-09-16 15:10:43','2026-09-19 14:10:43',1,'2026-09-15 14:10:43','2026-09-15 14:10:43',NULL),
(6,1,'TK00006',23,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-07-25 14:10:43','2026-07-25 14:10:43',NULL),
(7,1,'TK00007',30,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','urgent','waiting',6,6,NULL,NULL,1,'2026-09-14 14:10:43','2026-09-14 14:10:43',NULL),
(8,1,'TK00008',3,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','medium','closed',6,6,'2026-09-27 21:10:43','2026-10-01 14:10:43',1,'2026-09-27 14:10:43','2026-09-27 14:10:43',NULL),
(9,1,'TK00009',18,NULL,'Không đăng nhập được','Mô tả sự cố demo.','low','in_progress',6,6,NULL,NULL,1,'2026-09-06 14:10:43','2026-09-06 14:10:43',NULL),
(10,1,'TK00010',18,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','open',6,6,NULL,NULL,1,'2026-07-24 14:10:43','2026-07-24 14:10:43',NULL);
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT IGNORE INTO `tags` (`id`, `organization_id`, `name`, `color`, `created_at`, `updated_at`) VALUES (1,1,'VIP','#FF6B00','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(2,1,'Doanh nghiệp lớn','#2563EB','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(3,1,'Khách quen','#16A34A','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(4,1,'Cần chăm sóc','#F59E0B','2026-10-15 14:10:40','2026-10-15 14:10:40'),
(5,1,'Đối tác','#7C3AED','2026-10-15 14:10:40','2026-10-15 14:10:40');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `task_checklist_items` WRITE;
/*!40000 ALTER TABLE `task_checklist_items` DISABLE KEYS */;
INSERT IGNORE INTO `task_checklist_items` (`id`, `task_id`, `content`, `is_done`, `position`, `created_at`, `updated_at`) VALUES (1,1,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,1,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,2,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,2,'Liên hệ khách hàng',1,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,3,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,3,'Liên hệ khách hàng',1,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,4,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,4,'Liên hệ khách hàng',1,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,5,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(10,5,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(11,6,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(12,6,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(13,7,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(14,7,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(15,8,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(16,8,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(17,9,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(18,9,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(19,10,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(20,10,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(21,11,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(22,11,'Liên hệ khách hàng',1,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(23,12,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(24,12,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(25,13,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(26,13,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(27,14,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(28,14,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(29,15,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(30,15,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(31,16,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(32,16,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(33,17,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(34,17,'Liên hệ khách hàng',1,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(35,18,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(36,18,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(37,19,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(38,19,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(39,20,'Chuẩn bị tài liệu',1,1,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(40,20,'Liên hệ khách hàng',0,2,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `task_checklist_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `task_comments` WRITE;
/*!40000 ALTER TABLE `task_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_comments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT IGNORE INTO `tasks` (`id`, `organization_id`, `title`, `description`, `priority`, `status`, `due_date`, `completed_at`, `reminded_at`, `related_type`, `related_id`, `assigned_to`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Gọi điện tư vấn - Trần Xuân Tiển','Công việc demo.','low','in_progress','2026-10-19 14:00:00',NULL,NULL,'customer',19,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(2,1,'Hẹn gặp demo sản phẩm - Tập đoàn Xây dựng Bình Minh','Công việc demo.','medium','completed','2026-10-27 15:00:00','2026-10-26 08:00:00',NULL,'customer',29,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(3,1,'Gọi điện tư vấn - Công ty TNHH MTV Thương mại Vi','Công việc demo.','low','completed','2026-10-14 08:00:00','2026-10-13 01:00:00',NULL,'customer',9,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(4,1,'Hẹn gặp demo sản phẩm - Công ty Cổ phần Thương mại Hòa','Công việc demo.','urgent','completed','2026-10-13 15:00:00','2026-10-12 08:00:00',NULL,'customer',6,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(5,1,'Gọi điện tư vấn - Công ty TNHH MTV Thương mại Tâ','Công việc demo.','medium','cancelled','2026-10-10 16:00:00',NULL,NULL,'customer',12,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(6,1,'Theo dõi thanh toán - Trần Xuân Tiển','Công việc demo.','low','overdue','2026-10-06 10:00:00',NULL,NULL,'customer',19,2,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(7,1,'Gọi điện tư vấn - Công ty TNHH Dịch vụ Hưng Thịn','Công việc demo.','high','pending','2026-11-08 14:00:00',NULL,NULL,'customer',17,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(8,1,'Khảo sát nhu cầu - Công ty Cổ phần Logistics Trườ','Công việc demo.','urgent','overdue','2026-10-05 16:00:00',NULL,NULL,'customer',2,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(9,1,'Chuẩn bị hợp đồng - Khuất Giang Nam','Công việc demo.','medium','pending','2026-10-22 08:00:00',NULL,NULL,'customer',25,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(10,1,'Khảo sát nhu cầu - Công ty TNHH MTV Thực phẩm Hưn','Công việc demo.','high','overdue','2026-10-03 10:00:00',NULL,NULL,'customer',14,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(11,1,'Khảo sát nhu cầu - Tập đoàn Thực phẩm Nam Việt','Công việc demo.','medium','completed','2026-10-22 08:00:00','2026-10-21 01:00:00',NULL,'customer',5,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(12,1,'Chuẩn bị hợp đồng - Công ty TNHH Thương mại Sao Ma','Công việc demo.','urgent','overdue','2026-10-12 12:00:00',NULL,NULL,'customer',8,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(13,1,'Theo dõi thanh toán - Công ty TNHH Dịch vụ Đại Dương','Công việc demo.','medium','pending','2026-10-24 10:00:00',NULL,NULL,'customer',20,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(14,1,'Gọi điện tư vấn - Sử Xuyến Phượng','Công việc demo.','high','in_progress','2026-10-23 15:00:00',NULL,NULL,'customer',16,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(15,1,'Theo dõi thanh toán - Doãn Như Giang','Công việc demo.','low','cancelled','2026-11-04 11:00:00',NULL,NULL,'customer',22,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(16,1,'Chuẩn bị hợp đồng - Công ty Cổ phần Logistics Trườ','Công việc demo.','medium','pending','2026-10-25 12:00:00',NULL,NULL,'customer',2,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(17,1,'Theo dõi thanh toán - Công ty TNHH Dịch vụ Đại Dương','Công việc demo.','medium','completed','2026-10-17 10:00:00','2026-10-16 03:00:00',NULL,'customer',20,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(18,1,'Theo dõi thanh toán - Công ty TNHH Dịch vụ Đại Dương','Công việc demo.','urgent','in_progress','2026-10-24 17:00:00',NULL,NULL,'customer',20,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(19,1,'Khảo sát nhu cầu - Công ty Cổ phần Logistics Trườ','Công việc demo.','urgent','pending','2026-11-03 08:00:00',NULL,NULL,'customer',2,4,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL),
(20,1,'Khảo sát nhu cầu - Công ty TNHH MTV Thực phẩm Hưn','Công việc demo.','urgent','overdue','2026-09-29 08:00:00',NULL,NULL,'customer',14,3,2,1,'2026-10-15 14:10:43','2026-10-15 14:10:43',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `ticket_replies` WRITE;
/*!40000 ALTER TABLE `ticket_replies` DISABLE KEYS */;
INSERT IGNORE INTO `ticket_replies` (`id`, `support_ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`) VALUES (1,1,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(2,2,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(3,3,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(4,4,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(5,5,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(6,6,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(7,7,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(8,8,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(9,9,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43'),
(10,10,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-15 14:10:43','2026-10-15 14:10:43');
/*!40000 ALTER TABLE `ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `username`, `email_verified_at`, `password`, `phone`, `position`, `avatar`, `department_id`, `status`, `is_platform_admin`, `locale`, `theme`, `current_organization_id`, `last_login_at`, `last_login_ip`, `is_demo`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `two_factor_last_step`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Trần Minh Quân','quantri@demo.orangecrm.local',NULL,NULL,'$2y$12$oxOy.nH0Ndeh.3Jnc8A5bu/bSNz7IshtXQsQYCTVKmZD9OKdG3QSq','0952082305','Quản trị hệ thống',NULL,1,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40',NULL),
(2,'Lê Thu Hà','truongkinhdoanh@demo.orangecrm.local',NULL,NULL,'$2y$12$xMf/MEHw7o/2.iDoguZpTOVUpul.b9o3qfuFYf3pT2LT6Kg4seV.6','0962560774','Trưởng phòng kinh doanh',NULL,2,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:40','2026-10-15 14:10:40',NULL),
(3,'Nguyễn Văn Nam','kinhdoanh1@demo.orangecrm.local',NULL,NULL,'$2y$12$LfOZLTPQcbZv4T3bJHvgkunUSB2byP7D3wrbHxhJb1JKODQNbbNNS','0973871898','Nhân viên kinh doanh',NULL,2,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(4,'Phạm Thị Lan','kinhdoanh2@demo.orangecrm.local',NULL,NULL,'$2y$12$8M3bQM7FxF7FRWUuv30ghuDyBKw7qnAaHIZFd4lse0ezrlTx9jycm','0988273336','Nhân viên kinh doanh',NULL,2,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(5,'Hoàng Đức Huy','marketing@demo.orangecrm.local',NULL,NULL,'$2y$12$psnS8oNRgJWKhOo2Kodc0ePNDAY0zljUT5fGpLrB889OroroVhQhe','0964617421','Chuyên viên marketing',NULL,3,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(6,'Vũ Ngọc Anh','hotro@demo.orangecrm.local',NULL,NULL,'$2y$12$9Qyv/Oxm377aYlcdITMio.JkKoejrDZpKFzidYcAxl2ZBGot2YN8G','0995491917','Chuyên viên CSKH',NULL,4,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL),
(7,'Đỗ Thanh Tùng','xem@demo.orangecrm.local',NULL,NULL,'$2y$12$ga56QTKoj4RE327kjfyoHO3zxQev0eXgq4stGYz8mB3bzlaEb1hnS','0931818397','Kế toán',NULL,5,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-15 14:10:41','2026-10-15 14:10:41',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

