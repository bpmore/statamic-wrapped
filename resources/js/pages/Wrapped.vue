<script setup>
import { onMounted, ref } from 'vue';
import { Head } from '@statamic/cms/inertia';
import { Button, Header } from '@statamic/cms/ui';
import { celebrate, markCelebrated, shouldCelebrate } from '../celebrate.js';
import VideoMaker from '../components/VideoMaker.vue';

const props = defineProps({
    site: { type: String, required: true },
    snapshot: { type: Object, default: null },
});

onMounted(() => {
    const key = props.snapshot?.periodKey;
    const cards = props.snapshot?.cards?.length ?? 0;

    if (shouldCelebrate(key, cards)) {
        // Marked before firing, so a thrown error mid-burst still counts as
        // seen rather than replaying on every reload.
        markCelebrated(key);
        celebrate();
    }
});

// Built from the current path so it survives however the control panel is
// mounted, rather than being hardcoded to /cp.
const imageUrl = `${window.location.pathname.replace(/\/$/, '')}/image`;
const videoUrl = `${window.location.pathname.replace(/\/$/, '')}/video`;

// Which alt text was last copied, so the button can confirm it worked.
const copied = ref(null);

const copyAlt = async (key, text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        // Clipboard access can be refused. The text is on the page either way,
        // so selecting it by hand still works.
        return;
    }

    copied.value = key;
    setTimeout(() => {
        if (copied.value === key) copied.value = null;
    }, 2000);
};
</script>

<template>
    <div class="wrapped">
        <Head title="Wrapped" />

        <!--
            Statamic's own header and button, so this sits in the control panel
            the way its native screens do rather than as a visitor.
        -->
        <Header title="Wrapped">
            <!-- The tap-through version: same facts, at the reader's pace. -->
            <Button
                v-if="snapshot && snapshot.cards.length"
                :href="snapshot.storyUrl"
                variant="primary"
                text="Play it"
            />
        </Header>

        <p v-if="snapshot" class="wrapped-subtitle">
            {{ snapshot.label }} &middot; {{ site }}
        </p>

        <!--
            No snapshot at all. Not an error state: nobody has run the command
            yet, and saying so plainly beats an empty page.
        -->
        <div v-if="!snapshot" class="wrapped-panel">
            <h2 class="wrapped-panel__heading">Nothing here yet</h2>
            <p class="wrapped-muted">
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
            <p class="wrapped-notice" :class="{ 'wrapped-notice--limited': snapshot.history.limited }">
                {{ snapshot.history.notice }}
                <span v-if="snapshot.history.suggestion">{{ snapshot.history.suggestion }}</span>
            </p>

            <!--
                A snapshot whose cards were all left out. The notice above has
                already explained why, so this only has to not look broken.
            -->
            <div v-if="snapshot.cards.length === 0" class="wrapped-panel">
                <h2 class="wrapped-panel__heading">No cards this time</h2>
            </div>

            <!--
                A description list rather than a grid of divs: each card is a
                term and its explanation, which is what a screen reader should
                hear.
            -->
            <dl v-else class="wrapped-cards">
                <div v-for="card in snapshot.cards" :key="card.handle" class="wrapped-card">
                    <dt class="wrapped-card__heading">{{ card.heading }}</dt>
                    <dd class="wrapped-card__body">{{ card.body }}</dd>

                    <!--
                        A download, not a link to a hosted image. The picture is
                        a convenience; this page is the real version.
                    -->
                    <div v-if="snapshot.canExport" class="wrapped-card__actions">
                        <a :href="`${imageUrl}/${card.handle}`" class="wrapped-link">Download image</a>

                        <!--
                            Suggested alt text, written out rather than hidden
                            behind the button, so it can be read and edited
                            before it goes anywhere.
                        -->
                        <details class="wrapped-details">
                            <summary>Suggested alt text</summary>
                            <p class="wrapped-muted">{{ card.alt }}</p>
                            <button type="button" class="wrapped-link" @click="copyAlt(card.handle, card.alt)">
                                {{ copied === card.handle ? 'Copied' : 'Copy' }}
                            </button>
                        </details>
                    </div>
                </div>
            </dl>

            <!--
                Only when FFmpeg is there and there is something to put in it.
                No browser, no images; no FFmpeg, no video. The screen is the
                real version either way.
            -->
            <VideoMaker
                v-if="snapshot.video.available && snapshot.cards.length"
                :video="snapshot.video"
                :video-url="videoUrl"
            />

            <div v-if="snapshot.canExport && snapshot.cards.length" class="wrapped-summary">
                <a :href="imageUrl" class="wrapped-link">Download all of it as one image</a>

                <details class="wrapped-details">
                    <summary>Suggested alt text</summary>
                    <p class="wrapped-muted">{{ snapshot.summaryAlt }}</p>
                    <button type="button" class="wrapped-link" @click="copyAlt('summary', snapshot.summaryAlt)">
                        {{ copied === 'summary' ? 'Copied' : 'Copy' }}
                    </button>
                </details>
            </div>
        </template>
    </div>
