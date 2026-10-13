<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentBookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentBookmark extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'kind', 'target_id'];

    protected static function newFactory(): StudentBookmarkFactory
    {
        return StudentBookmarkFactory::new();
    }
}
