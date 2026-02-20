import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defaultTranslations } from '../translations';
import { useFeedbackChat } from './useFeedbackChat';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            feedbackWidget: {
                routes: {
                    chat: '/feedback/chat',
                    issue: '/feedback/issue',
                },
            },
        },
    }),
}));

function mockFetch(status: number, body: object): void {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: status >= 200 && status < 300,
            status,
            json: () => Promise.resolve(body),
        }),
    );
}

describe('useFeedbackChat', () => {
    beforeEach(() => {
        const { reset } = useFeedbackChat();
        reset();
        vi.unstubAllGlobals();
    });

    describe('translations', () => {
        it('uses default translations when none provided', () => {
            const { translations } = useFeedbackChat();
            expect(translations.value).toEqual(defaultTranslations);
        });

        it('merges partial translations with defaults', () => {
            const { translations } = useFeedbackChat({
                translations: { header: 'Custom' },
            });
            expect(translations.value.header).toBe('Custom');
            expect(translations.value.bugLabel).toBe(defaultTranslations.bugLabel);
        });
    });

    describe('sendMessage', () => {
        it('pushes user and assistant messages on success', async () => {
            mockFetch(200, { reply: 'Hello!', done: false });

            const { messages, sendMessage } = useFeedbackChat();
            await sendMessage('Hi', 'bug');

            expect(messages.value).toHaveLength(2);
            expect(messages.value[0]).toEqual({ role: 'user', content: 'Hi' });
            expect(messages.value[1]).toEqual({ role: 'assistant', content: 'Hello!' });
        });

        it('sets structured data when done', async () => {
            mockFetch(200, {
                reply: 'Done!',
                done: true,
                structured: { title: 'Bug title', body: 'Bug body' },
            });

            const { sendMessage, isComplete, structuredData } = useFeedbackChat();
            await sendMessage('confirm', 'bug');

            expect(isComplete.value).toBe(true);
            expect(structuredData.value).toEqual({ title: 'Bug title', body: 'Bug body' });
        });

        it('handles 419 session expired', async () => {
            mockFetch(419, {});

            const { messages, error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe(defaultTranslations.sessionExpired);
            expect(messages.value).toHaveLength(0);
        });

        it('handles 422 with server message', async () => {
            mockFetch(422, { message: 'The message field is required.' });

            const { messages, error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe('The message field is required.');
            expect(messages.value).toHaveLength(0);
        });

        it('handles 422 with fallback message', async () => {
            mockFetch(422, {});

            const { error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe(defaultTranslations.validationError);
        });

        it('handles 429 rate limit', async () => {
            mockFetch(429, {});

            const { messages, error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe(defaultTranslations.rateLimitError);
            expect(messages.value).toHaveLength(0);
        });

        it('handles 500 server error', async () => {
            mockFetch(500, { error: 'Internal server error' });

            const { messages, error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe('Internal server error');
            expect(messages.value).toHaveLength(0);
        });

        it('handles network error', async () => {
            vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Failed to fetch')));

            const { messages, error, sendMessage } = useFeedbackChat();
            await sendMessage('test', 'bug');

            expect(error.value).toBe(defaultTranslations.networkError);
            expect(messages.value).toHaveLength(0);
        });

        it('blocks when conversation reaches max messages', async () => {
            const fetchSpy = vi.fn();
            vi.stubGlobal('fetch', fetchSpy);

            const { messages, error, sendMessage } = useFeedbackChat();

            // Fill with 40 messages (20 round-trips)
            for (let i = 0; i < 20; i++) {
                messages.value.push({ role: 'user', content: `msg ${i}` });
                messages.value.push({ role: 'assistant', content: `reply ${i}` });
            }

            expect(messages.value).toHaveLength(40);

            await sendMessage('one more', 'bug');

            expect(error.value).toBe(defaultTranslations.maxMessagesError);
            expect(fetchSpy).not.toHaveBeenCalled();
            expect(messages.value).toHaveLength(40);
        });
    });

    describe('createIssue', () => {
        it('handles 429 rate limit', async () => {
            const { sendMessage, createIssue, error, structuredData, isComplete } = useFeedbackChat();

            // Set up structured data manually
            mockFetch(200, {
                reply: 'Done!',
                done: true,
                structured: { title: 'Title', body: 'Body' },
            });
            await sendMessage('confirm', 'bug');
            expect(isComplete.value).toBe(true);
            expect(structuredData.value).not.toBeNull();

            // Now mock 429 for createIssue
            mockFetch(429, {});
            await createIssue('bug');

            expect(error.value).toBe(defaultTranslations.rateLimitError);
        });
    });

    describe('reset', () => {
        it('clears all state', async () => {
            mockFetch(200, { reply: 'Hi', done: false });

            const { messages, error, isComplete, issueUrl, issueNumber, structuredData, sendMessage, reset } =
                useFeedbackChat();

            await sendMessage('test', 'bug');
            expect(messages.value.length).toBeGreaterThan(0);

            reset();

            expect(messages.value).toHaveLength(0);
            expect(isComplete.value).toBe(false);
            expect(issueUrl.value).toBeNull();
            expect(issueNumber.value).toBeNull();
            expect(error.value).toBeNull();
            expect(structuredData.value).toBeNull();
        });
    });
});
