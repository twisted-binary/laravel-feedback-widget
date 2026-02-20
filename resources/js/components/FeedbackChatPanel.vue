<script setup lang="ts">
import { useFeedbackChat } from '../composables/useFeedbackChat';
import { CheckCircle, ImagePlus, Loader2, Send, Star, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const emit = defineEmits<{
    close: [];
}>();

const { messages, isLoading, isComplete, issueUrl, error, screenshot, feedbackType, sendMessage, createIssue, reset } = useFeedbackChat();

const feedbackNoun = computed(() => {
    return { bug: 'issue', feature: 'idea', feedback: 'feedback' }[feedbackType.value];
});
const inputMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);

const starRating = ref(0);
const starHover = ref(0);

const isBugType = computed(() => feedbackType.value === 'bug');
const isFeedbackType = computed(() => feedbackType.value === 'feedback');
const showStarRating = computed(() => isFeedbackType.value && messages.value.length === 0 && !issueUrl.value);
const fileInput = ref<HTMLInputElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const screenshotPreview = ref<string | null>(null);

function handleScreenshotSelect(file: File): void {
    screenshot.value = file;
    screenshotPreview.value = URL.createObjectURL(file);
}

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        handleScreenshotSelect(file);
    }
    target.value = '';
}

function removeScreenshot(): void {
    screenshot.value = null;
    if (screenshotPreview.value) {
        URL.revokeObjectURL(screenshotPreview.value);
        screenshotPreview.value = null;
    }
}

function handlePaste(event: ClipboardEvent): void {
    if (!isBugType.value) {
        return;
    }
    const items = event.clipboardData?.items;
    if (!items) {
        return;
    }
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            event.preventDefault();
            const file = item.getAsFile();
            if (file) {
                handleScreenshotSelect(file);
            }
            return;
        }
    }
}

const typeOptions = [
    { value: 'bug' as const, label: 'Bug', icon: '🐛' },
    { value: 'feature' as const, label: 'Feature', icon: '✨' },
    { value: 'feedback' as const, label: 'Feedback', icon: '💬' },
];

async function handleSend(): Promise<void> {
    const message = inputMessage.value.trim();
    if (!message || isLoading.value || isComplete.value) {
        return;
    }

    let fullMessage = message;
    if (isFeedbackType.value && messages.value.length === 0 && starRating.value > 0) {
        fullMessage = `Rating: ${starRating.value}/5 stars\n\n${message}`;
    }

    inputMessage.value = '';
    await sendMessage(fullMessage, feedbackType.value);

    if (isComplete.value) {
        await createIssue(feedbackType.value);
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
}

function handleNewConversation(): void {
    removeScreenshot();
    reset();
    inputMessage.value = '';
    starRating.value = 0;
    starHover.value = 0;
}

onMounted(() => {
    textareaRef.value?.addEventListener('paste', handlePaste);
    if (screenshot.value && !screenshotPreview.value) {
        screenshotPreview.value = URL.createObjectURL(screenshot.value);
    }
});

onBeforeUnmount(() => {
    textareaRef.value?.removeEventListener('paste', handlePaste);
    if (screenshotPreview.value) {
        URL.revokeObjectURL(screenshotPreview.value);
    }
});

watch(
    messages,
    async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    },
    { deep: true },
);
</script>

<template>
    <div class="flex h-[480px] w-[380px] flex-col rounded-xl border border-border bg-background shadow-xl">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between border-b border-border px-4 py-3">
            <h3 class="text-sm font-semibold">Send Feedback</h3>
            <button
                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                @click="emit('close')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Type Toggle -->
        <div v-if="!issueUrl" class="flex shrink-0 gap-1 border-b border-border p-2">
            <button
                v-for="opt in typeOptions"
                :key="opt.value"
                :class="[
                    'flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                    feedbackType === opt.value
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                ]"
                :disabled="messages.length > 0"
                @click="feedbackType = opt.value"
            >
                {{ opt.icon }} {{ opt.label }}
            </button>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
            <!-- Empty state -->
            <div
                v-if="messages.length === 0 && !isLoading"
                class="flex h-full flex-col items-center justify-center gap-1 text-center text-sm text-muted-foreground"
            >
                <p class="font-medium">{{ isFeedbackType ? 'How are we doing?' : 'How can we help?' }}</p>
                <p class="text-xs">
                    {{
                        isFeedbackType
                            ? 'Rate your experience and tell us what you think.'
                            : `Describe your ${feedbackNoun} and we'll guide you through the details.`
                    }}
                </p>

                <!-- Star rating for feedback type -->
                <div v-if="showStarRating" class="mt-2 flex gap-1">
                    <button
                        v-for="star in 5"
                        :key="star"
                        class="transition-transform hover:scale-110"
                        @click="starRating = star"
                        @mouseenter="starHover = star"
                        @mouseleave="starHover = 0"
                    >
                        <Star
                            class="h-7 w-7"
                            :class="star <= (starHover || starRating) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground/40'"
                        />
                    </button>
                </div>
            </div>

            <!-- Message bubbles -->
            <div v-for="(msg, index) in messages" :key="index" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                <div
                    :class="[
                        'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                        msg.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground',
                    ]"
                >
                    <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                </div>
            </div>

            <!-- Loading skeleton -->
            <div v-if="isLoading" class="flex justify-start">
                <div class="flex max-w-[85%] flex-col gap-2 rounded-lg bg-muted px-3 py-2">
                    <div class="h-3 w-48 animate-pulse rounded bg-muted-foreground/20" />
                    <div class="h-3 w-32 animate-pulse rounded bg-muted-foreground/20" />
                </div>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300"
            >
                {{ error }}
            </div>
        </div>

        <!-- Success state -->
        <div v-if="issueUrl" class="flex shrink-0 flex-col items-center gap-3 border-t border-border p-4">
            <CheckCircle class="h-8 w-8 text-green-500" />
            <p class="text-sm font-medium">Thank you for your feedback!</p>
            <div class="mt-1 flex gap-2">
                <button
                    class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                    @click="handleNewConversation"
                >
                    Start over
                </button>
                <button
                    class="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>
        </div>

        <!-- Input -->
        <div v-else class="shrink-0 border-t border-border p-3">
            <!-- Screenshot preview -->
            <div v-if="screenshotPreview" class="mb-2 inline-flex items-start gap-1">
                <img :src="screenshotPreview" alt="Screenshot preview" class="h-16 w-auto rounded border border-border object-cover" />
                <button class="rounded-full p-0.5 text-muted-foreground hover:text-foreground" @click="removeScreenshot">
                    <X class="h-3 w-3" />
                </button>
            </div>

            <div class="flex gap-2">
                <textarea
                    ref="textareaRef"
                    v-model="inputMessage"
                    :disabled="isLoading || isComplete"
                    :placeholder="isComplete ? 'Creating issue...' : 'Type your message...'"
                    rows="1"
                    class="max-h-[100px] min-h-[36px] flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground transition-shadow outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    @keydown="handleKeydown"
                />
                <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
                <button
                    v-if="isBugType"
                    :disabled="isLoading || isComplete"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="h-4 w-4" />
                </button>
                <button
                    :disabled="!inputMessage.trim() || isLoading || isComplete"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50"
                    @click="handleSend"
                >
                    <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
