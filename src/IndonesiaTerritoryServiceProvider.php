<?php

namespace CaturSeptian\IndonesiaTerritory;

use CaturSeptian\IndonesiaTerritory\Console\BoundariesCommand;
use CaturSeptian\IndonesiaTerritory\Console\InstallCommand;
use CaturSeptian\IndonesiaTerritory\Console\SeedCommand;
use CaturSeptian\IndonesiaTerritory\Data\BoundaryDownloader;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IndonesiaTerritoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/territory.php', 'territory');

        $this->app->singleton(DataRepository::class, fn () => DataRepository::package());
        $this->app->bind(BoundaryDownloader::class, fn ($app) => new BoundaryDownloader(
            $app->make(DataRepository::class),
            config('territory.source.urls'),
            config('territory.boundary_path'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, SeedCommand::class, BoundariesCommand::class]);
        }

        if (config('territory.api.enabled') && config('territory.mode') !== 'eject') {
            Route::prefix(config('territory.api.prefix'))
                ->middleware(config('territory.api.middleware'))
                ->group(__DIR__.'/../routes/api.php');
        }
    }
}
