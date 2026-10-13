<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Enums\RegencyType;
use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kabupaten/kota, kode "NN.NN" (01-69 kabupaten, 71-99 kota).
 */
class Regency extends Model
{
    use SearchesByName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('regencies');
    }

    protected function casts(): array
    {
        return ['type' => RegencyType::class, 'latitude' => 'float', 'longitude' => 'float', 'bbox' => 'array'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'regency_code');
    }

    public function islands(): HasMany
    {
        return $this->hasMany(Island::class, 'regency_code');
    }

    public function boundary(): HasOne
    {
        return $this->hasOne(Boundary::class, 'code')->where('level', 'regency');
    }
}
