<?php

namespace CaturSeptian\IndonesiaTerritory\Tests;

use CaturSeptian\IndonesiaTerritory\IndonesiaTerritoryServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Base path sementara per test, supaya berkas hasil install tidak mengotori skeleton Testbench.
     */
    protected string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/territory-'.bin2hex(random_bytes(6));
        foreach (['app', 'config', 'database/migrations', 'database/seeders', 'routes', 'storage/logs'] as $directory) {
            File::ensureDirectoryExists("{$this->workspace}/{$directory}");
        }
        $this->app->setBasePath($this->workspace);

        Schema::dropAllTables();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [IndonesiaTerritoryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $connection = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', $connection === 'sqlite' ? 'testing' : $connection);
    }
}
