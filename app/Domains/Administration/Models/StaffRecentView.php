<?php

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRecentView extends Model
{
    public $timestamps = false;

    protected $fillable = ['administrator_id', 'entity_type', 'entity_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'immutable_datetime'];
    }
}
