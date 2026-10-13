<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Controllers;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Http\Resources\DistrictResource;
use CaturSeptian\IndonesiaTerritory\Http\Resources\VillageResource;
use CaturSeptian\IndonesiaTerritory\Models\District;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DistrictController
{
    public function show(District $district): DistrictResource
    {
        $district->load('regency.province');

        if (Level::from(config('territory.level', 'village'))->depth() >= Level::Village->depth()) {
            $district->loadCount('villages');
        }

        return DistrictResource::make($district);
    }

    public function villages(District $district): AnonymousResourceCollection
    {
        return VillageResource::collection($district->villages()->orderBy('code')->get());
    }
}
