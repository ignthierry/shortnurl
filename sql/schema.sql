CREATE DATABASE IF NOT EXISTS `shortnurl` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shortnurl`;

CREATE TABLE IF NOT EXISTS `urls` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_url` TEXT NOT NULL,
    `short_code` VARCHAR(10) NOT NULL,
    `click_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
