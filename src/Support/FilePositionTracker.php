<?php

namespace NWServices\TelegramLogger\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class FilePositionTracker
{
    protected string $cacheKey;
    protected string $storageMethod;
    protected string $positionFile;

    public function __construct(string $logPath, ?string $storageMethod = null)
    {
        $this->cacheKey = 'telegram_log_monitor_position_' . md5($logPath);
        $this->storageMethod = $storageMethod ?? config('telegram-logger.position_storage', 'cache');
        $this->positionFile = storage_path('app/telegram-logger-position.json');
    }

    /**
     * Get the last read position and file inode.
     *
     * @return array ['position' => int, 'inode' => int|null]
     */
    public function getPosition(): array
    {
        $default = ['position' => 0, 'inode' => null];

        if ($this->storageMethod === 'file') {
            return $this->getFromFile($default);
        }

        return Cache::get($this->cacheKey, $default);
    }

    /**
     * Save the current position.
     *
     * @param int $position
     * @param int|null $inode
     * @return void
     */
    public function savePosition(int $position, ?int $inode = null): void
    {
        $data = ['position' => $position, 'inode' => $inode];

        if ($this->storageMethod === 'file') {
            $this->saveToFile($data);
            return;
        }

        // Cache forever (or until manually cleared)
        Cache::forever($this->cacheKey, $data);
    }

    /**
     * Reset position (for log rotation detection or manual reset).
     *
     * @return void
     */
    public function reset(): void
    {
        if ($this->storageMethod === 'file') {
            $this->saveToFile(['position' => 0, 'inode' => null]);
            return;
        }

        Cache::forget($this->cacheKey);
    }

    /**
     * Detect if the log file was rotated.
     *
     * @param string $logPath
     * @return bool
     */
    public function wasRotated(string $logPath): bool
    {
        $saved = $this->getPosition();

        if (!File::exists($logPath)) {
            return true;
        }

        $currentSize = File::size($logPath);
        $currentInode = $this->getFileInode($logPath);

        // File is smaller than saved position = rotated
        if ($currentSize < $saved['position']) {
            return true;
        }

        // Inode changed = rotated (Unix systems)
        if ($saved['inode'] !== null && $currentInode !== null && $saved['inode'] !== $currentInode) {
            return true;
        }

        return false;
    }

    /**
     * Get the file inode (Unix systems only).
     *
     * @param string $path
     * @return int|null
     */
    protected function getFileInode(string $path): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null; // Windows doesn't have inodes
        }

        $stat = @stat($path);
        return $stat['ino'] ?? null;
    }

    /**
     * Get position from file storage.
     *
     * @param array $default
     * @return array
     */
    protected function getFromFile(array $default): array
    {
        if (!File::exists($this->positionFile)) {
            return $default;
        }

        $content = File::get($this->positionFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : $default;
    }

    /**
     * Save position to file storage.
     *
     * @param array $data
     * @return void
     */
    protected function saveToFile(array $data): void
    {
        $directory = dirname($this->positionFile);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($this->positionFile, json_encode($data));
    }
}
