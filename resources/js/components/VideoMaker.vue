<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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
    <section class="mt-10 p-5 border rounded-lg" aria-labelledby="wrapped-video-heading">
        <h2 id="wrapped-video-heading" class="font-medium text-lg">Make a video</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            One fact per screen, music underneath, sized for Reels, Stories and TikTok. Downloaded, never hosted.
        </p>

        <!-- Cards -->
        <fieldset class="mt-6">
            <legend class="text-sm font-medium">Which cards</legend>

            <ul class="mt-2 space-y-2">
                <li v-for="c in video.choices" :key="c.handle">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" :value="c.handle" v-model="ticked" class="mt-1">
                        <span>
                            <span class="font-medium">{{ c.heading }}</span>
                            <!--
                                A leading space inside the text, not just a
                                margin: without it a screen reader runs the
                                two together as "The teamnames people".
                            -->
                            <span v-if="c.people" class="ml-2 text-xs px-1.5 py-0.5 rounded bg-yellow-50 dark:bg-yellow-900/20 text-yellow-900 dark:text-yellow-200"> (names people)</span>
                            <span class="block text-sm text-gray-600 dark:text-gray-400">{{ c.body }}</span>
                        </span>
                    </label>
                </li>
            </ul>

            <!--
                Live, because it is the one number that decides whether anyone
                watches to the end. aria-live so a screen reader hears it change
                too.
            -->
            <p class="mt-3 text-sm" aria-live="polite">
                <span v-if="chosen.length === 0">Pick at least one card.</span>
                <span v-else>About {{ seconds }} seconds.</span>
                <span v-if="overLong" class="text-yellow-900 dark:text-yellow-200">
                    Over thirty; attention on short-video platforms runs out around there.
                </span>
            </p>
        </fieldset>

        <!-- Music -->
        <fieldset class="mt-6" v-if="video.tracks.length">
            <legend class="text-sm font-medium">Music</legend>

            <ul class="mt-2 space-y-2">
                <li v-for="t in video.tracks" :key="t.handle" class="flex items-start gap-3">
                    <label class="flex items-start gap-3 cursor-pointer flex-1">
                        <input type="radio" name="wrapped-track" :value="t.handle" v-model="track" class="mt-1">
                        <span>
                            <span class="font-medium">{{ t.name }}</span>
                            <span v-if="t.description" class="block text-sm text-gray-600 dark:text-gray-400">{{ t.description }}</span>
                        </span>
                    </label>

                    <button
                        v-if="canPreview(t)"
                        type="button"
                        class="text-sm underline shrink-0"
                        :aria-pressed="playing === t.handle"
                        @click="preview(t)"
                    >{{ playing === t.handle ? 'Stop' : 'Preview' }}</button>
                    <span v-else class="text-xs text-gray-500 shrink-0">Preview not supported in this browser</span>
                </li>
            </ul>
        </fieldset>

        <!-- Download -->
        <div class="mt-6">
            <a
                v-if="chosen.length"
                :href="downloadUrl"
                class="inline-block px-4 py-2 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm font-medium"
            >Download video</a>
            <span v-else class="text-sm text-gray-500">Pick a card to make a video.</span>
            <p class="text-xs text-gray-500 mt-2">Takes about half a minute to make. The file is {{ video.filename }}.</p>
        </div>

        <!-- Description -->
        <details v-if="chosen.length" class="mt-4">
            <summary class="text-sm cursor-pointer text-gray-600 dark:text-gray-400">Suggested description for posting</summary>
            <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">{{ description }}</p>
            <button type="button" class="text-sm underline mt-2" @click="copyDescription">{{ copied ? 'Copied' : 'Copy' }}</button>
        </details>
    </section>
</template>
