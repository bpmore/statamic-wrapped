<?php

namespace Bpmore\Wrapped\Settings;

use Illuminate\Support\Facades\File;
use Statamic\Addons\FileSettingsRepository;
use Statamic\Contracts\Addons\Settings;
use Statamic\Facades\Addon;
use Statamic\Facades\YAML;

/**
 * Finds an addon's saved settings under the name they were saved with.
 *
 * Statamic writes an addon's settings to `resources/addons/<slug>.yaml` and
 * reads them back from `resources/addons/<package name>.yaml`. For an addon
 * whose slug is its package name's second half those are one file. This
 * addon's slug is `wrapped` and its package is `statamic-wrapped`, so the
 * settings screen saved to one file and read another: every save looked
 * lost the moment the page reloaded, though the images and the story used
 * it. The addon's own readers (`SavedSettings`) ask by slug and were never
 * fooled; the screen was.
 *
 * Statamic's own lookup is kept for any addon this cannot place. Seen in
 * statamic/cms 6.31 through 6.33 (`FileSettingsRepository::find()` against
 * `FileSettings::path()`). Reported as statamic/cms#15494; remove this once
 * Statamic reads by slug.
 */
class SettingsRepository extends FileSettingsRepository
{
    public function find(string $addon): ?Settings
    {
        $found = Addon::all()->first(fn ($candidate) => $candidate->id() === $addon);

        if ($found === null) {
            return parent::find($addon);
        }

        $path = resource_path("addons/{$found->slug()}.yaml");

        if (! File::exists($path)) {
            return null;
        }

        return $this->make($found, YAML::file($path)->parse());
    }
}
