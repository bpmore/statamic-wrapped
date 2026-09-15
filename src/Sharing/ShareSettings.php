<?php

namespace Bpmore\Wrapped\Sharing;

use Bpmore\Wrapped\Export\ThemeSettings;
use Statamic\Contracts\Addons\SettingsRepository;

/**
 * Whether public links are allowed, as the config file and the settings
 * screen together decide it. Same rule as the look: the screen wins once it
 * has been saved, the config file answers until then.
 *
 * The toggle on the screen is drawn with the config file's value as its
 * default, so somebody who opens the screen to change the background and
 * saves does not switch sharing off by accident. See ServiceProvider.
 */
class ShareSettings
{
    public static function enabled(): bool
    {
        $saved = app(SettingsRepository::class)->find(ThemeSettings::ADDON)?->raw();

        if (is_array($saved) && array_key_exists('share_enabled', $saved) && $saved['share_enabled'] !== null) {
            return (bool) $saved['share_enabled'];
        }

        return (bool) config('wrapped.share.enabled', false);
    }
}
