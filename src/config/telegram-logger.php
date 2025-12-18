<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram bot token obtained from @BotFather.
    |
    */
    'bot_token' => env('TELEGRAM_LOGGER_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Telegram Chat ID
    |--------------------------------------------------------------------------
    |
    | The chat ID where notifications will be sent. This can be a user ID,
    | group ID, or channel ID (prefixed with @).
    |
    */
    'chat_id' => env('TELEGRAM_LOGGER_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Project Name
    |--------------------------------------------------------------------------
    |
    | The name of your project that will appear in notifications.
    | Defaults to your APP_NAME environment variable.
    |
    */
    'project_name' => env('TELEGRAM_LOGGER_PROJECT_NAME', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The current environment name that will appear in notifications.
    | Defaults to your APP_ENV environment variable.
    |
    */
    'environment' => env('TELEGRAM_LOGGER_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Notify Environments
    |--------------------------------------------------------------------------
    |
    | An array of environments where Telegram notifications should be sent.
    | In other environments, the handler will silently skip sending.
    |
    */
    'notify_environments' => explode(',', env('TELEGRAM_LOGGER_NOTIFY_ENVIRONMENTS', 'production,staging')),

    /*
    |--------------------------------------------------------------------------
    | Minimum Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum log level that will trigger a Telegram notification.
    | Available levels: debug, info, notice, warning, error, critical, alert, emergency
    |
    */
    'level' => env('TELEGRAM_LOGGER_LEVEL', 'error'),

    /*
    |--------------------------------------------------------------------------
    | Throttle Duration
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait before sending duplicate error messages.
    | This helps prevent spam when the same error occurs multiple times.
    |
    */
    'throttle' => (int) env('TELEGRAM_LOGGER_THROTTLE', 60),

    /*
    |--------------------------------------------------------------------------
    | Log File Path
    |--------------------------------------------------------------------------
    |
    | The path to the log file to monitor. Defaults to Laravel's standard log.
    |
    */
    'log_path' => env('TELEGRAM_LOGGER_LOG_PATH', storage_path('logs/laravel.log')),

    /*
    |--------------------------------------------------------------------------
    | Monitor Levels
    |--------------------------------------------------------------------------
    |
    | Log levels to monitor. Comma-separated string.
    | Available: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
    |
    */
    'monitor_levels' => env('TELEGRAM_LOGGER_MONITOR_LEVELS', 'ERROR,CRITICAL,ALERT,EMERGENCY'),

    /*
    |--------------------------------------------------------------------------
    | Position Storage Method
    |--------------------------------------------------------------------------
    |
    | Where to store the last read file position.
    | Options: 'cache' (Laravel cache) or 'file' (storage/app/telegram-logger-position.json)
    |
    */
    'position_storage' => env('TELEGRAM_LOGGER_POSITION_STORAGE', 'cache'),
];
