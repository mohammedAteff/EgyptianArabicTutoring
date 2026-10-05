<?php

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSavedView extends Model
{
    protected $fillable = ['administrator_id', 'section', 'name', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
