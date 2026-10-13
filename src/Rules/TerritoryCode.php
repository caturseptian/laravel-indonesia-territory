<?php

namespace CaturSeptian\IndonesiaTerritory\Rules;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Territory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Kode wilayah dengan format yang benar untuk level tertentu, dan bila diminta, ada di database.
 */
class TerritoryCode implements ValidationRule
{
    public function __construct(
        private readonly Level $level,
        private readonly bool $exists = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $label = mb_strtolower($this->level->label());

        if (! is_string($value) || ! Territory::isValid($value, $this->level)) {
            $fail(":attribute bukan kode {$label} yang valid.");

            return;
        }

        if ($this->exists && ! DB::table(Territory::table($this->level->table()))->where('code', $value)->exists()) {
            $fail(":attribute tidak ditemukan di data {$label}.");
        }
    }
}
