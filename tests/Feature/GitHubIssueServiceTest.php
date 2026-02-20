<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use TwistedBinary\FeedbackWidget\Services\GitHubIssueService;

function createFakeUser(int $id = 1): Authenticatable
{
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($id);

    return $user;
}

beforeEach(function (): void {
    $rsaKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($rsaKey, $pemKey);

    config()->set('feedback-widget.github.app_id', '123456');
    config()->set('feedback-widget.github.private_key', base64_encode((string) $pemKey));
    config()->set('feedback-widget.github.installation_id', '789');
    config()->set('feedback-widget.github.repo_owner', 'test-owner');
    config()->set('feedback-widget.github.repo_name', 'test-repo');
    config()->set('feedback-widget.github.feedback_label', 'user-feedback');

    $this->service = new GitHubIssueService;
});

it('creates a GitHub issue via the API', function (): void {
    Http::fake([
        'https://api.github.com/app/installations/789/access_tokens' => Http::response([
            'token' => 'ghs_fake_installation_token',
        ], 201),
        'https://api.github.com/repos/test-owner/test-repo/issues' => Http::response([
            'html_url' => 'https://github.com/test-owner/test-repo/issues/1',
            'number' => 1,
        ], 201),
    ]);

    $user = createFakeUser(42);

    $result = $this->service->createIssue('[Bug] Login broken', '## Steps', 'bug', $user);

    expect($result)
        ->toBe([
            'url' => 'https://github.com/test-owner/test-repo/issues/1',
            'number' => 1,
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/test-owner/test-repo/issues'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer ghs_fake_installation_token')
        && $request['title'] === '[Bug] Login broken'
        && str_contains((string) $request['body'], '## Steps')
        && str_contains((string) $request['body'], 'user 42')
        && in_array('user-feedback', $request['labels'])
        && in_array('bug', $request['labels']));
});

it('uses cached installation token without fetching a new one', function (): void {
    Cache::put('github_app_installation_token', 'ghs_cached_token', 3000);

    Http::fake([
        'https://api.github.com/repos/test-owner/test-repo/issues' => Http::response([
            'html_url' => 'https://github.com/test-owner/test-repo/issues/2',
            'number' => 2,
        ], 201),
    ]);

    $user = createFakeUser();

    $result = $this->service->createIssue('Test', 'Body', 'bug', $user);

    expect($result['number'])->toBe(2);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/app/installations/'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/test-owner/test-repo/issues'
        && $request->hasHeader('Authorization', 'Bearer ghs_cached_token'));
});

it('includes the correct type label', function (string $type, string $expectedLabel): void {
    Http::fake([
        'https://api.github.com/app/installations/789/access_tokens' => Http::response([
            'token' => 'ghs_fake_token',
        ], 201),
        'https://api.github.com/repos/test-owner/test-repo/issues' => Http::response([
            'html_url' => 'https://github.com/test-owner/test-repo/issues/1',
            'number' => 1,
        ], 201),
    ]);

    $user = createFakeUser();

    $this->service->createIssue('Test', 'Body', $type, $user);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/test-owner/test-repo/issues'
        && in_array($expectedLabel, $request['labels']));
})->with([
    'bug' => ['bug', 'bug'],
    'feature' => ['feature', 'enhancement'],
    'feedback' => ['feedback', 'feedback'],
]);

it('throws when GitHub API returns an error', function (): void {
    Http::fake([
        'https://api.github.com/app/installations/789/access_tokens' => Http::response([
            'token' => 'ghs_fake_token',
        ], 201),
        'https://api.github.com/repos/test-owner/test-repo/issues' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $user = createFakeUser();

    expect(fn () => $this->service->createIssue('Test', 'Body', 'bug', $user))
        ->toThrow(RuntimeException::class, 'Failed to create GitHub issue');
});

it('throws when installation token fetch fails', function (): void {
    Http::fake([
        'https://api.github.com/app/installations/789/access_tokens' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $user = createFakeUser();

    expect(fn () => $this->service->createIssue('Test', 'Body', 'bug', $user))
        ->toThrow(RuntimeException::class, 'Failed to get installation token');
});

it('throws when config is missing', function (): void {
    config()->set('feedback-widget.github.app_id');

    $user = createFakeUser();

    expect(fn () => $this->service->createIssue('Test', 'Body', 'bug', $user))
        ->toThrow(RuntimeException::class, 'GitHub feedback service is not configured');
});
