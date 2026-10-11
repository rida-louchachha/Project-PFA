<?php

namespace App\Services\Ai;

final class ChatResult
{
    /**
     * @param  array{input_tokens?: int, output_tokens?: int}  $usage
     */
    public function __construct(
        public readonly ?string $text,
        public readonly string $stopReason = 'end_turn',
        public readonly array $usage = [],
        public readonly ?string $servedByModel = null,
    ) {
    }

    public function hasText(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }
}
