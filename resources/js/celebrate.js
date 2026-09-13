import confetti from 'canvas-confetti';

/**
 * Confetti on first view. Once, not every time — SPEC.md §5.
 *
 * Kept out of the page component so the decision is one plain function with
 * every reason it might say no in one place:
 *
 *  - nothing to celebrate: an empty Wrapped gets an explanation, not a party
 *  - already seen: keyed by period, so next year's Wrapped celebrates again
 *  - reduced motion: a burst of moving particles is exactly what that setting
 *    is asking not to see, and the Wrapped is complete without it
 *
 * "Seen" lives in localStorage, per browser, the same as the widget's
 * dismissal. It is a nicety, not data.
 */
export function shouldCelebrate(periodKey, cardCount) {
    if (!periodKey || cardCount === 0) {
        return false;
    }

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        return false;
    }

    try {
        return window.localStorage.getItem(storageKey(periodKey)) !== '1';
    } catch (e) {
        // No storage means no way to remember, and celebrating on every visit
        // is the wrong side of that coin.
        return false;
    }
}

export function markCelebrated(periodKey) {
    try {
        window.localStorage.setItem(storageKey(periodKey), '1');
    } catch (e) {
        // Nothing to do; shouldCelebrate() already declines without storage.
    }
}

export function celebrate() {
    // Two short bursts from the lower corners, then done. Enough to be a
    // moment, not enough to be a screensaver.
    const common = { particleCount: 90, spread: 70, startVelocity: 45, ticks: 220, zIndex: 9999 };

    confetti({ ...common, angle: 60, origin: { x: 0, y: 0.9 } });
    confetti({ ...common, angle: 120, origin: { x: 1, y: 0.9 } });
}

function storageKey(periodKey) {
    return `wrapped.celebrated.${periodKey}`;
}
