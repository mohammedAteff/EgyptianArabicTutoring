<?php

namespace App\Domains\Administration\Models;

use Database\Factories\StaffBinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffBin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['author_id', 'title', 'body'];

    protected static function newFactory(): StaffBinFactory
    {
        return StaffBinFactory::new();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'author_id')->withTrashed();
    }
}
