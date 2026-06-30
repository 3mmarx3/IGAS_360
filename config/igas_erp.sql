-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jun 25, 2026 at 09:45 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `igas_erp`
--

-- --------------------------------------------------------

--
-- Table structure for table `client_activities`
--

CREATE TABLE `client_activities` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `activity_text` varchar(255) NOT NULL,
  `activity_time` datetime NOT NULL,
  `author` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `client_activities`
--

INSERT INTO `client_activities` (`id`, `client_id`, `activity_text`, `activity_time`, `author`) VALUES
(1, 1, 'Created new order ORD-7845 with total 1,380.00 SAR', '2026-06-24 18:41:46', 'System'),
(2, 1, 'Created new order ORD-7818 with total 13,167.50 SAR', '2026-06-24 18:43:57', 'System'),
(3, 1, 'Created new order ORD-7860 with total 13,167.50 SAR', '2026-06-24 23:07:20', 'System'),
(4, 1, 'Created new order ORD-7907 with total 8,280.00 SAR', '2026-06-24 23:11:23', 'System'),
(5, 1, 'Created new order ORD-7929 with total 11,442.50 SAR', '2026-06-24 23:16:21', 'System'),
(6, 1, 'Created new order ORD-7971 with total 6,267.50 SAR', '2026-06-24 23:20:41', 'System');

-- --------------------------------------------------------

--
-- Table structure for table `compliance_docs`
--

CREATE TABLE `compliance_docs` (
  `id` int NOT NULL,
  `entity_id` varchar(50) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','warning','critical','expired') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `compliance_docs`
--

INSERT INTO `compliance_docs` (`id`, `entity_id`, `entity_name`, `doc_type`, `expiry_date`, `status`) VALUES
(1, 'FLT-006', 'Volvo FH16', 'Vehicle Registration', '2026-06-28', 'critical'),
(2, 'DRV-102', 'Mohammed Saad', 'Driver License', '2026-06-27', 'critical');

-- --------------------------------------------------------

--
-- Table structure for table `dispatches`
--

CREATE TABLE `dispatches` (
  `id` int NOT NULL,
  `manifest_id` varchar(50) NOT NULL,
  `order_ref` varchar(50) NOT NULL,
  `vehicle_id` varchar(50) NOT NULL,
  `driver_id` varchar(50) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `dispatch_date` date NOT NULL,
  `eta_time` time NOT NULL,
  `instructions` text,
  `status` enum('dispatched','in_transit','delivered','cancelled') DEFAULT 'dispatched',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `distance` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `dispatches`
--

INSERT INTO `dispatches` (`id`, `manifest_id`, `order_ref`, `vehicle_id`, `driver_id`, `destination`, `dispatch_date`, `eta_time`, `instructions`, `status`, `created_at`, `distance`) VALUES
(2, 'DSP-0907', 'ORD-7971', 'FLT-006', 'DRV-003', 'عمار', '2026-07-01', '21:49:00', 'حي الياسمين', 'in_transit', '2026-06-25 18:44:37', 0);

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int NOT NULL,
  `driver_id` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `national_id` varchar(100) NOT NULL,
  `mobile_number` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `license_class` varchar(50) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `license_expiry` date NOT NULL,
  `medical_expiry` date DEFAULT NULL,
  `status` enum('active','on_leave','suspended') DEFAULT 'active',
  `assigned_vehicle` varchar(50) DEFAULT 'unassigned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `blood_type` varchar(10) DEFAULT 'O+',
  `emergency_contact` varchar(100) DEFAULT 'Not Specified',
  `rating` decimal(3,1) DEFAULT '5.0',
  `on_time_rate` decimal(5,2) DEFAULT '100.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `driver_id`, `full_name`, `national_id`, `mobile_number`, `email`, `license_class`, `license_number`, `license_expiry`, `medical_expiry`, `status`, `assigned_vehicle`, `created_at`, `blood_type`, `emergency_contact`, `rating`, `on_time_rate`) VALUES
(3, 'DRV-003', 'Omar Khaled', '1092837465', '+966501112222', 'omar@example.com', 'heavy_hazmat', 'LIC-889900', '2028-01-15', '2027-01-15', 'active', 'FLT-006', '2026-06-25 18:33:30', 'A+', '+966509998888', 4.9, 98.50),
(4, 'DRV-004', 'Fahad Saeed', '1083746592', '+966552223333', 'fahad@example.com', 'heavy', 'LIC-776655', '2027-11-20', '2027-05-10', 'active', 'FLT-007', '2026-06-25 18:33:30', 'O+', '+966559997777', 4.7, 95.00);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tickets`
--

CREATE TABLE `maintenance_tickets` (
  `id` int NOT NULL,
  `ticket_id` varchar(50) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `vehicle_id` varchar(50) NOT NULL,
  `odometer` int NOT NULL,
  `scheduled_date` date NOT NULL,
  `estimated_cost` decimal(15,2) DEFAULT '0.00',
  `workshop` varchar(255) NOT NULL,
  `instructions` text,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `maintenance_tickets`
--

INSERT INTO `maintenance_tickets` (`id`, `ticket_id`, `service_type`, `vehicle_id`, `odometer`, `scheduled_date`, `estimated_cost`, `workshop`, `instructions`, `status`, `created_at`) VALUES
(4, 'MNT-4025', 'repair', 'FLT-006', 67467, '2026-06-25', 200.00, 'ammar', 'ammar', 'scheduled', '2026-06-25 18:54:40'),
(5, 'MNT-4026', 'preventive', 'FLT-006', 46468, '2026-06-25', 46468.00, '46468', '46468', 'scheduled', '2026-06-25 19:09:01'),
(6, 'MNT-4027', 'inspection', 'FLT-006', 50000, '2026-06-25', 200.00, 'ASSIGNED WORKSHOP / MECHANIC', 'ASSIGNED WORKSHOP / MECHANIC', 'scheduled', '2026-06-25 19:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int NOT NULL,
  `reference_id` varchar(50) NOT NULL,
  `partner_type` enum('client','supplier','logistics') NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `cr_number` varchar(100) NOT NULL,
  `contract_ref` varchar(100) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `contact_first_name` varchar(100) NOT NULL,
  `contact_last_name` varchar(100) NOT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `entity_type` varchar(50) DEFAULT 'Corporate',
  `segment` varchar(100) DEFAULT 'General',
  `lifetime_value` decimal(15,2) DEFAULT '0.00',
  `balance_due` decimal(15,2) DEFAULT '0.00',
  `credit_limit` decimal(15,2) DEFAULT '0.00',
  `payment_terms` varchar(50) DEFAULT 'cod',
  `last_order_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rating` decimal(3,1) DEFAULT '0.0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`id`, `reference_id`, `partner_type`, `company_name`, `cr_number`, `contract_ref`, `country`, `city`, `address`, `tax_id`, `contact_first_name`, `contact_last_name`, `job_title`, `email`, `phone`, `postal_code`, `password_hash`, `status`, `entity_type`, `segment`, `lifetime_value`, `balance_due`, `credit_limit`, `payment_terms`, `last_order_date`, `created_at`, `updated_at`, `rating`) VALUES
(1, 'ACC-2984', 'client', 'ammarz', '73837387389', NULL, 'Saudi Arabia', 'sohag', 'sohag', '7383738738937982', 'Ammar', 'Ahmed', 'JOB ROLE / TITLE', 'Ammar11@gmail.com', '+20107738738', '82511', '$2y$10$T9OyTxvoTPrMcpuuD./5xeuYKVRv8KuDlj8S1GwG051ZdNnAxL7kG', 'approved', 'SME', 'Medical', 53705.00, 53705.00, 200.00, 'net30', '2026-06-24', '2026-06-24 13:24:35', '2026-06-25 21:30:44', 0.0),
(4, 'SUP-5025', 'supplier', 'ammarz', '7489f', NULL, 'Saudi Arabia', 'Default City', 'sohag', '738373873896737982', 'Ammar', 'Ahmed', 'JOB ROLE / TITLE', 'Ammar161@gmail.com', '+2010754465', NULL, '$2y$10$OOxEpH2UJKdCdWDWhV8F..BK6iLufHMWgeudTkauxsmor/A9FY8Pm', 'approved', 'Corporate', 'Chemicals', 0.00, 0.00, 0.00, 'Net 30 Days', NULL, '2026-06-24 14:36:09', '2026-06-25 21:30:43', 0.0),
(5, 'SUP-5001', 'supplier', 'Gulf Industrial Gases', 'CR-S5001-NEW', NULL, 'Saudi Arabia', 'Dammam', 'Industrial Area', NULL, '', '', NULL, 'gulf_gases@example.com', '+966 50 123 4567', NULL, '123456', '', 'Corporate', 'Raw Materials', 0.00, 0.00, 0.00, 'cod', NULL, '2026-06-24 14:48:47', '2026-06-24 14:48:47', 4.9);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `client_id` int NOT NULL,
  `supplier_reference` varchar(50) DEFAULT NULL,
  `specs` varchar(255) NOT NULL,
  `order_date` date NOT NULL,
  `delivery_address` text,
  `delivery_date` date DEFAULT NULL,
  `delivery_time` time DEFAULT NULL,
  `delivery_priority` varchar(50) DEFAULT 'standard',
  `payment_terms` varchar(50) DEFAULT 'cod',
  `status` enum('draft','processing','in_transit','delivered','cancelled') DEFAULT 'draft',
  `total_value` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `order_number`, `client_id`, `supplier_reference`, `specs`, `order_date`, `delivery_address`, `delivery_date`, `delivery_time`, `delivery_priority`, `payment_terms`, `status`, `total_value`) VALUES
(1, 'ORD-7845', 1, NULL, 'Supplier: Gulf Industrial Gases | Items: 1', '2026-06-24', NULL, '2026-06-26', NULL, 'standard', 'cod', 'processing', 1380.00),
(2, 'ORD-7818', 1, NULL, 'Supplier: Gulf Industrial Gases | Items: 6', '2026-06-24', NULL, '2026-06-27', NULL, 'standard', 'cod', 'in_transit', 13167.50),
(3, 'ORD-7860', 1, NULL, 'Supplier: Gulf Industrial Gases | Items: 6', '2026-06-24', NULL, '2026-06-23', NULL, 'standard', 'cod', 'delivered', 13167.50),
(4, 'ORD-7907', 1, 'SUP-5001', 'Supplier: Gulf Industrial Gases | Items: 3', '2026-06-24', NULL, '2026-06-28', NULL, 'standard', 'cod', 'processing', 8280.00),
(5, 'ORD-7929', 1, 'SUP-5001', 'Supplier: Gulf Industrial Gases | Items: 5', '2026-06-24', NULL, '2026-06-29', NULL, 'standard', 'cod', 'in_transit', 11442.50),
(6, 'ORD-7971', 1, 'SUP-5001', 'Supplier: Gulf Industrial Gases | Items: 4', '2026-06-24', 'عمار احمد', '2026-06-29', '23:20:00', 'standard', 'net30', 'draft', 6267.50);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int NOT NULL,
  `purchase_order_id` int NOT NULL,
  `supplier_reference` varchar(50) NOT NULL,
  `product_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(100) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `supplier_reference`, `product_id`, `item_name`, `item_code`, `qty`, `unit`, `unit_price`, `discount_pct`, `line_total`, `created_at`) VALUES
(1, 4, 'SUP-5001', 16, 'Liquid Carbon Dioxide', 'RM-CO2-LIQ', 1.00, 'Ton', 1500.00, 0.00, 1500.00, '2026-06-24 20:11:23'),
(2, 4, 'SUP-5001', 19, 'Helium Gas (99.999%)', 'CYL-HE-50L', 1.00, 'Cylinder', 1200.00, 0.00, 1200.00, '2026-06-24 20:11:23'),
(3, 4, 'SUP-5001', 23, 'High Pressure Brass Valve', 'SP-VALVE-01', 100.00, 'Piece', 45.00, 0.00, 4500.00, '2026-06-24 20:11:23'),
(4, 5, 'SUP-5001', 17, 'Argon Gas Cylinder (50L)', 'CYL-AR-50L', 1.00, 'Cylinder', 450.00, 0.00, 450.00, '2026-06-24 20:16:21'),
(5, 5, 'SUP-5001', 18, 'Acetylene Gas Cylinder (40L)', 'CYL-AC-40L', 1.00, 'Cylinder', 600.00, 0.00, 600.00, '2026-06-24 20:16:21'),
(6, 5, 'SUP-5001', 19, 'Helium Gas (99.999%)', 'CYL-HE-50L', 1.00, 'Cylinder', 1200.00, 0.00, 1200.00, '2026-06-24 20:16:21'),
(7, 5, 'SUP-5001', 22, 'Anhydrous Ammonia', 'RM-NH3-LIQ', 1.00, 'Ton', 3200.00, 0.00, 3200.00, '2026-06-24 20:16:21'),
(8, 5, 'SUP-5001', 23, 'High Pressure Brass Valve', 'SP-VALVE-01', 100.00, 'Piece', 45.00, 0.00, 4500.00, '2026-06-24 20:16:21'),
(9, 6, 'SUP-5001', 17, 'Argon Gas Cylinder (50L)', 'CYL-AR-50L', 1.00, 'Cylinder', 450.00, 0.00, 450.00, '2026-06-24 20:20:41'),
(10, 6, 'SUP-5001', 18, 'Acetylene Gas Cylinder (40L)', 'CYL-AC-40L', 1.00, 'Cylinder', 600.00, 0.00, 600.00, '2026-06-24 20:20:41'),
(11, 6, 'SUP-5001', 19, 'Helium Gas (99.999%)', 'CYL-HE-50L', 1.00, 'Cylinder', 1200.00, 0.00, 1200.00, '2026-06-24 20:20:41'),
(12, 6, 'SUP-5001', 22, 'Anhydrous Ammonia', 'RM-NH3-LIQ', 1.00, 'Ton', 3200.00, 0.00, 3200.00, '2026-06-24 20:20:41');

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `client_id` int NOT NULL,
  `specs` varchar(255) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('draft','sent','accepted','declined','expired') DEFAULT 'draft',
  `subtotal` decimal(15,2) DEFAULT '0.00',
  `vat_amount` decimal(15,2) DEFAULT '0.00',
  `total_value` decimal(15,2) DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_number`, `client_id`, `specs`, `issue_date`, `expiry_date`, `status`, `subtotal`, `vat_amount`, `total_value`, `notes`, `created_at`) VALUES
(1, 'QT-2401', 1, 'Medical Gases Supply', '2026-06-26', '2026-07-26', 'sent', 11000.00, 1650.00, 12650.00, 'Delivery: I-GAS Fleet Dispatch | Address: sohag | Terms: Net 30 Days', '2026-06-25 21:32:22');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int NOT NULL,
  `quotation_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(50) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `product_name`, `qty`, `unit`, `unit_price`, `line_total`) VALUES
