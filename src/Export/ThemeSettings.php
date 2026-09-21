<?php

namespace Bpmore\Wrapped\Export;

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
 * quietly beat a color a developer wrote in the config file on purpose.
 * `raw()` rather than `all()` for the same reason: `all()` blends the
 * defaults over what was saved.
 */
class ThemeSettings
{
    /**
     * What the settings repository looks the addon's record up by. See
     * `SavedSettings::ADDON`.
     */
    public const ADDON = SavedSettings::ADDON;

    /**
     * @return array{background: mixed, accent: mixed, logo: mixed}
     */
    public static function values(): array
    {
        $config = (array) config('wrapped.theme', []);

        $saved = SavedSettings::raw();

        if ($saved === null) {
            return $config + ['background' => null, 'accent' => null, 'logo' => null];
        }

        // Nulls only. An emptied color is null and means "back to the config
        // file"; there is no false or [] here that could be a real answer.
        $saved = array_filter(
            ['background' => $saved['background'] ?? null, 'accent' => $saved['accent'] ?? null, 'logo' => $saved['logo'] ?? null],
            fn ($value) => $value !== null && $value !== '',
        );

        if (isset($saved['logo'])) {
            $saved['logo'] = SavedSettings::asset($saved['logo']) ?? $saved['logo'];
        }

        return array_merge(['background' => null, 'accent' => null, 'logo' => null], $config, $saved);
    }
}
