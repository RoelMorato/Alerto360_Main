-- Update the incidents table status column to include new status values
-- This will add the new status values we need for the admin completion feature

ALTER TABLE `incidents` 
MODIFY COLUMN `status` ENUM('pending','in_progress','resolved','accepted','done','completed','accept and complete') DEFAULT 'pending';


UPDATE `incidents` SET `status` = 'pending' WHERE `status` = '' OR `status` IS NULL;
