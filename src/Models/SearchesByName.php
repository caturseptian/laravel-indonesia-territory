<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use Illuminate\Database\Eloquent\Builder;

trait SearchesByName
{
    /**
     * Nama mengandung $term tanpa membedakan huruf besar/kecil. % dan _ dicari apa adanya.
     * Karakter escape "!" dipakai karena berlaku sama di SQLite, MySQL, dan PostgreSQL.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));

        $query->whereRaw("lower({$this->qualifyColumn('name')}) like ? escape '!'", ["%{$escaped}%"]);
    }
}
