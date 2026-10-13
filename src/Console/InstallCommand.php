<?php

namespace CaturSeptian\IndonesiaTerritory\Console;

use CaturSeptian\IndonesiaTerritory\Data\BoundaryDownloader;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use CaturSeptian\IndonesiaTerritory\Install\Installer;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Install\InvalidInstallOptions;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'territory:install
        {--levels= : Level terdalam: province, regency, district, village}
        {--with= : Data tambahan dipisah koma: postal, coordinates, islands, changes}
        {--boundaries= : Level boundary yang diunduh, dipisah koma}
        {--boundary-provinces= : Kode provinsi untuk boundary kecamatan/desa, dipisah koma, atau all}
        {--mode= : dependent atau eject}
        {--api : Aktifkan REST API hanya-baca}
        {--prefix= : Prefix nama tabel (default territory_)}
        {--migrate : Jalankan migrate dan isi data setelah install}
        {--force : Timpa berkas yang sudah ada dan izinkan jalan di production}';

    protected $description = 'Pasang data wilayah Indonesia sesuai pilihan (sekali, saat development)';

    public function handle(Installer $installer, DataRepository $data): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('territory:install dijalankan saat development, lalu hasilnya di-commit. Tambahkan --force bila memang perlu di production.');

            return self::FAILURE;
        }

        try {
            $options = InstallOptions::fromInput($this->input->isInteractive() ? $this->prompts($data) : $this->flags());
            $this->summary($options, $data);

            if ($this->input->isInteractive() && ! confirm('Lanjutkan?', default: true)) {
                $this->components->info('Dibatalkan, tidak ada berkas yang ditulis.');

                return self::SUCCESS;
            }

            $written = $installer->install($options, (bool) $this->option('force'));
            foreach ($written as $path) {
                $this->components->twoColumnDetail('ditulis', str_replace(base_path().'/', '', $path));
            }
        } catch (InstallationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->downloadBoundaries($options);

        $migration = 'database/migrations/'.basename($written[0]);
        $migrated = $this->alreadyMigrated($written[0]);

        if ($this->option('migrate') || ($this->input->isInteractive() && confirm('Jalankan migrate dan isi data sekarang?', default: true))) {
            if ($migrated) {
                $this->rebuild($written[0]);
            }
            $this->call('migrate', ['--force' => true]);
            $options->mode === InstallOptions::EJECT
                ? $this->call('db:seed', ['--class' => 'Database\\Seeders\\TerritorySeeder', '--force' => true])
                : $this->call('territory:seed', ['--fresh' => true]);
        } elseif ($migrated) {
            $this->components->warn("Migration wilayah ({$migration}) sudah pernah dijalankan. Jalankan ulang perintah ini dengan --migrate untuk membangun ulang tabel wilayah; tabel aplikasi lain tidak disentuh.");
        }

        $this->nextSteps($options);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function flags(): array
    {
        $level = $this->option('levels') ?? 'village';

        return [
            'levels' => $level,
            'with' => $this->option('with') ?? ($level === 'village' ? 'postal,coordinates' : 'coordinates'),
            'boundaries' => $this->option('boundaries'),
            'boundary_provinces' => $this->option('boundary-provinces'),
            'mode' => $this->option('mode'),
            'api' => $this->option('api'),
            'prefix' => $this->option('prefix'),
        ];
    }

    /**
     * Tanyakan hanya opsi yang tidak diberikan lewat flag.
     *
     * @return array<string, mixed>
     */
    private function prompts(DataRepository $data): array
    {
        $levels = array_combine(
            array_map(fn (Level $level) => $level->value, Level::cases()),
            array_map(fn (Level $level) => $level->label(), Level::cases()),
        );
        $levelValue = $this->option('levels') ?? select(label: 'Sampai level wilayah mana?', options: $levels, default: 'village');
        $level = Level::tryFrom($levelValue) ?? throw new InvalidInstallOptions('Level tidak dikenal. Pilihan: province, regency, district, village.');

        $extras = array_filter([
            'postal' => $level === Level::Village ? 'Kode pos' : null,
            'coordinates' => 'Koordinat dan bbox',
            'islands' => $level->depth() >= Level::Regency->depth() ? 'Pulau' : null,
            'changes' => 'Riwayat perubahan kode 2017-2025',
        ]);
        $with = $this->option('with') ?? implode(',', multiselect(
            label: 'Data tambahan apa saja?',
            options: $extras,
            default: array_values(array_intersect(['postal', 'coordinates'], array_keys($extras))),
        ));

        $boundaries = $this->option('boundaries') ?? implode(',', multiselect(
            label: 'Unduh boundary (poligon) untuk level?',
            options: $this->boundaryChoices($level, $data),
            hint: 'Kosongkan bila tidak perlu. Ukuran untuk semua provinsi.',
        ));
        $needsProvinces = array_intersect(explode(',', $boundaries), ['district', 'village']) !== [];
        $provinces = $this->option('boundary-provinces') ?? ($needsProvinces
            ? text(label: 'Boundary kecamatan/desa untuk provinsi mana?', default: 'all', hint: 'Kode dipisah koma, misalnya 31,32, atau all.')
            : 'all');

        return [
            'levels' => $levelValue,
            'with' => $with,
            'boundaries' => $boundaries,
            'boundary_provinces' => $provinces,
            'mode' => $this->option('mode') ?? select(label: 'Mode?', options: [
                'dependent' => 'Dependent: package tetap di vendor',
                'eject' => 'Eject: salin ke aplikasi, package boleh dihapus',
            ], default: 'dependent'),
            'api' => $this->option('api') || confirm('Aktifkan REST API hanya-baca?', default: false),
            'prefix' => $this->option('prefix') ?? text(label: 'Prefix nama tabel', default: 'territory_'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function boundaryChoices(Level $level, DataRepository $data): array
    {
        $bytes = [];
        foreach ($data->manifest()['boundaries'] as $file) {
            $bytes[$file['level']] = ($bytes[$file['level']] ?? 0) + $file['bytes'];
        }

        $choices = [];
        foreach ($level->withAncestors() as $choice) {
            $choices[$choice->value] = sprintf('%s (%s)', $choice->label(), $this->megabytes($bytes[$choice->value] ?? 0));
        }

        return $choices;
    }

    private function summary(InstallOptions $options, DataRepository $data): void
    {
        $files = $data->manifest()['files'];

        $this->components->info('Ringkasan');
        foreach ($options->tables() as $table) {
            $this->components->twoColumnDetail($options->prefix.$table, isset($files[$table]) ? number_format($files[$table]['rows']).' baris' : 'dari boundary');
        }

        if ($options->boundaryLevels !== []) {
            $plan = $this->laravel->make(BoundaryDownloader::class)->plan($options->boundaryLevels, $options->boundaryProvinces);
            $this->components->twoColumnDetail('Unduhan boundary', count($plan).' berkas, '.$this->megabytes(array_sum(array_column($plan, 'bytes'))));
        }

        $this->components->twoColumnDetail('Mode', $options->mode);
    }

    private function downloadBoundaries(InstallOptions $options): void
    {
        if ($options->boundaryLevels === []) {
            return;
        }

        $downloader = $this->laravel->make(BoundaryDownloader::class);
        $this->components->info('Mengunduh boundary');

        try {
            $downloader->download(
                $downloader->plan($options->boundaryLevels, $options->boundaryProvinces),
                fn (array $file, string $status) => $this->components->twoColumnDetail($file['path'], $status),
            );
        } catch (InstallationException $e) {
            $this->components->warn($e->getMessage());
        }
    }

    private function nextSteps(InstallOptions $options): void
    {
        if ($options->mode !== InstallOptions::EJECT) {
            return;
        }

        $this->components->info('Eject selesai. Berkas di atas tidak lagi bergantung pada package; commit ke repo aplikasi, lalu hapus package:');
        $this->line('  composer remove caturseptian/laravel-indonesia-territory');
        $this->newLine();
        $this->line('  Supaya php artisan migrate --seed di production ikut mengisi data wilayah, tambahkan di database/seeders/DatabaseSeeder.php:');
        $this->line('  $this->call(TerritorySeeder::class);');

        if ($options->api) {
            $this->newLine();
            $this->line('  Daftarkan route API di bootstrap/app.php:');
            $this->line('  use Illuminate\\Support\\Facades\\Route;');
            $this->line("  ->withRouting(..., then: fn () => Route::middleware(['api', 'throttle:60,1'])->prefix('api/territory')->group(base_path('routes/territory.php')))");
        }
    }

    /**
     * Hapus tabel wilayah lewat down() migration yang baru ditulis (mencakup tabel dari pilihan sebelumnya),
     * lalu hapus catatannya supaya migrate membuatnya lagi. Migration aplikasi lain tidak disentuh.
     */
    private function rebuild(string $path): void
    {
        $this->components->task('Menghapus tabel wilayah lama', function () use ($path) {
            $this->laravel['files']->getRequire($path)->down();
            $this->laravel['migrator']->getRepository()->delete((object) ['migration' => basename($path, '.php')]);
        });
    }

    /**
     * Apakah migration ini sudah tercatat di tabel migrations.
     */
    private function alreadyMigrated(string $path): bool
    {
        $repository = $this->laravel['migrator']->getRepository();

        return $repository->repositoryExists() && in_array(basename($path, '.php'), $repository->getRan(), true);
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1_000_000, 1).' MB';
    }
}
