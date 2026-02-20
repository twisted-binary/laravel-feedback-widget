<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use RuntimeException;
use Throwable;
use TwistedBinary\FeedbackWidget\Contracts\IssueServiceInterface;

final class GitHubIssueService implements IssueServiceInterface
{
    /**
     * @return array{url: string, number: int}
     */
    public function createIssue(string $title, string $body, string $type, Authenticatable $user): array
    {
        $owner = config('feedback-widget.github.repo_owner');
        $repo = config('feedback-widget.github.repo_name');
        $feedbackLabel = config('feedback-widget.github.feedback_label', 'user-feedback');

        $token = $this->getInstallationToken();

        throw_unless($owner && $repo, RuntimeException::class, 'GitHub feedback service is not configured.');

        $labels = [
            $feedbackLabel,
            match ($type) {
                'bug' => 'bug',
                'feature' => 'enhancement',
                default => 'feedback',
            },
        ];

        $bodyWithContext = $body."\n\n---\n_Submitted via feedback widget by user {$user->getAuthIdentifier()}_";

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post(sprintf('https://api.github.com/repos/%s/%s/issues', $owner, $repo), [
                    'title' => $title,
                    'body' => $bodyWithContext,
                    'labels' => $labels,
                ]);

            throw_unless($response->successful(), RuntimeException::class, 'GitHub API returned '.$response->status().': '.$response->body());

            $data = $response->json();

            return [
                'url' => $data['html_url'],
                'number' => $data['number'],
            ];
        } catch (Throwable $throwable) {
            throw new RuntimeException('Failed to create GitHub issue: '.$throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * Get a cached installation access token, generating a new one if needed.
     */
    private function getInstallationToken(): string
    {
        $appId = config('feedback-widget.github.app_id');
        $privateKey = config('feedback-widget.github.private_key');
        $installationId = config('feedback-widget.github.installation_id');

        throw_unless($appId && $privateKey && $installationId, RuntimeException::class, 'GitHub feedback service is not configured.');

        return Cache::remember('github_app_installation_token', 3000, function () use ($appId, $privateKey, $installationId): string {
            $jwt = $this->generateJwt((string) $appId, (string) $privateKey);

            $response = Http::withToken($jwt)
                ->timeout(10)
                ->post(sprintf('https://api.github.com/app/installations/%s/access_tokens', $installationId));

            throw_unless($response->successful(), RuntimeException::class, 'Failed to get installation token: '.$response->status().': '.$response->body());

            return $response->json('token');
        });
    }

    /**
     * Generate a short-lived JWT for GitHub App authentication.
     */
    private function generateJwt(string $appId, string $base64PrivateKey): string
    {
        $now = new DateTimeImmutable;

        $token = new Builder(new JoseEncoder, ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($appId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+9 minutes'))
            ->getToken(new Sha256, InMemory::base64Encoded($base64PrivateKey));

        return $token->toString();
    }
}
