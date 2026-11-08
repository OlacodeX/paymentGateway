<?php

namespace App\Http\Middleware;

use App\Enums\IdempotencyRecoveryPoint;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
                'error' => 'missing_idempotency_key',
            ], 400);
        }

        $existing = IdempotencyKey::where([
            'idempotency_key' => $key,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
        ])->first();

        if ($existing !== null && $existing->isFinished()) {
            return $this->replayResponse($existing);
        }

        if ($existing !== null) {
            $record = $existing;
        } else {
            try {
                $record = IdempotencyKey::create([
                    'idempotency_key' => $key,
                    'request_method' => $request->method(),
                    'request_path' => $request->path(),
                    'recovery_point' => IdempotencyRecoveryPoint::STARTED,
                    'locked_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = IdempotencyKey::where([
                    'idempotency_key' => $key,
                    'request_method' => $request->method(),
                    'request_path' => $request->path(),
                ])->first();

                if ($existing->isFinished()) {
                    return $this->replayResponse($existing);
                }

                $record = $existing;
            }
        }

        //proccess the request
        $response = $next($request);

        // runs after the request is processed
        $record->update([
            'recovery_point' => IdempotencyRecoveryPoint::FINISHED,
            'response_code' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
            'locked_at' => null,
        ]);

        return $response;
    }

    protected function replayResponse(IdempotencyKey $record): JsonResponse
    {
        return response()->json($record->response_body, $record->response_code)->header('X-Idempotent-Replayed', 'true');
    }
}
