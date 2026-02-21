<?php

declare(strict_types=1);

return [
    'route_prefix' => env('FEEDBACK_WIDGET_ROUTE_PREFIX', 'feedback'),
    'middleware' => ['web', 'auth', 'verified'],
    'throttle' => [
        'chat' => '10,1',
        'issue' => '5,1',
    ],
    'openai_model' => env('OPENAI_FEEDBACK_MODEL', 'gpt-4o-mini'),
    'github' => [
        'app_id' => env('GITHUB_APP_ID'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
        'repo_owner' => env('GITHUB_REPO_OWNER'),
        'repo_name' => env('GITHUB_REPO_NAME'),
        'feedback_label' => env('GITHUB_FEEDBACK_LABEL', 'user-feedback'),
    ],
    'screenshot_disk' => 'public',
    'screenshot_path' => 'feedback-screenshots',
    'app_name' => env('FEEDBACK_WIDGET_APP_NAME', env('APP_NAME', 'the application')),
    'locale' => env('FEEDBACK_WIDGET_LOCALE', null),
];
