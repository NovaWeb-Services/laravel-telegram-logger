<?php

namespace NWServices\TelegramLogger;

use Illuminate\Support\ServiceProvider;
use NWServices\TelegramLogger\Commands\MonitorLogCommand;
use NWServices\TelegramLogger\Services\TelegramNotifier;

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

        // Bind the notifier as a singleton
        $this->app->singleton(TelegramNotifier::class, function ($app) {
            return new TelegramNotifier();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/telegram-logger.php' => config_path('telegram-logger.php'),
        ], 'telegram-logger-config');

        // Register the artisan command
        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorLogCommand::class,
            ]);
        }
    }
}
