<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Sharing\Share;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Bpmore\Wrapped\Sharing\YouTube;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\Voice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Making and revoking public links, from the control panel.
 *
 * Both actions land back on the Wrapped screen for the same period, where
 * the list of live links is. There is no separate management screen: a link
 * belongs with the Wrapped it shows.
 */
class ShareController extends CpController
{
    public function store(Request $request, ShareLinks $links, YouTube $youtube): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'max:20'],
            'people' => ['sometimes', 'boolean'],
            'voice' => ['sometimes', Rule::enum(Voice::class)],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.ShareLinks::MAX_DAYS],
            'music' => ['nullable', 'string', 'max:500'],
        ]);

        // Asked of YouTube before the snapshot is looked up, so a bad link is
        // a validation error on the form and never a half-made share.
        $music = isset($data['music']) && trim($data['music']) !== ''
            ? $youtube->resolve($data['music'])
            : null;

        $snapshot = Snapshot::query()
            ->where('site', Site::selected()->handle())
            ->where('period_key', $data['period'])
            ->first()
            ?? throw new NotFoundHttpException("There is no [{$data['period']}] Wrapped to share.");

        try {
            $links->publish(
                $snapshot,
                $snapshot->label(),
                people: (bool) ($data['people'] ?? false),
                days: isset($data['days']) ? (int) $data['days'] : null,
                by: $this->actor(),
                voice: isset($data['voice']) ? Voice::from($data['voice']) : Voice::We,
                music: $music,
            );
        } catch (RuntimeException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        }

        return redirect(cp_route('wrapped.index', ['period' => $snapshot->period_key]));
    }

    /**
     * Same identifier the generate command records, for the same reason:
     * the contract has no `id()`, only what Authenticatable gives it.
     */
    protected function actor(): ?string
    {
        $id = User::current()?->getAuthIdentifier();

        return $id === null ? null : (string) $id;
    }

    /**
     * Revoking is soft: the row stays, marked, so the control panel can still
     * say a link existed and when it was pulled. The public page is gone
     * immediately.
     */
    public function destroy(ShareLinks $links, Share $share): RedirectResponse
    {
        // Never another site's link, even with the id.
        if (! $links->canShare() || $share->site !== Site::selected()->handle()) {
            throw new NotFoundHttpException('There is no such link.');
        }

        $share->revoke();

        return redirect(cp_route('wrapped.index', ['period' => $share->period_key]));
    }
}
