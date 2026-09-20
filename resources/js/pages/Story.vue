<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@statamic/cms/inertia';

const props = defineProps({
    theme: { type: Object, required: true },
    site: { type: String, required: true },
    label: { type: String, required: true },
    backUrl: { type: String, required: true },
    frames: { type: Array, required: true },
    tracks: { type: Array, default: () => [] },
    defaultTrack: { type: String, default: null },
});

// --- Where we are ---------------------------------------------------------

const index = ref(0);
const frame = computed(() => props.frames[index.value]);
const last = computed(() => index.value === props.frames.length - 1);
const first = computed(() => index.value === 0);

// The reader sets the pace. Nothing here advances on a timer, which is what
// makes this the accessible form of the video.
const next = () => { if (!last.value) index.value++; };
const back = () => { if (!first.value) index.value--; };

// --- Making the change audible --------------------------------------------

// Focus lands on the new frame each time, so a screen reader reads it. The
// frame is tabindex="-1": reachable by script, not in the tab order.
const stage = ref(null);

watch(index, async () => {
    await nextTick();
    stage.value?.focus();
});

// --- Keys -------------------------------------------------------------------

// Deliberately few keys. Home, End, Page Up and Page Down are how a screen
// reader user moves around a page, and Backspace once meant "go back" in
// browsers; taking those over would break more than it helps. Arrows and
// Space are the slideshow convention and nothing else needs them here.
const onKey = (e) => {
    // Leave the controls alone: Enter on "Done" should follow the link, and
    // Space on a checkbox should tick it.
    if (e.target?.closest?.('.story-controls, .story-music')) return;

    if (['ArrowRight', ' ', 'Enter'].includes(e.key)) { e.preventDefault(); next(); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); back(); }
    else if (e.key === 'Escape') { window.location.href = props.backUrl; }
};

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

// --- Music, off until asked -------------------------------------------------

// Off by default. Autoplay with sound is blocked by browsers anyway, and a
// page that starts singing at someone is bad manners.
const music = ref(false);
const track = ref(props.defaultTrack);
const audio = ref(null);
const showControls = ref(false);

const canPlay = (t) => {
    const probe = document.createElement('audio');
    const type = t.mimeType === 'audio/mp4' ? 'audio/mp4; codecs="opus"' : t.mimeType;
    return probe.canPlayType(type) !== '';
};

const playable = computed(() => props.tracks.filter(canPlay));

const applyMusic = () => {
    if (!audio.value) {
        audio.value = new Audio();
        audio.value.loop = true;
    }

    const t = playable.value.find((x) => x.handle === track.value);

    if (!music.value || !t) { audio.value.pause(); return; }

    if (audio.value.src !== t.url) audio.value.src = t.url;
    audio.value.play().catch(() => { music.value = false; });
};

watch([music, track], applyMusic);
onBeforeUnmount(() => audio.value?.pause());

// --- Motion, only if welcome ------------------------------------------------

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// --- Room for the control panel's own header ---------------------------------

// The story is pinned to the viewport, but the control panel's header is
// pinned too and its own stacking context puts it on top: without this the
// progress bars sat behind it. Measured rather than guessed, in case a site
// or a Statamic release changes the header's height.
const headerHeight = ref(0);

onMounted(() => {
    const header = document.querySelector('header');
    headerHeight.value = header && getComputedStyle(header).position === 'fixed'
        ? header.getBoundingClientRect().height
        : 0;
});

// --- Look -------------------------------------------------------------------

// The same theme as the images and the video, handed over as CSS variables.
// Text and muted colors were chosen server-side for contrast; the accent is
// only present if it could be read.
const themeVars = computed(() => ({
    '--story-bg': props.theme.background,
    '--story-text': props.theme.text,
    '--story-muted': props.theme.muted,
    '--story-label': props.theme.accent ?? props.theme.muted,
    '--story-top': `${headerHeight.value}px`,
    // Controls sit on the background; tint them from the text color.
    '--story-rgb': props.theme.text === '#f7f7f8' || props.theme.text === '#ffffff' ? '247, 247, 248' : '22, 22, 29',
}));
</script>

