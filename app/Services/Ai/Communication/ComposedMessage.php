<?php

namespace App\Services\Ai\Communication;

final class ComposedMessage
{
    /**
     * @param  array<string, string>  $facts  Label => value, rendered from the database
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $greeting,
        public readonly ?string $personalLine,
        public readonly string $body,
        public readonly array $facts,
        public readonly string $actionLabel,
        public readonly string $actionUrl,
        public readonly string $source,
    ) {
    }

    /** Text stored in the in-app conversation. */
    public function chatText(): string
    {
        return trim($this->greeting . ' ' . ($this->personalLine ? $this->personalLine . ' ' : '') . $this->body);
    }
}
