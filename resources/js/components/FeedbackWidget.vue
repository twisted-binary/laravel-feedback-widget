<script setup lang="ts">
import { MessageSquarePlus } from 'lucide-vue-next';
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
        <div class="fixed right-6 bottom-6 z-50">
            <!-- Chat panel -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 scale-95 opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="translate-y-2 scale-95 opacity-0"
            >
                <div v-show="isOpen" class="mb-3">
                    <FeedbackChatPanel @close="isOpen = false" />
                </div>
            </Transition>

            <!-- FAB button -->
            <button
                class="bg-primary text-primary-foreground hover:bg-primary/90 flex h-12 w-12 items-center justify-center rounded-full shadow-lg transition-transform hover:scale-105 focus:ring-2 focus:ring-offset-2 focus:outline-none"
                :aria-label="isOpen ? translations.closeFeedback : translations.sendFeedback"
                @click="isOpen = !isOpen"
            >
                <MessageSquarePlus class="h-5 w-5" />
            </button>
        </div>
    </Teleport>
</template>
