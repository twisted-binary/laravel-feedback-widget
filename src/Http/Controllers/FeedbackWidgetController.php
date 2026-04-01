<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use TwistedBinary\FeedbackWidget\Contracts\FeedbackChatServiceInterface;
use TwistedBinary\FeedbackWidget\Contracts\IssueServiceInterface;
use TwistedBinary\FeedbackWidget\Events\FeedbackAiCostRecorded;
use TwistedBinary\FeedbackWidget\Models\FeedbackAiCost;
use TwistedBinary\FeedbackWidget\Http\Requests\CreateFeedbackIssueRequest;
use TwistedBinary\FeedbackWidget\Http\Requests\FeedbackChatRequest;

final class FeedbackWidgetController
{
    public function chat(FeedbackChatRequest $request, FeedbackChatServiceInterface $chatService): JsonResponse
    {
        try {
            $result = $chatService->chat(
                message: $request->validated('message'),
                history: $request->validated('history', []),
                type: $request->validated('type'),
            );

            if (config('feedback-widget.track_ai_costs', true) && $result->promptTokens !== null) {
                try {
                    $cost = FeedbackAiCost::create([
                        'conversation_id' => $request->validated('conversation_id'),
                        'user_id' => $request->user()?->id,
                        'model' => $result->model ?? '',
                        'prompt_tokens' => $result->promptTokens,
                        'completion_tokens' => $result->completionTokens ?? 0,
                        'total_tokens' => ($result->promptTokens) + ($result->completionTokens ?? 0),
                        'feedback_type' => $request->validated('type'),
                    ]);

                    event(new FeedbackAiCostRecorded(
                        userId: $cost->user_id,
                        model: $cost->model,
                        promptTokens: $cost->prompt_tokens,
                        completionTokens: $cost->completion_tokens,
                        feedbackType: $cost->feedback_type,
                        conversationId: $cost->conversation_id ?? '',
                        createdAt: $cost->created_at,
                    ));
                } catch (Throwable $costError) {
                    Log::warning('Failed to track AI cost', ['error' => $costError->getMessage()]);
                }
            }

            return response()->json([
                'reply' => $result->reply,
                'done' => $result->isComplete,
                'structured' => $result->structuredData,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    public function createIssue(CreateFeedbackIssueRequest $request, IssueServiceInterface $issueService): JsonResponse
    {
        try {
            $body = $request->validated('body');
            $disk = config('feedback-widget.screenshot_disk', 'public');
            $path = config('feedback-widget.screenshot_path', 'feedback-screenshots');

            if ($request->hasFile('screenshot')) {
                $storedPath = $request->file('screenshot')->store($path, $disk);
                $url = Storage::disk($disk)->url($storedPath);
                $body .= "\n\n![Screenshot]({$url})";
            }

            $result = $issueService->createIssue(
                title: $request->validated('title'),
                body: $body,
                type: $request->validated('type'),
                user: $request->user(),
            );

            return response()->json([
                'url' => $result['url'],
                'number' => $result['number'],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => 'Failed to create the issue. Please try again.',
            ], 500);
        }
    }
}
