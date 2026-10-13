<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Controllers;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Http\Resources\ProvinceResource;
use CaturSeptian\IndonesiaTerritory\Http\Resources\RegencyResource;
use CaturSeptian\IndonesiaTerritory\Models\Province;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProvinceController
{
    public function index(): AnonymousResourceCollection
    {
        return ProvinceResource::collection(Province::query()->orderBy('code')->get());
    }

    public function show(Province $province): ProvinceResource
    {
        if (Level::from(config('territory.level', 'village'))->depth() >= Level::Regency->depth()) {
            $province->loadCount('regencies');
        }

        return ProvinceResource::make($province);
    }

    public function regencies(Province $province): AnonymousResourceCollection
    {
        return RegencyResource::collection($province->regencies()->orderBy('code')->get());
    }
}
