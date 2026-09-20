<?php

namespace Bpmore\Wrapped\Export;

use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as Container;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\AssetContainer;

/**
 * What was saved on the settings screen, read the way the screen stores it.
 *
 * Two readers need this: the look (`ThemeSettings`) and the music
 * (`Soundtracks`). Both want the raw saved values, not the form's defaults
 * blended over them, and both need an assets field's value turned back into
 * an asset. See `ThemeSettings` for why "saved" is the question.
 */
class SavedSettings
{
    /**
     * The addon's slug, which is what the settings file is named after and
     * what the repository looks a record up by — not the package name.
     */
    public const ADDON = 'wrapped';

    /**
     * Exactly what was saved, or null when the screen has never been saved.
     *
     * @return array<string, mixed>|null
     */
    public static function raw(): ?array
    {
        $raw = app(SettingsRepository::class)->find(self::ADDON)?->raw();

        return is_array($raw) ? $raw : null;
    }

    /**
     * The asset an assets field's value points at, or null for a value that
     * is not one.
     *
     * The field stores a path inside its container and not the container
     * itself. The form always offers the same container (`formContainer()`),
     * so that one is asked first and is the answer on every ordinary site.
     * The others are tried after it, in handle order, for a value saved when
     * the site's containers were different. A `container::path` reference is
     * read as such.
     */
    public static function asset(mixed $value): ?Asset
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, '::')) {
            [$handle, $path] = explode('::', $value, 2);

            return AssetContainer::find($handle)?->asset($path);
        }

        foreach (static::containers() as $container) {
            if ($asset = $container->asset($value)) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * The container the settings form's assets fields offer: the site's
     * first, by handle, so it is the same one every time the form is drawn.
     * Null on a site with no containers at all.
     *
     * Typed as the class rather than the contract: the contract's docblock
     * says `asset()` always returns an asset, the class's (truthfully) says
     * it may return null, and callers rely on the null.
     */
    public static function formContainer(): ?Container
    {
        return static::containers()->first();
    }

    /**
     * Every container, the form's first. One ordering, used both when the
     * form is drawn and when a saved value is read back, so the two agree.
     *
     * @return Collection<int, Container>
     */
    protected static function containers(): Collection
    {
        return AssetContainer::all()->sortBy(fn (Container $container) => $container->handle())->values();
    }
}