</template>

<style>
/*
 * Not scoped, and not Tailwind utilities: the control panel ships only the
 * utility classes its own screens happen to use (p-4 and p-6 exist, p-5 does
 * not), so anything layout-critical is written out here. Unscoped so the
 * VideoMaker child can share the same vocabulary. Colours come from the
 * control panel's own tokens, so dark mode follows the site's setting.
 */
.wrapped {
    max-width: 64rem;
    margin: 0 auto;
}


.wrapped-subtitle,
.wrapped-muted {
    color: var(--color-gray-600);
    font-size: 0.875rem;
    margin: 0.25rem 0 0;
}

.wrapped-subtitle {
    margin: -0.75rem 0 1.5rem;
}

.dark .wrapped-subtitle,
.dark .wrapped-muted {
    color: var(--color-gray-400);
}


.wrapped-panel,
.wrapped-card {
    padding: 1.25rem;
    border: 1px solid var(--color-gray-200);
    border-radius: 0.5rem;
}

.dark .wrapped-panel,
.dark .wrapped-card {
    border-color: var(--color-gray-700);
}

.wrapped-panel__heading {
    font-weight: 500;
    margin: 0 0 0.5rem;
}

.wrapped-notice {
    font-size: 0.875rem;
    margin: 0 0 1.5rem;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    color: var(--color-gray-600);
}

.dark .wrapped-notice {
    color: var(--color-gray-400);
}

.wrapped-notice--limited {
    background: #fefce8;
    color: #713f12;
}

.dark .wrapped-notice--limited {
    background: rgba(113, 63, 18, 0.2);
    color: #fef08a;
}

.wrapped-cards {
    display: grid;
    gap: 1rem;
    margin: 0;
}

@media (min-width: 768px) {
    .wrapped-cards {
        grid-template-columns: 1fr 1fr;
    }
}

.wrapped-card {
    display: flex;
    flex-direction: column;
}

.wrapped-card__heading {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-gray-600);
}

.dark .wrapped-card__heading {
    color: var(--color-gray-400);
}

.wrapped-card__body {
    font-size: 1.125rem;
    margin: 0.25rem 0 0;
    flex: 1;
}

.wrapped-card__actions {
    margin-top: 0.75rem;
}

.wrapped-link {
    font-size: 0.875rem;
    text-decoration: underline;
    background: none;
    border: 0;
    padding: 0;
    color: inherit;
    cursor: pointer;
}

.wrapped-details {
    margin-top: 0.5rem;
}

.wrapped-details > summary {
    font-size: 0.875rem;
    cursor: pointer;
    color: var(--color-gray-600);
}

.dark .wrapped-details > summary {
    color: var(--color-gray-400);
}

.wrapped-details > p {
    margin-top: 0.5rem;
}

.wrapped-details > button {
    margin-top: 0.5rem;
}

.wrapped-summary {
    margin-top: 1.5rem;
}
</style>
