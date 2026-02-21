<script setup lang="ts">
import { MessageSquarePlus } from './icons';
import FeedbackChatPanel from './FeedbackChatPanel.vue';
import { useFeedbackChat } from '../composables/useFeedbackChat';
import type { FeedbackTranslations } from '../translations';

const props = defineProps<{
    translations?: Partial<FeedbackTranslations>;
}>();

const { isOpen, translations } = useFeedbackChat({ translations: props.translations });
</script>

<template>
    <Teleport to="body">
        <div class="tbfw-widget tbfw-anchor">
            <!-- Chat panel -->
            <Transition name="tbfw-panel">
                <div v-show="isOpen" class="tbfw-panel-wrapper">
                    <FeedbackChatPanel @close="isOpen = false" />
                </div>
            </Transition>

            <!-- FAB button -->
            <button
                class="tbfw-fab"
                :aria-label="isOpen ? translations.closeFeedback : translations.sendFeedback"
                @click="isOpen = !isOpen"
            >
                <MessageSquarePlus class="tbfw-icon-md" />
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.tbfw-widget {
    --tbfw-primary: #18181b;
    --tbfw-primary-fg: #fff;
    --tbfw-bg: #fff;
    --tbfw-fg: #09090b;
    --tbfw-muted: #f4f4f5;
    --tbfw-muted-fg: #71717a;
    --tbfw-border: #e4e4e7;
    --tbfw-input: #e4e4e7;
    --tbfw-ring: #18181b;
    --tbfw-accent: #f4f4f5;
    --tbfw-accent-fg: #18181b;
    --tbfw-radius: 0.75rem;
    --tbfw-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

.tbfw-anchor {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 50;
}

.tbfw-panel-wrapper {
    margin-bottom: 0.75rem;
}

.tbfw-fab {
    display: flex;
    height: 3rem;
    width: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: none;
    cursor: pointer;
    background-color: var(--tbfw-primary);
    color: var(--tbfw-primary-fg);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    transition: transform 0.15s ease;
}

.tbfw-fab:hover {
    opacity: 0.9;
    transform: scale(1.05);
}

.tbfw-fab:focus {
    outline: none;
    box-shadow:
        0 0 0 2px var(--tbfw-bg),
        0 0 0 4px var(--tbfw-ring);
}

.tbfw-icon-md {
    width: 1.25rem;
    height: 1.25rem;
}

/* Panel transition */
.tbfw-panel-enter-active {
    transition: all 0.2s ease-out;
}

.tbfw-panel-leave-active {
    transition: all 0.15s ease-in;
}

.tbfw-panel-enter-from,
.tbfw-panel-leave-to {
    opacity: 0;
    transform: translateY(0.5rem) scale(0.95);
}

.tbfw-panel-enter-to,
.tbfw-panel-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}
</style>
