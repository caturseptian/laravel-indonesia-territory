<?php

namespace CaturSeptian\IndonesiaTerritory\Console;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Seeding\TerritorySeeder;
use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedCommand extends Command
{
    protected $signature = 'territory:seed {--fresh : Hapus isi tabel wilayah lalu isi ulang}';

    protected $description = 'Isi tabel wilayah dari data package';

    public function handle(DataRepository $data): int
    {
        $table = Territory::table('provinces');

        if (! Schema::hasTable($table)) {
            $this->components->error("Tabel {$table} belum ada. Jalankan php artisan migrate dulu.");

            return self::FAILURE;
        }
        if (! $this->option('fresh') && DB::table($table)->exists()) {
            $this->components->error('Tabel wilayah sudah berisi. Pakai --fresh untuk mengisi ulang.');

            return self::FAILURE;
        }

        $configured = config('territory.dataset_version');
        if ($configured !== null && $configured !== $data->datasetVersion()) {
            $this->components->warn("config/territory.php mencatat dataset {$configured}, sedangkan data package {$data->datasetVersion()}. Data package yang dipakai; perbarui dataset_version di config.");
        }

        return $this->call('db:seed', ['--class' => TerritorySeeder::class, '--force' => true]);
    }
}
