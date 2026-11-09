<?php

namespace Bpmore\Wrapped\Export;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One render at a time per site.
 *
 * An image download starts a headless browser on the request thread, and a
 * video adds FFmpeg to it. Anyone with "view wrapped" can ask for either by
 * GET, and ten open tabs are ten browsers. The lock lets one run and
 * answers the rest with 429 and a Retry-After, so the screen stays up while
 * the one render finishes. The route's rate limit is the other half: it
 * caps how often one person may ask at all.
 */
class RenderLock
{
    /** Longer than any render should take. A worker that died mid-render frees the site after this. */
    public const SECONDS = 120;

    /** What a 429 tells the client to wait, in seconds. */
    public const RETRY_AFTER = 15;

    /**
     * @template T
     *
     * @param  Closure(): T  $render
     * @return T
     *
     * @throws RenderBusy another render holds this site
     * @throws Throwable whatever the render itself throws, after the lock is released
     */
    public function run(string $site, Closure $render): mixed
    {
        // A store with no locks (the null driver) cannot serialise; the
        // rate limit on the route still applies.
        if (! Cache::getStore() instanceof LockProvider) {
            return $render();
        }

        $lock = Cache::lock(self::key($site), self::SECONDS);

        if (! $lock->get()) {
            throw new RenderBusy('Another export is rendering for this site. Try again in a moment.');
        }

        try {
            return $render();
        } finally {
            $lock->release();
        }
    }

    public static function key(string $site): string
    {
        return "wrapped:render:{$site}";
    }
}
