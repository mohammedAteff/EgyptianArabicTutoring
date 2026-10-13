<?php

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffNoteTemplate extends Model
{
    use HasFactory;

    protected $table = 'staff_note_templates';

    protected $fillable = [
        'title',
        'category',
        'body',
        'created_by',
    ];

    /**
     * @return BelongsTo<Administrator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }
}
