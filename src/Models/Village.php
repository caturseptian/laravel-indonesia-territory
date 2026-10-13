<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Enums\VillageType;
use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kelurahan/desa, kode "NN.NN.NN.NNNN" (1xxx kelurahan, 2xxx desa, 3xxx desa adat).
 */
class Village extends Model
{
    use SearchesByName;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('villages');
    }

    protected function casts(): array
    {
        return ['type' => VillageType::class, 'latitude' => 'float', 'longitude' => 'float', 'bbox' => 'array'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code');
    }

    public function boundary(): HasOne
    {
        return $this->hasOne(Boundary::class, 'code')->where('level', 'village');
    }
}
