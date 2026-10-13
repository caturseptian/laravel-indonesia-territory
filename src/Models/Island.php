<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pulau bernama dari Lampiran D Kepmendagri, kode "NN.NN.4NNNN" (NN.00.4NNNN: langsung di bawah provinsi).
 *
 * @property string $code
 * @property string $name
 * @property string $province_code
 * @property string|null $regency_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $area_km2
 * @property bool|null $inhabited
 */
class Island extends Model
{
    use SearchesByName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('islands');
    }

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'area_km2' => 'float', 'inhabited' => 'boolean'];
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code');
    }

    /**
     * @return BelongsTo<Regency, $this>
     */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code');
    }
}
