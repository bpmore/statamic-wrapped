<script setup>
import { ref } from 'vue';
import { router } from '@statamic/cms/inertia';
import { Button } from '@statamic/cms/ui';

/*
 * Public links for one Wrapped. Only mounted when sharing is switched on and
 * this user may do it, so nothing here has to explain that it is off.
 */
const props = defineProps({
    share: { type: Object, required: true },
    periodKey: { type: String, required: true },
});

// The form for a new link. People cards are off until ticked, every time:
// a public page naming who did what is a decision per link, not a habit.
const people = ref(false);
const days = ref('');
const busy = ref(false);

const create = () => {
    busy.value = true;
    router.post(
        props.share.createUrl,
        { period: props.periodKey, people: people.value, days: days.value === '' ? null : Number(days.value) },
        { preserveScroll: true, onFinish: () => { busy.value = false; people.value = false; } },
    );
};

const revoke = (link) => {
    router.delete(link.revokeUrl, { preserveScroll: true });
};

// Which link was last copied, so its button can say so.
const copied = ref(null);

const copy = async (link) => {
    try {
        await navigator.clipboard.writeText(link.url);
    } catch (e) {
        // Refused clipboard access: the URL is on the page to select by hand.
        return;
    }

    copied.value = link.id;
    setTimeout(() => {
        if (copied.value === link.id) copied.value = null;
    }, 2000);
};

const dateOf = (iso) => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
</script>

<template>
    <section class="wrapped-panel wrapped-share" aria-labelledby="wrapped-share-heading">
        <h2 id="wrapped-share-heading" class="wrapped-share__title">Share publicly</h2>
        <p class="wrapped-muted">
            A page anyone with the link can open, frozen as this Wrapped is now. Hidden from search engines.
            You can take a link back at any time.
        </p>

        <!-- The links that exist -->
        <ul v-if="share.links.length" class="wrapped-share__list" aria-label="Live links">
            <li v-for="link in share.links" :key="link.id" class="wrapped-share__link">
                <a :href="link.url" class="wrapped-share__url" target="_blank" rel="noopener">{{ link.url }}</a>
                <span class="wrapped-muted wrapped-share__meta">
                    Made {{ dateOf(link.createdAt) }}<template v-if="link.expiresAt">, stops {{ dateOf(link.expiresAt) }}</template><template v-else>, does not expire</template><template v-if="link.people">, names people</template>.
                </span>
                <span class="wrapped-share__actions">
                    <button type="button" class="wrapped-link" @click="copy(link)">{{ copied === link.id ? 'Copied' : 'Copy link' }}</button>
                    <button type="button" class="wrapped-link" @click="revoke(link)">Revoke</button>
                </span>
            </li>
        </ul>
        <p v-else class="wrapped-muted wrapped-share__none">No public links for this Wrapped yet.</p>

        <!-- A new one -->
        <form class="wrapped-share__form" @submit.prevent="create">
            <label v-if="share.canPeople" class="wrapped-share__option">
                <input type="checkbox" v-model="people">
                <span>
                    Include the cards about people
                    <span class="wrapped-muted">Who did what, on a public page. Off unless you tick it, for each link.</span>
                </span>
            </label>

            <label class="wrapped-share__option wrapped-share__expiry">
                <span>Stops working after</span>
                <select v-model="days" class="wrapped-share__select">
                    <option value="">Never, until revoked</option>
                    <option value="7">7 days</option>
                    <option value="30">30 days</option>
                    <option value="90">90 days</option>
                    <option value="365">A year</option>
                </select>
            </label>

            <Button type="submit" variant="primary" text="Make a public link" :disabled="busy" />
        </form>
    </section>
</template>

<style>
/* Shares the `wrapped-*` vocabulary from the page; see the note there. */
.wrapped-share {
    margin-top: 2.5rem;
}

.wrapped-share__title {
    font-size: 1.125rem;
    font-weight: 500;
    margin: 0;
}

.wrapped-share__list {
    list-style: none;
    margin: 1.25rem 0 0;
    padding: 0;
}

.wrapped-share__link {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem 0;
    border-top: 1px solid var(--color-gray-200);
}

.dark .wrapped-share__link {
    border-color: var(--color-gray-700);
}

.wrapped-share__url {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.875rem;
    word-break: break-all;
    text-decoration: underline;
}

.wrapped-share__meta {
    margin: 0;
}

.wrapped-share__actions {
    display: flex;
    gap: 1rem;
}

.wrapped-share__none {
    margin-top: 1.25rem;
}

.wrapped-share__form {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
}

.wrapped-share__option {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    cursor: pointer;
}

.wrapped-share__option > input {
    margin-top: 0.3rem;
}

.wrapped-share__option .wrapped-muted {
    display: block;
}

.wrapped-share__expiry {
    align-items: center;
}

.wrapped-share__select {
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--color-gray-300);
    border-radius: 0.375rem;
    background: transparent;
    color: inherit;
}

.dark .wrapped-share__select {
    border-color: var(--color-gray-700);
}
</style>
