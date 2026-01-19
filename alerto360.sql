
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";




CREATE TABLE `acceptance_log` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `responder_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `email_verification` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `email_verification` (`id`, `user_id`, `email`, `verification_code`, `created_at`, `expires_at`, `is_verified`, `verified_at`) VALUES
(11, 28, 'stephencurrygravino09@gmail.com', '734486', '2025-12-07 08:13:07', '2025-12-07 09:28:07', 1, '2025-12-07 08:13:33'),
(13, 30, 'bloopersnoob@gmail.com', '165610', '2025-12-09 21:34:00', '2025-12-09 22:49:00', 1, '2025-12-09 21:34:35'),
(15, 26, 'embamorats@gmail.com', '428494', '2025-12-16 09:55:38', '2025-12-16 11:10:38', 1, '2025-12-16 09:56:36'),
(16, 34, 'johnghlendealdo@gmail.com', '894465', '2025-12-16 09:58:51', '2025-12-16 11:13:51', 1, '2025-12-16 09:59:40');



CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_progress','resolved','accepted','done','completed','declined','assigned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responder_type` varchar(20) DEFAULT NULL,
  `accepted_by` int(11) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `declined_by` int(11) DEFAULT NULL,
  `decline_reason` text DEFAULT NULL,
  `declined_at` timestamp NULL DEFAULT NULL,
  `device_type` varchar(100) DEFAULT NULL COMMENT 'Device type (Mobile/Desktop/Tablet)',
  `device_info` varchar(255) DEFAULT NULL COMMENT 'Browser/App and OS info',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'User IP address',
  `submitted_at` datetime DEFAULT NULL COMMENT 'Exact submission timestamp',
  `assigned_to` int(11) DEFAULT NULL,
  `reassigned_by` int(11) DEFAULT NULL,
  `reassigned_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `incidents` (`id`, `user_id`, `type`, `description`, `latitude`, `longitude`, `image_path`, `status`, `created_at`, `responder_type`, `accepted_by`, `accepted_at`, `completed_at`, `declined_by`, `decline_reason`, `declined_at`, `device_type`, `device_info`, `ip_address`, `submitted_at`, `assigned_to`, `reassigned_by`, `reassigned_at`) VALUES
(88, 29, 'Flood', '', 6.67698320, 125.28683770, 'uploads/incident_69661087f110d.jpg', 'completed', '2026-01-13 09:29:43', 'MDDRMO', 30, '2026-01-13 17:38:54', '2026-01-13 17:39:01', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-13 10:29:43', 30, 8, '2026-01-13 09:38:27'),
(89, 20, 'Fire', 'Test incident for reassign button', 14.59950000, 120.98420000, NULL, 'completed', '2026-01-13 09:34:39', NULL, 30, '2026-01-13 17:34:39', '2026-01-14 19:09:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, '2026-01-13 10:05:08'),
(93, 29, 'Fire', '', 6.67697690, 125.28684490, 'uploads/incident_696614c75afb1.jpg', 'completed', '2026-01-13 09:47:51', 'BFP', 37, '2026-01-13 17:50:33', '2026-01-13 17:53:36', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-13 10:47:51', 37, 8, '2026-01-13 09:48:38'),
(94, 29, 'Flood', '', 6.67697690, 125.28684490, 'uploads/incident_6966159e27c57.jpg', 'completed', '2026-01-13 09:51:26', 'MDDRMO', 30, NULL, '2026-01-14 19:09:48', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-13 10:51:26', 30, 8, '2026-01-13 10:04:54'),
(95, 29, 'Fire', '', 6.67697690, 125.28684490, 'uploads/incident_69661605de325.jpg', 'pending', '2026-01-13 09:53:09', 'BFP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-13 10:53:09', 37, 8, '2026-01-13 10:04:49'),
(101, 20, 'Crime', 'Test crime incident from mobile app', NULL, NULL, NULL, 'pending', '2026-01-13 10:24:51', 'PNP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android 14', '127.0.0.1', '2026-01-13 11:24:51', 36, NULL, NULL),
(102, 20, 'Crime', 'Test crime incident from mobile app', NULL, NULL, NULL, 'pending', '2026-01-13 10:25:27', 'PNP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android 14', '127.0.0.1', '2026-01-13 11:25:27', 36, NULL, NULL),
(103, 20, 'Flood', 'Test flood incident from mobile app', NULL, NULL, NULL, 'completed', '2026-01-13 10:25:27', 'MDDRMO', 30, NULL, '2026-01-14 19:09:39', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android 14', '127.0.0.1', '2026-01-13 11:25:27', 30, NULL, NULL),
(104, 20, 'Crime', 'Direct API test - Crime incident', NULL, NULL, NULL, 'pending', '2026-01-13 10:27:09', 'PNP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android 14', '::1', '2026-01-13 11:27:09', 36, NULL, NULL),
(105, 20, 'Flood', 'Direct API test - Flood incident', NULL, NULL, NULL, 'completed', '2026-01-13 10:27:09', 'MDDRMO', 30, NULL, '2026-01-14 19:09:37', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android 14', '::1', '2026-01-13 11:27:09', 30, NULL, NULL),
(106, 30, 'Fire', '', 6.67697240, 125.28683200, 'uploads/incident_69675eeba2938.jpg', 'completed', '2026-01-14 09:16:27', 'BFP', 35, '2026-01-14 19:23:51', '2026-01-14 19:23:56', NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-14 10:16:27', 35, NULL, NULL),
(107, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_696767769d03e_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 09:52:54', 'MDDRMO', 30, NULL, '2026-01-14 19:09:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(108, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_6967677cdedec_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 09:53:00', 'MDDRMO', 30, NULL, '2026-01-14 19:09:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(109, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_6967683936de7_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 09:56:09', 'MDDRMO', 30, NULL, '2026-01-14 19:09:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(110, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_696769159d8aa_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 09:59:49', 'MDDRMO', 30, NULL, '2026-01-14 19:09:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(111, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_69676a2da78b6_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 10:04:29', 'MDDRMO', 30, NULL, '2026-01-14 19:09:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(112, 29, 'Accident', '[Auto-detected incident type or description here]', 6.74208164, 125.35556977, '../uploads/incident_69676b1c80133_462563150_919083610090021_2039057445074010291_n.jpg', 'completed', '2026-01-14 10:08:28', 'MDDRMO', 30, NULL, '2026-01-14 19:08:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(113, 29, 'Accident', '', 6.67414715, 125.26423454, 'uploads/incident_69677e06e996d.jpg', 'pending', '2026-01-14 11:29:10', 'MDDRMO', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-14 12:29:10', 30, NULL, NULL),
(114, 29, 'Crime', '', 6.67414715, 125.26423454, 'uploads/incident_69677f051c9a1.jpg', 'pending', '2026-01-14 11:33:25', 'PNP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android KL6-H6935GH-U-OPPJ-PJ-251014V1671', '192.168.5.17', '2026-01-14 12:33:25', 36, NULL, NULL),
(115, 26, 'Accident', 'please help me in under the water', 6.68014940, 125.31590607, NULL, 'pending', '2026-01-14 11:56:06', 'MDDRMO', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android X6528-F069TUVWXYBdBeBfBpCrDmDnDpEaEbEc-T-OP-250731V1979', '192.168.5.16', '2026-01-14 12:56:06', 30, NULL, NULL),
(116, 26, 'Fire', '', 6.68014940, 125.31590607, 'uploads/incident_69678493b075d.jpg', 'pending', '2026-01-14 11:57:07', 'BFP', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android X6528-F069TUVWXYBdBeBfBpCrDmDnDpEaEbEc-T-OP-250731V1979', '192.168.5.16', '2026-01-14 12:57:07', 35, NULL, NULL),
(117, 26, 'Other', 'The image shows a person smiling and giving a thumbs-up gesture. There are no visible signs of any emergency incident such as fire, flood, accident, crime, or landslide.', 6.68014940, 125.31590607, 'uploads/incident_696784d69ed48.jpg', 'pending', '2026-01-14 11:58:19', 'MDDRMO', NULL, NULL, NULL, NULL, NULL, NULL, 'Mobile', 'Alerto360 App | android X6528-F069TUVWXYBdBeBfBpCrDmDnDpEaEbEc-T-OP-250731V1979', '192.168.5.16', '2026-01-14 12:58:14', 30, NULL, NULL);


DELIMITER $$
CREATE TRIGGER `incident_accepted_trigger` AFTER UPDATE ON `incidents` FOR EACH ROW BEGIN
            IF OLD.accepted_by IS NULL AND NEW.accepted_by IS NOT NULL THEN
                -- Check if entry already exists to prevent duplicates
                IF NOT EXISTS (
                    SELECT 1 FROM responder_history 
                    WHERE responder_id = NEW.accepted_by 
                    AND incident_id = NEW.id 
                    AND action_type = 'accepted'
                ) THEN
                    INSERT INTO responder_history (responder_id, incident_id, action_type, action_timestamp)
                    VALUES (NEW.accepted_by, NEW.id, 'accepted', COALESCE(NEW.accepted_at, NOW()));
                END IF;
            END IF;
        END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `incident_completed_trigger` AFTER UPDATE ON `incidents` FOR EACH ROW BEGIN
            IF OLD.status != 'completed' AND NEW.status = 'completed' AND NEW.accepted_by IS NOT NULL THEN
                INSERT INTO responder_history (responder_id, incident_id, action_type, action_timestamp)
                VALUES (NEW.accepted_by, NEW.id, 'completed', COALESCE(NEW.completed_at, NOW()));
            END IF;
        END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `incident_declined_trigger` AFTER UPDATE ON `incidents` FOR EACH ROW BEGIN
            IF OLD.declined_by IS NULL AND NEW.declined_by IS NOT NULL THEN
                -- Check if entry already exists to prevent duplicates
                IF NOT EXISTS (
                    SELECT 1 FROM responder_history 
                    WHERE responder_id = NEW.declined_by 
                    AND incident_id = NEW.id 
                    AND action_type = 'declined'
                ) THEN
                    INSERT INTO responder_history (responder_id, incident_id, action_type, action_reason, action_timestamp)
                    VALUES (NEW.declined_by, NEW.id, 'declined', NEW.decline_reason, COALESCE(NEW.declined_at, NOW()));
                END IF;
            END IF;
        END
$$
DELIMITER ;

CREATE TABLE `incident_images` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `notifications` (`id`, `user_id`, `incident_id`, `message`, `type`, `is_read`, `created_at`) VALUES
(176, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #69\nStatus: PENDING - Requires immediate response\nTime: 2025-12-16 12:00:31', 'general', 1, '2025-12-16 11:00:31'),
(177, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #69\nTime: 2025-12-16 12:00:31', 'general', 1, '2025-12-16 11:00:31'),
(178, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #70\nTime: 2026-01-06 07:26:14', 'general', 1, '2026-01-06 06:26:14'),
(179, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Crime\nIncident ID: #71\nTime: 2026-01-06 07:26:56', 'general', 1, '2026-01-06 06:26:56'),
(180, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #72\nTime: 2026-01-06 07:37:33', 'general', 1, '2026-01-06 06:37:33'),
(181, 30, NULL, '🚨 New Flood incident assigned to you - Please Accept or Decline (Incident #73)', 'general', 1, '2026-01-06 06:39:16'),
(182, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #73\nStatus: PENDING - Requires immediate response\nTime: 2026-01-06 07:39:16', 'general', 1, '2026-01-06 06:39:16'),
(183, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #73\nTime: 2026-01-06 07:39:16', 'general', 1, '2026-01-06 06:39:16'),
(184, 8, NULL, 'incident_declined (Incident #72)', 'general', 1, '2026-01-13 07:07:30'),
(185, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #74)', 'general', 0, '2026-01-13 07:30:46'),
(186, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #74\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 08:30:46', 'general', 0, '2026-01-13 07:30:46'),
(187, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #74\nTime: 2026-01-13 08:30:46', 'general', 1, '2026-01-13 07:30:46'),
(188, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #79)', 'general', 0, '2026-01-13 07:49:46'),
(189, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #79\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 08:49:46', 'general', 0, '2026-01-13 07:49:46'),
(190, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #79\nTime: 2026-01-13 08:49:46', 'general', 1, '2026-01-13 07:49:46'),
(191, 8, NULL, 'incident_declined (Incident #79)', 'general', 1, '2026-01-13 07:51:29'),
(192, 8, NULL, 'incident_declined (Incident #79)', 'general', 1, '2026-01-13 07:55:27'),
(193, 8, NULL, 'incident_declined (Incident #79)', 'general', 1, '2026-01-13 09:26:39'),
(194, 30, NULL, '🚨 New Flood incident assigned to you - Please Accept or Decline (Incident #88)', 'general', 0, '2026-01-13 09:29:44'),
(195, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #88\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:29:44', 'general', 0, '2026-01-13 09:29:44'),
(196, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #88\nTime: 2026-01-13 10:29:44', 'general', 1, '2026-01-13 09:29:44'),
(197, 8, NULL, 'incident_declined (Incident #88)', 'general', 1, '2026-01-13 09:30:41'),
(198, 8, NULL, 'incident_declined (Incident #88)', 'general', 1, '2026-01-13 09:38:12'),
(199, 8, NULL, '✅ INCIDENT COMPLETED\nIncident ID: #88\nType: Flood\nReporter: Stephen\nCompleted by: blop (MDDRMO)\nStatus: COMPLETED\nCompleted at: 2026-01-13 10:39:01', 'general', 1, '2026-01-13 09:39:01'),
(200, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #93)', 'general', 0, '2026-01-13 09:47:51'),
(201, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #93\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:47:51', 'general', 0, '2026-01-13 09:47:51'),
(202, 37, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #93\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:47:51', 'general', 0, '2026-01-13 09:47:51'),
(203, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #93\nTime: 2026-01-13 10:47:51', 'general', 1, '2026-01-13 09:47:51'),
(204, 8, NULL, 'incident_declined (Incident #93)', 'general', 1, '2026-01-13 09:48:14'),
(205, 30, NULL, '🚨 New Flood incident assigned to you - Please Accept or Decline (Incident #94)', 'general', 0, '2026-01-13 09:51:26'),
(206, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #94\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:51:26', 'general', 0, '2026-01-13 09:51:26'),
(207, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #94\nTime: 2026-01-13 10:51:26', 'general', 1, '2026-01-13 09:51:26'),
(208, 8, NULL, 'incident_declined (Incident #94)', 'general', 1, '2026-01-13 09:51:51'),
(209, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #95)', 'general', 0, '2026-01-13 09:53:09'),
(210, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #95\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:53:09', 'general', 0, '2026-01-13 09:53:09'),
(211, 37, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #95\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 10:53:09', 'general', 0, '2026-01-13 09:53:09'),
(212, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #95\nTime: 2026-01-13 10:53:09', 'general', 1, '2026-01-13 09:53:09'),
(213, 8, NULL, '✅ INCIDENT COMPLETED\nIncident ID: #93\nType: Fire\nReporter: Stephen\nCompleted by: gealon (BFP)\nStatus: COMPLETED\nCompleted at: 2026-01-13 10:53:36', 'general', 1, '2026-01-13 09:53:36'),
(214, 8, NULL, 'incident_declined (Incident #95)', 'general', 1, '2026-01-13 09:54:02'),
(215, 8, NULL, 'incident_declined (Incident #94)', 'general', 1, '2026-01-13 10:03:52'),
(216, 8, NULL, 'incident_declined (Incident #95)', 'general', 1, '2026-01-13 10:04:05'),
(218, 35, 95, 'New incident #95 (Fire) assigned to you - Please Accept or Decline', 'assignment', 0, '2026-01-13 10:11:55'),
(219, 8, NULL, 'incident_declined (Incident #95)', 'general', 1, '2026-01-13 10:12:20'),
(220, 37, 95, 'REASSIGNED: Incident #95 (Fire) has been reassigned to you - Please Accept or Decline', 'assignment', 0, '2026-01-13 10:12:42'),
(221, 36, NULL, '🚨 New Crime incident assigned to you - Please Accept or Decline (Incident #101)', 'general', 0, '2026-01-13 10:24:51'),
(222, 36, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Crime\nIncident ID: #101\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 11:24:51', 'general', 0, '2026-01-13 10:24:51'),
(223, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Crime\nIncident ID: #101\nTime: 2026-01-13 11:24:51', 'general', 1, '2026-01-13 10:24:51'),
(224, 36, NULL, '🚨 New Crime incident assigned to you - Please Accept or Decline (Incident #102)', 'general', 0, '2026-01-13 10:25:27'),
(225, 36, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Crime\nIncident ID: #102\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 11:25:27', 'general', 0, '2026-01-13 10:25:27'),
(226, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Crime\nIncident ID: #102\nTime: 2026-01-13 11:25:27', 'general', 1, '2026-01-13 10:25:27'),
(227, 30, NULL, '🚨 New Flood incident assigned to you - Please Accept or Decline (Incident #103)', 'general', 0, '2026-01-13 10:25:27'),
(228, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #103\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 11:25:27', 'general', 0, '2026-01-13 10:25:27'),
(229, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #103\nTime: 2026-01-13 11:25:27', 'general', 1, '2026-01-13 10:25:27'),
(230, 36, NULL, '🚨 New Crime incident assigned to you - Please Accept or Decline (Incident #104)', 'general', 0, '2026-01-13 10:27:09'),
(231, 36, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Crime\nIncident ID: #104\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 11:27:09', 'general', 0, '2026-01-13 10:27:09'),
(232, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Crime\nIncident ID: #104\nTime: 2026-01-13 11:27:09', 'general', 1, '2026-01-13 10:27:09'),
(233, 30, NULL, '🚨 New Flood incident assigned to you - Please Accept or Decline (Incident #105)', 'general', 0, '2026-01-13 10:27:09'),
(234, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Flood\nIncident ID: #105\nStatus: PENDING - Requires immediate response\nTime: 2026-01-13 11:27:09', 'general', 0, '2026-01-13 10:27:09'),
(235, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Flood\nIncident ID: #105\nTime: 2026-01-13 11:27:09', 'general', 1, '2026-01-13 10:27:09'),
(236, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #106)', 'general', 0, '2026-01-14 09:16:27'),
(237, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #106\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:16:27', 'general', 0, '2026-01-14 09:16:27'),
(238, 37, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #106\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:16:27', 'general', 0, '2026-01-14 09:16:27'),
(239, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Fire\nIncident ID: #106\nTime: 2026-01-14 10:16:27', 'general', 1, '2026-01-14 09:16:27'),
(240, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #107\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:52:54', 'general', 0, '2026-01-14 09:52:54'),
(241, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #107\nTime: 2026-01-14 10:52:54', 'general', 1, '2026-01-14 09:52:54'),
(242, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #108\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:53:00', 'general', 0, '2026-01-14 09:53:00'),
(243, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #108\nTime: 2026-01-14 10:53:00', 'general', 1, '2026-01-14 09:53:00'),
(244, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #109\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:56:09', 'general', 0, '2026-01-14 09:56:09'),
(245, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #109\nTime: 2026-01-14 10:56:09', 'general', 1, '2026-01-14 09:56:09'),
(246, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #110\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 10:59:49', 'general', 0, '2026-01-14 09:59:49'),
(247, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #110\nTime: 2026-01-14 10:59:49', 'general', 1, '2026-01-14 09:59:49'),
(248, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #111\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 11:04:29', 'general', 0, '2026-01-14 10:04:29'),
(249, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #111\nTime: 2026-01-14 11:04:29', 'general', 1, '2026-01-14 10:04:29'),
(250, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #112\nLocation: GPS: 6.74208164046477, 125.35556977048363\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 11:08:28', 'general', 0, '2026-01-14 10:08:28'),
(251, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #112\nTime: 2026-01-14 11:08:28', 'general', 1, '2026-01-14 10:08:28'),
(252, 8, NULL, '✅ INCIDENT COMPLETED\nIncident ID: #106\nType: Fire\nReporter: blop\nCompleted by: Brent Albaracin (BFP)\nStatus: COMPLETED\nCompleted at: 2026-01-14 12:23:56', 'general', 1, '2026-01-14 11:23:56'),
(253, 30, NULL, '🚨 New Accident incident assigned to you - Please Accept or Decline (Incident #113)', 'general', 0, '2026-01-14 11:29:11'),
(254, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #113\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:29:11', 'general', 0, '2026-01-14 11:29:11'),
(255, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #113\nTime: 2026-01-14 12:29:11', 'general', 1, '2026-01-14 11:29:11'),
(256, 36, NULL, '🚨 New Crime incident assigned to you - Please Accept or Decline (Incident #114)', 'general', 0, '2026-01-14 11:33:25'),
(257, 36, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Crime\nIncident ID: #114\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:33:25', 'general', 0, '2026-01-14 11:33:25'),
(258, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Crime\nIncident ID: #114\nTime: 2026-01-14 12:33:25', 'general', 1, '2026-01-14 11:33:25'),
(259, 8, NULL, 'incident_declined (Incident #113)', 'general', 1, '2026-01-14 11:36:11'),
(260, 30, 113, 'REASSIGNED: Incident #113 (Accident) has been reassigned to you - Please Accept or Decline', 'assignment', 0, '2026-01-14 11:39:42'),
(261, 30, NULL, '🚨 New Accident incident assigned to you - Please Accept or Decline (Incident #115)', 'general', 0, '2026-01-14 11:56:06'),
(262, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Accident\nIncident ID: #115\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:56:06', 'general', 0, '2026-01-14 11:56:06'),
(263, 8, NULL, '📊 ADMIN NOTIFICATION\nAction: New incident reported\nIncident Type: Accident\nIncident ID: #115\nTime: 2026-01-14 12:56:06', 'general', 1, '2026-01-14 11:56:06'),
(264, 35, NULL, '🚨 New Fire incident assigned to you - Please Accept or Decline (Incident #116)', 'general', 0, '2026-01-14 11:57:07'),
(265, 35, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #116\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:57:07', 'general', 0, '2026-01-14 11:57:07'),
(266, 37, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Fire\nIncident ID: #116\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:57:07', 'general', 0, '2026-01-14 11:57:07'),
(268, 30, NULL, '🚨 New Other incident assigned to you - Please Accept or Decline (Incident #117)', 'general', 0, '2026-01-14 11:58:20'),
(269, 30, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Other\nIncident ID: #117\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:58:20', 'general', 0, '2026-01-14 11:58:20'),
(270, 36, NULL, '🚨 NEW EMERGENCY ALERT 🚨\nType: Other\nIncident ID: #117\nStatus: PENDING - Requires immediate response\nTime: 2026-01-14 12:58:20', 'general', 0, '2026-01-14 11:58:20');



CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `reset_token` varchar(64) NOT NULL,
  `reset_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `password_resets` (`id`, `user_id`, `email`, `reset_token`, `reset_code`, `created_at`, `expires_at`, `is_used`, `used_at`) VALUES
