<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pulau bernama dari Lampiran D Kepmendagri, kode "NN.NN.4NNNN" (NN.00.4NNNN: langsung di bawah provinsi).
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

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code');
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_code');
    }
}
