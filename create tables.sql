CREATE TABLE `timetables` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `timetable_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `user` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `for_date` date NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user` (`user`),
    CONSTRAINT `timetables` FOREIGN KEY (`user`) REFERENCES `users` (`username`) ON DELETE CASCADE,
    CONSTRAINT `timetables_chk_1` CHECK (json_valid(`timetable_data`))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

CREATE TABLE `users` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `password_cipher` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `slack_bot_token` varchar(70) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `setup_complete` tinyint(1) DEFAULT '0',
    `notification_for_days_in_advance` int DEFAULT '10',
    `receive_notifications_for` varchar(70) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ausfall, vertretung, raumänderung, sonstiges',
    `last_login` datetime DEFAULT NULL,
    `created` datetime DEFAULT CURRENT_TIMESTAMP,
    `dictionary` varchar(300) COLLATE utf8mb4_general_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci