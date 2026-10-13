<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kecamatan (distrik di Papua), kode "NN.NN.NN".
 *
 * @property string $code
 * @property string $regency_code
 * @property string $name
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $spatial_status
 * @property array<int, float>|null $bbox
 */
class District extends Model
{
    use SearchesByName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('districts');
    }

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'bbox' => 'array'];
    }

    /**
     * @return BelongsTo<Regency, $this>
     */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code');
    }

    /**
     * @return HasMany<Village, $this>
     */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'district_code');
    }

    /**
     * @return HasOne<Boundary, $this>
     */
    public function boundary(): HasOne
    {
        return $this->hasOne(Boundary::class, 'code')->where('level', 'district');
    }
}
