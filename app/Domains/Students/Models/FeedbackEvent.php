<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\FeedbackEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackEvent extends Model
{
    /** @use HasFactory<FeedbackEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'actor_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
