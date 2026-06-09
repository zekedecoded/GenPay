-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2026 at 08:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ewallet`
--

-- --------------------------------------------------------

--
-- Table structure for table `cap_increase_log`
--

CREATE TABLE `cap_increase_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `super_admin_id` int(10) UNSIGNED NOT NULL COMMENT 'FK -> users.id -- must be super-admin role',
  `old_cap` decimal(15,2) NOT NULL,
  `new_cap` decimal(15,2) NOT NULL,
  `amount_added` decimal(15,2) NOT NULL,
  `reason` text NOT NULL COMMENT 'Mandatory justification for audit compliance',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cap_increase_log`
--

INSERT INTO `cap_increase_log` (`id`, `super_admin_id`, `old_cap`, `new_cap`, `amount_added`, `reason`, `created_at`) VALUES
(1, 7, 0.00, 200000.00, 200000.00, 'Initial system capitalization. Starting circulation cap set to 200,000.00 for S.Y. 2025-2026.', '2026-04-29 10:52:26');

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `courseID` int(11) NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `course_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`courseID`, `course_code`, `course_name`) VALUES
(1, 'BSA', 'Bachelor of Science in Accountancy'),
(2, 'BSMA', 'Bachelor of Science in Management Accounting'),
(3, 'BSAIS', 'Bachelor of Science in Accounting Information Systems'),
(4, 'BSIA', 'Bachelor of Science in Internal Auditing'),
(5, 'BSBA-FM', 'BSBA major in Financial Management'),
(6, 'BSBA-MM', 'BSBA major in Marketing Management'),
(7, 'BSBA-HRM', 'BSBA major in Human Resource Management'),
(8, 'BSBA-OM', 'BSBA major in Operations Management'),
(9, 'BS-ENTREP', 'Bachelor of Science in Entrepreneurship'),
(10, 'BSOA', 'Bachelor of Science in Office Administration'),
(11, 'BSIT', 'Bachelor of Science in Information Technology'),
(12, 'BEED', 'Bachelor of Elementary Education'),
(13, 'BSED-ENG', 'Bachelor of Secondary Education major in English'),
(14, 'BSED-MATH', 'Bachelor of Secondary Education major in Mathematics'),
(15, 'BSED-SCI', 'Bachelor of Secondary Education major in Science'),
(16, 'BPED', 'Bachelor of Physical Education'),
(17, 'BSHM', 'Bachelor of Science in Hospitality Management');

-- --------------------------------------------------------

--
-- Table structure for table `encashment_requests`
--

CREATE TABLE `encashment_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `merchant_wallet_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` varchar(80) NOT NULL DEFAULT 'Cashier Release',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reference_no` varchar(40) DEFAULT NULL,
  `released_by` int(10) UNSIGNED DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encashment_requests`
--

INSERT INTO `encashment_requests` (`id`, `user_id`, `merchant_wallet_id`, `amount`, `method`, `status`, `reference_no`, `released_by`, `released_at`, `rejected_by`, `rejected_at`, `created_at`) VALUES
(1, 8, 1, 4250.00, 'Cashier Release', 'released', 'TXN-20260513-63526', 7, '2026-05-13 16:08:11', NULL, NULL, '2026-05-13 16:07:59');

-- --------------------------------------------------------

--
-- Table structure for table `merchant`
--

CREATE TABLE `merchant` (
  `merchantID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `stall_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchant`
--

INSERT INTO `merchant` (`merchantID`, `userID`, `stall_name`) VALUES
(1, 8, 'Green Hell'),
(2, 9, 'Thornton Stall');

-- --------------------------------------------------------

--
-- Table structure for table `merchant_wallets`
--

CREATE TABLE `merchant_wallets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'FK -> users.id (merchant role)',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Collected points pending settlement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchant_wallets`
--

INSERT INTO `merchant_wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 8, 0.00, '2026-05-13 00:00:00', '2026-05-13 16:08:11'),
(2, 9, 0.00, '2026-05-13 00:00:00', '2026-05-13 00:00:00');

--
-- Triggers `merchant_wallets`
--
DELIMITER $$
CREATE TRIGGER `trg_guard_merchant_balance` BEFORE UPDATE ON `merchant_wallets` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `qr_tokens`
--

CREATE TABLE `qr_tokens` (
  `qrID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `qr_data` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `roleID` int(11) NOT NULL,
  `role_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`roleID`, `role_name`) VALUES
(1, 'student'),
(2, 'merchant'),
(3, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `student_info`
--

CREATE TABLE `student_info` (
  `stud_infoID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `studentID` varchar(255) NOT NULL,
  `yr_lvl` varchar(11) NOT NULL,
  `courseID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_wallets`
