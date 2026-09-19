<?php

namespace Bpmore\Wrapped\Stats;

/**
 * Who a Wrapped is talking to.
 *
 * In the control panel it talks to the editor: "you published 42 entries".
 * On a public page that reader did not publish anything, so the same card
 * reads "we published 42 entries", the site speaking for itself. The
 * wording is chosen when a link is made and frozen with it, like the rest.
 */
enum Voice: string
{
    case You = 'you';
    case We = 'we';

    /**
     * A translation key in this voice, or the plain key when the wording
     * does not change. `you` is the plain key, because it always existed.
     */
    public function key(string $key): string
    {
        if ($this === self::You) {
            return $key;
        }

        $ours = "{$key}_{$this->value}";

        return __("wrapped::{$ours}") !== "wrapped::{$ours}" ? $ours : $key;
    }
}
