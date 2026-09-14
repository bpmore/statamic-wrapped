<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@statamic/cms/ui';

const props = defineProps({
    video: { type: Object, required: true },
    videoUrl: { type: String, required: true },
});

// --- Which cards ---------------------------------------------------------

const ticked = ref(props.video.choices.filter((c) => c.selected).map((c) => c.handle));

const chosen = computed(() =>
    // In screen order regardless of the order boxes were ticked, so the video
    // reads the way the page does.
    props.video.choices.filter((c) => ticked.value.includes(c.handle)),
);

// The same arithmetic as the server: every frame's time, less one crossfade
// per join. Intro and outro are title cards on either end.
const seconds = computed(() => {
    const frames = [props.video.titleSeconds, ...chosen.value.map((c) => c.seconds), props.video.titleSeconds];
    const total = frames.reduce((sum, s) => sum + s, 0) - (frames.length - 1) * props.video.crossfade;

    return Math.round(total);
});

const overLong = computed(() => seconds.value > 30);

// --- Which track ---------------------------------------------------------

const track = ref(props.video.defaultTrack);

const audio = ref(null);
const playing = ref(null);

// Safari only learned Opus-in-M4A recently, and the bundled files are exactly
// that. Ask rather than assume, and say so instead of showing a broken player.
const canPreview = (t) => {
    const probe = document.createElement('audio');
    const type = t.mimeType === 'audio/mp4' ? 'audio/mp4; codecs="opus"' : t.mimeType;

    return probe.canPlayType(type) !== '';
};

const preview = (t) => {
    if (!audio.value) audio.value = new Audio();

    if (playing.value === t.handle) {
        audio.value.pause();
        playing.value = null;
        return;
    }

    audio.value.src = t.previewUrl;
    audio.value.currentTime = 0;
    audio.value.play().then(() => { playing.value = t.handle; }).catch(() => { playing.value = null; });
    audio.value.onended = () => { playing.value = null; };
};

onBeforeUnmount(() => audio.value?.pause());
watch(track, () => { audio.value?.pause(); playing.value = null; });

// --- The download --------------------------------------------------------

const downloadUrl = computed(() => {
    const params = new URLSearchParams();
    chosen.value.forEach((c) => params.append('cards[]', c.handle));
    if (track.value) params.set('track', track.value);

    return `${props.videoUrl}?${params.toString()}`;
});

// --- Its description -----------------------------------------------------

// Server-authored sentences, joined here in the order they will play.
const description = computed(() =>
    [props.video.descriptionLead, ...chosen.value.map((c) => `${c.heading}: ${c.body}`)].join(' '),
);

const copied = ref(false);

