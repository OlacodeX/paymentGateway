<?php

namespace App\Enums;

enum IdempotencyRecoveryPoint: string
{
    case STARTED = 'started';
    case FINISHED = 'finished';

    const DEFAULT = self::STARTED;

    public function title(): string
    {
        return match ($this) {
            self::STARTED => 'Started',
            self::FINISHED => 'Finished',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::STARTED => 'Request accepted and still processing',
            self::FINISHED => 'Request completed and response stored for replay', 
        };
    }
}
