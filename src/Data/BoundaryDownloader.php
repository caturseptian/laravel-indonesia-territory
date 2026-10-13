<?php

namespace CaturSeptian\IndonesiaTerritory\Data;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengunduh GeoJSON boundary dari free API, memverifikasi sha256 terhadap manifest package,
 * lalu menyimpannya dalam bentuk gzip. Berkas yang sudah ada dilewati, jadi unduhan bisa dilanjutkan.
 */
final class BoundaryDownloader
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        private readonly DataRepository $data,
        private readonly array $urls,
        private readonly string $target,
        private readonly int $retrySleepMs = 1000,
    ) {}

    /**
     * @param  list<Level>  $levels
     * @param  'all'|list<string>  $provinces
     * @return list<array{path: string, level: string, province: ?string, bytes: int, sha256: string}>
     */
    public function plan(array $levels, string|array $provinces): array
    {
        $entries = $this->data->manifest()['boundaries'];

        if (is_array($provinces)) {
            $unknown = array_diff($provinces, array_filter(array_column($entries, 'province')));
            if ($unknown !== []) {
                throw new InstallationException('Tidak ada boundary untuk provinsi: '.implode(', ', $unknown).'.');
            }
        }

        $wanted = array_map(fn (Level $level) => $level->value, $levels);

        return array_values(array_filter($entries, fn (array $entry) => in_array($entry['level'], $wanted, true)
            && ($entry['province'] === null || $provinces === 'all' || in_array($entry['province'], $provinces, true))));
    }

    /**
     * @param  list<array{path: string, sha256: string}>  $files
     * @param  (callable(array<string, mixed>, string): void)|null  $progress
     */
    public function download(array $files, ?callable $progress = null): void
    {
        File::ensureDirectoryExists($this->target);

        foreach ($files as $file) {
            $destination = "{$this->target}/{$file['path']}.gz";

            if (is_file($destination)) {
                $progress && $progress($file, 'sudah ada');

                continue;
            }

            $this->fetch($file, $destination);
            $progress && $progress($file, 'diunduh');
        }
    }

    /**
     * @param  array{path: string, sha256: string}  $file
     */
    private function fetch(array $file, string $destination): void
    {
        $errors = [];

        foreach ($this->urls as $url) {
            $source = rtrim($url, '/')."/api/v1/export/boundaries/{$file['path']}";

            try {
                $body = Http::timeout(600)->retry(3, $this->retrySleepMs)->get($source)->throw()->body();
            } catch (Throwable $e) {
                $errors[] = "{$source}: {$e->getMessage()}";

                continue;
            }

            if (hash('sha256', $body) !== $file['sha256']) {
                throw new InstallationException("Checksum {$file['path']} dari {$source} tidak cocok dengan manifest package. Data di sumber mungkin sudah diperbarui; perbarui package dengan composer update caturseptian/laravel-indonesia-territory.");
            }

            file_put_contents("{$destination}.tmp", gzencode($body, 6));
            rename("{$destination}.tmp", $destination);

            return;
        }

        throw new InstallationException("Gagal mengunduh boundary {$file['path']}:\n".implode("\n", $errors)."\nJalankan ulang php artisan territory:boundaries untuk melanjutkan.");
    }
}
