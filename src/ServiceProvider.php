<?php

namespace Bpmore\Wrapped;

use Bpmore\Wrapped\History\HistorySource;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\Sources\EntryDataHistorySource;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Bpmore\Wrapped\History\Sources\MtimeHistorySource;
use Bpmore\Wrapped\History\Sources\RevisionsHistorySource;
use Bpmore\Wrapped\Stats\Cards\AssetsUploadedCard;
use Bpmore\Wrapped\Stats\Cards\BusiestMonthCard;
use Bpmore\Wrapped\Stats\Cards\BusiestTimeCard;
use Bpmore\Wrapped\Stats\Cards\BusiestWeekCard;
use Bpmore\Wrapped\Stats\Cards\EntriesPublishedCard;
use Bpmore\Wrapped\Stats\Cards\FastestTurnaroundCard;
use Bpmore\Wrapped\Stats\Cards\LongestEntryCard;
use Bpmore\Wrapped\Stats\Cards\LongestStreakCard;
use Bpmore\Wrapped\Stats\Cards\LongestUntouchedCard;
use Bpmore\Wrapped\Stats\Cards\MostRevisedCard;
use Bpmore\Wrapped\Stats\Cards\TopTaxonomyTermCard;
use Bpmore\Wrapped\Stats\Cards\TotalWordsCard;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
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
    }
}
