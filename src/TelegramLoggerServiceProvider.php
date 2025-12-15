<?php

namespace Nova\TelegramLogger;

use Illuminate\Support\ServiceProvider;

class TelegramLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/telegram-logger.php',
            'telegram-logger'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/telegram-logger.php' => config_path('telegram-logger.php'),
        ], 'telegram-logger-config');

        $this->app->make('log')->extend('telegram', function ($app, array $config) {
            return (new TelegramLogger())($config);
        });
    }
}
