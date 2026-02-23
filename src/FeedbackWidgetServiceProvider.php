<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Override;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Contracts\IssueServiceInterface;
use TwistedBinary\FeedbackWidget\Services\FeedbackChatService;
use TwistedBinary\FeedbackWidget\Services\GitHubIssueService;

final class FeedbackWidgetServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feedback-widget.php', 'feedback-widget');

        $this->app->bind(FeedbackChatServiceInterface::class, fn (): FeedbackChatService => new FeedbackChatService(
            model: config('feedback-widget.openai_model'),
            appName: config('feedback-widget.app_name'),
            locale: config('feedback-widget.locale') ?? app()->getLocale(),
        ));

        $this->app->bind(IssueServiceInterface::class, GitHubIssueService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/feedback-widget.php' => config_path('feedback-widget.php'),
        ], 'feedback-widget-config');

        $this->registerRoutes();
        $this->shareInertiaProps();
    }

    private function registerRoutes(): void
    {
        Route::middleware(config('feedback-widget.middleware', ['web', 'auth', 'verified']))
            ->prefix(config('feedback-widget.route_prefix', 'feedback'))
            ->group(__DIR__.'/routes.php');

        Route::middleware('web')
            ->prefix(config('feedback-widget.route_prefix', 'feedback'))
            ->get('csrf', fn () => response()->noContent())
            ->name('feedback.csrf');
    }

    private function shareInertiaProps(): void
    {
        Inertia::share('feedbackWidget', fn () => [
            'routes' => [
                'chat' => route('feedback.chat'),
                'issue' => route('feedback.issue'),
                'csrf' => route('feedback.csrf'),
            ],
        ]);
    }
}
