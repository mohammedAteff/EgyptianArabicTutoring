<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentPortalLoginFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPortalLogin extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_id', 'timezone', 'authenticated_at'];

    protected static function newFactory(): StudentPortalLoginFactory
    {
        return StudentPortalLoginFactory::new();
    }

    protected function casts(): array
    {
        return ['authenticated_at' => 'immutable_datetime'];
    }
}
