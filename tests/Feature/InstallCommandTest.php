<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Data\BoundaryDownloader;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class InstallCommandTest extends TestCase
{
    /**
     * Migrator menyimpan berkas migration yang sudah di-require selama proses berjalan. Di aplikasi nyata
     * setiap territory:install adalah proses baru; di test, dua install berjalan dalam satu proses.
     */
    private function forgetLoadedMigrations(): void
    {
        if (property_exists(Migrator::class, 'requiredPathCache')) {
            (new \ReflectionProperty(Migrator::class, 'requiredPathCache'))->setValue(null, []);
        }
    }

    public function test_non_interaktif_dependent_dengan_migrate(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--migrate' => true])
            ->expectsOutputToContain('territory_provinces')
            ->assertSuccessful();

        $this->assertSame(38, DB::table('territory_provinces')->count());
        $this->assertSame('province', config('territory.level'));
    }

    public function test_default_non_interaktif_memakai_kode_pos_dan_koordinat(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true])->assertSuccessful();

        $this->assertSame(['postal', 'coordinates'], (require config_path('territory.php'))['extras']);
    }

    public function test_default_tanpa_kode_pos_bila_level_bukan_desa(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'regency'])->assertSuccessful();

        $this->assertSame(['coordinates'], (require config_path('territory.php'))['extras']);
    }

    public function test_menolak_di_production_tanpa_force(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('territory:install', ['--no-interaction' => true])
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->assertFileDoesNotExist(config_path('territory.php'));
    }

    public function test_opsi_tidak_konsisten_ditolak_tanpa_menulis_berkas(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'district', '--with' => 'postal'])
            ->expectsOutputToContain('membutuhkan level village')
            ->assertFailed();

        $this->assertFileDoesNotExist(config_path('territory.php'));
    }

    public function test_provinsi_boundary_tidak_dikenal_ditolak_sebelum_menulis(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--boundaries' => 'village', '--boundary-provinces' => '31,98'])
            ->expectsOutputToContain('98')
            ->assertFailed();

        $this->assertFileDoesNotExist(config_path('territory.php'));
    }

    public function test_interaktif_konfirmasi_lalu_tulis(): void
    {
        $this->artisan('territory:install', ['--levels' => 'province', '--with' => '', '--boundaries' => '', '--mode' => 'dependent', '--prefix' => 'territory_'])
            ->expectsConfirmation('Aktifkan REST API hanya-baca?', 'no')
            ->expectsConfirmation('Lanjutkan?', 'yes')
            ->expectsConfirmation('Jalankan migrate dan isi data sekarang?', 'no')
            ->assertSuccessful();

        $this->assertFileExists(config_path('territory.php'));
    }

    public function test_interaktif_dibatalkan_tidak_menulis_apa_pun(): void
    {
        $this->artisan('territory:install', ['--levels' => 'province', '--with' => '', '--boundaries' => '', '--mode' => 'dependent', '--prefix' => 'territory_'])
            ->expectsConfirmation('Aktifkan REST API hanya-baca?', 'no')
            ->expectsConfirmation('Lanjutkan?', 'no')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(config_path('territory.php'));
    }

    public function test_unduhan_boundary_gagal_tetap_memasang_data_inti(): void
    {
        Http::fake(fn () => Http::response('', 503));
        $this->app->bind(BoundaryDownloader::class, fn ($app) => new BoundaryDownloader(
            $app->make(DataRepository::class),
            config('territory.source.urls'),
            config('territory.boundary_path'),
            retrySleepMs: 0,
        ));

        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--boundaries' => 'province', '--migrate' => true])
            ->expectsOutputToContain('territory:boundaries')
            ->assertSuccessful();

        $this->assertSame(38, DB::table('territory_provinces')->count());
    }

    public function test_eject_menampilkan_langkah_berikutnya(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--mode' => 'eject', '--api' => true])
            ->expectsOutputToContain('composer remove caturseptian/laravel-indonesia-territory')
            ->expectsOutputToContain('use Illuminate\\Support\\Facades\\Route;')
            ->expectsOutputToContain("base_path('routes/territory.php')")
            ->assertSuccessful();
    }

    public function test_install_ulang_dengan_force_mengganti_skema_yang_sudah_dimigrasi(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--with' => '', '--migrate' => true])->assertSuccessful();

        $this->forgetLoadedMigrations();
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'regency', '--with' => '', '--force' => true, '--migrate' => true])
            ->assertSuccessful();

        $this->assertSame(514, DB::table('territory_regencies')->count());
        $this->assertCount(1, glob(database_path('migrations/*_create_territory_tables.php')));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_install_ulang_tanpa_migrate_memberi_perintah_refresh(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--with' => '', '--migrate' => true])->assertSuccessful();

        $this->forgetLoadedMigrations();
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'regency', '--with' => '', '--force' => true])
            ->expectsOutputToContain('migrate:refresh --path=database/migrations/')
            ->assertSuccessful();

        $path = basename(glob(database_path('migrations/*_create_territory_tables.php'))[0]);
        $this->artisan('migrate:refresh', ['--path' => "database/migrations/{$path}", '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('territory_regencies'));
    }

    public function test_eject_mengingatkan_memanggil_seeder_dari_database_seeder(): void
    {
        $this->artisan('territory:install', ['--no-interaction' => true, '--levels' => 'province', '--mode' => 'eject'])
            ->expectsOutputToContain('$this->call(TerritorySeeder::class);')
            ->assertSuccessful();
    }
}
