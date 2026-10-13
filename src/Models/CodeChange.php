<?php

namespace CaturSeptian\IndonesiaTerritory\Models;

use CaturSeptian\IndonesiaTerritory\Territory;
use Illuminate\Database\Eloquent\Model;

/**
 * Perubahan kode antar dokumen sumber (added, removed, renamed).
 *
 * @property int $id
 * @property string $from_document
 * @property string $to_document
 * @property string $level
 * @property string $change
 * @property string $code
 * @property string|null $old_name
 * @property string|null $new_name
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
