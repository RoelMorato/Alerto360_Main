
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_progress','resolved','accepted','done','completed','accept and complete') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responder_type` varchar(20) DEFAULT NULL,
  `accepted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `incidents` (`id`, `user_id`, `type`, `description`, `latitude`, `longitude`, `image_path`, `status`, `created_at`, `responder_type`, `accepted_by`) VALUES
(12, 3, 'Landslide', 'sakit akong kasing kasing', 6.67782124, 125.28568731, 'uploads/incident_6894397c3dc1d_pota.jpg', 'completed', '2025-08-07 05:28:28', 'MDDRMO', 9),
(13, 3, 'Accident', 'bangga', 6.67773599, 125.28500111, 'uploads/incident_6894463c51fa8_513393016_2086514065176390_8215323816011558095_n.jpg', 'resolved', '2025-08-07 06:22:52', 'MDDRMO', 9),
(14, 10, 'Fire', 'init', 6.67620152, 125.28397157, 'uploads/incident_68944a9410eb1_bad.jpg', 'completed', '2025-08-07 06:41:24', 'BFP', 7),
(15, 3, 'Fire', '', 6.67858848, 125.28448305, NULL, 'completed', '2025-08-09 04:48:49', 'BFP', NULL),
(16, 3, 'Fire', '', 6.67858848, 125.28448305, NULL, 'completed', '2025-08-09 04:50:35', 'BFP', NULL),
(17, 3, 'Fire', '', 6.67858848, 125.28448305, NULL, 'completed', '2025-08-09 04:52:15', 'BFP', NULL),
(18, 3, 'Accident', 'sakit akong kasing kasing', 6.67682900, 125.28692670, NULL, 'accepted', '2025-08-09 04:52:45', 'MDDRMO', 9),
(19, 3, 'Accident', '[Auto-detected incident type or description here]', 6.67682900, 125.28692670, 'uploads/captured_6896d5ba3bb74.jpg', 'accepted', '2025-08-09 04:59:38', 'MDDRMO', 9),
(20, 10, 'Crime', '[Auto-detected incident type or description here]', 6.67682900, 125.28692670, 'uploads/captured_6896d6a4d2971.jpg', 'pending', '2025-08-09 05:03:32', 'PNP', NULL);


CREATE TABLE `incident_images` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('citizen','responder','admin') NOT NULL DEFAULT 'citizen',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responder_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `responder_type`) VALUES
(1, 'Admin', 'admin@alerto360.local', '$2y$10$FMn0U1Zr3GPVeF.mz0vRk.2c6dM9AfYeaZjcij3mqGBbqYpVr2Eoe', 'admin', '2025-07-30 07:38:53', NULL),
(3, 'test', 'test@test', '$2y$10$8ev17yVNnVBXjb05we5N3ObkZYOvgzqXiEN2ltD3Jt/0DsgxaF68i', 'citizen', '2025-07-30 07:57:35', NULL),
(5, 'brent', 'brent@brent', '$2y$10$GQDFZxzNKPtV5r6Da56krOd2B62foHjNxO/Z8KWTS9fGP3qURQXUG', 'responder', '2025-07-30 08:37:33', 'PNP'),
(6, 'yawa', 'yawa@yawa', '$2y$10$lKIHSbwnBmHbsQ9z5tVncumMAuuuc.JkFRzG8cWgAQyLDzlXxgV2q', 'responder', '2025-07-30 08:37:59', 'MDDRMO'),
(7, 'gaylon', 'gaylon@gmail.com', '$2y$10$tfoQ155hsfW6kC7DBnoajulqCPgvDfbLATpDxr4JxCeIBJF46x4kG', 'responder', '2025-07-30 08:47:37', 'BFP'),
(8, 'Admin', 'admin@admin', '$2y$10$N3BsHgBn1TVqg8ROQdEEte4wIr3BwhC2t63TR9ruRAy/BVrYH1/Qq', 'admin', '2025-07-31 06:36:49', NULL),
(9, 'stephen', 'stephen@stephen', '$2y$10$ER08fPa8iXciNO08DtO19O96MXR8ZyDQ4YiNSTj3oF188g7IfEnkW', 'responder', '2025-07-31 06:53:39', 'MDDRMO'),
(10, 'test2', 'test2@test', '$2y$10$Lm9lmVyVP8PVBXmf0mtzjun30YJ3Ets/9kYzottIOD0.0AQ6ZqTUy', 'citizen', '2025-07-31 07:41:00', NULL);


ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `incident_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);


ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);


ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;


ALTER TABLE `incident_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;


ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);


ALTER TABLE `incident_images`
  ADD CONSTRAINT `incident_images_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`);


ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

