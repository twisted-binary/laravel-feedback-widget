<script setup lang="ts">
import { useFeedbackChat } from '../composables/useFeedbackChat';
import { CheckCircle, ImagePlus, Loader2, Send, Star, X } from './icons';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const emit = defineEmits<{
    close: [];
}>();

const {
    messages,
    isLoading,
    isComplete,
    issueUrl,
    error,
    screenshotPreview,
    isOpen,
    feedbackType,
    translations,
    sendMessage,
    createIssue,
    reset,
    setScreenshot,
    clearScreenshot,
} = useFeedbackChat();

const feedbackNoun = computed(() => {
    return {
        bug: translations.value.bugNoun,
        feature: translations.value.featureNoun,
        feedback: translations.value.feedbackNoun,
    }[feedbackType.value];
});
const inputMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);

const starRating = ref(0);
const starHover = ref(0);

const isBugType = computed(() => feedbackType.value === 'bug');
const isFeedbackType = computed(() => feedbackType.value === 'feedback');
const showStarRating = computed(() => isFeedbackType.value && messages.value.length === 0 && !issueUrl.value);
const fileInput = ref<HTMLInputElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

const typeOptions = computed(() => [
    { value: 'bug' as const, label: translations.value.bugLabel, icon: '\uD83D\uDC1B' },
    { value: 'feature' as const, label: translations.value.featureLabel, icon: '\u2728' },
    { value: 'feedback' as const, label: translations.value.feedbackLabel, icon: '\uD83D\uDCAC' },
]);

const emptyStateDescription = computed(() => {
    if (isFeedbackType.value) {
        return translations.value.emptyStateDescriptionFeedback;
    }
    return translations.value.emptyStateDescription.replace('{noun}', feedbackNoun.value ?? '');
});

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        setScreenshot(file);
    }
    target.value = '';
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
                setScreenshot(file);
            }
            return;
        }
    }
}

function autoResizeTextarea(): void {
    const textarea = textareaRef.value;
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
}

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
    await nextTick();
    autoResizeTextarea();
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

