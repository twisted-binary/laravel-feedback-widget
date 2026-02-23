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

        // Walk backwards from the last '}' to find the matching '{' via brace depth.
        // Use byte-string functions throughout — JSON delimiters are single-byte ASCII,
        // and mixing mb_strrpos (character offsets) with $content[$i] (byte offsets)
        // breaks on multibyte content like accented characters.
        $end = strrpos($content, '}');

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

        $candidate = substr($content, $start, $end - $start + 1);
        $json = json_decode($candidate, true);

        if (! is_array($json) || ! isset($json[self::SENTINEL], $json['title'], $json['body'])) {
            return new FeedbackChatResult(reply: $content, isComplete: false);
        }

        $visibleReply = trim(substr($content, 0, $start).substr($content, $end + 1));

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
        - When you have enough detail, confirm your understanding back to the user in plain conversational language (e.g. "So the page crashes when you click save — is that right?"). Do NOT show the formatted title/body or any structured data to the user.
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
        - When the user describes their feedback, immediately rewrite it into a clear, well-structured version and submit it.
        - Do NOT ask follow-up questions or ask for confirmation. Work with what the user gave you and submit right away.
        - Along with your brief, friendly acknowledgement, output a JSON object on its own line with this exact shape:
          {"__done__": true, "title": "Short descriptive title", "body": "Formatted markdown body"}
        - The title should be concise (under 80 chars) and prefixed with "[Feedback]".
        - The body should be well-structured markdown.
        - Always output the JSON in your first response — do not wait for a second message.
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
        - When you have enough information, confirm your understanding back to the user in plain conversational language (e.g. "Got it — you'd like a dark mode toggle in settings. Should I submit that?"). Do NOT show the formatted title/body or any structured data to the user.
        - When the user confirms, output a JSON object on its own line with this exact shape:
          {"__done__": true, "title": "Short descriptive title", "body": "Formatted markdown body with all gathered details"}
        - The title should be concise (under 80 chars) and prefixed with "[Feature]".
        - The body should be well-structured markdown.
        - Do NOT output the JSON until the user explicitly confirms the summary.
        PROMPT;
    }
}
