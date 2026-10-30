ALTER TABLE `service_fulfillment_item`
    ADD COLUMN `quantity_take_home` int(11) UNSIGNED NOT NULL DEFAULT 0
    AFTER `quantity_remaining`;
