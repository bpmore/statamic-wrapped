<?php

use Bpmore\Wrapped\ServiceProvider;
use Statamic\CP\Navigation\Nav as RealNav;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\User;

beforeEach(function () {
    $this->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
});

/**
 * The Wrapped item, from a nav built for real.
 *
 * Statamic's addon test case swaps the Nav facade for a mock that answers
 * `build()` with nothing, after the service provider has already registered
 * its item on the real one. So: put a real Nav back, register the item again
 * the way boot does, and build.
 */
function wrappedNavItem(): ?NavItem
{
    Nav::swap(new RealNav);

    $bootNav = new ReflectionMethod(ServiceProvider::class, 'bootNav');
    $bootNav->invoke(app()->getProvider(ServiceProvider::class));

    foreach (Nav::build(preferences: false) as $section) {
        foreach ($section['items'] as $item) {
            if ($item->display() === 'Wrapped') {
                return $item;
            }
        }
    }

    return null;
}

it('is in the tools section of the control panel nav, with the other reports', function () {
    // A year in review is something you generate and share, not something
    // you write. Under Content it sat next to Collections as if it were.
    $item = wrappedNavItem();

    expect($item)->not->toBeNull()
        ->and($item->section())->toBe('Tools')
        ->and($item->url())->toBe(cp_route('wrapped.index'));
});

it('uses an icon the control panel actually ships', function () {
    // The nav sends the icon's *name* to the browser, and the control panel
    // draws nothing at all for a name it does not know. So the name has to be
    // one of the files in Statamic's own set, or the item sits there with a
    // blank where the icon goes, which is how `sparkles` shipped.
    $icon = wrappedNavItem()->icon();

    expect($icon)->toBe('gift-present-surprise')
        ->and(statamic_path("resources/svg/icons/{$icon}.svg"))->toBeFile();
});