function getFocusableElements(): HTMLElement[] {
    if (!panelRef.value) return [];
    return Array.from(
        panelRef.value.querySelectorAll<HTMLElement>(
            'button:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    );
}

function handlePanelKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');
        return;
    }

    if (event.key === 'Tab') {
        const focusable = getFocusableElements();
        if (focusable.length === 0) return;

        const first = focusable[0]!;
        const last = focusable[focusable.length - 1]!;

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

function handleNewConversation(): void {
    reset();
    inputMessage.value = '';
    starRating.value = 0;
    starHover.value = 0;
}

onMounted(() => {
    textareaRef.value?.addEventListener('paste', handlePaste);
});

onBeforeUnmount(() => {
    textareaRef.value?.removeEventListener('paste', handlePaste);
});

watch(isOpen, (open) => {
    if (open) {
        nextTick(() => textareaRef.value?.focus());
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
    <div
        ref="panelRef"
        role="dialog"
        aria-modal="true"
        class="tbfw-panel"
        @keydown="handlePanelKeydown"
    >
        <!-- Header -->
        <div class="tbfw-header">
            <h3 class="tbfw-header-title">{{ translations.header }}</h3>
            <button
                :aria-label="translations.closeFeedback"
                class="tbfw-icon-btn"
                @click="emit('close')"
            >
                <X class="tbfw-icon-sm" />
            </button>
        </div>

        <!-- Type Toggle -->
        <div v-if="!issueUrl" class="tbfw-type-toggle">
            <button
                v-for="opt in typeOptions"
                :key="opt.value"
                :aria-selected="feedbackType === opt.value"
                :class="['tbfw-type-btn', feedbackType === opt.value && 'tbfw-type-btn--active']"
                :disabled="messages.length > 0"
                @click="feedbackType = opt.value"
            >
                {{ opt.icon }} {{ opt.label }}
            </button>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" aria-live="polite" class="tbfw-messages">
            <!-- Empty state -->
            <div v-if="messages.length === 0 && !isLoading" class="tbfw-empty-state">
                <p class="tbfw-empty-title">
                    {{ isFeedbackType ? translations.emptyStateTitleFeedback : translations.emptyStateTitle }}
                </p>
                <p class="tbfw-empty-desc">{{ emptyStateDescription }}</p>

                <!-- Star rating for feedback type -->
                <div v-if="showStarRating" class="tbfw-star-row">
                    <button
                        v-for="star in 5"
                        :key="star"
                        :aria-label="'Rate ' + star + ' out of 5 stars'"
                        :aria-pressed="starRating === star"
                        class="tbfw-star-btn"
                        @click="starRating = star"
                        @mouseenter="starHover = star"
                        @mouseleave="starHover = 0"
                    >
                        <Star
                            :class="['tbfw-star-icon', star <= (starHover || starRating) ? 'tbfw-star--filled' : 'tbfw-star--empty']"
                        />
                    </button>
                </div>
            </div>

            <!-- Message bubbles -->
            <div
                v-for="(msg, index) in messages"
                :key="index"
                :class="['tbfw-msg-row', msg.role === 'user' ? 'tbfw-msg-row--end' : 'tbfw-msg-row--start']"
            >
                <div
                    :class="['tbfw-bubble', msg.role === 'user' ? 'tbfw-bubble--user' : 'tbfw-bubble--assistant']"
                >
                    <p class="tbfw-bubble-text">{{ msg.content }}</p>
                </div>
            </div>

            <!-- Loading skeleton -->
            <div v-if="isLoading" class="tbfw-msg-row tbfw-msg-row--start">
                <div class="tbfw-skeleton-wrap">
                    <div class="tbfw-skeleton tbfw-skeleton--wide" />
                    <div class="tbfw-skeleton tbfw-skeleton--narrow" />
                </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="tbfw-error">
                {{ error }}
            </div>
        </div>

        <!-- Success state -->
        <div v-if="issueUrl" class="tbfw-success">
            <CheckCircle class="tbfw-success-icon" />
            <p class="tbfw-success-text">{{ translations.successMessage }}</p>
            <div class="tbfw-success-actions">
                <button class="tbfw-btn-outline" @click="handleNewConversation">
                    {{ translations.startOver }}
                </button>
                <button class="tbfw-btn-ghost" @click="emit('close')">
                    {{ translations.close }}
                </button>
            </div>
        </div>

        <!-- Input -->
        <div v-else class="tbfw-input-area">
            <!-- Screenshot preview -->
            <div v-if="screenshotPreview" class="tbfw-screenshot-preview">
                <img
                    :src="screenshotPreview"
                    :alt="translations.screenshotPreview"
                    class="tbfw-screenshot-img"
                />
                <button class="tbfw-screenshot-remove" @click="clearScreenshot">
                    <X class="tbfw-icon-xs" />
                </button>
            </div>

            <div class="tbfw-input-row">
                <textarea
                    ref="textareaRef"
                    v-model="inputMessage"
                    :disabled="isLoading || isComplete"
                    :placeholder="isComplete ? translations.creatingIssuePlaceholder : translations.inputPlaceholder"
                    rows="1"
                    class="tbfw-textarea"
                    @keydown="handleKeydown"
                    @input="autoResizeTextarea"
                />
                <input ref="fileInput" type="file" accept="image/*" class="tbfw-sr-only" @change="handleFileChange" />
                <button
                    v-if="isBugType"
                    :disabled="isLoading || isComplete"
                    aria-label="Attach screenshot"
                    class="tbfw-icon-btn tbfw-input-btn"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="tbfw-icon-sm" />
                </button>
                <button
                    :disabled="!inputMessage.trim() || isLoading || isComplete"
                    :aria-label="translations.sendFeedback"
                    class="tbfw-send-btn"
                    @click="handleSend"
                >
                    <Loader2 v-if="isLoading" class="tbfw-icon-sm tbfw-spin" />
                    <Send v-else class="tbfw-icon-sm" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Panel ─── */
.tbfw-panel {
    display: flex;
    flex-direction: column;
    width: 380px;
    height: 480px;
    border-radius: var(--tbfw-radius);
    border: 1px solid var(--tbfw-border);
    background-color: var(--tbfw-bg);
    color: var(--tbfw-fg);
    box-shadow: var(--tbfw-shadow);
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
}

/* ─── Header ─── */
.tbfw-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--tbfw-border);
}

.tbfw-header-title {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
}

/* ─── Type Toggle ─── */
.tbfw-type-toggle {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
    padding: 0.5rem;
    border-bottom: 1px solid var(--tbfw-border);
}

.tbfw-type-btn {
    flex: 1;
    padding: 0.375rem 0.5rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 500;
    transition: background-color 0.15s, color 0.15s;
    color: var(--tbfw-muted-fg);
    background: transparent;
}

.tbfw-type-btn:hover:not(:disabled) {
    background-color: var(--tbfw-accent);
    color: var(--tbfw-accent-fg);
}

.tbfw-type-btn:disabled {
    cursor: default;
    opacity: 0.5;
}

.tbfw-type-btn--active {
    background-color: var(--tbfw-primary);
    color: var(--tbfw-primary-fg);
}

.tbfw-type-btn--active:hover:not(:disabled) {
    background-color: var(--tbfw-primary);
    color: var(--tbfw-primary-fg);
}

/* ─── Messages ─── */
.tbfw-messages {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.75rem;
    overflow-y: auto;
    padding: 1rem;
}

/* ─── Empty State ─── */
.tbfw-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    height: 100%;
    text-align: center;
    color: var(--tbfw-muted-fg);
}

.tbfw-empty-title {
    font-weight: 500;
    margin: 0;
}

.tbfw-empty-desc {
    font-size: 0.75rem;
    margin: 0;
}

/* ─── Star Rating ─── */
.tbfw-star-row {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.tbfw-star-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    transition: transform 0.15s;
}

.tbfw-star-btn:hover {
    transform: scale(1.1);
}

.tbfw-star-icon {
    width: 1.75rem;
    height: 1.75rem;
}

.tbfw-star--filled {
    color: #facc15;
    fill: #facc15;
}

.tbfw-star--empty {
    color: var(--tbfw-muted-fg);
    opacity: 0.4;
}

/* ─── Message Bubbles ─── */
.tbfw-msg-row {
    display: flex;
}

.tbfw-msg-row--end {
    justify-content: flex-end;
}

.tbfw-msg-row--start {
    justify-content: flex-start;
}

.tbfw-bubble {
    max-width: 85%;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}

.tbfw-bubble--user {
    background-color: var(--tbfw-primary);
    color: var(--tbfw-primary-fg);
}

.tbfw-bubble--assistant {
    background-color: var(--tbfw-muted);
    color: var(--tbfw-fg);
}

.tbfw-bubble-text {
    margin: 0;
    white-space: pre-wrap;
}

/* ─── Loading Skeleton ─── */
.tbfw-skeleton-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-width: 85%;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    background-color: var(--tbfw-muted);
}

.tbfw-skeleton {
    height: 0.75rem;
    border-radius: 0.25rem;
    background-color: var(--tbfw-muted-fg);
    opacity: 0.2;
    animation: tbfw-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.tbfw-skeleton--wide {
    width: 12rem;
}

.tbfw-skeleton--narrow {
    width: 8rem;
}

@keyframes tbfw-pulse {
    0%,
    100% {
        opacity: 0.2;
    }
    50% {
        opacity: 0.1;
    }
}

/* ─── Error ─── */
.tbfw-error {
    border-radius: 0.5rem;
    border: 1px solid #fecaca;
    background-color: #fef2f2;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    color: #b91c1c;
}

@media (prefers-color-scheme: dark) {
    .tbfw-error {
        border-color: #991b1b;
        background-color: #450a0a;
        color: #fca5a5;
    }
}

/* ─── Success State ─── */
.tbfw-success {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    padding: 1rem;
    border-top: 1px solid var(--tbfw-border);
}

.tbfw-success-icon {
    width: 2rem;
    height: 2rem;
    color: #22c55e;
}

.tbfw-success-text {
    font-size: 0.875rem;
    font-weight: 500;
    margin: 0;
}

.tbfw-success-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.25rem;
}

/* ─── Buttons ─── */
.tbfw-btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    border: 1px solid var(--tbfw-input);
    background-color: var(--tbfw-bg);
    color: var(--tbfw-fg);
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}

