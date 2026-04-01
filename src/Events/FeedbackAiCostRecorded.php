<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class FeedbackAiCostRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly ?int $userId,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly string $feedbackType,
        public readonly string $conversationId,
        public readonly CarbonImmutable $createdAt,
    ) {}
}
