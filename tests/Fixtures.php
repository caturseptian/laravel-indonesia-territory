<?php

namespace CaturSeptian\IndonesiaTerritory\Tests;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;

/**
 * Subset data DKI Jakarta (provinsi 31) supaya test cepat. Dibuat sekali per proses.
 */
final class Fixtures
{
    private static ?string $path = null;

    public static function data(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        $source = DataRepository::package();
        $path = sys_get_temp_dir().'/territory-fixture-'.getmypid();
        @mkdir($path, 0777, true);

        $filters = [
            'provinces' => fn (array $row) => $row['code'] === '31',
            'regencies' => fn (array $row) => $row['province_code'] === '31',
            'districts' => fn (array $row) => str_starts_with($row['code'], '31.'),
            'villages' => fn (array $row) => str_starts_with($row['code'], '31.'),
            'islands' => fn (array $row) => $row['province_code'] === '31',
            'code_changes' => fn (array $row) => str_starts_with($row['code'], '31'),
        ];
        foreach ($filters as $name => $keep) {
            self::writeCsv("{$path}/{$name}.csv.gz", array_values(array_filter(iterator_to_array($source->rows($name), false), $keep)));
        }
        copy($source->path('manifest.json'), "{$path}/manifest.json");

        return self::$path = $path;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function writeCsv(string $path, array $rows): void
    {
        $handle = fopen('compress.zlib://'.$path, 'w');
        fputcsv($handle, array_keys($rows[0]), escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), escape: '');
        }
        fclose($handle);
    }
}
