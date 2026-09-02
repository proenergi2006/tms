<?php

namespace App\Services;

/**
 * Parser log aplikasi Laravel (storage/logs/laravel.log, LOG_CHANNEL=stack
 * -> single, lihat .env) — supaya Admin Sistem bisa memantau
 * error/exception di server tanpa akses SSH. Baca saja, tidak pernah
 * menulis/menghapus file log.
 */
class SystemLogReader
{
    /**
     * Batas baca dari akhir file supaya tidak membebani memori kalau log
     * membengkak tak terkendali (mis. loop error yang belum sempat
     * ditangani) — 5MB cukup untuk ribuan baris log terbaru.
     */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return list<array{id: string, timestamp: string, environment: string, level: string, message: string, detail: ?string}>
     */
    public function entries(): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($size > self::MAX_BYTES) {
            fseek($handle, $size - self::MAX_BYTES);
            fgets($handle); // buang baris parsial di awal potongan
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        $entries = [];
        $current = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\S+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current) {
                    $entries[] = $this->finalize($current);
                }

                $current = [
                    'timestamp' => $m[1],
                    'environment' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => $m[4],
                    'detailLines' => [],
                ];
            } elseif ($current) {
                $current['detailLines'][] = $line;
            }
        }

        if ($current) {
            $entries[] = $this->finalize($current);
        }

        return array_reverse($entries);
    }

    private function finalize(array $entry): array
    {
        $detail = trim(implode("\n", $entry['detailLines']));
        unset($entry['detailLines']);

        $entry['detail'] = $detail !== '' ? $detail : null;
        $entry['id'] = md5($entry['timestamp'].$entry['message'].strlen($detail));

        return $entry;
    }
}
