<?php

namespace CaturSeptian\IndonesiaTerritory;

use Illuminate\Support\ServiceProvider;

class IndonesiaTerritoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/territory.php', 'territory');
    }
}