.tbfw-btn-outline:hover {
    background-color: var(--tbfw-accent);
    color: var(--tbfw-accent-fg);
}

.tbfw-btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    border: none;
    background: transparent;
    color: var(--tbfw-muted-fg);
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}

.tbfw-btn-ghost:hover {
    background-color: var(--tbfw-accent);
    color: var(--tbfw-accent-fg);
}

.tbfw-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.375rem;
    border: none;
    background: transparent;
    color: var(--tbfw-muted-fg);
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}

.tbfw-icon-btn:hover {
    background-color: var(--tbfw-accent);
    color: var(--tbfw-accent-fg);
}

/* ─── Input Area ─── */
.tbfw-input-area {
    flex-shrink: 0;
    padding: 0.75rem;
    border-top: 1px solid var(--tbfw-border);
}

.tbfw-input-row {
    display: flex;
    gap: 0.5rem;
}

.tbfw-textarea {
    flex: 1;
    min-height: 2.25rem;
    max-height: 100px;
    resize: none;
    border-radius: 0.375rem;
    border: 1px solid var(--tbfw-input);
    background-color: var(--tbfw-bg);
    color: var(--tbfw-fg);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-family: inherit;
    line-height: 1.5;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.tbfw-textarea::placeholder {
    color: var(--tbfw-muted-fg);
}

.tbfw-textarea:focus {
    border-color: var(--tbfw-ring);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--tbfw-ring) 25%, transparent);
}

.tbfw-textarea:disabled {
    opacity: 0.5;
    cursor: default;
}

.tbfw-input-btn {
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
}

.tbfw-input-btn:disabled {
    pointer-events: none;
    opacity: 0.5;
}

.tbfw-send-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    background-color: var(--tbfw-primary);
    color: var(--tbfw-primary-fg);
    transition: opacity 0.15s;
}

.tbfw-send-btn:hover {
    opacity: 0.9;
}

.tbfw-send-btn:disabled {
    pointer-events: none;
    opacity: 0.5;
}

/* ─── Screenshot Preview ─── */
.tbfw-screenshot-preview {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.tbfw-screenshot-img {
    height: 4rem;
    width: auto;
    border-radius: 0.25rem;
    border: 1px solid var(--tbfw-border);
    object-fit: cover;
}

.tbfw-screenshot-remove {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.125rem;
    border-radius: 9999px;
    color: var(--tbfw-muted-fg);
    transition: color 0.15s;
}

.tbfw-screenshot-remove:hover {
    color: var(--tbfw-fg);
}

/* ─── Icons ─── */
.tbfw-icon-sm {
    width: 1rem;
    height: 1rem;
}

.tbfw-icon-xs {
    width: 0.75rem;
    height: 0.75rem;
}

/* ─── Utilities ─── */
.tbfw-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

.tbfw-spin {
    animation: tbfw-spin 1s linear infinite;
}

@keyframes tbfw-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
