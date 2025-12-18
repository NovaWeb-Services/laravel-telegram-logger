<?php

namespace NWServices\TelegramLogger\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NWServices\TelegramLogger\Services\TelegramNotifier;
use NWServices\TelegramLogger\Support\FilePositionTracker;
use NWServices\TelegramLogger\Support\LogParser;

class MonitorLogCommand extends Command
{
    protected $signature = 'telegram:monitor-log
                            {--log-path= : Path to log file (default: storage/logs/laravel.log)}
                            {--levels= : Comma-separated log levels to monitor (default: from config)}
                            {--dry-run : Parse and display errors without sending to Telegram}
                            {--reset : Reset the file position tracker}';

    protected $description = 'Monitor Laravel log file for new errors and send Telegram notifications';

    protected TelegramNotifier $notifier;
    protected LogParser $parser;
    protected FilePositionTracker $tracker;

    public function handle(): int
    {
        $logPath = $this->option('log-path') ?? config('telegram-logger.log_path', storage_path('logs/laravel.log'));
        $levelsOption = $this->option('levels');
        $levels = $levelsOption
            ? array_map('trim', explode(',', $levelsOption))
            : array_map('trim', explode(',', config('telegram-logger.monitor_levels', 'ERROR,CRITICAL,ALERT,EMERGENCY')));
        $dryRun = $this->option('dry-run');

        // Initialize components
        $this->notifier = app(TelegramNotifier::class);
        $this->parser = new LogParser();
        $this->tracker = new FilePositionTracker($logPath);

        // Handle reset option
        if ($this->option('reset')) {
            $this->tracker->reset();
            $this->info('Position tracker reset.');
            return Command::SUCCESS;
        }

        // Check if log file exists
        if (!File::exists($logPath)) {
            $this->warn("Log file not found: {$logPath}");
            return Command::SUCCESS; // Not an error - file may not exist yet
        }

        // Check for log rotation
        if ($this->tracker->wasRotated($logPath)) {
            $this->info('Log rotation detected. Resetting position.');
            $this->tracker->reset();
        }

        // Get position and read new content
        $position = $this->tracker->getPosition();
        $fileSize = File::size($logPath);

        if ($position['position'] >= $fileSize) {
            $this->info('No new log entries.');
            return Command::SUCCESS;
        }

        // Read new content from last position
        $handle = fopen($logPath, 'r');
        if ($handle === false) {
            $this->error("Unable to open log file: {$logPath}");
            return Command::FAILURE;
        }

        fseek($handle, $position['position']);
        $newContent = fread($handle, $fileSize - $position['position']);
        fclose($handle);

        if ($newContent === false) {
            $this->error("Unable to read log file: {$logPath}");
            return Command::FAILURE;
        }

        // Parse and filter entries
        $entries = $this->parser->parse($newContent, $levels);

        if (empty($entries)) {
            $this->info('No matching log entries found.');
        } else {
            $this->info(sprintf('Found %d error(s).', count($entries)));

            foreach ($entries as $entry) {
                $this->processEntry($entry, $dryRun);
            }
        }

        // Save new position
        $inode = PHP_OS_FAMILY !== 'Windows' ? (@stat($logPath)['ino'] ?? null) : null;
        $this->tracker->savePosition($fileSize, $inode);

        return Command::SUCCESS;
    }

    /**
     * Process a single log entry.
     *
     * @param array $entry
     * @param bool $dryRun
     * @return void
     */
    protected function processEntry(array $entry, bool $dryRun): void
    {
        $message = $entry['message'];
        if (!empty($entry['stacktrace'])) {
            $message .= "\n" . $entry['stacktrace'];
        }

        if ($dryRun) {
            $this->line('---');
            $this->line("[{$entry['level']}] {$entry['timestamp']} ({$entry['environment']})");
            $this->line($entry['message']);
            if (!empty($entry['stacktrace'])) {
                $stackPreview = substr($entry['stacktrace'], 0, 200);
                $this->line("Stack trace: {$stackPreview}" . (strlen($entry['stacktrace']) > 200 ? '...' : ''));
            }
            return;
        }

        $sent = $this->notifier->send(
            $message,
            $entry['level'],
            $entry['timestamp'],
            ['environment' => $entry['environment']]
        );

        $messagePreview = substr($entry['message'], 0, 50);
        if ($sent) {
            $this->info("Sent notification: {$messagePreview}...");
        } else {
            $this->warn("Skipped (throttled/filtered): {$messagePreview}...");
        }
    }
}
