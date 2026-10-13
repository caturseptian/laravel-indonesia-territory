<?php

namespace CaturSeptian\IndonesiaTerritory\Seeding;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Mengisi tabel wilayah dari CSV gzip di territory.data_path dan GeoJSON gzip di territory.boundary_path.
 * Hanya tabel dan kolom yang ada di database yang diisi, sesuai migration hasil territory:install.
 *
 * Kelas ini sengaja tidak memakai kelas lain dari package karena ikut disalin saat eject.
 */
class TerritorySeeder extends Seeder
{
    /**
     * Urutan pengisian agar induk selalu ada lebih dulu.
     */
    private const TABLES = ['provinces', 'regencies', 'districts', 'villages', 'islands', 'code_changes'];

    private const CHUNK = 1000;

    private const BOUNDARY_CHUNK = 50;

    public function run(): void
    {
        $prefix = (string) config('territory.prefix', 'territory_');
        $dataPath = config('territory.data_path') ?? dirname(__DIR__, 2).'/resources/data';
        $tables = array_values(array_filter(self::TABLES, fn (string $table) => Schema::hasTable($prefix.$table)));

        if (Schema::hasTable($prefix.'boundaries')) {
            DB::table($prefix.'boundaries')->delete();
        }
        foreach (array_reverse($tables) as $table) {
            DB::table($prefix.$table)->delete();
        }

        foreach ($tables as $table) {
            DB::transaction(fn () => $this->seedCsv($prefix.$table, "{$dataPath}/{$table}.csv.gz"));
            $this->command?->line(sprintf('  %s: %s', $prefix.$table, number_format(DB::table($prefix.$table)->count())));
        }

        if (Schema::hasTable($prefix.'boundaries')) {
            $this->seedBoundaries();
        }
    }

    /**
     * Isi ulang tabel boundaries dari semua berkas yang sudah diunduh.
     */
    public function seedBoundaries(): void
    {
        $table = config('territory.prefix', 'territory_').'boundaries';
        $files = glob(rtrim((string) config('territory.boundary_path'), '/').'/*.geojson.gz') ?: [];

        DB::transaction(function () use ($table, $files) {
            DB::table($table)->delete();

            foreach ($files as $file) {
                $features = json_decode(gzdecode(file_get_contents($file)), true, flags: JSON_THROW_ON_ERROR)['features'];
                $rows = array_map(fn (array $feature) => [
                    'level' => $feature['properties']['level'],
                    'code' => $feature['properties']['code'],
                    'geometry' => json_encode($feature['geometry'], JSON_THROW_ON_ERROR),
                ], array_values(array_filter($features, fn (array $feature) => $feature['geometry'] !== null)));

                foreach (array_chunk($rows, self::BOUNDARY_CHUNK) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });

        $this->command?->line(sprintf('  %s: %s', $table, number_format(DB::table($table)->count())));
    }

    private function seedCsv(string $table, string $path): void
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $handle = @fopen('compress.zlib://'.$path, 'r') ?: throw new RuntimeException("Tidak bisa membuka {$path}.");
        $header = fgetcsv($handle, escape: '');

        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $row = array_intersect_key(array_combine($header, $values), $columns);
            $rows[] = array_map(fn (string $value) => $value === '' ? null : $value, $row);

            if (count($rows) === self::CHUNK) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table($table)->insert($rows);
        }

        fclose($handle);
    }
}
