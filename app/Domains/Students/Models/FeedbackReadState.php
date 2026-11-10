<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\FeedbackReadStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackReadState extends Model
{
    /** @use HasFactory<FeedbackReadStateFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_sequence' => 'integer'];
    }
}
