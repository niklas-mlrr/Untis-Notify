CREATE TABLE `users` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(20) NOT NULL,
    `password` varchar(255) NOT NULL,
    `slack_bot_token` varchar(70) DEFAULT NULL,
    `setup_complete` tinyint(1) DEFAULT 0,
    `notification_for_days_in_advance` int(11) DEFAULT 14,
    `last_login` datetime DEFAULT NULL,
    `created` datetime DEFAULT current_timestamp(),
    `school_url` varchar(100) DEFAULT NULL,
    `dictionary` varchar(300) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `timetables` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `timetable_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`timetable_data`)),
    `user` varchar(20) NOT NULL,
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `for_date` date NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user` (`user`),
    CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;