--

CREATE TABLE `student_wallets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'FK -> users.id (student role)',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Current spendable balance in PHP points',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_wallets`
--

INSERT INTO `student_wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 2, 250.00, '2026-05-13 00:00:00', '2026-05-13 16:03:02'),
(2, 1, 2500.00, '2026-05-13 00:00:00', '2026-05-13 17:44:54');

--
-- Triggers `student_wallets`
--
DELIMITER $$
CREATE TRIGGER `trg_guard_student_balance` BEFORE UPDATE ON `student_wallets` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `total_circulation_cap` decimal(15,2) NOT NULL DEFAULT 200000.00 COMMENT 'Total money supply cap -- super-admin only',
  `cashier_vault_points` decimal(15,2) NOT NULL DEFAULT 200000.00 COMMENT 'Unsold points sitting in the cashiers vault',
  `last_cap_increased_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK -> users.id of the super-admin who last raised the cap',
  `last_cap_increased_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `total_circulation_cap`, `cashier_vault_points`, `last_cap_increased_by`, `last_cap_increased_at`, `updated_at`) VALUES
(1, 200000.00, 195850.00, 7, '2026-04-29 10:52:26', '2026-06-08 19:16:19');

--
-- Triggers `system_settings`
--
DELIMITER $$
CREATE TRIGGER `trg_guard_vault_update` BEFORE UPDATE ON `system_settings` FOR EACH ROW BEGIN
    IF NEW.cashier_vault_points > NEW.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VAULT_EXCEEDS_CAP: cashier_vault_points cannot exceed total_circulation_cap';
    END IF;

    IF NEW.total_circulation_cap < OLD.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_DECREASE_FORBIDDEN: total_circulation_cap can only be increased';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `topup`
--

CREATE TABLE `topup` (
  `topupID` int(11) NOT NULL,
  `adminID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `date_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topup_requests`
--

CREATE TABLE `topup_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `student_wallet_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(80) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `reference_no` varchar(40) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `topup_requests`
--

