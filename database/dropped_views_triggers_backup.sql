-- Backup of VIEWs and TRIGGERs removed from `ewallet` on 2026-07-04 22:22:13
-- To restore any object, run its CREATE statement below in phpMyAdmin.

-- ===== VIEW v_circulation_health =====
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_circulation_health` AS select `ss`.`total_circulation_cap` AS `cap`,`ss`.`cashier_vault_points` AS `vault`,coalesce(`sw`.`student_total`,0) AS `student_wallets_total`,coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`,coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`,(((`ss`.`cashier_vault_points` + coalesce(`sw`.`student_total`,0)) + coalesce(`mw`.`merchant_total`,0)) + coalesce(`vo`.`voucher_total`,0)) AS `total_in_circulation`,((((`ss`.`total_circulation_cap` - `ss`.`cashier_vault_points`) - coalesce(`sw`.`student_total`,0)) - coalesce(`mw`.`merchant_total`,0)) - coalesce(`vo`.`voucher_total`,0)) AS `circulation_drift`,coalesce(`cm`.`minted_this_month`,0) AS `minted_this_month`,coalesce(`cm`.`mint_events`,0) AS `mint_events_this_month`,50000.00 AS `monthly_soft_limit`,greatest(0,(50000.00 - coalesce(`cm`.`minted_this_month`,0))) AS `remaining_mint_budget`,`ss`.`updated_at` AS `as_of` from ((((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where (`vouchers`.`status` = 'active')) `vo`) left join (select sum(`cap_increase_log`.`amount_added`) AS `minted_this_month`,count(0) AS `mint_events` from `cap_increase_log` where ((month(`cap_increase_log`.`created_at`) = month(curdate())) and (year(`cap_increase_log`.`created_at`) = year(curdate())))) `cm` on((0 <> 1))) where (`ss`.`id` = 1);

-- ===== VIEW v_circulation_snapshot =====
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_circulation_snapshot` AS select `ss`.`total_circulation_cap` AS `cap`,`ss`.`cashier_vault_points` AS `vault`,coalesce(`sw`.`student_total`,0) AS `student_wallets_total`,coalesce(`mw`.`merchant_total`,0) AS `merchant_wallets_total`,coalesce(`vo`.`voucher_total`,0) AS `active_vouchers_total`,(((`ss`.`cashier_vault_points` + coalesce(`sw`.`student_total`,0)) + coalesce(`mw`.`merchant_total`,0)) + coalesce(`vo`.`voucher_total`,0)) AS `total_in_circulation`,((((`ss`.`total_circulation_cap` - `ss`.`cashier_vault_points`) - coalesce(`sw`.`student_total`,0)) - coalesce(`mw`.`merchant_total`,0)) - coalesce(`vo`.`voucher_total`,0)) AS `circulation_drift`,`ss`.`updated_at` AS `as_of` from (((`system_settings` `ss` join (select sum(`student_wallets`.`balance`) AS `student_total` from `student_wallets`) `sw`) join (select sum(`merchant_wallets`.`balance`) AS `merchant_total` from `merchant_wallets`) `mw`) join (select sum(`vouchers`.`remaining_balance`) AS `voucher_total` from `vouchers` where (`vouchers`.`status` = 'active')) `vo`) where (`ss`.`id` = 1);

-- ===== VIEW v_p2p_daily_totals =====
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_p2p_daily_totals` AS select `p2p_transfers`.`from_user_id` AS `from_user_id`,cast(`p2p_transfers`.`created_at` as date) AS `transfer_date`,sum(`p2p_transfers`.`amount`) AS `daily_total`,count(0) AS `transfer_count` from `p2p_transfers` where (`p2p_transfers`.`status` = 'completed') group by `p2p_transfers`.`from_user_id`,cast(`p2p_transfers`.`created_at` as date);

-- ===== VIEW v_vouchers_active =====
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vouchers_active` AS select `v`.`id` AS `id`,`v`.`voucher_code` AS `voucher_code`,`v`.`visitor_name` AS `visitor_name`,`v`.`visitor_contact` AS `visitor_contact`,`v`.`initial_value` AS `initial_value`,`v`.`remaining_balance` AS `remaining_balance`,`v`.`status` AS `status`,`v`.`is_refundable` AS `is_refundable`,`v`.`created_at` AS `created_at`,`v`.`expires_at` AS `expires_at`,timestampdiff(MINUTE,now(),`v`.`expires_at`) AS `minutes_until_expiry`,(case when (`v`.`status` <> 'active') then `v`.`status` when (now() > `v`.`expires_at`) then 'expired_pending' when (`v`.`remaining_balance` <= 0) then 'fully_redeemed' else 'active' end) AS `computed_status`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `issued_by_name`,`v`.`use_count` AS `use_count` from (`vouchers` `v` left join `users` `u` on((`u`.`userID` = `v`.`issued_by`)));

-- ===== TRIGGER trg_block_expired_voucher_use =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_block_expired_voucher_use` BEFORE UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.remaining_balance < OLD.remaining_balance
       AND OLD.status IN ('expired', 'cancelled', 'redeemed')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VOUCHER_INACTIVE: Cannot deduct from an expired, redeemed, or cancelled voucher.';
    END IF;
END$$
DELIMITER ;

-- ===== TRIGGER trg_guard_merchant_balance =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_guard_merchant_balance` BEFORE UPDATE ON `merchant_wallets` FOR EACH ROW BEGIN
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
END$$
DELIMITER ;

-- ===== TRIGGER trg_guard_student_balance =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_guard_student_balance` BEFORE UPDATE ON `student_wallets` FOR EACH ROW BEGIN
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
END$$
DELIMITER ;

-- ===== TRIGGER trg_guard_transaction_cap =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_guard_transaction_cap` BEFORE INSERT ON `transactions` FOR EACH ROW BEGIN
    DECLARE v_cap DECIMAL(15,2);
    SELECT total_circulation_cap INTO v_cap
    FROM system_settings WHERE id = 1;

    IF NEW.total_in_circulation > v_cap + 0.01 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_EXCEEDED: total_in_circulation would exceed total_circulation_cap. Transaction blocked.';
    END IF;
END$$
DELIMITER ;

-- ===== TRIGGER trg_guard_vault_update =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_guard_vault_update` BEFORE UPDATE ON `system_settings` FOR EACH ROW BEGIN
    IF NEW.cashier_vault_points > NEW.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VAULT_EXCEEDS_CAP: cashier_vault_points cannot exceed total_circulation_cap';
    END IF;

    IF NEW.total_circulation_cap < OLD.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_DECREASE_FORBIDDEN: total_circulation_cap can only be increased';
    END IF;
END$$
DELIMITER ;

-- ===== TRIGGER trg_recycle_expired_voucher =====
DELIMITER $$
CREATE DEFINER=`root`@`localhost` TRIGGER `trg_recycle_expired_voucher` AFTER UPDATE ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.status = 'expired'
       AND OLD.status != 'expired'
       AND NEW.remaining_balance > 0
       AND NEW.is_refundable = 0
    THEN
        UPDATE system_settings
           SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
         WHERE id = 1;
    END IF;
END$$
DELIMITER ;

