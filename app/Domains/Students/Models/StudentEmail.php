<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEmail extends Model
{
    /** @use HasFactory<StudentEmailFactory> */
    use HasFactory;

    protected static function newFactory(): StudentEmailFactory
    {
        return StudentEmailFactory::new();
    }

    protected $fillable = ['student_id', 'email_normalized', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
