-- Migration: 001_esp32_devices.sql
-- Creates the esp32_devices table for tracking ESP32-CYD display devices

CREATE TABLE IF NOT EXISTS `esp32_devices` (
  `device_id` int(11) NOT NULL AUTO_INCREMENT,
  `device_name` varchar(100) NOT NULL,
  `mac_address` varchar(17) NOT NULL,
  `department_id` int(11) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `uk_mac_address` (`mac_address`),
  UNIQUE KEY `uk_api_key` (`api_key`),
  KEY `idx_esp32_department` (`department_id`),
  KEY `idx_esp32_active` (`is_active`),
  CONSTRAINT `fk_esp32_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
