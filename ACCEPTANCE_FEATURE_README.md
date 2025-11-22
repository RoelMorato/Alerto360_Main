# Responder Acceptance Feature

## Overview
This feature allows responders to accept pending requests and automatically marks them as "done" while redirecting the responder to the admin dashboard.

## Files Created/Modified

### New Files:
1. **accept_request.php** - Main acceptance handler
2. **setup_acceptance_log.sql** - Database setup script
3. **ACCEPTANCE_FEATURE_README.md** - This documentation

### Modified Files:
1. **responder_dashboard.php** - Added "Accept & Complete" button
2. **admin_dashboard.php** - Added acceptance confirmation message

## How It Works

1. **Responder Dashboard**: Shows two buttons for pending incidents:
   - "Accept" (legacy) - Only changes status to 'accepted'
   - "Accept & Complete" (new) - Changes status to 'done' and redirects to admin dashboard

2. **Acceptance Process**: When "Accept & Complete" is clicked:
   - Validates the incident is still pending
   - Updates incident status to 'done'
   - Records acceptance in acceptance_log table
   - Redirects responder to admin dashboard with confirmation

3. **Admin Dashboard**: Shows confirmation message when accessed after acceptance

## Database Setup

Run the SQL script to set up the acceptance log table:
```sql
-- Run setup_acceptance_log.sql in your MySQL database
```

## Usage

1. Responder logs in and sees pending incidents
2. Clicks "Accept & Complete" on a pending incident
3. Confirms the action in the popup dialog
4. Gets redirected to admin dashboard with success message
5. Incident is marked as "done" in the system

## Features

- **Transaction Safety**: Uses database transactions to ensure data consistency
- **Logging**: All acceptance actions are logged in the acceptance_log table
- **Validation**: Checks that incidents are still pending before acceptance
- **User Feedback**: Clear success/error messages
- **Admin Visibility**: Admin dashboard shows when requests are completed via acceptance

## Security

- Only authenticated responders can access the acceptance functionality
- Validates user sessions and permissions
- Uses prepared statements to prevent SQL injection
- Includes CSRF protection through form tokens
