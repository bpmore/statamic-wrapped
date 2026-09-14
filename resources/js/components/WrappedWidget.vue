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
    <div v-if="visible" class="wrapped-widget">
        <div>
            <h2 class="wrapped-widget__title">{{ title }}</h2>
            <p class="wrapped-widget__headline">{{ headline }}</p>
            <a :href="url" class="wrapped-widget__link">Take a look</a>
        </div>

        <button
            v-if="nudge"
            type="button"
            class="wrapped-widget__dismiss"
            aria-label="Dismiss"
            @click="dismiss"
        >
            &times;
        </button>
    </div>
</template>

<style>
/* Written out, not utilities: see the note on the Wrapped page. */
.wrapped-widget {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--color-gray-200);
    border-radius: 0.5rem;
}

.dark .wrapped-widget {
    border-color: var(--color-gray-700);
}

.wrapped-widget__title {
    font-weight: 500;
    margin: 0;
}

.wrapped-widget__headline {
    font-size: 0.875rem;
    color: var(--color-gray-600);
    margin: 0.25rem 0 0;
}

.dark .wrapped-widget__headline {
    color: var(--color-gray-400);
}

.wrapped-widget__link {
    display: inline-block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    text-decoration: underline;
}

.wrapped-widget__dismiss {
    background: none;
    border: 0;
    padding: 0 0.25rem;
    font-size: 1.25rem;
    line-height: 1;
    color: var(--color-gray-500);
    cursor: pointer;
}

.wrapped-widget__dismiss:hover {
    color: var(--color-gray-800);
}

.dark .wrapped-widget__dismiss:hover {
    color: var(--color-gray-200);
}
</style>
