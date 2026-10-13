<?php

namespace CaturSeptian\IndonesiaTerritory\Http\Controllers;

use CaturSeptian\IndonesiaTerritory\Http\Resources\VillageResource;
use CaturSeptian\IndonesiaTerritory\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class VillageController
{
    /**
     * Cari kelurahan/desa berdasarkan nama atau kode pos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $postal = in_array('postal', config('territory.extras', []), true);
        $validated = $request->validate([
            'search' => [Rule::requiredIf(! $request->filled('postal_code')), 'string', 'min:3', 'max:100'],
            'postal_code' => [Rule::prohibitedIf(! $postal), 'digits:5'],
        ]);

        $villages = Village::query()
            ->with('district.regency.province')
            ->when($validated['search'] ?? null, fn (Builder $query, string $term) => $query->search($term))
            ->when($validated['postal_code'] ?? null, fn (Builder $query, string $code) => $query->where('postal_code', $code))
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return VillageResource::collection($villages);
    }

    public function show(Village $village): VillageResource
    {
        return VillageResource::make($village->load('district.regency.province'));
    }
}
