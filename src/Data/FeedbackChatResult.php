<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Data;

final readonly class FeedbackChatResult
{
    /**
     * @param  array{title: string, body: string}|null  $structuredData
     */
    public function __construct(
        public string $reply,
        public bool $isComplete,
        public ?array $structuredData = null,
    ) {}
}
