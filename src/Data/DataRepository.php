<?php

namespace CaturSeptian\IndonesiaTerritory\Data;

use Generator;
use RuntimeException;

/**
 * Membaca manifest dan CSV gzip data wilayah dari satu direktori.
 */
final class DataRepository
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $manifest = null;

    public function __construct(private readonly string $path) {}

    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).'/resources/data';
    }

    public static function package(): self
    {
        return new self(self::packagePath());
    }

    public function path(string $file = ''): string
    {
        return rtrim($this->path.'/'.$file, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return $this->manifest ??= json_decode(
            file_get_contents($this->path('manifest.json')) ?: throw new RuntimeException("Tidak bisa membaca {$this->path('manifest.json')}."),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    public function datasetVersion(): string
    {
        return $this->manifest()['dataset_version'];
    }

    /**
     * Baris CSV sebagai array asosiatif, satu per satu.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $name): Generator
    {
        $path = $this->path("{$name}.csv.gz");
        $handle = @fopen('compress.zlib://'.$path, 'r') ?: throw new RuntimeException("Tidak bisa membuka {$path}.");
        $header = fgetcsv($handle, escape: '');

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            yield array_combine($header, $values);
        }

        fclose($handle);
    }

    /**
     * @return list<string> berkas yang hilang atau checksum-nya tidak cocok
     */
    public function verify(): array
    {
        $errors = [];
        foreach ($this->manifest()['files'] as $file) {
            $path = $this->path($file['file']);

            if (! is_file($path)) {
                $errors[] = "{$file['file']} tidak ada.";
            } elseif (hash_file('sha256', $path) !== $file['sha256']) {
                $errors[] = "Checksum {$file['file']} tidak cocok dengan manifest.";
            }
        }

        return $errors;
    }
}
