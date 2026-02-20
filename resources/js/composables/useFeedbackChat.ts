import { usePage } from '@inertiajs/vue3';
import { type Ref, ref } from 'vue';

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
        };
    };
}

const messages = ref<ChatMessage[]>([]);
const isLoading = ref(false);
const isComplete = ref(false);
const issueUrl = ref<string | null>(null);
const issueNumber = ref<number | null>(null);
const error = ref<string | null>(null);
const structuredData = ref<{ title: string; body: string } | null>(null);
const screenshot: Ref<File | null> = ref(null);
const isOpen = ref(false);
const feedbackType = ref<'bug' | 'feature' | 'feedback'>('bug');

function getRoutes(): { chat: string; issue: string } {
    const page = usePage<FeedbackWidgetProps>();
    return page.props.feedbackWidget.routes;
}

export function useFeedbackChat() {
    async function sendMessage(message: string, type: string): Promise<void> {
        error.value = null;

        messages.value.push({ role: 'user', content: message });

        isLoading.value = true;

        try {
            const routes = getRoutes();

            const response = await fetch(routes.chat, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message,
                    history: messages.value.slice(0, -1),
                    type,
                }),
            });

            if (response.status === 419) {
                error.value = 'Session expired. Please refresh the page.';
                messages.value.pop();
                return;
            }

            if (response.status === 422) {
                const data = await response.json();
                error.value = data.message || 'Validation error. Please check your input.';
                messages.value.pop();
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                error.value = data.error || 'Something went wrong. Please try again.';
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
            error.value = 'Network error. Please check your connection.';
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
                'X-XSRF-TOKEN': getCsrfToken(),
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

            const response = await fetch(routes.issue, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body,
            });

            if (response.status === 419) {
                error.value = 'Session expired. Please refresh the page.';
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                error.value = data.error || 'Failed to create issue. Please try again.';
                return;
            }

            const data: IssueResponse = await response.json();

            issueUrl.value = data.url;
            issueNumber.value = data.number;
        } catch {
            error.value = 'Network error. Please check your connection.';
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
        isOpen,
        feedbackType,
        sendMessage,
        createIssue,
        reset,
    };
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
