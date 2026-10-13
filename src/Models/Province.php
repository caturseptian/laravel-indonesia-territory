<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Provinsi, kode "NN" (contoh: 31 DKI Jakarta).
 */
class Province extends Model
{
    use SearchesByName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('provinces');
    }

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'bbox' => 'array'];
    }

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'province_code');
    }

    public function islands(): HasMany
    {
        return $this->hasMany(Island::class, 'province_code');
    }

    public function boundary(): HasOne
    {
        return $this->hasOne(Boundary::class, 'code')->where('level', 'province');
    }
}
