<?php

namespace Bpmore\Wrapped;

use Bpmore\Wrapped\History\HistorySource;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\Sources\EntryDataHistorySource;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Bpmore\Wrapped\History\Sources\MtimeHistorySource;
use Bpmore\Wrapped\History\Sources\RevisionsHistorySource;
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
    }

    public function bootAddon()
    {
        //
    }
}
