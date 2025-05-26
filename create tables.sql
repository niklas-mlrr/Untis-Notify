-- Users table
CREATE TABLE `users` (
    `id` int(10) UNSIGNED NOT NULL,
    `username` varchar(20) NOT NULL,
    `password_cipher` varchar(255) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `email_adress` varchar(70) DEFAULT NULL,
    `setup_complete` tinyint(1) DEFAULT 0,
    `notification_for_days_in_advance` int(11) DEFAULT 10,
    `receive_notifications_for` varchar(70) NOT NULL DEFAULT 'ausfall, vertretung, raumänderung, sonstiges',
    `last_login` datetime DEFAULT NULL,
    `created` datetime DEFAULT current_timestamp(),
    `dictionary` varchar(300) DEFAULT NULL,
    `school_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Timetables table
CREATE TABLE `timetables` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `timetable_data` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `user` VARCHAR(20) COLLATE utf8mb4_general_ci NOT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `for_date` DATE NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user` (`user`),
    CONSTRAINT `timetables` FOREIGN KEY (`user`) REFERENCES `users` (`username`) ON DELETE CASCADE,
    CONSTRAINT `timetables_chk_1` CHECK (json_valid(`timetable_data`))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Settings table
CREATE TABLE `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pw_logging_mode` TINYINT(1) DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initial settings data
INSERT INTO `settings` (`id`, `pw_logging_mode`) VALUES (1, 0);
