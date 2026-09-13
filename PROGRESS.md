# PROGRESS — Wrapped

**Spec:** `wrapped-build-spec.md` · **Package:** `bpmore/statamic-wrapped` · **Free**
**Rule:** do the next unfinished task only, then update this file, verify the build, and commit.

## Phase 1 — History source (do this first; everything depends on it)
- [x] Scaffold the addon package, tests, CI
- [x] `HistorySource` interface + resolver returning a confidence rating
- [x] `LogbookHistorySource` (Statamic Logbook, if installed)
- [x] `RevisionsHistorySource` (Pro only; note storage/statamic/revisions is git-ignored)
- [x] `EntryDataHistorySource` (date / updated_at / author)
- [x] `MtimeHistorySource` (last resort, low confidence)
- [x] Resolver picks best available and reports which; tests for each

## Phase 2 — Stats
- [x] `wrapped_snapshots` migration + model
- [x] One class per stat card, each declaring the history confidence it needs
- [x] Volume: entries published, total words, assets uploaded
- [x] Time: busiest month, busiest week, busiest day-of-week + hour, longest streak
- [x] Superlatives: longest entry, most-revised page, fastest turnaround, top taxonomy term, longest-untouched page
- [x] Collections: fastest growing, went quiet
- [x] People stats — team by default, individual leaderboard opt-in, never rank from the bottom
- [x] Cards omitted (not guessed) when confidence is too low
- [x] `php please wrapped:generate` with `--year` / `--quarter` / `--site` / `--force`

## Phase 3 — Output
- [x] CP screen — the canonical, accessible version
- [x] Confidence notice when history data is limited
- [x] Dashboard widget + December nudge
- [x] PNG card export via headless Chrome
- [x] Suggested alt text generated with each PNG
- [x] Confetti on first view, once
- [ ] `view wrapped` permission; people-stats behind a narrower gate

## Phase 4 — Ship
- [ ] README, screenshots, marketplace listing copy
- [ ] Test on a large fixture site and on a 3-month-old site (first-year framing)
- [ ] Tag 1.0 — target late November 2026

## Notes
<!-- Record surprises, decisions and blockers here. If a task is wrong or blocked, write why and stop. -->

