# Laravel Telegram Logger

A Laravel package for sending Telegram notifications when errors are logged. Get instant alerts in your Telegram chat when critical errors occur in your Laravel application.

## Features

- Send error logs to Telegram in real-time
- Configurable log levels (error, critical, alert, emergency, etc.)
- Environment-based notifications (only send in production/staging)
- Rate limiting to prevent spam from duplicate errors
- Formatted messages with emojis for quick visual identification
- Includes project name, environment, timestamp, and context
- Graceful failure handling - won't break your app if Telegram is unavailable

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- Monolog 3.x

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
TELEGRAM_LOGGER_LEVEL=error
TELEGRAM_LOGGER_THROTTLE=60
```

## Adding the Telegram Channel to Laravel Logging

Edit your `config/logging.php` file to add the Telegram channel:

```php
'channels' => [
    // ... existing channels ...

    'telegram' => [
        'driver' => 'telegram',
        'level' => env('TELEGRAM_LOGGER_LEVEL', 'error'),
    ],
],
```

### Using with the Stack Channel

The recommended approach is to add Telegram to your stack channel so it works alongside your existing logging:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'telegram'],
        'ignore_exceptions' => false,
    ],

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],

    'telegram' => [
        'driver' => 'telegram',
        'level' => env('TELEGRAM_LOGGER_LEVEL', 'error'),
    ],
],
```

### Using Telegram for Specific Logging Only

You can also log to Telegram explicitly:

```php
use Illuminate\Support\Facades\Log;

Log::channel('telegram')->error('Something went wrong!', [
    'user_id' => $user->id,
    'action' => 'payment_failed',
]);
```

## Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `bot_token` | `''` | Your Telegram bot token from BotFather |
| `chat_id` | `''` | The Telegram chat/group/channel ID to send messages to |
| `project_name` | `APP_NAME` | Project name displayed in notifications |
| `environment` | `APP_ENV` | Environment name displayed in notifications |
| `notify_environments` | `['production', 'staging']` | Only send notifications in these environments |
| `level` | `'error'` | Minimum log level to trigger notifications |
| `throttle` | `60` | Seconds to wait before sending duplicate messages |

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
Time: 2025-01-15 14:30:45 UTC

Message:
SQLSTATE[HY000] [2002] Connection refused

Context:
{
    "exception": {
        "class": "PDOException",
        "message": "SQLSTATE[HY000] [2002] Connection refused",
        "file": "/var/www/app/Database/Connection.php",
        "line": 42
    }
}
```

## Testing

To test if your configuration is working:

```php
Log::channel('telegram')->error('Test notification from Laravel Telegram Logger');
```

## Security

- Never commit your bot token to version control
- Use environment variables for all sensitive configuration
- Consider using a dedicated bot for each project
- Be mindful of what information you include in log context

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
