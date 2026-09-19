<?php

namespace Bpmore\Wrapped\Stats;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Statamic\Facades\User;
use Statamic\Support\Str;

/**
 * Turns a stored card into a heading and a sentence.
 *
 * Cards store numbers and handles, never prose — a month is `7`, a term is a
 * slug, a contributor is a user id. That keeps the snapshot language-neutral
 * and lets a name or a term title change without rewriting history. The cost is
 * that somebody has to turn those back into English, and this is that somebody.
 *
 * Rendering happens here rather than in Vue so the wording lives in a normal
 * Laravel translation file, and so the control panel screen — the canonical,
 * accessible version — is plain text a screen reader can read straight through.
 */
class CardPresenter
{
    public function __construct(protected CardGate $gate) {}

    /**
     * Only the cards this viewer may see. The gate lives here rather than in
     * each caller because the screen, the widget, the image export and the alt
     * text all present cards, and a new caller must not be able to forget it.
     *
     * The voice is who the sentence is addressed to: the editor in the
     * control panel, everyone on a public page. See `Voice`.
     *
     * @param  array<string, array<string, mixed>>  $stats
     * @return list<array{handle: string, heading: string, body: string}>
     */
    public function present(array $stats, Voice $voice = Voice::You): array
    {
        $cards = [];

        foreach ($this->gate->visible($stats) as $handle => $data) {
            $key = $this->bodyKey($handle, $data);

            if (! $this->translated("cards.{$handle}.heading")) {
                // A card with no wording is a card we would render as its own
                // translation key. Better to leave it out and notice.
                continue;
            }

            $cards[] = [
                'handle' => $handle,
                'heading' => __('wrapped::'.$voice->key("cards.{$handle}.heading")),
                'body' => __('wrapped::'.$voice->key("cards.{$key}"), $this->replacements($handle, $data)),
            ];
        }

        return $cards;
    }

    /**
     * Cards that read differently with a period to compare against.
     *
     * @param  array<string, mixed>  $data
     */
    protected function bodyKey(string $handle, array $data): string
    {
        $comparable = in_array($handle, ['entries_published', 'fastest_growing_collection'], true);

        return $comparable && ($data['previous'] ?? null) !== null
            ? "{$handle}.compared"
            : "{$handle}.body";
    }

    /**
     * Numbers become readable here, not in the card: thousands separators, a
     * month name in the reader's language, a duration as "42 minutes".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function replacements(string $handle, array $data): array
    {
        $replacements = match ($handle) {
            'busiest_month' => ['month' => $this->monthName($data)],
            'busiest_week' => ['week' => $this->date($data['starts_on'] ?? null)],
            'busiest_time' => [
                'day' => $this->dayName($data),
                'hour' => $this->hour($data),
            ],
            'longest_streak' => [
                'from' => $this->date($data['from'] ?? null),
                'to' => $this->date($data['to'] ?? null),
            ],
            'assets_uploaded' => ['size' => Str::fileSizeForHumans((int) ($data['bytes'] ?? 0), 1)],
            'fastest_turnaround' => ['duration' => $this->duration((int) ($data['seconds'] ?? 0))],
            'longest_untouched' => ['date' => $this->date($data['last_touched'] ?? null)],
            'top_taxonomy_term' => ['term' => (string) ($data['term'] ?? '')],
            'top_contributor' => ['name' => $this->userName($data)],
            default => [],
        };

        foreach ($data as $key => $value) {
            if (! isset($replacements[$key]) && (is_int($value) || is_float($value))) {
                $replacements[$key] = number_format($value);
            } elseif (! isset($replacements[$key]) && is_string($value)) {
                $replacements[$key] = $value;
            }
        }

        return $replacements;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function monthName(array $data): string
    {
        $year = (int) ($data['year'] ?? CarbonImmutable::now()->year);
        $month = (int) ($data['month'] ?? 1);

        return CarbonImmutable::createMidnightDate($year, $month, 1)->translatedFormat('F Y');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function dayName(array $data): string
    {
        // ISO day, 1 being Monday. Carbon translates it for us.
        $day = (int) ($data['day'] ?? 1);

        return CarbonImmutable::now()->startOfWeek()->addDays($day - 1)->translatedFormat('l');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hour(array $data): string
    {
        return CarbonImmutable::now()->setTime((int) ($data['hour'] ?? 0), 0)->translatedFormat('g a');
    }

    protected function date(mixed $date): string
    {
        return is_string($date) && $date !== ''
            ? CarbonImmutable::parse($date)->translatedFormat('j F Y')
            : '';
    }

    protected function duration(int $seconds): string
    {
        return CarbonInterval::seconds(max($seconds, 0))->cascade()->forHumans(['short' => false, 'parts' => 2]);
    }

    /**
     * The snapshot stores a user id so a name can change without rewriting
     * history. If that user is gone, their id is not a thing to show a reader.
     *
     * @param  array<string, mixed>  $data
     */
    protected function userName(array $data): string
    {
        $id = $data['author'] ?? null;

        if (! is_string($id)) {
            return '';
        }

        $user = User::find($id);

        if ($user === null) {
            return '';
        }

        // Statamic's user contract only guarantees email(). A display name is
        // a data field, present on the file and eloquent drivers but not
        // promised by the interface, so it is asked for rather than assumed.
        if (method_exists($user, 'name') && is_string($name = $user->name()) && $name !== '') {
            return $name;
        }

        $email = $user->email();

        return is_string($email) ? $email : '';
    }

    protected function translated(string $key): bool
    {
        return __("wrapped::{$key}") !== "wrapped::{$key}";
    }
}
