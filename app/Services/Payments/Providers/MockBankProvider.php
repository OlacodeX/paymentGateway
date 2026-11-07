<?php

namespace App\Services\Payments\Providers;

use App\Exceptions\BankRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class MockBankProvider extends Provider
{
    /**
     * @param  array{card_number: string, cvv: string, expiry_month: int, expiry_year: int, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function authorize(array $payload, string $idempotencyKey): array
    {
        return $this->send('post', '/api/v1/authorizations', $payload, $idempotencyKey);
    }

    /**
     * @param  array{authorization_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function capture(array $payload, string $idempotencyKey): array
    {
        return $this->send('post', '/api/v1/captures', $payload, $idempotencyKey);
    }

    /**
     * @param  array{authorization_id: string}  $payload
     * @return array<string, mixed>
     */
    public function void(array $payload, string $idempotencyKey): array
    {
        return $this->send('post', '/api/v1/voids', $payload, $idempotencyKey);
    }

    /**
     * @param  array{capture_id: string, amount: int}  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload, string $idempotencyKey): array
    {
        return $this->send('post', '/api/v1/refunds', $payload, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthorization(string $authorizationId): array
    {
        return $this->send('get', "/api/v1/authorizations/{$authorizationId}");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        $attempts = config('services.bank.retry_times', 3);
        $sleepMs = config('services.bank.retry_sleep_ms', 200);

        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = $this->client($idempotencyKey);
                $response = $method === 'get'
                    ? $request->get($path)
                    : $request->{$method}($path, $payload);
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt === $attempts) {
                    throw new BankRequestException(
                        status: 0,
                        errorCode: 'connection_error',
                        message: $exception->getMessage(),
                    );
                }

                Sleep::usleep($this->backoffMicroseconds($attempt, $sleepMs));

                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->serverError() && $attempt < $attempts) {
                Sleep::usleep($this->backoffMicroseconds($attempt, $sleepMs));

                continue;
            }

            throw BankRequestException::fromResponse($response);
        }

        throw new BankRequestException(
            status: 0,
            errorCode: 'connection_error',
            message: $lastException?->getMessage() ?? 'Bank request failed after retries.',
        );
    }

    protected function client(?string $idempotencyKey = null): PendingRequest
    {
        $request = Http::baseUrl(config('services.bank.base_url'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(config('services.bank.connect_timeout', 3))
            ->timeout(config('services.bank.timeout', 15));

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ]);
        }

        return $request;
    }

    protected function backoffMicroseconds(int $attempt, int $baseSleepMs): int
    {
        $jitterMs = random_int(0, 100);

        return ($baseSleepMs * $attempt + $jitterMs) * 1000;
    }
}