(1, 1, 'LIQ. O₂ (Liquid Oxygen)', 2.00, 'Tons', 2500.00, 5000.00),
(2, 1, 'Helium Gas (99.999%)', 5.00, 'Cylinders', 1200.00, 6000.00);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` int NOT NULL,
  `supplier_reference` varchar(50) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` decimal(15,2) DEFAULT '1.00',
  `unit` varchar(100) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'SAR',
  `lead_time_days` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `supplier_reference`, `item_code`, `item_name`, `category`, `quantity`, `unit`, `unit_price`, `currency`, `lead_time_days`, `created_at`) VALUES
(3, 'SUP-5025', '234', 'بنزين', 'Chemicals', 1.00, 'Ton', 100.00, 'SAR', 7, '2026-06-24 14:43:16'),
(14, 'SUP-5001', 'RM-O2-LIQ', 'Liquid Oxygen (99.5%)', 'Raw Materials', 1.00, 'Ton', 2500.00, 'SAR', 2, '2026-06-24 14:48:47'),
(15, 'SUP-5001', 'RM-N2-LIQ', 'Liquid Nitrogen (99.999%)', 'Raw Materials', 1.00, 'Ton', 1800.00, 'SAR', 2, '2026-06-24 14:48:47'),
(16, 'SUP-5001', 'RM-CO2-LIQ', 'Liquid Carbon Dioxide', 'Raw Materials', 1.00, 'Ton', 1500.00, 'SAR', 3, '2026-06-24 14:48:47'),
(17, 'SUP-5001', 'CYL-AR-50L', 'Argon Gas Cylinder (50L)', 'Cylinders', 1.00, 'Cylinder', 450.00, 'SAR', 5, '2026-06-24 14:48:47'),
(18, 'SUP-5001', 'CYL-AC-40L', 'Acetylene Gas Cylinder (40L)', 'Cylinders', 1.00, 'Cylinder', 600.00, 'SAR', 4, '2026-06-24 14:48:47'),
(19, 'SUP-5001', 'CYL-HE-50L', 'Helium Gas (99.999%)', 'Cylinders', 1.00, 'Cylinder', 1200.00, 'SAR', 7, '2026-06-24 14:48:47'),
(20, 'SUP-5001', 'MIX-WELD-01', 'Welding Mixture (80% Ar, 20% CO2)', 'Other', 1.00, 'Cylinder', 550.00, 'SAR', 4, '2026-06-24 14:48:47'),
(21, 'SUP-5001', 'CYL-N2O-30L', 'Medical Nitrous Oxide (30L)', 'Cylinders', 1.00, 'Cylinder', 850.00, 'SAR', 5, '2026-06-24 14:48:47'),
(22, 'SUP-5001', 'RM-NH3-LIQ', 'Anhydrous Ammonia', 'Chemicals', 1.00, 'Ton', 3200.00, 'SAR', 10, '2026-06-24 14:48:47'),
(23, 'SUP-5001', 'SP-VALVE-01', 'High Pressure Brass Valve', 'Spare Parts', 100.00, 'Piece', 45.00, 'SAR', 14, '2026-06-24 14:48:47');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `fleet_id` varchar(50) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `make_model` varchar(100) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `vin` varchar(100) NOT NULL,
  `manufacturing_year` int DEFAULT NULL,
  `load_capacity` decimal(10,2) DEFAULT '0.00',
  `cylinder_capacity` int DEFAULT '0',
  `fuel_type` varchar(50) DEFAULT 'diesel',
  `driver_id` varchar(50) DEFAULT 'unassigned',
  `status` varchar(50) DEFAULT 'available',
  `registration_expiry` date DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `fleet_id`, `plate_number`, `make_model`, `vehicle_type`, `vin`, `manufacturing_year`, `load_capacity`, `cylinder_capacity`, `fuel_type`, `driver_id`, `status`, `registration_expiry`, `insurance_expiry`, `created_at`) VALUES
