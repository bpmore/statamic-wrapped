<?php

namespace Bpmore\Wrapped\Export;

use Statamic\Assets\Asset;
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
     * itself, and the container the form offered was whichever the site had
     * when the form was drawn; so the path is looked for in every container,
     * first match wins. A `container::path` reference is read as such.
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

        foreach (AssetContainer::all() as $container) {
            if ($asset = $container->asset($value)) {
                return $asset;
            }
        }

        return null;
    }
}
