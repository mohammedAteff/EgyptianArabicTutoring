<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\FeedbackOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackOperation extends Model
{
    /** @use HasFactory<FeedbackOperationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result_sequence' => 'integer', 'result_version' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
