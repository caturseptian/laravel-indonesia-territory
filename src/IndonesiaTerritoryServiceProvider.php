<?php

namespace CaturSeptian\IndonesiaTerritory;

use CaturSeptian\IndonesiaTerritory\Console\SeedCommand;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use Illuminate\Support\ServiceProvider;

class IndonesiaTerritoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/territory.php', 'territory');

        $this->app->singleton(DataRepository::class, fn () => DataRepository::package());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SeedCommand::class]);
        }
    }
}
