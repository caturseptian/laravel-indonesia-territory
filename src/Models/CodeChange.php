<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;

/**
 * Perubahan kode antar dokumen sumber (added, removed, renamed).
 */
class CodeChange extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return Territory::table('code_changes');
    }
}
