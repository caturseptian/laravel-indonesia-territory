<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use CaturSeptian\IndonesiaTerritory\Models\Regency;
use Illuminate\Http\Request;

/**
 * @mixin Regency
 */
class RegencyResource extends TerritoryResource
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
            'province_code' => $this->province_code,
            ...$this->coordinates(),
            'districts_count' => $this->whenCounted('districts'),
            'province' => ProvinceResource::make($this->whenLoaded('province')),
        ];
    }
}