INSERT INTO `topup_requests` (`id`, `user_id`, `student_wallet_id`, `amount`, `payment_method`, `status`, `reference_no`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `created_at`) VALUES
(1, 2, 1, 1000.00, 'Cash at Cashier', 'approved', 'TXN-20260513-04199', 7, '2026-05-13 16:01:50', NULL, NULL, '2026-05-13 16:01:32'),
(2, 1, 2, 2000.00, 'Cash at Cashier', 'approved', 'TXN-20260513-02871', 7, '2026-05-13 16:04:48', NULL, NULL, '2026-05-13 16:02:38'),
(3, 1, 2, 2000.00, 'GCash', 'approved', 'TXN-20260513-58155', 7, '2026-05-13 16:05:16', NULL, NULL, '2026-05-13 16:04:22'),
(4, 1, 2, 2000.00, 'Maya', 'approved', 'TXN-20260513-21460', 7, '2026-05-13 17:44:54', NULL, NULL, '2026-05-13 16:04:39');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `transactionID` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `merchantID` int(11) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `date_time` datetime NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_no` varchar(30) NOT NULL,
  `transaction_type` enum('cash_in','payment','voucher_payment','merchant_settle','voucher_create','voucher_expire','cap_increase') NOT NULL,
  `initiated_by` int(10) UNSIGNED NOT NULL COMMENT 'FK -> users.id -- who triggered this transaction',
  `student_wallet_id` int(10) UNSIGNED DEFAULT NULL,
  `merchant_wallet_id` int(10) UNSIGNED DEFAULT NULL,
  `voucher_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `vault_before` decimal(15,2) NOT NULL COMMENT 'Vault snapshot before',
  `vault_after` decimal(15,2) NOT NULL COMMENT 'Vault snapshot after',
  `total_in_circulation` decimal(15,2) NOT NULL COMMENT 'vault_after + all wallet balances + all active voucher balances',
  `status` enum('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `reference_no`, `transaction_type`, `initiated_by`, `student_wallet_id`, `merchant_wallet_id`, `voucher_id`, `amount`, `vault_before`, `vault_after`, `total_in_circulation`, `status`, `notes`, `created_at`) VALUES
(1, 'TXN-20260513-04199', 'cash_in', 7, 1, NULL, NULL, 1000.00, 200000.00, 199000.00, 200000.00, 'completed', NULL, '2026-05-13 16:01:50'),
(2, 'TXN-20260513-86618', 'payment', 2, 1, 1, NULL, 750.00, 199000.00, 199000.00, 200000.00, 'completed', NULL, '2026-05-13 16:03:02'),
(3, 'TXN-20260513-02871', 'cash_in', 7, 2, NULL, NULL, 2000.00, 199000.00, 197000.00, 200000.00, 'completed', NULL, '2026-05-13 16:04:48'),
(4, 'TXN-20260513-58155', 'cash_in', 7, 2, NULL, NULL, 2000.00, 197000.00, 195000.00, 200000.00, 'completed', NULL, '2026-05-13 16:05:16'),
(5, 'TXN-20260513-41051', 'payment', 1, 2, 1, NULL, 3500.00, 195000.00, 195000.00, 200000.00, 'completed', NULL, '2026-05-13 16:06:58'),
(6, 'TXN-20260513-63526', 'merchant_settle', 7, NULL, 1, NULL, 4250.00, 195000.00, 199250.00, 200000.00, 'completed', NULL, '2026-05-13 16:08:11'),
(7, 'TXN-20260513-21460', 'cash_in', 7, 2, NULL, NULL, 2000.00, 199250.00, 197250.00, 200000.00, 'completed', NULL, '2026-05-13 17:44:54'),
(8, 'VOU-20260514-00001', 'voucher_create', 7, NULL, NULL, 1, 500.00, 197250.00, 196750.00, 200000.00, 'completed', 'Voucher VCH-9F948010 issued to Ezekiel Clarence · exp 2026-05-15 03:38:25', '2026-05-14 03:38:25'),
(9, 'VOU-20260608-00002', 'voucher_create', 7, NULL, NULL, 2, 900.00, 196750.00, 195850.00, 200000.00, 'completed', 'Voucher VCH-EC2381D1 issued to Paolo Varon - exp 2026-06-09 19:16:19', '2026-06-08 19:16:19');

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `trg_guard_transaction_cap` BEFORE INSERT ON `transactions` FOR EACH ROW BEGIN
    DECLARE v_cap DECIMAL(15,2);
    SELECT total_circulation_cap INTO v_cap
    FROM system_settings WHERE id = 1;

    IF NEW.total_in_circulation > v_cap + 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_EXCEEDED: total_in_circulation would exceed total_circulation_cap. Transaction blocked.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) NOT NULL DEFAULT '',
  `suffix` varchar(20) NOT NULL DEFAULT '',
  `contact_number` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `roleID` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mint_pin` varchar(255) DEFAULT NULL COMMENT 'bcrypt hash of the super-admin mint PIN -- required above monthly limit',
  `profile_img` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `last_name`, `first_name`, `middle_name`, `suffix`, `contact_number`, `email`, `roleID`, `password`, `mint_pin`, `profile_img`, `created_at`) VALUES
(1, 'Banez', 'Michael Keith', 'Garciua', '', 9171234567, 'michael@email.com', 1, '$2y$10$3DbXBT4q2/EuPLc7lWBvO.aJsGq9VULf1jkV51Y5naIJ4vQXVLhj2', NULL, '', '2026-04-29 13:46:44'),
(2, 'Clarence', 'Zeke', 'Dela', '', 9179876543, 'otto.cruz@email.com', 1, 'pass123', NULL, '', '2026-04-29 13:46:44'),
(5, 'Ramos', 'Maria', 'Bautista', '', 9175556789, 'maria.ramos@email.com', 1, 'pass123', NULL, 'f', '2026-04-29 13:46:44'),
(6, 'Garcia', 'Jose', 'Mendoza', '', 9176667890, 'jose.garcia@email.com', 1, 'pass123', NULL, 'f', '2026-04-29 13:46:44'),
(7, 'Reyes', 'Ana', 'Lopez', '', 9170001111, 'ana.reyes@email.com', 3, 'adminpass', NULL, 'f', '2026-04-29 13:46:44'),
(8, 'Villanueva', 'Carlos', 'Aquino', '', 9171230000, 'carlos.villanueva@email.com', 2, 'merchantpass', NULL, 'f', '2026-04-29 13:46:44'),
(9, 'Fernandez', 'Laura', 'Torres', '', 9171230001, 'laura.fernandez@email.com', 2, 'merchantpass', NULL, 'f', '2026-04-29 13:46:44');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(10) UNSIGNED NOT NULL,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `voucher_code` varchar(64) NOT NULL,
  `issued_by` int(10) UNSIGNED NOT NULL COMMENT 'FK -> users.id (cashier or admin who created it)',
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
  `use_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `qr_code_hash`, `voucher_code`, `issued_by`, `is_refundable`, `visitor_name`, `visitor_contact`, `initial_value`, `original_amount`, `remaining_balance`, `status`, `is_non_refundable`, `expires_at`, `expired_at`, `cancelled_at`, `last_used_at`, `use_count`, `created_at`, `updated_at`) VALUES
