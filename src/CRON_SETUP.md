# Simple Cron Setup for Azure Queue Sync

## Yes, crontab is MUCH simpler! 

No need for Azure Logic Apps, Azure Functions, or sync_endpoint.php.
Just run the sync script directly on your server every 2 minutes.

## Setup Steps:

### 1. Upload files to your server:
- `sync_from_azure_cron.php`
- Update Azure SQL connection details in the file

### 2. Make the script executable:
```bash
chmod +x sync_from_azure_cron.php
```

### 3. Add to crontab:
```bash
# Edit crontab
crontab -e

# Add this line (runs every 2 minutes):
*/2 * * * * /usr/bin/php /path/to/your/sync_from_azure_cron.php >> /path/to/sync.log 2>&1

# Example with actual path:
*/2 * * * * /usr/bin/php /home/yourdomain/public_html/sync_from_azure_cron.php >> /home/yourdomain/sync.log 2>&1
```

### 4. Check your crontab:
```bash
crontab -l
```

## Cron Schedule Options:

```bash
# Every 2 minutes (recommended):
*/2 * * * * /usr/bin/php /path/to/sync_from_azure_cron.php >> /path/to/sync.log 2>&1

# Every 5 minutes (less frequent):
*/5 * * * * /usr/bin/php /path/to/sync_from_azure_cron.php >> /path/to/sync.log 2>&1

# Every minute (real-time, might be overkill):
* * * * * /usr/bin/php /path/to/sync_from_azure_cron.php >> /path/to/sync.log 2>&1
```

## Benefits of Cron Approach:

✅ **Completely FREE** - No Azure charges
✅ **Simple setup** - Just one cron line
✅ **No external dependencies** - Runs on your server
✅ **Easy debugging** - Check sync.log for issues
✅ **Reliable** - Cron is rock solid
✅ **Fast** - Direct server-to-server connection

## Log Monitoring:

```bash
# Watch the sync log in real-time:
tail -f /path/to/sync.log

# Check last 20 log entries:
tail -20 /path/to/sync.log

# Check for errors only:
grep "error" /path/to/sync.log
```

## Before You Start:

1. **Update Azure SQL connection** in `sync_from_azure_cron.php`:
   - Server name
   - Database name  
   - Username
   - Password

2. **Test manually first**:
   ```bash
   php sync_from_azure_cron.php
   ```

3. **Find PHP path** (if different):
   ```bash
   which php
   # Usually: /usr/bin/php or /usr/local/bin/php
   ```

## Files You DON'T Need Anymore:
- `sync_endpoint.php` - Not needed for cron
- `AZURE_SCHEDULING.md` - Not needed for cron
- `run_sync.bat` - Windows only, not for server

This is definitely the easiest approach! 🎯