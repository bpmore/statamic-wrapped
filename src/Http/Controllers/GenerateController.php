<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Snapshots\NoReadableHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\SnapshotBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Building a Wrapped from the control panel.
 *
 * The same work as `php please wrapped:generate`, for the site the control
 * panel is looking at, then straight to the Wrapped it built. Always a
 * rebuild: someone who chose a period and pressed the button wants what is
 * there now, not the note that one already exists.
 */
class GenerateController extends CpController
{
    public function store(Request $request, SnapshotBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::enum(Period::class)],
            'year' => ['required', 'integer', 'min:1970', 'max:9999'],
            'part' => ['nullable', 'integer'],
        ]);

        $period = Period::from($data['period']);
        $year = (int) $data['year'];
        $part = isset($data['part']) ? (int) $data['part'] : null;

        // A quarter or a month needs its number; a year must not have one.
        // Checked here rather than with a rule per case so the message says
        // what was wrong in words.
        if ($period === Period::Year ? $part !== null : ($part === null || ! $period->hasPart($part))) {
            throw ValidationException::withMessages([
                'part' => match ($period) {
                    Period::Year => 'A year has no part.',
                    Period::Quarter => 'Choose a quarter, 1 to 4.',
                    Period::Month => 'Choose a month, 1 to 12.',
                },
            ]);
        }

        $site = Site::selected()->handle();

        try {
            $result = $builder->build($period, $year, $part, $site, force: true, by: $this->actor());
        } catch (NoReadableHistory $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }

        Toast::success(__('wrapped::messages.generate.built', ['label' => $result->snapshot->label()]));

        return redirect(cp_route('wrapped.index', ['period' => $result->snapshot->period_key]));
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
}
