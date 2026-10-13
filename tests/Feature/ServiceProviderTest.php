<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_config_default_dimuat(): void
    {
        $this->assertSame('territory_', config('territory.prefix'));
        $this->assertSame('dependent', config('territory.mode'));
        $this->assertFalse(config('territory.api.enabled'));
    }

    public function test_workspace_menjadi_base_path(): void
    {
        $this->assertSame($this->workspace.'/database/migrations', database_path('migrations'));
        $this->assertDirectoryExists(app_path());
    }
}
