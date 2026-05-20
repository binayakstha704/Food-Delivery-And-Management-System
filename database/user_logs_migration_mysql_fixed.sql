DROP TABLE IF EXISTS `user_logs`;

CREATE TABLE `user_logs` (
  `log_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'NULL for anonymous events',
  `event_type` VARCHAR(50) NOT NULL COMMENT 'login_success | login_failed | logout | access_denied',
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IPv4 or IPv6 address',
  `description` TEXT NOT NULL COMMENT 'Human-readable detail string',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_logs_user` (`user_id`),
  KEY `idx_user_logs_event_date` (`event_type`, `created_at`),
  CONSTRAINT `fk_user_logs_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
