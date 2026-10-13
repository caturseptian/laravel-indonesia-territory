<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;

class ApiRegencyLevelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('territory.api.enabled', true);
        $app['config']->set('territory.level', 'regency');
    }

    public function test_hanya_mendaftarkan_route_level_yang_di_install(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Regency));
        $this->artisan('territory:seed')->assertSuccessful();

        $this->getJson('/api/territory/provinces/31/regencies')->assertOk();
        $this->getJson('/api/territory/regencies/31.71')->assertOk()->assertJsonMissingPath('data.districts_count');
        $this->getJson('/api/territory/regencies/31.71/districts')->assertNotFound();
        $this->getJson('/api/territory/villages?search=gambir')->assertNotFound();
    }
}
