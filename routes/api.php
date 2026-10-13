<?php

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\DistrictController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\ProvinceController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\RegencyController;
use CaturSeptian\IndonesiaTerritory\Http\Controllers\VillageController;
use Illuminate\Support\Facades\Route;

// Hanya level yang di-install yang mendapat route.
$depth = Level::from(config('territory.level', 'village'))->depth();

// Kode yang tidak ada dijawab 404 tanpa menyebut nama kelas model, juga saat APP_DEBUG=false.
$missing = fn () => response()->json(['message' => 'Not Found.'], 404);

Route::get('provinces', [ProvinceController::class, 'index']);
Route::get('provinces/{province}', [ProvinceController::class, 'show'])->missing($missing);

if ($depth >= Level::Regency->depth()) {
    Route::get('provinces/{province}/regencies', [ProvinceController::class, 'regencies'])->missing($missing);
    Route::get('regencies/{regency}', [RegencyController::class, 'show'])->missing($missing);
}

if ($depth >= Level::District->depth()) {
    Route::get('regencies/{regency}/districts', [RegencyController::class, 'districts'])->missing($missing);
    Route::get('districts/{district}', [DistrictController::class, 'show'])->missing($missing);
}

if ($depth >= Level::Village->depth()) {
    Route::get('districts/{district}/villages', [DistrictController::class, 'villages'])->missing($missing);
    Route::get('villages', [VillageController::class, 'index']);
    Route::get('villages/{village}', [VillageController::class, 'show'])->missing($missing);
}