const copyDescription = async () => {
    try {
        await navigator.clipboard.writeText(description.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (e) {
        // Refused; the text is on the page to select by hand.
    }
};
</script>

<template>
    <section class="wrapped-panel wrapped-video" aria-labelledby="wrapped-video-heading">
        <h2 id="wrapped-video-heading" class="wrapped-video__title">Make a video</h2>
        <p class="wrapped-muted">
            One fact per screen, music underneath, sized for Reels, Stories and TikTok. Downloaded, never hosted.
        </p>

        <!-- Cards -->
        <fieldset class="wrapped-video__group">
            <legend class="wrapped-video__legend">Which cards</legend>

            <ul class="wrapped-video__list">
                <li v-for="c in video.choices" :key="c.handle">
                    <label class="wrapped-video__option">
                        <input type="checkbox" :value="c.handle" v-model="ticked">
                        <span>
                            <span class="wrapped-video__name">{{ c.heading }}</span>
                            <!--
                                A leading space inside the text, not just a
                                margin: without it a screen reader runs the
                                two together as "The teamnames people".
                            -->
                            <span v-if="c.people" class="wrapped-video__badge"> (names people)</span>
                            <span class="wrapped-muted">{{ c.body }}</span>
                        </span>
                    </label>
                </li>
            </ul>

            <!--
                Live, because it is the one number that decides whether anyone
                watches to the end. aria-live so a screen reader hears it change
                too.
            -->
            <p class="wrapped-video__readout" aria-live="polite">
                <span v-if="chosen.length === 0">Pick at least one card.</span>
                <span v-else>About {{ seconds }} seconds.</span>
                <span v-if="overLong" class="wrapped-video__warn">
                    Over thirty; attention on short-video platforms runs out around there.
                </span>
            </p>
        </fieldset>

        <!-- Music -->
        <fieldset class="wrapped-video__group" v-if="video.tracks.length">
            <legend class="wrapped-video__legend">Music</legend>

            <ul class="wrapped-video__list">
                <li v-for="t in video.tracks" :key="t.handle" class="wrapped-video__track">
                    <label class="wrapped-video__option">
                        <input type="radio" name="wrapped-track" :value="t.handle" v-model="track">
                        <span>
                            <span class="wrapped-video__name">{{ t.name }}</span>
                            <span v-if="t.description" class="wrapped-muted">{{ t.description }}</span>
                        </span>
                    </label>

                    <button
                        v-if="canPreview(t)"
                        type="button"
                        class="wrapped-link"
                        :aria-pressed="playing === t.handle"
                        @click="preview(t)"
                    >{{ playing === t.handle ? 'Stop' : 'Preview' }}</button>
                    <span v-else class="wrapped-muted wrapped-video__unsupported">Preview not supported in this browser</span>
                </li>
            </ul>
        </fieldset>

        <!-- Download -->
        <div class="wrapped-video__group">
            <Button v-if="chosen.length" :href="downloadUrl" variant="primary" text="Download video" />
            <span v-else class="wrapped-muted">Pick a card to make a video.</span>
            <p class="wrapped-muted wrapped-video__hint">Takes about half a minute to make. The file is {{ video.filename }}.</p>
        </div>

        <!-- Description -->
        <details v-if="chosen.length" class="wrapped-details">
            <summary>Suggested description for posting</summary>
            <p class="wrapped-muted">{{ description }}</p>
            <button type="button" class="wrapped-link" @click="copyDescription">{{ copied ? 'Copied' : 'Copy' }}</button>
        </details>
    </section>
</template>

<style>
/* Shares the `wrapped-*` vocabulary from the page; see the note there. */
.wrapped-video {
    margin-top: 2.5rem;
}

.wrapped-video__title {
    font-size: 1.125rem;
    font-weight: 500;
    margin: 0;
}

.wrapped-video__group {
    margin-top: 1.5rem;
    padding: 0;
    border: 0;
}

.wrapped-video__legend {
    font-size: 0.875rem;
    font-weight: 500;
    padding: 0;
}

.wrapped-video__list {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
}

.wrapped-video__list > li + li {
    margin-top: 0.5rem;
}

.wrapped-video__option {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    flex: 1;
}

.wrapped-video__option > input {
    margin-top: 0.25rem;
}

.wrapped-video__option .wrapped-muted {
    display: block;
    margin: 0;
}

.wrapped-video__name {
    font-weight: 500;
}

.wrapped-video__badge {
    margin-left: 0.5rem;
    font-size: 0.75rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    background: #fefce8;
    color: #713f12;
}

.dark .wrapped-video__badge {
    background: rgba(113, 63, 18, 0.2);
    color: #fef08a;
}

.wrapped-video__track {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.wrapped-video__unsupported {
    font-size: 0.75rem;
    flex-shrink: 0;
}

.wrapped-video__readout {
    font-size: 0.875rem;
    margin: 0.75rem 0 0;
}

.wrapped-video__warn {
    color: #713f12;
}

.dark .wrapped-video__warn {
    color: #fef08a;
}

.wrapped-video__hint {
    font-size: 0.75rem;
    margin-top: 0.5rem;
}
</style>
