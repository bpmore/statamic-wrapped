<?php

namespace Bpmore\Wrapped;

use Bpmore\Wrapped\Console\Commands\GenerateWrapped;
use Bpmore\Wrapped\Export\ChromeRenderer;
use Bpmore\Wrapped\Export\FfmpegRenderer;
use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\SavedSettings;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\History\HistorySource;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\Sources\EntryDataHistorySource;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Bpmore\Wrapped\History\Sources\MtimeHistorySource;
use Bpmore\Wrapped\History\Sources\RevisionsHistorySource;
use Bpmore\Wrapped\Settings\SettingsRepository;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\Cards\AssetsUploadedCard;
use Bpmore\Wrapped\Stats\Cards\BusiestMonthCard;
use Bpmore\Wrapped\Stats\Cards\BusiestTimeCard;
use Bpmore\Wrapped\Stats\Cards\BusiestWeekCard;
use Bpmore\Wrapped\Stats\Cards\CollectionWentQuietCard;
use Bpmore\Wrapped\Stats\Cards\EntriesPublishedCard;
use Bpmore\Wrapped\Stats\Cards\FastestGrowingCollectionCard;
use Bpmore\Wrapped\Stats\Cards\FastestTurnaroundCard;
use Bpmore\Wrapped\Stats\Cards\LongestEntryCard;
use Bpmore\Wrapped\Stats\Cards\LongestStreakCard;
use Bpmore\Wrapped\Stats\Cards\LongestUntouchedCard;
use Bpmore\Wrapped\Stats\Cards\MostRevisedCard;
use Bpmore\Wrapped\Stats\Cards\PeopleCard;
use Bpmore\Wrapped\Stats\Cards\TopContributorCard;
use Bpmore\Wrapped\Stats\Cards\TopTaxonomyTermCard;
use Bpmore\Wrapped\Stats\Cards\TotalWordsCard;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Bpmore\Wrapped\Widgets\WrappedWidget;
use Illuminate\Console\Command;
use Statamic\Assets\AssetContainer;
use Statamic\Contracts\Addons\SettingsRepository as SettingsRepositoryContract;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\YAML;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Widgets\Widget;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * Control panel assets, built to `public/build` and published into the host
     * site's `public/vendor` under the addon slug.
     *
     * The plain list form rather than the array form on purpose: the parent
     * declares `list<string>`, and the array form exists only to move the build
     * directory, which is not worth fighting the declared type over.
     *
     * @var list<string>
     */
    protected $vite = ['resources/js/cp.js'];

    /** Views are `wrapped::`, matching the config and translation namespaces. */
    protected $viewNamespace = 'wrapped';

    /** @var list<class-string<Widget>> */
    protected $widgets = [
        WrappedWidget::class,
    ];

    /** @var list<class-string<Command>> */
    protected $commands = [
        GenerateWrapped::class,
    ];

    /**
     * Every history source, best first.
     *
     * Order is load-bearing. The resolver takes the highest confidence that is
     * available and keeps the first registered on a tie, so this list is what
     * decides between two sources of equal confidence — Revisions over entry
     * data, both being Partial.
     *
     * @var list<class-string<HistorySource>>
     */
    protected array $historySources = [
        LogbookHistorySource::class,
        RevisionsHistorySource::class,
        EntryDataHistorySource::class,
        MtimeHistorySource::class,
    ];

    /**
     * Every stat card. Order here is the order they are computed, not the order
     * they are shown — that is the CP screen's business.
     *
     * @var list<class-string<StatCard>>
     */
    protected array $statCards = [
        // Volume
        EntriesPublishedCard::class,
        TotalWordsCard::class,
        AssetsUploadedCard::class,

        // Time
        BusiestMonthCard::class,
        BusiestWeekCard::class,
        BusiestTimeCard::class,
        LongestStreakCard::class,

        // Superlatives
        LongestEntryCard::class,
        MostRevisedCard::class,
        FastestTurnaroundCard::class,
        TopTaxonomyTermCard::class,
        LongestUntouchedCard::class,

        // Collections
        FastestGrowingCollectionCard::class,
        CollectionWentQuietCard::class,

        // People. Team stats are on by default; the individual one is opt-in.
        // See config/wrapped.php for why.
        PeopleCard::class,
        TopContributorCard::class,
    ];

    public function register()
    {
        parent::register();

        // A singleton because deciding availability means reading the logbook
        // table, the revisions directory, or every entry on the site. That is
        // worth doing once per process, not once per stat card.
        $this->app->singleton(HistorySourceResolver::class, function ($app) {
            return new HistorySourceResolver(array_map(
                fn (string $source) => $app->make($source),
                $this->historySources,
            ));
        });

        $this->app->bind(ImageRenderer::class, ChromeRenderer::class);
        $this->app->bind(VideoRenderer::class, FfmpegRenderer::class);

        // A singleton: the manifest is read and every file checked once per
        // process, not once per lookup.
        $this->app->singleton(Soundtracks::class);

        // Read once per process; the logo may involve reading a file. The
        // settings screen lays over the config file — see ThemeSettings. A
        // long-lived worker (Octane, a queue) holds this until it restarts,
        // the same as any config change.
        $this->app->singleton(Theme::class, fn () => Theme::fromSettings());

        $this->app->singleton(StatCardRegistry::class, function ($app) {
            return new StatCardRegistry(array_map(
                fn (string $card) => $app->make($card),
                $this->statCards,
            ));
        });
    }

    public function bootAddon()
    {
        // Loaded rather than published, so `php artisan migrate` in the host
        // site picks the table up with no install step.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->bootPermissions();
        $this->bootNav();
        // After Statamic's own binding: it saves settings by slug and reads
        // them by package name, and ours differ. See the class.
        $this->app->bind(SettingsRepositoryContract::class, SettingsRepository::class);

        $this->bootSettings();
    }

    /**
     * The settings form, from resources/blueprints/settings.yaml with one
     * thing filled in: which asset container the logo and soundtrack fields
     * offer.
     *
     * Statamic registers that file on its own, but an assets field with no
     * container works only on a site with exactly one and throws on any
     * other, and the file cannot know the site. So the same file is
     * registered again here, through a closure that runs when the form is
     * drawn, with the site's first container named; a site with none gets a
     * plain text field for a path or URL instead.
     */
    protected function bootSettings(): void
    {
        $this->registerSettingsBlueprint(function () {
            $blueprint = YAML::file(__DIR__.'/../resources/blueprints/settings.yaml')->parse();

            // The same container SavedSettings reads a saved value back from.
            $container = SavedSettings::formContainer();

            foreach ($blueprint['tabs']['main']['sections'] as $i => &$section) {
                foreach ($section['fields'] as $j => &$field) {
                    if (($field['handle'] ?? null) === 'logo') {
                        $field['field'] = $this->logoField($field['field'], $container);
                    }

                    // Uploads need somewhere to go. A site with no container
                    // keeps the config file for its music, so the field, and
                    // the section that only holds it, are left out.
                    if (($field['handle'] ?? null) === 'soundtracks') {
                        if ($container === null) {
                            unset($section['fields'][$j]);
                        } else {
                            $field['field'] += ['container' => $container->handle()];
                        }
                    }

                    // The toggle starts where the config file is, so saving
                    // the screen for some other reason does not quietly turn
                    // sharing off on a site that switched it on in config.
                    if (($field['handle'] ?? null) === 'share_enabled') {
                        $field['field']['default'] = (bool) config('wrapped.share.enabled', false);
                    }
                }
            }

            unset($section, $field);

            $blueprint['tabs']['main']['sections'] = array_values(array_filter(
                $blueprint['tabs']['main']['sections'],
                fn (array $section) => $section['fields'] !== [],
            ));

            return $blueprint;
        });
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function logoField(array $field, ?AssetContainer $container): array
    {
        if ($container === null) {
            return [
                'type' => 'text',
                'display' => $field['display'],
                'instructions' => 'A picture on this site, as its address (starting with /), or a full web address. Shown on the opening and closing frames. Leave it empty to use the control panel logo, if the site has one.',
            ];
        }

        return $field + ['container' => $container->handle()];
    }

    /**
     * Four permissions, nested so no child can be granted without
     * `view wrapped`, which is meant to be granted broadly. The people one is
     * a separate decision — see config/wrapped.php for why — and so is
     * sharing, which shows the numbers to everyone. Building is work the
     * server does on demand, so that is its own decision too.
     */
    protected function bootPermissions(): void
    {
        Permission::extend(function () {
            Permission::group('wrapped', __('wrapped::permissions.group'), function () {
                Permission::register(CardGate::VIEW, function ($permission) {
                    $permission
                        ->label(__('wrapped::permissions.view'))
                        ->description(__('wrapped::permissions.view_desc'))
                        ->children([
                            Permission::make(CardGate::VIEW_PEOPLE)
                                ->label(__('wrapped::permissions.view_people'))
                                ->description(__('wrapped::permissions.view_people_desc')),
                            Permission::make(CardGate::SHARE)
                                ->label(__('wrapped::permissions.share'))
                                ->description(__('wrapped::permissions.share_desc')),
                            Permission::make(CardGate::GENERATE)
                                ->label(__('wrapped::permissions.generate'))
                                ->description(__('wrapped::permissions.generate_desc')),
                        ]);
                });
            });
        });
    }

    /**
     * Under Tools, with the other reports. A year in review is something you
     * generate and share, not something you write, and under Content it sat
     * next to Collections as if it were.
     *
     * A present, from Statamic's own icon set. The name has to be one the
     * control panel ships: an unknown name is not an error, it is a nav item
     * with a blank where the icon goes, which is what `sparkles` was.
     */
    protected function bootNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->tools('Wrapped')
                ->route('wrapped.index')
                ->icon('gift-present-surprise')
                ->can(CardGate::VIEW);
        });
    }
}
