<?php

namespace App\Domains\Administration\Models;

use Database\Factories\StaffNotePreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffNotePreference extends Model
{
    use HasFactory;

    protected $fillable = ['administrator_id', 'staff_bin_id', 'pinned', 'favorite'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'favorite' => 'boolean'];
    }

    protected static function newFactory(): StaffNotePreferenceFactory
    {
        return StaffNotePreferenceFactory::new();
    }
}
