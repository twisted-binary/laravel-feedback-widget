# Laravel Feedback Widget

An AI-powered feedback widget for Laravel + Inertia + Vue apps. Users describe bugs, feature requests, or general feedback through a guided chat — the AI structures it into a well-formatted GitHub issue automatically.

## Features

- **Guided chat** — AI asks follow-up questions to gather the right details for each feedback type (bug, feature, feedback)
- **Auto-structured issues** — Produces well-formatted GitHub issues with proper sections (Steps to Reproduce, Expected Behavior, etc.)
- **Screenshot uploads** — Users can paste or attach screenshots for bug reports
- **Star ratings** — Optional 5-star rating for general feedback
- **GitHub App auth** — Uses GitHub App installation tokens (not PATs) for secure issue creation
- **Zero config UI** — Drop `<FeedbackWidget />` into any layout; routes, bindings, and Inertia props are handled by the service provider

## Requirements

- PHP 8.4+
- Laravel 12+
- Inertia.js v2 (Vue 3)
- An OpenAI API key
- A GitHub App with issue write permissions

## Installation

### 1. Composer

Since this package isn't on Packagist, add the VCS repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/twisted-binary/laravel-feedback-widget.git"
        }
    ],
    "require": {
        "twisted-binary/laravel-feedback-widget": "^1.0"
    }
}
```

```bash
composer update twisted-binary/laravel-feedback-widget
```

### 2. npm

```json
{
    "dependencies": {
        "@twisted-binary/feedback-widget": "github:twisted-binary/laravel-feedback-widget"
    }
}
```

```bash
npm install
```

### 3. Tailwind CSS v4 — scan the package

Add this to your CSS so Tailwind picks up classes from the widget:

```css
@source "../../node_modules/@twisted-binary/feedback-widget";
```

### 4. Vite config — dedupe peer dependencies

If you're using `file:` or symlinked references during development, add `resolve.dedupe` to your `vite.config.ts`:

```ts
export default defineConfig({
    resolve: {
        dedupe: ['vue', '@inertiajs/vue3', 'lucide-vue-next'],
    },
    // ...
});
```

### 5. Publish config (optional)

```bash
php artisan vendor:publish --tag=feedback-widget-config
```

This publishes `config/feedback-widget.php` where you can customize routes, middleware, throttling, and more.

## GitHub App Setup

This package uses a GitHub App (not a personal access token) to create issues. Here's how to set one up:

### 1. Create the GitHub App

Go to [github.com/settings/apps/new](https://github.com/settings/apps/new) and configure:

| Field | Value |
|-------|-------|
| App name | Something like `myapp-feedback` |
| Homepage URL | Your app URL |
| Webhook | Uncheck "Active" (not needed) |
| Permissions → Repository → Issues | Read & write |
| Where can this app be installed? | Only on this account |

Click **Create GitHub App**. Note the **App ID** shown on the next page.

### 2. Generate a private key

On the app settings page, scroll to **Private keys** → **Generate a private key**. A `.pem` file downloads.

### 3. Install the app on your repo

On the app settings page, click **Install App** in the left sidebar → **Install** on your account → select **Only select repositories** → pick your feedback repo → **Install**.

Note the **Installation ID** from the URL after installing:

```
https://github.com/settings/installations/INSTALLATION_ID
```

### 4. Base64-encode the private key

```bash
cat your-app.private-key.pem | base64 | tr -d '\n'
```

Copy the output — you'll use it as `GITHUB_APP_PRIVATE_KEY` below.

## Environment Variables

Add these to your `.env`:

```env
# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_FEEDBACK_MODEL=gpt-4o-mini    # optional, defaults to gpt-4o-mini

# GitHub App
GITHUB_APP_ID=123456
GITHUB_APP_PRIVATE_KEY=base64:...     # base64-encoded PEM private key
GITHUB_APP_INSTALLATION_ID=789
GITHUB_REPO_OWNER=your-org
GITHUB_REPO_NAME=your-repo
GITHUB_FEEDBACK_LABEL=user-feedback   # optional

# Widget
FEEDBACK_WIDGET_APP_NAME=MyApp        # optional, used in AI prompts
FEEDBACK_WIDGET_ROUTE_PREFIX=feedback  # optional
```

## Usage

Add the widget to your layout:

```vue
<script setup>
import { FeedbackWidget } from '@twisted-binary/feedback-widget';
</script>

<template>
    <!-- your layout content -->
    <FeedbackWidget />
</template>
```

That's it. The service provider automatically:
- Registers routes (`POST /feedback/chat`, `POST /feedback/issue`)
- Binds the chat and issue services
- Shares route URLs to the frontend via Inertia props

## Configuration

The full config file (`config/feedback-widget.php`):

```php
return [
    'route_prefix' => env('FEEDBACK_WIDGET_ROUTE_PREFIX', 'feedback'),
    'middleware' => ['web', 'auth', 'verified'],
    'throttle' => [
        'chat' => '30,1',   // 30 requests per minute
        'issue' => '5,1',   // 5 requests per minute
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
];
```

## Exports

The npm package exports:

```ts
import { FeedbackWidget, FeedbackChatPanel, useFeedbackChat } from '@twisted-binary/feedback-widget';
```

| Export | Description |
|--------|-------------|
| `FeedbackWidget` | Full widget with FAB button + chat panel (drop into layout) |
| `FeedbackChatPanel` | Chat panel only (if you want a custom trigger) |
| `useFeedbackChat` | Composable with all state and methods |

## How It Works

1. User clicks the floating action button
2. Selects feedback type (bug / feature / feedback)
3. Chats with the AI assistant which guides them through the details
4. AI produces a structured title + body and asks for confirmation
5. On confirmation, a GitHub issue is created with proper labels
6. User sees a success state

## License

MIT
