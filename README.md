# School SMS Management System

A web-based SMS management platform that allows school administrators to manage students, guardians, and bulk messaging efficiently while maintaining a clean and optimized database.

## Overview

This system allows administrators to:

- Send bulk SMS messages to classes

- Send individual SMS messages to guardians

- Track message delivery status

- Promote students automatically at the end of the academic year

- Delete graduated students (Grade 9)

- Clean up old messages to prevent database bloat

- Lock academic year closure to prevent duplicate execution

The academic year begins January 1 and ends December 31. Year closure is manual and must be triggered by an administrator.

## Features

### Messaging

- Send SMS by Grade Level

- Send SMS by Stream

- Send SMS by Boarding Status

- Send individual guardian messages

- Store message status and timestamps

- API timeout protection

### Student & Guardian Management

- Students linked to guardians

- Automatic enrollment timestamp (enrolled_at)

- Automatic message timestamp (sent_at)

- Clean relational structure

### Academic Year Closure

When "Close Academic Year" is triggered:

- Deletes all messages before the cutoff date

- Deletes Grade 9 students (graduates)

- Deletes guardians linked to Grade 9 students

- Promotes remaining students by one grade

- Locks the academic year so it cannot be run twice

The system uses a transaction to ensure all operations succeed together or none are applied.

## Database Tables

`students`

- id (Primary Key)

- grade_level (INT)

- stream (VARCHAR)

- boarding_status (VARCHAR)

- enrolled_at (DATETIME)

`guardians`

- id (Primary Key)

- student_id (Foreign Key)

- guardian_name (VARCHAR)

- phone_number (VARCHAR)

`messages`

- id (Primary Key)

- msg_id (VARCHAR)

- content (TEXT)

- sender_id (INT)

- recipient_id (INT)

- status (VARCHAR)

- sent_at (DATETIME)

`academic_year_closures`

- id (Primary Key)

- year (INT, UNIQUE)

- closed_at (TIMESTAMP)

## Academic Year Logic

Cutoff date formula:

- cutoffDate = (year + 1)-01-01

Promotion condition:

Students are promoted if:

- grade_level < 9

enrolled_at < cutoffDate

Grade 9 students with enrolled_at < cutoffDate are permanently deleted.

Messages with sent_at < cutoffDate are deleted to prevent database growth.

The year is recorded in academic_year_closures to prevent duplicate execution.

## Installation

Import the database schema.

Ensure the following fields exist:

- students.enrolled_at

- messages.sent_at

Create the academic_year_closures table.

Configure database credentials in the PHP files.

Configure SMS API credentials.

## Performance Strategy

- Old messages deleted annually

- Graduated students removed

- No automatic cron jobs (manual control)

- Clean relational structure

## Recommended Maintenance

Once per year:

- Backup database

- Click "Close Academic Year"

- Confirm successful response

## Security Notes

- Backend validation enforced

- Transaction-based updates

- Year lock prevents double execution

- API keys should not be publicly exposed

## Future Improvements

- Archive table instead of hard delete

- Academic year reporting
