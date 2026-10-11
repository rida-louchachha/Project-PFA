<?php

namespace App\Services\Ai\Intent;

final class IntentionResult
{
    public function __construct(
        public readonly string $intention,
        public readonly float $confidence,
        public readonly string $language,
        public readonly string $provider,
        public readonly bool $valid = true,
        public readonly bool $fallback = false,
    ) {
    }

    public static function none(string $provider, bool $valid = true, bool $fallback = false): self
    {
        return new self('none', 0.0, 'other', $provider, $valid, $fallback);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intention' => $this->intention,
            'confidence' => round($this->confidence, 2),
            'language' => $this->language,
            'provider' => $this->provider,
            'valid' => $this->valid,
            'fallback' => $this->fallback,
        ];
    }
}
