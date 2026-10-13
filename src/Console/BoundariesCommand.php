<?php

namespace CaturSeptian\IndonesiaTerritory\Console;

use CaturSeptian\IndonesiaTerritory\Data\BoundaryDownloader;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use CaturSeptian\IndonesiaTerritory\Seeding\TerritorySeeder;
use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BoundariesCommand extends Command
{
    protected $signature = 'territory:boundaries {--provinces= : Kode provinsi dipisah koma, atau all}';

    protected $description = 'Unduh atau lanjutkan unduhan boundary, lalu isi tabel boundaries';

    public function handle(BoundaryDownloader $downloader): int
    {
        $levels = array_map(fn (string $level) => Level::from($level), config('territory.boundaries.levels', []));
        if ($levels === []) {
            $this->components->error('Belum ada level boundary di config/territory.php. Jalankan territory:install --boundaries=province,... --force.');

            return self::FAILURE;
        }

        $option = $this->option('provinces');
        $provinces = $option === null ? config('territory.boundaries.provinces', 'all')
            : ($option === 'all' ? 'all' : array_values(array_filter(array_map('trim', explode(',', $option)), fn (string $code) => $code !== '')));

        try {
            $downloader->download(
                $downloader->plan($levels, $provinces),
                fn (array $file, string $status) => $this->components->twoColumnDetail($file['path'], $status),
            );
        } catch (InstallationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! Schema::hasTable(Territory::table('boundaries'))) {
            $this->components->info('Boundary sudah diunduh. Jalankan php artisan migrate lalu territory:seed untuk mengisinya.');

            return self::SUCCESS;
        }

        $this->laravel->make(TerritorySeeder::class)->setCommand($this)->seedBoundaries();

        return self::SUCCESS;
    }
}
