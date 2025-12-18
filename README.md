# Laravel Telegram Logger

A Laravel package that monitors your `laravel.log` file and sends Telegram notifications when errors occur. Get instant alerts in your Telegram chat when critical errors happen in your Laravel application.

## Features

- Monitors `storage/logs/laravel.log` for new errors
- Scheduled task runs via Laravel scheduler (e.g., every minute)
- Only alerts on NEW errors (tracks file position to avoid duplicates)
- Configurable log levels (ERROR, CRITICAL, ALERT, EMERGENCY)
- Environment-based notifications (only send in production/staging)
- Rate limiting to prevent spam from duplicate errors
- Formatted messages with emojis for quick visual identification
- Handles multi-line log entries (stack traces)
- Detects log rotation automatically

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation

Install the package via Composer:

```bash
composer require nwservices/laravel-telegram-logger
```

The service provider will be automatically registered through Laravel's package auto-discovery.

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=telegram-logger-config
```

## Getting a Telegram Bot Token

1. Open Telegram and search for [@BotFather](https://t.me/BotFather)
2. Start a conversation and send `/newbot`
3. Follow the prompts to name your bot
4. BotFather will provide you with a token like `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`
5. Save this token for your environment configuration

## Getting Your Chat ID

### For Personal Notifications

1. Search for [@userinfobot](https://t.me/userinfobot) on Telegram
2. Start a conversation with it
3. It will reply with your user ID (a number like `123456789`)

### For Group Notifications

1. Add your bot to the group
2. Send a message in the group
3. Visit `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
4. Look for the `chat` object and find the `id` field (it will be negative for groups, like `-123456789`)

### For Channel Notifications

1. Add your bot as an administrator to the channel
2. Use the channel's username with an `@` prefix (e.g., `@mychannel`)
3. Or use the channel ID (find it using the getUpdates method after posting)

## Configuration

Add these environment variables to your `.env` file:

```env
TELEGRAM_LOGGER_BOT_TOKEN=your-bot-token-here
TELEGRAM_LOGGER_CHAT_ID=your-chat-id-here

# Optional settings
TELEGRAM_LOGGER_PROJECT_NAME="${APP_NAME}"
TELEGRAM_LOGGER_ENVIRONMENT="${APP_ENV}"
TELEGRAM_LOGGER_NOTIFY_ENVIRONMENTS=production,staging
TELEGRAM_LOGGER_THROTTLE=60

# Log monitoring settings
TELEGRAM_LOGGER_LOG_PATH=storage/logs/laravel.log
TELEGRAM_LOGGER_MONITOR_LEVELS=ERROR,CRITICAL,ALERT,EMERGENCY
TELEGRAM_LOGGER_POSITION_STORAGE=cache
```

## Setting Up the Scheduler

The package provides an artisan command that monitors your log file. You need to schedule it to run periodically.

### Laravel 11+ (routes/console.php)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('telegram:monitor-log')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

### Laravel 10 (app/Console/Kernel.php)

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('telegram:monitor-log')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}
```

Make sure your server's cron is set up to run Laravel's scheduler:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Artisan Command

### Basic Usage

```bash
# Run the log monitor (typically via scheduler)
php artisan telegram:monitor-log

# Test without sending (dry run)
php artisan telegram:monitor-log --dry-run

# Reset the position tracker (start fresh)
php artisan telegram:monitor-log --reset

# Monitor a custom log file
php artisan telegram:monitor-log --log-path=/var/log/myapp/app.log

# Monitor specific levels only
php artisan telegram:monitor-log --levels=ERROR,CRITICAL
```

### Command Options

| Option | Description |
|--------|-------------|
| `--log-path` | Path to log file (default: `storage/logs/laravel.log`) |
| `--levels` | Comma-separated log levels to monitor (default: from config) |
| `--dry-run` | Parse and display errors without sending to Telegram |
| `--reset` | Reset the file position tracker |

## Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `bot_token` | `''` | Your Telegram bot token from BotFather |
| `chat_id` | `''` | The Telegram chat/group/channel ID to send messages to |
| `project_name` | `APP_NAME` | Project name displayed in notifications |
| `environment` | `APP_ENV` | Environment name displayed in notifications |
| `notify_environments` | `['production', 'staging']` | Only send notifications in these environments |
| `throttle` | `60` | Seconds to wait before sending duplicate messages |
| `log_path` | `storage/logs/laravel.log` | Path to the log file to monitor |
| `monitor_levels` | `ERROR,CRITICAL,ALERT,EMERGENCY` | Log levels to monitor |
| `position_storage` | `cache` | Where to store file position (`cache` or `file`) |

## How It Works

1. The `telegram:monitor-log` command runs on a schedule (e.g., every minute)
2. It reads `laravel.log` from the last saved position
3. Parses new content for log entries matching configured levels (e.g., `.ERROR`)
4. Sends Telegram notifications for each matching entry
5. Saves the new file position to avoid re-processing
6. Automatically detects log rotation and resets position

## Log Level Emojis

Messages are prefixed with emojis for quick visual identification:

| Level | Emoji |
|-------|-------|
| Emergency | 🆘 |
| Alert | 🔔 |
| Critical | 🔴 |
| Error | 🚨 |
| Warning | ⚠️ |
| Notice | 📝 |
| Info | ℹ️ |
| Debug | 🔍 |

## Example Message

```
🚨 ERROR

Project: My Laravel App
Environment: production
Time: 2025-01-15 14:30:45

Message:
SQLSTATE[HY000] [2002] Connection refused
#0 /var/www/app/Database/Connection.php(42): PDO->__construct()
#1 /var/www/app/Database/Manager.php(123): Connection->connect()
...

Context:
{
    "environment": "production"
}
```

## Testing

To test if your configuration is working:

```bash
# Add a test error to your log file
echo "[$(date '+%Y-%m-%d %H:%M:%S')] production.ERROR: Test error from Laravel Telegram Logger" >> storage/logs/laravel.log

# Run the monitor in dry-run mode
php artisan telegram:monitor-log --dry-run

# Or run it for real
php artisan telegram:monitor-log
```

## Security

- Never commit your bot token to version control
- Use environment variables for all sensitive configuration
- Consider using a dedicated bot for each project
- Be mindful of what information appears in your logs

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
