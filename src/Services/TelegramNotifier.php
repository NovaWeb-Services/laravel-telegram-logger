<?php

namespace NWServices\TelegramLogger\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TelegramNotifier
{
    protected string $botToken;
    protected string $chatId;
    protected string $projectName;
    protected string $environment;
    protected array $notifyEnvironments;
    protected int $throttle;

    public function __construct(array $config = [])
    {
        $this->botToken = $config['bot_token'] ?? config('telegram-logger.bot_token', '');
        $this->chatId = $config['chat_id'] ?? config('telegram-logger.chat_id', '');
        $this->projectName = $config['project_name'] ?? config('telegram-logger.project_name', 'Laravel');
        $this->environment = $config['environment'] ?? config('telegram-logger.environment', 'production');
        $this->notifyEnvironments = $config['notify_environments'] ?? config('telegram-logger.notify_environments', ['production', 'staging']);
        $this->throttle = $config['throttle'] ?? config('telegram-logger.throttle', 60);
    }

    /**
     * Send a notification to Telegram.
     *
     * @param string $message The log message
     * @param string $level The log level (ERROR, CRITICAL, etc.)
     * @param string|null $timestamp The timestamp of the log entry
     * @param array $context Additional context data
     * @return bool Whether the message was sent successfully
     */
    public function send(string $message, string $level = 'ERROR', ?string $timestamp = null, array $context = []): bool
    {
        if (!$this->shouldSend()) {
            return false;
        }

        if ($this->isThrottled($message)) {
            return false;
        }

        $formattedMessage = $this->formatMessage($message, $level, $timestamp, $context);
        $response = $this->sendToTelegram($formattedMessage);

        // If Markdown failed, retry with plain text
        if (!($response['ok'] ?? false)) {
            $plainMessage = $this->formatPlainMessage($message, $level, $timestamp, $context);
            $response = $this->sendToTelegram($plainMessage, false);
        }

        return $response['ok'] ?? false;
    }

    /**
     * Determine if the notification should be sent.
     *
     * @return bool
     */
    protected function shouldSend(): bool
    {
        // Skip if credentials are missing
        if (empty($this->botToken) || empty($this->chatId)) {
            return false;
        }

        // Skip if not in a notify environment
        if (!in_array($this->environment, $this->notifyEnvironments, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the message is throttled.
     *
     * @param string $message
     * @return bool
     */
    protected function isThrottled(string $message): bool
    {
        if ($this->throttle <= 0) {
            return false;
        }

        $cacheKey = 'telegram_logger_' . md5($message);

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, $this->throttle);

        return false;
    }

    /**
     * Format the message for Telegram with Markdown.
     *
     * @param string $message
     * @param string $level
     * @param string|null $timestamp
     * @param array $context
     * @return string
     */
    protected function formatMessage(string $message, string $level, ?string $timestamp, array $context): string
    {
        $emoji = $this->getLevelEmoji($level);
        $levelName = strtoupper($level);
        $timestamp = $timestamp ?? date('Y-m-d H:i:s T');
        $message = $this->truncate($message, 3000);

        $text = "{$emoji} *{$levelName}*\n\n";
        $text .= "*Project:* `{$this->escapeMarkdown($this->projectName)}`\n";
        $text .= "*Environment:* `{$this->escapeMarkdown($this->environment)}`\n";
        $text .= "*Time:* `{$timestamp}`\n\n";
        $text .= "*Message:*\n```\n{$this->escapeMarkdown($message)}\n```";

        if (!empty($context)) {
            $contextStr = $this->formatContext($context);
            if (!empty($contextStr)) {
                $text .= "\n\n*Context:*\n```\n{$this->escapeMarkdown($contextStr)}\n```";
            }
        }

        return $text;
    }

    /**
     * Format the message as plain text (no Markdown).
     *
     * @param string $message
     * @param string $level
     * @param string|null $timestamp
     * @param array $context
     * @return string
     */
    protected function formatPlainMessage(string $message, string $level, ?string $timestamp, array $context): string
    {
        $emoji = $this->getLevelEmoji($level);
        $levelName = strtoupper($level);
        $timestamp = $timestamp ?? date('Y-m-d H:i:s T');
        $message = $this->truncate($message, 3000);

        $text = "{$emoji} {$levelName}\n\n";
        $text .= "Project: {$this->projectName}\n";
        $text .= "Environment: {$this->environment}\n";
        $text .= "Time: {$timestamp}\n\n";
        $text .= "Message:\n{$message}";

        if (!empty($context)) {
            $contextStr = $this->formatContext($context);
            if (!empty($contextStr)) {
                $text .= "\n\nContext:\n{$contextStr}";
            }
        }

        return $text;
    }

    /**
     * Format the context array into a string.
     *
     * @param array $context
     * @return string
     */
    protected function formatContext(array $context): string
    {
        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->truncate($json ?: '', 500);
    }

    /**
     * Get the emoji for the log level.
     *
     * @param string $level
     * @return string
     */
    protected function getLevelEmoji(string $level): string
    {
        return match (strtoupper($level)) {
            'EMERGENCY' => "\xF0\x9F\x86\x98", // 🆘
            'ALERT' => "\xF0\x9F\x94\x94", // 🔔
            'CRITICAL' => "\xF0\x9F\x94\xB4", // 🔴
            'ERROR' => "\xF0\x9F\x9A\xA8", // 🚨
            'WARNING' => "\xE2\x9A\xA0\xEF\xB8\x8F", // ⚠️
            'NOTICE' => "\xF0\x9F\x93\x9D", // 📝
            'INFO' => "\xE2\x84\xB9\xEF\xB8\x8F", // ℹ️
            'DEBUG' => "\xF0\x9F\x94\x8D", // 🔍
            default => "\xF0\x9F\x9A\xA8", // 🚨
        };
    }

    /**
     * Truncate a string to a maximum length.
     *
     * @param string $string
     * @param int $maxLength
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
     * @param string $text
     * @return string
     */
    protected function escapeMarkdown(string $text): string
    {
        return str_replace(['`'], ['\\`'], $text);
    }

    /**
     * Send the message to Telegram.
     *
     * @param string $message
     * @param bool $useMarkdown
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

        try {
            $response = Http::timeout(5)->post($url, $payload);
            return $response->json() ?? ['ok' => false, 'description' => 'No response'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
