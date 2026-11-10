<?php

namespace App\Domains\Reporting\Models;

use Database\Factories\Domains\Reporting\Models\TutorWorkEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorWorkEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = ['version' => 1, 'corrected' => false];

    protected $hidden = ['description', 'original_declaration'];

    protected static function newFactory(): TutorWorkEntryFactory
    {
        return TutorWorkEntryFactory::new();
    }

    protected function casts(): array
    {
        return ['workspace_id' => 'integer', 'created_by' => 'integer', 'active_workspace' => 'integer', 'original_declaration' => 'array', 'corrected' => 'boolean', 'version' => 'integer', 'unplaced_seconds' => 'integer'];
    }
}
