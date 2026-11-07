<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class BankRequestException extends Exception
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode,
        string $message,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();

        return new self(
            status: $response->status(),
            errorCode: $body['error'],
            message: $body['message'],
            body: $body,
        );
    }

    public function isTransient(): bool
    {
        return $this->status >= 500;
    }
}