(1, 'b18e113a3760541abe1ca05777f4faebf1c7241d575a17a62a5aeb56de97a014', 'VCH-9F948010', 7, 1, 'Ezekiel Clarence', '09610912764', 500.00, 0.00, 500.00, 'active', 1, '2026-05-15 03:38:25', NULL, NULL, NULL, 0, '2026-05-14 03:38:25', '2026-05-14 03:38:25'),
(2, '28f2b60aea6ba408da195d7d7e6104a0274845171c20c70b4db1457682801e7f', 'VCH-EC2381D1', 7, 1, 'Paolo Varon', '', 900.00, 0.00, 900.00, 'active', 1, '2026-06-09 19:16:19', NULL, NULL, NULL, 0, '2026-06-08 19:16:19', '2026-06-08 19:16:19');

--
-- Triggers `vouchers`
--
DELIMITER $$
CREATE TRIGGER `trg_block_expired_voucher_use` BEFORE UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.remaining_balance < OLD.remaining_balance
       AND OLD.status IN ('expired', 'cancelled', 'redeemed')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VOUCHER_INACTIVE: Cannot deduct from an expired, redeemed, or cancelled voucher.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recycle_expired_voucher` AFTER UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.status = 'expired'
       AND OLD.status != 'expired'
       AND NEW.remaining_balance > 0
       AND NEW.is_refundable = 0
    THEN
        UPDATE system_settings
           SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
         WHERE id = 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_payment_log`
--

