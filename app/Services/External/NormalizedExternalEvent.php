<?php

namespace App\Services\External;

/**
 * Immutable DTO representing a normalized external service event.
 */
final class NormalizedExternalEvent
{
    public function __construct(
        public readonly string $service,
        public readonly string $eventType,
        public readonly string $messageId,
        public readonly ?string $fromName,
        public readonly ?string $message,
        public readonly ?string $amount,
        public readonly ?string $currency,
        public readonly array $templateTags,  // ['event.from_name' => ..., 'event.amount' => ...]
        public readonly array $raw,           // original decoded payload
    ) {}

    public function getService(): string { return $this->service; }
    public function getEventType(): string { return $this->eventType; }
    public function getMessageId(): string { return $this->messageId; }
    public function getFromName(): ?string { return $this->fromName; }
    public function getMessage(): ?string { return $this->message; }
    public function getAmount(): ?string { return $this->amount; }
    public function getCurrency(): ?string { return $this->currency; }
    public function getTemplateTags(): array { return $this->templateTags; }
    public function getRaw(): array { return $this->raw; }
}
