<?php

/** Cache JSON su filesystem con lock, condivisa tra i worker PHP-FPM. */
final class ResponseCache
{
    public static function remember(string $key, int $ttl, callable $producer): mixed
    {
        $directory = BASE_PATH . '/data/runtime-cache';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return $producer();
        }
        $name = hash('sha256', $key);
        $file = $directory . '/' . $name . '.json';

        // Percorso veloce lock-free: il file viene pubblicato con rename
        // atomico, quindi più lettori possono usarlo contemporaneamente.
        clearstatcache(true, $file);
        if (is_file($file) && filemtime($file) + $ttl >= time()) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (json_last_error() === JSON_ERROR_NONE) return $cached;
        }

        $lock = @fopen($directory . '/' . $name . '.lock', 'c');
        if (!$lock) return $producer();

        try {
            if (!flock($lock, LOCK_EX)) return $producer();
            clearstatcache(true, $file);
            if (is_file($file) && filemtime($file) + $ttl >= time()) {
                $cached = json_decode((string) file_get_contents($file), true);
                if (json_last_error() === JSON_ERROR_NONE) return $cached;
            }
            $value = $producer();
            $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json !== false) {
                $temporary = $file . '.' . getmypid() . '.tmp';
                if (@file_put_contents($temporary, $json, LOCK_EX) !== false) @rename($temporary, $file);
            }
            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