CREATE TABLE `voucher_payment_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `voucher_id` int(10) UNSIGNED NOT NULL,
  `merchant_wallet_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `scanned_by` int(10) UNSIGNED DEFAULT NULL,
  `transaction_ref` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_circulation_health`
-- (See below for the actual view)
--
CREATE TABLE `v_circulation_health` (
`cap` decimal(15,2)
,`vault` decimal(15,2)
,`student_wallets_total` decimal(37,2)
,`merchant_wallets_total` decimal(37,2)
,`active_vouchers_total` decimal(32,2)
,`total_in_circulation` decimal(40,2)
,`circulation_drift` decimal(40,2)
,`minted_this_month` decimal(37,2)
,`mint_events_this_month` bigint(21)
,`monthly_soft_limit` decimal(7,2)
,`remaining_mint_budget` decimal(38,2)
,`as_of` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_circulation_snapshot`
-- (See below for the actual view)
--
CREATE TABLE `v_circulation_snapshot` (
`cap` decimal(15,2)
,`vault` decimal(15,2)
,`student_wallets_total` decimal(37,2)
,`merchant_wallets_total` decimal(37,2)
,`active_vouchers_total` decimal(32,2)
,`total_in_circulation` decimal(40,2)
,`circulation_drift` decimal(40,2)
,`as_of` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vouchers_active`
-- (See below for the actual view)
--
CREATE TABLE `v_vouchers_active` (
`id` int(10) unsigned
,`voucher_code` varchar(64)
,`visitor_name` varchar(120)
,`visitor_contact` varchar(60)
,`initial_value` decimal(15,2)
,`remaining_balance` decimal(10,2)
,`status` enum('active','used','expired','void')
,`is_refundable` tinyint(1)
,`created_at` datetime
,`expires_at` datetime
,`minutes_until_expiry` bigint(21)
,`computed_status` varchar(15)
,`issued_by_name` varchar(356)
,`use_count` smallint(5) unsigned
);

-- --------------------------------------------------------

--
-- Table structure for table `wallet`
--

CREATE TABLE `wallet` (
  `wallet_id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `balance` int(11) NOT NULL,
  `last_updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `v_circulation_health`
--
DROP TABLE IF EXISTS `v_circulation_health`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_circulation_health`  AS SELECT `ss`.`total_circulation_cap` AS `cap`, `ss`.`cashier_vault_points` AS `vault`, coalesce(`sw`.`student_total`,0) AS `student_wallets_total`, coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`, coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`, `ss`.`cashier_vault_points`+ coalesce(`sw`.`student_total`,0) + coalesce(`mw`.`merchant_total`,0) + coalesce(`vo`.`voucher_total`,0) AS `total_in_circulation`, `ss`.`total_circulation_cap`- `ss`.`cashier_vault_points` - coalesce(`sw`.`student_total`,0) - coalesce(`mw`.`merchant_total`,0) - coalesce(`vo`.`voucher_total`,0) AS `circulation_drift`, coalesce(`cm`.`minted_this_month`,0) AS `minted_this_month`, coalesce(`cm`.`mint_events`,0) AS `mint_events_this_month`, 50000.00 AS `monthly_soft_limit`, greatest(0,50000.00 - coalesce(`cm`.`minted_this_month`,0)) AS `remaining_mint_budget`, `ss`.`updated_at` AS `as_of` FROM ((((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where `vouchers`.`status` = 'active') `vo`) left join (select sum(`cap_increase_log`.`amount_added`) AS `minted_this_month`,count(0) AS `mint_events` from `cap_increase_log` where month(`cap_increase_log`.`created_at`) = month(curdate()) and year(`cap_increase_log`.`created_at`) = year(curdate())) `cm` on(1)) WHERE `ss`.`id` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `v_circulation_snapshot`
--
DROP TABLE IF EXISTS `v_circulation_snapshot`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_circulation_snapshot`  AS SELECT `ss`.`total_circulation_cap` AS `cap`, `ss`.`cashier_vault_points` AS `vault`, coalesce(`sw`.`student_total`,0) AS `student_wallets_total`, coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`, coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`, `ss`.`cashier_vault_points`+ coalesce(`sw`.`student_total`,0) + coalesce(`mw`.`merchant_total`,0) + coalesce(`vo`.`voucher_total`,0) AS `total_in_circulation`, `ss`.`total_circulation_cap`- `ss`.`cashier_vault_points` - coalesce(`sw`.`student_total`,0) - coalesce(`mw`.`merchant_total`,0) - coalesce(`vo`.`voucher_total`,0) AS `circulation_drift`, `ss`.`updated_at` AS `as_of` FROM (((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where `vouchers`.`status` = 'active') `vo`) WHERE `ss`.`id` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `v_vouchers_active`
--
DROP TABLE IF EXISTS `v_vouchers_active`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vouchers_active`  AS SELECT `v`.`id` AS `id`, `v`.`voucher_code` AS `voucher_code`, `v`.`visitor_name` AS `visitor_name`, `v`.`visitor_contact` AS `visitor_contact`, `v`.`initial_value` AS `initial_value`, `v`.`remaining_balance` AS `remaining_balance`, `v`.`status` AS `status`, `v`.`is_refundable` AS `is_refundable`, `v`.`created_at` AS `created_at`, `v`.`expires_at` AS `expires_at`, timestampdiff(MINUTE,current_timestamp(),`v`.`expires_at`) AS `minutes_until_expiry`, CASE WHEN `v`.`status` <> 'active' THEN `v`.`status` WHEN current_timestamp() > `v`.`expires_at` THEN 'expired_pending' WHEN `v`.`remaining_balance` <= 0 THEN 'fully_redeemed' ELSE 'active' END AS `computed_status`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `issued_by_name`, `v`.`use_count` AS `use_count` FROM (`vouchers` `v` left join `users` `u` on(`u`.`userID` = `v`.`issued_by`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cap_increase_log`
--
ALTER TABLE `cap_increase_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`courseID`);

--
-- Indexes for table `encashment_requests`
--
ALTER TABLE `encashment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `idx_encash_user` (`user_id`),
  ADD KEY `idx_encash_status` (`status`);

--
-- Indexes for table `merchant`
--
ALTER TABLE `merchant`
  ADD PRIMARY KEY (`merchantID`),
  ADD KEY `merchant.usersFK` (`userID`);

--
-- Indexes for table `merchant_wallets`
--
ALTER TABLE `merchant_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD PRIMARY KEY (`qrID`),
  ADD KEY `qr.usersFK` (`userID`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`roleID`);

--
-- Indexes for table `student_info`
--
ALTER TABLE `student_info`
  ADD PRIMARY KEY (`stud_infoID`),
  ADD KEY `student.courseFK` (`courseID`),
  ADD KEY `student.usersFK` (`userID`);

--
-- Indexes for table `student_wallets`
--
ALTER TABLE `student_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `topup`
--
ALTER TABLE `topup`
  ADD PRIMARY KEY (`topupID`),
  ADD KEY `adminFK` (`adminID`),
  ADD KEY `toptup.usersFK` (`userID`);

--
-- Indexes for table `topup_requests`
--
ALTER TABLE `topup_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `idx_topup_user` (`user_id`),
  ADD KEY `idx_topup_status` (`status`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`transactionID`),
  ADD KEY `transaction.usersFK` (`wallet_id`),
  ADD KEY `transaction.merchantFK` (`merchantID`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `idx_txn_type` (`transaction_type`),
  ADD KEY `idx_txn_student` (`student_wallet_id`),
  ADD KEY `idx_txn_merchant` (`merchant_wallet_id`),
  ADD KEY `idx_txn_voucher` (`voucher_id`),
  ADD KEY `idx_txn_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD KEY `users_roleFK` (`roleID`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_code` (`voucher_code`),
  ADD UNIQUE KEY `qr_code_hash` (`qr_code_hash`),
  ADD KEY `idx_v_hash` (`qr_code_hash`),
  ADD KEY `idx_v_status` (`status`),
  ADD KEY `idx_v_expiry` (`expires_at`,`status`);

--
-- Indexes for table `voucher_payment_log`
--
ALTER TABLE `voucher_payment_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vpl_voucher` (`voucher_id`),
  ADD KEY `idx_vpl_merchant` (`merchant_wallet_id`);

--
-- Indexes for table `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`wallet_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cap_increase_log`
--
ALTER TABLE `cap_increase_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `encashment_requests`
--
ALTER TABLE `encashment_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `merchant`
--
ALTER TABLE `merchant`
  MODIFY `merchantID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `merchant_wallets`
--
ALTER TABLE `merchant_wallets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  MODIFY `qrID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `roleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_info`
--
ALTER TABLE `student_info`
  MODIFY `stud_infoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_wallets`
--
ALTER TABLE `student_wallets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `topup`
--
ALTER TABLE `topup`
  MODIFY `topupID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `topup_requests`
--
ALTER TABLE `topup_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `transactionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `voucher_payment_log`
--
ALTER TABLE `voucher_payment_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet`
--
ALTER TABLE `wallet`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `merchant`
--
ALTER TABLE `merchant`
  ADD CONSTRAINT `merchant.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD CONSTRAINT `qr.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `student_info`
--
ALTER TABLE `student_info`
  ADD CONSTRAINT `student.courseFK` FOREIGN KEY (`courseID`) REFERENCES `course` (`courseID`),
  ADD CONSTRAINT `student.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `topup`
--
ALTER TABLE `topup`
  ADD CONSTRAINT `adminFK` FOREIGN KEY (`adminID`) REFERENCES `users` (`userID`),
  ADD CONSTRAINT `toptup.usersFK` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`);

--
-- Constraints for table `transaction`
--
ALTER TABLE `transaction`
  ADD CONSTRAINT `transaction.merchantFK` FOREIGN KEY (`merchantID`) REFERENCES `merchant` (`merchantID`),
  ADD CONSTRAINT `transaction.usersFK` FOREIGN KEY (`wallet_id`) REFERENCES `users` (`userID`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_roleFK` FOREIGN KEY (`roleID`) REFERENCES `role` (`roleID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
