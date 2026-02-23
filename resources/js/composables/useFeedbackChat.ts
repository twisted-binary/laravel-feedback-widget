import { usePage } from '@inertiajs/vue3';
import { type Ref, ref } from 'vue';
import { type FeedbackTranslations, defaultTranslations } from '../translations';

export interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

interface ChatResponse {
    reply: string;
    done: boolean;
    structured?: {
        title: string;
        body: string;
    };
    error?: string;
}

interface IssueResponse {
    url: string;
    number: number;
    error?: string;
}

interface FeedbackWidgetProps {
    feedbackWidget: {
        routes: {
            chat: string;
            issue: string;
            csrf: string;
        };
    };
    [key: string]: unknown;
}

const MAX_MESSAGES = 40;

const messages = ref<ChatMessage[]>([]);
const isLoading = ref(false);
const isComplete = ref(false);
const issueUrl = ref<string | null>(null);
const issueNumber = ref<number | null>(null);
const error = ref<string | null>(null);
const structuredData = ref<{ title: string; body: string } | null>(null);
const screenshot: Ref<File | null> = ref(null);
const screenshotPreview = ref<string | null>(null);
const isOpen = ref(false);
const feedbackType = ref<'bug' | 'feature' | 'feedback'>('bug');
const translations = ref<FeedbackTranslations>({ ...defaultTranslations });

function getRoutes(): { chat: string; issue: string; csrf: string } {
    const page = usePage<FeedbackWidgetProps>();
    return page.props.feedbackWidget.routes;
}

function setScreenshot(file: File): void {
    if (screenshotPreview.value) {
        URL.revokeObjectURL(screenshotPreview.value);
    }
    screenshot.value = file;
    screenshotPreview.value = URL.createObjectURL(file);
}

function clearScreenshot(): void {
    if (screenshotPreview.value) {
        URL.revokeObjectURL(screenshotPreview.value);
    }
    screenshot.value = null;
    screenshotPreview.value = null;
}

export function useFeedbackChat(options?: { translations?: Partial<FeedbackTranslations> }) {
    if (options?.translations) {
        translations.value = { ...defaultTranslations, ...options.translations };
    }

    async function sendMessage(message: string, type: string): Promise<void> {
        error.value = null;

        if (messages.value.length >= MAX_MESSAGES) {
            error.value = translations.value.maxMessagesError;
            return;
        }

        messages.value.push({ role: 'user', content: message });

        isLoading.value = true;

        try {
            const routes = getRoutes();

            const response = await csrfFetch(routes.chat, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    message,
                    history: messages.value.slice(0, -1),
                    type,
                }),
            });

            if (response.status === 419) {
                error.value = translations.value.sessionExpired;
                messages.value.pop();
                return;
            }

            if (response.status === 422) {
                const data = await response.json();
                error.value = data.message || translations.value.validationError;
                messages.value.pop();
                return;
            }

            if (response.status === 429) {
                error.value = translations.value.rateLimitError;
                messages.value.pop();
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                error.value = data.error || translations.value.genericError;
                messages.value.pop();
                return;
            }

            const data: ChatResponse = await response.json();

            messages.value.push({ role: 'assistant', content: data.reply });

            if (data.done && data.structured) {
                isComplete.value = true;
                structuredData.value = data.structured;
            }
        } catch {
            error.value = translations.value.networkError;
            messages.value.pop();
        } finally {
            isLoading.value = false;
        }
    }

    async function createIssue(type: string): Promise<void> {
        if (!structuredData.value) {
            return;
        }

        error.value = null;
        isLoading.value = true;

        try {
            const routes = getRoutes();

            const headers: Record<string, string> = {
                Accept: 'application/json',
            };

            let body: FormData | string;

            if (screenshot.value) {
                const formData = new FormData();
                formData.append('title', structuredData.value.title);
                formData.append('body', structuredData.value.body);
                formData.append('type', type);
                formData.append('screenshot', screenshot.value);
                body = formData;
            } else {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({
                    title: structuredData.value.title,
                    body: structuredData.value.body,
                    type,
                });
            }

            const response = await csrfFetch(routes.issue, {
                method: 'POST',
                headers,
                body,
            });

            if (response.status === 419) {
                error.value = translations.value.sessionExpired;
                return;
            }

            if (response.status === 429) {
                error.value = translations.value.rateLimitError;
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                error.value = data.error || translations.value.issueCreationError;
                return;
            }

            const data: IssueResponse = await response.json();

            issueUrl.value = data.url;
            issueNumber.value = data.number;
        } catch {
            error.value = translations.value.networkError;
        } finally {
            isLoading.value = false;
        }
    }

    function reset(): void {
        messages.value = [];
        isLoading.value = false;
        isComplete.value = false;
        issueUrl.value = null;
        issueNumber.value = null;
        error.value = null;
        structuredData.value = null;
        screenshot.value = null;
        if (screenshotPreview.value) {
            URL.revokeObjectURL(screenshotPreview.value);
        }
        screenshotPreview.value = null;
        feedbackType.value = 'bug';
    }

    return {
        messages,
        isLoading,
        isComplete,
        issueUrl,
        issueNumber,
        error,
        structuredData,
        screenshot,
        screenshotPreview,
        isOpen,
        feedbackType,
        translations,
        sendMessage,
        createIssue,
        reset,
        setScreenshot,
        clearScreenshot,
    };
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function refreshCsrfToken(): Promise<boolean> {
    try {
        const routes = getRoutes();
        await fetch(routes.csrf, { credentials: 'same-origin' });
        return true;
    } catch {
        return false;
    }
}

async function csrfFetch(url: string, init: RequestInit): Promise<Response> {
    const doFetch = () => {
        const headers: Record<string, string> = {
            ...(init.headers as Record<string, string>),
            'X-XSRF-TOKEN': getCsrfToken(),
        };
        return fetch(url, { ...init, headers, credentials: 'same-origin' });
    };

    const response = await doFetch();

    if (response.status === 419 && (await refreshCsrfToken())) {
        return doFetch();
    }

    return response;
}
