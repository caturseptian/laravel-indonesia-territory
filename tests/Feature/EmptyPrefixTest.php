<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Models\Province;
use CaturSeptian\IndonesiaTerritory\Models\Regency;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;

class EmptyPrefixTest extends TestCase
{
    public function test_model_bekerja_tanpa_prefix(): void
    {
        config(['territory.prefix' => '']);
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Regency, prefix: ''));

        $this->artisan('territory:seed')->assertSuccessful();

        $this->assertSame('provinces', (new Province)->getTable());
        $this->assertSame('31', Regency::find('31.71')->province->code);
    }

    public function test_pencarian_dengan_prefix_diawali_angka(): void
    {
        config(['territory.prefix' => '2025_']);
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Province, prefix: '2025_'));
        $this->artisan('territory:seed')->assertSuccessful();

        $this->assertSame(['31'], Province::search('jakarta')->pluck('code')->all());
    }
}