<template>
    <div class="story" :class="{ 'story--still': reducedMotion }" :style="themeVars">
        <Head :title="`${label} · story`" />

        <!-- Progress: one bar per frame, the story-app convention. -->
        <div class="story-progress" role="progressbar" :aria-valuenow="index + 1" :aria-valuemin="1" :aria-valuemax="frames.length" :aria-valuetext="`${index + 1} of ${frames.length}`">
            <span v-for="(f, i) in frames" :key="i" class="story-progress__bar" :class="{ 'is-done': i < index, 'is-current': i === index }"></span>
        </div>

        <!--
            The stage. Click or tap anywhere on it to advance; it is also the
            focus target when the frame changes, so a screen reader reads the
            new fact without being asked. Not a button: a button would swallow
            the text inside it as its own label.
        -->
        <div
            ref="stage"
            class="story-stage"
            tabindex="-1"
            role="region"
            :aria-label="`Frame ${index + 1} of ${frames.length}`"
            @click="next"
        >
            <transition :name="reducedMotion ? '' : 'story-fade'" mode="out-in">
                <div :key="index" class="story-frame">
                    <template v-if="frame.kind === 'card'">
                        <p class="story-heading">{{ frame.heading }}</p>
                        <p class="story-body">{{ frame.body }}</p>
                    </template>
                    <template v-else>
                        <img v-if="theme.logo" class="story-logo" :src="theme.logo" alt="">
                        <p class="story-title">{{ frame.title }}</p>
                        <p v-if="frame.subtitle" class="story-subtitle">{{ frame.subtitle }}</p>
                    </template>
                </div>
            </transition>
        </div>

        <!-- Pinned, exactly as in the video. -->
        <div class="story-footer" aria-hidden="true">
            <span class="story-footer__period">{{ label }}</span>
            <span>{{ site }}</span>
        </div>

        <!--
            Visible controls too. Tapping and arrow keys are the fast path, but
            a switch user or anyone who has not guessed the convention needs
            buttons that say what they do.
        -->
        <div class="story-controls" @click.stop>
            <button type="button" class="story-button" :disabled="first" @click="back" aria-label="Previous">‹</button>

            <button type="button" class="story-button story-button--text" @click="showControls = !showControls" :aria-expanded="showControls">
                {{ music ? 'Music on' : 'Music off' }}
            </button>

            <a :href="backUrl" class="story-button story-button--text">Done</a>

            <button type="button" class="story-button" :disabled="last" @click="next" aria-label="Next">›</button>
        </div>

        <div v-if="showControls" class="story-music" @click.stop>
            <label class="story-music__toggle">
                <input type="checkbox" v-model="music"> Play music
            </label>

            <p v-if="playable.length === 0" class="story-music__note">This browser cannot play the bundled tracks.</p>

            <label v-for="t in playable" :key="t.handle" class="story-music__track">
                <input type="radio" name="story-track" :value="t.handle" v-model="track">
                <span>{{ t.name }}</span>
                <span v-if="t.description" class="story-music__desc">{{ t.description }}</span>
            </label>
        </div>
    </div>
</template>

<style scoped>
/* The same palette and proportions as the video frames, so the story and the
   film are recognizably the same thing. Contrast 5.34:1 for the gray, checked. */
.story {
    position: fixed;
    inset: var(--story-top, 0) 0 0 0;
    z-index: 50;
    background: var(--story-bg);
    color: var(--story-text);
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}

.story-progress {
    display: flex;
    gap: 6px;
    padding: 16px 20px 0;
}

.story-progress__bar {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: rgba(var(--story-rgb), 0.2);
}

.story-progress__bar.is-done,
.story-progress__bar.is-current {
    background: var(--story-text);
}

.story-stage {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 32px 120px;
    cursor: pointer;
    outline: none;
}

.story-stage:focus-visible {
    box-shadow: inset 0 0 0 3px rgba(var(--story-rgb), 0.5);
}

.story-frame {
    max-width: 720px;
    width: 100%;
}

.story-heading {
    font-size: clamp(14px, 1.6vw, 18px);
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--story-label);
    margin-bottom: 20px;
}

.story-body {
    font-size: clamp(28px, 5vw, 52px);
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

.story-title {
    font-size: clamp(36px, 7vw, 72px);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.03em;
}

.story-subtitle {
    font-size: clamp(16px, 2vw, 22px);
    color: var(--story-muted);
    margin-top: 16px;
}

.story-logo {
    height: clamp(40px, 8vw, 72px);
    width: auto;
    margin-bottom: 32px;
}

.story-footer {
    position: absolute;
    left: 32px;
    right: 32px;
    bottom: 88px;
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--story-muted);
    pointer-events: none;
}

.story-footer__period {
    font-weight: 600;
    color: var(--story-text);
}

.story-controls {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(transparent, var(--story-bg));
}

.story-button {
    min-width: 44px;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 22px;
    border: 1px solid rgba(var(--story-rgb), 0.25);
    background: rgba(var(--story-rgb), 0.06);
    color: var(--story-text);
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.story-button--text {
    font-size: 14px;
}

.story-button:disabled {
    opacity: 0.35;
    cursor: default;
}

.story-button:focus-visible {
    outline: 3px solid var(--story-text);
    outline-offset: 2px;
}

.story-music {
    position: absolute;
    left: 50%;
    bottom: 84px;
    transform: translateX(-50%);
    width: min(360px, calc(100% - 32px));
    padding: 16px;
    border-radius: 12px;
    background: var(--story-bg);
    border: 1px solid rgba(var(--story-rgb), 0.25);
    font-size: 14px;
}

.story-music__toggle,
.story-music__track {
    display: flex;
    gap: 10px;
    align-items: baseline;
    padding: 6px 0;
    cursor: pointer;
}

.story-music__desc,
.story-music__note {
    color: var(--story-muted);
    font-size: 13px;
}

.story-fade-enter-active,
.story-fade-leave-active {
    transition: opacity 0.35s ease;
}

.story-fade-enter-from,
.story-fade-leave-to {
    opacity: 0;
}

.story--still .story-frame {
    transition: none;
}
</style>
