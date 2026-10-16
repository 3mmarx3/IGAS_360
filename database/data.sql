-- ==============================================================================
-- I-GAS ENTERPRISE CRM & ERP — UNIFIED MASTER DATABASE SCHEMA
-- ==============================================================================
-- File: unified_crm_master.sql
-- Production Master Relational Database Schema & Automated Seeding
-- Consolidating & Replacing:
--   1. database/migrations/rbac_migration.sql
--   2. database/master_schema.sql
--   3. database/schema.sql
--   4. u596103429_crm_gases.sql
--
-- Strict Relational Integrity & Zero-Defect Dependency Order
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Row-Level Security: 1-to-N Sales Rep Ownership across Clients, Quotations,
--                     Sales Orders, Consumptions, Invoices, Vouchers & Payments.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

-- ==============================================================================
-- CLEAN SLATE RESET: DROP EXISTING TABLES & VIEWS (REVERSE DEPENDENCY ORDER)
-- ==============================================================================
DROP VIEW IF EXISTS `view_clients_partners`;
DROP VIEW IF EXISTS `partners_view`;
DROP TRIGGER IF EXISTS `after_production_order_completed`;

DROP TABLE IF EXISTS `sales_commissions`;
DROP TABLE IF EXISTS `receipt_vouchers`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `consumptions`;
DROP TABLE IF EXISTS `dispatches`;
DROP TABLE IF EXISTS `production_order_cylinders`;
DROP TABLE IF EXISTS `client_cylinder_ledger`;
DROP TABLE IF EXISTS `cylinder_logs`;
DROP TABLE IF EXISTS `order_status_logs`;
DROP TABLE IF EXISTS `po_release_items`;
DROP TABLE IF EXISTS `po_releases`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `sales_orders`;
DROP TABLE IF EXISTS `quotation_items`;
DROP TABLE IF EXISTS `cylinders`;
DROP TABLE IF EXISTS `quotations`;
DROP TABLE IF EXISTS `cylinder_batches`;
DROP TABLE IF EXISTS `shift_logs`;
DROP TABLE IF EXISTS `production_work_orders`;
DROP TABLE IF EXISTS `warehouse_transactions`;
DROP TABLE IF EXISTS `warehouse_inventory`;
DROP TABLE IF EXISTS `material_transactions`;
DROP TABLE IF EXISTS `gas_mixtures`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `raw_materials`;
DROP TABLE IF EXISTS `supplier_products`;
DROP TABLE IF EXISTS `customer_tanks`;
DROP TABLE IF EXISTS `client_contracts`;
DROP TABLE IF EXISTS `customer_followups`;
DROP TABLE IF EXISTS `client_activities`;
DROP TABLE IF EXISTS `client_contacts`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `partners`;
DROP TABLE IF EXISTS `maintenance_tickets`;
DROP TABLE IF EXISTS `vehicle_logs`;
DROP TABLE IF EXISTS `drivers`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `unit_conversion_logs`;
DROP TABLE IF EXISTS `gas_conversion_logs`;
DROP TABLE IF EXISTS `compliance_docs`;
DROP TABLE IF EXISTS `gas_categories`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- ==============================================================================
-- SECTION 1: SYSTEM SETTINGS, RBAC & FOUNDATION TABLES (TIER 1)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 1: roles
-- Defines system roles for enterprise RBAC.
-- ------------------------------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_key` VARCHAR(50) NOT NULL,
  `role_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 2: permissions
-- Granular catalog of all CRM, ERP, and operational permissions.
-- ------------------------------------------------------------------------------
CREATE TABLE `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(50) NOT NULL,
  `permission_key` VARCHAR(100) NOT NULL,
  `permission_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_permission_key` (`permission_key`),
  KEY `idx_perm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 3: role_permissions
-- Many-to-Many pivot table linking roles to granted permissions.
-- ------------------------------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_rp_role` (`role_id`),
  KEY `idx_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 4: system_settings
-- Global configuration parameters, regional formats, and security policies.
-- ------------------------------------------------------------------------------
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 5: gas_categories
-- Classification hierarchy for bulk and specialty gas products.
-- ------------------------------------------------------------------------------
CREATE TABLE `gas_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 6: compliance_docs
-- Regulatory compliance, safety certifications, and hazardous permits.
-- ------------------------------------------------------------------------------
CREATE TABLE `compliance_docs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_name` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `alert_days` INT(11) DEFAULT 30,
  `status` ENUM('valid','expiring_soon','expired') DEFAULT 'valid',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 7: gas_conversion_logs
-- Thermodynamic and volumetric conversion logs for industrial gases.
-- ------------------------------------------------------------------------------
CREATE TABLE `gas_conversion_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `gas_type` VARCHAR(100) NOT NULL,
  `input_qty` DECIMAL(15,4) NOT NULL,
  `input_unit` VARCHAR(50) NOT NULL,
  `output_qty` DECIMAL(15,4) NOT NULL,
  `output_unit` VARCHAR(50) NOT NULL,
  `temp_celsius` DECIMAL(6,2) DEFAULT 15.00,
  `pressure_bar` DECIMAL(8,2) DEFAULT 1.01,
  `logged_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 8: unit_conversion_logs
-- General engineering and metric conversion audit trail.
-- ------------------------------------------------------------------------------
CREATE TABLE `unit_conversion_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `gas_type` VARCHAR(100) NOT NULL,
  `from_unit` VARCHAR(50) NOT NULL,
  `to_unit` VARCHAR(50) NOT NULL,
  `input_value` DECIMAL(15,4) NOT NULL,
  `result_value` DECIMAL(15,4) NOT NULL,
  `logged_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 9: vehicles
-- Logistics fleet registry (tankers, cylinder delivery trucks, flatbeds).
-- ------------------------------------------------------------------------------
CREATE TABLE `vehicles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `fleet_id` VARCHAR(50) NOT NULL,
  `plate_number` VARCHAR(50) NOT NULL,
  `make_model` VARCHAR(100) NOT NULL,
  `vehicle_type` VARCHAR(50) NOT NULL,
  `vin` VARCHAR(100) NOT NULL,
  `manufacturing_year` INT(11) DEFAULT NULL,
  `load_capacity` DECIMAL(10,2) DEFAULT 0.00,
  `cylinder_capacity` INT(11) DEFAULT 0,
  `fuel_type` VARCHAR(50) DEFAULT 'diesel',
  `driver_id` VARCHAR(50) DEFAULT 'unassigned',
  `status` VARCHAR(50) DEFAULT 'available',
  `last_maintenance_date` DATE DEFAULT NULL,
  `next_maintenance_due` DATE DEFAULT NULL,
  `registration_expiry` DATE DEFAULT NULL,
  `insurance_expiry` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fleet_id` (`fleet_id`),
  UNIQUE KEY `uniq_plate_number` (`plate_number`),
  UNIQUE KEY `uniq_vin` (`vin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 10: products
