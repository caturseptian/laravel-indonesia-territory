<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use CaturSeptian\IndonesiaTerritory\Models\Village;
use Illuminate\Http\Request;

/**
 * @mixin Village
 */
class VillageResource extends TerritoryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'district_code' => $this->district_code,
            'postal_code' => $this->when(array_key_exists('postal_code', $this->resource->getAttributes()), fn () => $this->postal_code),
            ...$this->coordinates(),
            'district' => DistrictResource::make($this->whenLoaded('district')),
        ];
    }
}
