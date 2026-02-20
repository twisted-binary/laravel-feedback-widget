<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TwistedBinary\FeedbackWidget\Http\Controllers\FeedbackWidgetController;

Route::post('chat', [FeedbackWidgetController::class, 'chat'])
    ->middleware('throttle:'.config('feedback-widget.throttle.chat', '30,1'))
    ->name('feedback.chat');

Route::post('issue', [FeedbackWidgetController::class, 'createIssue'])
    ->middleware('throttle:'.config('feedback-widget.throttle.issue', '5,1'))
    ->name('feedback.issue');
