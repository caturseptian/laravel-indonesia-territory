<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Model $resource
 */
abstract class TerritoryResource extends JsonResource
{
    /**
     * Koordinat hanya muncul bila kolomnya di-install.
     *
     * @return array<string, mixed>
     */
    protected function coordinates(): array
    {
        if (! array_key_exists('latitude', $this->resource->getAttributes())) {
            return [];
        }

        return $this->resource->only(['latitude', 'longitude', 'spatial_status', 'bbox']);
    }
}
