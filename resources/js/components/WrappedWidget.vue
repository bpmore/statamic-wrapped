<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    key: { type: String, required: true },
    title: { type: String, required: true },
    headline: { type: String, required: true },
    url: { type: String, required: true },
    nudge: { type: Boolean, default: false },
});

// Dismissal is a per-browser convenience, not data worth a table and an
// endpoint. Keyed by period so next year's Wrapped nudges again on its own.
const storageKey = `wrapped.dismissed.${props.key}`;

const readStorage = () => {
    try {
        return window.localStorage.getItem(storageKey) === '1';
    } catch (e) {
        return false;
    }
};

const dismissed = ref(readStorage());

const dismiss = () => {
    dismissed.value = true;

    try {
        window.localStorage.setItem(storageKey, '1');
    } catch (e) {
        // Private windows and blocked site data. Hiding it for this page view
        // is still the right outcome.
    }
};

// Only the December nudge can be dismissed. The quiet link the rest of the
// year is not in anybody's way.
const visible = computed(() => !props.nudge || !dismissed.value);
</script>

<template>
    <div v-if="visible" class="p-4 border rounded-lg flex items-start justify-between gap-4">
        <div>
            <h2 class="font-medium">{{ title }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ headline }}</p>
            <a :href="url" class="text-sm underline mt-2 inline-block">Take a look</a>
        </div>

        <button
            v-if="nudge"
            type="button"
            class="text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
            aria-label="Dismiss"
            @click="dismiss"
        >
            &times;
        </button>
    </div>
</template>
