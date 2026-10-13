<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Seeding\TerritorySeeder;
use CaturSeptian\IndonesiaTerritory\Tests\Fixtures;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

    public function test_fresh_mempertahankan_baris_yang_direferensikan_aplikasi(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village));
        $this->artisan('territory:seed')->assertSuccessful();
        // SQLite di Testbench tidak menegakkan foreign key secara default; MySQL dan PostgreSQL selalu menegakkannya.
        Schema::enableForeignKeyConstraints();
        Schema::create('addresses', function ($table) {
            $table->id();
            $table->string('village_code', 13);
            $table->foreign('village_code')->references('code')->on('territory_villages');
        });
        DB::table('addresses')->insert(['village_code' => '31.71.01.1001']);
        $count = DB::table('territory_villages')->count();

        $this->artisan('territory:seed', ['--fresh' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('addresses')->count());
        $this->assertSame($count, DB::table('territory_villages')->count());
    }

    public function test_fresh_menghapus_kode_yang_tidak_ada_lagi_di_data(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village));
        $this->artisan('territory:seed')->assertSuccessful();
        DB::table('territory_villages')->insert(['code' => '31.71.01.1999', 'district_code' => '31.71.01', 'type' => 'kelurahan', 'name' => 'Lama']);

        $this->artisan('territory:seed', ['--fresh' => true])->assertSuccessful();

        $this->assertFalse(DB::table('territory_villages')->where('code', '31.71.01.1999')->exists());
    }

    public function test_memperingatkan_bila_boundary_dikonfigurasi_tetapi_belum_diunduh(): void
    {
        $this->useFixtureData();
        config(['territory.boundaries.levels' => ['province'], 'territory.boundary_path' => $this->workspace.'/kosong']);
        $this->migrate(new InstallOptions(Level::Province, boundaryLevels: [Level::Province]));

        $this->artisan('territory:seed')->expectsOutputToContain('territory:boundaries')->assertSuccessful();
    }

    public function test_boundary_besar_diisi_tanpa_memuat_seluruh_berkas_ke_memori(): void
    {
        $this->useFixtureData();
        File::ensureDirectoryExists($path = $this->workspace.'/boundaries');
        $ring = json_encode(array_map(fn (int $i) => [106 + $i / 1000, -6 - $i / 1000], range(0, 500)));
        $handle = fopen('compress.zlib://'.$path.'/villages-31.geojson.gz', 'w');
        fwrite($handle, '{"type":"FeatureCollection","features":['."\n");
        for ($i = 1; $i <= 2000; $i++) {
            $code = sprintf('31.71.01.%04d', 1000 + $i);
            fwrite($handle, '{"type":"Feature","id":"'.$code.'","properties":{"code":"'.$code.'","level":"village"},"geometry":{"type":"Polygon","coordinates":[['.$ring.','.$ring.']]}}'.($i < 2000 ? ',' : '')."\n");
        }
        fwrite($handle, ']}'."\n");
        fclose($handle);
        config(['territory.boundary_path' => $path]);
        $this->migrate(new InstallOptions(Level::Province, boundaryLevels: [Level::Province]));

        $before = memory_get_usage();
        memory_reset_peak_usage();
        (new TerritorySeeder)->seedBoundaries();

        $this->assertSame(2000, DB::table('territory_boundaries')->count());
        $this->assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function test_boundary_satu_baris_tetap_terbaca(): void
    {
        $this->useFixtureData();
        File::ensureDirectoryExists($path = $this->workspace.'/boundaries');
        file_put_contents($path.'/provinces.geojson.gz', gzencode('{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"code":"31","level":"province"},"geometry":{"type":"Point","coordinates":[106.8,-6.2]}}]}'));
        config(['territory.boundary_path' => $path]);
        $this->migrate(new InstallOptions(Level::Province, boundaryLevels: [Level::Province]));

        (new TerritorySeeder)->seedBoundaries();

        $this->assertSame(1, DB::table('territory_boundaries')->count());
    }

    public function test_boundary_hanya_dari_level_dan_provinsi_yang_dikonfigurasi(): void
    {
        $this->useFixtureData();
        File::ensureDirectoryExists($path = $this->workspace.'/boundaries');
        $collection = fn (string $level, string $code) => gzencode('{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"code":"'.$code.'","level":"'.$level.'"},"geometry":{"type":"Point","coordinates":[106.8,-6.2]}}]}');
        file_put_contents($path.'/provinces.geojson.gz', $collection('province', '31'));
        file_put_contents($path.'/villages-31.geojson.gz', $collection('village', '31.71.01.1001'));
        file_put_contents($path.'/villages-32.geojson.gz', $collection('village', '32.04.01.2001'));
        file_put_contents($path.'/regencies.geojson.gz', $collection('regency', '31.71'));
        config(['territory.boundary_path' => $path, 'territory.boundaries.levels' => ['province', 'village'], 'territory.boundaries.provinces' => ['31']]);
        $this->migrate(new InstallOptions(Level::Village, boundaryLevels: [Level::Province, Level::Village]));

        (new TerritorySeeder)->seedBoundaries();

        $this->assertEqualsCanonicalizing(['31', '31.71.01.1001'], DB::table('territory_boundaries')->pluck('code')->all());
    }
}
