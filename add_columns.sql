-- Add missing columns to incidents table for Alerto360
-- Run this script to fix the "accepted_at" and "completed_at" column errors

USE alerto360;

-- Add accepted_at column (if it doesn't exist)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL;

-- Add completed_at column (if it doesn't exist)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL;

-- Update status ENUM to include 'completed'
ALTER TABLE incidents MODIFY COLUMN status ENUM('pending', 'accepted', 'done', 'resolved', 'completed', 'accept and complete') DEFAULT 'pending';

-- Show success message
SELECT 'Essential database columns added successfully! Accept & Complete should now work.' as Status;
