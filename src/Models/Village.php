<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Enums\VillageType;
use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kelurahan/desa, kode "NN.NN.NN.NNNN" (1xxx kelurahan, 2xxx desa, 3xxx desa adat).
 *
 * @property string $code
 * @property string $district_code
 * @property VillageType $type
 * @property string $name
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $spatial_status
 * @property array<int, float>|null $bbox
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

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code');
    }

    /**
     * @return HasOne<Boundary, $this>
     */
    public function boundary(): HasOne
    {
        return $this->hasOne(Boundary::class, 'code')->where('level', 'village');
    }
}
