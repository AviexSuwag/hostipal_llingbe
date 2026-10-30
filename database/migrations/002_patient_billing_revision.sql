ALTER TABLE `patient`
    ADD COLUMN `patient_code` varchar(24) DEFAULT NULL AFTER `patient_id`,
    ADD COLUMN `senior_id_type` varchar(150) DEFAULT NULL AFTER `birth_date`,
    ADD COLUMN `senior_id_number` varchar(100) DEFAULT NULL AFTER `senior_id_type`,
    ADD UNIQUE KEY `uq_patient_patient_code` (`patient_code`);

UPDATE `patient`
SET `patient_code` = CONCAT('PH-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 12)))
WHERE `patient_code` IS NULL OR `patient_code` = '';

ALTER TABLE `room_type`
    ADD COLUMN `charge_interval` enum('once','hour','day') NOT NULL DEFAULT 'day'
    AFTER `rate_per_day`;

ALTER TABLE `room_assignment`
    ADD COLUMN `charge_interval_at_assignment` enum('once','hour','day') NOT NULL DEFAULT 'day'
    AFTER `daily_rate_at_assignment`;

ALTER TABLE `charge`
    ADD COLUMN `charge_interval` enum('once','hour','day') NOT NULL DEFAULT 'once'
    AFTER `actual_price`,
    ADD COLUMN `service_start_datetime` datetime DEFAULT NULL
    AFTER `service_start_date`,
    ADD COLUMN `service_end_datetime` datetime DEFAULT NULL
    AFTER `service_end_date`;

UPDATE `charge_item`
SET `is_stockable` = 0, `stock_quantity` = 0;

ALTER TABLE `charge_item`
    ADD COLUMN `tracks_patient_doses` tinyint(1) NOT NULL DEFAULT 0
    AFTER `stock_quantity`;

UPDATE `charge_item` ci
INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
SET ci.tracks_patient_doses = CASE
    WHEN cc.category_name = 'Drugs & Medicines' THEN 1
    ELSE 0
END;

INSERT INTO `charge_category` (`category_name`, `description`, `calculation_type`, `is_recurring`)
SELECT 'Emergency Room', 'Emergency department service and time-based charges', 'Per Item', 0
WHERE NOT EXISTS (
    SELECT 1 FROM `charge_category` WHERE `category_name` = 'Emergency Room'
);
