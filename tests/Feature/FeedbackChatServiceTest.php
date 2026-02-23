<?php

declare(strict_types=1);

use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use TwistedBinary\FeedbackWidget\Data\FeedbackChatResult;
use TwistedBinary\FeedbackWidget\Services\FeedbackChatService;

beforeEach(function (): void {
    $this->service = new FeedbackChatService(model: 'gpt-4o-mini', appName: 'TestApp', locale: 'en');
});

it('returns a non-complete result for normal replies', function (): void {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Can you describe the steps?']],
            ],
        ]),
    ]);

    $result = $this->service->chat('Button is broken', [], 'bug');

    expect($result)
        ->toBeInstanceOf(FeedbackChatResult::class)
        ->reply->toBe('Can you describe the steps?')
        ->isComplete->toBeFalse()
        ->structuredData->toBeNull();
});

it('detects the sentinel and returns structured data', function (): void {
    $content = 'Great, here is a summary of your bug report.'
        ."\n"
        .'{"__done__": true, "title": "[Bug] Button broken on dashboard", "body": "## Steps\n1. Click the button"}';

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);

    $result = $this->service->chat('Yes, that looks correct', [
        ['role' => 'user', 'content' => 'The button is broken'],
        ['role' => 'assistant', 'content' => 'Can you describe the steps?'],
    ], 'bug');

    expect($result)
        ->isComplete->toBeTrue()
        ->structuredData->toBe([
            'title' => '[Bug] Button broken on dashboard',
            'body' => "## Steps\n1. Click the button",
        ])
        ->reply->toBe('Great, here is a summary of your bug report.');
});

it('returns non-complete result when sentinel JSON is malformed', function (): void {
    $content = 'Here is some text with __done__ but no valid JSON';

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);

    $result = $this->service->chat('Hello', [], 'feedback');

    expect($result)
        ->isComplete->toBeFalse()
        ->reply->toBe($content);
});

it('throws RuntimeException when OpenAI returns empty content', function (): void {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => '']],
            ],
        ]),
    ]);

    expect(fn () => $this->service->chat('Hello', [], 'bug'))
        ->toThrow(RuntimeException::class);
});

it('passes history and type correctly to OpenAI', function (): void {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Got it.']],
            ],
        ]),
    ]);

    $history = [
        ['role' => 'user', 'content' => 'First message'],
        ['role' => 'assistant', 'content' => 'First reply'],
    ];

    $result = $this->service->chat('Second message', $history, 'feature');

    expect($result)->reply->toBe('Got it.');
});

it('handles nested braces in the JSON body', function (): void {
    $content = 'Here is your issue.'
        ."\n"
        .'{"__done__": true, "title": "[Bug] Template rendering fails", "body": "## Code\n```\nif (x) { doThing(); }\n```"}';

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);

    $result = $this->service->chat('Confirmed', [], 'bug');

    expect($result)
        ->isComplete->toBeTrue()
        ->reply->toBe('Here is your issue.')
        ->structuredData->toBe([
            'title' => '[Bug] Template rendering fails',
            'body' => "## Code\n```\nif (x) { doThing(); }\n```",
        ]);
});

it('provides a default reply when sentinel is found but visible text is empty', function (): void {
    $content = '{"__done__": true, "title": "[Feature] Add dark mode", "body": "## Description\nAdd dark mode support"}';

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);

    $result = $this->service->chat('Confirmed', [], 'feature');

    expect($result)
        ->isComplete->toBeTrue()
        ->reply->toBe('Thank you! Creating your issue now...')
        ->structuredData->not->toBeNull();
});

it('correctly parses sentinel JSON when response contains multibyte characters', function (): void {
    $content = "Merci pour votre retour ! J'ai bien noté le problème."
        ."\n"
        .'{"__done__": true, "title": "[Bug] Impossible de sauvegarder une description", "body": "## Steps to Reproduce\n1. Accéder à une localisation d\'entreprise.\n2. Entrer une description.\n\n## Actual Behavior\nLa description reste vide après la sauvegarde.\n\n## Expected Behavior\nLa description devrait être visible immédiatement après avoir cliqué sur \'sauvegarder\'."}';

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);

    $result = $this->service->chat('Oui, c\'est correct', [], 'bug');

    expect($result)
        ->isComplete->toBeTrue()
        ->reply->toBe("Merci pour votre retour ! J'ai bien noté le problème.")
        ->structuredData->title->toBe('[Bug] Impossible de sauvegarder une description');
});

it('does not prepend locale instruction for English locale', function (): void {
    $service = new FeedbackChatService(model: 'gpt-4o-mini', appName: 'TestApp', locale: 'en');
    $method = new ReflectionMethod($service, 'buildSystemPrompt');

    $prompt = $method->invoke($service, 'bug');

    expect($prompt)->not->toContain('You must respond in');
});

it('prepends locale instruction for non-English locale', function (): void {
    $service = new FeedbackChatService(model: 'gpt-4o-mini', appName: 'TestApp', locale: 'fr');
    $method = new ReflectionMethod($service, 'buildSystemPrompt');

    $prompt = $method->invoke($service, 'bug');

    expect($prompt)->toStartWith('You must respond in fr.');
});

it('prepends locale instruction for all prompt types', function (string $type): void {
    $service = new FeedbackChatService(model: 'gpt-4o-mini', appName: 'TestApp', locale: 'es');
    $method = new ReflectionMethod($service, 'buildSystemPrompt');

    $prompt = $method->invoke($service, $type);

    expect($prompt)->toStartWith('You must respond in es.');
})->with(['bug', 'feature', 'feedback']);
