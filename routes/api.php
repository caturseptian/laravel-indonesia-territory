<?php

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\DistrictController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\ProvinceController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\RegencyController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\VillageController;
use Illuminate\Support\Facades\Route;

// Hanya level yang di-install yang mendapat route.
$depth = Level::from(config('territory.level', 'village'))->depth();

Route::get('provinces', [ProvinceController::class, 'index']);
Route::get('provinces/{province}', [ProvinceController::class, 'show']);

if ($depth >= Level::Regency->depth()) {
    Route::get('provinces/{province}/regencies', [ProvinceController::class, 'regencies']);
    Route::get('regencies/{regency}', [RegencyController::class, 'show']);
}

if ($depth >= Level::District->depth()) {
    Route::get('regencies/{regency}/districts', [RegencyController::class, 'districts']);
    Route::get('districts/{district}', [DistrictController::class, 'show']);
}

if ($depth >= Level::Village->depth()) {
    Route::get('districts/{district}/villages', [DistrictController::class, 'villages']);
    Route::get('villages', [VillageController::class, 'index']);
    Route::get('villages/{village}', [VillageController::class, 'show']);
}
