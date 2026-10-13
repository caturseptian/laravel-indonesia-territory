<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Controllers;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Http\Resources\DistrictResource;
use CaturSeptian\IndonesiaTerritory\Http\Resources\RegencyResource;
use CaturSeptian\IndonesiaTerritory\Models\Regency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegencyController
{
    public function show(Regency $regency): RegencyResource
    {
        $regency->load('province');

        if (Level::from(config('territory.level', 'village'))->depth() >= Level::District->depth()) {
            $regency->loadCount('districts');
        }

        return RegencyResource::make($regency);
    }

    public function districts(Regency $regency): AnonymousResourceCollection
    {
        return DistrictResource::collection($regency->districts()->orderBy('code')->get());
    }
}
