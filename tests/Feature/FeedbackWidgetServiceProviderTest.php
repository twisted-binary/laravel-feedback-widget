<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Contracts\IssueServiceInterface;
use TwistedBinary\FeedbackWidget\Services\FeedbackChatService;
use TwistedBinary\FeedbackWidget\Services\GitHubIssueService;

it('binds FeedbackChatServiceInterface to FeedbackChatService', function (): void {
    $resolved = app(FeedbackChatServiceInterface::class);

    expect($resolved)->toBeInstanceOf(FeedbackChatService::class);
});

it('binds IssueServiceInterface to GitHubIssueService', function (): void {
    $resolved = app(IssueServiceInterface::class);

    expect($resolved)->toBeInstanceOf(GitHubIssueService::class);
});

it('registers the feedback.chat route', function (): void {
    expect(Route::has('feedback.chat'))->toBeTrue();

    $route = Route::getRoutes()->getByName('feedback.chat');

    expect($route->methods())->toContain('POST')
        ->and($route->uri())->toBe('feedback/chat');
});

it('registers the feedback.issue route', function (): void {
    expect(Route::has('feedback.issue'))->toBeTrue();

    $route = Route::getRoutes()->getByName('feedback.issue');

    expect($route->methods())->toContain('POST')
        ->and($route->uri())->toBe('feedback/issue');
});

it('applies configured middleware to routes', function (): void {
    $route = Route::getRoutes()->getByName('feedback.chat');

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('verified');
});

it('uses the configured route prefix', function (): void {
    $route = Route::getRoutes()->getByName('feedback.chat');

    expect($route->getPrefix())->toBe('feedback');
});

it('merges default config values', function (): void {
    expect(config('feedback-widget.route_prefix'))->toBe('feedback')
        ->and(config('feedback-widget.openai_model'))->toBe('gpt-4o-mini')
        ->and(config('feedback-widget.throttle.chat'))->toBe('30,1')
        ->and(config('feedback-widget.throttle.issue'))->toBe('5,1')
        ->and(config('feedback-widget.screenshot_disk'))->toBe('public')
        ->and(config('feedback-widget.screenshot_path'))->toBe('feedback-screenshots')
        ->and(config('feedback-widget.locale'))->toBeNull();
});

it('passes app locale to FeedbackChatService when locale config is null', function (): void {
    config()->set('feedback-widget.locale', null);
    app()->setLocale('fr');

    $service = app(FeedbackChatServiceInterface::class);
    $prop = new ReflectionProperty($service, 'locale');

    expect($prop->getValue($service))->toBe('fr');
});

it('passes configured locale to FeedbackChatService', function (): void {
    config()->set('feedback-widget.locale', 'de');

    $service = app(FeedbackChatServiceInterface::class);
    $prop = new ReflectionProperty($service, 'locale');

    expect($prop->getValue($service))->toBe('de');
});

it('shares Inertia props with feedback widget routes', function (): void {
    $shared = Inertia::getShared('feedbackWidget');

    expect($shared)->toBeCallable();

    $result = $shared();

    expect($result)->toHaveKey('routes')
        ->and($result['routes'])->toHaveKey('chat')
        ->and($result['routes'])->toHaveKey('issue')
        ->and($result['routes']['chat'])->toContain('feedback/chat')
        ->and($result['routes']['issue'])->toContain('feedback/issue');
});
