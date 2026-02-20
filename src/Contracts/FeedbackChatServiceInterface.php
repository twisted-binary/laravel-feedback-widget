<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Contracts;

use TwistedBinary\FeedbackWidget\Data\FeedbackChatResult;

interface FeedbackChatServiceInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(string $message, array $history, string $type): FeedbackChatResult;
}
