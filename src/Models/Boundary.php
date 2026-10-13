<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;

/**
 * Poligon batas wilayah BIG. Kunci gabungan (level, code); geometry berupa geometry GeoJSON.
 */
class Boundary extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('boundaries');
    }

    protected function casts(): array
    {
        return ['geometry' => 'array'];
    }
}
