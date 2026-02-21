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
- Tailwind CSS v4
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

### 2. Vite config

The Vue components ship as raw SFCs inside the Composer package. Add a resolve alias so Vite can find them:

```ts
import path from 'node:path';

export default defineConfig({
    resolve: {
        alias: {
            '@twisted-binary/feedback-widget': path.resolve(
                'vendor/twisted-binary/laravel-feedback-widget/resources/js/index.ts',
            ),
        },
    },
    // ...
});
```

### 3. Tailwind CSS v4 — scan the package

Add this to your CSS so Tailwind picks up classes from the widget:

```css
@source "../../vendor/twisted-binary/laravel-feedback-widget/resources/js";
```

### 4. Publish config (optional)

```bash
php artisan vendor:publish --tag=feedback-widget-config
```

This publishes `config/feedback-widget.php` where you can customize routes, middleware, throttling, and more.

## OpenAI API Setup

This package uses the OpenAI API to power the guided feedback chat. Here's how to get set up:

### 1. Create an OpenAI account

Go to [platform.openai.com](https://platform.openai.com/) and sign up or log in.

### 2. Add billing

Navigate to **Settings** → **Billing** and add a payment method. The API is pay-per-use — the default `gpt-4o-mini` model is very affordable for short feedback conversations.

### 3. Generate an API key

Go to **API keys** ([platform.openai.com/api-keys](https://platform.openai.com/api-keys)) → **Create new secret key**.

Give it a name (e.g. `feedback-widget`) and copy the key — you won't be able to see it again.

### 4. Add to your `.env`

```env
OPENAI_API_KEY=sk-...
OPENAI_FEEDBACK_MODEL=gpt-4o-mini    # optional, defaults to gpt-4o-mini
```

The `OPENAI_FEEDBACK_MODEL` setting is optional. `gpt-4o-mini` is the default and works well for feedback conversations. You can switch to `gpt-4o` for higher quality responses at a higher cost.

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
FEEDBACK_WIDGET_LOCALE=en             # optional, defaults to app()->getLocale()
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

**Note:** The widget uses a singleton pattern — only one instance per page is supported.

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
    'locale' => env('FEEDBACK_WIDGET_LOCALE', null), // null = app()->getLocale()
];
```

## Multilanguage Support

### AI Response Language (`locale`)

Set `FEEDBACK_WIDGET_LOCALE` in your `.env` to control the language the AI responds in. When not set, it falls back to `app()->getLocale()`. For English (`en`), no extra instruction is prepended to the AI prompt.

### UI Translations (`translations` prop)

All UI strings have English defaults. To translate the widget, pass a `translations` prop with your overrides:

```vue
<script setup>
import { FeedbackWidget } from '@twisted-binary/feedback-widget';
import type { FeedbackTranslations } from '@twisted-binary/feedback-widget';

const translations: Partial<FeedbackTranslations> = {
    header: 'Envoyer un commentaire',
    bugLabel: 'Bug',
    featureLabel: 'Fonctionnalité',
    feedbackLabel: 'Avis',
    inputPlaceholder: 'Tapez votre message...',
    successMessage: 'Merci pour votre retour !',
    // ... override only the strings you need
};
</script>

<template>
    <FeedbackWidget :translations="translations" />
</template>
```

You only need to pass the strings you want to override — all others fall back to the English defaults. See the `FeedbackTranslations` interface for the full list of translatable strings.

## Exports

The package exports:

```ts
import { FeedbackWidget, FeedbackChatPanel, useFeedbackChat } from '@twisted-binary/feedback-widget';
import { defaultTranslations } from '@twisted-binary/feedback-widget';
import type { FeedbackTranslations } from '@twisted-binary/feedback-widget';
```

| Export | Description |
|--------|-------------|
| `FeedbackWidget` | Full widget with FAB button + chat panel (drop into layout) |
| `FeedbackChatPanel` | Chat panel only (if you want a custom trigger) |
| `useFeedbackChat` | Composable with all state and methods |
| `defaultTranslations` | Default English translations object |
| `FeedbackTranslations` | TypeScript interface for all translatable strings |

## How It Works

1. User clicks the floating action button
2. Selects feedback type (bug / feature / feedback)
3. Chats with the AI assistant which guides them through the details
4. AI produces a structured title + body and asks for confirmation
5. On confirmation, a GitHub issue is created with proper labels
6. User sees a success state

## License

MIT
