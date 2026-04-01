<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Data\FeedbackChatResult;
use TwistedBinary\FeedbackWidget\Events\FeedbackAiCostRecorded;

beforeEach(function (): void {
    $this->app['config']->set('database.default', 'testing');
    $this->app['config']->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $this->artisan('migrate');
});

describe('FeedbackAiCostRecorded event', function (): void {
    it('is dispatched after a successful cost record', function (): void {
        Event::fake(FeedbackAiCostRecorded::class);

        $user = $this->createAuthenticatedUser();
        $user->id = 1;

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Got it.',
                isComplete: false,
                promptTokens: 100,
                completionTokens: 50,
                model: 'gpt-4o-mini',
            ));

        $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Bug report',
            'type' => 'bug',
            'conversation_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ])->assertSuccessful();

        Event::assertDispatched(FeedbackAiCostRecorded::class, function (FeedbackAiCostRecorded $event): bool {
            return $event->userId === 1
                && $event->model === 'gpt-4o-mini'
                && $event->promptTokens === 100
                && $event->completionTokens === 50
                && $event->feedbackType === 'bug'
                && $event->conversationId === 'f47ac10b-58cc-4372-a567-0e02b2c3d479'
                && $event->createdAt instanceof CarbonImmutable;
        });
    });

    it('is not dispatched when cost tracking is disabled', function (): void {
        Event::fake(FeedbackAiCostRecorded::class);

        config()->set('feedback-widget.track_ai_costs', false);

        $user = $this->createAuthenticatedUser();
        $user->id = 1;

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Got it.',
                isComplete: false,
                promptTokens: 100,
                completionTokens: 50,
                model: 'gpt-4o-mini',
            ));

        $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Bug report',
            'type' => 'bug',
        ])->assertSuccessful();

        Event::assertNotDispatched(FeedbackAiCostRecorded::class);
    });

    it('is not dispatched when prompt tokens are null', function (): void {
        Event::fake(FeedbackAiCostRecorded::class);

        $user = $this->createAuthenticatedUser();

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Got it.',
                isComplete: false,
            ));

        $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Bug report',
            'type' => 'bug',
        ])->assertSuccessful();

        Event::assertNotDispatched(FeedbackAiCostRecorded::class);
    });

    it('is not dispatched when the database insert fails', function (): void {
        Event::fake(FeedbackAiCostRecorded::class);

        // Drop the table so the insert fails
        $this->app['db']->connection()->getSchemaBuilder()->drop('feedback_ai_costs');

        $user = $this->createAuthenticatedUser();
        $user->id = 1;

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Got it.',
                isComplete: false,
                promptTokens: 100,
                completionTokens: 50,
                model: 'gpt-4o-mini',
            ));

        $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Bug report',
            'type' => 'bug',
        ])->assertSuccessful();

        Event::assertNotDispatched(FeedbackAiCostRecorded::class);
    });

    it('does not break the chat response when a listener throws', function (): void {
        Event::listen(FeedbackAiCostRecorded::class, function (): void {
            throw new RuntimeException('Listener exploded');
        });

        $user = $this->createAuthenticatedUser();
        $user->id = 1;

        $this->mock(FeedbackChatServiceInterface::class)
            ->expects('chat')
            ->andReturn(new FeedbackChatResult(
                reply: 'Got it.',
                isComplete: false,
                promptTokens: 100,
                completionTokens: 50,
                model: 'gpt-4o-mini',
            ));

        $this->actingAs($user)->postJson(route('feedback.chat'), [
            'message' => 'Bug report',
            'type' => 'bug',
        ])->assertSuccessful();
    });
});
