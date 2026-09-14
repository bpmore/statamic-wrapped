<?php

namespace Bpmore\Wrapped\Export;

use Statamic\Assets\Asset;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\AssetContainer;

/**
 * The look, as the config file and the settings screen together decide it.
 *
 * The control panel screen wins once somebody has saved it, and the config
 * file answers until then. A screen that silently lost to a file would be
 * worse than no screen: somebody changes the background, saves, sees no
 * change, and stops trusting everything else.
 *
 * **Whether it has been saved is the question, not whether it has values.**
 * An unsaved settings record is not empty: it comes back carrying the
 * form's own defaults, so reading the values alone would let a field default
 * quietly beat a colour a developer wrote in the config file on purpose.
 * `raw()` rather than `all()` for the same reason: `all()` blends the
 * defaults over what was saved.
 */
class ThemeSettings
{
    /**
     * The addon's slug, which is what the settings file is named after and
     * what the repository looks a record up by — not the package name.
     */
    public const ADDON = 'wrapped';

    /**
     * @return array{background: mixed, accent: mixed, logo: mixed}
     */
    public static function values(): array
    {
        $config = (array) config('wrapped.theme', []);

        $saved = app(SettingsRepository::class)->find(self::ADDON)?->raw();

        if ($saved === null) {
            return $config + ['background' => null, 'accent' => null, 'logo' => null];
        }

        // Nulls only. An emptied colour is null and means "back to the config
        // file"; there is no false or [] here that could be a real answer.
        $saved = array_filter(
            ['background' => $saved['background'] ?? null, 'accent' => $saved['accent'] ?? null, 'logo' => $saved['logo'] ?? null],
            fn ($value) => $value !== null && $value !== '',
        );

        if (isset($saved['logo'])) {
            $saved['logo'] = static::asset($saved['logo']) ?? $saved['logo'];
        }

        return array_merge(['background' => null, 'accent' => null, 'logo' => null], $config, $saved);
    }

    /**
     * The asset the logo field points at, or null for a value that is not one.
     *
     * The field stores a path inside its container and not the container
     * itself, and the container the form offered was whichever the site had
     * when the form was drawn; so the path is looked for in every container,
     * first match wins. A `container::path` reference is read as such.
     */
    protected static function asset(mixed $value): ?Asset
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
