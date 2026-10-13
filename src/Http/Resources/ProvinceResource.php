<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Resources;

use Illuminate\Http\Request;

class ProvinceResource extends TerritoryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            ...$this->coordinates(),
            'regencies_count' => $this->whenCounted('regencies'),
        ];
    }
}
