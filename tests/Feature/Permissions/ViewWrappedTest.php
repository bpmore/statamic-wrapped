<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Bpmore\Wrapped\Widgets\WrappedWidget;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/**
 * A logged-in user who is not a super, holding exactly these permissions.
 *
 * @param  list<string>  $permissions
 */
function viewerWith(array $permissions): void
{
    Role::make('viewer')->title('Viewer')->permissions($permissions)->save();

    test()->actingAs(User::make()->id('viewer')->email('viewer@example.com')->assignRole('viewer'));
}

function aWrappedWithPeople(): void
{
    Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => [
            'entries_published' => ['count' => 47, 'previous' => 21],
            'people' => ['people' => 3, 'entries' => 47, 'unattributed' => 0],
            'top_contributor' => ['author' => 'ada', 'count' => 20],
        ],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);
}

beforeEach(function () {
    $this->roles = sys_get_temp_dir().'/wrapped-roles-'.bin2hex(random_bytes(6));
    mkdir($this->roles, 0777, true);

    // Roles are file-backed; point them somewhere disposable.
    config(['statamic.users.repositories.file.paths.roles' => $this->roles.'/roles.yaml']);
    Stache::clear();

    app()->instance(ImageRenderer::class, new FakeImageRenderer);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->roles));
});

it('registers both permissions, the people one nested under the first', function () {
    $all = Permission::boot()->flattened()->map->value()->all();

    expect($all)->toContain(CardGate::VIEW)
        ->and($all)->toContain(CardGate::VIEW_PEOPLE);

    $view = Permission::get(CardGate::VIEW);

    expect(collect($view->children())->map->value()->all())->toContain(CardGate::VIEW_PEOPLE);
});

describe('someone with no wrapped permission at all', function () {
    beforeEach(fn () => viewerWith(['access cp']));

    // Statamic's control panel turns an authorization failure into a redirect
    // back to the dashboard with an error, rather than a bare 403 page. That
    // is what a user actually sees, so that is what is asserted.
    it('cannot open the screen', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.index'))
            ->assertRedirect(cp_route('index'))
            ->assertSessionHas('error', 'This action is unauthorized.');
    });

    it('cannot download an image', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.image.summary'))->assertRedirect(cp_route('index'));
        $this->get(cp_route('wrapped.image', 'entries_published'))->assertRedirect(cp_route('index'));
    });

    it('gets no dashboard widget, not even a locked door', function () {
        aWrappedWithPeople();
        CarbonImmutable::setTestNow('2026-12-05 09:00:00');

        expect(app(WrappedWidget::class)->component())->toBeNull();

        CarbonImmutable::setTestNow();
    });
});

describe('someone who may view wrapped but not people', function () {
    beforeEach(fn () => viewerWith(['access cp', CardGate::VIEW]));

    it('sees the screen without the people cards', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('snapshot.cards', 1)
                ->where('snapshot.cards.0.handle', 'entries_published'));
    });

    it('cannot download a people card, even by guessing the url', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.image', 'people'))->assertNotFound();
        $this->get(cp_route('wrapped.image', 'top_contributor'))->assertNotFound();
        $this->get(cp_route('wrapped.image', 'entries_published'))->assertOk();
    });

    it('gets a summary image without the people cards on it', function () {
        aWrappedWithPeople();

        $renderer = app(ImageRenderer::class);

        $this->get(cp_route('wrapped.image.summary'))->assertOk();

        expect($renderer->html)->toContain('Entries published')
            ->and($renderer->html)->not->toContain('The team')
            ->and($renderer->html)->not->toContain('Top contributor');
    });

    it('gets alt text that does not mention the people cards either', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('snapshot.summaryAlt', 'The 2026 Wrapped for Laravel. Entries published: You published 47 entries, against 21 the period before.'));
    });

    it('still gets the widget', function () {
        aWrappedWithPeople();

        expect(app(WrappedWidget::class)->component())->not->toBeNull();
    });
});

describe('someone who may view people stats', function () {
    beforeEach(fn () => viewerWith(['access cp', CardGate::VIEW, CardGate::VIEW_PEOPLE]));

    it('sees every card', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('snapshot.cards', 3));
    });

    it('can download a people card', function () {
        aWrappedWithPeople();

        $this->get(cp_route('wrapped.image', 'people'))->assertOk();
    });
});

it('lets a super user see everything without being granted anything', function () {
    $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper());
    aWrappedWithPeople();

    $this->get(cp_route('wrapped.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('snapshot.cards', 3));
});

it('shows nobody the people cards when nobody is logged in', function () {
    aWrappedWithPeople();

    $gate = app(CardGate::class);

    expect($gate->canView())->toBeFalse()
        ->and($gate->canViewPeople())->toBeFalse()
        ->and(array_keys($gate->visible(Snapshot::first()->stats)))->toBe(['entries_published']);
});

it('knows which cards are about people from the cards themselves', function () {
    $gate = app(CardGate::class);

    expect($gate->isAboutPeople('people'))->toBeTrue()
        ->and($gate->isAboutPeople('top_contributor'))->toBeTrue()
        ->and($gate->isAboutPeople('entries_published'))->toBeFalse()
        ->and($gate->isAboutPeople('nonsense'))->toBeFalse();
});
