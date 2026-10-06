<?php

namespace Database\Factories\Domains\System\Models;

use App\Domains\System\Models\DevelopmentDataOperation;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DevelopmentDataOperation>
 */
class DevelopmentDataOperationFactory extends Factory
{
    protected $model = DevelopmentDataOperation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'administrator_id' => AdministratorFactory::new()->state(['role' => 'super_admin']),
            'token_hash' => hash('sha256', Str::random(64)), 'session_binding' => hash('sha256', 'test-session'),
            'security_fingerprint' => hash('sha256', 'test-security'), 'type' => 'reset', 'domain' => 'analytics',
            'scope' => [], 'preview' => [], 'status' => 'pending', 'expires_at' => now('UTC')->addMinutes(5),
        ];
    }
}
