<?php

namespace CaturSeptian\IndonesiaTerritory\Seeding;

use Generator;
use Illuminate\Console\Command;
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

    /**
     * Batas variabel terikat per query di SQLite sebelum 3.32; dipakai untuk menghitung ukuran chunk.
     */
    private const MAX_BINDINGS = 999;

    private const BOUNDARY_CHUNK = 50;

    /**
     * Seeder bisa dijalankan langsung tanpa perintah artisan; $command baru terisi lewat setCommand().
     */
    private bool $hasCommand = false;

    public function setCommand(Command $command)
    {
        $this->hasCommand = true;

        return parent::setCommand($command);
    }

    public function run(): void
    {
        $prefix = (string) config('territory.prefix', 'territory_');
        $dataPath = config('territory.data_path') ?? dirname(__DIR__, 2).'/resources/data';
        $tables = array_values(array_filter(self::TABLES, fn (string $table) => Schema::hasTable($prefix.$table)));

        // Baris disinkronkan per kode, bukan dihapus semua lalu diisi ulang, supaya tabel aplikasi
        // yang mereferensikan kode wilayah lewat foreign key tidak ikut terhapus atau terhalang.
        $seen = [];
        foreach ($tables as $table) {
            $seen[$table] = DB::transaction(fn () => $this->syncCsv($prefix.$table, "{$dataPath}/{$table}.csv.gz", $table !== 'code_changes'));
        }
        foreach (array_reverse($tables) as $table) {
            if ($table !== 'code_changes') {
                $this->deleteMissing($prefix.$table, $seen[$table]);
            }
            $this->report($prefix.$table);
        }

        if (Schema::hasTable($prefix.'boundaries')) {
            $this->seedBoundaries();
        }
    }

    /**
     * Isi ulang tabel boundaries dari semua berkas yang sudah diunduh, satu feature per baris
     * sehingga berkas besar tidak dimuat utuh ke memori.
     */
    public function seedBoundaries(): void
    {
        $table = config('territory.prefix', 'territory_').'boundaries';
        $files = $this->boundaryFiles();

        if ($files === [] && config('territory.boundaries.levels', []) !== [] && $this->hasCommand) {
            $this->command->warn('  Berkas boundary belum ada. Jalankan php artisan territory:boundaries untuk mengunduhnya.');
        }

        DB::transaction(function () use ($table, $files) {
            DB::table($table)->delete();

            foreach ($files as $file) {
                $rows = [];
                foreach ($this->features($file) as $feature) {
                    if ($feature['geometry'] === null) {
                        continue;
                    }
                    $rows[] = [
                        'level' => $feature['properties']['level'],
                        'code' => $feature['properties']['code'],
                        'geometry' => json_encode($feature['geometry'], JSON_THROW_ON_ERROR),
                    ];
                    if (count($rows) === self::BOUNDARY_CHUNK) {
                        DB::table($table)->insert($rows);
                        $rows = [];
                    }
                }
                if ($rows !== []) {
                    DB::table($table)->insert($rows);
                }
            }
        });

        $this->report($table);
    }

    /**
     * Berkas boundary yang sesuai level dan provinsi di config. Tanpa level di config, semua berkas dipakai.
     *
     * @return list<string>
     */
    private function boundaryFiles(): array
    {
        $files = glob(rtrim((string) config('territory.boundary_path'), '/').'/*.geojson.gz') ?: [];
        $levels = config('territory.boundaries.levels', []);
        $provinces = config('territory.boundaries.provinces', 'all');
        if ($levels === []) {
            return $files;
        }

        $plural = ['province' => 'provinces', 'regency' => 'regencies', 'district' => 'districts', 'village' => 'villages'];

        return array_values(array_filter($files, function (string $file) use ($levels, $plural, $provinces) {
            if (preg_match('/^(\w+?)(?:-(\d{2}))?\.geojson\.gz$/D', basename($file), $match) !== 1) {
                return false;
            }
            $level = array_search($match[1], $plural, true);

            return in_array($level, $levels, true)
                && (! isset($match[2]) || $provinces === 'all' || in_array($match[2], (array) $provinces, true));
        }));
    }

    /**
     * Feature dari GeoJSON gzip. Berkas dari Indonesia Region API berisi satu feature per baris dan dibaca
     * baris demi baris; bentuk lain dibaca utuh.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function features(string $file): Generator
    {
        $handle = @fopen('compress.zlib://'.$file, 'r') ?: throw new RuntimeException("Tidak bisa membuka {$file}.");
        $streamed = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, ",\r\n");
            if (str_starts_with($line, '{"type":"Feature"')) {
                $streamed = true;
                yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
        }
        fclose($handle);

        if (! $streamed) {
            yield from json_decode(gzdecode(file_get_contents($file)), true, flags: JSON_THROW_ON_ERROR)['features'];
        }
    }

    /**
     * Tampilkan jumlah baris bila dijalankan dari perintah artisan.
     */
    private function report(string $table): void
    {
        if ($this->hasCommand) {
            $this->command->line(sprintf('  %s: %s', $table, number_format(DB::table($table)->count())));
        }
    }

    /**
     * Isi tabel dari CSV gzip. Tabel berkode di-upsert per kode; tabel tanpa kode diisi ulang.
     *
     * @return array<string, true> kode yang ada di data
     */
    private function syncCsv(string $table, string $path, bool $keyed): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $handle = @fopen('compress.zlib://'.$path, 'r') ?: throw new RuntimeException("Tidak bisa membuka {$path}.");
        $header = fgetcsv($handle, escape: '');

        if (! $keyed) {
            DB::table($table)->delete();
        }

        $chunk = max(1, intdiv(self::MAX_BINDINGS, count(array_intersect($header, array_keys($columns)))));
        $seen = [];
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $row = array_intersect_key(array_combine($header, $values), $columns);
            $rows[] = array_map(fn (string $value) => $value === '' ? null : $value, $row);
            if ($keyed) {
                $seen[$row['code']] = true;
            }

            if (count($rows) === $chunk) {
                $this->write($table, $rows, $keyed);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->write($table, $rows, $keyed);
        }

        fclose($handle);

        return $seen;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     */
    private function write(string $table, array $rows, bool $keyed): void
    {
        $keyed
            ? DB::table($table)->upsert($rows, ['code'], array_values(array_diff(array_keys($rows[0]), ['code'])))
            : DB::table($table)->insert($rows);
    }

    /**
     * Hapus kode yang tidak ada lagi di data, misalnya wilayah yang dimekarkan atau digabung.
     *
     * @param  array<string, true>  $seen
     */
    private function deleteMissing(string $table, array $seen): void
    {
        $missing = DB::table($table)->pluck('code')->reject(fn (string $code) => isset($seen[$code]))->values()->all();

        foreach (array_chunk($missing, 500) as $codes) {
            DB::table($table)->whereIn('code', $codes)->delete();
        }
    }
}