-- Master catalog of finished gases, bulk liquids, cylinders & equipment.
-- ------------------------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` ENUM('Bulk Liquid', 'Compressed Gas', 'Gas Mixture', 'Specialty Gas', 'Equipment') NOT NULL,
  `purity` VARCHAR(50) DEFAULT NULL,
  `unit` VARCHAR(50) NOT NULL COMMENT 'Liter, Cylinder, Nm3, Ton, Piece',
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `min_stock_alert` DECIMAL(15,2) NOT NULL DEFAULT 10.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_sku` (`sku`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 2: USERS, RBAC OVERRIDES & FLEET OPERATIONS (TIER 2)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 11: users
-- Internal staff, sales reps, operations engineers, and administrators.
-- ------------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emp_id` VARCHAR(50) DEFAULT NULL,
  `role_id` INT(11) DEFAULT NULL,
  `role` ENUM('admin','sales_manager','sales_rep','plant_manager','shift_supervisor','logistics_manager','driver','warehouse_manager','finance','compliance_officer','Admin','Sales Manager','Sales Rep','Logistics Manager','Logistics Assistant','Production Manager','Production Supervisor','Warehouse Manager','Finance','Read Only') DEFAULT NULL,
  `permission_level` ENUM('Admin','Edit','View') NOT NULL DEFAULT 'View',
  `manager_id` INT(11) DEFAULT NULL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Active','Suspended','active','inactive','suspended') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_manager_id` (`manager_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 12: user_permissions
-- User-specific permission overrides (explicit grant or revoke).
-- ------------------------------------------------------------------------------
CREATE TABLE `user_permissions` (
  `user_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`, `permission_id`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_perm` (`permission_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 13: audit_logs
-- System security, compliance audit trail and row-level modification logs.
-- ------------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(50) NOT NULL,
  `details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 14: drivers
-- Driver personnel profiles, hazardous material (Hazmat) permits and licenses.
-- ------------------------------------------------------------------------------
CREATE TABLE `drivers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `driver_id` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `national_id` VARCHAR(100) NOT NULL,
  `mobile_number` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `license_class` VARCHAR(50) NOT NULL,
  `license_number` VARCHAR(100) NOT NULL,
  `license_expiry` DATE NOT NULL,
  `medical_expiry` DATE DEFAULT NULL,
  `status` ENUM('active','on_leave','suspended') DEFAULT 'active',
  `assigned_vehicle` VARCHAR(50) DEFAULT NULL,
  `hazmat_certified` TINYINT(1) DEFAULT 0,
  `safety_violations` INT(11) DEFAULT 0,
  `emergency_contact_name` VARCHAR(255) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_driver_id` (`driver_id`),
  UNIQUE KEY `uniq_driver_national_id` (`national_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 15: vehicle_logs
-- Trip execution, odometer logs, fuel refills, and route tracking.
-- ------------------------------------------------------------------------------
CREATE TABLE `vehicle_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `fleet_id` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `event_date` DATE NOT NULL,
  `event_time` TIME NOT NULL,
  `odometer` INT(11) NOT NULL,
  `fuel_liters` DECIMAL(10,2) DEFAULT 0.00,
  `event_cost` DECIMAL(15,2) DEFAULT 0.00,
  `logged_by` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vl_fleet` (`fleet_id`),
  CONSTRAINT `vehicle_logs_ibfk_1` FOREIGN KEY (`fleet_id`) REFERENCES `vehicles` (`fleet_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 16: maintenance_tickets
-- Fleet maintenance, preventive schedules, inspection reports, and repair costs.
-- ------------------------------------------------------------------------------
CREATE TABLE `maintenance_tickets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` VARCHAR(50) NOT NULL,
  `service_type` VARCHAR(50) NOT NULL,
  `vehicle_id` VARCHAR(50) NOT NULL,
  `odometer` INT(11) NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `estimated_cost` DECIMAL(15,2) DEFAULT 0.00,
  `workshop` VARCHAR(255) NOT NULL,
  `instructions` TEXT DEFAULT NULL,
  `status` ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ticket_id` (`ticket_id`),
  KEY `idx_mt_vehicle` (`vehicle_id`),
  CONSTRAINT `maintenance_tickets_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`fleet_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 3: PARTNERS & CRM CLIENTS (TIER 3)
-- Strict 1-to-N Sales Rep Ownership across both Clients and Partners
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 17: partners
-- Master partner registry supporting Clients, Suppliers, and Logistics providers.
-- Upgraded: sales_rep_id is strictly typed as INT(11) with Foreign Key to users(id).
-- ------------------------------------------------------------------------------
CREATE TABLE `partners` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reference_id` VARCHAR(50) NOT NULL,
  `partner_type` ENUM('client','supplier','logistics') NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `cr_number` VARCHAR(100) NOT NULL,
  `contract_ref` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'Saudi Arabia',
  `city` VARCHAR(100) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `tax_id` VARCHAR(100) DEFAULT NULL,
  `contact_first_name` VARCHAR(100) NOT NULL,
  `contact_last_name` VARCHAR(100) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
  `status` ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
  `entity_type` VARCHAR(50) DEFAULT 'Corporate',
  `segment` VARCHAR(100) DEFAULT 'General',
  `lifetime_value` DECIMAL(15,2) DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) DEFAULT 0.00,
  `credit_limit` DECIMAL(15,2) DEFAULT 0.00,
  `payment_terms` VARCHAR(50) DEFAULT 'cod',
  `last_order_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rating` DECIMAL(3,1) DEFAULT 0.0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_partner_reference` (`reference_id`),
  KEY `idx_partners_sales_rep` (`sales_rep_id`),
  KEY `idx_partners_type` (`partner_type`),
  KEY `idx_partners_status` (`status`),
  CONSTRAINT `fk_partners_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 18: clients
-- Dedicated Enterprise CRM Client Table with Strict 1-to-N Sales Rep Ownership.
-- ------------------------------------------------------------------------------
CREATE TABLE `clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_code` VARCHAR(50) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL COMMENT 'Designated Sales Rep responsible for this client',
  `company_name` VARCHAR(255) NOT NULL,
  `industry` VARCHAR(100) DEFAULT NULL,
  `cr_number` VARCHAR(100) DEFAULT NULL,
  `tax_id` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `address` TEXT NOT NULL,
  `credit_limit` DECIMAL(15,2) NOT NULL DEFAULT 50000.00,
  `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms` VARCHAR(50) NOT NULL DEFAULT 'Net 30',
  `status` ENUM('lead', 'active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_clients_code` (`client_code`),
  KEY `idx_clients_sales_rep` (`sales_rep_id`),
  KEY `idx_clients_status` (`status`),
  CONSTRAINT `fk_clients_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 4: CLIENT SUBSIDIARIES, CONTACTS, ACTIVITIES & CATALOG (TIER 4)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 19: client_contacts
-- Authorized corporate contacts and decision makers for CRM clients.
-- ------------------------------------------------------------------------------
CREATE TABLE `client_contacts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contacts_client` (`client_id`),
  CONSTRAINT `fk_contacts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 20: client_activities
-- Comprehensive interaction log (calls, meetings, quotes, orders, logs).
-- ------------------------------------------------------------------------------
CREATE TABLE `client_activities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `author` VARCHAR(100) DEFAULT NULL,
  `activity_type` ENUM('meeting', 'call', 'email', 'site_visit', 'quote_issued', 'order_placed', 'consumption_logged', 'note') NOT NULL DEFAULT 'note',
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `activity_text` VARCHAR(255) DEFAULT NULL,
  `activity_time` DATETIME DEFAULT NULL,
  `activity_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activities_client` (`client_id`),
  KEY `idx_activities_sales_rep` (`sales_rep_id`),
  KEY `idx_activities_date` (`activity_date`),
  CONSTRAINT `fk_act_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 21: customer_followups
-- Pipeline follow-ups, scheduled calls, task reminders, and deal closure tracking.
-- ------------------------------------------------------------------------------
CREATE TABLE `customer_followups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `owner` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'cancelled', 'New', 'Follow-up', 'Won', 'Lost') NOT NULL DEFAULT 'pending',
  `last_contact` DATE DEFAULT NULL,
  `next_followup` DATE DEFAULT NULL,
  `scheduled_at` DATETIME DEFAULT NULL,
  `reminder_type` VARCHAR(50) NOT NULL DEFAULT 'call',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_followups_client` (`client_id`),
  KEY `idx_followups_sales_rep` (`sales_rep_id`),
  CONSTRAINT `fk_followups_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 22: client_contracts
-- Long-term gas supply contracts, take-or-pay agreements, and SLA policies.
-- ------------------------------------------------------------------------------
CREATE TABLE `client_contracts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `contract_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `contract_type` ENUM('fixed_supply','rental_tank','take_or_pay','consignment') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `billing_frequency` ENUM('monthly','quarterly','annual') NOT NULL,
  `penalty_rate` DECIMAL(5,2) DEFAULT 0.00,
  `minimum_commitment_tons` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('active','expired','terminated') DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `sla_delivery_hours` INT(11) DEFAULT 24,
  `emergency_delivery_fee` DECIMAL(10,2) DEFAULT 0.00,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_contract_number` (`contract_number`),
  KEY `idx_cc_client` (`client_id`),
  CONSTRAINT `fk_client_contracts_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 23: customer_tanks
-- On-site cryogenic storage tanks with IoT telemetry integration.
-- ------------------------------------------------------------------------------
CREATE TABLE `customer_tanks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `serial_number` VARCHAR(50) NOT NULL,
  `tank_type` VARCHAR(50) NOT NULL,
  `capacity_liters` DECIMAL(10,2) NOT NULL,
  `current_level_liters` DECIMAL(10,2) DEFAULT 0.00,
  `location_address` TEXT NOT NULL,
  `telemetry_installed` TINYINT(1) DEFAULT 0,
  `telemetry_device_id` VARCHAR(50) DEFAULT NULL,
  `last_inspection_date` DATE DEFAULT NULL,
  `status` ENUM('operational','maintenance','decommissioned') DEFAULT 'operational',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tank_serial` (`serial_number`),
  KEY `idx_tanks_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 24: supplier_products
-- Supplier price catalogs and procurement references.
-- ------------------------------------------------------------------------------
CREATE TABLE `supplier_products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_reference` VARCHAR(50) NOT NULL,
  `item_code` VARCHAR(50) NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT 0.00,
  `unit` VARCHAR(50) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'SAR',
  `lead_time_days` INT(11) DEFAULT 3,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sp_supplier` (`supplier_reference`),
  CONSTRAINT `fk_supplier_products` FOREIGN KEY (`supplier_reference`) REFERENCES `partners` (`reference_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 25: raw_materials
-- Industrial gas feedstocks, liquid bulk tanks (O2, N2, CO2, Ar, He).
-- ------------------------------------------------------------------------------
CREATE TABLE `raw_materials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_sku` VARCHAR(50) NOT NULL,
  `material_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `unit` VARCHAR(20) NOT NULL,
  `handling_notes` TEXT DEFAULT NULL,
  `opening_stock` DECIMAL(12,2) DEFAULT 0.00,
  `current_stock` DECIMAL(12,2) DEFAULT 0.00,
  `max_capacity` DECIMAL(12,2) DEFAULT 0.00,
  `safety_stock_threshold` DECIMAL(12,2) DEFAULT 0.00,
  `warehouse_zone` VARCHAR(50) DEFAULT NULL,
  `lead_time_days` INT(11) DEFAULT 1,
  `stock_status` ENUM('optimal','warning','critical') DEFAULT 'optimal',
  `supplier_id` INT(11) DEFAULT NULL,
  `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rm_sku` (`material_sku`),
  KEY `idx_rm_supplier` (`supplier_id`),
  CONSTRAINT `raw_materials_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 26: warehouses
-- Central depots, plant storage yards, and client on-site custody stores.
-- ------------------------------------------------------------------------------
CREATE TABLE `warehouses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `is_client_custody` TINYINT(1) DEFAULT 0,
  `client_id` INT(11) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `manager_name` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_warehouse_code` (`code`),
  KEY `idx_warehouse_client` (`client_id`),
  CONSTRAINT `fk_warehouse_client_idx` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 27: gas_mixtures
-- Formulations, recipe specifications, calibration and welding gas blends.
-- ------------------------------------------------------------------------------
CREATE TABLE `gas_mixtures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mixture_name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `gas_components` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `volume` VARCHAR(20) DEFAULT NULL,
  `container_capacity` DECIMAL(10,2) DEFAULT 0.00,
  `pressure_bar` DECIMAL(8,2) DEFAULT NULL,
  `tolerance_percentage` DECIMAL(5,2) DEFAULT 1.00,
  `filling_time_minutes` INT(11) DEFAULT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT(11) DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mixture_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 5: PRODUCTION, WAREHOUSES & QUOTATIONS (TIER 5)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 28: material_transactions
-- Inbound raw gas deliveries, production consumption, and tank adjustments.
-- ------------------------------------------------------------------------------
CREATE TABLE `material_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_id` INT(11) NOT NULL,
  `transaction_ref` VARCHAR(50) NOT NULL,
  `type` ENUM('in','out','adjustment') NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  `source_ref` VARCHAR(100) DEFAULT NULL,
  `logged_by` VARCHAR(100) DEFAULT NULL,
  `transaction_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mat_trans_material` (`material_id`),
  CONSTRAINT `material_transactions_material_fk` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 29: warehouse_inventory
-- Live balances of items, cylinders, and spare equipment across warehouses.
-- ------------------------------------------------------------------------------
CREATE TABLE `warehouse_inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` INT(11) NOT NULL,
  `item_type` ENUM('cylinder','liquid_bulk','raw_material','equipment') NOT NULL,
  `item_reference_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(20) NOT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wi_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_inventory_warehouse_idx` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 30: warehouse_transactions
-- Custody deposits, withdrawals, transfers, and inventory balance shifts.
-- ------------------------------------------------------------------------------
CREATE TABLE `warehouse_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` INT(11) NOT NULL,
  `type` ENUM('receive','dispatch','transfer_in','transfer_out','custody_deposit','custody_withdraw') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `source_destination` VARCHAR(255) DEFAULT NULL,
  `authorized_by` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `document_reference` VARCHAR(100) DEFAULT NULL,
  `transaction_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wt_inventory` (`inventory_id`),
  CONSTRAINT `fk_trans_inventory_idx` FOREIGN KEY (`inventory_id`) REFERENCES `warehouse_inventory` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 31: production_work_orders
-- Work orders for cylinder filling, mixture synthesis, and plant processing.
-- ------------------------------------------------------------------------------
CREATE TABLE `production_work_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `raw_material_id` INT(11) NOT NULL,
  `consumed_qty` DECIMAL(12,2) NOT NULL,
  `status` ENUM('draft','in_progress','completed','cancelled') DEFAULT 'draft',
  `logged_by` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pwo_order_number` (`order_number`),
  KEY `idx_pwo_raw_mat` (`raw_material_id`),
  CONSTRAINT `fk_pwo_raw_mat` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 32: shift_logs
-- Plant daily operational metrics, compressor running hours, power & production.
-- ------------------------------------------------------------------------------
CREATE TABLE `shift_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `shift_code` VARCHAR(50) NOT NULL,
  `shift_date` DATE NOT NULL,
  `shift_type` ENUM('Morning','Evening','Night') NOT NULL,
  `supervisor` VARCHAR(100) NOT NULL,
  `operator` VARCHAR(100) NOT NULL,
  `compressor_hours` DECIMAL(6,2) DEFAULT 0.00,
  `power_consumption_kwh` DECIMAL(10,2) DEFAULT 0.00,
  `cooling_water_temp_c` DECIMAL(5,2) DEFAULT NULL,
  `ambient_temp_c` DECIMAL(5,2) DEFAULT NULL,
  `output_o2_m3` DECIMAL(10,2) DEFAULT 0.00,
  `output_n2_m3` DECIMAL(10,2) DEFAULT 0.00,
  `output_ar_m3` DECIMAL(10,2) DEFAULT 0.00,
  `liquid_produced_liters` DECIMAL(10,2) DEFAULT 0.00,
  `downtime_minutes` INT(11) DEFAULT 0,
  `downtime_reason` TEXT DEFAULT NULL,
  `safety_incidents` INT(11) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shift_code` (`shift_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 33: cylinder_batches
-- Cylinder manufacturing and hydro-test qualification batches.
-- ------------------------------------------------------------------------------
CREATE TABLE `cylinder_batches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_number` VARCHAR(50) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `gas_classification` VARCHAR(50) NOT NULL,
  `volume` VARCHAR(20) NOT NULL,
  `build_material` VARCHAR(50) NOT NULL,
  `valve_spec` VARCHAR(50) NOT NULL,
  `color_coding` VARCHAR(50) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `raw_material_id` INT(11) DEFAULT NULL,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `supplier_id` INT(11) DEFAULT NULL,
  `po_ref` VARCHAR(50) DEFAULT NULL,
  `mfg_date` DATE NOT NULL,
  `working_pressure` INT(11) NOT NULL,
  `test_pressure` INT(11) NOT NULL,
  `initial_test_date` DATE NOT NULL,
  `next_test_due` DATE NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_batch_number` (`batch_number`),
  KEY `idx_cb_supplier` (`supplier_id`),
  KEY `idx_cb_raw_mat` (`raw_material_id`),
  CONSTRAINT `cylinder_batches_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cb_raw_mat` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 34: quotations
-- Official price quotations with VAT calculations and Sales Rep ownership.
-- ------------------------------------------------------------------------------
CREATE TABLE `quotations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL,
  `specs` VARCHAR(255) DEFAULT NULL,
  `company_name` VARCHAR(255) DEFAULT NULL,
  `company_logo` VARCHAR(255) DEFAULT NULL,
  `issue_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `status` ENUM('draft', 'sent', 'accepted', 'declined', 'expired', 'converted') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `vat_percentage` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `vat_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms` VARCHAR(100) NOT NULL DEFAULT 'Net 30',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quotation_number` (`quotation_number`),
  KEY `idx_quotes_client` (`client_id`),
  KEY `idx_quotes_sales_rep` (`sales_rep_id`),
  KEY `idx_quotes_status` (`status`),
  CONSTRAINT `fk_quotes_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 6: CYLINDERS, QUOTATION ITEMS & ORDERS (TIER 6)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 35: cylinders
-- Individual barcoded gas cylinders with lifecycle status and custody tracking.
-- ------------------------------------------------------------------------------
CREATE TABLE `cylinders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `barcode` VARCHAR(50) NOT NULL,
  `batch_id` INT(11) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `status` ENUM('in_plant_empty','in_plant_filled','dispatched_client','client_possession','maintenance','retired') DEFAULT 'in_plant_empty',
  `current_client_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cylinder_barcode` (`barcode`),
  KEY `idx_cyl_batch` (`batch_id`),
  KEY `idx_cyl_client` (`current_client_id`),
  CONSTRAINT `cylinders_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `cylinder_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 36: quotation_items
-- Itemized products on quotations (supporting legacy names and modern product_id).
-- ------------------------------------------------------------------------------
CREATE TABLE `quotation_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `product_name` VARCHAR(255) DEFAULT NULL,
  `qty` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
  `quantity` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
  `consumed_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(50) DEFAULT 'unit',
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_qi_quotation` (`quotation_id`),
  KEY `idx_qi_product` (`product_id`),
  CONSTRAINT `fk_qi_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_qi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 37: sales_orders
-- Confirmed sales orders converted from accepted quotations or contract call-offs.
-- ------------------------------------------------------------------------------
CREATE TABLE `sales_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL,
  `quotation_id` INT(11) DEFAULT NULL,
  `customer_po_number` VARCHAR(100) DEFAULT NULL,
  `order_date` DATE NOT NULL,
  `delivery_deadline` DATE DEFAULT NULL,
  `delivery_address` TEXT NOT NULL,
  `status` ENUM('pending', 'confirmed', 'processing', 'dispatched', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `vat_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_so_order_number` (`order_number`),
  KEY `idx_orders_client` (`client_id`),
  KEY `idx_orders_sales_rep` (`sales_rep_id`),
  KEY `idx_orders_quotation` (`quotation_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 38: purchase_orders
-- Legacy and partner purchase order execution table.
-- ------------------------------------------------------------------------------
CREATE TABLE `purchase_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `customer_po_number` VARCHAR(100) DEFAULT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `production_supervisor_id` INT(11) DEFAULT NULL,
  `quotation_id` INT(11) DEFAULT NULL,
  `supplier_reference` VARCHAR(50) DEFAULT NULL,
  `specs` TEXT DEFAULT NULL,
  `order_date` DATE NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `delivery_date` DATE NOT NULL,
  `delivery_time` TIME DEFAULT NULL,
  `delivery_priority` ENUM('standard','urgent','critical') DEFAULT 'standard',
  `payment_terms` VARCHAR(50) DEFAULT 'cod',
  `status` ENUM('draft','submitted','under_review','confirmed','in_production','dispatched','delivered','cancelled') DEFAULT 'submitted',
  `production_status` ENUM('pending','ready_for_dispatch','production_scheduled') DEFAULT 'pending',
  `total_value` DECIMAL(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_po_order_number` (`order_number`),
  KEY `idx_po_client` (`client_id`),
  KEY `idx_po_sales_rep` (`sales_rep_id`),
  KEY `idx_po_quotation` (`quotation_id`),
  CONSTRAINT `fk_po_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_po_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 7: ORDER ITEMS, CYLINDER LEDGER & TELEMETRY CONSUMPTION (TIER 7)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 39: order_items
-- Itemized products under sales orders.
-- ------------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` DECIMAL(15,2) NOT NULL,
  `delivered_quantity` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `total_price` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 40: purchase_order_items
-- Items registered under legacy client purchase orders.
-- ------------------------------------------------------------------------------
CREATE TABLE `purchase_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` INT(11) NOT NULL,
  `supplier_reference` VARCHAR(50) DEFAULT NULL,
  `product_id` INT(11) NOT NULL DEFAULT 0,
  `item_name` VARCHAR(255) NOT NULL,
  `item_code` VARCHAR(50) DEFAULT NULL,
  `qty` DECIMAL(10,2) NOT NULL,
  `consumed_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(50) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `discount_pct` DECIMAL(5,2) DEFAULT 0.00,
  `line_total` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_poi_po` (`purchase_order_id`),
  CONSTRAINT `fk_purchase_order_items_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 41: po_releases
-- Partial delivery releases and call-offs for multi-phase orders.
-- ------------------------------------------------------------------------------
CREATE TABLE `po_releases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `po_id` INT(11) NOT NULL,
  `release_number` VARCHAR(50) NOT NULL,
  `release_date` DATE NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `status` ENUM('pending','approved','dispatched','delivered') DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `dispatched_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_release_number` (`release_number`),
  KEY `idx_pr_po` (`po_id`),
  CONSTRAINT `fk_poreleases_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 42: po_release_items
-- Item breakdown for scheduled order delivery releases.
-- ------------------------------------------------------------------------------
CREATE TABLE `po_release_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `po_release_id` INT(11) NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(50) NOT NULL,
  `dispatched_quantity` DECIMAL(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_pri_pr` (`po_release_id`),
  CONSTRAINT `fk_poreleaseitems_pr` FOREIGN KEY (`po_release_id`) REFERENCES `po_releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 43: order_status_logs
-- Status transition history and audit timestamps for purchase orders.
-- ------------------------------------------------------------------------------
CREATE TABLE `order_status_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osl_order` (`order_id`),
  CONSTRAINT `fk_order_status_log` FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 44: cylinder_logs
-- Scan history, fill status, warehouse movements, and client possession tracking.
-- ------------------------------------------------------------------------------
CREATE TABLE `cylinder_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cylinder_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `from_location` VARCHAR(100) DEFAULT NULL,
  `to_location` VARCHAR(100) DEFAULT NULL,
  `reference_id` VARCHAR(50) DEFAULT NULL,
  `operator` VARCHAR(100) DEFAULT NULL,
  `logged_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cl_cylinder` (`cylinder_id`),
  CONSTRAINT `fk_cyl_logs_cylinder` FOREIGN KEY (`cylinder_id`) REFERENCES `cylinders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 45: client_cylinder_ledger
-- Client cylinder custody balance, dispatch and return tracking.
-- ------------------------------------------------------------------------------
CREATE TABLE `client_cylinder_ledger` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `cylinder_id` INT(11) NOT NULL,
  `dispatched_date` DATE NOT NULL,
  `returned_date` DATE DEFAULT NULL,
  `status` ENUM('with_client','returned','lost') DEFAULT 'with_client',
  `delivery_ref` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ccl_client` (`client_id`),
  KEY `idx_ccl_cylinder` (`cylinder_id`),
  CONSTRAINT `fk_ledger_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 46: production_order_cylinders
-- Cylinders filled and processed under plant work orders.
-- ------------------------------------------------------------------------------
CREATE TABLE `production_order_cylinders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` INT(11) NOT NULL,
  `cylinder_id` INT(11) NOT NULL,
  `filled_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_poc_cylinder` (`cylinder_id`),
  KEY `idx_poc_wo` (`work_order_id`),
  CONSTRAINT `fk_poc_cylinder` FOREIGN KEY (`cylinder_id`) REFERENCES `cylinders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_poc_pwo` FOREIGN KEY (`work_order_id`) REFERENCES `production_work_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 47: dispatches
-- Delivery manifests, vehicle loading, driver routes, and trip execution.
-- ------------------------------------------------------------------------------
CREATE TABLE `dispatches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `manifest_id` VARCHAR(50) NOT NULL,
  `order_id` INT(11) NOT NULL,
  `order_ref` VARCHAR(100) DEFAULT NULL,
  `driver_id` INT(11) NOT NULL,
  `vehicle_id` INT(11) NOT NULL,
  `route_zone` VARCHAR(100) NOT NULL,
  `dispatch_date` DATE NOT NULL,
  `dispatch_time` TIME NOT NULL,
  `cylinders_loaded` INT(11) DEFAULT 0,
  `status` ENUM('assigned','loaded','in_transit','delivered','returned') DEFAULT 'assigned',
  `delivery_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_manifest_id` (`manifest_id`),
  KEY `idx_disp_order` (`order_id`),
  KEY `idx_disp_driver` (`driver_id`),
  KEY `idx_disp_vehicle` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 48: consumptions
-- Client telemetry gas consumption, bulk tank refilling, and meter readings.
-- Strictly tied to Sales Rep ownership and client for IDOR-free isolation.
-- ------------------------------------------------------------------------------
CREATE TABLE `consumptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `consumption_code` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL,
  `sales_order_id` INT(11) DEFAULT NULL,
  `product_id` INT(11) NOT NULL,
  `reading_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quantity_consumed` DECIMAL(15,2) NOT NULL,
  `unit` VARCHAR(50) NOT NULL,
  `flow_rate_or_pressure` VARCHAR(100) DEFAULT NULL COMMENT 'E.g. 180 Bar, 95% Tank Telemetry Level',
  `logged_by_user_id` INT(11) NOT NULL,
  `batch_or_cylinder_id` VARCHAR(100) DEFAULT NULL,
  `delivery_status` ENUM('verified', 'disputed', 'invoiced') NOT NULL DEFAULT 'verified',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_consumption_code` (`consumption_code`),
  KEY `idx_consumptions_client` (`client_id`),
  KEY `idx_consumptions_sales_rep` (`sales_rep_id`),
  KEY `idx_consumptions_order` (`sales_order_id`),
  KEY `idx_consumptions_product` (`product_id`),
  KEY `idx_consumptions_date` (`reading_date`),
  CONSTRAINT `fk_cns_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cns_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cns_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cns_logger` FOREIGN KEY (`logged_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 8: FINANCE: INVOICES, ITEMS, PAYMENTS & COMMISSIONS (TIER 8)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Table 49: invoices
-- Customer billing invoices with VAT breakdowns, payments, and sales rep tracing.
-- ------------------------------------------------------------------------------
CREATE TABLE `invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `order_id` INT(11) DEFAULT NULL,
  `po_reference` VARCHAR(50) DEFAULT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `vat_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('unpaid', 'pending', 'partially_paid', 'partial', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'unpaid',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invoice_number` (`invoice_number`),
  KEY `idx_invoices_client` (`client_id`),
  KEY `idx_invoices_sales_rep` (`sales_rep_id`),
  KEY `idx_invoices_order` (`order_id`),
  KEY `idx_invoices_status` (`status`),
  CONSTRAINT `fk_invoices_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 50: invoice_items
-- Granular line items and product descriptions billed on invoices.
-- ------------------------------------------------------------------------------
CREATE TABLE `invoice_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(15,2) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `total_price` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ii_invoice` (`invoice_id`),
  KEY `idx_ii_product` (`product_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ii_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 51: payments
-- Payments received against invoices, with audit tracking and sales rep isolation.
-- ------------------------------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `payment_reference` VARCHAR(50) NOT NULL,
  `invoice_id` INT(11) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('bank_transfer', 'check', 'credit_card', 'cash') NOT NULL DEFAULT 'bank_transfer',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payment_reference` (`payment_reference`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_client` (`client_id`),
  KEY `idx_payments_sales_rep` (`sales_rep_id`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 52: receipt_vouchers
-- Cash receipt vouchers and customer collection notes.
-- ------------------------------------------------------------------------------
CREATE TABLE `receipt_vouchers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` VARCHAR(50) NOT NULL,
  `invoice_id` INT(11) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` ENUM('cash','bank_transfer','cheque') NOT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `payment_date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_receipt_number` (`receipt_number`),
  KEY `idx_rv_invoice` (`invoice_id`),
  KEY `idx_rv_client` (`client_id`),
  KEY `idx_rv_sales_rep` (`sales_rep_id`),
  CONSTRAINT `fk_receipt_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_receipt_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 53: sales_commissions
-- Sales representative commission earnings per paid customer invoice.
-- ------------------------------------------------------------------------------
CREATE TABLE `sales_commissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `sales_rep_id` INT(11) NOT NULL,
  `rate_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `commission_amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('pending','approved','paid') DEFAULT 'pending',
  `paid_at` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comm_invoice` (`invoice_id`),
  KEY `idx_comm_sales_rep` (`sales_rep_id`),
  CONSTRAINT `fk_commission_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_commission_sales_rep` FOREIGN KEY (`sales_rep_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SECTION 9: DATABASE TRIGGERS & AUTOMATION
-- ==============================================================================

DELIMITER $$

CREATE TRIGGER `after_production_order_completed` 
AFTER UPDATE ON `production_work_orders` 
FOR EACH ROW 
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        
        -- A. Deduct consumed quantity from raw material current stock
        UPDATE `raw_materials`
        SET `current_stock` = `current_stock` - NEW.consumed_qty
        WHERE `id` = NEW.raw_material_id;
        
        -- B. Log material transaction audit record
        INSERT INTO `material_transactions` 
        (`material_id`, `transaction_ref`, `type`, `quantity`, `source_ref`, `logged_by`, `transaction_date`)
        VALUES 
        (NEW.raw_material_id, CONCAT('PROD-', NEW.order_number), 'out', NEW.consumed_qty, 'Production', NEW.logged_by, NOW());
        
        -- C. Update status of linked cylinders to in-plant filled
        UPDATE `cylinders`
        SET `status` = 'in_plant_filled'
        WHERE `id` IN (SELECT `cylinder_id` FROM `production_order_cylinders` WHERE `work_order_id` = NEW.id);
        
    END IF;
END$$

DELIMITER ;

-- ==============================================================================
-- SECTION 10: COMPREHENSIVE ENTERPRISE SEED DATA
-- ==============================================================================

-- 10.1 Seed System Roles (The 10 Industrial Roles)
INSERT IGNORE INTO `roles` (`id`, `role_key`, `role_name`, `description`, `is_system`) VALUES
(1, 'admin', 'Super Administrator', 'Full unrestricted enterprise-wide control across all modules', 1),
(2, 'sales_manager', 'Sales Manager', 'Manages sales team, approvals, pricing, contracts, and clients', 1),
(3, 'sales_rep', 'Sales Representative', 'Direct management of assigned client accounts, orders, and consumptions', 1),
(4, 'plant_manager', 'Plant & Production Manager', 'Full control over plant output, formulations, raw materials, and tanks', 1),
(5, 'shift_supervisor', 'Production Shift Supervisor', 'Daily shift logs, cylinder inspections, and filling operations', 1),
(6, 'logistics_manager', 'Logistics & Fleet Manager', 'Oversees dispatch schedule, vehicles, drivers, and maintenance', 1),
(7, 'driver', 'Fleet Driver & Courier', 'Trip execution, mileage logging, and delivery verification', 1),
(8, 'warehouse_manager', 'Warehouse & Custody Manager', 'Oversees warehouse inventory, stock receipts, and custody withdrawals', 1),
(9, 'finance', 'Finance & Accountant', 'Manages cash flow, collections, invoices, and sales commissions', 1),
(10, 'compliance_officer', 'Compliance & Safety Auditor', 'Auditing, reporting, hazardous compliance, and safety inspections', 1);

-- 10.2 Seed System Permissions (43 Granular Permissions)
INSERT IGNORE INTO `permissions` (`id`, `module`, `permission_key`, `permission_name`, `description`) VALUES
-- Users & Roles
(1, 'users', 'users.view', 'View Users', 'View staff and user accounts'),
(2, 'users', 'users.create', 'Create Users', 'Create new staff members'),
(3, 'users', 'users.edit', 'Edit Users', 'Edit staff profiles and permissions'),
(4, 'users', 'users.delete', 'Delete Users', 'Delete staff accounts'),
(5, 'users', 'roles.manage', 'Manage Roles & RBAC', 'Assign roles and configure system permissions'),

-- Clients & CRM
(6, 'clients', 'clients.view', 'View Clients', 'View client directory and profiles'),
(7, 'clients', 'clients.view_all', 'View All Clients', 'View all clients unrestricted by sales rep assignment'),
(8, 'clients', 'clients.create', 'Create Clients', 'Register new client accounts'),
(9, 'clients', 'clients.edit', 'Edit Clients', 'Edit existing client information and terms'),
(10, 'clients', 'clients.delete', 'Delete Clients', 'Remove client accounts'),

-- Quotations
(11, 'quotations', 'quotations.view', 'View Quotations', 'View quotations list and details'),
(12, 'quotations', 'quotations.create', 'Create Quotations', 'Create and send new price quotations'),
(13, 'quotations', 'quotations.edit', 'Edit Quotations', 'Edit existing draft quotations'),
(14, 'quotations', 'quotations.delete', 'Delete Quotations', 'Remove quotations from system'),
(15, 'quotations', 'quotations.approve', 'Approve Quotations', 'Approve and accept quotations for PO conversion'),

-- Orders & Operations
(16, 'orders', 'orders.view', 'View Orders', 'View purchase and sales orders'),
(17, 'orders', 'orders.create', 'Create Orders', 'Create new purchase orders or call-offs'),
(18, 'orders', 'orders.edit', 'Edit Orders', 'Update purchase orders and delivery details'),
(19, 'orders', 'orders.delete', 'Delete Orders', 'Cancel or delete orders'),
(20, 'orders', 'orders.approve', 'Approve Orders', 'Approve orders for production and dispatch'),

-- Invoices & Collections
(21, 'invoices', 'invoices.view', 'View Invoices', 'View invoices and account statements'),
(22, 'invoices', 'invoices.create', 'Create Invoices', 'Generate billing invoices'),
(23, 'invoices', 'invoices.edit', 'Edit Invoices', 'Modify invoice line items and status'),
(24, 'invoices', 'invoices.manage', 'Manage Collections', 'Record payments and reconcile receipt vouchers'),

-- Production & Gas Mixtures
(25, 'production', 'production.view', 'View Production', 'View raw materials, formulations, and shift logs'),
(26, 'production', 'production.create', 'Create Production Logs', 'Log shifts, register cylinder batches, create formulations'),
(27, 'production', 'production.edit', 'Edit Production Records', 'Edit shift logs, materials, and batch parameters'),
(28, 'production', 'production.delete', 'Delete Production Records', 'Delete formulations, batches, or raw material records'),

-- Warehouse & Custody
(29, 'warehouse', 'warehouse.view', 'View Warehouses', 'View warehouses and inventory balances'),
(30, 'warehouse', 'warehouse.create', 'Create Warehouses', 'Add new warehouse or custody locations'),
(31, 'warehouse', 'warehouse.edit', 'Edit Warehouse Items', 'Add inventory items and edit locations'),
(32, 'warehouse', 'warehouse.delete', 'Delete Warehouses', 'Delete warehouses and inventory entries'),
(33, 'warehouse', 'warehouse.withdraw', 'Withdraw & Transfer', 'Perform inventory withdrawals and inter-warehouse transfers'),

-- Logistics & Fleet
(34, 'logistics', 'logistics.view', 'View Fleet & Logistics', 'View vehicles, drivers, dispatches, and maintenance'),
(35, 'logistics', 'logistics.create', 'Create Logistics Records', 'Add vehicles, drivers, dispatches, and tickets'),
(36, 'logistics', 'logistics.edit', 'Edit Logistics Records', 'Update vehicle/driver details and renew documents'),
(37, 'logistics', 'logistics.delete', 'Delete Logistics Records', 'Delete vehicles, drivers, tickets, or dispatches'),

-- Finance & Reporting
(38, 'finance', 'finance.view', 'View Financial Reports', 'View cash flow, P&L, and balance metrics'),
(39, 'finance', 'finance.manage', 'Manage Financial Operations', 'Configure payment terms and cash flow settings'),
(40, 'finance', 'commissions.approve', 'Approve Commissions', 'Approve and pay sales team commissions'),
(41, 'reports', 'reports.view', 'View Reports', 'Access module reports and analytical dashboards'),
(42, 'reports', 'reports.export', 'Export Reports', 'Export reports to CSV / Excel'),

-- System Administration
(43, 'settings', 'settings.manage', 'Manage System Settings', 'Configure system parameters, backups, and security');

-- 10.3 Map Role Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.role_key IN ('admin', 'Admin');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'clients.view', 'clients.view_all', 'clients.create', 'clients.edit', 'clients.delete',
    'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete', 'quotations.approve',
    'orders.view', 'orders.create', 'orders.edit', 'orders.approve',
    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.manage',
    'finance.view', 'commissions.approve', 'reports.view', 'reports.export'
) WHERE r.role_key IN ('sales_manager', 'Sales Manager');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'clients.view', 'clients.create', 'clients.edit',
    'quotations.view', 'quotations.create', 'quotations.edit',
    'orders.view', 'orders.create',
    'invoices.view', 'reports.view'
) WHERE r.role_key IN ('sales_rep', 'Sales Rep');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'production.view', 'production.create', 'production.edit', 'production.delete',
    'warehouse.view', 'warehouse.withdraw',
    'reports.view', 'reports.export'
) WHERE r.role_key IN ('plant_manager', 'Production Manager');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'production.view', 'production.create', 'production.edit',
    'warehouse.view'
) WHERE r.role_key IN ('shift_supervisor', 'Production Supervisor');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'logistics.view', 'logistics.create', 'logistics.edit', 'logistics.delete',
    'orders.view', 'reports.view', 'reports.export'
) WHERE r.role_key IN ('logistics_manager', 'Logistics Manager');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'logistics.view', 'logistics.create', 'orders.view'
) WHERE r.role_key IN ('driver', 'Logistics Assistant');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'warehouse.view', 'warehouse.create', 'warehouse.edit', 'warehouse.delete', 'warehouse.withdraw',
    'production.view', 'reports.view', 'reports.export'
) WHERE r.role_key IN ('warehouse_manager', 'Warehouse Manager');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.manage',
    'finance.view', 'finance.manage', 'commissions.approve',
    'clients.view', 'orders.view', 'reports.view', 'reports.export'
) WHERE r.role_key IN ('finance', 'Finance');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.permission_key IN (
    'clients.view', 'quotations.view', 'orders.view', 'invoices.view',
    'production.view', 'warehouse.view', 'logistics.view', 'reports.view'
) WHERE r.role_key IN ('compliance_officer', 'Read Only');

-- 10.4 Seed System Settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `category`) VALUES
('app_language', 'en', 'general'),
('auto_route_hydro', '1', 'operations'),
('backup_freq', 'daily', 'system'),
('currency', 'SAR', 'finance'),
('currency_symbol', 'ر.س', 'finance'),
('date_format', 'Y-m-d', 'general'),
('enforce_2fa', '1', 'security'),
('hq_designation', 'Jeddah Industrial — HQ', 'company'),
('ip_whitelist', '192.168.1.1, 212.34.120.45', 'security'),
('log_retention', '1year', 'system'),
('pwd_complexity', 'high', 'security'),
('region', 'Saudi Arabia', 'general'),
('reorder_threshold', '15', 'inventory'),
('seo_description', 'I-GAS Comprehensive Industrial Gas Management & ERP System.', 'marketing'),
('seo_keywords', 'Gas, ERP, Logistics, Industrial, Management', 'marketing'),
('session_timeout', '30', 'security'),
('site_title', 'I-GAS Enterprise Systems', 'general'),
('sms_alerts', '1', 'notifications'),
('sync_window', '5', 'operations'),
('time_format', 'H:i', 'general'),
('timezone', 'Asia/Riyadh', 'general');

-- 10.5 Seed Users
INSERT IGNORE INTO `users` (`id`, `emp_id`, `role_id`, `role`, `permission_level`, `manager_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `status`) VALUES
(1, 'EMP-ADM-001', 1, 'admin', 'Admin', NULL, 'admin', 'admin@igas-crm.com', '$2y$12$KkQcO1.wW75fM0v0Vf0s6uX89XqO4x/T5h1m5XwZ/WwXqO4x/T5h1', 'System Administrator', '+966500000001', 'Active'),
(2, 'ADMIN-001', 1, 'admin', 'Admin', NULL, 'ammar', 'ammar@gmail.com', '$2y$10$xxNF2dodMg4N/4.IvoLIMebH9ZiAMS.KLo7bd4AL9hZsBQ4ndxqye', 'Eng / Ammar Ahmed Mostafa', '+966500000002', 'Active'),
(3, 'EMP-REP-001', 3, 'sales_rep', 'View', 2, 'ahmedb', 'ahmedb@gmail.com', '$2y$10$R0iqafSkXsodayATux6qE.rCzZZCNLunMCs7FfSps.9sLwGV5xoyi', 'Ahmed Albana', '2025550174', 'Active'),
(4, 'EMP-REP-002', 3, 'sales_rep', 'Edit', 2, 'rep_fahad', 'fahad@igas-crm.com', '$2y$12$KkQcO1.wW75fM0v0Vf0s6uX89XqO4x/T5h1m5XwZ/WwXqO4x/T5h1', 'Fahad Al-Otaibi', '+966500000003', 'Active'),
(5, 'EMP-REP-003', 3, 'sales_rep', 'Edit', 2, 'rep_sara', 'sara@igas-crm.com', '$2y$12$KkQcO1.wW75fM0v0Vf0s6uX89XqO4x/T5h1m5XwZ/WwXqO4x/T5h1', 'Sara Al-Ghamdi', '+966500000004', 'Active');

-- 10.6 Seed Products
INSERT IGNORE INTO `products` (`id`, `sku`, `name`, `category`, `purity`, `unit`, `unit_price`, `cost_price`, `stock_quantity`, `min_stock_alert`, `is_active`) VALUES
(1, 'GAS-O2-MED', 'Medical Oxygen 99.5%', 'Compressed Gas', '99.5%', 'Cylinder', 45.00, 18.00, 350.00, 50.00, 1),
(2, 'GAS-N2-IND', 'High Purity Nitrogen', 'Compressed Gas', '99.999%', 'Cylinder', 65.00, 25.00, 220.00, 40.00, 1),
(3, 'GAS-AR-WLD', 'Pure Argon for TIG Welding', 'Compressed Gas', '99.995%', 'Cylinder', 110.00, 48.00, 180.00, 30.00, 1),
(4, 'LIQ-O2-BLK', 'Liquid Bulk Oxygen (LOX)', 'Bulk Liquid', '99.7%', 'Ton', 400.00, 160.00, 45.00, 10.00, 1),
(5, 'LIQ-N2-BLK', 'Liquid Bulk Nitrogen (LIN)', 'Bulk Liquid', '99.99%', 'Ton', 380.00, 150.00, 60.00, 12.00, 1),
(6, 'MIX-C25-MIG', 'Ar/CO2 75/25 Shielding Gas Blend', 'Gas Mixture', 'Industrial', 'Cylinder', 95.00, 40.00, 140.00, 25.00, 1);

-- 10.7 Seed Partners & CRM Clients
INSERT IGNORE INTO `partners` (`id`, `reference_id`, `partner_type`, `company_name`, `cr_number`, `contract_ref`, `country`, `city`, `address`, `tax_id`, `contact_first_name`, `contact_last_name`, `sales_rep_id`, `job_title`, `email`, `phone`, `status`, `entity_type`, `segment`, `credit_limit`, `payment_terms`) VALUES
(1, 'SUP-5684', 'supplier', 'شركه عبدالله الهاشم للتوريدات الصناعية', '73833937982', NULL, 'Saudi Arabia', 'Jeddah', 'Industrial Area Phase 3', '738338937982', 'Abdullah', 'Al-Hashem', NULL, 'مدير مبيعات', 'info@alhashem-gases.com', '12025550174', 'approved', 'Corporate', 'Chemicals', 0.00, 'Net 30 Days'),
(2, 'CLI-9784', 'client', 'ABC Industrial Fabrication Ltd', '478478947', 'CTR-2026-001', 'Saudi Arabia', 'Dammam', '1201 Orange St, Industrial Zone 2', '4789478947', 'Alex', 'Smith', 3, 'Procurement Manager', 'eee2004@gmail.com', '2025550174', 'approved', 'SME', 'Industrial Gas', 50000.00, 'cod'),
(3, 'CLI-1001', 'client', 'Saudi Aramco — Gas Distribution Div', '1010002345', 'CTR-ARAMCO-2026', 'Saudi Arabia', 'Dhahran', 'Aramco Complex Bldg 45', '300001010000003', 'Ibrahim', 'Al-Hussaini', 4, 'Operations VP', 'ibrahim.h@aramco.com', '+966138760000', 'approved', 'Enterprise', 'Energy & Petroleum', 500000.00, 'Net 60'),
(4, 'CLI-1002', 'client', 'SABIC Petrochemicals Plant 4', '1010098765', 'CTR-SABIC-2026', 'Saudi Arabia', 'Jubail Industrial City', 'Royal Commission Sector 6', '300002020000003', 'Mansour', 'Al-Zahrani', 4, 'Procurement Director', 'mansour.z@sabic.com', '+966133590000', 'approved', 'Enterprise', 'Petrochemicals', 450000.00, 'Net 45'),
(5, 'CLI-1003', 'client', 'Maaden Mining & Smelting Ops', '1010065432', 'CTR-MAADEN-2026', 'Saudi Arabia', 'Ras Al Khair', 'Industrial Port Zone', '300003030000003', 'Khalid', 'Al-Shehri', 5, 'Supply Chain Lead', 'khalid.s@maaden.com', '+966118740000', 'approved', 'Enterprise', 'Mining & Metallurgy', 300000.00, 'Net 30');

INSERT IGNORE INTO `clients` (`id`, `client_code`, `sales_rep_id`, `company_name`, `industry`, `cr_number`, `tax_id`, `city`, `address`, `credit_limit`, `current_balance`, `payment_terms`, `status`) VALUES
(1, 'CLI-1001', 4, 'Saudi Aramco — Gas Distribution Div', 'Energy & Petroleum', '1010002345', '300001010000003', 'Dhahran', 'Aramco Complex Bldg 45', 500000.00, 42500.00, 'Net 60', 'active'),
(2, 'CLI-1002', 4, 'SABIC Petrochemicals Plant 4', 'Petrochemicals', '1010098765', '300002020000003', 'Jubail Industrial City', 'Royal Commission Sector 6', 450000.00, 18750.00, 'Net 45', 'active'),
(3, 'CLI-1003', 5, 'Maaden Mining & Smelting Ops', 'Mining & Metallurgy', '1010065432', '300003030000003', 'Ras Al Khair', 'Industrial Port Zone', 300000.00, 0.00, 'Net 30', 'active'),
(4, 'CLI-9784', 3, 'ABC Industrial Fabrication Ltd', 'Industrial Gas', '478478947', '4789478947', 'Dammam', '1201 Orange St, Industrial Zone 2', 50000.00, 460.00, 'cod', 'active');

-- 10.8 Seed Client Contacts & Activities
INSERT IGNORE INTO `client_contacts` (`id`, `client_id`, `full_name`, `job_title`, `email`, `phone`, `is_primary`) VALUES
(1, 1, 'Eng. Tariq Al-Ghamdi', 'Cryogenic Systems Director', 'tariq.g@aramco.com', '+966501112233', 1),
(2, 2, 'Sultan Al-Otaibi', 'Senior Buyer', 'sultan.o@sabic.com', '+966502223344', 1),
(3, 4, 'Alex Smith', 'Procurement Manager', 'eee2004@gmail.com', '2025550174', 1);

INSERT IGNORE INTO `client_activities` (`id`, `client_id`, `sales_rep_id`, `author`, `activity_type`, `title`, `description`, `activity_text`, `activity_date`) VALUES
(1, 4, 3, 'Ahmed Albana', 'call', 'Initial Contract Discussions', 'Client interested in weekly oxygen replenishment and cylinder leases.', 'Discussed regular supplies', '2026-09-18 11:30:00'),
(2, 1, 4, 'Fahad Al-Otaibi', 'meeting', 'Q4 Bulk Liquid Oxygen Expansion Review', 'Met with Engineering Dir to evaluate 50-ton storage tank telemetry integration.', 'Met on-site', '2026-10-10 10:00:00'),
(3, 2, 4, 'Fahad Al-Otaibi', 'quote_issued', 'Issued Quote QT-2026-0042', 'Sent quote for 150 cylinders of high-purity nitrogen for scheduled maintenance shutdown.', 'Quote sent', '2026-10-12 14:15:00');

-- 10.9 Seed Raw Materials & Supplier Products
INSERT IGNORE INTO `raw_materials` (`id`, `material_sku`, `material_name`, `category`, `unit`, `opening_stock`, `current_stock`, `max_capacity`, `safety_stock_threshold`, `warehouse_zone`, `lead_time_days`, `stock_status`, `supplier_id`, `unit_cost`) VALUES
(1, 'RM-1356', 'اكسجين سائل عالي النقاوة', 'Bulk Gas', 'Metric Tons', 20.00, 46.00, 55.00, 10.00, 'الدمام - المستودع الرئيسي', 3, 'optimal', 1, 400.00);

INSERT IGNORE INTO `supplier_products` (`id`, `supplier_reference`, `item_code`, `item_name`, `category`, `quantity`, `unit`, `unit_price`, `currency`, `lead_time_days`) VALUES
(1, 'SUP-5684', 'RM-1356', 'اكسجين سائل عالي النقاوة', 'Bulk Gas', 1.00, 'Metric Tons', 400.00, 'SAR', 3);

-- 10.10 Seed Logistics: Vehicles & Drivers
INSERT IGNORE INTO `vehicles` (`id`, `fleet_id`, `plate_number`, `make_model`, `vehicle_type`, `vin`, `manufacturing_year`, `load_capacity`, `cylinder_capacity`, `fuel_type`, `driver_id`, `status`) VALUES
(1, 'FLT-101', '7841-KSA', 'Mercedes-Benz Actros 3340', 'Cryogenic Tanker', 'WDB9340331K109823', 2023, 25.00, 0, 'diesel', 'DRV-001', 'available'),
(2, 'FLT-102', '9920-KSA', 'Volvo FMX 440 Flatbed', 'Cylinder Truck', 'YV2JS02A1DA109384', 2024, 18.00, 240, 'diesel', 'DRV-002', 'available');

INSERT IGNORE INTO `drivers` (`id`, `driver_id`, `full_name`, `national_id`, `mobile_number`, `email`, `license_class`, `license_number`, `license_expiry`, `status`, `assigned_vehicle`, `hazmat_certified`) VALUES
(1, 'DRV-001', 'Salim Al-Harbi', '1098374621', '+966504445566', 'salim.h@igas-crm.com', 'Heavy Transport & Hazmat', 'LIC-98374', '2028-05-15', 'active', 'FLT-101', 1),
(2, 'DRV-002', 'Youssef Mansoor', '1087364529', '+966505556677', 'youssef.m@igas-crm.com', 'Heavy Transport & Hazmat', 'LIC-87364', '2027-11-20', 'active', 'FLT-102', 1);

-- 10.11 Seed Cylinder Batches & Barcoded Cylinders
INSERT IGNORE INTO `cylinder_batches` (`id`, `batch_number`, `sku`, `gas_classification`, `volume`, `build_material`, `valve_spec`, `color_coding`, `quantity`, `raw_material_id`, `cost_price`, `selling_price`, `supplier_id`, `po_ref`, `mfg_date`, `working_pressure`, `test_pressure`, `initial_test_date`, `next_test_due`) VALUES
(1, 'BAT-8433', 'CYL-O2-10L', 'اكسجين', '10L', 'Seamless Steel 34CrMo4', 'BS-3', 'White Shoulder / Black Body', 2, 1, 20.00, 25.00, NULL, 'PO-37405', '2026-09-21', 200, 300, '2026-09-20', '2031-10-01');

INSERT IGNORE INTO `cylinders` (`id`, `barcode`, `batch_id`, `sku`, `status`, `current_client_id`) VALUES
(1, 'BAT-8433-0001', 1, 'CYL-O2-10L', 'in_plant_filled', NULL),
(2, 'BAT-8433-0002', 1, 'CYL-O2-10L', 'in_plant_filled', NULL);

-- 10.12 Seed Quotations & Line Items
INSERT IGNORE INTO `quotations` (`id`, `quotation_number`, `client_id`, `sales_rep_id`, `specs`, `company_name`, `issue_date`, `expiry_date`, `status`, `subtotal`, `vat_percentage`, `vat_amount`, `delivery_fee`, `total_value`, `total_amount`, `payment_terms`, `notes`) VALUES
(1, 'QT-2376', 4, 3, 'DELIVERY ADDRESS / DESTINATION: Dammam Industrial Zone 2', 'ABC Industrial Fabrication Ltd', '2026-09-18', '2026-10-18', 'accepted', 1600.00, 15.00, 240.00, 0.00, 1840.00, 1840.00, 'cod', 'Delivery: I-GAS Fleet Dispatch | Terms: COD'),
(2, 'QT-2026-0041', 1, 4, 'Bulk LOX Delivery with telemetry tank refills', 'Saudi Aramco — Gas Distribution Div', '2026-10-01', '2026-11-01', 'accepted', 36000.00, 15.00, 5400.00, 1100.00, 42500.00, 42500.00, 'Net 60', 'Standard monthly cryo delivery SLA'),
(3, 'QT-2026-0042', 2, 4, 'Emergency Nitrogen Cylinders for Maintenance', 'SABIC Petrochemicals Plant 4', '2026-10-12', '2026-10-26', 'sent', 15000.00, 15.00, 2250.00, 1500.00, 18750.00, 18750.00, 'Net 45', 'Direct to Jubail Gate 3');

INSERT IGNORE INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `product_name`, `qty`, `quantity`, `consumed_qty`, `unit`, `unit_price`, `line_total`, `total_price`) VALUES
(1, 1, 4, 'اكسجين سائل عالي النقاوة', 4.00, 4.00, 1.00, 'Metric Tons', 400.00, 1600.00, 1600.00),
(2, 2, 4, 'Liquid Bulk Oxygen (LOX)', 90.00, 90.00, 35.00, 'Ton', 400.00, 36000.00, 36000.00),
(3, 3, 2, 'High Purity Nitrogen', 150.00, 150.00, 0.00, 'Cylinder', 100.00, 15000.00, 15000.00);

-- 10.13 Seed Orders (Sales Orders & Purchase Orders)
INSERT IGNORE INTO `purchase_orders` (`id`, `order_number`, `customer_po_number`, `client_id`, `sales_rep_id`, `quotation_id`, `specs`, `order_date`, `delivery_address`, `delivery_date`, `delivery_priority`, `payment_terms`, `status`, `production_status`, `total_value`) VALUES
(2, 'PO-37405', 'CUST-PO-9912', 2, 3, 1, 'Delivery to Dammam Warehouse Gate 1', '2026-09-18', 'الدمام - المنطقة الصناعية', '2026-09-19', 'standard', 'cod', 'delivered', 'ready_for_dispatch', 460.00);

INSERT IGNORE INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `item_name`, `qty`, `consumed_qty`, `unit`, `unit_price`, `discount_pct`, `line_total`) VALUES
(2, 2, 4, 'اكسجين سائل عالي النقاوة', 1.00, 0.00, 'Metric Tons', 400.00, 0.00, 400.00);

INSERT IGNORE INTO `sales_orders` (`id`, `order_number`, `client_id`, `sales_rep_id`, `quotation_id`, `customer_po_number`, `order_date`, `delivery_deadline`, `delivery_address`, `status`, `subtotal`, `vat_amount`, `total_amount`, `notes`) VALUES
(1, 'SO-2026-0089', 1, 4, 2, 'ARAMCO-PO-4500912', '2026-10-05', '2026-10-15', 'Aramco Complex Dhahran Gas Station 4', 'delivered', 36000.00, 5400.00, 42500.00, 'Fully delivered via FLT-101 cryo tanker'),
(2, 'SO-2026-0090', 4, 3, 1, 'CUST-PO-9912', '2026-09-18', '2026-09-20', '1201 Orange St, Industrial Zone 2, Dammam', 'delivered', 400.00, 60.00, 460.00, 'Delivered and verified');

INSERT IGNORE INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `delivered_quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 4, 90.00, 90.00, 400.00, 36000.00),
(2, 2, 4, 1.00, 1.00, 400.00, 400.00);

-- 10.14 Seed Gas Telemetry Consumptions
INSERT IGNORE INTO `consumptions` (`id`, `consumption_code`, `client_id`, `sales_rep_id`, `sales_order_id`, `product_id`, `reading_date`, `quantity_consumed`, `unit`, `flow_rate_or_pressure`, `logged_by_user_id`, `batch_or_cylinder_id`, `delivery_status`, `notes`) VALUES
(1, 'CNS-2026-0012', 1, 4, 1, 4, '2026-10-11 08:30:00', 35.00, 'Ton', 'Tank Telemetry Level 92%', 2, 'BAT-8433', 'verified', 'Cryo Tank #1 automated telemetry top-up'),
(2, 'CNS-2026-0013', 4, 3, 2, 4, '2026-09-20 14:00:00', 1.00, 'Metric Tons', '180 Bar Pressure', 2, 'BAT-8433-0001', 'invoiced', 'Direct manifold cylinder refill');

-- 10.15 Seed Finance: Invoices, Items, Payments & Vouchers
INSERT IGNORE INTO `invoices` (`id`, `invoice_number`, `client_id`, `sales_rep_id`, `order_id`, `po_reference`, `issue_date`, `due_date`, `amount`, `subtotal`, `vat_amount`, `total_amount`, `paid_amount`, `balance_due`, `status`) VALUES
(1, 'INV-89625', 4, 3, 2, 'PO-37405', '2026-09-20', '2026-09-20', 460.00, 400.00, 60.00, 460.00, 460.00, 0.00, 'paid'),
(2, 'INV-2026-0104', 1, 4, 1, 'ARAMCO-PO-4500912', '2026-10-12', '2026-12-12', 42500.00, 36000.00, 5400.00, 42500.00, 0.00, 42500.00, 'unpaid');

INSERT IGNORE INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 4, 'اكسجين سائل عالي النقاوة - Metric Tons', 1.00, 400.00, 400.00),
(2, 2, 4, 'Liquid Bulk Oxygen (LOX) Supply (90 Tons)', 90.00, 400.00, 36000.00);

INSERT IGNORE INTO `payments` (`id`, `payment_reference`, `invoice_id`, `client_id`, `sales_rep_id`, `amount`, `payment_date`, `payment_method`, `notes`) VALUES
(1, 'PAY-2026-0001', 1, 4, 3, 460.00, '2026-09-20', 'cash', 'Quick counter payment reconciled');

INSERT IGNORE INTO `receipt_vouchers` (`id`, `receipt_number`, `invoice_id`, `client_id`, `sales_rep_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `notes`) VALUES
(1, 'RV-AUTO-1', 1, 2, 3, 460.00, 'cash', 'TRX-PAY-89625', '2026-09-20', 'Auto Quick Pay Reconciled');

INSERT IGNORE INTO `sales_commissions` (`id`, `invoice_id`, `sales_rep_id`, `rate_percentage`, `commission_amount`, `status`, `paid_at`) VALUES
(1, 1, 3, 3.00, 12.00, 'paid', '2026-09-30');

-- 10.16 Seed Material Transactions
INSERT IGNORE INTO `material_transactions` (`id`, `material_id`, `transaction_ref`, `type`, `quantity`, `source_ref`, `logged_by`, `transaction_date`) VALUES
(1, 1, 'TRX-19752', 'in', 30.00, 'ORD-7087', 'System', '2026-09-18 11:06:58'),
(2, 1, 'SAL-832463', 'out', 4.00, 'QT-2376', 'Eng / Ammar Ahmed Mostafa', '2026-09-18 11:33:49');

-- ==============================================================================
-- SECTION 11: BACKWARD COMPATIBILITY VIEWS
-- ==============================================================================

CREATE OR REPLACE VIEW `view_clients_partners` AS
SELECT 
    c.id,
    c.client_code AS reference_id,
    'client' AS partner_type,
    c.company_name,
    COALESCE(c.cr_number, '') AS cr_number,
    NULL AS contract_ref,
    'Saudi Arabia' AS country,
    c.city,
    c.address,
    COALESCE(c.tax_id, '') AS tax_id,
    COALESCE(cc.full_name, 'General Procurement') AS contact_first_name,
    '' AS contact_last_name,
    c.sales_rep_id,
    COALESCE(cc.job_title, 'Manager') AS job_title,
    COALESCE(cc.email, '') AS email,
    COALESCE(cc.phone, '') AS phone,
    NULL AS postal_code,
    '' AS password_hash,
    c.status,
    'Corporate' AS entity_type,
    c.industry AS segment,
    0.00 AS lifetime_value,
    c.current_balance AS balance_due,
    c.credit_limit,
    c.payment_terms,
    c.created_at,
    c.updated_at
FROM `clients` c
LEFT JOIN `client_contacts` cc ON cc.client_id = c.id AND cc.is_primary = 1;

-- ==============================================================================
-- RESTORE FOREIGN KEY CHECKS
-- ==============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- END OF UNIFIED MASTER DATABASE SCHEMA
-- ==============================================================================