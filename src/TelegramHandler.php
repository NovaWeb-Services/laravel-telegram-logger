<?php

namespace NWServices\TelegramLogger;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramHandler extends AbstractProcessingHandler
{
    protected string $botToken;
    protected string $chatId;
    protected string $projectName;
    protected string $environment;
    protected array $notifyEnvironments;
    protected int $throttle;

    public function __construct(Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->botToken = config('telegram-logger.bot_token', '');
        $this->chatId = config('telegram-logger.chat_id', '');
        $this->projectName = config('telegram-logger.project_name', 'Laravel');
        $this->environment = config('telegram-logger.environment', 'production');
        $this->notifyEnvironments = config('telegram-logger.notify_environments', ['production', 'staging']);
        $this->throttle = config('telegram-logger.throttle', 60);
    }

    /**
     * Writes the log record to Telegram.
     *
     * @param  \Monolog\LogRecord  $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        try {
            if (!$this->shouldSend($record)) {
                return;
            }

            $message = $this->formatMessage($record);

            $response = $this->sendToTelegram($message);

            // If Markdown failed, retry with plain text
            if (!$response['ok']) {
                $plainMessage = $this->formatPlainMessage($record);
                $this->sendToTelegram($plainMessage, false);
            }
        } catch (\Throwable $e) {
            // Log to a fallback file to help diagnose issues
            $this->logFallback($record, $e);
        }
    }

    /**
     * Determine if the notification should be sent.
     *
     * @param  \Monolog\LogRecord  $record
     * @return bool
     */
    protected function shouldSend(LogRecord $record): bool
    {
        // Skip if credentials are missing
        if (empty($this->botToken) || empty($this->chatId)) {
            return false;
        }

        // Skip if not in a notify environment
        if (!in_array($this->environment, $this->notifyEnvironments, true)) {
            return false;
        }

        // Check throttle for duplicate messages
        if ($this->throttle > 0 && $this->isThrottled($record)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the message is throttled.
     *
     * @param  \Monolog\LogRecord  $record
     * @return bool
     */
    protected function isThrottled(LogRecord $record): bool
    {
        $cacheKey = 'telegram_logger_' . md5($record->message);

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, $this->throttle);

        return false;
    }

    /**
     * Format the log record into a Telegram message.
     *
     * @param  \Monolog\LogRecord  $record
     * @return string
     */
    protected function formatMessage(LogRecord $record): string
    {
        $emoji = $this->getLevelEmoji($record->level);
        $levelName = strtoupper($record->level->name);
        $timestamp = $record->datetime->format('Y-m-d H:i:s T');
        $message = $this->truncate($record->message, 3000);

        $text = "{$emoji} *{$levelName}*\n\n";
        $text .= "*Project:* `{$this->escapeMarkdown($this->projectName)}`\n";
        $text .= "*Environment:* `{$this->escapeMarkdown($this->environment)}`\n";
        $text .= "*Time:* `{$timestamp}`\n\n";
        $text .= "*Message:*\n```\n{$this->escapeMarkdown($message)}\n```";

        if (!empty($record->context)) {
            $context = $this->formatContext($record->context);
            if (!empty($context)) {
                $text .= "\n\n*Context:*\n```\n{$this->escapeMarkdown($context)}\n```";
            }
        }

        return $text;
    }

    /**
     * Format the context array into a string.
     *
     * @param  array  $context
     * @return string
     */
    protected function formatContext(array $context): string
    {
        // Remove exception object if present, as it can be very large
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->truncate($json ?: '', 500);
    }

    /**
     * Get the emoji for the log level.
     *
     * @param  \Monolog\Level  $level
     * @return string
     */
    protected function getLevelEmoji(Level $level): string
    {
        return match ($level) {
            Level::Emergency => "\xF0\x9F\x86\x98", // 🆘
            Level::Alert => "\xF0\x9F\x94\x94", // 🔔
            Level::Critical => "\xF0\x9F\x94\xB4", // 🔴
            Level::Error => "\xF0\x9F\x9A\xA8", // 🚨
            Level::Warning => "\xE2\x9A\xA0\xEF\xB8\x8F", // ⚠️
            Level::Notice => "\xF0\x9F\x93\x9D", // 📝
            Level::Info => "\xE2\x84\xB9\xEF\xB8\x8F", // ℹ️
            Level::Debug => "\xF0\x9F\x94\x8D", // 🔍
        };
    }

    /**
     * Truncate a string to a maximum length.
     *
     * @param  string  $string
     * @param  int  $maxLength
     * @return string
     */
    protected function truncate(string $string, int $maxLength): string
    {
        if (mb_strlen($string) <= $maxLength) {
            return $string;
        }

        return mb_substr($string, 0, $maxLength - 3) . '...';
    }

    /**
     * Escape special characters for Telegram Markdown.
     *
     * @param  string  $text
     * @return string
     */
    protected function escapeMarkdown(string $text): string
    {
        // Only escape characters that would break the formatting outside of code blocks
        // Inside code blocks (```), Telegram handles escaping automatically
        return str_replace(['`'], ['\\`'], $text);
    }

    /**
     * Send the message to Telegram.
     *
     * @param  string  $message
     * @param  bool  $useMarkdown
     * @return array
     */
    protected function sendToTelegram(string $message, bool $useMarkdown = true): array
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ];

        if ($useMarkdown) {
            $payload['parse_mode'] = 'Markdown';
        }

        $response = Http::timeout(5)->post($url, $payload);

        return $response->json() ?? ['ok' => false, 'description' => 'No response'];
    }

    /**
     * Format the log record into a plain text message (no Markdown).
     *
     * @param  \Monolog\LogRecord  $record
     * @return string
     */
    protected function formatPlainMessage(LogRecord $record): string
    {
        $emoji = $this->getLevelEmoji($record->level);
        $levelName = strtoupper($record->level->name);
        $timestamp = $record->datetime->format('Y-m-d H:i:s T');
        $message = $this->truncate($record->message, 3000);

        $text = "{$emoji} {$levelName}\n\n";
        $text .= "Project: {$this->projectName}\n";
        $text .= "Environment: {$this->environment}\n";
        $text .= "Time: {$timestamp}\n\n";
        $text .= "Message:\n{$message}";

        if (!empty($record->context)) {
            $context = $this->formatContext($record->context);
            if (!empty($context)) {
                $text .= "\n\nContext:\n{$context}";
            }
        }

        return $text;
    }

    /**
     * Log to a fallback file when Telegram sending fails.
     *
     * @param  \Monolog\LogRecord  $record
     * @param  \Throwable  $exception
     * @return void
     */
    protected function logFallback(LogRecord $record, \Throwable $exception): void
    {
        $fallbackPath = storage_path('logs/telegram-logger-errors.log');
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] Telegram logger failed: {$exception->getMessage()}\n";
        $entry .= "Original log: [{$record->level->name}] {$record->message}\n";
        $entry .= "Exception trace: {$exception->getTraceAsString()}\n";
        $entry .= str_repeat('-', 80) . "\n";

        @file_put_contents($fallbackPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
