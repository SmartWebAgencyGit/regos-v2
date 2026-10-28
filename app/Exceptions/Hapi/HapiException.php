<?php

namespace App\Exceptions\Hapi;

use Exception;

/**
 * A failed HAPI call. The status is the HTTP status (0 for transport
 * errors) and the body is the decoded JSON error response, when present.
 */
class HapiException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Field validation errors returned by HAPI with a 400 response.
     *
     * @return array<string, mixed>
     */
    public function validationErrors(): array
    {
        if ($this->status !== 400) {
            return [];
        }

        return (array) ($this->body['errors'] ?? []);
    }
}