### Task 1 — Scaffold the addon package, tests, CI (done)
- Namespace is `Bpmore\Wrapped\` over `src/`; tests are `Bpmore\Wrapped\Tests\` over `tests/`.
- Resolved versions: `statamic/cms` v5.74.4, `orchestra/testbench` v10 (Laravel 12), Pest 3.8, PHPStan 2 via larastan 3.
- `composer.lock` is git-ignored (correct for a library), so CI runs `composer update`, not `composer install`.
- PHPStan starts at level 4. Raise it later if it stays quiet; don't raise it mid-feature.
- **Deferred:** no `package.json` / Vite setup yet. No CP assets exist until Phase 3, and an empty build step
  would just be a thing that can break. Add it with the CP screen. `npm install && npm run build` in CLAUDE.md
  will not work until then — that is expected, not a broken environment.
- Verified: `vendor/bin/pest` 2 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.
- **Corrected after task 1:** scaffold resolved `statamic/cms` to v5.74.4. Bumped the constraint to `^6.0`; now on v6.31.0. All specs target Statamic 6.

### Task 2 — `HistorySource` interface + resolver (done)
- Everything lives in `src/History/`: `Confidence`, `HistoryEventType`, `HistoryEvent`, `HistorySource`,
  `ResolvedHistory`, `HistorySourceResolver`.
- `Confidence` and `HistoryEventType` were written but uncommitted before this task; they are covered by tests now.
- Sources flatten whatever they know into a stream of `HistoryEvent`, so the stat layer never branches on source.
  `author` and `site` are nullable — a source that does not know them must leave them null rather than guess.
- The interface is `events(from, to, ?site)` plus `earliestEvent(?site)`. `earliestEvent` exists for the
  first-year framing in SPEC.md §1, not as a convenience.
- Resolver takes highest `Confidence::rank()` among available sources; ties keep the first registered.
  So register best-first (logbook, revisions, entry_data, mtime) in task 6.
- `resolve()` returns null when nothing is available; `confidence()` reports `Low` in that case rather than
  throwing, because "we know almost nothing" is the honest answer, not an error.
- **Not done on purpose:** the resolver is not bound in the container yet. There are no real sources to register
  until tasks 3-6, and an empty binding would be a thing that can rot. Bind it in task 6.
- `tests/Fixtures/FakeHistorySource.php` lets the resolver be tested without a real source.
- Verified: `vendor/bin/pest` 14 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 3 — `LogbookHistorySource` (done)
- Sources live in `src/History/Sources/`. Read against Logbook v2.2.0 source, not from memory.
- Table is `logbook_audit_logs` on its **own** connection named `logbook`, built from
  `config('logbook.db.connection')`. Logbook's `DbConnectionResolver` does exactly that; we replicate the
  three lines rather than depend on their class, so this addon has no hard dependency on Logbook.
- Actions are `statamic.<subject>.<operation>`. Mapped operations: created/uploaded -> Created,
  published -> Published, updated/saved/replaced/unpublished -> Updated, deleted -> Deleted.
  Everything else (logins, two-factor, impersonation) is audit signal, not content history, and is dropped.
- `subject_type` is passed straight through as `itemType` ('entry', 'asset', 'term', 'collection'...).
  The source stays dumb; the stat layer filters.
- Site comes from `meta->site`, which Logbook only sets for site-aware subjects. Rows without it get
  `site = null` and are excluded when a site filter is given. That is the honest answer, not a bug.
- **Availability is decided by the table, not by `class_exists`.** A site that removed Logbook still has
  the history it recorded, and that history is no less true.
- **Verified on SQLite only:** the `where('meta->site', ...)` JSON filter. Laravel generates the MySQL and
  Postgres equivalents but no test covers them here.
- The test file rebuilds Logbook's schema by hand (it is not a dependency). If Logbook's schema drifts,
  `tests/Feature/History/LogbookHistorySourceTest.php` is the file that has to notice.
- **Deferred to Phase 4:** a `suggest` entry for `emran-alhaddad/statamic-logbook` in `composer.json`.
- Still not bound in the container. Registration is task 6, as decided in task 2.
- Verified: `vendor/bin/pest` 37 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 4 — `RevisionsHistorySource` (done)
- Read against the installed `vendor/statamic/cms` v6.31.0 source, not from docs.
- **Only entries are revisable.** `Statamic\Revisions\Revisable` is used by `Entry` and nothing else in
  Statamic 6, so `itemType` is always `entry`. No assets, terms or globals from this source, ever.
- Layout is `<revisions dir>/collections/<collection>/<site>/<entry id>/<timestamp>.yaml`. The filename is
  the unix timestamp, so the date window and the site are decided from the path before a file is opened.
  The site segment is the entry's locale, so site filtering here is exact rather than best-effort.
- Directory comes from `Statamic\Facades\Revision::directory()` rather than rebuilding it from the three
  config keys that feed it (`STATAMIC_REVISIONS_PATH`, `statamic.revisions.path`, the stache store).
- Actions: `publish` -> Published, `unpublish` -> Updated, `revision` (the default) -> Updated.
  `working.yaml` is the unsaved working copy — a draft, not something that happened — and is skipped by name.
- **There is no Created event from revisions.** Statamic records no creation action, and the oldest revision
  is not a creation date (revisions can be switched on years into a site's life). Phase 2 must get creation
  dates from entry data, not from here.
- **Decision — rated Partial, not High.** The trail itself is real (who, what, when), which by the enum's
  own wording is High. But revisions are opt-in *per collection*, and nothing on disk says which collections
  had them on. A site with revisions on the blog only would report "40 entries published" for a site that
  published 400 — confidently wrong, which SPEC.md §1 says is worse than saying less. Reverse this only if
  the source is taught to report its own collection coverage.
  Phase 2 note: set card requirements knowing revisions is Partial, or cards revisions *can* answer well
  (most-revised page) will be omitted for no good reason.
- `earliestEvent()` reads every file rather than taking the lowest filename, so an unparseable or
  unrecognised revision cannot set the date the whole Wrapped is framed around. Same contract as Logbook.
- Availability is decided by the files, not by `Statamic::pro()`. A site that dropped to Solo still has the
  revisions it wrote.
- PHPStan rejected the defensive `is_array()`/`is_string()` guards around `YAML::parse()` and
  `Revision::directory()` as always-true — the facade docblocks declare those return types. Removed rather
  than suppressed; the try/catch around the calls is the real guard.
- Verified: `vendor/bin/pest` 55 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 5 — `EntryDataHistorySource` (done)
- At most **two events per entry**: it went live (`date`), and it was last touched (`updated_at`).
  Every edit before the last one is gone. That is the whole shape of this source.
- **No Created events, ever.** Statamic stores no creation timestamp. An entry's `date` is when it is
  published or displayed, not when someone first typed into it. **No Deleted events either** — a deleted
  entry is not there to read. Phase 2 must not expect creation dates from any source we have so far.
- Reads `$entry->get('updated_at')` **directly, never `lastModified()`**. `TracksLastModified::lastModified()`
  falls back to file mtime, which would launder a Low-confidence signal into a Partial-confidence source.
  This is the single most important line in the class.
- `updated_by` is the author of the Updated event; the blueprint's `author` field is the author of the
  Published event. An `author` field can hold several users — the first is taken, the rest dropped rather
  than double-counted.
- **Decision — a future-dated entry is not counted as published.** Statamic's default
  `futureDateBehavior: public` means `status()` returns 'published' for an entry dated next month. Wrapped
  reports what has happened, so `date->isFuture()` is excluded. Drafts are excluded via `status()`;
  'expired' is included, because it did go out and its date has merely passed.
- `isAvailable()` is "does any entry actually yield an event", not "do entries exist". A site with no dated
  collections and no `updated_at` has entries but no history in them, and must fall through to mtime.
  It short-circuits on the first entry that yields something.
- **Known leak, documented in the class:** on a dated collection, `Entry::date()` falls back to
  last-modified and from there to mtime for an entry with no date of its own. Rare (the date normally comes
  from the filename) but it is the one way mtime can reach this source's Partial rating.
- PHPStan: `Statamic\Contracts\Entries\Entry` and `...\QueryBuilder` are both **empty interfaces**, so
  they give the analyser nothing. Entries are type-hinted as the concrete `Statamic\Entries\Entry` (which
  the eloquent driver extends), and the query builder is narrowed with a real `instanceof` check that
  returns an empty result for a driver we cannot drive — which makes the source unavailable and lets the
  resolver fall through. Not a suppression; it is the correct runtime behaviour.
- Tests build real entries in a temporary content directory by repointing the `collections`/`entries`
  stache stores and calling `Stache::clear()`. Multisite tests need `Site::setSites([...])` first or
  `slug()` fatals on a null site.
- Verified: `vendor/bin/pest` 72 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 6 — `MtimeHistorySource` (done)
- **Updated events only, and that is the point.** An mtime says a file was written; it does not say anything
  was published. Emitting a Published event from one is exactly the "busiest week that's actually the date
  of the last deploy" SPEC.md §1 warns about. No author either — a filesystem does not record who.
  One event per entry: only the most recent write survives.
- Reads the mtime with `is_file()` / `filemtime()` rather than Statamic's `fileLastModified()`, which
  **returns `Carbon::now()` when the file does not exist**. That would turn "no file" into "edited today"
  and invent a busiest day. Same class of trap as `lastModified()` in task 5 — Statamic's convenience
  accessors around dates fall back rather than fail, and this addon must not.
- An entry with no file behind it (eloquent driver, or a file moved out from under the Stache) yields
  nothing, which correctly makes this source unavailable on an eloquent-driver site.
- **Refactor:** the entry-walking query moved to `src/History/Sources/Concerns/IteratesEntries.php`, shared
  with `EntryDataHistorySource` — second occurrence, so it was time. The `instanceof QueryBuilder` narrowing
  is the part that mattered to share; it is a subtle invariant that must not drift between the two.
  The small `string()` caster is still duplicated across all four sources. Left alone deliberately: eight
  obvious lines, and extracting it would touch every source for no real gain.
- **Test harness gotcha, cost real time:** `Collection::sites()` silently collapses to `[Site::default()]`
  unless `config('statamic.system.multisite')` is true — `Site::setSites([...])` alone is not enough, and
  `Site::hasMultiple()` returning true is misleading. Both multisite test helpers now set the config first.
- Verified: `vendor/bin/pest` 84 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 7 — Resolver picks best available and reports which (done) — **Phase 1 complete**
- `ServiceProvider::register()` binds `HistorySourceResolver` as a **singleton** with all four sources.
  Singleton because deciding availability means reading the logbook table, the revisions directory, or
  every entry on the site — worth doing once per process, not once per stat card.
- **Registration order is load-bearing**, and lives in `ServiceProvider::$historySources`:
  logbook, revisions, entry_data, mtime. Ties are broken by this order, which is what puts Revisions ahead
  of entry data when both are Partial. Do not reorder without re-reading task 4's confidence note.
- `tests/Feature/History/ResolvesBestSourceTest.php` is the end-to-end proof: real fixtures for all four
  sources, each test adding a better one, asserting the resolver switches to it. It also reads events back
  through whatever it resolved, so the interface is exercised as the stat layer will use it.
- Resolution ladder confirmed by test: nothing -> null/Low · entry files only -> mtime/Low ·
  dated entries -> entry_data/Partial · revision files -> revisions/Partial · logbook table -> logbook/High.
- "Reports which" is `ResolvedHistory::handle()` (for the snapshot's `history_source` column),
  `name()` (for the UI line) and `confidence` (for the `confidence` column and card gating).
  Showing it on screen is Phase 3.

### Phase 1 summary — what the stat layer can rely on
- **No source produces a Created event.** Logbook does when the site has it, but the other three cannot,
  and the resolver may hand you any of them. Do not design a card around creation dates.
- **Only Logbook produces Deleted events.**
- **Only Logbook and Revisions have a real edit trail.** entry_data gives at most two events per entry;
  mtime gives exactly one and never an author.
- **Only Logbook covers assets and taxonomy terms.** The other three are entries-only.
- Confidence available in practice: High (logbook), Partial (revisions, entry_data), Low (mtime).

### Task 8 — `wrapped_snapshots` migration + model (done) — first of phase 2
- `database/migrations/2026_09_08_000000_create_wrapped_snapshots_table.php`, **loaded** by the service
  provider rather than published, so `php artisan migrate` in the host site picks it up with no install step.
- Columns exactly as SPEC.md §4: site, period, period_key, history_source, confidence, stats(json),
  generated_at, generated_by. Default connection; no separate database.
- **Unique on (site, period, period_key).** Regenerating replaces the row rather than adding a second one,
  which is what `--force` will rely on. A duplicate insert throws `UniqueConstraintViolationException`.
- `history_source` and `confidence` are **stored on the row, not derived on read.** They describe the run
  that made this snapshot. A site that installs Logbook in March does not retroactively improve the Wrapped
  it generated in January, and the UI must not claim otherwise. `Snapshot::supports()` mirrors
  `ResolvedHistory::supports()` so a card can be gated from a stored snapshot as well as a live resolve.
- New `Snapshots\Period` enum (year|quarter) with `key(int $year, ?int $quarter)` — the **only** place the
  `'2026'` / `'2026-Q3'` format is written, because it is a stored value that must stay stable across
  releases. Throws on a quarterly key with no usable quarter rather than writing a malformed key.
- `$timestamps = false`: `generated_at` is the only time the row has, and regenerating updates it, so a
  created_at/updated_at pair would say nothing new.
- Test env note: testbench's default connection is sqlite `:memory:`, so `RefreshDatabase` runs the
  migration with no extra setup.
- Verified: `vendor/bin/pest` 107 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 9 — Stat card shape: interface, context, registry (done)
- `Stats\StatCard`: `handle()` (the stable key in the snapshot's stats JSON — stored data, do not rename
  after release), `requires(): Confidence`, `compute(StatContext): ?array`.
- **`compute()` returning null means "computed, and there is no story here"** — a year with no entries, a
  site with one collection. It does **not** mean "not allowed to compute". Confidence gating happens before
  `compute()` is called, and that is a later task ("Cards omitted when confidence is too low").
- `Stats\StatContext` carries the resolved history, the window and the site, and **reads each window from
  the source exactly once**. A dozen cards each asking for the same window would read the logbook table a
  dozen times; on a large site that is the difference between a job and a timeout. `events($from, $to)`
  lets a card ask for another window (last year's number alongside this year's) and caches that separately.
- The context deliberately does **not** expose entries, assets or terms. Several cards will need entry
  content (word counts, taxonomy terms) — let each fetch what it needs rather than guess at a shared API
  before any card exists.
- `Stats\StatCardRegistry` **throws on a duplicate handle.** Two cards sharing one would silently overwrite
  each other in the stats JSON, which is a data bug that would never surface as an error.
- Bound as a singleton in `ServiceProvider::$statCards`, currently empty — unlike the history sources in
  task 2, which were left unbound. The difference: cards land in the very next task, and the list property
  is the extension point the next task needs.
- `tests/Fixtures/FakeHistorySource` gained a public `$reads` counter so the context's caching is proved
  rather than assumed. `tests/Fixtures/FakeStatCard` stands in for a real card.
- Gotcha: a Pest helper named `event()` collides with Laravel's global helper and fatals. Named `anEvent()`.
- Verified: `vendor/bin/pest` 119 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 10 — Volume cards (done)
Three cards in `src/Stats/Cards/`, registered in `ServiceProvider::$statCards`.

**`entries_published`** — requires Partial.
- Counts **distinct entries, not events.** An entry unpublished and put back out was published once as far
  as a reader is concerned; counting events would double it.
- `previous` is **null, not 0**, when the source has no history before the window. A site three months old
  did not publish nothing last year, it did not exist. `StatContext::hasHistoryBefore()` decides this.
  A genuine quiet year still reports 0.
- Returns null (no card) when the count is 0. "You published 0 entries" is a scolding — SPEC.md §3.

**`total_words`** — requires Partial.
- **Counted from entries as they stand today, not as they stood when published.** An entry published in
  January and rewritten in June counts at its June length. No source can give us the January one.
  Entries since deleted cannot be counted at all, which is why the card reports `entries` alongside
  `words` — that number is not always the published count.
- Word counting lives in `Stats\Support\WordCounter` so the heuristic can be argued with in one place.
  `readability-core` does not exist (checked on packagist), so this is written here; SPEC.md §8 says replace
  it rather than keep both if that package ever ships.
- Two rules keep it honest: only fieldtypes the blueprint says are prose are counted
  (text/textarea/markdown/bard/replicator/grid/group — so a select value or taxonomy handle is not
  "words someone wrote"), and inside those, structural keys are skipped. **The structural key list exists
  because of Bard**: every ProseMirror node carries `type`, and counting "paragraph" as a word would
  badly inflate a long article.

**`assets_uploaded`** — requires **High**, so it only appears on a site running Logbook.
- Nothing else records an upload: revisions and entry data are entries-only, and an asset's file mtime is
  the date of the last deploy on most sites. Counting uploads from a directory listing would give a
  confident number that is simply wrong, so the card is left off instead. This is the clearest example so
  far of "omit rather than guess", and it gives sites a real reason to install Logbook.
- Reports `count`, `bytes` and `weighed`. An upload since deleted still happened, but no longer has a size,
  so `weighed` says how many of the count the byte total actually covers.

**Also in this task:** `StatContext` gained `period`, `previousWindow()` and `hasHistoryBefore()` — the
"last year's number alongside" requirement needs them, and shifting by the period (not by the window's
length) keeps leap years and uneven quarters landing on the right dates.

**Test-harness notes:** shared card helpers now live in `tests/Pest.php`, because Pest files share one
global namespace and a helper defined in two files is a fatal redeclaration. Names are prefixed
(`cardContext`, `wasPublished`, `wrappedEvent`) — `context()` and `event()` are already Laravel globals and
both fataled before being renamed. A Pest dataset entry of `[]` spreads to zero arguments; wrap every case
in an array.

- Verified: `vendor/bin/pest` 156 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 11 — Time cards (done)
Four cards, all requiring Partial, all built on publications rather than edits.

**`busiest_month`** — stores `year` + `month` as **numbers, never a name**. A stored "September" would be
wrong on a French site and impossible to change later; the CP screen translates it. Ties go to the earlier
month (countBy fills in oldest-first order and `sortDesc` is stable), which is deterministic and documented.

**`busiest_week`** — keyed on the **Monday the week starts**, not the ISO number alone. ISO week 1 can fall
in either of two calendar years, so a bare "week 1" is ambiguous in stored data. The number is reported too
because that is what people say.

**`busiest_time`** — the delightful one from SPEC.md §2.
- **The important decision: events landing exactly on 00:00:00 are ignored.** A dated collection stores a
  date with no time, so `Entry::date()` returns the start of the day. Counting those would report that
  every site on earth publishes at midnight — a card that is confidently, uniformly wrong. Treating exact
  midnight as "no time recorded" costs the occasional genuine midnight publication and saves the card.
  `from` reports how many publications actually carried a time, so the UI need not imply more.
- Day and hour are counted **independently, not as a pair**. Over a year one day-and-hour combination is
  thin enough to be noise, while "Tuesdays" and "the afternoon" are each well supported — and it still
  reads as one sentence.
- Day is ISO (1 = Monday), hour is 0-23 local.

**`longest_streak`** — consecutive calendar days with something published. Three entries on one day is a
busy day, not a three-day streak. **Only shown from two days upward**: a streak of one is every site that
has ever published anything.

**Refactor:** `Stats\Concerns\ReadsPublishedEntries` now owns the "entries only, Published only" filter and
`firstPublications()`, which keeps the **earliest** event per entry so a republished entry counts once and
lands in the month it first appeared. `EntriesPublishedCard` and `TotalWordsCard` moved onto it.

**Process note:** one test in this task was written with expectations I was unsure of and marked `skip`.
That is dead weight, not caution — it was rewritten as a case that actually discriminates (without
deduplication September wins with three events; with it, March wins with two) and the skip removed.
No skipped tests in this suite.

- Verified: `vendor/bin/pest` 177 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 12 — Superlative cards (done)
Five cards. New `Stats\Concerns\DescribesEntries` handles looking an entry up and coping with it having
been deleted since — a superlative names one page, so every card here needs that.

**`longest_entry`** — Partial. Same caveat as total words: measured as the entry reads today.

**`most_revised`** — Partial, and **this resolves the task 4 question about revisions being rated Partial.**
The card guards on the *data*, not the rating: entry data and mtime can only ever emit one Updated event
per entry, so anything below two edits returns nothing. The card therefore disappears on sources that
cannot count edits and works properly on Revisions (Partial) as well as Logbook (High). Requiring High
would have dropped it on the sites best able to answer it. **Publishing is not counted as editing** — it is
a status change with cards of its own. Falls through to the runner-up if the winner has been deleted.

**`fastest_turnaround`** — **High**, so Logbook only. It is the sole source that records a creation.
Only entries both created *and* published inside the period count: something started last December and
published in January had a turnaround, but not one this year can claim. Stored in **seconds** — "42 minutes"
would not translate. A publication timestamped before its own creation is a clock problem, not a fast
writer, and is dropped.

**`top_taxonomy_term`** — Partial. Read through the blueprint: fields of type `terms`, whose `taxonomies`
config says which taxonomy a bare slug belongs to. Statamic stores **bare slugs for a single-taxonomy field
and `taxonomy::slug` for a multi-taxonomy one**, so both shapes are handled; a bare slug on a
multi-taxonomy field is ambiguous and is dropped rather than attributed to the wrong taxonomy.
Stores the handle and slug, **never the term's title** — titles are localised and editable.
Needs at least two uses: one is not what a period was "mostly about".

**`longest_untouched`** — Partial. Warm, never scolding (SPEC.md §3): this is the page nobody has *needed*
to change. Only counts if untouched for the whole period. **Known limit, documented in the class:** an
entry the source has never recorded is invisible here, and is very likely the most untouched page of all.
A site that installed Logbook last year cannot see the page last edited in 2019. "We have no record of it"
is not "nobody touched it", so it is left out rather than claimed.

**Pattern worth keeping:** three of these cards guard on a minimum (2 edits, 2 term uses, untouched for the
whole period) rather than on confidence alone. A superlative that is barely a superlative is not a card.

- Verified: `vendor/bin/pest` 203 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 13 — Collections cards (done)
Both read the collection from `HistoryEvent->meta['collection']`, which **all four sources set**, so these
work whichever source was resolved. Events arriving without one are dropped: an uncounted entry beats a
collection named "".

**`fastest_growing_collection`** — Partial. **"Most" is a count, not a ratio.** A ratio would let a
collection going from one entry to three beat one going from fifty to a hundred and twenty, which is not
what anyone means by "grew the most". `previous` rides along so the screen can say "up from 31", and is
null (not 0) for a site younger than one period. Needs **at least two collections** to have published —
on a single-collection site the answer was never in doubt, and that is not a card.

**`collection_went_quiet`** — Partial. Busy last period, completely silent this one. Two guards keep it
from being noise: it must have gone fully quiet rather than merely slowed down, and it must have published
**at least twice** before — one entry last year and none this year is a collection that barely started, not
one that went quiet. Picks the collection with the most to lose. A site with no history before the period
cannot produce this card, which is correct: nothing can go quiet before it has spoken.
Wording carries the warmth (SPEC.md §3) and belongs to the CP screen; the card supplies only the fact.

**Shared:** `ReadsPublishedEntries::publicationsByCollection()` — counts per collection, biggest first.

- Verified: `vendor/bin/pest` 217 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 14 — People stats (done)
The socially loaded one. SPEC.md §3 is the spec here, not a nice-to-have.

**New `config/wrapped.php`**, which is where the reasoning lives because a site operator reads the config,
not the source. `wrapped.people.enabled` (team stats, **on**) and `wrapped.people.individuals` (stats that
name one person, **off**). Both have env overrides.

**`people`** — team card, on by default, requires Partial. "42 entries from 7 people." Names nobody, so it
cannot single anyone out. **Needs two people**: on a one-person site a "team" card identifies that person
by arithmetic. Reports `unattributed` separately, because the attributable count is not always the
published total.

**`top_contributor`** — off until the site opts in. Returns **exactly one person and their count — never a
ranked list**. A full leaderboard invites reading down it, which is the whole problem. Needs two
contributors, since with one there is nothing to be top of. **Stores the user id, never the name**, so a
snapshot does not freeze somebody's name into the database.

**Never ranks from the bottom.** There is no "least active author" card and there will not be one. There is
a test asserting no registered card handle contains `least`, `quietest`, `leaderboard` or `ranking` — a
guard on the product decision rather than on an implementation detail, so a future addition trips it.

`Stats\Concerns\CountsPeople` holds the two permission checks and `byAuthor()`. Events with no author are
dropped rather than gathered into an "unknown" bucket: an unknown bucket can *win*, and "your top
contributor is nobody" is not a card.

**Packaging note:** added `extra.statamic.slug: wrapped` to composer.json. Statamic derives the addon config
filename and config key from the slug (`Addons\Manifest` reads `extra.statamic.slug`), so without it the
config would have to live at `config/statamic-wrapped.php` and be read as
`config('statamic-wrapped.people.enabled')`. Verified by test: `config('wrapped.people.enabled')` is true.

- Verified: `vendor/bin/pest` 232 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 15 — Cards omitted when confidence is too low (done)
`Stats\CardRunner` is where SPEC.md §1's rule is actually enforced. A card whose `requires()` exceeds the
resolved confidence is **never computed at all** — not computed and hidden, not computed with a caveat.
There is no code path where a card's numbers exist without the history to back them. A test asserts the
card's `compute()` was never called, not merely that its output was dropped.

`Stats\StatsResult` carries four things, and the split matters:
- `stats` — handle => data.
- `omitted` — never computed, the history is not good enough. **This is what the Phase 3 "based on limited
  history data" notice hangs on** (`wasLimited()`).
- `empty` — computed, nothing worth showing. A quiet year, not a limitation. These look identical on a
  finished Wrapped, and a reader has to be able to tell which happened.
- `failed` — threw. Logged at warning with the card handle, and the rest of the Wrapped carries on. One
  awkward entry must not cost someone their whole year, but this is recorded and logged rather than
  swallowed so it surfaces as a bug report instead of a mysteriously missing card.

**Product consequence, verified by test and worth knowing before Phase 3: on an mtime-only site every
single card is omitted and the Wrapped is empty.** Every card this addon ships requires Partial or better,
because nothing honest can be said from file timestamps that a deploy rewrites. That is the spec's position
("nearly worthless on a deployed site"), but the CP screen will need to handle a completely empty Wrapped
gracefully — most likely as "we can't tell you much yet, here is why" rather than a blank page.
Flag for the CP screen task.

Confidence ladder confirmed end to end over the real registry:
- Low → everything omitted.
- Partial → only `assets_uploaded` and `fastest_turnaround` omitted (the two that need Logbook).
- High → nothing omitted, nothing failed.

- Verified: `vendor/bin/pest` 243 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 16 — `wrapped:generate` (done) — **Phase 2 complete**
`src/Console/Commands/GenerateWrapped.php`, registered via `ServiceProvider::$commands`.
`--year` (defaults to the current year, which is what a 1 December run wants), `--quarter` (1-4),
`--site` (defaults to **every** site, one snapshot each — SPEC.md §8), `--force`.

- Invalid input exits `INVALID` and writes nothing; no readable history exits `FAILURE`. A scheduled run
  that quietly succeeds while producing nothing would be worse than a non-zero exit.
- `--force` uses `updateOrCreate` against the unique key, so a rebuild **replaces** the row rather than
  adding a second one. Without it, an existing snapshot is left completely alone — `generated_at` is
  asserted unchanged, not just the row count.
- `Period` gained `window()` alongside `key()`, so the period owns both its stored key format and its
  bounds. `assertQuarter()` throws rather than defaulting: a quarterly period with no quarter would write a
  malformed key or silently build the wrong three months.
- `generated_by` uses `getAuthIdentifier()`, not `id()` — Statamic's user *contract* does not declare
  `id()`, it inherits the identifier from Laravel's `Authenticatable`. Null for an unattended run.
- **An mtime-grade site still gets a row written**, with empty stats. The CP needs it to explain why.

**Real bug found and fixed while writing this task's tests.** `LogbookHistorySource` filtered by
`meta->site`, and `Statamic\Assets\Asset` **has no `site()` method** — asset containers are global in
Statamic, not per site — so Logbook never records a site for an upload. Every asset event was being dropped
by the site filter, which would have left `assets_uploaded` permanently empty on every site, silently.
Fixed: a row that names a site belongs to that site, a row that names none belongs to everybody.
`FakeHistorySource` now mirrors that so fixtures cannot drift from real behaviour again.
This only surfaced because the command is the first thing that passes a concrete site handle all the way
through — worth remembering that the per-card tests could not have caught it.

**Not done, and not in this task's line:**
- SPEC.md §4 also wants the work in a **queued job** and a **1 December schedule**. The command does the
  work synchronously. Neither is in the task list; add them deliberately rather than as a side effect.
- **The addon is not installed in the host site.** `~/Herd/statamic-dev/composer.json` has the path
  repository for `../statamic-wrapped` but never requires the package, so `php please wrapped:generate`
  does not exist there yet. Left alone: it is a change to another repo and outside this task. The command is
  fully covered in-repo through Testbench, which is the addon's real test environment.

- Verified: `vendor/bin/pest` 259 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 17 — CP screen (done) — first of phase 3
**Statamic 6's control panel is Inertia + Vue. There are no Blade CP views any more** — `resources/views/cp`
does not exist in the package. An addon screen is: a Vue page component registered client-side, and
`Inertia::render()` server-side with a matching name.

The wiring, confirmed against the Statamic 6 docs rather than guessed:
- `package.json` depends on `@statamic/cms` via `file:./vendor/statamic/cms/resources/dist-package`.
- `vite.config.js` uses `laravel-vite-plugin` plus `@statamic/cms/vite-plugin`. That plugin **externalises
  Vue to the copy the CP already loaded** — bundling a second one would give the page a different
  reactivity system to the CP around it.
- `resources/js/cp.js` does `Statamic.booting(() => Statamic.$inertia.register('wrapped::Wrapped', Wrapped))`.
  **The name must match `Inertia::render('wrapped::Wrapped')` exactly.**
- `ServiceProvider::$vite` (plain list form — the parent declares `list<string>`, and the array form only
  exists to move the build directory, not worth fighting the type for), so the build goes to `public/build`.
  Assets publish under the addon slug: `php artisan vendor:publish --tag=wrapped`.
- `routes/cp.php` is auto-detected. Nav item via `Nav::extend()`.

**The presenter is the design decision.** Cards store numbers and handles — a month is `7`, a term is a
slug, a contributor is a user id. `Stats\CardPresenter` turns those back into a heading and a sentence at
display time, so the wording lives in a normal Laravel translation file (`resources/lang/en/cards.php`) and
the screen is plain text a screen reader reads straight through. A card with no wording is **left out
rather than rendered as its own translation key**.

Two states the screen has to handle, both tested:
- No snapshot at all — "run `php please wrapped:generate`". Not an error.
- A snapshot whose cards were all omitted — the mtime case flagged in task 15. It explains what it was
  built from instead of showing a blank page. The dedicated confidence notice is the next task.

**Two mistakes worth remembering:**
1. The `$vite` property edit **silently did nothing** — my scripted replace matched a docblock Pint had
   already rewritten (`\Illuminate\Console\Command` -> `Command`). Nothing failed; the property simply was
   not there, and assets did not publish. Caught only by checking `ServiceProvider::$publishGroups` in the
   host site. **Assert on every scripted edit.**
2. `loadTranslationsFrom()` in `bootAddon()` was redundant — Statamic's own `bootTranslations()` already
   picks up `resources/lang` and registers the `wrapped-translations` publish group. Removed.

**Verified in the host site**, not just in tests: route registers as `statamic.cp.wrapped.index`, and
`vendor:publish --tag=wrapped` copies the built manifest into `public/vendor/statamic-wrapped/build`.

- Verified: `vendor/bin/pest` 277 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean.

### Task 18 — Confidence notice (done)
`Stats\ConfidenceNotice`. A Wrapped now **never appears without saying what it was built from**, and the
wording escalates only as far as the truth requires — SPEC.md §1.

Three levels, in `resources/lang/en/messages.php`:
- **complete** — "Built from Statamic Logbook." A quiet line, nothing more.
- **limited** — names the source and says how many cards were left out *rather than guessed at*.
  Pluralised through `trans_choice`, so it reads "One card", never "1 cards".
- **empty** — "There is not enough history here to say anything yet... and most deploys rewrite those."
  This is the mtime case flagged back in task 15, now with a real explanation instead of a blank page.

**The omitted count is derived, not stored.** The snapshot records the confidence it had and every card
declares the confidence it needs, so the answer falls out of the two. That avoids a schema change, stays
correct as cards are added, and answers the more useful question anyway: what would this site get if its
history improved.

**Sources are named, not handled.** "mtime" means nothing to a reader; "file modification times" does.
An unrecognised handle falls back to "an unknown source" rather than leaking the handle.

**The suggestion is offered once and never nags.** Logbook is suggested only when the history is limited
*and* the site is not already running Logbook. A complete Wrapped gets no upsell.

On the screen it is a `<p>`, not an alert. Thin history is not an error and not the reader's fault.

**Props moved:** `confidence` and `historySource` are now under `snapshot.history` alongside the notice.
Two task 17 tests asserted the old paths and were updated.

- Verified: `vendor/bin/pest` 292 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished to the host site.

### Task 19 — Dashboard widget and December nudge (done)
`src/Widgets/WrappedWidget.php`, registered via `ServiceProvider::$widgets`. A Statamic widget returns
`VueComponent::render('name', $props)`, so the Vue side is an **ordinary component** registered with
`Statamic.$components.register()` — not `$inertia.register()`, which is only for pages.

- **Shows nothing at all when there is no Wrapped.** `component()` returns null rather than advertising
  itself on a dashboard with nothing to show.
- **It only calls itself a nudge in December, for the year that is ending** — a Year-period snapshot whose
  key is the current year, in month 12. A quarter never nudges, and neither does last year's Wrapped.
  The rest of the year it is a quiet link to something that already exists.
- The headline leads with `entries_published` when present, because that is the fact people came for,
  falling back to the first card and then to the **confidence notice**. A thin site gets "there is not
  enough history here" rather than an empty line.

**Dismissal is `localStorage`, keyed by period.** A per-browser convenience does not deserve a table, an
endpoint and per-user state to get wrong, and keying by period means next year's Wrapped nudges again on
its own. Reads and writes are wrapped in try/catch — private windows and blocked site data throw. Only the
December nudge is dismissible; the quiet link is not in anybody's way.

**Not done here:** the widget does not check a `view wrapped` permission yet — that is the last task in
this phase and will need to cover the screen, the widget and the nav item together.

- Verified: `vendor/bin/pest` 302 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished to the host site.

### Task 20 — PNG card export (done)
`Export\ImageRenderer` (interface), `Export\ChromeRenderer`, `Export\CardImages`, two Blade templates,
and two CP download routes.

**Chrome is driven directly — no Puppeteer, no Browsershot.** Those would put Node and a
several-hundred-megabyte browser download between a free addon and a picture of a number, and card layout
is only HTML and CSS. `--headless=new --screenshot` against a `file://` URL is the whole mechanism.
The binary comes from `wrapped.images.chrome`, else the usual install locations are checked.
**A missing browser hides the download; it never fails a request** — the screen is the real version.

