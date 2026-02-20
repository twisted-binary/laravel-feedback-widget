<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Contracts\IssueServiceInterface;
use TwistedBinary\FeedbackWidget\Data\FeedbackChatResult;

describe('POST /feedback/chat', function (): void {
    it('requires authentication', function (): void {
        $response = $this->postJson(route('feedback.chat'), [
            'message' => 'Hello',
            'type' => 'bug',
        ]);

        $response->assertUnauthorized();
    });

    it('validates the request', function (array $data, string $errorField): void {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson(route('feedback.chat'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors($errorField);
    })->with([
        'missing message' => [['type' => 'bug'], 'message'],
        'missing type' => [['message' => 'Hello'], 'type'],
        'invalid type' => [['message' => 'Hello', 'type' => 'invalid'], 'type'],
        'message too long' => [['message' => str_repeat('a', 2001), 'type' => 'bug'], 'message'],
        'history too large' => [['message' => 'Hi', 'type' => 'bug', 'history' => array_fill(0, 21, ['role' => 'user', 'content' => 'x'])], 'history'],
        'invalid history role' => [['message' => 'Hi', 'type' => 'bug', 'history' => [['role' => 'system', 'content' => 'x']]], 'history.0.role'],
        'history content too long' => [['message' => 'Hi', 'type' => 'bug', 'history' => [['role' => 'user', 'content' => str_repeat('a', 5001)]]], 'history.0.content'],
    ]);

    it('returns a chat reply', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->with('My button is broken', [], 'bug')
            ->andReturn(new FeedbackChatResult(
                reply: 'Can you describe what happens when you click the button?',
                isComplete: false,
            ));

        $response = $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'My button is broken',
            'type' => 'bug',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'reply' => 'Can you describe what happens when you click the button?',
            'done' => false,
            'structured' => null,
        ]);
    });

    it('returns structured data when conversation is complete', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Thank you! Creating your issue now...',
                isComplete: true,
                structuredData: ['title' => '[Bug] Button broken', 'body' => '## Steps\n1. Click button'],
            ));

        $response = $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Yes, that looks correct',
            'type' => 'bug',
            'history' => [
                ['role' => 'user', 'content' => 'Button broken'],
                ['role' => 'assistant', 'content' => 'Here is the summary...'],
            ],
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'done' => true,
            'structured' => ['title' => '[Bug] Button broken', 'body' => '## Steps\n1. Click button'],
        ]);
    });

    it('returns 500 when the service throws', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andThrow(new RuntimeException('OpenAI down'));

        $response = $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Hello',
            'type' => 'bug',
        ]);

        $response->assertInternalServerError();
        $response->assertJson(['error' => 'Something went wrong. Please try again.']);
    });
});

describe('POST /feedback/issue', function (): void {
    it('requires authentication', function (): void {
        $response = $this->postJson(route('feedback.issue'), [
            'title' => 'Test',
            'body' => 'Test body',
            'type' => 'bug',
        ]);

        $response->assertUnauthorized();
    });

    it('validates the request', function (array $data, string $errorField): void {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors($errorField);
    })->with([
        'missing title' => [['body' => 'Something', 'type' => 'bug'], 'title'],
        'missing body' => [['title' => 'Bug', 'type' => 'bug'], 'body'],
        'missing type' => [['title' => 'Bug', 'body' => 'Something'], 'type'],
        'invalid type' => [['title' => 'Bug', 'body' => 'Something', 'type' => 'invalid'], 'type'],
        'title too long' => [['title' => str_repeat('a', 256), 'body' => 'Something', 'type' => 'bug'], 'title'],
        'body too long' => [['title' => 'Bug', 'body' => str_repeat('a', 10001), 'type' => 'bug'], 'body'],
    ]);

    it('rejects a screenshot with invalid mime type', function (): void {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), [
            'title' => 'Test',
            'body' => 'Test body',
            'type' => 'bug',
            'screenshot' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshot');
    });

    it('rejects an oversized screenshot', function (): void {
        $user = $this->createAuthenticatedUser();

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), [
            'title' => 'Test',
            'body' => 'Test body',
            'type' => 'bug',
            'screenshot' => UploadedFile::fake()->image('large.png')->size(5121),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('screenshot');
    });

    it('creates a GitHub issue and returns its URL', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(IssueServiceInterface::class)
            ->expects('createIssue')
            ->withArgs(fn (string $title, string $body, string $type, Authenticatable $issueUser): bool => $title === '[Bug] Login broken'
                && $body === '## Details'
                && $type === 'bug')
            ->andReturn(['url' => 'https://github.com/owner/repo/issues/42', 'number' => 42]);

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), [
            'title' => '[Bug] Login broken',
            'body' => '## Details',
            'type' => 'bug',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'url' => 'https://github.com/owner/repo/issues/42',
            'number' => 42,
        ]);
    });

    it('returns 500 when the service throws', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(IssueServiceInterface::class)
            ->expects('createIssue')
            ->andThrow(new RuntimeException('GitHub API down'));

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), [
            'title' => 'Test',
            'body' => 'Test body',
            'type' => 'bug',
        ]);

        $response->assertInternalServerError();
        $response->assertJson(['error' => 'Failed to create the issue. Please try again.']);
    });

    it('uploads a screenshot and appends it to the issue body', function (): void {
        Storage::fake('public');
        $user = $this->createAuthenticatedUser();

        $this->mock(IssueServiceInterface::class)
            ->expects('createIssue')
            ->withArgs(fn (string $title, string $body, string $type, Authenticatable $issueUser): bool => $title === '[Bug] Upload test'
                && str_contains($body, '## Details')
                && str_contains($body, '![Screenshot](')
                && $type === 'bug')
            ->andReturn(['url' => 'https://github.com/owner/repo/issues/99', 'number' => 99]);

        $response = $this->actingAs($user)->post(route('feedback.issue'), [
            'title' => '[Bug] Upload test',
            'body' => '## Details',
            'type' => 'bug',
            'screenshot' => UploadedFile::fake()->image('screenshot.png', 800, 600),
        ]);

        $response->assertSuccessful();
        $response->assertJson(['url' => 'https://github.com/owner/repo/issues/99', 'number' => 99]);

        $files = Storage::disk('public')->files('feedback-screenshots');
        expect($files)->toHaveCount(1);
    });

    it('creates an issue without a screenshot', function (): void {
        $user = $this->createAuthenticatedUser();

        $this->mock(IssueServiceInterface::class)
            ->expects('createIssue')
            ->withArgs(fn (string $title, string $body, string $type, Authenticatable $issueUser): bool => $title === '[Feature] No screenshot'
                && $body === '## Idea'
                && ! str_contains($body, '![Screenshot]')
                && $type === 'feature')
            ->andReturn(['url' => 'https://github.com/owner/repo/issues/50', 'number' => 50]);

        $response = $this->actingAs($user)->postJson(route('feedback.issue'), [
            'title' => '[Feature] No screenshot',
            'body' => '## Idea',
            'type' => 'feature',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['url' => 'https://github.com/owner/repo/issues/50', 'number' => 50]);
    });
});
