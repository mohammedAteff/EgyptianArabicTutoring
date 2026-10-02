<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentEmailVerificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEmailVerification extends Model
{
    /** @use HasFactory<StudentEmailVerificationFactory> */
    use HasFactory;

    protected static function newFactory(): StudentEmailVerificationFactory
    {
        return StudentEmailVerificationFactory::new();
    }

    protected $fillable = ['student_id', 'email_normalized', 'token_hash', 'expires_at', 'consumed_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
