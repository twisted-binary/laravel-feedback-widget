# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Vitest test suite with translations and composable tests
- Accessibility: `role="dialog"`, `aria-modal`, `aria-live`, `aria-label`, `aria-pressed`, `aria-selected` attributes
- Focus trap (Tab/Shift+Tab) and Escape-to-close in chat panel
- Textarea auto-resize on input
- Rate limit (429) error handling for chat and issue endpoints
- Conversation length guard (max 40 messages)
- `rateLimitError` and `maxMessagesError` translation keys

### Fixed
- Screenshot preview URL lifecycle managed in composable (prevents memory leaks from unreleased object URLs)

## [1.1.0] - 2025-05-20

### Added
- Multilanguage support via `translations` prop and `locale` config
- `FeedbackTranslations` interface and `defaultTranslations` export
- AI response language controlled by `FEEDBACK_WIDGET_LOCALE` env variable

## [1.0.0] - 2025-05-15

### Added
- AI-guided feedback chat (bug, feature, feedback types)
- Auto-structured GitHub issue creation via GitHub App
- Screenshot upload (paste or file picker) for bug reports
- Star ratings for general feedback
- Configurable routes, middleware, and throttling
