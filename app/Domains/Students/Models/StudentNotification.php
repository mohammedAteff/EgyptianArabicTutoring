<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentNotification extends Model
{
    /** @use HasFactory<StudentNotificationFactory> */
    use HasFactory;

    protected $table = 'student_notifications';

    protected $fillable = ['student_id', 'deduplication_key', 'type', 'title', 'message', 'link', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
