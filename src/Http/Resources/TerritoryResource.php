<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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

        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'spatial_status' => $this->spatial_status,
            'bbox' => $this->bbox,
        ];
    }
}
