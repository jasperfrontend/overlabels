<?php

namespace App\Services\External;

use NumberFormatter;

/**
 * Immutable DTO representing a normalized external service event.
 */
final readonly class NormalizedExternalEvent
{
    public function __construct(
        public string $service,
        public string $eventType,
        public string $messageId,
        public ?string $fromName,
        public ?string $message,
        public ?string $amount,
        public ?string $currency,
        public array $templateTags,  // ['event.from_name' => ..., 'event.amount' => ...]
        public array $raw,           // payload-as-stored: PII already stripped by the driver
    ) {}

    public function getService(): string
    {
        return $this->service;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getTemplateTags(): array
    {
        return $this->templateTags;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Adds `event.formatted_amount`, derived from this event's own amount and
     * currency in the streamer's locale.
     *
     * It used to be StreamLabs' string, passed through verbatim by their driver
     * alone. That made it the one donation tag the other four services could
     * not offer, and it carried StreamLabs' formatting rather than the reader's
     * - "$13.37" whatever locale the streamer runs, when nl-NL writes "€ 45,00"
     * and fr-FR writes "45,00 €" for the same money. ICU knows both; StreamLabs
     * knows neither.
     *
     * Derived here rather than in each driver because a driver is handed a
     * payload and nothing else - it cannot know whose account the donation
     * landed in, so it cannot know the locale. Applied once before the event is
     * stored, so the stored `normalized_payload` carries it and the alert, the
     * events feed and a later replay all read the same string.
     *
     * The currency is the DONATION's, never the locale's: a dollar tip to a
     * Dutch streamer reads "US$ 13,37", Dutch punctuation on the money that
     * actually arrived. Anything without a numeric amount and a three-letter
     * code is left alone and the tag simply does not appear, which renders as
     * nothing.
     */
    public function withFormattedAmount(string $locale): self
    {
        if ($this->amount === null || ! is_numeric($this->amount)) {
            return $this;
        }

        if ($this->currency === null || ! preg_match('/^[A-Za-z]{3}$/', $this->currency)) {
            return $this;
        }

        $formatted = (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency((float) $this->amount, strtoupper($this->currency));

        if ($formatted === false) {
            return $this;
        }

        return new self(
            service: $this->service,
            eventType: $this->eventType,
            messageId: $this->messageId,
            fromName: $this->fromName,
            message: $this->message,
            amount: $this->amount,
            currency: $this->currency,
            templateTags: array_merge($this->templateTags, ['event.formatted_amount' => $formatted]),
            raw: $this->raw,
        );
    }
}
