<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Enums\RegencyType;
use CaturSeptian\IndonesiaTerritory\Enums\VillageType;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Models\Boundary;
use CaturSeptian\IndonesiaTerritory\Models\CodeChange;
use CaturSeptian\IndonesiaTerritory\Models\District;
use CaturSeptian\IndonesiaTerritory\Models\Province;
use CaturSeptian\IndonesiaTerritory\Models\Regency;
use CaturSeptian\IndonesiaTerritory\Models\Village;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;

class ModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village, InstallOptions::EXTRAS, [Level::Province]));
        $this->artisan('territory:seed')->assertSuccessful();
    }

    public function test_relasi_dari_desa_sampai_provinsi(): void
    {
        $village = Village::find('31.71.01.1001');

        $this->assertSame('Gambir', $village->name);
        $this->assertSame(VillageType::Kelurahan, $village->type);
        $this->assertSame('31.71.01', $village->district->code);
        $this->assertSame(RegencyType::Kota, $village->district->regency->type);
        $this->assertSame('31', $village->district->regency->province->code);
        $this->assertSame(6, Province::find('31')->regencies()->count());
        $this->assertTrue(District::find('31.71.01')->villages->contains('code', '31.71.01.1001'));
    }

    public function test_cast_koordinat_dan_bbox(): void
    {
        $province = Province::find('31');

        $this->assertIsFloat($province->latitude);
        $this->assertCount(4, $province->bbox);
    }

    public function test_pencarian_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->assertTrue(Village::search('GAMBIR')->get()->contains('code', '31.71.01.1001'));
        $this->assertTrue(Village::search('gambir')->get()->contains('code', '31.71.01.1001'));
    }

    public function test_karakter_wildcard_dicari_apa_adanya(): void
    {
        $this->assertSame(0, Village::search('%')->count());
        $this->assertSame(0, Village::search('_')->count());
        $this->assertSame(0, Village::search('!')->count());
    }

    public function test_pulau_dan_riwayat_perubahan(): void
    {
        $this->assertGreaterThan(0, Regency::find('31.01')->islands()->count());
        $this->assertTrue(CodeChange::where('code', '31')->where('change', 'renamed')->exists());
    }

    public function test_boundary_terhubung_per_level(): void
    {
        Boundary::query()->insert([
            'level' => 'province',
            'code' => '31',
            'geometry' => json_encode(['type' => 'Point', 'coordinates' => [106.8, -6.2]]),
        ]);

        $this->assertSame('Point', Province::find('31')->boundary->geometry['type']);
        $this->assertNull(Regency::find('31.71')->boundary);
    }
}