(10, 26, 'embamorats@gmail.com', 'e4ceaec7cdecff852c7e2b08c17083dfd3852919c5f3641327e45f08e568397a', '815636', '2025-12-16 09:32:53', '2025-12-16 10:47:53', 1, '2025-12-16 09:33:36');


CREATE TABLE `responder_history` (
  `id` int(11) NOT NULL,
  `responder_id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `action_type` enum('accepted','declined','completed') NOT NULL,
  `action_reason` text DEFAULT NULL,
  `action_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action` enum('accepted','declined','completed') NOT NULL DEFAULT 'accepted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `responder_history` (`id`, `responder_id`, `incident_id`, `action_type`, `action_reason`, `action_timestamp`, `created_at`, `action`) VALUES
(23, 30, 88, 'declined', 'Not enough resources/equipment', '2026-01-13 09:30:09', '2026-01-13 09:30:09', 'accepted'),
(24, 30, 89, 'accepted', NULL, '2026-01-13 09:34:39', '2026-01-13 09:34:39', 'accepted'),
(25, 30, 89, 'declined', 'Test decline - too far from location', '2026-01-13 09:34:39', '2026-01-13 09:34:39', 'accepted'),
(29, 30, 88, 'accepted', NULL, '2026-01-13 09:38:54', '2026-01-13 09:38:54', 'accepted'),
(30, 30, 88, 'completed', NULL, '2026-01-13 09:39:01', '2026-01-13 09:39:01', 'accepted'),
(35, 35, 93, 'declined', 'Currently responding to another incident', '2026-01-13 09:48:14', '2026-01-13 09:48:14', 'accepted'),
(36, 37, 93, 'accepted', NULL, '2026-01-13 09:50:33', '2026-01-13 09:50:33', 'accepted'),
(37, 30, 94, 'declined', 'Too far from location', '2026-01-13 09:51:51', '2026-01-13 09:51:51', 'accepted'),
(38, 37, 93, 'completed', NULL, '2026-01-13 09:53:36', '2026-01-13 09:53:36', 'accepted'),
(39, 35, 95, 'declined', 'Currently responding to another incident', '2026-01-13 09:54:02', '2026-01-13 09:54:02', 'accepted'),
(48, 30, 112, 'accepted', NULL, '2026-01-14 11:08:24', '2026-01-14 11:08:24', 'accepted'),
(49, 30, 111, 'accepted', NULL, '2026-01-14 11:08:25', '2026-01-14 11:08:25', 'accepted'),
(50, 30, 110, 'accepted', NULL, '2026-01-14 11:08:26', '2026-01-14 11:08:26', 'accepted'),
(51, 30, 109, 'accepted', NULL, '2026-01-14 11:08:27', '2026-01-14 11:08:27', 'accepted'),
(52, 30, 108, 'accepted', NULL, '2026-01-14 11:08:27', '2026-01-14 11:08:27', 'accepted'),
(53, 30, 107, 'accepted', NULL, '2026-01-14 11:08:28', '2026-01-14 11:08:28', 'accepted'),
(54, 30, 105, 'accepted', NULL, '2026-01-14 11:08:29', '2026-01-14 11:08:29', 'accepted'),
(55, 30, 103, 'accepted', NULL, '2026-01-14 11:08:30', '2026-01-14 11:08:30', 'accepted'),
(56, 30, 94, 'accepted', NULL, '2026-01-14 11:08:32', '2026-01-14 11:08:32', 'accepted'),
(57, 30, 112, 'completed', NULL, '2026-01-14 11:08:57', '2026-01-14 11:08:57', 'accepted'),
(58, 30, 111, 'completed', NULL, '2026-01-14 11:09:09', '2026-01-14 11:09:09', 'accepted'),
(59, 30, 110, 'completed', NULL, '2026-01-14 11:09:24', '2026-01-14 11:09:24', 'accepted'),
(60, 30, 109, 'completed', NULL, '2026-01-14 11:09:27', '2026-01-14 11:09:27', 'accepted'),
(61, 30, 108, 'completed', NULL, '2026-01-14 11:09:31', '2026-01-14 11:09:31', 'accepted'),
(62, 30, 107, 'completed', NULL, '2026-01-14 11:09:35', '2026-01-14 11:09:35', 'accepted'),
(63, 30, 105, 'completed', NULL, '2026-01-14 11:09:37', '2026-01-14 11:09:37', 'accepted'),
(64, 30, 103, 'completed', NULL, '2026-01-14 11:09:39', '2026-01-14 11:09:39', 'accepted'),
(65, 30, 94, 'completed', NULL, '2026-01-14 11:09:48', '2026-01-14 11:09:48', 'accepted'),
(66, 30, 89, 'completed', NULL, '2026-01-14 11:09:50', '2026-01-14 11:09:50', 'accepted'),
(67, 35, 106, 'accepted', NULL, '2026-01-14 11:23:51', '2026-01-14 11:23:51', 'accepted'),
(68, 35, 106, 'completed', NULL, '2026-01-14 11:23:56', '2026-01-14 11:23:56', 'accepted'),
(69, 30, 113, 'declined', 'Too far from location', '2026-01-14 11:36:11', '2026-01-14 11:36:11', 'accepted');


CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_required` tinyint(1) DEFAULT 1,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','responder','citizen','super_admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responder_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `users` (`id`, `name`, `email`, `email_verified`, `verification_required`, `password`, `role`, `created_at`, `responder_type`) VALUES
(8, 'Admin', 'admin@admin', 0, 1, '$2y$10$N3BsHgBn1TVqg8ROQdEEte4wIr3BwhC2t63TR9ruRAy/BVrYH1/Qq', 'admin', '2025-07-31 06:36:49', NULL),
(20, 'Stephen', 'letmemidsteff09@gmail.com', 0, 1, '$2y$10$CKSt43kqYFpxQlxoY.zyYubysBQHCtMSRNbPbeRo5zD5HtEq8N0i.', 'citizen', '2025-12-06 10:22:02', NULL),
(26, 'emba', 'embamorats@gmail.com', 1, 0, '$2y$10$aEELhkCmhiCaM8udZfNkpuKpc2hr7byTO73rwCOYB6W0cZAzKuIhK', 'citizen', '2025-12-06 12:03:42', NULL),
(29, 'Stephen', 'stephenjoiegravino@gmail.com', 1, 0, '$2y$10$iyml2EXYk1zHdtah82HoX.ZBi2QlnCVff9wsybAQC5G4rk1EeZZUO', 'citizen', '2025-12-09 21:00:13', NULL),
(30, 'blop', 'bloopersnoob@gmail.com', 1, 0, '$2y$10$EXVBYtnAemGZpcwrwF0fB.1UoEvUpIzQcU.2MSnLOyWPJ8y.l1cta', 'responder', '2025-12-09 21:32:31', 'MDDRMO'),
(33, 'stephen joie gravino', 'stephencurrygravino09@gmail.com', 1, 1, '$2y$10$lYRpul25ioXh/fhc8mS2HexyxtQEpw04fcAyJ6lc5IBrroDq8cxGC', 'super_admin', '2025-12-11 14:33:13', NULL),
(34, 'lolnoob', 'johnghlendealdo@gmail.com', 1, 0, '$2y$10$LW1G0x7ZR.UtqcTZ7QlmY.wZ2nIcZ/D4Zsxyunb/t0NsxhCtWDNLW', 'citizen', '2025-12-16 09:58:51', NULL),
(35, 'Brent Albaracin', 'brent@gmail.com', 1, 1, '$2y$10$Q09nImUaAMx3L337sTIwhejCnApzXAlZTTFG2HVN81a7R6H7D6xOy', 'responder', '2026-01-13 06:33:23', 'BFP'),
(36, 'Roel morato', 'roel@gmail.com', 1, 1, '$2y$10$Uc1r2amzTMsIWGxjMcje/OyXCMhuj3NcF2YnGXxGmvvxqswx3yKme', 'responder', '2026-01-13 06:33:44', 'PNP'),
(37, 'gealon', 'gealon@gmail.com', 1, 1, '$2y$10$da3z8oegQdhj9ssmLszioOYpKUKzDPeWmILjzHSgJm2TwGKq1jt96', 'responder', '2026-01-13 08:49:25', 'BFP');


CREATE TABLE `user_online_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_online` tinyint(1) DEFAULT 1,
  `on_duty` tinyint(1) DEFAULT 0,
  `device_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `user_online_status` (`id`, `user_id`, `last_seen`, `is_online`, `on_duty`, `device_info`) VALUES
(2, 8, '2025-11-25 09:52:55', 0, 0, 'Flutter Mobile App'),
(121, 29, '2026-01-14 12:09:50', 1, 0, 'Flutter Mobile App'),
(122, 30, '2026-01-14 11:39:08', 0, 1, 'Web Browser - Responder Dashboard'),
(137, 26, '2026-01-14 12:12:55', 1, 0, 'Flutter Mobile App'),
(138, 33, '2026-01-06 06:21:51', 1, 0, 'Flutter Mobile App'),
(183, 35, '2026-01-14 11:29:18', 0, 1, 'Web Browser - Responder Dashboard'),
(230, 37, '2026-01-13 09:53:39', 0, 0, 'Web Browser - Responder Dashboard');

ALTER TABLE `acceptance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acceptance_log_incident` (`incident_id`),
  ADD KEY `idx_acceptance_log_responder` (`responder_id`);


ALTER TABLE `email_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_code` (`verification_code`),
  ADD KEY `idx_user` (`user_id`);


ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_incidents_accepted_at` (`accepted_at`),
  ADD KEY `idx_incidents_completed_at` (`completed_at`),
  ADD KEY `fk_reassigned_by` (`reassigned_by`);


ALTER TABLE `incident_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_notifications_incident` (`incident_id`);


ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`reset_token`),
  ADD KEY `idx_code` (`reset_code`);


ALTER TABLE `responder_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_responder_action` (`responder_id`,`incident_id`,`action_type`),
  ADD KEY `idx_responder_id` (`responder_id`),
  ADD KEY `idx_incident_id` (`incident_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_action_timestamp` (`action_timestamp`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `user_online_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`),
  ADD KEY `idx_is_online` (`is_online`),
  ADD KEY `idx_last_seen` (`last_seen`),
  ADD KEY `idx_on_duty` (`on_duty`);


ALTER TABLE `acceptance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `email_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;


ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;


ALTER TABLE `incident_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;


ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;


ALTER TABLE `responder_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;


ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;


ALTER TABLE `user_online_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=443;


ALTER TABLE `acceptance_log`
  ADD CONSTRAINT `acceptance_log_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acceptance_log_ibfk_2` FOREIGN KEY (`responder_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;


ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_reassigned_by` FOREIGN KEY (`reassigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);


ALTER TABLE `incident_images`
  ADD CONSTRAINT `incident_images_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`);


ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);


ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;


ALTER TABLE `responder_history`
  ADD CONSTRAINT `responder_history_ibfk_1` FOREIGN KEY (`responder_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responder_history_ibfk_2` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;


ALTER TABLE `user_online_status`
  ADD CONSTRAINT `user_online_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