(6, 'FLT-006', 'KSA 1122', 'Volvo FH16', 'cryo', 'VIN100200300A', 2024, 25.00, 0, 'diesel', 'DRV-003', 'maintenance', '2027-03-01', '2027-02-15', '2026-06-25 18:33:30'),
(7, 'FLT-007', 'KSA 3344', 'Isuzu F-Series', 'flatbed', 'VIN400500600B', 2023, 15.00, 250, 'diesel', 'DRV-004', 'in_transit', '2026-12-10', '2026-11-25', '2026-06-25 18:33:30');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_logs`
--

CREATE TABLE `vehicle_logs` (
  `id` int NOT NULL,
  `fleet_id` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `odometer` int NOT NULL,
  `fuel_liters` decimal(10,2) DEFAULT '0.00',
  `event_cost` decimal(15,2) DEFAULT '0.00',
  `logged_by` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vehicle_logs`
--

INSERT INTO `vehicle_logs` (`id`, `fleet_id`, `event_type`, `event_date`, `event_time`, `odometer`, `fuel_liters`, `event_cost`, `logged_by`, `description`, `created_at`) VALUES
(9, 'FLT-006', 'maintenance', '2026-06-25', '19:09:01', 46468, 0.00, 46468.00, 'System', 'Service ticket MNT-4026 (Preventive Maintenance (PM)) scheduled at 46468. Notes: 46468', '2026-06-25 19:09:01'),
(10, 'FLT-006', 'maintenance', '2026-06-25', '19:35:52', 50000, 0.00, 200.00, 'System', 'Service ticket MNT-4027 (Safety / DOT Inspection) scheduled at ASSIGNED WORKSHOP / MECHANIC. Notes: ASSIGNED WORKSHOP / MECHANIC', '2026-06-25 19:35:52'),
(11, 'FLT-006', 'arrival', '2026-06-25', '19:37:00', 200000, 200000.00, 200000.00, 'LOGGED BY / AUTHORITY SIGN-OFF', 'LOG DESCRIPTION & DYNAMIC DETAILS', '2026-06-25 19:37:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client_activities`
--
ALTER TABLE `client_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `compliance_docs`
--
ALTER TABLE `compliance_docs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispatches`
--
ALTER TABLE `dispatches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `manifest_id` (`manifest_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `driver_id` (`driver_id`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- Indexes for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD UNIQUE KEY `cr_number` (`cr_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `supplier_reference` (`supplier_reference`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `supplier_reference` (`supplier_reference`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_reference` (`supplier_reference`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fleet_id` (`fleet_id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD UNIQUE KEY `vin` (`vin`);

--
-- Indexes for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fleet_id` (`fleet_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client_activities`
--
ALTER TABLE `client_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `compliance_docs`
--
ALTER TABLE `compliance_docs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dispatches`
--
ALTER TABLE `dispatches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_activities`
--
ALTER TABLE `client_activities`
  ADD CONSTRAINT `fk_act_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD CONSTRAINT `maintenance_tickets_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`fleet_id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_reference`) REFERENCES `partners` (`reference_id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_purchase_order_items_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_order_items_supplier` FOREIGN KEY (`supplier_reference`) REFERENCES `partners` (`reference_id`) ON DELETE CASCADE;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quote_client` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `fk_quote_item` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `fk_supplier_products` FOREIGN KEY (`supplier_reference`) REFERENCES `partners` (`reference_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  ADD CONSTRAINT `vehicle_logs_ibfk_1` FOREIGN KEY (`fleet_id`) REFERENCES `vehicles` (`fleet_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
