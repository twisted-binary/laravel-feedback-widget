<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface IssueServiceInterface
{
    /**
     * @return array{url: string, number: int}
     */
    public function createIssue(string $title, string $body, string $type, Authenticatable $user): array;
}
