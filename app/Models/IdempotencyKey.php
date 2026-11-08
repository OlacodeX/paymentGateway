<?php

namespace App\Models;

use App\Enums\IdempotencyRecoveryPoint;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'idempotency_key',
    'request_method',
    'request_path',
    'recovery_point',
    'response_code',
    'response_body',
    'locked_at',
])]
class IdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recovery_point' => IdempotencyRecoveryPoint::class,
            'response_body' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return $this->recovery_point === IdempotencyRecoveryPoint::FINISHED;
    }
}
