<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Services;

use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Data\FeedbackChatResult;

final readonly class FeedbackChatService implements FeedbackChatServiceInterface
{
    private const string SENTINEL = '__done__';

    public function __construct(
        private string $model,
        private string $appName,
        private string $locale = 'en',
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(string $message, array $history, string $type): FeedbackChatResult
    {
        try {
            $messages = [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($type)],
                ...$history,
                ['role' => 'user', 'content' => $message],
            ];

            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

            $content = $response->choices[0]->message->content ?? '';

            throw_if($content === '', RuntimeException::class, 'Empty response from OpenAI.');

            return $this->parseResponse($content);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Feedback chat failed: '.$throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    private function parseResponse(string $content): FeedbackChatResult
    {
        if (! str_contains($content, self::SENTINEL)) {
            return new FeedbackChatResult(reply: $content, isComplete: false);
        }

        // Walk backwards from the last '}' to find the matching '{' via brace depth
        $end = mb_strrpos($content, '}');

        if ($end === false) {
            return new FeedbackChatResult(reply: $content, isComplete: false);
        }

        $depth = 0;
        $start = null;

        for ($i = $end; $i >= 0; $i--) {
            if ($content[$i] === '}') {
                $depth++;
            } elseif ($content[$i] === '{') {
                $depth--;
            }

            if ($depth === 0) {
                $start = $i;
                break;
            }
        }

        if ($start === null) {
            return new FeedbackChatResult(reply: $content, isComplete: false);
        }

        $candidate = mb_substr($content, $start, $end - $start + 1);
        $json = json_decode($candidate, true);

        if (! is_array($json) || ! isset($json[self::SENTINEL], $json['title'], $json['body'])) {
            return new FeedbackChatResult(reply: $content, isComplete: false);
        }

        $visibleReply = mb_trim(mb_substr($content, 0, $start).mb_substr($content, $end + 1));

        return new FeedbackChatResult(
            reply: $visibleReply !== '' ? $visibleReply : 'Thank you! Creating your issue now...',
            isComplete: true,
            structuredData: [
                'title' => $json['title'],
                'body' => $json['body'],
            ],
        );
    }

    private function buildSystemPrompt(string $type): string
    {
        $prompt = match ($type) {
            'bug' => $this->bugPrompt(),
            'feedback' => $this->feedbackPrompt(),
            default => $this->featurePrompt(),
        };

        if ($this->locale !== 'en') {
            $prompt = "You must respond in {$this->locale}.\n\n".$prompt;
        }

        return $prompt;
    }

    private function bugPrompt(): string
    {
        return <<<PROMPT
        You are a friendly bug-report assistant for a web application called {$this->appName}. Your job is to help users submit clear, actionable bug reports.

        Guidelines:
        - Focus on three things: (1) what they did (steps to reproduce), (2) what actually happened, and (3) what they expected to happen.
        - Ask one short follow-up at a time. Most bugs need only 1-2 follow-ups to clarify the reproduction steps or the expected outcome.
        - Do NOT ask about browser, OS, or device unless the user hints it might be relevant.
        - Do NOT ask the user to attach a screenshot — they can attach one separately.
        - When you have enough detail, present a brief summary and ask the user to confirm.
        - When the user confirms (e.g. "yes", "looks good", "send it", "confirm"), output a JSON object on its own line with this exact shape:
          {"__done__": true, "title": "Short descriptive title", "body": "Formatted markdown body"}
        - The title should be concise (under 80 chars) and prefixed with "[Bug]".
        - The body should use these markdown sections: ## Steps to Reproduce, ## Actual Behavior, ## Expected Behavior.
        - Do NOT output the JSON until the user explicitly confirms the summary.
        PROMPT;
    }

    private function feedbackPrompt(): string
    {
        return <<<PROMPT
        You are a friendly feedback assistant for a web application called {$this->appName}. Your job is to help users submit clear, structured general feedback.

        Guidelines:
        - When the user describes their feedback, immediately rewrite it into a clear, well-structured version.
        - Present the polished version and ask the user to confirm or adjust it.
        - Do NOT ask follow-up questions. Work with what the user gave you.
        - Keep your response concise — just the rewritten feedback and a short confirmation prompt.
        - When the user confirms (e.g. "yes", "looks good", "send it", "confirm"), output a JSON object on its own line with this exact shape:
          {"__done__": true, "title": "Short descriptive title", "body": "Formatted markdown body"}
        - The title should be concise (under 80 chars) and prefixed with "[Feedback]".
        - The body should be well-structured markdown.
        - Do NOT output the JSON until the user explicitly confirms.
        PROMPT;
    }

    private function featurePrompt(): string
    {
        return <<<PROMPT
        You are a friendly feedback assistant for a web application called {$this->appName}. Your job is to help users submit clear, structured feature requests.

        Guidelines:
        - Ask 2-4 targeted follow-up questions to gather enough detail.
        - For feature requests: ask about the use case, desired behavior, and priority.
        - Keep responses concise and conversational — one question at a time.
        - When you have enough information, present a brief summary and ask the user to confirm.
        - When the user confirms, output a JSON object on its own line with this exact shape:
          {"__done__": true, "title": "Short descriptive title", "body": "Formatted markdown body with all gathered details"}
        - The title should be concise (under 80 chars) and prefixed with "[Feature]".
        - The body should be well-structured markdown.
        - Do NOT output the JSON until the user explicitly confirms the summary.
        PROMPT;
    }
}