**Rendered at `--force-device-scale-factor=2`**, so a 1200x630 card comes out 2400x1260 and stays sharp
when a social platform re-encodes it. Summary is 1200x1500.

**No hosted URL, ever.** The routes return `Content-Disposition: attachment`. Nothing is written to a
public directory and no link is minted: a URL leaking a team's internal publishing stats is a support
problem and a privacy problem for a free addon — SPEC.md §5.

**The templates are deliberately self-contained**: inline CSS, system fonts, no network. The renderer loads
them from `file://` with no internet, so anything fetched would simply be missing from the picture. Tests
assert the HTML contains no `http://`, no `https://` and no `<script`, and that card text is escaped rather
than becoming markup.

**Verified for real, not just mocked.** One test runs actual Chrome and asserts PNG bytes (skipped when no
browser is present). Rendered against the seeded dev site and inspected the output image: it reads
"WHEN YOU PUBLISH / You publish most on Tuesday, and most often around 2 pm. / 2026 · default".

- Verified: `vendor/bin/pest` 318 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished · both routes registered in the host site.

### Task 21 — Suggested alt text (done)
`Export\AltText`, with the wording in `resources/lang/en/messages.php` under `alt.*`.

**The alt text is the card's own words**, plus where they came from:
"When you publish: You publish most on Tuesday, and most often around 2 pm. From the 2026 Wrapped for
default." A card image is text made of pixels, and there is nothing in it a sighted reader gets that a
described reader should not.

