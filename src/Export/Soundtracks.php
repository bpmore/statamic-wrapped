<?php

namespace Bpmore\Wrapped\Export;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;

/**
 * Every soundtrack a video may have: the ones shipped with the addon, any the
 * site adds in config, and any uploaded on the settings screen.
 *
 * A track whose file is not where it says it is is left out and logged, never
 * offered. Offering it would put a broken choice in front of somebody and fail
 * their download after they made it.
 *
 * Site-supplied tracks come first in the list, config then uploads, on the
 * reasoning that a site which went to the trouble of adding its own music
 * wants to see it.
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
        return $this->all ??= $this->site()->merge($this->uploaded())->merge($this->bundled());
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
     * The files uploaded to the "Your soundtracks" field on the settings
     * screen. The field cannot refuse a non-audio file, so anything that is
     * not one is passed over here without a word: the instructions on the
     * field already say so. A file on a disk this server cannot read directly
     * is left out for now; see PROGRESS.md, Phase 9.
     *
     * The handle is a short hash of the asset id rather than the id itself,
     * because the id has slashes and a `::` in it and the handle goes in a
     * URL. It stays the same as long as the file stays where it is.
     *
     * @return Collection<string, Soundtrack>
     */
    protected function uploaded(): Collection
    {
        $tracks = new Collection;
        $saved = SavedSettings::raw()['soundtracks'] ?? null;

        if (! is_array($saved)) {
            return $tracks;
        }

        foreach ($saved as $value) {
            $asset = SavedSettings::asset($value);

            if ($asset === null || ! $asset->isAudio()) {
                continue;
            }

            $path = $asset->resolvedPath();

            if (! is_file($path)) {
                Log::warning('[wrapped] Uploaded soundtrack is not on this server\'s disk and was left out.', ['asset' => $asset->id()]);

                continue;
            }

            $handle = 'upload-'.substr(sha1((string) $asset->id()), 0, 12);

            $tracks->put($handle, new Soundtrack(
                handle: $handle,
                name: $this->trackName($asset),
                description: 'Uploaded to this site.',
                path: $path,
                bundled: false,
            ));
        }

        return $tracks;
    }

    /**
     * The asset's title if somebody gave it one, else its filename made
     * readable: `house-theme_v2.mp3` reads "House Theme V2".
     */
    protected function trackName(Asset $asset): string
    {
        $title = $asset->get('title');

        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        return ucwords(trim(preg_replace('/[-_]+/', ' ', (string) $asset->filename()) ?? ''));
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
