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

        <!--
            A snapshot exists but every card was left out. On a site with only
            file modification times this is the honest outcome, and it needs a
            real explanation rather than a blank page.
        -->
        <div v-else-if="snapshot.cards.length === 0" class="p-6 border rounded-lg">
            <h2 class="font-medium mb-2">Not enough history to say anything yet</h2>
            <p class="text-sm">
                This Wrapped was built from {{ snapshot.historySource }}, which cannot support
                any of the cards on its own.
            </p>
        </div>

        <!--
            A description list rather than a grid of divs: each card is a term
            and its explanation, which is what a screen reader should hear.
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
    </div>
</template>
