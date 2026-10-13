<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Tests\Fixtures;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SeederTest extends TestCase
{
    public function test_hanya_mengisi_tabel_yang_di_install(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Regency));

        $this->artisan('territory:seed')->assertSuccessful();

        $this->assertSame(1, DB::table('territory_provinces')->count());
        $this->assertSame(6, DB::table('territory_regencies')->count());
    }

    public function test_desa_dengan_kode_pos_dan_koordinat(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village, [InstallOptions::POSTAL, InstallOptions::COORDINATES]));

        $this->artisan('territory:seed')->assertSuccessful();

        $gambir = DB::table('territory_villages')->where('code', '31.71.01.1001')->first();
        $this->assertSame('Gambir', $gambir->name);
        $this->assertSame('kelurahan', $gambir->type);
        $this->assertSame('10110', $gambir->postal_code);
        $this->assertNotNull($gambir->latitude);
        $this->assertIsArray(json_decode($gambir->bbox, true));
    }

    public function test_menolak_mengisi_ulang_tanpa_fresh_dan_stabil_dengan_fresh(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village, InstallOptions::EXTRAS));
        $this->artisan('territory:seed')->assertSuccessful();
        $count = DB::table('territory_villages')->count();

        $this->artisan('territory:seed')->expectsOutputToContain('--fresh')->assertFailed();
        $this->artisan('territory:seed', ['--fresh' => true])->assertSuccessful();
        $this->artisan('territory:seed', ['--fresh' => true])->assertSuccessful();

        $this->assertSame($count, DB::table('territory_villages')->count());
        $this->assertGreaterThan(0, DB::table('territory_islands')->count());
    }

    public function test_gagal_bila_tabel_belum_dimigrasi(): void
    {
        $this->artisan('territory:seed')->expectsOutputToContain('php artisan migrate')->assertFailed();
    }

    public function test_memperingatkan_bila_versi_dataset_berbeda(): void
    {
        $this->useFixtureData();
        config(['territory.dataset_version' => 'kepmendagri-lama']);
        $this->migrate(new InstallOptions(Level::Province));

        $this->artisan('territory:seed')->expectsOutputToContain('kepmendagri-lama')->assertSuccessful();
    }

    public function test_nama_dengan_kutip_koma_dan_apostrof_tetap_utuh(): void
    {
        $name = 'Nama "Kutip", Koma \'Apostrof\'';
        File::ensureDirectoryExists($path = $this->workspace.'/data');
        Fixtures::writeCsv("{$path}/provinces.csv.gz", [
            ['code' => '31', 'name' => $name, 'latitude' => '', 'longitude' => '', 'spatial_status' => '', 'bbox' => ''],
        ]);
        config(['territory.data_path' => $path]);
        $this->migrate(new InstallOptions(Level::Province));

        $this->artisan('territory:seed')->assertSuccessful();

        $this->assertSame($name, DB::table('territory_provinces')->value('name'));
    }

    public function test_seluruh_data_package_sesuai_manifest(): void
    {
        $this->migrate(new InstallOptions(Level::Village, InstallOptions::EXTRAS));

        $this->artisan('territory:seed')->assertSuccessful();

        foreach (DataRepository::package()->manifest()['files'] as $name => $file) {
            $this->assertSame($file['rows'], DB::table("territory_{$name}")->count(), $name);
        }
    }
}
