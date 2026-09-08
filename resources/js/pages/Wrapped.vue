<script setup>
import { Head } from '@statamic/cms/inertia';

defineProps({
    site: { type: String, required: true },
    snapshot: { type: Object, default: null },
});
</script>

<template>
    <div class="max-w-5xl mx-auto">
        <Head title="Wrapped" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold">Wrapped</h1>
            <p v-if="snapshot" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ snapshot.periodKey }} &middot; {{ site }}
            </p>
        </header>

        <!--
            No snapshot at all. Not an error state: nobody has run the command
            yet, and saying so plainly beats an empty page.
        -->
        <div v-if="!snapshot" class="p-6 border rounded-lg">
            <h2 class="font-medium mb-2">Nothing here yet</h2>
            <p class="text-sm">
                Run <code>php please wrapped:generate</code> to build one.
            </p>
        </div>

        <template v-else>
            <!--
                The confidence notice. Always present, so a Wrapped never
                presents itself without saying what it was built from. Quiet
                when everything worked, plain when it did not. It is a <p>, not
                an alert: thin history is not an error and not the reader's
                fault.
            -->
            <p
                class="text-sm mb-6 px-4 py-3 rounded-lg"
                :class="snapshot.history.limited
                    ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-900 dark:text-yellow-200'
                    : 'text-gray-600 dark:text-gray-400'"
            >
                {{ snapshot.history.notice }}
                <span v-if="snapshot.history.suggestion">{{ snapshot.history.suggestion }}</span>
            </p>

            <!--
                A snapshot whose cards were all left out. The notice above has
                already explained why, so this only has to not look broken.
            -->
            <div v-if="snapshot.cards.length === 0" class="p-6 border rounded-lg">
                <h2 class="font-medium">No cards this time</h2>
            </div>

            <!--
                A description list rather than a grid of divs: each card is a
                term and its explanation, which is what a screen reader should
                hear.
            -->
            <dl v-else class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="card in snapshot.cards"
                    :key="card.handle"
                    class="p-5 border rounded-lg"
                >
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        {{ card.heading }}
                    </dt>
                    <dd class="mt-1 text-lg">{{ card.body }}</dd>
                </div>
            </dl>
        </template>
    </div>
</template>
