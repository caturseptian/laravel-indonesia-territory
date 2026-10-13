<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use CaturSeptian\IndonesiaTerritory\Models\District;
use Illuminate\Http\Request;

/**
 * @mixin District
 */
class DistrictResource extends TerritoryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'regency_code' => $this->regency_code,
            ...$this->coordinates(),
            'villages_count' => $this->whenCounted('villages'),
            'regency' => RegencyResource::make($this->whenLoaded('regency')),
        ];
    }
}
