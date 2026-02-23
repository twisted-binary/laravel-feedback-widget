# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.3.2] - 2026-02-23

### Fixed
- Chat no longer shows "Session expired" when the page has been open for a long time — the CSRF token is now automatically refreshed and the request retried, preserving the user's typed message
- Fixed test assertion for chat throttle config default (30 → 10 requests per minute)

## [1.3.1] - 2026-02-23

### Fixed
- Fixed JSON extraction failing on AI responses containing multibyte characters (e.g. French accented characters), which caused raw JSON to be displayed in the chat instead of creating the GitHub issue

## [1.3.0] - 2026-02-21

### Changed
- Bug and feature prompts now confirm understanding conversationally instead of showing structured title/body data
- Feedback prompt submits immediately without requiring user confirmation
- Tightened chat endpoint throttle from 30 to 10 requests per minute

## [1.2.0] - 2026-02-21

### Changed
- Replaced Tailwind CSS utility classes with self-contained scoped CSS using `tbfw-` prefixed class names
- Widget now renders correctly without Tailwind CSS or shadcn/ui theme variables
- CSS custom properties (`--tbfw-*`) defined on `.tbfw-widget` root for consumer overrides
- Removed `tailwindcss` from peerDependencies and `prettier-plugin-tailwindcss` from devDependencies
- Removed `@source` directive step from installation docs

## [1.1.1] - 2026-02-21

### Changed
- npm install instruction now pins to a semver range (`^1.0`)
- Removed `package-lock.json` from repository (lock files are ignored by consuming apps)

## [1.1.0] - 2026-02-21

### Added
- Multilanguage support via `translations` prop and `locale` config
- `FeedbackTranslations` interface and `defaultTranslations` export
- AI response language controlled by `FEEDBACK_WIDGET_LOCALE` env variable
- Vitest test suite with translations and composable tests
- Accessibility: `role="dialog"`, `aria-modal`, `aria-live`, `aria-label`, `aria-pressed`, `aria-selected` attributes
- Focus trap (Tab/Shift+Tab) and Escape-to-close in chat panel
- Textarea auto-resize on input
- Rate limit (429) error handling for chat and issue endpoints
- Conversation length guard (max 40 messages)
- `rateLimitError` and `maxMessagesError` translation keys

### Fixed
- Screenshot preview URL lifecycle managed in composable (prevents memory leaks from unreleased object URLs)

## [1.0.0] - 2026-02-20

### Added
- AI-guided feedback chat (bug, feature, feedback types)
- Auto-structured GitHub issue creation via GitHub App
- Screenshot upload (paste or file picker) for bug reports
- Star ratings for general feedback
- Configurable routes, middleware, and throttling
