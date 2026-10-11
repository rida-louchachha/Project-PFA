<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Normalised provider failure (timeout, rate limit, invalid response, refusal...).
 * The message is safe to log; it never contains the API key or customer content.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'provider_error',
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