**Deliberately not "an image of a card showing...".** Alt text carries the information, not a description
of the medium — telling somebody they are looking at a picture tells them what they are missing instead of
telling them the thing. A test asserts the output contains none of "image of", "screenshot" or "picture of".

The summary's alt text lists **every** card. Long, because the image is long: a truncated description of a
summary is worse than none.

**On screen it is written out, not hidden behind the button.** A `<details>` under each download shows the
suggested text so it can be read and edited before it goes anywhere, with a Copy button beside it. The
clipboard call is wrapped — access can be refused — and the text stays selectable either way.

Fixed while testing: an empty Wrapped produced a trailing space where the cards should have been.

- Verified: `vendor/bin/pest` 324 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · checked against the seeded dev site.

### Task 22 — Confetti on first view, once (done)
`resources/js/celebrate.js`, using `canvas-confetti` (1.9.4, bundled by Vite — no runtime dependency for
the site). Two short bursts from the lower corners, then done: a moment, not a screensaver.

The decision is one plain function, `shouldCelebrate(periodKey, cardCount)`, with every reason it might say
no in one place:
- **Nothing to celebrate.** An empty Wrapped gets an explanation, not a party.
- **Already seen.** `localStorage`, keyed by period — the same per-browser nicety as the widget dismissal —
  so next year's Wrapped celebrates again on its own.
- **Reduced motion.** `prefers-reduced-motion: reduce` means never. A burst of moving particles is exactly
  what that setting asks not to see, and the Wrapped is complete without it.
- **No storage.** If localStorage throws (private window, blocked site data) it declines. No way to
  remember means it would fire on every visit, which is the wrong side of that coin.

"Seen" is marked **before** the burst fires, so an error mid-animation still counts as seen rather than
replaying on every reload.

**Testing honesty:** this repo has no JavaScript test runner and adding one for a single function is not
worth the toolchain. The seven guard cases were checked with an ad hoc node script stubbing `window`
(all pass) and the result recorded here; they are not part of `vendor/bin/pest`. Visual confirmation is
manual: load `/cp/wrapped` on the seeded dev site, once.

The bundle grew from 6.9 kB to 18.3 kB gzipped-7.4 kB. Acceptable for a control panel page loaded on demand.

- Verified: `vendor/bin/pest` 324 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished.
