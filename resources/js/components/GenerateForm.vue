<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@statamic/cms/inertia';
import { Button, Input, Select } from '@statamic/cms/ui';

/*
 * Build a Wrapped from the screen: the same thing as `php please
 * wrapped:generate`, for the site the control panel is looking at. Only
 * mounted for someone with the permission, so nothing here has to explain
 * that it is off.
 */
const props = defineProps({
    // { url, year, quarter, month }: where to post, and today's values.
    generate: { type: Object, required: true },
    // The period keys this site already has, so the button can say "Rebuild".
    existing: { type: Array, default: () => [] },
});

const period = ref('year');
const year = ref(props.generate.year);
const quarter = ref(props.generate.quarter);
const month = ref(props.generate.month);
const busy = ref(false);

const periodOptions = [
    { label: 'A year', value: 'year' },
    { label: 'A quarter', value: 'quarter' },
    { label: 'A month', value: 'month' },
];

const quarterOptions = [1, 2, 3, 4].map((q) => ({ label: `Q${q}`, value: q }));

// Month names in the browser's language, which is what the picker on the
// screen already uses for the labels it shows.
const monthOptions = Array.from({ length: 12 }, (_, i) => ({
    label: new Date(2000, i, 1).toLocaleDateString(undefined, { month: 'long' }),
    value: i + 1,
}));

// The stored key for what is chosen, in the same shape the server writes:
// 2026, 2026-Q3, 2026-09. Used only to say Build or Rebuild.
const key = computed(() => {
    if (period.value === 'quarter') return `${year.value}-Q${quarter.value}`;
    if (period.value === 'month') return `${year.value}-${String(month.value).padStart(2, '0')}`;

    return String(year.value);
});

const exists = computed(() => props.existing.includes(key.value));

const label = computed(() => {
    const y = year.value;

    if (period.value === 'quarter') return `Q${quarter.value} ${y}`;
    if (period.value === 'month') return `${monthOptions[month.value - 1].label} ${y}`;

    return String(y);
});

// Validation messages come back on the page's errors, keyed by field.
const errors = computed(() => usePage().props.errors ?? {});

const submit = () => {
    busy.value = true;

    router.post(
        props.generate.url,
        {
            period: period.value,
            year: Number(year.value),
            part: period.value === 'quarter' ? quarter.value : period.value === 'month' ? month.value : null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
};
</script>

<template>
    <form class="wrapped-generate" @submit.prevent="submit">
        <div class="wrapped-generate__fields">
            <label class="wrapped-generate__field">
                <span class="wrapped-generate__label">Build</span>
                <Select v-model="period" :options="periodOptions" :disabled="busy" />
            </label>

            <label class="wrapped-generate__field wrapped-generate__field--year">
                <span class="wrapped-generate__label">Year</span>
                <Input v-model="year" type="number" min="1970" max="9999" required :disabled="busy" />
            </label>

            <label v-if="period === 'quarter'" class="wrapped-generate__field">
                <span class="wrapped-generate__label">Quarter</span>
                <Select v-model="quarter" :options="quarterOptions" :disabled="busy" />
            </label>

            <label v-if="period === 'month'" class="wrapped-generate__field">
                <span class="wrapped-generate__label">Month</span>
                <Select v-model="month" :options="monthOptions" :disabled="busy" />
            </label>

            <div class="wrapped-generate__submit">
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="busy"
                    :text="busy ? 'Building…' : `${exists ? 'Rebuild' : 'Build'} ${label}`"
                />
            </div>
        </div>

        <!-- role=status so a screen reader hears the outcome without hunting for it. -->
        <p v-if="errors.period || errors.year || errors.part" class="wrapped-generate__error" role="status">
            {{ errors.period || errors.year || errors.part }}
        </p>
        <p v-else class="wrapped-muted">
            Reads every entry on the site, so it takes a moment on a large one.
            <template v-if="exists">Rebuilding replaces the {{ label }} Wrapped that is already here; public links to it are unaffected.</template>
        </p>
    </form>
</template>

<style>
.wrapped-generate__fields {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
}

.wrapped-generate__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 9rem;
}

.wrapped-generate__field--year {
    min-width: 6rem;
    max-width: 7rem;
}

.wrapped-generate__label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-gray-600);
}

.dark .wrapped-generate__label {
    color: var(--color-gray-400);
}

.wrapped-generate__submit {
    display: flex;
    align-items: flex-end;
}

.wrapped-generate__error {
    font-size: 0.875rem;
    margin: 0.5rem 0 0;
    color: #b91c1c;
}

.dark .wrapped-generate__error {
    color: #fca5a5;
}
</style>
