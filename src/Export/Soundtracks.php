<?php

namespace Bpmore\Wrapped\Export;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Every soundtrack a video may have: the ones shipped with the addon, plus any
 * the site adds in config.
 *
 * A track whose file is not where it says it is is left out and logged, never
 * offered. Offering it would put a broken choice in front of somebody and fail
 * their download after they made it.
 *
 * Site-supplied tracks come first in the list, on the reasoning that a site
 * which went to the trouble of adding its own music wants to see it.
 */
class Soundtracks
{
    public const BUNDLED_DIRECTORY = __DIR__.'/../../resources/audio';

    public const MANIFEST = self::BUNDLED_DIRECTORY.'/tracks.php';

    /** @var Collection<string, Soundtrack>|null */
    protected ?Collection $all = null;

    /**
     * @return Collection<string, Soundtrack> Keyed by handle.
     */
    public function all(): Collection
    {
        return $this->all ??= $this->site()->merge($this->bundled());
    }

    public function find(string $handle): ?Soundtrack
    {
        return $this->all()->get($handle);
    }

    /**
     * What plays if nobody chooses. The first site track if there are any,
     * else the first bundled one.
     */
    public function default(): ?Soundtrack
    {
        return $this->all()->first();
    }

    public function isEmpty(): bool
    {
        return $this->all()->isEmpty();
    }

    /**
     * @return Collection<string, Soundtrack>
     */
    protected function bundled(): Collection
    {
        if (! (bool) config('wrapped.video.bundled', true) || ! is_file(self::MANIFEST)) {
            return new Collection;
        }

        $manifest = require self::MANIFEST;

        return $this->build(is_array($manifest) ? $manifest : [], self::BUNDLED_DIRECTORY, bundled: true);
    }

    /**
     * @return Collection<string, Soundtrack>
     */
    protected function site(): Collection
    {
        $tracks = config('wrapped.video.tracks', []);

        return $this->build(is_array($tracks) ? $tracks : [], null, bundled: false);
    }

    /**
     * Bundled entries name a file in the audio directory by their handle;
     * site entries give a full `path`.
     *
     * @param  array<mixed>  $entries
     * @return Collection<string, Soundtrack>
     */
    protected function build(array $entries, ?string $directory, bool $bundled): Collection
    {
        $tracks = new Collection;

        foreach ($entries as $handle => $entry) {
            if (! is_string($handle) || ! is_array($entry)) {
                continue;
            }

            $path = $directory === null
                ? $this->string($entry['path'] ?? null)
                : $this->locate($directory, $handle);

            if ($path === null || ! is_file($path)) {
                Log::warning('[wrapped] Soundtrack file is missing and was left out.', [
                    'handle' => $handle,
                    'path' => $path,
                ]);

                continue;
            }

            $tracks->put($handle, new Soundtrack(
                handle: $handle,
                name: $this->string($entry['name'] ?? null) ?? ucwords(str_replace('-', ' ', $handle)),
                description: $this->string($entry['description'] ?? null) ?? '',
                path: $path,
                bundled: $bundled,
            ));
        }

        return $tracks;
    }

    /**
     * A bundled track is `<handle>.<any audio extension>`, so the manifest
     * does not have to repeat the extension and a format change is a file
     * swap rather than an edit.
     */
    protected function locate(string $directory, string $handle): ?string
    {
        foreach (['m4a', 'mp3', 'ogg', 'opus', 'wav', 'flac', 'aac', 'mp4'] as $extension) {
            $candidate = "{$directory}/{$handle}.{$extension}";

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
