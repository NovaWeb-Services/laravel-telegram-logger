<?php

namespace Nova\TelegramLogger;

use Monolog\Level;
use Monolog\Logger;

class TelegramLogger
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config): Logger
    {
        $level = $this->parseLevel($config['level'] ?? config('telegram-logger.level', 'error'));

        return new Logger('telegram', [
            new TelegramHandler($level),
        ]);
    }

    /**
     * Parse the log level from string to Monolog Level.
     *
     * @param  string  $level
     * @return \Monolog\Level
     */
    protected function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Error,
        };
    }
}
