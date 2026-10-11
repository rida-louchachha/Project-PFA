<?php

namespace App\Services\Ai\Tools;

final class ToolOutcome
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $payload,
    ) {
    }

    public static function success(array $payload): self
    {
        return new self(true, $payload);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, ['error' => $code, 'message' => $message]);
    }

    public function toJson(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
