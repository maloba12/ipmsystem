# Log Rotation Setup

## Overview
This document outlines the log rotation setup for the IPMS system.

## Log Directory Structure
Logs are stored in the `logs/` directory with the following structure:
```
logs/
├── access.log
├── error.log
├── application.log
└── rotated/
    ├── access.log.2024-01-01
    ├── error.log.2024-01-01
    └── application.log.2024-01-01
```

## Rotation Strategy
- Logs are rotated daily at midnight
- Each log type is rotated independently
- Old logs are compressed and moved to the `rotated/` subdirectory
- Logs older than 30 days are automatically deleted

## Implementation
The log rotation is handled by a cron job that runs daily. The script performs the following tasks:
1. Create new log files for the current day
2. Compress and timestamp old log files
3. Move compressed logs to the `rotated/` directory
4. Delete logs older than 30 days

## Cron Job Setup
Add the following cron job to your system's crontab:
```
0 0 * * * php /path/to/ipmsystem/logs/log_rotation.php
```

## Monitoring
- Check log rotation status daily
- Verify that old logs are being properly archived
- Monitor disk usage to ensure logs are being cleaned up

## Troubleshooting
If log rotation fails:
1. Check cron job logs
2. Verify write permissions on the logs directory
3. Check disk space
4. Review error logs for specific errors
