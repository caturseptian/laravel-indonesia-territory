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

        // wrap() menambahkan prefix tabel koneksi dan tanda kutip, jadi aman untuk prefix apa pun.
        $column = $query->getQuery()->getGrammar()->wrap($this->qualifyColumn('name'));

        $query->whereRaw("lower({$column}) like ? escape '!'", ["%{$escaped}%"]);
    }
}
