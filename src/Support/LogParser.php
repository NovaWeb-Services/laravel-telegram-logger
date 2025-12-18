<?php

namespace NWServices\TelegramLogger\Support;

class LogParser
{
    /**
     * Laravel log format pattern.
     * Matches: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
     */
    protected const LOG_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/';

    /**
     * Parse log entries from content, filtering by level.
     *
     * @param string $content Raw log file content
     * @param array $levels Levels to match (e.g., ['ERROR', 'CRITICAL'])
     * @return array Array of parsed log entries
     */
    public function parse(string $content, array $levels = ['ERROR']): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;
        $levels = array_map('strtoupper', $levels);

        foreach ($lines as $line) {
            if (preg_match(self::LOG_PATTERN, $line, $matches)) {
                // Save previous entry if it exists
                if ($currentEntry !== null) {
                    $currentEntry['stacktrace'] = trim($currentEntry['stacktrace']);
                    $entries[] = $currentEntry;
                }

                $level = strtoupper($matches[3]);

                // Start new entry only if it matches our target levels
                if (in_array($level, $levels, true)) {
                    $currentEntry = [
                        'timestamp' => $matches[1],
                        'environment' => $matches[2],
                        'level' => $level,
                        'message' => $matches[4],
                        'stacktrace' => '',
                    ];
                } else {
                    // This is a new log entry but not one we care about
                    $currentEntry = null;
                }
            } elseif ($currentEntry !== null && trim($line) !== '') {
                // Continuation line (stack trace) - append to current entry
                $currentEntry['stacktrace'] .= $line . "\n";
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null) {
            $currentEntry['stacktrace'] = trim($currentEntry['stacktrace']);
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    /**
     * Check if a line starts a new log entry.
     *
     * @param string $line
     * @return bool
     */
    public function isLogEntryStart(string $line): bool
    {
        return preg_match(self::LOG_PATTERN, $line) === 1;
    }

    /**
     * Parse a single log line.
     *
     * @param string $line
     * @return array|null Parsed entry or null if not a valid log line
     */
    public function parseLine(string $line): ?array
    {
        if (!preg_match(self::LOG_PATTERN, $line, $matches)) {
            return null;
        }

        return [
            'timestamp' => $matches[1],
            'environment' => $matches[2],
            'level' => strtoupper($matches[3]),
            'message' => $matches[4],
            'stacktrace' => '',
        ];
    }
}
