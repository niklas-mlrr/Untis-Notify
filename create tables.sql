CREATE TABLE `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(20) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `slack_bot_token` VARCHAR(70) DEFAULT NULL,
    `setup_complete` TINYINT(1) DEFAULT 0,
    `notification_for_days_in_advance` INT(11) DEFAULT 14,
    `last_login` DATETIME DEFAULT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `school_url` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`id`)
)

CREATE TABLE `timetables` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `timetable_data` JSON NOT NULL,
    `user` VARCHAR(20) NOT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `for_date` DATE NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user`) REFERENCES `users`(`username`) ON DELETE CASCADE
)