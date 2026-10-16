-- ============================================================================
--  TOP CRM — Database schema + dữ liệu khởi tạo
--  Database : orangecrm_db  (MariaDB 10.11 / MySQL 8, InnoDB, utf8mb4)
--  Tạo lúc  : 16/10/2026 21:42  bằng scripts/generate-sql.ps1
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
CREATE TABLE IF NOT EXISTS `custom_field_definitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `entity` varchar(30) NOT NULL DEFAULT 'customer',
  `key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `custom_field_definitions_organization_id_entity_key_unique` (`organization_id`,`entity`,`key`),
  CONSTRAINT `custom_field_definitions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `country` char(2) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `type` enum('individual','business') NOT NULL DEFAULT 'individual',
  `lead_source_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','potential','vip','inactive') NOT NULL DEFAULT 'active',
  `segment` varchar(20) DEFAULT NULL,
  `lifecycle_stage` varchar(20) DEFAULT NULL,
  `lifecycle_changed_at` timestamp NULL DEFAULT NULL,
  `preferred_channel` varchar(20) DEFAULT NULL,
  `marketing_consent` tinyint(1) NOT NULL DEFAULT 0,
  `marketing_consent_at` timestamp NULL DEFAULT NULL,
  `marketing_consent_source` varchar(100) DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `merged_into_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `customers_merged_into_id_foreign` (`merged_into_id`),
  KEY `customers_organization_id_segment_index` (`organization_id`,`segment`),
  KEY `customers_organization_id_lifecycle_stage_index` (`organization_id`,`lifecycle_stage`),
  KEY `customers_organization_id_country_index` (`organization_id`,`country`),
  CONSTRAINT `customers_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_merged_into_id_foreign` FOREIGN KEY (`merged_into_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
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
  `type` enum('note','call','email','meeting','follow_up','other') NOT NULL DEFAULT 'note',
  `status` varchar(20) NOT NULL DEFAULT 'done',
  `subject` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `outcome` varchar(1000) DEFAULT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reminded_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `interactions_interactable_type_interactable_id_index` (`interactable_type`,`interactable_id`),
  KEY `interactions_user_id_foreign` (`user_id`),
  KEY `interactions_organization_id_index` (`organization_id`),
  KEY `interactions_organization_id_status_scheduled_at_index` (`organization_id`,`status`,`scheduled_at`),
  KEY `interactions_assigned_to_status_index` (`assigned_to`,`status`),
  CONSTRAINT `interactions_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `interactions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `interactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS `lead_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'secondary',
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_qualified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_stages_organization_id_key_unique` (`organization_id`,`key`),
  CONSTRAINT `lead_stages_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `status` varchar(50) NOT NULL DEFAULT 'new',
  `qualified_at` timestamp NULL DEFAULT NULL,
  `lost_reason_id` bigint(20) unsigned DEFAULT NULL,
  `lost_note` varchar(500) DEFAULT NULL,
  `score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `converted_customer_id` bigint(20) unsigned DEFAULT NULL,
  `converted_opportunity_id` bigint(20) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `marketing_consent` tinyint(1) NOT NULL DEFAULT 0,
  `marketing_consent_at` timestamp NULL DEFAULT NULL,
  `marketing_consent_source` varchar(100) DEFAULT NULL,
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
  KEY `leads_lost_reason_id_foreign` (`lost_reason_id`),
  KEY `leads_organization_id_status_index` (`organization_id`,`status`),
  CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_customer_id_foreign` FOREIGN KEY (`converted_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_converted_opportunity_id_foreign` FOREIGN KEY (`converted_opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lost_reason_id_foreign` FOREIGN KEY (`lost_reason_id`) REFERENCES `lost_reasons` (`id`) ON DELETE SET NULL,
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
CREATE TABLE IF NOT EXISTS `lost_reasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `applies_to` varchar(20) NOT NULL DEFAULT 'both',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lost_reasons_organization_id_name_unique` (`organization_id`,`name`),
  CONSTRAINT `lost_reasons_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `pipeline_id` bigint(20) unsigned NOT NULL,
  `pipeline_stage_id` bigint(20) unsigned NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `probability` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expected_close_date` date DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(500) DEFAULT NULL,
  `lost_reason_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `opportunities_lost_reason_id_foreign` (`lost_reason_id`),
  KEY `opportunities_pipeline_id_foreign` (`pipeline_id`),
  KEY `opportunities_organization_id_pipeline_id_index` (`organization_id`,`pipeline_id`),
  CONSTRAINT `opportunities_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_lost_reason_id_foreign` FOREIGN KEY (`lost_reason_id`) REFERENCES `lost_reasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `opportunities_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`),
  CONSTRAINT `opportunities_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `opportunity_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opportunity_items_opportunity_id_foreign` (`opportunity_id`),
  KEY `opportunity_items_product_id_foreign` (`product_id`),
  CONSTRAINT `opportunity_items_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opportunity_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS `organization_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_settings_organization_id_key_unique` (`organization_id`,`key`),
  CONSTRAINT `organization_settings_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `organization_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` enum('active','invited','suspended') NOT NULL DEFAULT 'active',
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_user_organization_id_user_id_unique` (`organization_id`,`user_id`),
  KEY `organization_user_user_id_foreign` (`user_id`),
  KEY `organization_user_department_id_foreign` (`department_id`),
  CONSTRAINT `organization_user_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
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
  `pipeline_id` bigint(20) unsigned NOT NULL,
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
  UNIQUE KEY `pipeline_stages_pipeline_slug_unique` (`pipeline_id`,`slug`),
  KEY `pipeline_stages_organization_id_index` (`organization_id`),
  CONSTRAINT `pipeline_stages_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `pipeline_stages_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `pipelines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pipelines_organization_id_name_unique` (`organization_id`,`name`),
  CONSTRAINT `pipelines_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS `search_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `query` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `search_histories_organization_id_user_id_query_unique` (`organization_id`,`user_id`,`query`),
  KEY `search_histories_user_id_updated_at_index` (`user_id`,`updated_at`),
  CONSTRAINT `search_histories_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `search_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
INSERT IGNORE INTO `campaign_leads` (`campaign_id`, `lead_id`, `created_at`) VALUES (1,11,'2026-01-19 10:54:00'),
(1,14,'2026-08-27 05:30:00'),
(1,19,'2026-07-23 06:22:00'),
(1,20,'2026-09-23 03:18:00'),
(1,22,'2026-04-16 02:24:00'),
(1,24,'2026-08-29 07:30:00'),
(1,25,'2026-08-06 01:19:00'),
(1,26,'2026-03-22 02:46:00'),
(1,28,'2026-01-18 08:36:00'),
(2,3,'2026-09-08 04:08:00'),
(2,4,'2026-01-28 08:18:00'),
(2,9,'2026-02-24 06:58:00'),
(2,13,'2026-09-21 10:16:00'),
(2,16,'2026-08-23 08:03:00'),
(3,15,'2026-05-04 04:46:00'),
(3,23,'2026-02-20 02:56:00'),
(4,1,'2026-02-15 07:24:00'),
(4,6,'2026-07-05 04:19:00'),
(4,7,'2026-04-12 10:27:00'),
(4,12,'2025-12-11 06:53:00'),
(4,17,'2026-09-14 10:47:00'),
(5,2,'2026-04-27 05:03:00'),
(5,5,'2026-07-27 05:07:00'),
(5,8,'2026-04-18 04:39:00'),
(5,10,'2026-07-10 03:36:00'),
(5,18,'2026-02-17 08:22:00'),
(5,21,'2026-06-27 03:54:00'),
(5,27,'2026-07-14 06:45:00'),
(5,29,'2026-07-18 05:36:00'),
(5,30,'2025-12-22 10:49:00');
/*!40000 ALTER TABLE `campaign_leads` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
INSERT IGNORE INTO `campaigns` (`id`, `organization_id`, `name`, `type`, `start_date`, `end_date`, `budget`, `target_leads`, `status`, `description`, `owner_id`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Ra mắt CRM Pro Q1','event','2025-12-20','2026-02-18',50000000.00,40,'completed','Chiến dịch demo.',5,5,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(2,1,'Quảng cáo Facebook mùa hè','social','2026-05-19','2026-07-18',30000000.00,60,'completed','Chiến dịch demo.',5,5,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(3,1,'Google Ads doanh nghiệp SME','ads','2026-08-17','2026-11-15',45000000.00,80,'active','Chiến dịch demo.',5,5,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(4,1,'Email chăm sóc khách hàng cũ','email','2026-09-16','2026-12-15',5000000.00,30,'active','Chiến dịch demo.',5,5,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(5,1,'Hội thảo chuyển đổi số cuối năm','event','2026-11-05','2026-12-05',80000000.00,100,'planned','Chiến dịch demo.',5,5,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL);
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT IGNORE INTO `contacts` (`id`, `organization_id`, `customer_id`, `full_name`, `position`, `email`, `phone`, `company`, `notes`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,1,'Phí Hoàn Thêu','Trưởng phòng IT','lienhe1@demo.orangecrm.local','0940066563','Ông Hoan Hòa',NULL,3,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(2,1,2,'Từ Chiêu Liêm','Giám đốc','lienhe2@demo.orangecrm.local','0941196576','Công ty Cổ phần Logistics Trường Sơn',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(3,1,3,'Lô Xuyến Lệ','Trưởng phòng mua hàng','lienhe3@demo.orangecrm.local','0916079650','Công ty TNHH MTV Logistics Hòa Bình',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(4,1,4,'Bửu Lộc Bảo','Giám đốc','lienhe4@demo.orangecrm.local','0994679102','Tập đoàn Thực phẩm Sao Mai',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(5,1,5,'Bảo Quân Nghĩa','Trưởng phòng mua hàng','lienhe5@demo.orangecrm.local','0950784437','Tập đoàn Thực phẩm Nam Việt',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(6,1,6,'Khu Hỷ Nhi','Giám đốc','lienhe6@demo.orangecrm.local','0936038797','Công ty Cổ phần Thương mại Hòa Bình',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(7,1,7,'Nguyễn Trân Đại','Trưởng phòng IT','lienhe7@demo.orangecrm.local','0925117432','Mang Uyển Quân',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(8,1,8,'Thái Sỹ San','Trưởng phòng IT','lienhe8@demo.orangecrm.local','0927396489','Công ty TNHH Thương mại Sao Mai',NULL,3,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(9,1,9,'Chử Di Hỷ','Giám đốc','lienhe9@demo.orangecrm.local','0998691390','Công ty TNHH MTV Thương mại Việt Tiến',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(10,1,10,'Chương Trinh Kiên','Kế toán trưởng','lienhe10@demo.orangecrm.local','0916916165','Tập đoàn Thực phẩm An Khang',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(11,1,11,'Mạch Xuyến Phụng','Trưởng phòng IT','lienhe11@demo.orangecrm.local','0958920416','Công ty Cổ phần Dịch vụ Trường Sơn',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(12,1,12,'Triệu Hoàn Miên','Kế toán trưởng','lienhe12@demo.orangecrm.local','0931305193','Công ty TNHH MTV Thương mại Tân Á',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(13,1,13,'Đỗ Nhân Kiên','Trưởng phòng mua hàng','lienhe13@demo.orangecrm.local','0981664394','Trưng Tuấn Hảo',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(14,1,14,'Tông Phi Định','Trưởng phòng IT','lienhe14@demo.orangecrm.local','0957960423','Công ty TNHH MTV Thực phẩm Hưng Thịnh',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(15,1,15,'Vi Thuận Triệu','Trưởng phòng mua hàng','lienhe15@demo.orangecrm.local','0955939693','Tập đoàn Logistics Thành Công',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(16,1,16,'Cung Kiều Khôi','Trưởng phòng mua hàng','lienhe16@demo.orangecrm.local','0997266062','Công ty TNHH Sản xuất Tân Á',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(17,1,17,'Bảo Độ Vĩnh','Kế toán trưởng','lienhe17@demo.orangecrm.local','0930374249','Công ty TNHH Dịch vụ Hưng Thịnh',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(18,1,18,'Kiều Uy Phụng','Trưởng phòng IT','lienhe18@demo.orangecrm.local','0939145653','Tập đoàn Thực phẩm Tân Á',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(19,1,19,'Chu Sáng Linh','Kế toán trưởng','lienhe19@demo.orangecrm.local','0970813813','Trần Xuân Tiển',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(20,1,20,'Hình Lâm Chi','Trưởng phòng IT','lienhe20@demo.orangecrm.local','0939153277','Công ty TNHH Dịch vụ Đại Dương',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(21,1,21,'Chiêm Hợp Vọng','Trưởng phòng mua hàng','lienhe21@demo.orangecrm.local','0982252626','Công ty Cổ phần Công nghệ Đại Dương',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(22,1,22,'Phí Tuấn Thủy','Giám đốc','lienhe22@demo.orangecrm.local','0938928996','Công ty TNHH Dịch vụ Đại Dương',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(23,1,23,'Đậu Ngọc Lân','Trưởng phòng IT','lienhe23@demo.orangecrm.local','0992540246','Công ty Cổ phần Logistics Thành Công',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(24,1,24,'Ánh Hòa Trầm','Giám đốc','lienhe24@demo.orangecrm.local','0952314941','Công ty Cổ phần Xây dựng An Khang',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(25,1,25,'Hạ Ty Hà','Trưởng phòng IT','lienhe25@demo.orangecrm.local','0930612243','Khuất Giang Nam',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(26,1,26,'Đặng Dân Yên','Trưởng phòng mua hàng','lienhe26@demo.orangecrm.local','0954798889','Công ty TNHH MTV Sản xuất Đại Dương',NULL,3,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(27,1,27,'Vũ Ngọc Lập','Giám đốc','lienhe27@demo.orangecrm.local','0968623142','Tập đoàn Sản xuất Hoàng Long',NULL,3,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(28,1,28,'Hoàng Tài Giao','Trưởng phòng IT','lienhe28@demo.orangecrm.local','0990663611','Công ty TNHH Công nghệ Bình Minh',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(29,1,29,'Hy Hiền Lập','Trưởng phòng IT','lienhe29@demo.orangecrm.local','0918872351','Tập đoàn Xây dựng Bình Minh',NULL,2,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(30,1,30,'Mâu Bảo Vy','Giám đốc','lienhe30@demo.orangecrm.local','0987312097','Tập đoàn Sản xuất Đại Dương',NULL,4,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL);
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `custom_field_definitions` WRITE;
/*!40000 ALTER TABLE `custom_field_definitions` DISABLE KEYS */;
/*!40000 ALTER TABLE `custom_field_definitions` ENABLE KEYS */;
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
INSERT IGNORE INTO `customers` (`id`, `organization_id`, `code`, `name`, `phone`, `email`, `address`, `country`, `company`, `type`, `lead_source_id`, `status`, `segment`, `lifecycle_stage`, `lifecycle_changed_at`, `preferred_channel`, `marketing_consent`, `marketing_consent_at`, `marketing_consent_source`, `assigned_to`, `created_by`, `updated_by`, `notes`, `custom_fields`, `merged_into_id`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'KH00001','Ông Hoan Hòa','0987170998','khachhang1@demo.orangecrm.local','7249 Phố Ty Cát Diệu, Xã Trường Nhuận, Quận Cát Cam\nHải Phòng','VN',NULL,'individual',2,'inactive','retail','prospect',NULL,'phone',0,NULL,NULL,3,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-03-16 01:19:00','2026-10-16 14:42:23',NULL),
(2,1,'KH00002','Công ty Cổ phần Logistics Trường Sơn','0996921891','khachhang2@demo.orangecrm.local','8, Ấp Nhạn, Xã 8, Quận Ly Thi\nHậu Giang','VN',NULL,'business',1,'active','sme','new',NULL,'email',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-05-14 08:12:00','2026-10-16 14:42:23',NULL),
(3,1,'KH00003','Công ty TNHH MTV Logistics Hòa Bình','0980274382','khachhang3@demo.orangecrm.local','82, Thôn 6, Xã Phi Thanh, Huyện Xa\nSơn La','VN',NULL,'business',2,'active','enterprise','active',NULL,'sms',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-05-05 10:16:00','2026-10-16 14:42:23',NULL),
(4,1,'KH00004','Hoàng Hùng Cúc','0912445407','khachhang4@demo.orangecrm.local','7198 Phố Kha, Thôn An Quân, Quận Cầm\nĐà Nẵng','VN','Tập đoàn Thực phẩm Sao Mai','individual',6,'active','partner','loyal',NULL,'zalo',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-01-16 10:39:00','2026-10-16 14:42:23',NULL),
(5,1,'KH00005','Tập đoàn Thực phẩm Nam Việt','0972110656','khachhang5@demo.orangecrm.local','93 Phố Lại Yên Thanh, Phường Hiên Hạ, Quận 4\nBắc Kạn','VN',NULL,'business',4,'vip','government','at_risk',NULL,'in_person',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-06-11 01:05:00','2026-10-16 14:42:23',NULL),
(6,1,'KH00006','Công ty Cổ phần Thương mại Hòa Bình','0999814588','khachhang6@demo.orangecrm.local','8 Phố An, Xã Tân, Huyện Thào Mỹ\nTuyên Quang','VN',NULL,'business',4,'active','retail','churned',NULL,'phone',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-08-15 05:37:00','2026-10-16 14:42:23',NULL),
(7,1,'KH00007','Mang Uyển Quân','0941744345','khachhang7@demo.orangecrm.local','5945 Phố Lò, Xã Lương, Huyện Bồ Nhi\nHà Nội','VN',NULL,'individual',6,'vip','sme','prospect',NULL,'email',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-07-11 06:35:00','2026-10-16 14:42:23',NULL),
(8,1,'KH00008','Công ty TNHH Thương mại Sao Mai','0993802851','khachhang8@demo.orangecrm.local','7206 Phố Ma Lĩnh Lan, Phường Hàn Ngôn Dương, Huyện Đài Ngân\nHải Phòng','VN',NULL,'business',9,'vip','enterprise','new',NULL,'sms',0,NULL,NULL,3,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-03-06 01:26:00','2026-10-16 14:42:23',NULL),
(9,1,'KH00009','Công ty TNHH MTV Thương mại Việt Tiến','0922426244','khachhang9@demo.orangecrm.local','728 Phố Kim Hữu Thịnh, Xã Bình, Huyện 55\nHòa Bình','VN',NULL,'business',7,'active','partner','active',NULL,'zalo',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-11-09 02:50:00','2026-10-16 14:42:23',NULL),
(10,1,'KH00010','Tào Tường Thông','0968338331','khachhang10@demo.orangecrm.local','174 Phố Bồ Cường Anh, Phường Phan Thiên Lai, Huyện Xuyến Đức\nKon Tum','VN','Tập đoàn Thực phẩm An Khang','individual',7,'potential','government','loyal',NULL,'in_person',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-11-10 05:12:00','2026-10-16 14:42:23',NULL),
(11,1,'KH00011','Công ty Cổ phần Dịch vụ Trường Sơn','0924739066','khachhang11@demo.orangecrm.local','8939, Ấp Giao Tông, Xã Tú Danh, Huyện Tòng Vượng Yên\nTrà Vinh','VN',NULL,'business',1,'active','retail','at_risk',NULL,'phone',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-06-14 08:26:00','2026-10-16 14:42:23',NULL),
(12,1,'KH00012','Công ty TNHH MTV Thương mại Tân Á','0945718800','khachhang12@demo.orangecrm.local','6592, Thôn Thịnh Tín Xuân, Phường Khổng Lĩnh Vinh, Quận Nhiệm\nĐiện Biên','VN',NULL,'business',7,'vip','sme','churned',NULL,'email',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-01-08 07:01:00','2026-10-16 14:42:23',NULL),
(13,1,'KH00013','Trưng Tuấn Hảo','0927052592','khachhang13@demo.orangecrm.local','920, Thôn Ma, Thôn Hồng Đình, Quận Sa\nĐiện Biên','VN',NULL,'individual',9,'active','enterprise','prospect',NULL,'sms',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-05-14 07:59:00','2026-10-16 14:42:23',NULL),
(14,1,'KH00014','Công ty TNHH MTV Thực phẩm Hưng Thịnh','0914873094','khachhang14@demo.orangecrm.local','84 Phố Huỳnh Nhật Tài, Phường 08, Quận Liễu Thục\nThanh Hóa','VN',NULL,'business',3,'inactive','partner','new',NULL,'zalo',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-09-20 03:53:00','2026-10-16 14:42:23',NULL),
(15,1,'KH00015','Tập đoàn Logistics Thành Công','0922725920','khachhang15@demo.orangecrm.local','508, Ấp Thi Vương, Phường Phúc Tống, Huyện Bùi\nKon Tum','VN',NULL,'business',4,'active','government','active',NULL,'in_person',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-06-05 07:53:00','2026-10-16 14:42:23',NULL),
(16,1,'KH00016','Sử Xuyến Phượng','0910140404','khachhang16@demo.orangecrm.local','12 Phố Phước, Xã Luận Từ, Huyện Trà Hiếu\nHồ Chí Minh','VN','Công ty TNHH Sản xuất Tân Á','individual',1,'active','retail','loyal',NULL,'phone',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-08-16 09:31:00','2026-10-16 14:42:24',NULL),
(17,1,'KH00017','Công ty TNHH Dịch vụ Hưng Thịnh','0927713402','khachhang17@demo.orangecrm.local','1 Phố Chung, Phường Hợp Lô, Quận Thiều\nCao Bằng','VN',NULL,'business',5,'vip','sme','at_risk',NULL,'email',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-01-12 06:36:00','2026-10-16 14:42:24',NULL),
(18,1,'KH00018','Tập đoàn Thực phẩm Tân Á','0910300037','khachhang18@demo.orangecrm.local','8, Ấp Tùng Luật, Phường Thảo Ca, Quận Yên\nLong An','VN',NULL,'business',5,'potential','enterprise','churned',NULL,'sms',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-02-24 10:46:00','2026-10-16 14:42:24',NULL),
(19,1,'KH00019','Trần Xuân Tiển','0985681066','khachhang19@demo.orangecrm.local','7002 Phố Khúc Phúc Thục, Phường Thu Đan, Huyện Đan Khuê\nCần Thơ','VN',NULL,'individual',3,'inactive','partner','prospect',NULL,'zalo',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-09-04 07:02:00','2026-10-16 14:42:24',NULL),
(20,1,'KH00020','Công ty TNHH Dịch vụ Đại Dương','0958099409','khachhang20@demo.orangecrm.local','5020 Phố Lâm, Phường Sỹ, Huyện Phan\nBà Rịa - Vũng Tàu','VN',NULL,'business',8,'active','government','new',NULL,'in_person',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-11-24 09:28:00','2026-10-16 14:42:24',NULL),
(21,1,'KH00021','Công ty Cổ phần Công nghệ Đại Dương','0974902804','khachhang21@demo.orangecrm.local','912, Ấp Võ, Phường Kỳ, Huyện 3\nKiên Giang','VN',NULL,'business',3,'active','retail','active',NULL,'phone',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-08-11 07:55:00','2026-10-16 14:42:24',NULL),
(22,1,'KH00022','Doãn Như Giang','0962007634','khachhang22@demo.orangecrm.local','80 Phố Mạch Hiệp Mi, Phường Lều Thục Ca, Huyện 70\nHải Phòng','VN','Công ty TNHH Dịch vụ Đại Dương','individual',6,'active','sme','loyal',NULL,'email',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-11-27 07:27:00','2026-10-16 14:42:24',NULL),
(23,1,'KH00023','Công ty Cổ phần Logistics Thành Công','0955216935','khachhang23@demo.orangecrm.local','7928 Phố Kha Nữ Hào, Ấp Vĩ Hồng, Quận Chiêm Huy Hoài\nHải Phòng','VN',NULL,'business',8,'vip','enterprise','at_risk',NULL,'sms',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-09-12 10:33:00','2026-10-16 14:42:24',NULL),
(24,1,'KH00024','Công ty Cổ phần Xây dựng An Khang','0923412641','khachhang24@demo.orangecrm.local','4 Phố Hạnh, Xã Ngôn Ca, Huyện Kha\nHưng Yên','VN',NULL,'business',5,'inactive','partner','churned',NULL,'zalo',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-09-03 01:39:00','2026-10-16 14:42:24',NULL),
(25,1,'KH00025','Khuất Giang Nam','0979833082','khachhang25@demo.orangecrm.local','52 Phố Danh Cát Du, Phường 38, Quận Hợp\nBình Định','VN',NULL,'individual',7,'inactive','government','prospect',NULL,'in_person',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-12-30 02:57:00','2026-10-16 14:42:24',NULL),
(26,1,'KH00026','Công ty TNHH MTV Sản xuất Đại Dương','0959846276','khachhang26@demo.orangecrm.local','301 Phố Cổ Thạc Châu, Phường Phương, Huyện Bình Âu\nHải Phòng','VN',NULL,'business',8,'vip','retail','new',NULL,'phone',0,NULL,NULL,3,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2025-11-27 07:44:00','2026-10-16 14:42:24',NULL),
(27,1,'KH00027','Tập đoàn Sản xuất Hoàng Long','0917164802','khachhang27@demo.orangecrm.local','7957 Phố Ngạn, Ấp Thanh Tâm, Huyện Hồng\nVĩnh Long','VN',NULL,'business',9,'vip','sme','active',NULL,'email',0,NULL,NULL,3,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-04-15 10:42:00','2026-10-16 14:42:24',NULL),
(28,1,'KH00028','Diệp Quế Hòa','0943173054','khachhang28@demo.orangecrm.local','57, Ấp Hạnh Quản, Phường Châu, Quận 4\nBến Tre','VN','Công ty TNHH Công nghệ Bình Minh','individual',6,'potential','enterprise','loyal',NULL,'sms',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-07-02 08:43:00','2026-10-16 14:42:24',NULL),
(29,1,'KH00029','Tập đoàn Xây dựng Bình Minh','0910414899','khachhang29@demo.orangecrm.local','237 Phố Đinh Hoa Lâm, Phường 90, Quận Dụng Viên\nVĩnh Long','VN',NULL,'business',3,'potential','partner','at_risk',NULL,'zalo',0,NULL,NULL,2,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-01-29 01:41:00','2026-10-16 14:42:24',NULL),
(30,1,'KH00030','Tập đoàn Sản xuất Đại Dương','0948497293','khachhang30@demo.orangecrm.local','599 Phố Dân, Xã Ty Thanh Quân, Huyện 02\nCần Thơ','VN',NULL,'business',2,'active','government','churned',NULL,'in_person',0,NULL,NULL,4,2,NULL,'Khách hàng demo.',NULL,NULL,1,'2026-08-19 06:37:00','2026-10-16 14:42:24',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT IGNORE INTO `departments` (`id`, `organization_id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES (1,1,'Ban Giám đốc','BGD',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,'Phòng Kinh doanh','KD',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,'Phòng Marketing','MKT',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,1,'Phòng Chăm sóc khách hàng','CSKH',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,1,'Phòng Kế toán','KT',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `interactions` WRITE;
/*!40000 ALTER TABLE `interactions` DISABLE KEYS */;
INSERT IGNORE INTO `interactions` (`id`, `organization_id`, `interactable_type`, `interactable_id`, `type`, `status`, `subject`, `content`, `outcome`, `occurred_at`, `scheduled_at`, `completed_at`, `reminded_at`, `user_id`, `assigned_to`, `created_at`, `updated_at`) VALUES (1,1,'customer',1,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-03-17 01:19:00',NULL,NULL,NULL,3,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(2,1,'customer',2,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-05-17 08:12:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(3,1,'customer',3,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-05-05 10:16:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(4,1,'customer',4,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-01-19 10:39:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(5,1,'customer',5,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-06-12 01:05:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(6,1,'customer',6,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-08-17 05:37:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(7,1,'customer',7,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-07-12 06:35:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(8,1,'customer',8,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-03-11 01:26:00',NULL,NULL,NULL,3,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(9,1,'customer',9,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-11-12 02:50:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(10,1,'customer',10,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-11-14 05:12:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(11,1,'customer',11,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-06-16 08:26:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(12,1,'customer',12,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-01-09 07:01:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(13,1,'customer',13,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-05-15 07:59:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(14,1,'customer',14,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-09-24 03:53:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(15,1,'customer',15,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-06-10 07:53:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(16,1,'customer',16,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-08-21 09:31:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(17,1,'customer',17,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-01-14 06:36:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(18,1,'customer',18,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-02-28 10:46:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(19,1,'customer',19,'call','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-09-07 07:02:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(20,1,'customer',20,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-11-29 09:28:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(21,1,'customer',21,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-08-16 07:55:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(22,1,'customer',22,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-11-30 07:27:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(23,1,'customer',23,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-09-17 10:33:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(24,1,'customer',24,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-09-05 01:39:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(25,1,'customer',25,'meeting','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-12-31 02:57:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(26,1,'customer',26,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2025-11-27 07:44:00',NULL,NULL,NULL,3,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(27,1,'customer',27,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-04-16 10:42:00',NULL,NULL,NULL,3,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(28,1,'customer',28,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-07-05 08:43:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(29,1,'customer',29,'note','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-02-01 01:41:00',NULL,NULL,NULL,2,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(30,1,'customer',30,'email','done','Trao đổi nhu cầu','Nội dung tương tác demo với khách hàng.',NULL,'2026-08-22 06:37:00',NULL,NULL,NULL,4,NULL,'2026-10-16 14:42:22','2026-10-16 14:42:22'),
(31,1,'customer',1,'call','planned','Gọi lại tư vấn gói (demo)',NULL,NULL,NULL,'2026-10-17 02:00:00',NULL,NULL,3,3,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(32,1,'customer',4,'meeting','planned','Hẹn demo sản phẩm (demo)',NULL,NULL,NULL,'2026-10-18 03:00:00',NULL,NULL,4,4,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(33,1,'customer',7,'follow_up','planned','Theo dõi báo giá (demo)',NULL,NULL,NULL,'2026-10-19 04:00:00',NULL,NULL,4,4,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(34,1,'customer',10,'email','planned','Gửi tài liệu (demo)',NULL,NULL,NULL,'2026-10-20 05:00:00',NULL,NULL,2,2,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(35,1,'customer',13,'call','planned','Gọi lại tư vấn gói (demo)',NULL,NULL,NULL,'2026-10-21 06:00:00',NULL,NULL,4,4,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(36,1,'customer',16,'meeting','planned','Hẹn demo sản phẩm (demo)',NULL,NULL,NULL,'2026-10-22 07:00:00',NULL,NULL,2,2,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(37,1,'customer',19,'follow_up','planned','Theo dõi báo giá (demo)',NULL,NULL,NULL,'2026-10-14 08:00:00',NULL,NULL,4,4,'2026-10-16 14:42:24','2026-10-16 14:42:24'),
(38,1,'customer',22,'email','planned','Gửi tài liệu (demo)',NULL,NULL,NULL,'2026-10-13 09:00:00',NULL,NULL,4,4,'2026-10-16 14:42:24','2026-10-16 14:42:24');
/*!40000 ALTER TABLE `interactions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT IGNORE INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,2,'Phần mềm CRM - Gói Chuyên nghiệp',3.00,25000000.00,10.00,67500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,2,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,3,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,4,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,5,5,'Phát triển tính năng theo yêu cầu',2.00,800000.00,10.00,1440000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,6,5,'Phát triển tính năng theo yêu cầu',1.00,800000.00,5.00,760000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,6,8,'Đào tạo người dùng',3.00,5000000.00,0.00,15000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(10,7,1,'Phần mềm CRM - Gói Tiêu chuẩn',2.00,12000000.00,0.00,24000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(11,7,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(12,8,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT IGNORE INTO `invoices` (`id`, `organization_id`, `number`, `customer_id`, `sales_order_id`, `issue_date`, `due_date`, `status`, `payment_status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `amount_paid`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'HD00001',30,1,'2026-08-31','2026-09-15','issued','partial',76050000.00,0.00,10.00,7605000.00,83655000.00,41827500.00,NULL,4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(2,1,'HD00002',23,2,'2026-05-07','2026-05-22','issued','paid',40500000.00,0.00,10.00,4050000.00,44550000.00,44550000.00,NULL,4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(3,1,'HD00003',25,3,'2026-03-12','2026-03-27','issued','paid',8550000.00,0.00,10.00,855000.00,9405000.00,9405000.00,NULL,4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(4,1,'HD00004',5,4,'2026-09-20','2026-10-05','issued','partial',5000000.00,0.00,10.00,500000.00,5500000.00,2750000.00,NULL,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(5,1,'HD00005',29,5,'2026-04-21','2026-05-06','issued','paid',1440000.00,0.00,10.00,144000.00,1584000.00,1584000.00,NULL,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(6,1,'HD00006',27,6,'2025-12-26','2026-01-10','issued','paid',101260000.00,0.00,10.00,10126000.00,111386000.00,111386000.00,NULL,3,3,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(7,1,'HD00007',29,7,'2026-05-18','2026-06-02','issued','unpaid',64500000.00,0.00,10.00,6450000.00,70950000.00,0.00,NULL,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(8,1,'HD00008',7,8,'2026-04-17','2026-05-02','issued','unpaid',5000000.00,0.00,10.00,500000.00,5500000.00,0.00,NULL,4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `lead_sources` WRITE;
/*!40000 ALTER TABLE `lead_sources` DISABLE KEYS */;
INSERT IGNORE INTO `lead_sources` (`id`, `organization_id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (1,1,'Website',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,'Facebook',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,'Zalo',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,1,'Google Ads',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,1,'Giới thiệu',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(6,1,'Sự kiện',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(7,1,'Gọi điện',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(8,1,'Email',1,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(9,1,'Khác',1,'2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `lead_sources` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `lead_stages` WRITE;
/*!40000 ALTER TABLE `lead_stages` DISABLE KEYS */;
INSERT IGNORE INTO `lead_stages` (`id`, `organization_id`, `key`, `name`, `color`, `position`, `is_system`, `is_qualified`, `created_at`, `updated_at`) VALUES (1,1,'new','Mới','info',1,0,0,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(2,1,'contacted','Đã liên hệ','primary',2,0,0,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(3,1,'qualified','Đủ điều kiện','warning',3,0,1,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(4,1,'proposal','Đàm phán','orange',4,0,0,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(5,1,'converted','Đã chuyển đổi','success',5,1,0,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(6,1,'lost','Thất bại','danger',6,1,0,'2026-10-16 14:42:17','2026-10-16 14:42:17');
/*!40000 ALTER TABLE `lead_stages` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT IGNORE INTO `leads` (`id`, `organization_id`, `code`, `name`, `company`, `email`, `phone`, `lead_source_id`, `status`, `qualified_at`, `lost_reason_id`, `lost_note`, `score`, `estimated_value`, `assigned_to`, `created_by`, `converted_customer_id`, `converted_opportunity_id`, `converted_at`, `notes`, `marketing_consent`, `marketing_consent_at`, `marketing_consent_source`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'LD00001','Trác Vy Lai','Công ty TNHH Logistics Việt Tiến','lead1@demo.orangecrm.local','0329532340',9,'new',NULL,NULL,NULL,83,114000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-02-15 07:24:00','2026-02-15 07:24:00',NULL),
(2,1,'LD00002','Nhữ Lạc Thương','Công ty TNHH MTV Thương mại Đại Dương','lead2@demo.orangecrm.local','0343543332',9,'new',NULL,NULL,NULL,73,6000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-04-27 05:03:00','2026-04-27 05:03:00',NULL),
(3,1,'LD00003','Bì Hà Uyển','Công ty TNHH MTV Dịch vụ Việt Tiến','lead3@demo.orangecrm.local','0388777634',6,'contacted',NULL,NULL,NULL,77,66000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-09-08 04:08:00','2026-09-08 04:08:00',NULL),
(4,1,'LD00004','Uông Hằng Giáp','Công ty Cổ phần Công nghệ Hoàng Long','lead4@demo.orangecrm.local','0344880316',4,'contacted',NULL,NULL,NULL,53,29000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-01-28 08:18:00','2026-01-28 08:18:00',NULL),
(5,1,'LD00005','Trưng Diệu Luận','Công ty TNHH MTV Sản xuất Đại Dương','lead5@demo.orangecrm.local','0379647561',5,'qualified','2026-07-30 05:07:00',NULL,NULL,64,91000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-27 05:07:00','2026-10-16 14:42:24',NULL),
(6,1,'LD00006','Tiêu Thịnh Lễ','Tập đoàn Xây dựng Hoàng Long','lead6@demo.orangecrm.local','0358976027',1,'proposal',NULL,NULL,NULL,33,27000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-05 04:19:00','2026-07-05 04:19:00',NULL),
(7,1,'LD00007','Ma Nhã Mỹ','Công ty Cổ phần Thương mại Việt Tiến','lead7@demo.orangecrm.local','0367143741',8,'converted','2026-04-13 10:27:00',NULL,NULL,13,106000000.00,3,5,15,NULL,'2026-04-15 10:27:00','Lead demo.',0,NULL,NULL,1,'2026-04-12 10:27:00','2026-10-16 14:42:24',NULL),
(8,1,'LD00008','Diêm Sương Thiện','Công ty TNHH MTV Logistics Hòa Bình','lead8@demo.orangecrm.local','0373545167',2,'lost',NULL,1,'Lý do demo.',75,71000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-04-18 04:39:00','2026-10-16 14:42:24',NULL),
(9,1,'LD00009','Mạc Trúc Phong','Tập đoàn Công nghệ An Khang','lead9@demo.orangecrm.local','0359110732',6,'new',NULL,NULL,NULL,73,60000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-02-24 06:58:00','2026-02-24 06:58:00',NULL),
(10,1,'LD00010','Thân Trung Nhi','Công ty TNHH MTV Sản xuất Bình Minh','lead10@demo.orangecrm.local','0364899923',8,'new',NULL,NULL,NULL,33,43000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-10 03:36:00','2026-07-10 03:36:00',NULL),
(11,1,'LD00011','Thạch Dân Thúy','Tập đoàn Thương mại Hòa Bình','lead11@demo.orangecrm.local','0395289835',6,'contacted',NULL,NULL,NULL,45,115000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-01-19 10:54:00','2026-01-19 10:54:00',NULL),
(12,1,'LD00012','Cự Khuyên Huấn','Công ty TNHH MTV Logistics Hoàng Long','lead12@demo.orangecrm.local','0362076308',7,'contacted',NULL,NULL,NULL,63,103000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2025-12-11 06:53:00','2025-12-11 06:53:00',NULL),
(13,1,'LD00013','Bạc Huyền Khanh','Công ty TNHH Thực phẩm Phú Thịnh','lead13@demo.orangecrm.local','0349581250',2,'qualified','2026-09-24 10:16:00',NULL,NULL,87,83000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-09-21 10:16:00','2026-10-16 14:42:24',NULL),
(14,1,'LD00014','Thôi Tú Nhiên','Công ty TNHH Công nghệ Hòa Bình','lead14@demo.orangecrm.local','0362531211',3,'proposal',NULL,NULL,NULL,78,7000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-08-27 05:30:00','2026-08-27 05:30:00',NULL),
(15,1,'LD00015','Lữ Tuyết Bình','Công ty TNHH Logistics Việt Tiến','lead15@demo.orangecrm.local','0338332101',2,'converted','2026-05-08 04:46:00',NULL,NULL,84,103000000.00,2,5,27,NULL,'2026-05-10 04:46:00','Lead demo.',0,NULL,NULL,1,'2026-05-04 04:46:00','2026-10-16 14:42:24',NULL),
(16,1,'LD00016','Hy Ca Thông','Tập đoàn Logistics Kim Ngân','lead16@demo.orangecrm.local','0398568291',5,'lost',NULL,1,'Lý do demo.',34,118000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-08-23 08:03:00','2026-10-16 14:42:24',NULL),
(17,1,'LD00017','Đới Diệu Du','Công ty Cổ phần Logistics Đại Dương','lead17@demo.orangecrm.local','0341190811',7,'new',NULL,NULL,NULL,67,114000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-09-14 10:47:00','2026-09-14 10:47:00',NULL),
(18,1,'LD00018','Hình Hồng Sương','Công ty TNHH Logistics Nam Việt','lead18@demo.orangecrm.local','0313372341',4,'new',NULL,NULL,NULL,71,44000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-02-17 08:22:00','2026-02-17 08:22:00',NULL),
(19,1,'LD00019','Bàng Lâm Ngôn','Công ty TNHH MTV Công nghệ Hòa Bình','lead19@demo.orangecrm.local','0327090968',2,'contacted',NULL,NULL,NULL,35,91000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-23 06:22:00','2026-07-23 06:22:00',NULL),
(20,1,'LD00020','Đái Đan Tùng','Công ty TNHH MTV Logistics Đại Dương','lead20@demo.orangecrm.local','0338313952',7,'contacted',NULL,NULL,NULL,10,22000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-09-23 03:18:00','2026-09-23 03:18:00',NULL),
(21,1,'LD00021','Đinh Tuệ Nguyệt','Tập đoàn Công nghệ Trường Sơn','lead21@demo.orangecrm.local','0391701988',7,'qualified','2026-06-30 03:54:00',NULL,NULL,45,82000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-06-27 03:54:00','2026-10-16 14:42:24',NULL),
(22,1,'LD00022','Diệp Lâm Hoài','Công ty TNHH MTV Xây dựng Trường Sơn','lead22@demo.orangecrm.local','0371593183',9,'proposal',NULL,NULL,NULL,21,82000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-04-16 02:24:00','2026-04-16 02:24:00',NULL),
(23,1,'LD00023','Trác Vỹ Hải','Công ty TNHH Công nghệ An Khang','lead23@demo.orangecrm.local','0335536034',2,'converted','2026-03-02 02:56:00',NULL,NULL,58,102000000.00,2,5,15,NULL,'2026-03-04 02:56:00','Lead demo.',0,NULL,NULL,1,'2026-02-20 02:56:00','2026-10-16 14:42:24',NULL),
(24,1,'LD00024','Phùng Giáp Trang','Công ty TNHH MTV Thực phẩm Kim Ngân','lead24@demo.orangecrm.local','0324921292',1,'lost',NULL,1,'Lý do demo.',26,57000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-08-29 07:30:00','2026-10-16 14:42:24',NULL),
(25,1,'LD00025','Liễu Ân Mạnh','Công ty Cổ phần Sản xuất Nam Việt','lead25@demo.orangecrm.local','0335863999',5,'new',NULL,NULL,NULL,53,63000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-08-06 01:19:00','2026-08-06 01:19:00',NULL),
(26,1,'LD00026','Sử Giang Chinh','Tập đoàn Sản xuất Đại Dương','lead26@demo.orangecrm.local','0371198710',1,'new',NULL,NULL,NULL,26,81000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-03-22 02:46:00','2026-03-22 02:46:00',NULL),
(27,1,'LD00027','Hoa Giáp Liên','Công ty Cổ phần Thương mại Tân Á','lead27@demo.orangecrm.local','0381713341',3,'contacted',NULL,NULL,NULL,27,29000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-14 06:45:00','2026-07-14 06:45:00',NULL),
(28,1,'LD00028','Đậu Đan Tuyền','Công ty Cổ phần Sản xuất Minh Phát','lead28@demo.orangecrm.local','0338666907',9,'contacted',NULL,NULL,NULL,16,40000000.00,2,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-01-18 08:36:00','2026-01-18 08:36:00',NULL),
(29,1,'LD00029','Tông Thiên Sĩ','Tập đoàn Xây dựng Trường Sơn','lead29@demo.orangecrm.local','0331454326',5,'qualified','2026-07-21 05:36:00',NULL,NULL,46,85000000.00,3,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2026-07-18 05:36:00','2026-10-16 14:42:24',NULL),
(30,1,'LD00030','Chương Hoài Duy','Công ty Cổ phần Công nghệ Nam Việt','lead30@demo.orangecrm.local','0348329614',9,'proposal',NULL,NULL,NULL,60,40000000.00,4,5,NULL,NULL,NULL,'Lead demo.',0,NULL,NULL,1,'2025-12-22 10:49:00','2025-12-22 10:49:00',NULL);
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `lost_reasons` WRITE;
/*!40000 ALTER TABLE `lost_reasons` DISABLE KEYS */;
INSERT IGNORE INTO `lost_reasons` (`id`, `organization_id`, `name`, `applies_to`, `is_active`, `position`, `created_at`, `updated_at`) VALUES (1,1,'Giá cao so với ngân sách','both',1,1,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(2,1,'Chọn đối thủ cạnh tranh','both',1,2,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(3,1,'Không còn nhu cầu','both',1,3,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(4,1,'Không liên lạc được','both',1,4,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(5,1,'Thông tin không chính xác','both',1,5,'2026-10-16 14:42:17','2026-10-16 14:42:17'),
(6,1,'Khác','both',1,6,'2026-10-16 14:42:17','2026-10-16 14:42:17');
/*!40000 ALTER TABLE `lost_reasons` ENABLE KEYS */;
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
(17,'2026_10_18_100100_grant_api_permission',1),
(18,'2026_10_19_100000_create_organization_settings_table',1),
(19,'2026_10_19_100100_add_department_to_organization_user',1),
(20,'2026_10_20_100000_upgrade_interactions_to_activities',1),
(21,'2026_10_21_100000_extend_customers_profile',1),
(22,'2026_10_22_100000_configurable_lead_stages',1),
(23,'2026_10_23_100000_multiple_pipelines_and_deal_items',1),
(24,'2026_10_24_100000_create_search_histories_table',1);
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
INSERT IGNORE INTO `opportunities` (`id`, `organization_id`, `code`, `title`, `customer_id`, `contact_id`, `lead_id`, `pipeline_id`, `pipeline_stage_id`, `value`, `probability`, `expected_close_date`, `closed_at`, `lost_reason`, `lost_reason_id`, `position`, `assigned_to`, `created_by`, `notes`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'CH00001','Đào tạo nhân viên - Tập đoàn Thực phẩm Tân Á',18,18,NULL,1,1,226000000.00,10,'2026-12-13',NULL,NULL,NULL,0,2,2,'Cơ hội demo.',1,'2026-06-22 14:42:23','2026-06-22 14:42:23',NULL),
(2,1,'CH00002','Nâng cấp ERP - Tập đoàn Xây dựng Bình Minh',29,29,NULL,1,2,54000000.00,25,'2026-12-29',NULL,NULL,NULL,1,2,2,'Cơ hội demo.',1,'2026-07-06 14:42:23','2026-07-06 14:42:23',NULL),
(3,1,'CH00003','Nâng cấp ERP - Mang Uyển Quân',7,7,NULL,1,3,214000000.00,50,'2026-12-09',NULL,NULL,NULL,2,4,2,'Cơ hội demo.',1,'2026-03-12 14:42:23','2026-03-12 14:42:23',NULL),
(4,1,'CH00004','Mua máy chủ - Diệp Quế Hòa',28,28,NULL,1,4,127000000.00,75,'2026-10-30',NULL,NULL,NULL,3,2,2,'Cơ hội demo.',1,'2026-04-02 14:42:23','2026-04-02 14:42:23',NULL),
(5,1,'CH00005','Triển khai CRM - Khuất Giang Nam',25,25,NULL,1,5,48000000.00,100,'2026-10-05','2026-06-15 14:42:23',NULL,NULL,4,4,2,'Cơ hội demo.',1,'2026-06-04 14:42:23','2026-06-04 14:42:23',NULL),
(6,1,'CH00006','Triển khai CRM - Doãn Như Giang',22,22,NULL,1,6,55000000.00,0,'2026-12-13','2026-01-22 14:42:23','Khách chọn nhà cung cấp khác (demo)',1,5,4,2,'Cơ hội demo.',1,'2025-12-21 14:42:23','2026-10-16 14:42:24',NULL),
(7,1,'CH00007','Triển khai CRM - Tào Tường Thông',10,10,NULL,1,1,27000000.00,10,'2026-09-19',NULL,NULL,NULL,6,2,2,'Cơ hội demo.',1,'2026-04-29 14:42:23','2026-04-29 14:42:23',NULL),
(8,1,'CH00008','Mua máy chủ - Tập đoàn Xây dựng Bình Minh',29,29,NULL,1,2,13000000.00,25,'2027-01-02',NULL,NULL,NULL,7,2,2,'Cơ hội demo.',1,'2026-04-21 14:42:23','2026-04-21 14:42:23',NULL),
(9,1,'CH00009','Đào tạo nhân viên - Tập đoàn Thực phẩm Tân Á',18,18,NULL,1,3,244000000.00,50,'2027-01-09',NULL,NULL,NULL,8,2,2,'Cơ hội demo.',1,'2026-06-29 14:42:23','2026-06-29 14:42:23',NULL),
(10,1,'CH00010','Đào tạo nhân viên - Công ty TNHH Dịch vụ Hưng Thịn',17,17,NULL,1,4,129000000.00,75,'2026-10-29',NULL,NULL,NULL,9,2,2,'Cơ hội demo.',1,'2026-02-28 14:42:23','2026-02-28 14:42:23',NULL),
(11,1,'CH00011','Trang bị POS - Công ty Cổ phần Logistics Thàn',23,23,NULL,1,5,271000000.00,100,'2026-11-09','2026-06-16 14:42:23',NULL,NULL,10,4,2,'Cơ hội demo.',1,'2026-06-06 14:42:23','2026-06-06 14:42:23',NULL),
(12,1,'CH00012','Gói hỗ trợ năm - Ông Hoan Hòa',1,1,NULL,1,6,121000000.00,0,'2026-10-22','2026-08-19 14:42:23','Khách chọn nhà cung cấp khác (demo)',1,11,3,2,'Cơ hội demo.',1,'2026-07-26 14:42:23','2026-10-16 14:42:24',NULL),
(13,1,'CH00013','Mua máy chủ - Công ty Cổ phần Dịch vụ Trường',11,11,NULL,1,1,130000000.00,10,'2026-10-12',NULL,NULL,NULL,12,2,2,'Cơ hội demo.',1,'2026-02-01 14:42:23','2026-02-01 14:42:23',NULL),
(14,1,'CH00014','Gói hỗ trợ năm - Công ty TNHH Thương mại Sao Ma',8,8,NULL,1,2,90000000.00,25,'2026-09-25',NULL,NULL,NULL,13,3,2,'Cơ hội demo.',1,'2026-07-27 14:42:23','2026-07-27 14:42:23',NULL),
(15,1,'CH00015','Triển khai CRM - Công ty Cổ phần Dịch vụ Trường',11,11,NULL,1,3,288000000.00,50,'2026-12-12',NULL,NULL,NULL,14,2,2,'Cơ hội demo.',1,'2026-03-15 14:42:23','2026-03-15 14:42:23',NULL);
/*!40000 ALTER TABLE `opportunities` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `opportunity_items` WRITE;
/*!40000 ALTER TABLE `opportunity_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `opportunity_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `opportunity_stage_histories` WRITE;
/*!40000 ALTER TABLE `opportunity_stage_histories` DISABLE KEYS */;
INSERT IGNORE INTO `opportunity_stage_histories` (`id`, `opportunity_id`, `from_stage_id`, `to_stage_id`, `changed_by`, `created_at`) VALUES (1,1,NULL,1,2,'2026-06-22 14:42:23'),
(2,2,NULL,2,2,'2026-07-06 14:42:23'),
(3,3,NULL,3,2,'2026-03-12 14:42:23'),
(4,4,NULL,4,2,'2026-04-02 14:42:23'),
(5,5,NULL,5,2,'2026-06-04 14:42:23'),
(6,6,NULL,6,2,'2025-12-21 14:42:23'),
(7,7,NULL,1,2,'2026-04-29 14:42:23'),
(8,8,NULL,2,2,'2026-04-21 14:42:23'),
(9,9,NULL,3,2,'2026-06-29 14:42:23'),
(10,10,NULL,4,2,'2026-02-28 14:42:23'),
(11,11,NULL,5,2,'2026-06-06 14:42:23'),
(12,12,NULL,6,2,'2026-07-26 14:42:23'),
(13,13,NULL,1,2,'2026-02-01 14:42:23'),
(14,14,NULL,2,2,'2026-07-27 14:42:23'),
(15,15,NULL,3,2,'2026-03-15 14:42:23');
/*!40000 ALTER TABLE `opportunity_stage_histories` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `organization_settings` WRITE;
/*!40000 ALTER TABLE `organization_settings` DISABLE KEYS */;
INSERT IGNORE INTO `organization_settings` (`id`, `organization_id`, `key`, `value`, `created_at`, `updated_at`) VALUES (1,1,'provisioned_at','2026-10-16 21:42:20','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,'company_name','TOP CRM','2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `organization_settings` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `organization_user` WRITE;
/*!40000 ALTER TABLE `organization_user` DISABLE KEYS */;
INSERT IGNORE INTO `organization_user` (`id`, `organization_id`, `user_id`, `status`, `department_id`, `joined_at`, `created_at`, `updated_at`) VALUES (1,1,1,'active',1,'2026-10-16 14:42:20','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,2,'active',2,'2026-10-16 14:42:20','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,3,'active',2,'2026-10-16 14:42:21','2026-10-16 14:42:21','2026-10-16 14:42:21'),
(4,1,4,'active',2,'2026-10-16 14:42:21','2026-10-16 14:42:21','2026-10-16 14:42:21'),
(5,1,5,'active',3,'2026-10-16 14:42:21','2026-10-16 14:42:21','2026-10-16 14:42:21'),
(6,1,6,'active',4,'2026-10-16 14:42:21','2026-10-16 14:42:21','2026-10-16 14:42:21'),
(7,1,7,'active',5,'2026-10-16 14:42:21','2026-10-16 14:42:21','2026-10-16 14:42:21');
/*!40000 ALTER TABLE `organization_user` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT IGNORE INTO `organizations` (`id`, `name`, `slug`, `status`, `owner_id`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'TOP CRM','top','active',NULL,'2026-10-16 14:42:08','2026-10-16 14:42:08',NULL);
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT IGNORE INTO `payments` (`id`, `organization_id`, `invoice_id`, `amount`, `payment_date`, `method`, `reference`, `notes`, `recorded_by`, `is_demo`, `created_at`, `updated_at`) VALUES (1,1,1,41827500.00,'2026-09-05','bank_transfer','DEMO-1',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,2,44550000.00,'2026-05-08','bank_transfer','DEMO-2',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,1,3,9405000.00,'2026-03-24','bank_transfer','DEMO-3',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,1,4,2750000.00,'2026-10-01','bank_transfer','DEMO-4',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,1,5,1584000.00,'2026-04-27','bank_transfer','DEMO-5',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,1,6,111386000.00,'2026-01-07','bank_transfer','DEMO-6',NULL,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT IGNORE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'api.use','web','2026-10-16 14:42:15','2026-10-16 14:42:15'),
(2,'dashboard.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(3,'customers.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(4,'customers.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(5,'customers.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(6,'customers.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(7,'customers.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(8,'customers.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(9,'customers.assign','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(10,'contacts.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(11,'contacts.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(12,'contacts.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(13,'contacts.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(14,'contacts.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(15,'contacts.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(16,'leads.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(17,'leads.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(18,'leads.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(19,'leads.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(20,'leads.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(21,'leads.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(22,'leads.assign','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(23,'opportunities.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(24,'opportunities.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(25,'opportunities.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(26,'opportunities.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(27,'opportunities.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(28,'opportunities.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(29,'opportunities.assign','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(30,'tasks.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(31,'tasks.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(32,'tasks.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(33,'tasks.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(34,'tasks.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(35,'tasks.assign','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(36,'products.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(37,'products.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(38,'products.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(39,'products.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(40,'products.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(41,'quotations.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(42,'quotations.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(43,'quotations.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(44,'quotations.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(45,'quotations.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(46,'quotations.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(47,'quotations.approve','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(48,'orders.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(49,'orders.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(50,'orders.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(51,'orders.update','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(52,'orders.delete','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(53,'orders.export','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(54,'orders.approve','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(55,'invoices.view','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(56,'invoices.view_all','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(57,'invoices.create','web','2026-10-16 14:42:18','2026-10-16 14:42:18'),
(58,'invoices.update','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(59,'invoices.delete','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(60,'invoices.export','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(61,'campaigns.view','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(62,'campaigns.view_all','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(63,'campaigns.create','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(64,'campaigns.update','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(65,'campaigns.delete','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(66,'campaigns.export','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(67,'tickets.view','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(68,'tickets.view_all','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(69,'tickets.create','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(70,'tickets.update','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(71,'tickets.delete','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(72,'tickets.export','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(73,'tickets.assign','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(74,'reports.view','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(75,'reports.export','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(76,'users.view','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(77,'users.create','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(78,'users.update','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(79,'users.delete','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(80,'users.manage','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(81,'activity_logs.view','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(82,'settings.manage','web','2026-10-16 14:42:19','2026-10-16 14:42:19');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `pipeline_stages` WRITE;
/*!40000 ALTER TABLE `pipeline_stages` DISABLE KEYS */;
INSERT IGNORE INTO `pipeline_stages` (`id`, `organization_id`, `pipeline_id`, `name`, `slug`, `position`, `probability`, `color`, `is_won`, `is_lost`, `created_at`, `updated_at`) VALUES (1,1,1,'Cơ hội mới','new',1,10,'#6B7280',0,0,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,1,'Đánh giá nhu cầu','qualification',2,25,'#3B82F6',0,0,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,1,'Gửi đề xuất','proposal',3,50,'#F59E0B',0,0,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,1,1,'Đàm phán','negotiation',4,75,'#FF6B00',0,0,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,1,1,'Thắng','won',5,100,'#16A34A',1,0,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(6,1,1,'Thất bại','lost',6,0,'#DC2626',0,1,'2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `pipeline_stages` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `pipelines` WRITE;
/*!40000 ALTER TABLE `pipelines` DISABLE KEYS */;
INSERT IGNORE INTO `pipelines` (`id`, `organization_id`, `name`, `is_default`, `is_active`, `position`, `created_at`, `updated_at`) VALUES (1,1,'Pipeline bán hàng',1,1,1,'2026-10-16 14:42:17','2026-10-16 14:42:17');
/*!40000 ALTER TABLE `pipelines` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT IGNORE INTO `product_categories` (`id`, `organization_id`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,1,'Phần mềm',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,'Dịch vụ',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,'Phần cứng',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,1,'Đào tạo',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,1,'Gói hỗ trợ',NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT IGNORE INTO `products` (`id`, `organization_id`, `sku`, `name`, `product_category_id`, `description`, `price`, `unit`, `status`, `image`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'SW-CRM-STD','Phần mềm CRM - Gói Tiêu chuẩn',1,'Sản phẩm demo: Phần mềm CRM - Gói Tiêu chuẩn',12000000.00,'gói','active',NULL,1,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(2,1,'SW-CRM-PRO','Phần mềm CRM - Gói Chuyên nghiệp',1,'Sản phẩm demo: Phần mềm CRM - Gói Chuyên nghiệp',25000000.00,'gói','active',NULL,1,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(3,1,'SW-ERP-LITE','Phần mềm ERP Lite',1,'Sản phẩm demo: Phần mềm ERP Lite',45000000.00,'gói','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(4,1,'SV-IMPL','Dịch vụ triển khai',2,'Sản phẩm demo: Dịch vụ triển khai',15000000.00,'dự án','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(5,1,'SV-CUSTOM','Phát triển tính năng theo yêu cầu',2,'Sản phẩm demo: Phát triển tính năng theo yêu cầu',800000.00,'giờ','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(6,1,'HW-SERVER','Máy chủ Dell PowerEdge T150',3,'Sản phẩm demo: Máy chủ Dell PowerEdge T150',38000000.00,'cái','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(7,1,'HW-POS','Máy bán hàng POS',3,'Sản phẩm demo: Máy bán hàng POS',9500000.00,'cái','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(8,1,'TR-USER','Đào tạo người dùng',4,'Sản phẩm demo: Đào tạo người dùng',5000000.00,'buổi','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(9,1,'SP-YEAR','Gói hỗ trợ kỹ thuật 12 tháng',5,'Sản phẩm demo: Gói hỗ trợ kỹ thuật 12 tháng',18000000.00,'năm','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL),
(10,1,'SP-CLOUD','Lưu trữ đám mây 1TB',5,'Sản phẩm demo: Lưu trữ đám mây 1TB',3600000.00,'năm','active',NULL,1,'2026-10-16 14:42:22','2026-10-16 14:42:22',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT IGNORE INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,2,'Phần mềm CRM - Gói Chuyên nghiệp',3.00,25000000.00,10.00,67500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,2,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,3,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,4,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,5,5,'Phát triển tính năng theo yêu cầu',2.00,800000.00,10.00,1440000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,6,5,'Phát triển tính năng theo yêu cầu',1.00,800000.00,5.00,760000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,6,8,'Đào tạo người dùng',3.00,5000000.00,0.00,15000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(10,7,1,'Phần mềm CRM - Gói Tiêu chuẩn',2.00,12000000.00,0.00,24000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(11,7,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(12,8,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(13,9,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,0.00,12000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(14,9,9,'Gói hỗ trợ kỹ thuật 12 tháng',1.00,18000000.00,0.00,18000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(15,10,1,'Phần mềm CRM - Gói Tiêu chuẩn',2.00,12000000.00,10.00,21600000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(16,10,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(17,11,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,5.00,11400000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(18,11,6,'Máy chủ Dell PowerEdge T150',1.00,38000000.00,0.00,38000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(19,12,3,'Phần mềm ERP Lite',1.00,45000000.00,5.00,42750000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(20,12,8,'Đào tạo người dùng',2.00,5000000.00,5.00,9500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(21,12,9,'Gói hỗ trợ kỹ thuật 12 tháng',3.00,18000000.00,5.00,51300000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT IGNORE INTO `quotations` (`id`, `organization_id`, `number`, `customer_id`, `opportunity_id`, `issue_date`, `valid_until`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'BG00001',30,NULL,'2026-08-28','2026-09-27','accepted',76050000.00,0.00,10.00,7605000.00,83655000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(2,1,'BG00002',23,NULL,'2026-05-04','2026-06-03','accepted',40500000.00,0.00,10.00,4050000.00,44550000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(3,1,'BG00003',25,NULL,'2026-03-09','2026-04-08','accepted',8550000.00,0.00,10.00,855000.00,9405000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(4,1,'BG00004',5,NULL,'2026-09-17','2026-10-17','accepted',5000000.00,0.00,10.00,500000.00,5500000.00,'Báo giá demo.',2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(5,1,'BG00005',29,NULL,'2026-04-18','2026-05-18','accepted',1440000.00,0.00,10.00,144000.00,1584000.00,'Báo giá demo.',2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(6,1,'BG00006',27,NULL,'2025-12-23','2026-01-22','accepted',101260000.00,0.00,10.00,10126000.00,111386000.00,'Báo giá demo.',3,3,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(7,1,'BG00007',29,NULL,'2026-05-15','2026-06-14','accepted',64500000.00,0.00,10.00,6450000.00,70950000.00,'Báo giá demo.',2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(8,1,'BG00008',7,NULL,'2026-04-14','2026-05-14','accepted',5000000.00,0.00,10.00,500000.00,5500000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(9,1,'BG00009',18,NULL,'2026-06-06','2026-07-06','accepted',30000000.00,0.00,10.00,3000000.00,33000000.00,'Báo giá demo.',2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(10,1,'BG00010',18,NULL,'2025-11-10','2025-12-10','rejected',30150000.00,0.00,10.00,3015000.00,33165000.00,'Báo giá demo.',2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(11,1,'BG00011',30,NULL,'2026-07-12','2026-08-11','sent',49400000.00,0.00,10.00,4940000.00,54340000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(12,1,'BG00012',6,NULL,'2026-01-15','2026-02-14','rejected',103550000.00,0.00,10.00,10355000.00,113905000.00,'Báo giá demo.',4,4,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL);
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
INSERT IGNORE INTO `roles` (`id`, `organization_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,1,'Organization Owner','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(2,1,'Organization Administrator','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(3,1,'Sales Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(4,1,'Sales Employee','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(5,1,'Marketing Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(6,1,'Marketing Employee','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(7,1,'Customer Support Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(8,1,'Support Agent','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(9,1,'Finance Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(10,1,'Accountant','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(11,1,'Inventory Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(12,1,'Project Manager','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(13,1,'Employee','web','2026-10-16 14:42:19','2026-10-16 14:42:19'),
(14,1,'Read-only Auditor','web','2026-10-16 14:42:19','2026-10-16 14:42:19');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_order_items` WRITE;
/*!40000 ALTER TABLE `sales_order_items` DISABLE KEYS */;
INSERT IGNORE INTO `sales_order_items` (`id`, `sales_order_id`, `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `line_total`, `created_at`, `updated_at`) VALUES (1,1,2,'Phần mềm CRM - Gói Chuyên nghiệp',3.00,25000000.00,10.00,67500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,2,4,'Dịch vụ triển khai',3.00,15000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,3,7,'Máy bán hàng POS',1.00,9500000.00,10.00,8550000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,4,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,5,5,'Phát triển tính năng theo yêu cầu',2.00,800000.00,10.00,1440000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,6,3,'Phần mềm ERP Lite',2.00,45000000.00,5.00,85500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,6,5,'Phát triển tính năng theo yêu cầu',1.00,800000.00,5.00,760000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,6,8,'Đào tạo người dùng',3.00,5000000.00,0.00,15000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(10,7,1,'Phần mềm CRM - Gói Tiêu chuẩn',2.00,12000000.00,0.00,24000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(11,7,3,'Phần mềm ERP Lite',1.00,45000000.00,10.00,40500000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(12,8,8,'Đào tạo người dùng',1.00,5000000.00,0.00,5000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(13,9,1,'Phần mềm CRM - Gói Tiêu chuẩn',1.00,12000000.00,0.00,12000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(14,9,9,'Gói hỗ trợ kỹ thuật 12 tháng',1.00,18000000.00,0.00,18000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `sales_order_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
INSERT IGNORE INTO `sales_orders` (`id`, `organization_id`, `number`, `customer_id`, `quotation_id`, `order_date`, `status`, `subtotal`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `notes`, `created_by`, `assigned_to`, `approved_by`, `approved_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'DH00001',30,1,'2026-08-30','completed',76050000.00,0.00,10.00,7605000.00,83655000.00,NULL,4,4,2,'2026-08-30 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(2,1,'DH00002',23,2,'2026-05-06','completed',40500000.00,0.00,10.00,4050000.00,44550000.00,NULL,4,4,2,'2026-05-06 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(3,1,'DH00003',25,3,'2026-03-11','completed',8550000.00,0.00,10.00,855000.00,9405000.00,NULL,4,4,2,'2026-03-11 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(4,1,'DH00004',5,4,'2026-09-19','completed',5000000.00,0.00,10.00,500000.00,5500000.00,NULL,2,2,2,'2026-09-19 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(5,1,'DH00005',29,5,'2026-04-20','completed',1440000.00,0.00,10.00,144000.00,1584000.00,NULL,2,2,2,'2026-04-20 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(6,1,'DH00006',27,6,'2025-12-25','completed',101260000.00,0.00,10.00,10126000.00,111386000.00,NULL,3,3,2,'2025-12-25 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(7,1,'DH00007',29,7,'2026-05-17','completed',64500000.00,0.00,10.00,6450000.00,70950000.00,NULL,2,2,2,'2026-05-17 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(8,1,'DH00008',7,8,'2026-04-16','confirmed',5000000.00,0.00,10.00,500000.00,5500000.00,NULL,4,4,2,'2026-04-16 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(9,1,'DH00009',18,9,'2026-06-08','confirmed',30000000.00,0.00,10.00,3000000.00,33000000.00,NULL,2,2,2,'2026-06-08 14:42:23',1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL);
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `sales_targets` WRITE;
/*!40000 ALTER TABLE `sales_targets` DISABLE KEYS */;
INSERT IGNORE INTO `sales_targets` (`id`, `organization_id`, `user_id`, `year`, `month`, `target_amount`, `created_at`, `updated_at`) VALUES (1,1,2,2026,10,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,2,2026,9,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,1,2,2026,8,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,1,3,2026,10,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,1,3,2026,9,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,1,3,2026,8,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,1,4,2026,10,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,1,4,2026,9,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,1,4,2026,8,150000000.00,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `sales_targets` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `search_histories` WRITE;
/*!40000 ALTER TABLE `search_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_histories` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT IGNORE INTO `settings` (`id`, `key`, `value`, `group`, `created_at`, `updated_at`) VALUES (1,'company_name','TOP CRM','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,'brand_name','TOP','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,'brand_tagline','TECHNOLOGY OF PROWESS','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,'brand_slogan','TOP CRM - Quản lý khách hàng, Kiến tạo tăng trưởng','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,'login_headline','Kết nối khách hàng','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(6,'login_headline_accent','Kiến tạo tăng trưởng','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(7,'login_description','TOP CRM giúp doanh nghiệp quản lý khách hàng, tối ưu quy trình bán hàng và nâng cao hiệu quả kinh doanh với công nghệ hiện đại.','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(8,'login_stats','10K+|Khách hàng tin dùng\n300%|Tăng hiệu suất\n99.9%|Bảo mật dữ liệu\n24/7|Hỗ trợ khách hàng','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(9,'login_background','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(10,'copyright_owner','Trịnh Phú Phúc','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(11,'copyright_text','TOP — Technology of Prowess','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(12,'page_terms','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(13,'page_privacy','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(14,'page_support','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(15,'google_client_id','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(16,'google_client_secret','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(17,'facebook_client_id','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(18,'facebook_client_secret','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(19,'company_email','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(20,'company_phone','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(21,'company_address','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(22,'company_logo','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(23,'brand_color','#D9823B','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(24,'timezone','Asia/Ho_Chi_Minh','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(25,'currency','VND','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(26,'date_format','d/m/Y','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(27,'language','vi','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(28,'default_tax_rate','10','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(29,'notify_lead_assigned','1','notifications','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(30,'notify_task_due','1','notifications','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(31,'notify_deal_stage','1','notifications','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(32,'notify_ticket_new','1','notifications','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(33,'require_2fa_admins','0','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(34,'lead_form_enabled','0','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(35,'lead_form_token','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(36,'lead_form_assignee','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(37,'lead_form_source','','general','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(38,'lead_form_thanks','','general','2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
INSERT IGNORE INTO `support_tickets` (`id`, `organization_id`, `code`, `customer_id`, `contact_id`, `subject`, `description`, `priority`, `status`, `assigned_to`, `created_by`, `resolved_at`, `closed_at`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'TK00001',4,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','closed',6,6,'2026-09-05 00:42:23','2026-09-06 14:42:23',1,'2026-09-02 14:42:23','2026-09-02 14:42:23',NULL),
(2,1,'TK00002',10,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','high','waiting',6,6,NULL,NULL,1,'2026-08-15 14:42:23','2026-08-15 14:42:23',NULL),
(3,1,'TK00003',30,NULL,'Máy POS không in hóa đơn','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-09-12 14:42:23','2026-09-12 14:42:23',NULL),
(4,1,'TK00004',23,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','medium','waiting',6,6,NULL,NULL,1,'2026-07-28 14:42:23','2026-07-28 14:42:23',NULL),
(5,1,'TK00005',20,NULL,'Không đăng nhập được','Mô tả sự cố demo.','urgent','closed',6,6,'2026-09-17 15:42:23','2026-09-20 14:42:23',1,'2026-09-16 14:42:23','2026-09-16 14:42:23',NULL),
(6,1,'TK00006',16,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','waiting',6,6,NULL,NULL,1,'2026-07-26 14:42:23','2026-07-26 14:42:23',NULL),
(7,1,'TK00007',24,NULL,'Lỗi xuất báo cáo','Mô tả sự cố demo.','urgent','waiting',6,6,NULL,NULL,1,'2026-09-15 14:42:23','2026-09-15 14:42:23',NULL),
(8,1,'TK00008',22,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','medium','closed',6,6,'2026-09-28 21:42:23','2026-10-02 14:42:23',1,'2026-09-28 14:42:23','2026-09-28 14:42:23',NULL),
(9,1,'TK00009',2,NULL,'Không đăng nhập được','Mô tả sự cố demo.','low','in_progress',6,6,NULL,NULL,1,'2026-09-07 14:42:23','2026-09-07 14:42:23',NULL),
(10,1,'TK00010',9,NULL,'Yêu cầu thêm người dùng','Mô tả sự cố demo.','low','open',6,6,NULL,NULL,1,'2026-07-25 14:42:23','2026-07-25 14:42:23',NULL);
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT IGNORE INTO `tags` (`id`, `organization_id`, `name`, `color`, `created_at`, `updated_at`) VALUES (1,1,'VIP','#FF6B00','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(2,1,'Doanh nghiệp lớn','#2563EB','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(3,1,'Khách quen','#16A34A','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(4,1,'Cần chăm sóc','#F59E0B','2026-10-16 14:42:20','2026-10-16 14:42:20'),
(5,1,'Đối tác','#7C3AED','2026-10-16 14:42:20','2026-10-16 14:42:20');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `task_checklist_items` WRITE;
/*!40000 ALTER TABLE `task_checklist_items` DISABLE KEYS */;
INSERT IGNORE INTO `task_checklist_items` (`id`, `task_id`, `content`, `is_done`, `position`, `created_at`, `updated_at`) VALUES (1,1,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,1,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,2,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,2,'Liên hệ khách hàng',1,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,3,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,3,'Liên hệ khách hàng',1,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,4,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,4,'Liên hệ khách hàng',1,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,5,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(10,5,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(11,6,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(12,6,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(13,7,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(14,7,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(15,8,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(16,8,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(17,9,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(18,9,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(19,10,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(20,10,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(21,11,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(22,11,'Liên hệ khách hàng',1,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(23,12,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(24,12,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(25,13,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(26,13,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(27,14,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(28,14,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(29,15,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(30,15,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(31,16,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(32,16,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(33,17,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(34,17,'Liên hệ khách hàng',1,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(35,18,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(36,18,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(37,19,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(38,19,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(39,20,'Chuẩn bị tài liệu',1,1,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(40,20,'Liên hệ khách hàng',0,2,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `task_checklist_items` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `task_comments` WRITE;
/*!40000 ALTER TABLE `task_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_comments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT IGNORE INTO `tasks` (`id`, `organization_id`, `title`, `description`, `priority`, `status`, `due_date`, `completed_at`, `reminded_at`, `related_type`, `related_id`, `assigned_to`, `created_by`, `is_demo`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Gọi điện tư vấn - Tào Tường Thông','Công việc demo.','low','in_progress','2026-10-20 14:00:00',NULL,NULL,'customer',10,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(2,1,'Hẹn gặp demo sản phẩm - Tập đoàn Logistics Thành Công','Công việc demo.','medium','completed','2026-10-28 15:00:00','2026-10-27 08:00:00',NULL,'customer',15,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(3,1,'Gọi điện tư vấn - Công ty TNHH MTV Thực phẩm Hưn','Công việc demo.','low','completed','2026-10-15 08:00:00','2026-10-14 01:00:00',NULL,'customer',14,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(4,1,'Hẹn gặp demo sản phẩm - Tập đoàn Thực phẩm Tân Á','Công việc demo.','urgent','completed','2026-10-14 15:00:00','2026-10-13 08:00:00',NULL,'customer',18,4,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(5,1,'Gọi điện tư vấn - Tập đoàn Xây dựng Bình Minh','Công việc demo.','medium','cancelled','2026-10-11 16:00:00',NULL,NULL,'customer',29,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(6,1,'Theo dõi thanh toán - Công ty Cổ phần Dịch vụ Trường','Công việc demo.','low','overdue','2026-10-07 10:00:00',NULL,NULL,'customer',11,3,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(7,1,'Gọi điện tư vấn - Diệp Quế Hòa','Công việc demo.','high','pending','2026-11-09 14:00:00',NULL,NULL,'customer',28,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(8,1,'Khảo sát nhu cầu - Công ty Cổ phần Logistics Thàn','Công việc demo.','urgent','overdue','2026-10-06 16:00:00',NULL,NULL,'customer',23,3,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(9,1,'Chuẩn bị hợp đồng - Công ty TNHH Dịch vụ Đại Dương','Công việc demo.','medium','pending','2026-10-23 08:00:00',NULL,NULL,'customer',20,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(10,1,'Khảo sát nhu cầu - Công ty Cổ phần Dịch vụ Trường','Công việc demo.','high','overdue','2026-10-04 10:00:00',NULL,NULL,'customer',11,3,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(11,1,'Khảo sát nhu cầu - Công ty Cổ phần Logistics Trườ','Công việc demo.','medium','completed','2026-10-23 08:00:00','2026-10-22 01:00:00',NULL,'customer',2,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(12,1,'Chuẩn bị hợp đồng - Công ty TNHH MTV Logistics Hòa','Công việc demo.','urgent','overdue','2026-10-13 12:00:00',NULL,NULL,'customer',3,3,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(13,1,'Theo dõi thanh toán - Hoàng Hùng Cúc','Công việc demo.','medium','pending','2026-10-25 10:00:00',NULL,NULL,'customer',4,4,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(14,1,'Gọi điện tư vấn - Diệp Quế Hòa','Công việc demo.','high','in_progress','2026-10-24 15:00:00',NULL,NULL,'customer',28,3,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(15,1,'Theo dõi thanh toán - Sử Xuyến Phượng','Công việc demo.','low','cancelled','2026-11-05 11:00:00',NULL,NULL,'customer',16,4,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(16,1,'Chuẩn bị hợp đồng - Công ty TNHH MTV Sản xuất Đại','Công việc demo.','medium','pending','2026-10-26 12:00:00',NULL,NULL,'customer',26,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(17,1,'Theo dõi thanh toán - Hoàng Hùng Cúc','Công việc demo.','medium','completed','2026-10-18 10:00:00','2026-10-17 03:00:00',NULL,'customer',4,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(18,1,'Theo dõi thanh toán - Mang Uyển Quân','Công việc demo.','urgent','in_progress','2026-10-25 17:00:00',NULL,NULL,'customer',7,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(19,1,'Khảo sát nhu cầu - Trưng Tuấn Hảo','Công việc demo.','urgent','pending','2026-11-04 08:00:00',NULL,NULL,'customer',13,2,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL),
(20,1,'Khảo sát nhu cầu - Trần Xuân Tiển','Công việc demo.','urgent','overdue','2026-09-30 08:00:00',NULL,NULL,'customer',19,4,2,1,'2026-10-16 14:42:23','2026-10-16 14:42:23',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `ticket_replies` WRITE;
/*!40000 ALTER TABLE `ticket_replies` DISABLE KEYS */;
INSERT IGNORE INTO `ticket_replies` (`id`, `support_ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`) VALUES (1,1,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(2,2,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(3,3,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(4,4,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(5,5,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(6,6,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(7,7,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(8,8,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(9,9,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23'),
(10,10,6,'Đã tiếp nhận yêu cầu (demo).',0,'2026-10-16 14:42:23','2026-10-16 14:42:23');
/*!40000 ALTER TABLE `ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `username`, `email_verified_at`, `password`, `phone`, `position`, `avatar`, `department_id`, `status`, `is_platform_admin`, `locale`, `theme`, `current_organization_id`, `last_login_at`, `last_login_ip`, `is_demo`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `two_factor_last_step`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Trần Minh Quân','quantri@demo.orangecrm.local',NULL,NULL,'$2y$12$X/WxAO82M4ECLhNdvuGY1OhfJRR2Slu/z2SHYUPPE3V9IabSpkwv6','0952082305','Quản trị hệ thống',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20',NULL),
(2,'Lê Thu Hà','truongkinhdoanh@demo.orangecrm.local',NULL,NULL,'$2y$12$0/aOKgbd6Pav2Gl.pvO30.ybPu1bfvRHcbHOhZElZ5h0oCP4jBlv6','0962560774','Trưởng phòng kinh doanh',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:20','2026-10-16 14:42:20',NULL),
(3,'Nguyễn Văn Nam','kinhdoanh1@demo.orangecrm.local',NULL,NULL,'$2y$12$34yo.cfWMwpMgdzSYuyoUOpBfTdPePk7o2R2aPDeJhXrgI4uCF/qe','0973871898','Nhân viên kinh doanh',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(4,'Phạm Thị Lan','kinhdoanh2@demo.orangecrm.local',NULL,NULL,'$2y$12$NTe1zUt1CTi1RCseQw4eJun/sZ4fRBm0sa.0HDwCUL1V.dxs8nJTq','0988273336','Nhân viên kinh doanh',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(5,'Hoàng Đức Huy','marketing@demo.orangecrm.local',NULL,NULL,'$2y$12$wdwVTb22yzVaq41Qh05vO.zV7egeIfeYZFREkTxQc268g4YtqPSju','0964617421','Chuyên viên marketing',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(6,'Vũ Ngọc Anh','hotro@demo.orangecrm.local',NULL,NULL,'$2y$12$pZDQWtT16Q5yiMJs38EmtuJQ0EnE3GdiJgvKUASVRFoS.Cx1XdE8K','0995491917','Chuyên viên CSKH',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL),
(7,'Đỗ Thanh Tùng','xem@demo.orangecrm.local',NULL,NULL,'$2y$12$HLfWuAY8xEL5TMKYdM.M4e1RDcu8zvKaELttpaKcod2XYNFCFfsF.','0931818397','Kế toán',NULL,NULL,'active',0,NULL,NULL,1,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'2026-10-16 14:42:21','2026-10-16 14:42:21',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

