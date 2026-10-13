<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;

class ApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('territory.api.enabled', true);
        $app['config']->set('territory.extras', ['postal', 'coordinates']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village, [InstallOptions::POSTAL, InstallOptions::COORDINATES]));
        $this->artisan('territory:seed')->assertSuccessful();
    }

    public function test_daftar_provinsi(): void
    {
        $this->getJson('/api/territory/provinces')
            ->assertOk()
            ->assertJsonPath('data.0.code', '31')
            ->assertJsonStructure(['data' => [['code', 'name', 'latitude', 'longitude', 'spatial_status', 'bbox']]]);
    }

    public function test_detail_provinsi_dengan_jumlah_anak(): void
    {
        $this->getJson('/api/territory/provinces/31')->assertOk()->assertJsonPath('data.regencies_count', 6);
    }

    public function test_turun_dari_provinsi_sampai_desa(): void
    {
        $this->getJson('/api/territory/provinces/31/regencies')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/territory/regencies/31.71/districts')->assertOk()->assertJsonPath('data.0.regency_code', '31.71');
        $this->getJson('/api/territory/districts/31.71.01/villages')->assertOk()->assertJsonFragment(['code' => '31.71.01.1001']);
    }

    public function test_detail_desa_dengan_induknya(): void
    {
        $this->getJson('/api/territory/villages/31.71.01.1001')
            ->assertOk()
            ->assertJsonPath('data.name', 'Gambir')
            ->assertJsonPath('data.type', 'kelurahan')
            ->assertJsonPath('data.postal_code', '10110')
            ->assertJsonPath('data.district.regency.province.code', '31');
    }

    public function test_cari_desa_berdasarkan_nama_dan_kode_pos(): void
    {
        $this->getJson('/api/territory/villages?search=GAMBIR')->assertOk()->assertJsonFragment(['code' => '31.71.01.1001']);
        $this->getJson('/api/territory/villages?postal_code=10110')->assertOk()->assertJsonFragment(['code' => '31.71.01.1001']);
        $this->getJson('/api/territory/villages?search=%25%25%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/territory/villages')->assertUnprocessable();
    }

    public function test_kode_tidak_dikenal_404_tanpa_membocorkan_kelas_internal(): void
    {
        foreach (['provinces/99', 'regencies/31.70', 'districts/31.71.99', 'villages/31.71.01.1999', 'provinces/99/regencies'] as $path) {
            $this->getJson("/api/territory/{$path}")->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
        }
    }
}
